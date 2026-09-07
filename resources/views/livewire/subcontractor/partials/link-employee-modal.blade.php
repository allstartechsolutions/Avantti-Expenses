{{--
    Link an existing employee row to the same worker's row at another company.
    Full page: the candidate list carries company, title, phone, e-mail and tax
    id side by side, which is what the decision is made on.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $th = 'px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $linking = $this->linkingEmployee;
    $candidates = $this->linkCandidates;
@endphp

<x-ui.modal name="link-employee-modal" maxWidth="full">
    @if($linking)
        <form wire:submit="linkEmployee" class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Link :name to another record', ['name' => $linking->name]) }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ __('Say which record at another company is the same worker. Both records stay as they are; they are counted as one worker from now on.') }}</p>
                    </div>
                    <button type="button" wire:click="cancelLink" class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- The record being linked -->
                    <div class="space-y-4">
                        <div class="{{ $card }} space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('This record') }}</h3>
                            <dl class="space-y-2 text-sm">
                                <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Name') }}</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $linking->name }}</dd></div>
                                <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Company') }}</dt><dd class="text-slate-900 dark:text-white">{{ $subcontractor->company_name }}</dd></div>
                                <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Title') }}</dt><dd class="text-slate-900 dark:text-white">{{ $linking->title ?? '—' }}</dd></div>
                                <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Phone') }}</dt><dd class="text-slate-900 dark:text-white">{{ $linking->formatted_phone ?? '—' }}</dd></div>
                                <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Email') }}</dt><dd class="text-slate-900 dark:text-white break-all">{{ $linking->email ?? '—' }}</dd></div>
                                <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Tax ID') }}</dt><dd class="font-mono text-slate-900 dark:text-white">{{ $linking->tax_id ?? '—' }}</dd></div>
                            </dl>
                        </div>

                        @if($linking->siblings()->isNotEmpty())
                            <div class="{{ $card }} space-y-2">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Already linked to') }}</h3>
                                <ul class="space-y-1 text-sm">
                                    @foreach($linking->siblings() as $sibling)
                                        <li class="text-slate-900 dark:text-white">
                                            {{ $sibling->name }}
                                            <span class="text-slate-500 dark:text-slate-400">{{ __('at :company', ['company' => $sibling->subcontractor?->company_name]) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('A new link joins this worker; if the other record is already linked elsewhere, the two workers become one.') }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Candidates -->
                    <div class="lg:col-span-2 space-y-4">
                        @if($candidates['suggested']->isNotEmpty())
                            <div class="{{ $card }} space-y-3">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Looks like the same worker') }}</h3>
                                @include('livewire.subcontractor.partials.link-candidates-table', ['rows' => $candidates['suggested'], 'withReasons' => true])
                            </div>
                        @endif

                        <div class="{{ $card }} space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Search other companies') }}</h3>
                            <input type="text" wire:model.live.debounce.300ms="link_search" class="{{ $field }}" placeholder="{{ __('Name, phone, e-mail, tax id or company…') }}">
                            @if(mb_strlen(trim($link_search)) < 2)
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Type at least two characters to search every other company\'s employees.') }}</p>
                            @elseif($candidates['found']->isEmpty())
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Nobody at another company matches that. The worker may not have been added there yet — add them on that company\'s page and the link will be offered.') }}</p>
                            @else
                                @include('livewire.subcontractor.partials.link-candidates-table', ['rows' => $candidates['found']->map(fn ($e) => ['employee' => $e, 'reasons' => []]), 'withReasons' => false])
                            @endif
                        </div>

                        <div class="{{ $card }} space-y-2">
                            <label for="link_reason" class="{{ $label }}">{{ __('Why are these the same worker?') }}</label>
                            <input type="text" id="link_reason" wire:model="link_reason" class="{{ $field }}" placeholder="{{ __('e.g. Same foreman, confirmed on site') }}">
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Optional, but it is what the next reader will see when they wonder who decided this.') }}</p>
                            @error('link_reason') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            @error('link_target_id') <span class="block text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        @if($link_target_id)
                            {{ __('One record chosen. The link is recorded with your name, the date and the reason.') }}
                        @else
                            {{ __('Choose a record above to enable the link.') }}
                        @endif
                    </p>
                    <div class="flex items-center gap-3">
                        <x-ui.button type="button" variant="secondary" wire:click="cancelLink">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon="check" :disabled="! $link_target_id">{{ __('Link as the same worker') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    @endif
</x-ui.modal>
