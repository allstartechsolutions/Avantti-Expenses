<div>
    <!-- Page header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Meeting Series') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('The meetings you hold again and again, with who normally attends and what they normally cover.') }}
            </p>
        </div>
        <x-ui.button variant="primary" icon="plus" wire:click="openCreate">{{ __('New Series') }}</x-ui.button>
    </div>

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

    <div class="mb-4">
        <x-ui.toggle wire:model.live="showInactive" :checked="$showInactive"
                     label="{{ __('Show inactive series') }}" />
    </div>

    @if($this->seriesList->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('No meeting series yet') }}</h3>
            <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500 dark:text-slate-400">
                {{ __('A series is a meeting you hold again and again. It is what makes open items carry from one meeting to the next one of the same kind, instead of mixing with every other meeting in the company.') }}
            </p>
            <div class="mt-4">
                <x-ui.button variant="primary" size="sm" icon="plus" wire:click="openCreate">{{ __('New Series') }}</x-ui.button>
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Series') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Cadence') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Attendees') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Covers') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Meetings') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($this->seriesList as $series)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40" wire:key="series-{{ $series->id }}">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs rounded bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-slate-600 dark:text-slate-300">{{ $series->code }}</span>
                                        <span class="font-medium text-slate-900 dark:text-white">{{ $series->name }}</span>
                                        @if(! $series->is_active)
                                            <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Inactive') }}</span>
                                        @endif
                                    </div>
                                    @if($series->description)
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $series->description }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $series->getCadenceLabel() }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $series->members_count }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                    {{ $series->scopes_count > 0
                                        ? trans_choice(':count location|:count locations', $series->scopes_count, ['count' => $series->scopes_count])
                                        : __('nothing yet') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $series->meetings_count }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.button variant="ghost" size="sm" wire:click="toggleActive({{ $series->id }})">
                                            {{ $series->is_active ? __('Deactivate') : __('Activate') }}
                                        </x-ui.button>
                                        <x-ui.button variant="secondary" size="sm" icon="edit" wire:click="edit({{ $series->id }})">{{ __('Edit') }}</x-ui.button>
                                        @if(auth()->user()?->can('meetings.delete') && $series->meetings_count === 0)
                                            <x-ui.button variant="danger" size="sm" icon="trash"
                                                         wire:click="delete({{ $series->id }})"
                                                         wire:confirm="{{ __('Delete this series? It has held no meetings.') }}">
                                                {{ __('Delete') }}
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @include('livewire.meeting.partials.series-form-modal')
</div>
