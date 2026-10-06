@props([
    // Subcontractors loaded through withDocumentHealth() — the rows' payees.
    'subcontractors',
])

@php
    $flagged = collect($subcontractors)
        ->filter()
        ->unique('id')
        ->filter(fn ($s) => in_array($s->document_health, ['expired', 'expiring_soon'], true))
        ->sortBy(fn ($s) => [$s->document_health === 'expired' ? 0 : 1, $s->company_name])
        ->values();

    $expiredCount = $flagged->where('document_health', 'expired')->count();
    $expiringCount = $flagged->count() - $expiredCount;
    $canOpen = auth()->user()?->can('vendors.view');
@endphp

@if($flagged->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded-xl border p-4 '.($expiredCount > 0
            ? 'border-red-200 bg-red-50 dark:border-red-800/60 dark:bg-red-900/20'
            : 'border-amber-200 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-900/20')]) }}
         role="alert">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 mt-0.5 {{ $expiredCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold {{ $expiredCount > 0 ? 'text-red-800 dark:text-red-300' : 'text-amber-800 dark:text-amber-300' }}">
                    @if($expiredCount > 0 && $expiringCount > 0)
                        {{ __('Subcontractors to check: :expired, :expiring.', [
                            'expired' => trans_choice(':count with expired documents|:count with expired documents', $expiredCount, ['count' => $expiredCount]),
                            'expiring' => trans_choice(':count with documents expiring soon|:count with documents expiring soon', $expiringCount, ['count' => $expiringCount]),
                        ]) }}
                    @elseif($expiredCount > 0)
                        {{ trans_choice(':count subcontractor to be paid has expired documents.|:count subcontractors to be paid have expired documents.', $expiredCount, ['count' => $expiredCount]) }}
                    @else
                        {{ trans_choice(':count subcontractor to be paid has documents expiring soon.|:count subcontractors to be paid have documents expiring soon.', $expiringCount, ['count' => $expiringCount]) }}
                    @endif
                </p>
                <p class="mt-0.5 text-xs {{ $expiredCount > 0 ? 'text-red-700 dark:text-red-400' : 'text-amber-700 dark:text-amber-400' }}">
                    {{ __('Check the compliance documents before releasing a payment.') }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($flagged as $sub)
                        <div wire:key="doc-notice-{{ $sub->id }}" class="inline-flex flex-wrap items-center gap-1.5 rounded-lg bg-white/70 dark:bg-slate-800/60 px-2 py-1 max-w-full">
                            @if($canOpen)
                                <a href="{{ route('subcontractors.show', [$sub->id, 'tab' => 'documents']) }}"
                                   class="text-xs font-medium text-slate-900 dark:text-white hover:underline break-words">{{ $sub->company_name }}</a>
                            @else
                                <span class="text-xs font-medium text-slate-900 dark:text-white break-words">{{ $sub->company_name }}</span>
                            @endif
                            <x-vendor.document-health
                                :state="$sub->document_health"
                                :expired="$sub->expired_documents_count"
                                :expiring="$sub->expiring_documents_count" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
