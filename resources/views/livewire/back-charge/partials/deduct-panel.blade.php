{{--
    The back charges a payment may take, under its row (docs/back-charges.md).
    Shared by the payment batch and Contract Payments.

    Expects:
      $contract  the row's contract
      $rows      [['bc' => BackCharge, 'available' => float], ...]
      $colspan   the table's column count
    Binds to $wire.payAmounts[id] and $wire.payDeductions[id][bc].
--}}
@php
    $currency = config('app.currency');
    $locale = config('app.locale');
@endphp
{{-- Shown once the row has an amount (or already deducts), or when its
     "open back charges" tag is clicked: a subcontractor with many contracts
     would otherwise get a panel under every one. --}}
<tr wire:key="contract-{{ $contract->id }}-back-charges" class="bg-amber-50/60 dark:bg-amber-900/10"
    x-data="{
                id: @js((string) $contract->id),
                forced: false,
                money: new Intl.NumberFormat(@js(str_replace('_', '-', $locale)), { style: 'currency', currency: @js($currency) }),
                get amount() { return parseFloat(($wire.payAmounts ?? {})[this.id]) || 0; },
                value(bc) { return parseFloat((($wire.payDeductions ?? {})[this.id] ?? {})[bc]) || 0; },
                get deducted() {
                    return Math.round(Object.values(($wire.payDeductions ?? {})[this.id] ?? {}).map(v => parseFloat(v)).filter(v => v > 0).reduce((s, v) => s + v, 0) * 100) / 100;
                },
                get net() { return Math.round((this.amount - this.deducted) * 100) / 100; },
                takeAll(bc, available) {
                    const room = this.amount > 0 ? this.amount - (this.deducted - this.value(bc)) : available;
                    $wire.set('payDeductions.' + this.id + '.' + bc, Math.max(0, Math.min(available, room)).toFixed(2), false);
                },
             }"
    x-on:toggle-back-charges-{{ $contract->id }}.window="forced = ! forced"
    x-show="forced || amount > 0 || deducted > 0" x-cloak>
    <td colspan="{{ $colspan }}" class="px-4 py-3">
        <div class="ml-4 max-w-4xl">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 uppercase">
                    {{ trans_choice(':count open back charge for this subcontractor|:count open back charges for this subcontractor', count($rows), ['count' => count($rows)]) }}
                </p>
                <p class="text-sm text-slate-700 dark:text-slate-300" x-show="deducted > 0" x-cloak>
                    <span x-text="money.format(amount)"></span>
                    <span class="text-red-600 dark:text-red-400">− <span x-text="money.format(deducted)"></span></span>
                    = <span class="font-semibold" :class="net < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'">{{ __('check') }} <span x-text="money.format(net)"></span></span>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-slate-500 dark:text-slate-400">
                            <th class="text-left pb-1 pr-4 font-medium">{{ __('Back charge') }}</th>
                            <th class="text-left pb-1 pr-4 font-medium">{{ __('Reason') }}</th>
                            <th class="text-right pb-1 pr-4 font-medium">{{ __('Amount') }}</th>
                            <th class="text-right pb-1 pr-4 font-medium">{{ __('Available') }}</th>
                            <th class="text-right pb-1 font-medium">{{ __('Deduct now') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-200/60 dark:divide-amber-800/40">
                        @foreach($rows as $row)
                            @php $bc = $row['bc']; @endphp
                            <tr wire:key="contract-{{ $contract->id }}-bc-{{ $bc->id }}">
                                <td class="py-1.5 pr-4 whitespace-nowrap">
                                    <a href="{{ route('back-charges.show', $bc) }}" target="_blank" class="font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $bc->number }}</a>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">· {{ $bc->date->appDate() }}</span>
                                    @if($bc->subcontractorEmployee)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Employee: :name', ['name' => $bc->subcontractorEmployee->name]) }}</div>
                                    @endif
                                </td>
                                <td class="py-1.5 pr-4 text-slate-700 dark:text-slate-300 max-w-xs truncate" title="{{ $bc->reason }}">{{ $bc->reason }}</td>
                                <td class="py-1.5 pr-4 text-right whitespace-nowrap text-slate-700 dark:text-slate-300">{{ Number::currency($bc->amount, $currency, $locale) }}</td>
                                <td class="py-1.5 pr-4 text-right whitespace-nowrap font-medium text-amber-700 dark:text-amber-400">{{ Number::currency($row['available'], $currency, $locale) }}</td>
                                <td class="py-1.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <button type="button" x-on:click="takeAll({{ $bc->id }}, {{ $row['available'] }})"
                                                class="text-xs font-medium text-[#3F5189] dark:text-indigo-300 hover:underline"
                                                title="{{ __('Deduct what is left, up to the payment') }}">{{ __('All') }}</button>
                                        <input type="number" step="0.01" min="0" max="{{ $row['available'] }}"
                                               wire:model="payDeductions.{{ $contract->id }}.{{ $bc->id }}"
                                               placeholder="0.00"
                                               aria-label="{{ __('Deduct from :number', ['number' => $bc->number]) }}"
                                               class="w-28 px-2 py-1 text-sm text-right border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    </div>
                                    @error('payDeductions.'.$contract->id.'.'.$bc->id)
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400 whitespace-normal">{{ $message }}</p>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('payDeductions.'.$contract->id)
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Leave a box empty to skip that back charge this time. What is not deducted stays open for the next payment.') }}</p>
        </div>
    </td>
</tr>
