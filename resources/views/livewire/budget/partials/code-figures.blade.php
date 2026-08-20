{{--
    The money beside one cost code on the budget page: what it is budgeted now,
    what has been spent against it, and what is left.
    Expects: $row (a CostCodeLedger row, or null), $item, $size ('parent'|'child')
--}}
@php
    $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));

    $revised = $row ? (float) $row['revised'] : (float) $item->budgeted_amount;
    $changes = $row ? (float) $row['changes'] : 0.0;
    $projected = $row ? (float) $row['projected'] : 0.0;
    $actual = $row ? (float) $row['actual'] : 0.0;
    $remaining = $row ? (float) $row['remaining'] : $revised;
    $over = $row ? $row['over_budget'] : false;

    $spentPct = $revised > 0 ? min(100, max(0, $actual / $revised * 100)) : null;
    $projPct = $revised > 0 ? min(100, max(0, $projected / $revised * 100)) : null;

    $title = implode("\n", array_filter([
        __('Original') . ': ' . $fmt($row ? $row['original'] : $item->budgeted_amount),
        $changes != 0.0 ? __('Approved changes') . ': ' . $signed($changes) : null,
        __('Committed') . ': ' . $fmt($row ? $row['committed'] : 0),
        __('Actual') . ': ' . $fmt($actual),
        __('Projected') . ': ' . $fmt($projected),
    ]));
@endphp

<div class="text-right" title="{{ $title }}">
    <div class="flex items-center justify-end gap-2">
        @if($changes != 0.0)
            <span class="text-xs {{ $changes < 0 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}">{{ $signed($changes) }}</span>
        @endif
        <span class="{{ $size === 'parent' ? 'font-semibold text-slate-900 dark:text-white' : 'text-sm font-medium text-slate-700 dark:text-slate-300' }}">
            {{ $fmt($revised) }}
        </span>
    </div>

    @if($projected != 0.0 || $over)
        <div class="mt-1 flex items-center justify-end gap-2">
            <div class="relative w-20 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="absolute inset-y-0 left-0 {{ $over ? 'bg-red-300 dark:bg-red-800' : 'bg-[#3F5189]/30 dark:bg-[#4A5A96]/40' }}" style="width: {{ $projPct ?? 100 }}%"></div>
                <div class="absolute inset-y-0 left-0 {{ $over ? 'bg-red-600 dark:bg-red-500' : 'bg-[#3F5189] dark:bg-[#4A5A96]' }}" style="width: {{ $spentPct ?? 0 }}%"></div>
            </div>
            <span class="text-xs {{ $remaining < 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-slate-500 dark:text-slate-400' }}">
                {{ $remaining < 0
                    ? __(':amount over', ['amount' => $fmt(abs($remaining))])
                    : __(':amount left', ['amount' => $fmt($remaining)]) }}
            </span>
        </div>
    @endif
</div>
