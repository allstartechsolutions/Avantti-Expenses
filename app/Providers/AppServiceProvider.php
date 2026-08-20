<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One resolver per request: it memoises the answers it has already
        // given, and the memberships it loaded to give them.
        $this->app->singleton(PermissionResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @admin ... @endadmin — render a block only for admin users.
        // Retired module by module as each pass replaces it with @can.
        Blade::if('admin', fn () => auth()->check() && auth()->user()->is_admin);

        // @money($project) ... @endmoney — render monetary figures only where
        // this person is allowed to see them.
        Blade::if('money', fn ($scope = null) => app(PermissionResolver::class)
            ->canSeeMoney(auth()->user(), $scope));

        $this->registerPermissionGate();
    }

    /**
     * Route every ability in config/permissions.php through the resolver, so
     * `@can('expenses.create', $project)`, `$user->can(...)` and
     * `$this->authorize(...)` all give the same answer as the policies do.
     *
     * Returning null leaves anything that is not ours — a policy ability, a
     * gate defined elsewhere — exactly as it was.
     */
    protected function registerPermissionGate(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! AbilityCatalog::has($ability) && $ability !== AbilityCatalog::financeAbility()) {
                return null;
            }

            return app(PermissionResolver::class)
                ->allows($user, $ability, $arguments[0] ?? null);
        });
    }
}
