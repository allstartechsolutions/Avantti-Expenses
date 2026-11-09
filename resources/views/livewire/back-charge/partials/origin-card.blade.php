{{--
    Back charges that started on this job site or lot (docs/back-charges.md).
    A label only: the money comes off whichever contract it is deducted from.
    Expects $originBackCharges (withDeducted) and $originLabel.
--}}
@can('back-charges.view')
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Back Charges') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $originLabel }}</p>
        </div>
        <div class="p-6">
            @if($originBackCharges->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('None recorded here.') }}</p>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($originBackCharges as $bc)
                        <li wire:key="origin-bc-{{ $bc->id }}" class="py-2 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('back-charges.show', $bc) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $bc->number }}</a>
                                <span class="text-xs text-slate-500 dark:text-slate-400">· {{ $bc->subcontractor?->company_name }}</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $bc->reason }}</p>
                            </div>
                            <div class="text-right whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900 dark:text-white"><x-ui.money :amount="$bc->amount" /></div>
                                <div class="text-xs {{ $bc->getStatusColor() === 'green' ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $bc->isFullyDeducted() ? $bc->getStatusLabel() : __(':amount left', ['amount' => Number::currency($bc->getRemaining(), config('app.currency'), config('app.locale'))]) }}
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endcan
