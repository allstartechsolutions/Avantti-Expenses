{{--
    The template editor. Full page: the matrix is a hundred-odd repeating rows.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
    $template = $this->editing;
    $readOnly = ! auth()->user()?->can('access.manage');
@endphp

<x-ui.modal name="template-modal" maxWidth="full">
    @if($showModal)
    <form wire:submit="save" class="flex min-h-screen flex-col">
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $template ? __('Template: :name', ['name' => __($template->name)]) : __('New Template') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $this->levelName($level) }} · {{ __(':count abilities granted', ['count' => $this->grantedCount]) }}
                        @if($template && $template->memberships_count > 0)
                            · {{ trans_choice('{1} Used by :count person|[2,*] Used by :count people', $template->memberships_count, ['count' => $template->memberships_count]) }}
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="closeModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
            @if($template && $template->memberships_count > 0)
                <div class="p-4 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-300 text-sm">
                    {{ trans_choice(
                        '{1} :count person is using this template. Changing it here does not change what they can already do — their access was copied when they were added.|[2,*] :count people are using this template. Changing it here does not change what they can already do — their access was copied when they were added.',
                        $template->memberships_count,
                        ['count' => $template->memberships_count]
                    ) }}
                </div>
            @endif

            <div class="{{ $card }} p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="{{ $field }}" placeholder="{{ __('e.g. Site Supervisor') }}" @disabled($readOnly)>
                    @error('name') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $label }}">{{ __('Description') }}</label>
                    <input type="text" wire:model="description" class="{{ $field }}" placeholder="{{ __('Who this is for, in one line') }}" @disabled($readOnly)>
                    @error('description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Used on') }}</label>
                    <select wire:model.live="level" class="{{ $field }}" @disabled($readOnly)>
                        <option value="project">{{ __('A whole project') }}</option>
                        <option value="job_site">{{ __('One job site') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('A project template also covers every job site under it, unless that site gives the person something of its own.') }}
                    </p>
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Approval limit') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="approvalLimit" class="{{ $field }}"
                           placeholder="{{ __('No limit') }}" @disabled($readOnly || $isGuest)>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Caps what this person may approve, award or release. Leave empty for no ceiling.') }}
                    </p>
                    @error('approvalLimit') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="isGuest"
                               class="mt-1 h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700"
                               @disabled($readOnly)>
                        <span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('For outsiders (guests)') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('A client, engineer or vendor. Never offered when adding staff, never sees money, and cannot hold a sensitive action.') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="canSeeMoney"
                               class="mt-1 h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700"
                               @disabled($readOnly || $isGuest)>
                        <span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('See monetary figures') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Off, and every total, budget figure and financial report is hidden from this person here.') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            @include('livewire.access.partials.ability-matrix', [
                'sections' => $this->matrix,
                'readOnly' => $readOnly,
                'search' => $matrixSearch,
            ])
        </div>

        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __(':count abilities granted', ['count' => $this->grantedCount]) }}
                    @if($isGuest) · {{ __('Sensitive actions are dropped from a guest template.') }} @endif
                </p>
                <div class="flex items-center gap-3">
                    <x-ui.button variant="secondary" type="button" wire:click="closeModal">{{ __('Cancel') }}</x-ui.button>
                    @unless($readOnly)
                        <x-ui.button variant="primary" type="submit" icon="save">{{ __('Save Template') }}</x-ui.button>
                    @endunless
                </div>
            </div>
        </div>
    </form>
    @endif
</x-ui.modal>
