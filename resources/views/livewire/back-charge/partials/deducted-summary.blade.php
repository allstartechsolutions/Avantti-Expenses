{{--
    What a paid or rejected line deducted, read-only (docs/back-charges.md).
    Expects $contract, $deductions (with backCharge), $amount, $colspan, $rejected.
--}}
@php
    $currency = config('app.currency');
    $locale = config('app.locale');
    $total = round($deductions->sum(fn ($d) => (float) $d->amount), 2);
@endphp
<tr wire:key="contract-{{ $contract->id }}-back-charges-done" class="bg-slate-50 dark:bg-slate-900/40">
    <td colspan="{{ $colspan }}" class="px-4 py-2">
        <div class="ml-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm {{ $rejected ? 'line-through text-slate-400' : 'text-slate-700 dark:text-slate-300' }}">
            @foreach($deductions as $d)
                <span>
                    <a href="{{ route('back-charges.show', $d->back_charge_id) }}" class="font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $d->backCharge?->number }}</a>
                    <span class="text-red-600 dark:text-red-400">− {{ Number::currency($d->amount, $currency, $locale) }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $d->backCharge?->reason }}</span>
                </span>
            @endforeach
            <span class="font-semibold text-slate-900 dark:text-white">{{ __('Check: :amount', ['amount' => Number::currency(round($amount - $total, 2), $currency, $locale)]) }}</span>
        </div>
    </td>
</tr>
