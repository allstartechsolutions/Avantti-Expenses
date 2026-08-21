{{--
    The role editor: name, description and the whole ability matrix.

    Full page rather than a dialog because the matrix is a hundred-odd repeating
    rows with running totals — see the modal size rule in CLAUDE.md.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
    $role = $this->editingRole;
    $readOnly = $this->editingAdmin || ! auth()->user()?->can('access.manage');
@endphp

<x-ui.modal name="role-modal" maxWidth="full">
    @if($showRoleModal)
    <form wire:submit="save" class="flex min-h-screen flex-col">
        <!-- Header -->
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $role ? __('Role: :name', ['name' => __($role->name)]) : __('New Role') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        @if($this->editingAdmin)
                            {{ __('Administrators are allowed everything that is switched on, including abilities added in future updates. There is nothing to choose.') }}
                        @else
                            {{ __(':count of :total abilities granted', ['count' => $this->grantedCount, 'total' => $this->totalAbilities]) }}
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="closeRoleModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
            <!-- The role itself -->
            <div class="{{ $card }} p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="{{ $field }}"
                           placeholder="{{ __('e.g. Procurement') }}"
                           @disabled($role?->isSystem() || $readOnly)>
                    @if($role?->isSystem())
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Built-in roles cannot be renamed.') }}</p>
                    @endif
                    @error('name') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="{{ $label }}">{{ __('Description') }}</label>
                    <input type="text" wire:model="description" class="{{ $field }}"
                           placeholder="{{ __('What this role is for, in one line') }}" @disabled($readOnly)>
                    @error('description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Which projects this role can reach at all. The single most
                 consequential setting on the screen, so it is the first thing
                 under the name and it says who it would affect. --}}
            @unless($this->editingAdmin)
                <div class="{{ $card }} p-5">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Which projects and job sites can they see?') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('This is what confines somebody to their own work. It applies to job sites, expenses, documents, reports — everything that belongs to a project.') }}
                    </p>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 p-4 rounded-lg border cursor-pointer
                            {{ $accessScope === 'company' ? 'border-[#3F5189] bg-[#3F5189]/5 dark:border-[#4A5A96] dark:bg-[#4A5A96]/10' : 'border-slate-200 dark:border-slate-700' }}">
                            <input type="radio" value="company" wire:model.live="accessScope"
                                   class="mt-1 h-4 w-4 border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600"
                                   @disabled($readOnly)>
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('Every project and job site') }}</span>
                                <span class="block text-sm text-slate-500 dark:text-slate-400">{{ __('How the system works today. They can open anything, subject to the abilities below.') }}</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 p-4 rounded-lg border cursor-pointer
                            {{ $accessScope === 'assigned' ? 'border-amber-400 bg-amber-50 dark:border-amber-600 dark:bg-amber-900/20' : 'border-slate-200 dark:border-slate-700' }}">
                            <input type="radio" value="assigned" wire:model.live="accessScope"
                                   class="mt-1 h-4 w-4 border-slate-300 text-amber-600 focus:ring-amber-500 dark:border-slate-600"
                                   @disabled($readOnly)>
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('Only the ones they are added to') }}</span>
                                <span class="block text-sm text-slate-500 dark:text-slate-400">{{ __('They see a project only after somebody adds them to its team, or to one of its job sites. Everything else is as if it did not exist.') }}</span>
                            </span>
                        </label>
                    </div>

                    {{-- The lists are not scoped yet: saying "they only see
                         their own projects" while the project index still shows
                         every one of them would be a promise the code does not
                         keep. It comes out on its own once M2 lands. --}}
                    @unless(\App\Services\AbilityCatalog::isSwept('project'))
                        <div class="mt-3 p-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 dark:bg-slate-900/40 dark:border-slate-700 dark:text-slate-300 text-sm">
                            <p class="font-medium">{{ __('This setting is recorded but not enforced yet.') }}</p>
                            <p class="mt-1">{{ __('The project and job-site screens have not been converted, so the lists still show everything to everybody. Choosing it now means it takes effect the moment they are.') }}</p>
                        </div>
                    @endunless

                    @if($accessScope === 'assigned')
                        <div class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200 text-sm">
                            <p>
                                {{ trans_choice(
                                    '{0} Nobody holds this role yet.|{1} :count person holds this role and follows it. Add them to the projects they work on, or they will see nothing.|[2,*] :count people hold this role and follow it. Add them to the projects they work on, or they will see nothing.',
                                    $this->followersOfEditedRole,
                                    ['count' => $this->followersOfEditedRole]
                                ) }}
                            </p>
                            <p class="mt-1">{{ __('Somebody whose own setting has been changed on their user record keeps that instead.') }}</p>
                        </div>
                    @endif
                    @error('accessScope') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <!-- Money -->
                <div class="{{ $card }} p-5">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="seeMoney"
                               class="mt-1 h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700"
                               @disabled($readOnly)>
                        <span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('See monetary figures') }}</span>
                            <span class="block text-sm text-slate-500 dark:text-slate-400">
                                {{ __('Without this, totals, budgets and financial reports are hidden on the company-wide screens. On a project, the person\'s membership decides instead.') }}
                            </span>
                        </span>
                    </label>
                </div>

                <!-- The approval ceiling -->
                <div class="{{ $card }} p-5">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Approval limit') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('The most anybody with this role may approve, award or pay on the company-wide screens. Leave it blank for no limit.') }}
                    </p>

                    <div class="mt-4 max-w-xs">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ config('app.currency') }}
                            </span>
                            <input type="number" step="0.01" min="0" wire:model="approvalLimit"
                                   class="{{ $field }} pl-10" placeholder="{{ __('No limit') }}" @disabled($readOnly)>
                        </div>
                        @error('approvalLimit') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Inside a project, a person\'s own limit on that project\'s team wins instead. One person can also be given a different limit on their user record.') }}
                    </p>
                </div>

                @include('livewire.access.partials.ability-matrix', [
                    'sections' => $this->matrix,
                    'readOnly' => $readOnly,
                    'search' => $matrixSearch,
                ])
            @endunless
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @unless($this->editingAdmin)
                        {{ __(':count of :total abilities granted', ['count' => $this->grantedCount, 'total' => $this->totalAbilities]) }}
                    @endunless
                </p>
                <div class="flex items-center gap-3">
                    <x-ui.button variant="secondary" type="button" wire:click="closeRoleModal">{{ __('Cancel') }}</x-ui.button>
                    @unless($readOnly)
                        <x-ui.button variant="primary" type="submit" icon="save">{{ __('Save Role') }}</x-ui.button>
                    @endunless
                </div>
            </div>
        </div>
    </form>
    @endif
</x-ui.modal>
