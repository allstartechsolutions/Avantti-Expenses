{{--
    Change order headline figures. Expects: $summary (from changeOrderSummary()).
--}}
@php
    $money = fn ($value) => Number::currency((float) $value, config('app.currency'), config('app.locale'));
    $signed = fn ($value) => ((float) $value >= 0 ? '+' : '') . Number::currency((float) $value, config('app.currency'), config('app.locale'));
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    {{-- Approved only, so this card and the contract value on the financial
         report can never disagree. --}}
    <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-5 text-white">
        <p class="text-sm font-medium text-white/80">{{ __('Billed to the Client') }}</p>
        <p class="text-2xl font-bold mt-1">{{ $signed($summary['approved_revenue']) }}</p>
        <p class="mt-2 text-xs text-white/80">
            {{ trans_choice(':count approved change order|:count approved change orders', $summary['approved_count'], ['count' => $summary['approved_count']]) }}
        </p>
        @if($summary['count'] > $summary['approved_count'])
            <p class="mt-1 text-xs text-white/70">
                {{ trans_choice(
                    ':count more raised, not counted|:count more raised, not counted',
                    $summary['count'] - $summary['approved_count'],
                    ['count' => $summary['count'] - $summary['approved_count']]
                ) }}
            </p>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Approved Cost Impact') }}</p>
        <p class="text-2xl font-bold mt-1 {{ $summary['approved_cost'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
            {{ $signed($summary['approved_cost']) }}
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            {{ __('Already revising the cost code budgets') }}
        </p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Margin on Approved') }}</p>
        <p class="text-2xl font-bold mt-1 {{ $summary['approved_margin'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
            {{ $money($summary['approved_margin']) }}
        </p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            @if($summary['approved_margin_percent'] === null)
                {{ __('Nothing billed on the approved changes') }}
            @else
                {{ number_format($summary['approved_margin_percent'], 1) }}% {{ __('of') }} {{ $money($summary['approved_revenue']) }}
            @endif
        </p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Awaiting a Decision') }}</p>
        <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ $summary['awaiting_count'] }}</p>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
            @if($summary['awaiting_count'] === 0)
                {{ __('Every change order has been decided') }}
            @else
                {{ $signed($summary['awaiting_revenue']) }} {{ __('billed') }} · {{ $signed($summary['awaiting_cost']) }} {{ __('cost held back') }}
            @endif
        </p>
    </div>
</div>

@if($summary['uncosted_count'] > 0)
    <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 flex items-start gap-3">
        <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"></path>
        </svg>
        <p class="text-sm text-amber-900 dark:text-amber-200">
            {{ trans_choice(
                ':count change order bills the client but has no cost breakdown, so it does not appear in any cost code budget.|:count change orders bill the client but have no cost breakdown, so they do not appear in any cost code budget.',
                $summary['uncosted_count'],
                ['count' => $summary['uncosted_count']]
            ) }}
        </p>
    </div>
@endif
