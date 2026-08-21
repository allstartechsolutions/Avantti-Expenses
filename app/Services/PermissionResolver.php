<?php

namespace App\Services;

use App\Contracts\PermissionScope;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\ModuleAccess;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place that answers "may this person do this?".
 *
 * Everything else — the Gate, the policies, the Livewire trait, the middleware,
 * the navigation builder — asks this class. Nothing else reads
 * `role_abilities`, `memberships` or `membership_abilities` to make a decision.
 *
 * The order of the checks, and why each one is where it is, is set out in
 * docs/permissions-module-plan.md §6. In short:
 *
 *   1. no user, or a user who is not active            → no
 *   2. the customer has the module switched off        → no, for everybody
 *   3. administrator                                   → yes
 *   4. the area has not had its permission pass yet    → THE LEGACY BRIDGE
 *   5. otherwise: the role for company-wide users, the membership for the
 *      project or job site in hand, and money and approval limits on top
 *
 * Results are memoised for the request. There is deliberately no cross-request
 * cache: a revoked ability has to be gone on the next click.
 */
class PermissionResolver
{
    /** @var array<int, array<int, string>> Role abilities, by role id. */
    protected array $roleAbilities = [];

    /** @var array<int, array<string, Membership>> Active memberships, by user id then "Type:id". */
    protected array $memberships = [];

    /** @var array<string, bool> Answers already given this request. */
    protected array $answers = [];

    /*
    |---------------------------------------------------------------------------
    | The question
    |---------------------------------------------------------------------------
    */

    /**
     * @param  Project|JobSite|null  $scope  The record the ability is asked about.
     */
    public function allows(?User $user, string $ability, mixed $scope = null): bool
    {
        if (! $user) {
            return false;
        }

        $key = $this->cacheKey($user, $ability, $scope);

        return $this->answers[$key] ??= $this->decide($user, $ability, $scope);
    }

    public function denies(?User $user, string $ability, mixed $scope = null): bool
    {
        return ! $this->allows($user, $ability, $scope);
    }

    protected function decide(User $user, string $ability, mixed $scope): bool
    {
        // 1. Suspended and inactive people hold nothing.
        if (! $user->isActive()) {
            return false;
        }

        // The company-wide money switch is not an area, so it is answered
        // straight from the role.
        if ($ability === AbilityCatalog::financeAbility()) {
            return $user->is_admin || $this->roleAllows($user, $ability);
        }

        if (! AbilityCatalog::has($ability)) {
            return false;
        }

        // 2. The install-level switch wins over every permission there is.
        if (! $this->moduleEnabled($ability)) {
            return false;
        }

        // 3. Administrators are allowed everything that is switched on.
        if ($user->is_admin) {
            return true;
        }

        // 4. A company-wide screen — the left menu and what is behind it — is
        //    the role's business and nothing else's. Confinement is about
        //    *which projects* somebody can reach; it must not empty their menu
        //    of the things that have no project at all.
        if (! $this->isScopedAbility($ability)) {
            // Except for a guest: an outsider has no company-wide anything,
            // whatever role they were given. Belt and braces — invitations
            // give a guest no role at all.
            return ! $user->is_guest && $this->roleAllows($user, $ability);
        }

        // 5. Something that belongs to a project or a job site.
        $membership = $scope !== null ? $this->membershipFor($user, $scope) : null;

        // THE LEGACY BRIDGE (docs/permissions-module-plan.md §9.1): while the
        // area still runs on its old checks it cannot honour a membership, so
        // a confined person is denied rather than half-served.
        if (! AbilityCatalog::isSwept($ability)) {
            return ! $user->isConfined() && $this->roleAllows($user, $ability);
        }

        // 6. A membership REPLACES the role on the scope it covers. Being made
        //    a Site Supervisor on one job site means being a site supervisor
        //    there — not a site supervisor plus whatever the role happened to
        //    give. Specific beats general, the same way a job-site membership
        //    beats the project's.
        if ($membership) {
            // Being on a project is being able to open it. Without this, a
            // membership that forgot `project.view` — or was backfilled before
            // the area existed — would lock somebody out of the very project
            // they were added to, and every tab of it with them.
            if ($ability === 'project.view') {
                return true;
            }

            return in_array($ability, $this->membershipAbilities($membership), true);
        }

        // 7. No membership here. Somebody confined has no business on this
        //    project at all; anybody else falls back to their role.
        if ($user->isConfined()) {
            // Asked without a scope ("may they do this anywhere?"), the answer
            // is yes if any of their memberships says so — that is what a menu
            // or an index needs to know before it has a record in hand.
            return $scope === null && $this->heldOnAnyScope($user, $ability);
        }

        return $this->roleAllows($user, $ability);
    }

    /** Whether this ability belongs to a project or job site rather than the company. */
    protected function isScopedAbility(string $ability): bool
    {
        $levels = AbilityCatalog::area(AbilityCatalog::split($ability)[0])['levels'] ?? [];

        return in_array('project', $levels, true) || in_array('job_site', $levels, true);
    }

    /** Does any membership this person holds grant this ability anywhere? */
    protected function heldOnAnyScope(User $user, string $ability): bool
    {
        foreach ($this->membershipsOf($user) as $membership) {
            if (in_array($ability, $this->membershipAbilities($membership), true)) {
                return true;
            }
        }

        return false;
    }

    /*
    |---------------------------------------------------------------------------
    | Money and approval limits
    |---------------------------------------------------------------------------
    */

    /**
     * Whether monetary figures are shown to this person here.
     *
     * The membership's `can_see_money` is the one deliberate subtraction in the
     * model: it can take money away from somebody whose role would otherwise
     * show it. Away from a project or job site, the role's finance ability
     * decides.
     */
    public function canSeeMoney(?User $user, mixed $scope = null): bool
    {
        if (! $user || ! $user->isActive()) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        if ($membership = $this->membershipFor($user, $scope)) {
            return $membership->can_see_money;
        }

        return $user->isCompanyWide()
            && $this->roleAllows($user, AbilityCatalog::financeAbility());
    }

    /**
     * The ceiling on this person's `limited` actions here, in cents.
     * Null means no ceiling.
     */
    public function approvalLimit(?User $user, mixed $scope = null): ?int
    {
        if (! $user || $user->is_admin) {
            return null;
        }

        return $this->membershipFor($user, $scope)?->approval_limit;
    }

    /**
     * Whether an amount (in cents) is within this person's ceiling here.
     * Used by the `limited` actions — approve, award, convert, pay.
     */
    public function withinApprovalLimit(?User $user, ?int $amount, mixed $scope = null): bool
    {
        $limit = $this->approvalLimit($user, $scope);

        return $limit === null || $amount === null || $amount <= $limit;
    }

    /*
    |---------------------------------------------------------------------------
    | The pieces
    |---------------------------------------------------------------------------
    */

    protected function moduleEnabled(string $ability): bool
    {
        $module = AbilityCatalog::moduleFor($ability);

        return $module === null || ModuleAccess::isEnabled($module);
    }

    protected function roleAllows(User $user, string $ability): bool
    {
        if (! $user->role_id) {
            return false;
        }

        $abilities = $this->roleAbilities[$user->role_id] ??= $user->role
            ? $user->role->abilityRows()->pluck('ability')->all()
            : [];

        return in_array($ability, $abilities, true);
    }

    /**
     * What this person may do on this project or job site, by membership.
     *
     * A job site is answered by its own membership if there is one, and by the
     * parent project's if there is not — specific beats general, and a project
     * membership cascades down.
     *
     * @return array<int, string>
     */
    public function abilitiesFor(User $user, mixed $scope): array
    {
        $membership = $this->membershipFor($user, $scope);

        return $membership ? $this->membershipAbilities($membership) : [];
    }

    /**
     * The membership that governs this scope, or null.
     *
     * The most specific one wins: a job site's own membership before the
     * project's. The walk is generic — a scope is asked for its parent — so a
     * third kind of scope needs nothing here beyond implementing
     * App\Contracts\PermissionScope.
     */
    public function membershipFor(User $user, mixed $scope): ?Membership
    {
        $scope = $this->scopeOf($scope);

        if ($scope === null) {
            return null;
        }

        $memberships = $this->membershipsOf($user);
        $guard = 0;

        while ($scope !== null && $guard++ < 10) {
            $key = $this->scopeKey($scope::class, $scope->getKey());

            if (isset($memberships[$key])) {
                return $memberships[$key];
            }

            $scope = $scope instanceof PermissionScope ? $scope->parentScope() : null;
        }

        return null;
    }

    /**
     * Every active membership this user holds, loaded once per request with
     * its abilities.
     *
     * @return array<string, Membership>
     */
    public function membershipsOf(User $user): array
    {
        return $this->memberships[$user->id] ??= $user->memberships()
            ->active()
            ->with('abilityRows')
            ->get()
            ->keyBy(fn (Membership $m) => $this->scopeKey($m->scopeable_type, $m->scopeable_id))
            ->all();
    }

    /** @return array<int, string> */
    protected function membershipAbilities(Membership $membership): array
    {
        return $membership->abilityRows->pluck('ability')->all();
    }

    /**
     * The project or job site a subject belongs to.
     *
     * A scope is its own answer. Anything else — an expense, a requisition, a
     * purchase order — is asked for its job site first and its project second,
     * so that `@can('expenses.pay', $expense)` reads the same way in a view as
     * `@can('expenses.pay', $project)` does, and the more specific membership
     * is the one that wins. A record that belongs to neither has no scope, and
     * the caller is answered by role alone.
     *
     * This lives here rather than in the policies because every consumer needs
     * it — the Gate, the Blade directives, canSeeMoney() and approvalLimit().
     */
    public function scopeOf(mixed $subject): ?PermissionScope
    {
        $guard = 0;

        while ($subject instanceof Model && $guard++ < 10) {
            if ($subject instanceof PermissionScope) {
                return $subject;
            }

            $subject = $this->scopeParentOf($subject);
        }

        return null;
    }

    /**
     * One step up from a record towards its project.
     *
     * `job_site_id` and `project_id` cover almost everything. A record that
     * carries neither — an expense installment, a quotation line — says where
     * to look next by declaring `permissionScope()`, and the walk continues
     * from whatever that returns.
     */
    protected function scopeParentOf(Model $model): ?Model
    {
        if (method_exists($model, 'permissionScope')) {
            return $model->permissionScope();
        }

        if (! empty($model->job_site_id)) {
            return $model->relationLoaded('jobSite')
                ? $model->jobSite
                : JobSite::find($model->job_site_id);
        }

        if (! empty($model->project_id)) {
            return $model->relationLoaded('project')
                ? $model->project
                : Project::find($model->project_id);
        }

        return null;
    }

    protected function scopeKey(string $type, int|string|null $id): string
    {
        return $type.':'.$id;
    }

    /**
     * Two expenses on the same job site are the same question, so the key is
     * built from the *derived* scope rather than the record handed in.
     */
    protected function cacheKey(User $user, string $ability, mixed $scope): string
    {
        $resolved = $this->scopeOf($scope);

        $scopeKey = $resolved instanceof Model
            ? $this->scopeKey($resolved::class, $resolved->getKey())
            : ($scope === null ? 'none' : 'unscoped');

        return $user->id.'|'.$ability.'|'.$scopeKey;
    }

    /**
     * Forget everything memoised. Called after any grant changes, and by the
     * tests between personas.
     */
    public function flush(): void
    {
        $this->roleAbilities = [];
        $this->memberships = [];
        $this->answers = [];
    }
}
