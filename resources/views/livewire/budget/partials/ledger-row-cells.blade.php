{{--
    The money cells of one cost code ledger row, shared by every row type in
    the grid so a section header, a line and a subtotal always line up.
    Expects: $row, $emphasis ('line' | 'section' | 'subtotal' | 'total')
--}}
@php
    $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));

    $weight = match ($emphasis) {
        'total' => 'font-bold text-slate-900 dark:text-white',
        'subtotal', 'section' => 'font-semibold text-slate-900 dark:text-white',
        default => 'text-slate-700 dark:text-slate-300',
    };
    $pad = $emphasis === 'total' ? 'px-4 py-3' : ($emphasis === 'line' ? 'px-4 py-2' : 'px-4 py-2.5');

    $revised = (float) $row['revised'];
    $spentPct = $revised > 0 ? min(100, max(0, $row['actual'] / $revised * 100)) : null;
    $projPct = $revised > 0 ? min(100, max(0, $row['projected'] / $revised * 100)) : null;

    $breakdown = implode("\n", array_filter([
        __('Contracts') . ': ' . $fmt($row['committed_contracts']),
        (float) $row['committed_pos'] != 0.0 ? __('Purchase orders awaiting approval') . ': ' . $fmt($row['committed_pos']) : null,
        __('Expenses') . ': ' . $fmt($row['actual_expenses']),
        (float) $row['actual_payments'] != 0.0 ? __('Contract payments') . ': ' . $fmt($row['actual_payments']) : null,
    ]));
@endphp

<td class="{{ $pad }} text-sm text-right {{ $weight }}">{{ $fmt($row['original']) }}</td>

<td class="{{ $pad }} text-sm text-right {{ (float) $row['changes'] == 0.0 ? 'text-slate-400 dark:text-slate-500' : ((float) $row['changes'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400') }} {{ $emphasis === 'line' ? '' : 'font-semibold' }}">
    {{ (float) $row['changes'] == 0.0 ? '—' : $signed($row['changes']) }}
</td>

<td class="{{ $pad }} text-sm text-right {{ $weight }}">{{ $fmt($row['revised']) }}</td>

<td class="{{ $pad }} text-sm text-right {{ $weight }}" title="{{ $breakdown }}">{{ $fmt($row['committed']) }}</td>

<td class="{{ $pad }} text-sm text-right {{ (float) $row['actual'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500' }} {{ $emphasis === 'line' ? '' : 'font-semibold' }}" title="{{ $breakdown }}">
    {{ $fmt($row['actual']) }}
</td>

<td class="{{ $pad }} text-sm text-right {{ $weight }}">{{ $fmt($row['projected']) }}</td>

<td class="{{ $pad }} text-sm text-right {{ (float) $row['remaining'] < 0 ? 'text-red-600 dark:text-red-400 font-semibold' : $weight }}">
    {{ $fmt($row['remaining']) }}
</td>

<td class="{{ $pad }} text-right">
    @if($projPct === null)
        <span class="text-sm text-slate-400 dark:text-slate-500">—</span>
    @else
        <div class="flex items-center justify-end gap-2">
            <div class="relative w-16 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="absolute inset-y-0 left-0 {{ $row['over_budget'] ? 'bg-red-300 dark:bg-red-800' : 'bg-[#3F5189]/30 dark:bg-[#4A5A96]/40' }}" style="width: {{ $projPct }}%"></div>
                <div class="absolute inset-y-0 left-0 {{ $row['over_budget'] ? 'bg-red-600 dark:bg-red-500' : 'bg-[#3F5189] dark:bg-[#4A5A96]' }}" style="width: {{ $spentPct }}%"></div>
            </div>
            <span class="text-sm tabular-nums {{ $row['over_budget'] ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-300' }}">
                {{ number_format($row['percent_committed'], 0) }}%
            </span>
        </div>
    @endif
</td>
