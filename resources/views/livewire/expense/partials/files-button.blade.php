{{--
    The paperclip on an expense row: how many files the expense carries (its
    receipt plus attachments) and a click that opens it at them. Expects
    $expense loaded with withCount('attachments'). Nothing renders without files.
--}}
@php $fileCount = (int) $expense->attachments_count + ($expense->receipt_path ? 1 : 0); @endphp
@if($fileCount > 0)
    <button type="button"
            wire:click="openExpenseFiles({{ $expense->id }})"
            class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-[#3F5189] dark:text-slate-400 dark:hover:text-[#8B9BD0]"
            title="{{ __('Open the files') }}"
            aria-label="{{ trans_choice(':count file|:count files', $fileCount, ['count' => $fileCount]) }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
        {{ $fileCount }}
    </button>
@endif
