<?php

namespace App\Livewire\Concerns;

use App\Enums\MembershipStatus;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AbilityCatalog;
use App\Services\InvitationService;
use App\Services\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/**
 * The Team tab, shared by the project and the job-site level.
 *
 * A membership is one person on one project or one job site, carrying their own
 * ability list. A project membership cascades to every job site under it; a
 * job-site membership overrides the project's for that site. Both halves of
 * that rule are visible on the screen rather than only true in the resolver:
 * the job-site tab lists the people who reach it through the project, and says
 * so.
 *
 * The host component supplies contextScope() — the Project or JobSite this tab
 * belongs to.
 */
trait ManagesTeam
{
    use AuthorizesAbility;
    use HasAbilityMatrix;

    public bool $showMemberModal = false;

    public ?int $editingMembershipId = null;

    /** The person being added; empty while editing an existing membership. */
    public string $userId = '';

    public string $memberSearch = '';

    public ?int $templateId = null;

    public string $title = '';

    public bool $canSeeMoney = true;

    public string $approvalLimit = '';

    /**
     * Adding somebody who already has a login, or inviting somebody who does
     * not. The same modal does both — the abilities are chosen the same way
     * either way.
     */
    public bool $inviting = false;

    public string $inviteEmail = '';

    public string $inviteName = '';

    public bool $inviteAsGuest = false;

    abstract protected function contextScope(): Project|JobSite;

    protected function scopeLevel(): string
    {
        return $this->contextScope() instanceof JobSite ? 'job_site' : 'project';
    }

    protected function isJobSiteLevel(): bool
    {
        return $this->scopeLevel() === 'job_site';
    }

    /*
    |---------------------------------------------------------------------------
    | The list
    |---------------------------------------------------------------------------
    */

    /** People with a membership on this exact project or job site. */
    #[Computed]
    public function members()
    {
        $scope = $this->contextScope();

        return Membership::query()
            ->with(['user', 'template', 'invitedBy', 'abilityRows'])
            ->where('scopeable_type', $scope::class)
            ->where('scopeable_id', $scope->getKey())
            ->get()
            ->sortBy(fn (Membership $m) => mb_strtolower((string) $m->user?->name))
            ->values();
    }

    /**
     * On a job site: the people who reach it through the project, and are not
     * overridden here. The cascade, made visible.
     */
    #[Computed]
    public function inheritedMembers()
    {
        if (! $this->isJobSiteLevel()) {
            return collect();
        }

        $overridden = $this->members->pluck('user_id')->all();

        return Membership::query()
            ->with(['user', 'template', 'abilityRows'])
            ->where('scopeable_type', Project::class)
            ->where('scopeable_id', $this->contextScope()->project_id)
            ->whereNotIn('user_id', $overridden)
            ->get()
            ->sortBy(fn (Membership $m) => mb_strtolower((string) $m->user?->name))
            ->values();
    }

    /** Invitations sent for this project or job site and not yet taken up. */
    #[Computed]
    public function pendingInvitations()
    {
        $scope = $this->contextScope();

        return UserInvitation::with('invitedBy')
            ->pending()
            ->get()
            ->filter(function (UserInvitation $invitation) use ($scope) {
                foreach ($invitation->payload ?? [] as $entry) {
                    if (($entry['scopeable_type'] ?? null) === $scope::class
                        && (int) ($entry['scopeable_id'] ?? 0) === (int) $scope->getKey()) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /** Staff who could be added here and are not on the list yet. */
    #[Computed]
    public function addableUsers()
    {
        $taken = $this->members->pluck('user_id')->all();

        return User::query()
            ->whereNotIn('id', $taken)
            ->where('is_guest', false)
            ->when($this->memberSearch !== '', function ($query) {
                $term = '%'.$this->memberSearch.'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email', 'role_id']);
    }

    /**
     * The templates offered here. Guest templates are kept apart from staff
     * ones: offering "Client (read only)" while adding an engineer, or the
     * Project Manager preset while inviting a client, is how mistakes happen.
     */
    #[Computed]
    public function templates()
    {
        return PermissionTemplate::query()
            ->with('abilityRows')
            ->forLevel($this->scopeLevel())
            ->when($this->inviteAsGuest, fn ($query) => $query->forGuests(), fn ($query) => $query->forStaff())
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    /** The areas a membership covers, for the chips on each row. */
    public function areaChips(Membership $membership): array
    {
        $names = [];

        foreach ($membership->abilities() as $ability) {
            $area = AbilityCatalog::split($ability)[0];
            $names[$area] = __(AbilityCatalog::area($area)['name'] ?? $area);
        }

        return array_values($names);
    }

    /*
    |---------------------------------------------------------------------------
    | Adding and editing
    |---------------------------------------------------------------------------
    */

    public function addMember(): void
    {
        $this->authorizeAbility('team.invite', $this->contextScope());

        $this->reset(['editingMembershipId', 'userId', 'templateId', 'title', 'approvalLimit', 'granted', 'matrixSearch', 'memberSearch', 'inviting', 'inviteEmail', 'inviteName', 'inviteAsGuest']);
        $this->canSeeMoney = true;
        $this->resetValidation();
        $this->showMemberModal = true;

        $this->dispatch('open-modal', 'member-modal');
    }

    /** Invite somebody who has no login yet — staff or an outside guest. */
    public function inviteSomebody(bool $asGuest = false): void
    {
        $this->authorizeAbility('team.invite', $this->contextScope());

        $this->reset(['editingMembershipId', 'userId', 'templateId', 'title', 'approvalLimit', 'granted', 'matrixSearch', 'memberSearch', 'inviteEmail', 'inviteName']);

        $this->inviting = true;
        $this->inviteAsGuest = $asGuest;
        $this->canSeeMoney = ! $asGuest;

        $this->resetValidation();
        $this->showMemberModal = true;

        $this->dispatch('open-modal', 'member-modal');
    }

    public function editMember(int $membershipId): void
    {
        $this->authorizeAbility('team.view', $this->contextScope());

        $membership = $this->findMembership($membershipId);

        $this->editingMembershipId = $membership->id;
        $this->userId = (string) $membership->user_id;
        $this->templateId = $membership->permission_template_id;
        $this->title = (string) $membership->title;
        $this->canSeeMoney = $membership->can_see_money;
        $this->approvalLimit = $membership->approval_limit
            ? number_format($membership->approval_limit / 100, 2, '.', '')
            : '';
        $this->matrixSearch = '';

        $this->loadGrants($membership->abilities());

        $this->resetValidation();
        $this->showMemberModal = true;

        $this->dispatch('open-modal', 'member-modal');
    }

    /**
     * Give somebody who currently inherits from the project their own access
     * on this job site, starting from what they already have.
     */
    public function overrideInherited(int $membershipId): void
    {
        $this->authorizeAbility('team.invite', $this->contextScope());

        $inherited = Membership::with('abilityRows')->findOrFail($membershipId);

        $this->reset(['editingMembershipId', 'templateId', 'title', 'approvalLimit', 'granted', 'matrixSearch', 'memberSearch']);

        $this->userId = (string) $inherited->user_id;
        $this->templateId = $inherited->permission_template_id;
        $this->title = (string) $inherited->title;
        $this->canSeeMoney = $inherited->can_see_money;
        $this->approvalLimit = $inherited->approval_limit
            ? number_format($inherited->approval_limit / 100, 2, '.', '')
            : '';

        // Only what can be held at this level survives the copy.
        $this->loadGrants(AbilityCatalog::filter($inherited->abilities(), $this->scopeLevel()));

        $this->resetValidation();
        $this->showMemberModal = true;

        $this->dispatch('open-modal', 'member-modal');
    }

    public function closeMemberModal(): void
    {
        $this->dispatch('close-modal', 'member-modal');

        $this->showMemberModal = false;
        $this->reset(['editingMembershipId', 'userId', 'templateId', 'title', 'approvalLimit', 'granted', 'matrixSearch', 'memberSearch', 'inviting', 'inviteEmail', 'inviteName', 'inviteAsGuest']);
    }

    public function updatedInviteAsGuest(): void
    {
        // The offered templates change, so whatever was picked no longer
        // applies; and a guest never sees monetary figures.
        $this->templateId = null;
        $this->granted = [];
        $this->canSeeMoney = ! $this->inviteAsGuest;

        unset($this->templates);
    }

    /** Picking a template loads its abilities; the matrix is then editable. */
    public function updatedTemplateId($value): void
    {
        if (! $value) {
            return;
        }

        $template = $this->templates->firstWhere('id', (int) $value);

        if (! $template) {
            return;
        }

        $this->loadGrants($template->abilities());
        $this->canSeeMoney = $template->can_see_money;
        $this->approvalLimit = $template->approval_limit
            ? number_format($template->approval_limit / 100, 2, '.', '')
            : '';
    }

    #[Computed]
    public function matrix(): array
    {
        return $this->buildMatrix($this->scopeLevel());
    }

    #[Computed]
    public function grantedCount(): int
    {
        return count($this->grantedAbilities($this->scopeLevel()));
    }

    #[Computed]
    public function editingMembership(): ?Membership
    {
        return $this->editingMembershipId
            ? Membership::with(['user', 'template'])->find($this->editingMembershipId)
            : null;
    }

    /** A plain sentence describing what is about to be saved. */
    #[Computed]
    public function accessSummary(): string
    {
        $abilities = $this->grantedAbilities($this->scopeLevel());

        if ($abilities === []) {
            return __('This person will be able to open nothing here yet.');
        }

        $areas = [];

        foreach ($abilities as $ability) {
            $area = AbilityCatalog::split($ability)[0];
            $areas[$area] = __(AbilityCatalog::area($area)['name'] ?? $area);
        }

        $sentence = __(':count abilities across :areas.', [
            'count' => count($abilities),
            'areas' => implode(', ', array_slice(array_values($areas), 0, 6))
                .(count($areas) > 6 ? ' '.__('and :count more', ['count' => count($areas) - 6]) : ''),
        ]).' '.($this->canSeeMoney ? __('Monetary figures are shown.') : __('Monetary figures are hidden.'));

        // Do not describe a restriction that is not switched on: name the
        // areas among these that are still running on their old rules.
        $pending = array_values(array_intersect(array_keys($areas), AbilityCatalog::unsweptAreas()));

        if ($pending !== []) {
            $sentence .= ' '.__('Not enforced yet: :areas.', [
                'areas' => implode(', ', array_map(
                    fn ($area) => __(AbilityCatalog::area($area)['name'] ?? $area),
                    array_slice($pending, 0, 6),
                )).(count($pending) > 6 ? ' '.__('and :count more', ['count' => count($pending) - 6]) : ''),
            ]);
        }

        return $sentence;
    }

    public function saveMember(): void
    {
        $scope = $this->contextScope();
        $membership = $this->editingMembership;

        $this->authorizeAbility($membership ? 'team.manage' : 'team.invite', $scope);

        $this->validate($this->inviting ? [
            'inviteEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'inviteName' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:80'],
            'approvalLimit' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ] : [
            'userId' => ['required', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:80'],
            'approvalLimit' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [], [
            'inviteEmail' => __('E-mail address'),
            'inviteName' => __('Name'),
            'userId' => __('Person'),
            'title' => __('Title'),
            'approvalLimit' => __('Approval limit'),
        ]);

        $abilities = $this->grantedAbilities($this->scopeLevel());

        if ($this->inviting) {
            $this->sendInvitation($scope, $abilities);

            return;
        }

        DB::transaction(function () use ($scope, $membership, $abilities) {
            $before = $membership?->abilities() ?? [];

            $attributes = [
                'permission_template_id' => $this->templateId ?: null,
                'title' => $this->title ?: null,
                'can_see_money' => $this->canSeeMoney,
                'approval_limit' => $this->approvalLimit === '' ? null : (int) round((float) $this->approvalLimit * 100),
            ];

            if ($membership) {
                $membership->update($attributes);
            } else {
                $membership = Membership::updateOrCreate(
                    [
                        'user_id' => (int) $this->userId,
                        'scopeable_type' => $scope::class,
                        'scopeable_id' => $scope->getKey(),
                    ],
                    $attributes + [
                        'status' => MembershipStatus::ACTIVE,
                        'invited_by' => auth()->id(),
                        'invited_at' => now(),
                        'accepted_at' => now(),
                    ],
                );
            }

            $membership->syncAbilities($abilities);

            PermissionAudit::record(
                subjectType: 'membership',
                subjectId: $membership->id,
                action: $before === [] ? 'created' : 'updated',
                summary: $this->auditSummary($membership, $before, $abilities),
                subjectUserId: $membership->user_id,
                scopeable: $scope,
                before: ['abilities' => $before],
                after: ['abilities' => $abilities],
            );
        });

        app(PermissionResolver::class)->flush();

        unset($this->members, $this->inheritedMembers, $this->addableUsers);

        session()->flash('message', __('Access saved.'));

        $this->closeMemberModal();
    }

    /**
     * Nothing is written to `users` until the person accepts, so an invitation
     * that is never taken up leaves no account behind.
     */
    protected function sendInvitation(Project|JobSite $scope, array $abilities): void
    {
        // A guest may never hold a sensitive action, whatever was ticked.
        if ($this->inviteAsGuest) {
            $abilities = array_values(array_filter(
                $abilities,
                fn ($ability) => ! AbilityCatalog::isSensitive($ability),
            ));
        }

        app(InvitationService::class)->invite(
            email: $this->inviteEmail,
            name: $this->inviteName ?: null,
            roleId: $this->inviteAsGuest ? null : $this->defaultInviteRoleId(),
            isGuest: $this->inviteAsGuest,
            membership: [
                'scope' => $scope,
                'template_id' => $this->templateId ?: null,
                'title' => $this->title ?: null,
                'can_see_money' => $this->inviteAsGuest ? false : $this->canSeeMoney,
                'approval_limit' => $this->approvalLimit === '' ? null : (int) round((float) $this->approvalLimit * 100),
                'abilities' => $abilities,
            ],
        );

        unset($this->pendingInvitations);

        session()->flash('message', __('Invitation sent to :email. They will appear here once they accept.', [
            'email' => $this->inviteEmail,
        ]));

        $this->closeMemberModal();
    }

    /**
     * Staff invited from a Team tab get the lowest company-wide role there is,
     * confined to what they are given here. Widening somebody is a deliberate
     * act on the Users screen, not a side effect of being invited.
     */
    protected function defaultInviteRoleId(): ?int
    {
        return \App\Models\Role::where('name', 'employee')->value('id');
    }

    public function resendInvitation(int $invitationId): void
    {
        $this->authorizeAbility('team.invite', $this->contextScope());

        $invitation = $this->findInvitation($invitationId);

        app(InvitationService::class)->resend($invitation);

        unset($this->pendingInvitations);

        session()->flash('message', __('Invitation sent again to :email. The previous link no longer works.', [
            'email' => $invitation->email,
        ]));
    }

    public function withdrawInvitation(int $invitationId): void
    {
        $this->authorizeAbility('team.manage', $this->contextScope());

        $invitation = $this->findInvitation($invitationId);

        app(InvitationService::class)->revoke($invitation);

        unset($this->pendingInvitations);

        session()->flash('message', __('Invitation withdrawn. The link stops working immediately.'));
    }

    /** Never let one Team tab reach an invitation belonging to another. */
    protected function findInvitation(int $invitationId): UserInvitation
    {
        $invitation = $this->pendingInvitations->firstWhere('id', $invitationId);

        abort_if($invitation === null, 404);

        return $invitation;
    }

    protected function auditSummary(Membership $membership, array $before, array $after): string
    {
        $who = $membership->user?->name ?? __('Someone');
        $where = $this->scopeName();

        return $before === []
            ? __(':who added to :where with :count ability(ies)', ['who' => $who, 'where' => $where, 'count' => count($after)])
            : $who.' — '.__(':added granted, :removed revoked', [
                'added' => count(array_diff($after, $before)),
                'removed' => count(array_diff($before, $after)),
            ]);
    }

    public function suspendMember(int $membershipId): void
    {
        $this->authorizeAbility('team.manage', $this->contextScope());

        $membership = $this->findMembership($membershipId);

        $suspending = $membership->status !== MembershipStatus::SUSPENDED;

        $membership->update([
            'status' => $suspending ? MembershipStatus::SUSPENDED : MembershipStatus::ACTIVE,
            'revoked_at' => $suspending ? now() : null,
        ]);

        PermissionAudit::record(
            subjectType: 'membership',
            subjectId: $membership->id,
            action: $suspending ? 'suspended' : 'reactivated',
            summary: ($membership->user?->name ?? __('Someone')).' — '.($suspending
                ? __('suspended on :where', ['where' => $this->scopeName()])
                : __('reactivated on :where', ['where' => $this->scopeName()])),
            subjectUserId: $membership->user_id,
            scopeable: $this->contextScope(),
        );

        app(PermissionResolver::class)->flush();

        unset($this->members, $this->inheritedMembers);

        session()->flash('message', $suspending ? __('Access suspended.') : __('Access restored.'));
    }

    public function removeMember(int $membershipId): void
    {
        $this->authorizeAbility('team.manage', $this->contextScope());

        $membership = $this->findMembership($membershipId);

        PermissionAudit::record(
            subjectType: 'membership',
            subjectId: $membership->id,
            action: 'removed',
            summary: ($membership->user?->name ?? __('Someone')).' — '.__('removed from :where', ['where' => $this->scopeName()]),
            subjectUserId: $membership->user_id,
            scopeable: $this->contextScope(),
            before: ['abilities' => $membership->abilities()],
        );

        $membership->delete();

        app(PermissionResolver::class)->flush();

        unset($this->members, $this->inheritedMembers, $this->addableUsers);

        session()->flash('message', __('Removed from the team. The history of what they did stays.'));
    }

    /** Never let one Team tab reach another project's membership. */
    protected function findMembership(int $membershipId): Membership
    {
        $scope = $this->contextScope();

        return Membership::with(['user', 'abilityRows'])
            ->where('scopeable_type', $scope::class)
            ->where('scopeable_id', $scope->getKey())
            ->findOrFail($membershipId);
    }

    protected function scopeName(): string
    {
        $scope = $this->contextScope();

        return $scope instanceof JobSite
            ? (string) $scope->job_site_name
            : (string) $scope->project_name;
    }
}
