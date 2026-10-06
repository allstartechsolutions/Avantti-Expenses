@props([
    'subcontractor',
    // Subcontractor::documentsNeedingAttention() — expired or due soon, soonest first.
    'documents',
    // 'compact' drops the per-document list, for a modal that already has a lot on it.
    'compact' => false,
])

@if($subcontractor && $documents->isNotEmpty())
    @php
        $expired = $documents->filter(fn ($d) => $d->days_until_expiry < 0)->count();
        $expiring = $documents->count() - $expired;
        $tone = $expired > 0
            ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-800/60 dark:bg-red-900/20 dark:text-red-300'
            : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/20 dark:text-amber-300';

        $headline = match (true) {
            $expired > 0 && $expiring > 0 => __(':name has :expired and :expiring.', [
                'name' => $subcontractor->company_name,
                'expired' => trans_choice(':count expired document|:count expired documents', $expired, ['count' => $expired]),
                'expiring' => trans_choice(':count document expiring soon|:count documents expiring soon', $expiring, ['count' => $expiring]),
            ]),
            $expired > 0 => trans_choice(':name has :count expired document.|:name has :count expired documents.', $expired, ['name' => $subcontractor->company_name, 'count' => $expired]),
            default => trans_choice(':name has :count document expiring soon.|:name has :count documents expiring soon.', $expiring, ['name' => $subcontractor->company_name, 'count' => $expiring]),
        };
    @endphp

    <div {{ $attributes->merge(['class' => 'rounded-lg border p-4 '.$tone]) }} role="alert">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">{{ $headline }}</p>
                <p class="mt-0.5 text-xs opacity-90">{{ __('Check the compliance documents before releasing a payment.') }}</p>

                {{-- Which documents and their dates are the vendor's file; the
                     warning itself is for anyone paying, the detail is not. --}}
                @if(! $compact && auth()->user()?->can('vendors.view'))
                    <ul class="mt-3 space-y-1.5">
                        @foreach($documents as $document)
                            @php $days = $document->days_until_expiry; @endphp
                            <li wire:key="doc-alert-{{ $document->id }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-4 text-sm">
                                <span class="font-medium break-words">{{ $document->documentType?->name ?? $document->file_name }}</span>
                                <span class="text-xs sm:text-sm sm:whitespace-nowrap">
                                    @if($days < 0)
                                        {{ trans_choice('Expired :date (:count day ago)|Expired :date (:count days ago)', abs($days), ['date' => $document->expiration_date->appDate(), 'count' => abs($days)]) }}
                                    @elseif($days === 0)
                                        {{ __('Expires today') }}
                                    @else
                                        {{ trans_choice('Expires :date (in :count day)|Expires :date (in :count days)', $days, ['date' => $document->expiration_date->appDate(), 'count' => $days]) }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('vendors.view')
                    <a href="{{ route('subcontractors.show', [$subcontractor->id, 'tab' => 'documents']) }}"
                       class="mt-3 inline-flex items-center gap-1 text-xs font-semibold underline hover:no-underline">
                        {{ __('Review the documents') }}
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endcan
            </div>
        </div>
    </div>
@endif
