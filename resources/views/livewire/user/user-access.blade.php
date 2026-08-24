@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';

    $readOnly = ! auth()->user()->can('access.manage') || $this->isAdministrator;
    $exceptions = $this->exceptions;
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Access') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $user->name }} · {{ $user->role?->getLabel() ?? __('No role') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" :href="route('users.show', $user->id)" icon="arrow-left">{{ __('Back') }}</x-ui.button>
        </div>
    </div>

    {{-- Two tabs: one sets access, the other explains it. --}}
    <div class="mb-6 border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
            @foreach ([
                'edit' => __('Set access'),
                'effective' => __('What they can do'),
            ] as $key => $title)
                <button type="button" wire:click="$set('tab', '{{ $key }}')"
                        class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $tab === $key ? 'border-[#3F5189] text-[#3F5189] dark:text-[#4A5A96] dark:border-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ $title }}
                </button>
            @endforeach
        </nav>
    </div>

    @if (session('message'))
        <div class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-200">
            {{ session('message') }}
        </div>
    @endif

    {{--
        Three people this screen does not decide for. Each says so plainly:
        promising a control that does nothing is the fault this whole module
        exists to stop.
    --}}
    @if ($tab === 'effective')
        @include('livewire.user.partials.effective-access')
    @elseif ($this->isAdministrator)
        <div class="{{ $card }} p-6 text-center">
            <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <h2 class="mt-3 text-base font-semibold text-slate-900 dark:text-white">{{ __('Administrators are allowed everything') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-lg mx-auto">
                {{ __('An administrator is answered before any permission is read, so exceptions set here would have no effect. To limit what this person can do, move them to another role first.') }}
            </p>
            <div class="mt-4">
                <x-ui.button variant="secondary" :href="route('users.edit', $user->id)" icon="edit">{{ __('Change their role') }}</x-ui.button>
            </div>
        </div>
    @else
        <form wire:submit="save" class="space-y-6">
            {{-- What follows from their role --}}
            <div class="{{ $card }} p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Starting from their role') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                            {{ __('Everything below starts as whatever :role allows. Change anything you like — only the differences are saved, so the rest keeps following the role when the role changes.', ['role' => $user->role?->getLabel() ?? __('their role')]) }}
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        @if ($exceptions['total'] === 0)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                {{ __('Follows the role exactly') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ trans_choice('{1} :count exception|[2,*] :count exceptions', $exceptions['total'], ['count' => $exceptions['total']]) }}
                            </span>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __(':added always allowed · :removed never allowed', ['added' => $exceptions['added'], 'removed' => $exceptions['removed']]) }}
                            </p>
                        @endif
                    </div>
                </div>

                @if ($user->is_guest)
                    <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200 text-sm">
                        <p class="font-medium">{{ __('This person is a guest.') }}</p>
                        <p class="mt-1">{{ __('A guest holds nothing company-wide, whatever is ticked here. What they can do is decided entirely by the projects and job sites they have been added to.') }}</p>
                    </div>
                @elseif ($user->isConfined())
                    <div class="mt-4 p-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 dark:bg-slate-900/40 dark:border-slate-700 dark:text-slate-300 text-sm">
                        <p class="font-medium">{{ __('This person only sees the projects they are added to.') }}</p>
                        <p class="mt-1">{{ __('Anything belonging to a project is answered by their team membership there, not here. Exceptions set below reach the company-wide screens only.') }}</p>
                    </div>
                @endif
            </div>

            {{-- The ceiling --}}
            <div class="{{ $card }} p-5">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Approval limit') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                    {{ __('The most this person may approve, award or pay on the company-wide screens. Leave it blank to follow their role.') }}
                </p>

                <div class="mt-4 max-w-xs">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500 dark:text-slate-400">
                            {{ config('app.currency') }}
                        </span>
                        <input type="number" step="0.01" min="0" wire:model.live="approvalLimit"
                               class="{{ $field }} pl-10"
                               placeholder="{{ $this->roleApprovalLimit ?? __('No limit') }}"
                               @disabled($readOnly)>
                    </div>
                    @error('approvalLimit') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    @if ($this->roleApprovalLimit)
                        {{ __('Their role allows up to :amount.', ['amount' => Number::currency((float) $this->roleApprovalLimit, config('app.currency'), config('app.locale'))]) }}
                    @else
                        {{ __('Their role sets no limit.') }}
                    @endif
                    {{ __('Inside a project, the limit on that project\'s team wins instead.') }}
                </p>
            </div>

            {{-- Money --}}
            <div class="{{ $card }} p-5">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="seeMoney"
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700"
                           @disabled($readOnly)>
                    <span>
                        <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('See monetary figures') }}</span>
                        <span class="block text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Without this, totals, budgets and financial reports are hidden on the company-wide screens. On a project, their membership decides instead.') }}
                        </span>
                    </span>
                </label>
            </div>

            @include('livewire.access.partials.ability-matrix', [
                'sections' => $this->matrix,
                'readOnly' => $readOnly,
                'search' => $matrixSearch,
            ])

            {{-- Footer --}}
            <div class="sticky bottom-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __(':count of :total abilities allowed', ['count' => $this->allowedCount, 'total' => $this->totalAbilities]) }}
                    </p>
                    <div class="flex items-center gap-3">
                        @unless($readOnly)
                            <x-ui.button variant="secondary" type="button" wire:click="followRole"
                                         wire:confirm="{{ __('Put every permission back to whatever their role says?') }}">
                                {{ __('Follow the role') }}
                            </x-ui.button>
                            <x-ui.button variant="primary" type="submit" icon="save">{{ __('Save Access') }}</x-ui.button>
                        @endunless
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
