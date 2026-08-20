<?php

namespace App\Livewire\Concerns;

use App\Services\PermissionResolver;

/**
 * The guard every Livewire component uses once its module has had its
 * permission pass.
 *
 * Call authorizeAbility() at the top of mount() **and at the top of every
 * action method**: hiding a button is not protection, because the wire:click
 * behind it can be invoked directly.
 *
 *     public function mount(Project $project): void
 *     {
 *         $this->authorizeAbility('expenses.view', $project);
 *     }
 *
 *     public function approve(int $id): void
 *     {
 *         $this->authorizeAbility('change-orders.approve', $this->project);
 *         ...
 *     }
 *
 * Replaces AuthorizesAdmin, which is deleted when the last module passes.
 */
trait AuthorizesAbility
{
    protected function authorizeAbility(string $ability, mixed $scope = null): void
    {
        abort_unless(
            $this->allowsAbility($ability, $scope),
            403,
            __('You do not have permission to do that.'),
        );
    }

    /** Any of the given abilities is enough. */
    protected function authorizeAnyAbility(array $abilities, mixed $scope = null): void
    {
        foreach ($abilities as $ability) {
            if ($this->allowsAbility($ability, $scope)) {
                return;
            }
        }

        abort(403, __('You do not have permission to do that.'));
    }

    /** For deciding what to render — never a substitute for the guard above. */
    protected function allowsAbility(string $ability, mixed $scope = null): bool
    {
        return app(PermissionResolver::class)->allows(auth()->user(), $ability, $scope);
    }

    /** Whether this user sees monetary figures here. */
    protected function allowsMoney(mixed $scope = null): bool
    {
        return app(PermissionResolver::class)->canSeeMoney(auth()->user(), $scope);
    }

    /**
     * A `limited` action against a value, in cents: the ability, and then the
     * person's ceiling for this project or job site.
     */
    protected function authorizeAbilityWithin(string $ability, ?int $amount, mixed $scope = null): void
    {
        $this->authorizeAbility($ability, $scope);

        $resolver = app(PermissionResolver::class);

        abort_unless(
            $resolver->withinApprovalLimit(auth()->user(), $amount, $scope),
            403,
            __('This is above the amount you may approve.'),
        );
    }
}
