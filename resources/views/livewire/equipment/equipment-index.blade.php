@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $select = 'block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $statusChip = [
        'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'in_maintenance' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'retired' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'sold' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
    $canDelete = auth()->user()->can('equipment.delete');
    $urgencyChip = [
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'scheduled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
@endphp
<div>
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Equipment') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Vehicles, machinery and tools: what the company has, where it is, and when it is next due for service.') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                @can('equipment.view')
                    <x-ui.button variant="secondary" href="{{ route('equipment.maintenance.index') }}" icon="settings">{{ __('Maintenance') }}</x-ui.button>
                @endcan
                @can('equipment.create')
                    <x-ui.button variant="primary" href="{{ route('equipment.create') }}" icon="plus">{{ __('Add Equipment') }}</x-ui.button>
                @endcan
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('In service') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $counts['total'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Not retired or sold') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Active') }}</p>
            <p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ $counts['active'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Available for work') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('In maintenance') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $counts['in_maintenance'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $counts['in_maintenance'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('A service is under way') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Maintenance overdue') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $counts['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ $counts['overdue'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Past the date or the meter') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Due in 30 days') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $counts['due_soon'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $counts['due_soon'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Or within the meter margin') }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 p-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-5">
                <label for="search" class="sr-only">{{ __('Search equipment') }}</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" id="search" wire:model.live.debounce.300ms="search" class="{{ $select }} pl-10 placeholder-slate-400" placeholder="{{ __('Search by name, tag, plate, serial, make or model…') }}">
                </div>
            </div>
            <div class="md:col-span-2">
                <label for="typeFilter" class="sr-only">{{ __('Type') }}</label>
                <select id="typeFilter" wire:model.live="typeFilter" class="{{ $select }}">
                    <option value="">{{ __('Type: all') }}</option>
                    @foreach($types as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="statusFilter" class="sr-only">{{ __('Status') }}</label>
                <select id="statusFilter" wire:model.live="statusFilter" class="{{ $select }}">
                    <option value="">{{ __('Status: in service') }}</option>
                    @foreach($statuses as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="assignmentFilter" class="sr-only">{{ __('Where') }}</label>
                <select id="assignmentFilter" wire:model.live="assignmentFilter" class="{{ $select }}">
                    <option value="">{{ __('Where: anywhere') }}</option>
                    <option value="unassigned">{{ __('Unassigned') }}</option>
                    <option value="person">{{ __('With a person, no site') }}</option>
                    @foreach($projects as $project)
                        <option value="project:{{ $project->id }}">{{ $project->project_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if($this->hasFilters())
            <div class="mt-4 flex justify-end">
                <x-ui.button variant="secondary" size="sm" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button>
            </div>
        @endif
    </div>

    @if($equipment->count() > 0)
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Equipment') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Type') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Tag / Plate') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Where') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Meter') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Next maintenance') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($equipment as $item)
                            @php $next = $nextMaintenance[$item->id] ?? null; @endphp
                            <tr wire:key="equipment-{{ $item->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 {{ $item->isRetired() ? 'opacity-60' : '' }}">
                                <td class="px-6 py-4">
                                    <a href="{{ route('equipment.show', $item) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $item->name }}</a>
                                    @if($item->make || $item->model || $item->year)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ trim($item->make.' '.$item->model.' '.$item->year) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $item->getTypeLabel() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-700 dark:text-slate-300">
                                    {{ $item->asset_tag ?? '—' }}@if($item->plate) <span class="text-slate-400">/</span> {{ $item->plate }}@endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusChip[$item->status] ?? $statusChip['active'] }}">{{ $item->getStatusLabel() }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                    @if($item->project || $item->jobSite)
                                        <div>{{ $item->project?->project_name }}@if($item->jobSite) <span class="text-slate-400">/</span> {{ $item->jobSite->job_site_name }}@endif</div>
                                        @if($item->responsible)<div class="text-xs text-slate-500 dark:text-slate-400">{{ $item->responsible->name }}</div>@endif
                                    @elseif($item->responsible)
                                        <div>{{ $item->responsible->name }}</div>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">{{ __('Unassigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $item->formatMeter($item->current_meter !== null ? (float) $item->current_meter : null) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($next)
                                        @php $next->setRelation('equipment', $item); $u = $next->urgency(); @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $urgencyChip[$u] ?? $urgencyChip['scheduled'] }}">{{ \App\Models\EquipmentMaintenance::urgencyLabel($u) }}</span>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $next->dueLabel() }}</div>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <x-ui.view-edit-buttons :viewRoute="route('equipment.show', $item)" :editRoute="auth()->user()->can('equipment.edit') ? route('equipment.edit', $item) : null" />
                                        @if($canDelete && $item->hasNoRecords())
                                            <x-ui.button variant="danger" size="sm" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Delete :name? Nothing has been recorded under it.', ['name' => $item->name]) }}" title="{{ __('Delete') }}"></x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">{{ $equipment->links() }}</div>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 4h8m-8 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"></path></svg>
                @if($this->hasFilters())
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No equipment matches these filters') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Retired and sold equipment only shows when its status is chosen.') }}</p>
                    <div class="mt-6"><x-ui.button variant="secondary" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button></div>
                @else
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No equipment yet') }}</h3>
                    @can('equipment.create')
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Register the vehicles, machines and tools the company owns, leases or rents; then schedule their maintenance and send them to a site.') }}</p>
                        <div class="mt-6"><x-ui.button variant="primary" href="{{ route('equipment.create') }}" icon="plus">{{ __('Add Equipment') }}</x-ui.button></div>
                    @else
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing has been registered yet. You can see equipment but not add it — ask an administrator if that is wrong.') }}</p>
                    @endcan
                @endif
            </div>
        </div>
    @endif
</div>
