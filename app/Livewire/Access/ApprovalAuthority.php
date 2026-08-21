<?php

namespace App\Livewire\Access;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Membership;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Who can approve what" — the report F1 owes (docs/permissions-module-plan.md §9).
 *
 * Approval authority is the one thing in this module a customer's finance
 * director has to be able to check without reading five screens: **who, in this
 * company, can approve, award, convert or pay — and up to how much?**
 *
 * The answer is scattered by design. It comes from a role, or from a person's
 * own exceptions (F0); the ceiling comes from the role, or the person, or a
 * membership on one project. This screen is the one place it is gathered up.
 *
 * Everything on it is asked of `PermissionResolver`, never of the tables
 * underneath. A report that read the rows itself could drift from what the
 * application actually does, and the whole point of it is that it does not.
 */
class ApprovalAuthority extends Component
{
    use AuthorizesAbility;

    public string $search = '';

    /** Show only the people who can approve something. */
    public bool $onlyAuthorised = true;

    public function mount(): void
    {
        $this->authorizeAbility('access.view');
    }

    /**
     * The actions that answer to a ceiling: everything the catalogue marks
     * `limited`. Approve a requisition, award a quotation, convert it, approve
     * a change order, pay a contract.
     *
     * @return array<int, array{ability: string, name: string, area: string}>
     */
    #[Computed]
    public function actions(): array
    {
        $actions = [];

        foreach (AbilityCatalog::areas() as $area) {
            foreach ($area['actions'] as $action) {
                if ($action['limited'] ?? false) {
                    $actions[] = [
                        'ability' => $action['ability'],
                        'name' => __($action['name']),
                        'area' => __($area['name']),
                    ];
                }
            }
        }

        return $actions;
    }

    /**
     * One row per person: what they may approve, their ceiling, and where each
     * of those came from.
     */
    #[Computed]
    public function people(): array
    {
        $resolver = app(PermissionResolver::class);
        $actions = $this->actions;

        $users = User::query()
            ->with('role')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($users as $user) {
            $held = [];

            foreach ($actions as $action) {
                if ($resolver->allows($user, $action['ability'])) {
                    $held[] = $action;
                }
            }

            if ($this->onlyAuthorised && $held === []) {
                continue;
            }

            $rows[] = [
                'user' => $user,
                'actions' => $held,
                'limit' => $resolver->approvalLimit($user),
                'limit_source' => $this->limitSource($user),
                'exceptions' => $user->abilityOverrides()->count(),
                'projects' => $this->projectCeilings($user),
            ];
        }

        return $rows;
    }

    /** Where this person's company-wide ceiling comes from, in words. */
    protected function limitSource(User $user): string
    {
        if ($user->is_admin) {
            return __('Administrator — never capped');
        }

        if ($user->approval_limit !== null) {
            return __('Set on this person');
        }

        if ($user->role?->approval_limit !== null) {
            return __('From their role, :role', ['role' => ucfirst((string) $user->role->name)]);
        }

        return __('No limit set');
    }

    /**
     * The projects and job sites where a membership sets a different ceiling.
     * Only the differences: a membership that sets none follows the person.
     *
     * @return array<int, array{label: string, limit: int}>
     */
    protected function projectCeilings(User $user): array
    {
        return Membership::query()
            ->with('scopeable')
            ->where('user_id', $user->id)
            ->active()
            ->whereNotNull('approval_limit')
            ->get()
            ->map(fn ($membership) => [
                'label' => $membership->scopeable?->scopeLabel() ?? __('Unknown'),
                'limit' => (int) $membership->approval_limit,
            ])
            ->all();
    }

    /** Nobody at all can approve anything — worth saying rather than showing a blank. */
    #[Computed]
    public function totalAuthorised(): int
    {
        return count(array_filter($this->people, fn ($row) => $row['actions'] !== []));
    }

    public function render()
    {
        return view('livewire.access.approval-authority');
    }
}
