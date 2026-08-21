<?php

namespace App\Livewire\Access;

use App\Enums\AccessScope;
use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\HasAbilityMatrix;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Settings → Roles & Access.
 *
 * The company-wide half of the permission model: what a role may do anywhere.
 * Project and job-site access — templates, memberships, invitations — is the
 * Team tab, built in M1.
 *
 * Every toggle on this screen writes an ability row and a line in the audit
 * trail. Nothing here invents permissions: the matrix is config/permissions.php
 * rendered, so a role can only ever be given something the application actually
 * checks for.
 */
class AccessIndex extends Component
{
    use AuthorizesAbility;
    use HasAbilityMatrix;

    public string $activeTab = 'roles';

    /** The role being edited, or null while the list is showing. */
    public ?int $editingRoleId = null;

    public bool $showRoleModal = false;

    public string $name = '';

    public string $description = '';

    /** The company-wide money switch, which is not an area. */
    public bool $seeMoney = false;

    /**
     * The most anybody with this role may approve away from a project, in the
     * app's currency. Empty means no ceiling — which is what every install has
     * today, and what every seeded role keeps.
     */
    public string $approvalLimit = '';

    /**
     * Which projects and job sites people with this role can reach at all:
     * 'company' (every one) or 'assigned' (only the ones they are added to).
     */
    public string $accessScope = 'company';

    public function mount(): void
    {
        $this->authorizeAbility('access.view');
    }

    /*
    |---------------------------------------------------------------------------
    | The list
    |---------------------------------------------------------------------------
    */

    #[Computed]
    public function roles()
    {
        // Built-in roles first, in their own order, then anything the
        // customer has created. Sorted here rather than in SQL: the list is a
        // handful of rows and FIELD() is MySQL-only.
        return Role::query()
            ->withCount(['users', 'abilityRows'])
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Role $role) => [
                array_search($role->name, Role::SYSTEM, true) === false ? 1 : 0,
                array_search($role->name, Role::SYSTEM, true) ?: 0,
                $role->name,
            ])
            ->values();
    }

    /** Every ability that exists, for the "12 of 137" counts. */
    #[Computed]
    public function totalAbilities(): int
    {
        return count(AbilityCatalog::abilities()) + 1;   // + finance.view_amounts
    }

    /** The areas each role touches, for the chips on the list. */
    #[Computed]
    public function areasByRole(): array
    {
        $areas = [];

        foreach (DB::table('role_abilities')->get(['role_id', 'ability']) as $row) {
            $area = AbilityCatalog::split($row->ability)[0];
            $areas[$row->role_id][$area] = AbilityCatalog::area($area)['name'] ?? $area;
        }

        return array_map(fn ($names) => array_values($names), $areas);
    }

    /*
    |---------------------------------------------------------------------------
    | The matrix
    |---------------------------------------------------------------------------
    */

    /** A role is company-wide, so its matrix is the whole catalogue. */
    #[Computed]
    public function matrix(): array
    {
        return $this->buildMatrix();
    }

    /** The typed ceiling in cents, or null for "no ceiling". */
    protected function approvalLimitInCents(): ?int
    {
        return trim($this->approvalLimit) === ''
            ? null
            : (int) round(((float) $this->approvalLimit) * 100);
    }

    public function financeAbility(): string
    {
        return AbilityCatalog::financeAbility();
    }

    /*
    |---------------------------------------------------------------------------
    | Editing
    |---------------------------------------------------------------------------
    */

    public function newRole(): void
    {
        $this->authorizeAbility('access.manage');

        $this->reset(['editingRoleId', 'name', 'description', 'granted', 'seeMoney', 'matrixSearch', 'approvalLimit']);
        $this->accessScope = AccessScope::COMPANY->value;
        $this->resetValidation();
        $this->showRoleModal = true;

        $this->dispatch('open-modal', 'role-modal');
    }

    public function editRole(int $roleId): void
    {
        $this->authorizeAbility('access.view');

        $role = Role::with('abilityRows')->findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->description = (string) $role->description;
        $abilities = $role->abilities();

        $this->accessScope = ($role->access_scope ?? AccessScope::COMPANY)->value;
        $this->seeMoney = in_array(AbilityCatalog::financeAbility(), $abilities, true);
        $this->approvalLimit = $role->approval_limit === null
            ? ''
            : rtrim(rtrim(number_format($role->approval_limit / 100, 2, '.', ''), '0'), '.');
        $this->loadGrants($abilities);
        $this->matrixSearch = '';

        $this->resetValidation();
        $this->showRoleModal = true;

        $this->dispatch('open-modal', 'role-modal');
    }

    public function closeRoleModal(): void
    {
        $this->dispatch('close-modal', 'role-modal');

        $this->showRoleModal = false;
        $this->reset(['editingRoleId', 'name', 'description', 'granted', 'seeMoney', 'matrixSearch', 'accessScope', 'approvalLimit']);
    }

    /** The role in the editor, or null when creating one. */
    #[Computed]
    public function editingRole(): ?Role
    {
        return $this->editingRoleId ? Role::find($this->editingRoleId) : null;
    }

    /** Administrators are allowed everything; there is nothing to edit. */
    #[Computed]
    public function editingAdmin(): bool
    {
        return (bool) $this->editingRole?->isAdmin();
    }

    #[Computed]
    public function grantedCount(): int
    {
        return count($this->grantedAbilities()) + ($this->seeMoney ? 1 : 0);
    }

    public function save(): void
    {
        $this->authorizeAbility('access.manage');

        $role = $this->editingRole;

        // The three seeded roles are compared by name in code that has not had
        // its permission pass yet, so their names are fixed until F2.
        $this->validate([
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[\pL\pN _-]+$/u',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'accessScope' => ['required', 'in:company,assigned'],
            'approvalLimit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [], [
            'name' => __('Name'),
            'description' => __('Description'),
            'accessScope' => __('Which projects they can see'),
            'approvalLimit' => __('Approval limit'),
        ]);

        if ($role?->isSystem() && $this->name !== $role->name) {
            $this->addError('name', __('The built-in roles cannot be renamed.'));

            return;
        }

        $abilities = $this->grantedAbilities();

        // finance.view_amounts is not an area, so it is carried separately
        // rather than being dropped by the catalogue filter.
        if ($this->seeMoney) {
            $abilities[] = $this->financeAbility();
        }

        DB::transaction(function () use ($role, $abilities) {
            $before = $role?->abilities() ?? [];

            $attributes = [
                'name' => $this->name,
                'description' => $this->description ?: null,
                'access_scope' => $this->accessScope,
                'approval_limit' => $this->approvalLimitInCents(),
            ];

            // Administrators are never confined: they are allowed everything
            // before memberships are consulted, so 'assigned' would be a lie.
            if ($role?->isAdmin()) {
                $attributes['access_scope'] = AccessScope::COMPANY->value;
            }

            $role = $role
                ? tap($role)->update($attributes)
                : Role::create($attributes);

            if (! $role->isAdmin()) {
                $role->syncAbilities($abilities);
            }

            $this->recordAudit($role, $before, $role->isAdmin() ? $before : $abilities);
        });

        app(PermissionResolver::class)->flush();

        unset($this->roles, $this->areasByRole);

        session()->flash('message', __('Role saved.'));

        $this->closeRoleModal();
    }

    /**
     * One audit line per save, saying what actually moved.
     */
    protected function recordAudit(Role $role, array $before, array $after): void
    {
        sort($before);
        sort($after);

        $addedCount = count(array_diff($after, $before));
        $removedCount = count(array_diff($before, $after));

        $summary = $before === []
            ? __('Role created with :count ability(ies)', ['count' => count($after)])
            : __(':added granted, :removed revoked', ['added' => $addedCount, 'removed' => $removedCount]);

        PermissionAudit::record(
            subjectType: 'role',
            subjectId: $role->id,
            action: $before === [] ? 'created' : 'updated',
            summary: $role->name.' — '.$summary,
            before: ['abilities' => $before],
            after: ['abilities' => $after],
        );
    }

    public function deleteRole(int $roleId): void
    {
        $this->authorizeAbility('access.manage');

        $role = Role::withCount('users')->findOrFail($roleId);

        if ($role->isSystem()) {
            session()->flash('error', __('The built-in roles cannot be deleted.'));

            return;
        }

        if ($role->users_count > 0) {
            session()->flash('error', __('Move the :count person(s) holding this role to another one first.', [
                'count' => $role->users_count,
            ]));

            return;
        }

        PermissionAudit::record(
            subjectType: 'role',
            subjectId: $role->id,
            action: 'deleted',
            summary: __('Role deleted: :name', ['name' => $role->name]),
            before: ['abilities' => $role->abilities()],
        );

        $role->delete();

        app(PermissionResolver::class)->flush();

        unset($this->roles, $this->areasByRole);

        session()->flash('message', __('Role deleted.'));
    }

    /*
    |---------------------------------------------------------------------------
    | The trail
    |---------------------------------------------------------------------------
    */

    #[Computed]
    public function audits()
    {
        return PermissionAudit::with('actor')
            ->where('subject_type', 'role')
            ->latest('created_at')
            ->limit(25)
            ->get();
    }

    /**
     * The people a scope change on the role being edited would affect: those
     * who follow the role rather than carrying their own setting.
     */
    #[Computed]
    public function followersOfEditedRole(): int
    {
        if (! $this->editingRoleId) {
            return 0;
        }

        return \App\Models\User::where('role_id', $this->editingRoleId)
            ->whereNull('access_scope')
            ->where('is_guest', false)
            ->count();
    }

    /** How much of the module is actually enforced yet. */
    #[Computed]
    public function rolloutProgress(): array
    {
        $areas = AbilityCatalog::areas();
        $unswept = AbilityCatalog::unsweptAreas();

        return [
            'total' => count($areas),
            'enforced' => count($areas) - count($unswept),
            'pending' => count($unswept),
        ];
    }

    public function render()
    {
        return view('livewire.access.access-index')->layout('components.layouts.app');
    }
}
