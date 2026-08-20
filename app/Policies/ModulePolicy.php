<?php

namespace App\Policies;

use App\Models\JobSite;
use App\Models\Project;
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
     * The project or job site a subject belongs to. A Project or JobSite is
     * its own scope; anything else is asked for its job site first, since the
     * more specific membership is the one that should win.
     */
    protected function scopeOf(mixed $subject): Project|JobSite|null
    {
        if ($subject instanceof Project || $subject instanceof JobSite) {
            return $subject;
        }

        if ($subject instanceof Model) {
            return $this->scopeFor($subject);
        }

        return null;
    }

    protected function scopeFor(Model $model): Project|JobSite|null
    {
        if (isset($model->job_site_id) && $model->job_site_id) {
            return $model->relationLoaded('jobSite')
                ? $model->jobSite
                : JobSite::find($model->job_site_id);
        }

        if (isset($model->project_id) && $model->project_id) {
            return $model->relationLoaded('project')
                ? $model->project
                : Project::find($model->project_id);
        }

        return null;
    }
}
