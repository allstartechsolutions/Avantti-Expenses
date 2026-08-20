{{--
    Compact "where this budget stands" block for budget list rows.
    Expects: $totals (a CostCodeLedger totals row, or null), $align ('right' by default)
--}}
@php
    $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $revised = $totals ? (float) $totals['revised'] : 0.0;
    $actual = $totals ? (float) $totals['actual'] : 0.0;
    $remaining = $totals ? (float) $totals['remaining'] : 0.0;
    $over = $totals ? $totals['over_budget'] : false;
    $spentPct = $revised > 0 ? min(100, max(0, $actual / $revised * 100)) : null;
    $projPct = $revised > 0 && $totals ? min(100, max(0, $totals['projected'] / $revised * 100)) : null;
@endphp

<div class="text-right">
    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $fmt($revised) }}</p>

    @if($totals && ((float) $totals['projected'] != 0.0 || (float) $totals['changes'] != 0.0))
        <div class="mt-1 flex items-center justify-end gap-2">
            <div class="relative w-24 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="absolute inset-y-0 left-0 {{ $over ? 'bg-red-300 dark:bg-red-800' : 'bg-[#3F5189]/30 dark:bg-[#4A5A96]/40' }}" style="width: {{ $projPct ?? 0 }}%"></div>
                <div class="absolute inset-y-0 left-0 {{ $over ? 'bg-red-600 dark:bg-red-500' : 'bg-[#3F5189] dark:bg-[#4A5A96]' }}" style="width: {{ $spentPct ?? 0 }}%"></div>
            </div>
            <span class="text-xs {{ $remaining < 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-slate-500 dark:text-slate-400' }}">
                {{ $remaining < 0
                    ? __(':amount over', ['amount' => $fmt(abs($remaining))])
                    : __(':amount left', ['amount' => $fmt($remaining)]) }}
            </span>
        </div>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            {{ $fmt($actual) }} {{ __('spent') }}
            @if((float) $totals['committed'] != 0.0)
                &bull; {{ $fmt($totals['committed']) }} {{ __('committed') }}
            @endif
        </p>
    @endif
</div>
