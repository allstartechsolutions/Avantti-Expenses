<?php

namespace App\Policies;

use App\Contracts\PermissionScope;
use App\Models\User;
use App\Services\PermissionResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * The base every module's policy extends during its permission pass.
 *
 * A policy's whole job here is to say which area of the catalogue the model
 * belongs to, and how to get from a record to the project or job site it lives
 * on. The decision itself always comes from the resolver.
 *
 *     class ExpensePolicy extends ModulePolicy
 *     {
 *         protected string $area = 'expenses';
 *     }
 *
 * The default scopeFor() covers the usual case — a model with `job_site_id`
 * and/or `project_id` — and a policy overrides it when the route to the
 * project is longer than that.
 */
abstract class ModulePolicy
{
    /** The catalogue area this policy speaks for, e.g. 'expenses'. */
    protected string $area;

    public function __construct(protected PermissionResolver $resolver) {}

    public function viewAny(User $user, mixed $scope = null): bool
    {
        return $this->check($user, 'view', $scope);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->check($user, 'view', $model);
    }

    public function create(User $user, mixed $scope = null): bool
    {
        return $this->check($user, 'create', $scope);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->check($user, 'edit', $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->check($user, 'delete', $model);
    }

    /**
     * Any action of this area against a record or a scope:
     * `$user->can('approve', $changeOrder)` reaches this through the policy.
     */
    public function check(User $user, string $action, mixed $subject = null): bool
    {
        return $this->resolver->allows($user, "{$this->area}.{$action}", $this->scopeOf($subject));
    }

    /** Whether monetary figures are shown to this user for this record. */
    public function seeMoney(User $user, mixed $subject = null): bool
    {
        return $this->resolver->canSeeMoney($user, $this->scopeOf($subject));
    }

    /**
     * The project or job site a subject belongs to.
     *
     * The walk itself belongs to the resolver, which every other consumer uses
     * too; a policy overrides this only when the route from its record to a
     * project is longer than a `job_site_id` / `project_id` column.
     */
    protected function scopeOf(mixed $subject): ?PermissionScope
    {
        return $this->resolver->scopeOf($subject);
    }
}
