@props([
    /** The figure, in the app's currency. */
    'amount' => 0,
    /** The project or job site the figure belongs to; decides who may see it. */
    'scope' => null,
    /** Set on roll-ups — totals, budgets, margins — which `can_see_money` hides. */
    'rollup' => false,
])

{{--
    One monetary figure.

    `can_see_money` hides ROLL-UPS, not records: what a project or a job site
    adds up to is the company's financial picture, while the amount on an
    expense somebody filed themselves is not a secret from them. So a figure
    marked `rollup` disappears for anybody whose membership hides money, and an
    ordinary figure is shown to anyone allowed on the screen at all.

    See docs/permissions-module.md, M4.
--}}
@if($rollup && ! app(\App\Services\PermissionResolver::class)->canSeeMoney(auth()->user(), $scope))
    <span {{ $attributes }} title="{{ __('Totals are hidden for your access on this project.') }}">
        <span aria-hidden="true">&mdash;&mdash;</span>
        <span class="sr-only">{{ __('Hidden') }}</span>
    </span>
@else
    <span {{ $attributes }}>{{ Number::currency($amount, config('app.currency'), config('app.locale')) }}</span>
@endif
