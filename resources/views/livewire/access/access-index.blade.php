@php
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $progress = $this->rolloutProgress;
@endphp

<div>
    <!-- Page header -->
    <div class="mb-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Roles & Access') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('What each role may do anywhere in the system. Access to a single project or job site is given on that project\'s Team tab.') }}
                </p>
            </div>
            @if($activeTab === 'roles')
                @can('access.manage')
                    <x-ui.button variant="primary" wire:click="newRole" icon="plus">{{ __('New Role') }}</x-ui.button>
                @endcan
            @endif
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-8 overflow-x-auto">
                <button wire:click="$set('activeTab', 'roles')"
                        class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'roles' ? 'border-[#3F5189] text-[#3F5189] dark:text-[#4A5A96] dark:border-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Roles') }}
                </button>
                <button wire:click="$set('activeTab', 'templates')"
                        class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'templates' ? 'border-[#3F5189] text-[#3F5189] dark:text-[#4A5A96] dark:border-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Templates') }}
                </button>
            </nav>
        </div>
    </div>

    @if($activeTab === 'templates')
        <livewire:access.template-manager />
    @else

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- Honesty about the rollout: this screen would otherwise promise
         enforcement that is not switched on yet. --}}
    @if($progress['pending'] > 0)
        <div class="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.71-3.03l-6.93-11a2 2 0 00-3.42 0l-6.93 11A2 2 0 005.07 19z"></path>
                </svg>
                <div class="text-sm">
                    <p class="font-medium">{{ __('The permission module is still being rolled out, one module at a time.') }}</p>
                    <p class="mt-1">
                        {{ __(':enforced of :total areas are enforced by these settings. The rest still use the old rules — you can set them here now, and they take effect when that module is converted.', ['enforced' => $progress['enforced'], 'total' => $progress['total']]) }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Roles -->
    <div class="{{ $card }} overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Roles') }}</h2>
            <span class="text-sm text-slate-500 dark:text-slate-400">{{ trans_choice('{1} :count role|[2,*] :count roles', $this->roles->count(), ['count' => $this->roles->count()]) }}</span>
        </div>

        @forelse($this->roles as $role)
            @php $areas = $this->areasByRole[$role->id] ?? []; @endphp
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white capitalize">{{ __($role->name) }}</h3>
                            @if($role->isSystem())
                                <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Built in') }}</span>
                            @endif
                            @if($role->isAdmin())
                                <span class="px-2 py-0.5 text-xs rounded-full bg-[#3F5189]/10 text-[#3F5189] dark:bg-[#4A5A96]/20 dark:text-[#8fa0d8]">{{ __('Allowed everything') }}</span>
                            @elseif($role->confinesToAssignments())
                                <span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __($role->access_scope->label()) }}</span>
                            @endif
                        </div>

                        @if($role->description)
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __($role->description) }}</p>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                            <span>{{ trans_choice('{0} No one holds this role|{1} :count person|[2,*] :count people', $role->users_count, ['count' => $role->users_count]) }}</span>
                            <span aria-hidden="true">·</span>
                            @if($role->isAdmin())
                                <span>{{ __('Every ability, including any added later') }}</span>
                            @else
                                <span>{{ __(':granted of :total abilities', ['granted' => $role->ability_rows_count, 'total' => $this->totalAbilities]) }}</span>
                            @endif
                        </div>

                        @if(! $role->isAdmin() && count($areas))
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach(array_slice($areas, 0, 8) as $areaName)
                                    <span class="px-2 py-0.5 text-xs rounded bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __($areaName) }}</span>
                                @endforeach
                                @if(count($areas) > 8)
                                    <span class="px-2 py-0.5 text-xs rounded bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">{{ __('+:count more', ['count' => count($areas) - 8]) }}</span>
                                @endif
                            </div>
                        @elseif(! $role->isAdmin())
                            <p class="mt-3 text-sm text-amber-700 dark:text-amber-400">{{ __('This role can do nothing yet.') }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <x-ui.button variant="secondary" size="sm" wire:click="editRole({{ $role->id }})" icon="edit">
                            {{ $role->isAdmin() ? __('View') : __('Edit access') }}
                        </x-ui.button>
                        @can('access.manage')
                            @unless($role->isSystem())
                                <x-ui.button
                                    variant="danger"
                                    size="sm"
                                    icon="trash"
                                    wire:click="deleteRole({{ $role->id }})"
                                    wire:confirm="{{ __('Delete this role? People holding it must be moved first.') }}">
                                    {{ __('Delete') }}
                                </x-ui.button>
                            @endunless
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-12 text-center">
                <p class="text-slate-500 dark:text-slate-400">{{ __('No roles yet.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- The trail -->
    <div class="{{ $card }} mt-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Recent access changes') }}</h2>
        </div>
        @forelse($this->audits as $audit)
            <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0 flex flex-wrap items-baseline justify-between gap-2">
                <span class="text-sm text-slate-700 dark:text-slate-300">{{ $audit->summary }}</span>
                <span class="text-xs text-slate-400 dark:text-slate-500">
                    {{ $audit->actor?->name ?? __('System') }} · {{ $audit->created_at?->diffForHumans() }}
                </span>
            </div>
        @empty
            <div class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('Nothing has been changed yet. Every grant and revoke is recorded here.') }}
            </div>
        @endforelse
    </div>

    @include('livewire.access.partials.role-modal')

    @endif
</div>
