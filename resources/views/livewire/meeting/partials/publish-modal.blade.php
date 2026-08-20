{{-- Publishing turns a working document into a record, so it says exactly what that means. --}}
<x-ui.modal name="publish-modal" maxWidth="lg" layer="top">
    <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Publish this minute') }}</h3>

        @if($this->unownedActions->isNotEmpty())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20">
                <p class="text-sm font-medium text-red-800 dark:text-red-300">
                    {{ __('Not yet — some action items have nobody against them.') }}
                </p>
                <ul class="mt-2 space-y-1 text-sm text-red-700 dark:text-red-400">
                    @foreach($this->unownedActions as $item)
                        <li>{{ $item->number() }} — {{ $item->title }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-red-700 dark:text-red-400">
                    {{ __('An action item needs an owner and a date, or the minute promises something nobody owns.') }}
                </p>
            </div>
        @else
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ __('The minute is locked, every task on it is photographed as it stands today, and the record stops changing. Corrections after this are logged and shown on the document.') }}
            </p>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Items') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $this->counters['items'] }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Action items') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">{{ $this->counters['actions'] }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Attendance') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-100">
                        {{ __(':present of :invited', ['present' => $this->counters['present'], 'invited' => $this->counters['invited']]) }}
                    </dd>
                </div>
            </dl>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Next meeting') }}</label>
                <input type="date" wire:model="nextMeetingDate"
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
            </div>
        @endif

        <div class="mt-5 flex justify-end gap-3">
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'publish-modal')">{{ __('Not yet') }}</x-ui.button>
            @if($this->unownedActions->isEmpty())
                <x-ui.button variant="primary" wire:click="publish">{{ __('Publish') }}</x-ui.button>
            @endif
        </div>
    </div>
</x-ui.modal>
