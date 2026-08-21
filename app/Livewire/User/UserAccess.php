<?php

namespace App\Livewire\User;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\HasAbilityMatrix;
use App\Models\Membership;
use App\Models\ModuleAccess;
use App\Models\PermissionAudit;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * One person's own company-wide access (F0).
 *
 * Until this screen existed, everything a person could do away from a project
 * came from their role and nothing else. Giving one supervisor the cost codes
 * — or taking "delete expenses" off one bookkeeper — meant inventing a role for
 * them, and installs end up with roles called "Manager (no deletes)". That is
 * P6, and P13/P19 (the ceiling) and P34 (the money switch) are the same gap
 * seen from three other angles.
 *
 * **The screen is two-state and the storage is three-state, on purpose.**
 * Whoever is editing sets each ability the way they want it; what is saved is
 * only where that differs from the role. So a difference is a deliberate
 * exception with a row of its own, and everything else keeps following the
 * role — including when the role changes later.
 *
 * Three people this does not apply to, and the screen says so rather than
 * lying by omission:
 *
 *   - an **administrator** is allowed everything before any of this is read;
 *   - a **guest** holds no company-wide ability at all, whatever is ticked;
 *   - somebody **confined to their projects** answers from their memberships on
 *     anything belonging to a project, so exceptions here reach only the
 *     company-wide screens.
 */
class UserAccess extends Component
{
    use AuthorizesAbility;
    use HasAbilityMatrix;

    public User $user;

    /** 'edit' sets their access; 'effective' explains it. */
    public string $tab = 'edit';

    /** The company-wide money switch, which is not an area. */
    public bool $seeMoney = false;

    /** In the app's currency; empty means "follow the role". */
    public string $approvalLimit = '';

    public function mount(User $user): void
    {
        $this->authorizeAbility('access.view');

        $this->user = $user->load('role');

        $this->loadFromUser();
    }

    protected function loadFromUser(): void
    {
        $effective = $this->effectiveAbilities();

        $this->loadGrants($effective);
        $this->seeMoney = in_array(AbilityCatalog::financeAbility(), $effective, true);

        $this->approvalLimit = $this->user->approval_limit === null
            ? ''
            : $this->toCurrency($this->user->approval_limit);

        $this->matrixSearch = '';
        $this->resetValidation();
    }

    /*
    |---------------------------------------------------------------------------
    | What they may do today
    |---------------------------------------------------------------------------
    */

    /**
     * The role's list with this person's exceptions laid over it — the same
     * answer the resolver gives, worked out here so the screen can show it.
     *
     * @return array<int, string>
     */
    protected function effectiveAbilities(): array
    {
        $overrides = $this->user->abilityOverrideMap();
        $fromRole = $this->roleAbilities();

        $effective = array_values(array_filter($fromRole, fn ($a) => $overrides[$a] ?? true));

        foreach ($overrides as $ability => $granted) {
            if ($granted && ! in_array($ability, $effective, true)) {
                $effective[] = $ability;
            }
        }

        return $effective;
    }

    /** @return array<int, string> */
    protected function roleAbilities(): array
    {
        return $this->user->role?->abilities() ?? [];
    }

    /** Every ability this screen can express an opinion about. */
    protected function universe(): array
    {
        return array_merge(AbilityCatalog::abilities(), [AbilityCatalog::financeAbility()]);
    }

    /*
    |---------------------------------------------------------------------------
    | What the screen needs to know
    |---------------------------------------------------------------------------
    */

    #[Computed]
    public function matrix(): array
    {
        return $this->buildMatrix();
    }

    #[Computed]
    public function totalAbilities(): int
    {
        return count(AbilityCatalog::abilities());
    }

    public function financeAbility(): string
    {
        return AbilityCatalog::financeAbility();
    }

    /** True when this person is an administrator, for whom none of this binds. */
    #[Computed]
    public function isAdministrator(): bool
    {
        return (bool) $this->user->is_admin;
    }

    /** How many abilities are ticked right now, for the footer count. */
    #[Computed]
    public function allowedCount(): int
    {
        return count($this->grantedAbilities()) + ($this->seeMoney ? 1 : 0);
    }

    /** The exceptions as they stand in the editor, ability => granted. */
    protected function pendingOverrides(): array
    {
        $wanted = $this->grantedAbilities();

        if ($this->seeMoney) {
            $wanted[] = AbilityCatalog::financeAbility();
        }

        $fromRole = $this->roleAbilities();
        $overrides = [];

        foreach ($this->universe() as $ability) {
            $shouldHold = in_array($ability, $wanted, true);

            if ($shouldHold !== in_array($ability, $fromRole, true)) {
                $overrides[$ability] = $shouldHold;
            }
        }

        return $overrides;
    }

    /** How far this person's access has been moved from their role's. */
    #[Computed]
    public function exceptions(): array
    {
        $overrides = $this->pendingOverrides();

        return [
            'added' => count(array_filter($overrides)),
            'removed' => count($overrides) - count(array_filter($overrides)),
            'total' => count($overrides),
        ];
    }

    /** The role's ceiling, in the app's currency, for the placeholder. */
    #[Computed]
    public function roleApprovalLimit(): ?string
    {
        $limit = $this->user->role?->approval_limit;

        return $limit === null ? null : $this->toCurrency($limit);
    }

    protected function toCurrency(int $cents): string
    {
        return rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');
    }

    /*
    |---------------------------------------------------------------------------
    | Saving
    |---------------------------------------------------------------------------
    */

    /** Put everything back to whatever the role says, in one click. */
    public function followRole(): void
    {
        $this->authorizeAbility('access.manage');

        $this->loadGrants($this->roleAbilities());
        $this->seeMoney = in_array(AbilityCatalog::financeAbility(), $this->roleAbilities(), true);
        $this->approvalLimit = '';
    }

    public function save(): void
    {
        $this->authorizeAbility('access.manage');

        // Nothing here reaches an administrator, so saving it would write rows
        // that do nothing and read as a control that works.
        abort_if($this->isAdministrator, 403, __('You do not have permission to do that.'));

        $this->validate([
            'approvalLimit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [], [
            'approvalLimit' => __('Approval limit'),
        ]);

        $before = $this->user->abilityOverrideMap();
        $overrides = $this->pendingOverrides();

        DB::transaction(function () use ($overrides) {
            $this->user->syncAbilityOverrides($overrides);

            $this->user->update([
                'approval_limit' => trim($this->approvalLimit) === ''
                    ? null
                    : (int) round(((float) $this->approvalLimit) * 100),
            ]);
        });

        $this->recordAudit($before, $overrides);

        app(PermissionResolver::class)->flush();

        $this->user->refresh()->load('role');
        $this->loadFromUser();

        unset($this->exceptions, $this->roleApprovalLimit, $this->allowedCount);

        session()->flash('message', __('Access updated.'));
    }

    /**
     * One audit line per save, saying what actually moved — the same shape the
     * role editor writes, so the two read as one trail.
     */
    protected function recordAudit(array $before, array $after): void
    {
        if ($before == $after) {
            return;
        }

        $added = count(array_filter($after));
        $removed = count($after) - $added;

        PermissionAudit::record(
            subjectType: 'user',
            subjectId: $this->user->id,
            action: 'abilities-changed',
            summary: __(':name — :added always allowed, :removed never allowed', [
                'name' => $this->user->name,
                'added' => $added,
                'removed' => $removed,
            ]),
            subjectUserId: $this->user->id,
            before: ['overrides' => $before],
            after: ['overrides' => $after],
        );
    }

    /*
    |---------------------------------------------------------------------------
    | The inspector: what they can actually do, and why
    |---------------------------------------------------------------------------
    |
    | F1's second deliverable. When a customer rings up to ask why Maria cannot
    | see the budget, the answer is in four places — her role, her own
    | exceptions, her project memberships, and whether the module is even
    | switched on. This is those four places in one list.
    |
    | Every answer is asked of PermissionResolver rather than worked out here,
    | so the inspector cannot disagree with the application it is explaining.
    */

    /**
     * Every ability, with the answer and where it came from.
     *
     * @return array<int, array>
     */
    #[Computed]
    public function effective(): array
    {
        $resolver = app(PermissionResolver::class);
        $overrides = $this->user->abilityOverrideMap();
        $fromRole = $this->roleAbilities();
        $sections = [];

        foreach (AbilityCatalog::areas() as $key => $area) {
            if (! $this->matchesMatrixSearch($key, $area)) {
                continue;
            }

            $rows = [];

            foreach ($area['actions'] as $action) {
                $ability = $action['ability'];

                $rows[] = [
                    'name' => __($action['name']),
                    'ability' => $ability,
                    'allowed' => $resolver->allows($this->user, $ability),
                    'source' => $this->sourceOf($ability, $overrides, $fromRole),
                ];
            }

            $sections[] = [
                'key' => $key,
                'name' => __($area['name']),
                'scoped' => in_array('project', $area['levels'], true)
                    || in_array('job_site', $area['levels'], true),
                'rows' => $rows,
            ];
        }

        return $sections;
    }

    /** Why the answer is what it is, in one phrase. */
    protected function sourceOf(string $ability, array $overrides, array $fromRole): string
    {
        if ($this->user->is_admin) {
            return __('Administrator');
        }

        if (! AbilityCatalog::isSwept($ability)) {
            return __('Not enforced yet');
        }

        $module = AbilityCatalog::moduleFor($ability);

        if ($module !== null && ! ModuleAccess::isEnabled($module)) {
            return __('Module switched off');
        }

        if (array_key_exists($ability, $overrides)) {
            return $overrides[$ability] ? __('Always allowed — set here') : __('Never allowed — set here');
        }

        if ($this->user->is_guest) {
            return __('Guest — company-wide access only from a project');
        }

        return in_array($ability, $fromRole, true)
            ? __('From their role')
            : __('Not on their role');
    }

    /**
     * The projects and job sites they have been added to, and what each gives.
     *
     * @return array<int, array>
     */
    #[Computed]
    public function scopes(): array
    {
        return Membership::query()
            ->with('scopeable')
            ->where('user_id', $this->user->id)
            ->get()
            ->map(fn ($membership) => [
                'label' => $membership->scopeable?->scopeLabel() ?? __('Unknown'),
                'level' => $membership->scopeable?->scopeLevel() ?? '',
                'status' => $membership->status,
                'title' => $membership->title,
                'money' => (bool) $membership->can_see_money,
                'limit' => $membership->approval_limit,
                'count' => count($membership->abilities()),
            ])
            ->all();
    }

    public function render()
    {
        return view('livewire.user.user-access')->layout('components.layouts.app');
    }
}
