<?php

namespace App\Services;

use App\Enums\AccessScope;
use App\Enums\UserStatus;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who can actually be given a piece of buying work, here.
 *
 * One list, used by every screen that names a buyer — the defaults panel, the
 * requisition raise form, the approve dialog and the reassign control — so
 * that a picker and the endpoint behind it can never disagree about who is
 * eligible. Assigning work to somebody who will hit a 403 is a dead letter,
 * and this is where that is prevented.
 *
 * At a project or job site the candidates start from the memberships on it,
 * never from every user in the company — on a confined person's screen that
 * would be a staff directory. Each candidate is then put back through the
 * resolver, because a membership proves somebody is *here*, not that they may
 * buy.
 */
class BuyerDirectory
{
    /** Memo per request: these screens ask the same question several times. */
    protected array $answers = [];

    /**
     * The people who may raise a quotation round on this scope.
     *
     * A null scope asks the company-wide question — "who may raise a round
     * anywhere?" — which is what the install-wide default needs.
     *
     * @return Collection<int, User>
     */
    public function for(Project|JobSite|null $scope): Collection
    {
        return $this->holdersOf('quotations.create', $scope);
    }

    /**
     * The people who hold one ability on this scope.
     *
     * The same question with a different verb: who may *quote* here, who may
     * *approve* here. Everything else about the answer — start from the
     * memberships, re-check through the resolver — is identical, so it is one
     * method rather than two that drift.
     *
     * @return Collection<int, User>
     */
    public function holdersOf(string $ability, Project|JobSite|null $scope): Collection
    {
        $key = $ability.'|'.($scope === null ? 'global' : $scope::class.':'.$scope->id);

        return $this->answers[$key] ??= $this->resolve($ability, $scope);
    }

    /** @return Collection<int, User> */
    protected function resolve(string $ability, Project|JobSite|null $scope): Collection
    {
        $resolver = app(PermissionResolver::class);

        $users = $scope === null
            ? $this->activeStaff()->orderBy('name')->get()
            : $this->around($scope);

        return $users
            ->filter(fn (User $user) => $resolver->allows($user, $ability, $scope))
            ->values();
    }

    /**
     * A guest is never a buyer: an outsider invited to answer one RFI has no
     * business being handed the company's purchasing.
     */
    protected function activeStaff()
    {
        return User::query()
            ->where('status', UserStatus::ACTIVE)
            ->where('is_guest', false);
    }

    /**
     * Everybody with a membership on this project or job site, plus the people
     * whose access is company-wide and who therefore never needed one.
     *
     * @return Collection<int, User>
     */
    protected function around(Project|JobSite $scope): Collection
    {
        $memberships = Membership::query()
            ->active()
            ->where(function ($query) use ($scope) {
                $query->where(fn ($q) => $q
                    ->where('scopeable_type', $scope::class)
                    ->where('scopeable_id', $scope->id));

                // A project membership covers every job site under it, so the
                // site's list has to offer the project's people too.
                if ($scope instanceof JobSite) {
                    $query->orWhere(fn ($q) => $q
                        ->where('scopeable_type', Project::class)
                        ->where('scopeable_id', $scope->project_id));
                }
            })
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        $companyWide = $this->activeStaff()
            ->where('access_scope', AccessScope::COMPANY->value)
            ->get();

        return $memberships
            ->merge($companyWide)
            ->filter(fn (?User $user) => $user && $user->isActive() && ! $user->is_guest)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
