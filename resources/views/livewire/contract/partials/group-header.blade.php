{{-- A group's heading row in the contract payment tables (/contract-payments and the batch
     edit screen): name, count and the whole group's money. $trailing = columns after Balance. --}}
<tr wire:key="group-{{ $header['key'] }}" class="bg-slate-100 dark:bg-slate-900/60">
    <td colspan="4" class="px-4 py-2.5">
        <div class="text-sm font-semibold text-slate-900 dark:text-white">
            {{ $header['label'] }}
            @if($header['sub'])
                <span class="font-normal text-slate-500 dark:text-slate-400">· {{ $header['sub'] }}</span>
            @endif
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
            {{ trans_choice(':count contract|:count contracts', $header['totals']['count'], ['count' => $header['totals']['count']]) }}
            @if($header['continued'])
                · {{ __('continued from the previous page') }}
            @endif
        </div>
    </td>
    <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm font-semibold text-slate-900 dark:text-white">
        {{ Number::currency($header['totals']['amount'], config('app.currency'), config('app.locale')) }}
    </td>
    <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm font-semibold {{ $header['totals']['change_orders'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
        @if($header['totals']['change_orders'] != 0)
            {{ $header['totals']['change_orders'] > 0 ? '+' : '' }}{{ Number::currency($header['totals']['change_orders'], config('app.currency'), config('app.locale')) }}
        @else
            <span class="text-slate-400 dark:text-slate-500">-</span>
        @endif
    </td>
    <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm font-semibold text-green-600 dark:text-green-400">
        {{ Number::currency($header['totals']['paid'], config('app.currency'), config('app.locale')) }}
    </td>
    <td class="px-4 py-2.5 whitespace-nowrap text-right text-sm font-semibold {{ $header['totals']['balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
        {{ Number::currency($header['totals']['balance'], config('app.currency'), config('app.locale')) }}
    </td>
    <td colspan="{{ $trailing }}"></td>
</tr>
