<?php

namespace App\Providers;

use App\Models\ModuleAccess;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\MeetingAgendaService;
use App\Services\PermissionResolver;
use Carbon\Carbon;
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
        // Which modules are switched on is memoised for the life of this
        // application, so the answer is not fetched from the cache store —
        // which is the database — once per permission decision.
        ModuleAccess::flushEnabled();

        // One resolver per request: it memoises the answers it has already
        // given, and the memberships it loaded to give them.
        $this->app->singleton(PermissionResolver::class);

        // Likewise the agenda service: it memoises how the earlier meetings of
        // a series were ordered, which the sort asks for once per location on
        // the agenda. Resolved fresh each time, that cache never survived long
        // enough to be read — the agenda screen was rebuilding it four times.
        //
        // `scoped` rather than `singleton` so a long-lived worker starts each
        // request with an empty one.
        $this->app->scoped(MeetingAgendaService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @admin ... @endadmin lived here until F2. Every use of it became a
        // @can on the ability that actually applies.

        // @money($project) ... @endmoney — render monetary figures only where
        // this person is allowed to see them.
        Blade::if('money', fn ($scope = null) => app(PermissionResolver::class)
            ->canSeeMoney(auth()->user(), $scope));

        $this->registerPermissionGate();
        $this->registerDateMacros();
    }

    /**
     * How this install writes a date and a time.
     *
     * Every screen used to decide for itself, which is how a Brazilian install
     * came to show `Aug 31, 2026` — US order *and* English month names, which
     * no locale setting fixes because `format()` never translates. Four habits
     * were in the codebase at once: 133 hardcoded `M d, Y`, 11 `m/d/Y`, 29
     * `d/m/Y` (wrong the other way, on a US install), and 39 copies of the
     * same country ternary. This is the single place that decides now.
     *
     * Machine formats — `Y-m-d` for a date input, `Y-m` for a grouping key —
     * are deliberately not routed through here: they are not read by a person
     * and must not move when the country does.
     */
    protected function registerDateMacros(): void
    {
        /*
         * `Aug 31, 2026` and `31 ago. 2026`.
         *
         * The month is a word on purpose — it is what this product has always
         * shown, and it cannot be misread the way a bare `08/31` can when the
         * reader is used to the other order. `translatedFormat`, so the word is
         * in the reader's language rather than always English, which was the
         * original complaint.
         */
        Carbon::macro('appDate', fn () => config('app.country') === 'BR'
            ? $this->translatedFormat('d M Y')
            : $this->translatedFormat('M d, Y'));

        // 14:30 against 2:30 PM: Brazil writes the twenty-four hour clock.
        Carbon::macro('appTime', fn () => $this->format(
            config('app.country') === 'BR' ? 'H:i' : 'g:i A'
        ));

        Carbon::macro('appDateTime', fn () => config('app.country') === 'BR'
            ? $this->translatedFormat('d M Y H:i')
            : $this->translatedFormat('M d, Y g:i A'));

        /*
         * The long form, for the face of a document rather than a table cell.
         *
         * `translatedFormat` and not `format`, because this one prints the
         * month as a word and that word has to be in the reader's language:
         * *31 de agosto de 2026*, `August 31, 2026`.
         */
        Carbon::macro('appDateLong', fn () => config('app.country') === 'BR'
            ? $this->translatedFormat('j \d\e F \d\e Y')
            : $this->translatedFormat('F j, Y'));

        // Day and month only, for a column too narrow for a year.
        Carbon::macro('appDateShort', fn () => config('app.country') === 'BR'
            ? $this->translatedFormat('d M')
            : $this->translatedFormat('M d'));

        /*
         * The numeric form, for a date *input* and nothing else.
         *
         * `<input type="date">` shows whatever the browser's own locale says,
         * which is not this install's country — see `<x-ui.date-input>`, which
         * is the only thing that should be calling this.
         */
        Carbon::macro('appDateNumeric', fn () => $this->format(
            config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y'
        ));
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
