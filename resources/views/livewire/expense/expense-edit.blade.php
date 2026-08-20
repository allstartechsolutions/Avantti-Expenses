<div>
    @php
        $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $locked = $this->amountsAreLocked();
    @endphp

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Edit Expense') }}</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $expense->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' }}">
                        {{ $expense->status === 'paid' ? __('Paid') : __('Unpaid') }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $expense->project->project_name }}
                    @if($expense->jobSite) / {{ $expense->jobSite->job_site_name }} @endif
                    &bull; {{ $expense->expense_date->translatedFormat('d M Y') }}
                    &bull; {{ $fmt($expense->total_amount) }}
                </p>
            </div>
            <div>
                <x-ui.button variant="secondary" href="{{ $this->backUrl }}" icon="arrow-left">
                    {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    @if($this->lockReason)
        <div class="mb-6 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
            <p class="text-sm text-amber-900 dark:text-amber-200">{{ $this->lockReason }}</p>
        </div>
    @endif

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        @include('livewire.expense.partials.form-body', ['amountsLocked' => $locked])

        @if($expense->receipt_path)
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 px-6 py-4 flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Current receipt') }}</p>
                    <a href="{{ route('files.download', ['path' => $expense->receipt_path]) }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                        {{ basename($expense->receipt_path) }}
                    </a>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model="removeReceipt" class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                    {{ __('Remove it') }}
                </label>
            </div>
        @endif

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4">
            <x-ui.button type="button" variant="secondary" href="{{ $this->backUrl }}">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save">
                {{ __('Save Changes') }}
            </x-ui.button>
        </div>
    </form>

    <!-- History -->
    <div class="mt-8 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('History') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every payment and every edit made to this expense.') }}</p>
        </div>

        @if($expense->changeHistories->isNotEmpty())
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($expense->changeHistories as $entry)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between gap-4 flex-wrap">
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $entry->getActionLabel() }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $entry->changedBy?->name ?? __('Unknown') }} &bull; {{ $entry->created_at->translatedFormat('d M Y H:i') }}
                            </span>
                        </div>
                        @if($entry->changes)
                            <dl class="mt-2 space-y-1">
                                @foreach($entry->changes as $field => $change)
                                    <div class="text-xs text-slate-600 dark:text-slate-400">
                                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $field }}:</span>
                                        <span class="line-through">{{ is_array($change) ? ($change['old'] ?? '—') : '—' }}</span>
                                        <span class="mx-1">&rarr;</span>
                                        <span class="text-slate-900 dark:text-white">{{ is_array($change) ? ($change['new'] ?? '—') : $change }}</span>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-6 py-8 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing has happened to this expense yet.') }}</p>
            </div>
        @endif
    </div>

    @include('livewire.expense.partials.item-modal', ['amountsLocked' => $locked])
</div>
