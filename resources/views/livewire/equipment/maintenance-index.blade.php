@php
    $select = 'block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $tab = fn ($key) => $view === $key
        ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]'
        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300';
    $urgencyChip = [
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'scheduled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'cancelled' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
    ];
    $closed = in_array($view, ['completed', 'cancelled'], true);
@endphp
<div>
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Maintenance') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every service across the fleet: what is overdue, what comes next, what is under way. Schedule and complete work on the equipment page.') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button variant="secondary" href="{{ route('equipment.index') }}" icon="arrow-left">{{ __('Equipment') }}</x-ui.button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <button type="button" wire:click="setView('overdue')" class="text-left bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5 hover:border-[#3F5189]">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Overdue or due') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $counts['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ $counts['overdue'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Past the date, or the meter reached') }}</p>
        </button>
        <button type="button" wire:click="setView('due_soon')" class="text-left bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5 hover:border-[#3F5189]">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Due soon') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $counts['due_soon'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $counts['due_soon'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Within 30 days or the meter margin') }}</p>
        </button>
        <button type="button" wire:click="setView('')" class="text-left bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5 hover:border-[#3F5189]">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Scheduled') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $counts['scheduled'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Planned, not yet near') }}</p>
        </button>
        <button type="button" wire:click="setView('in_progress')" class="text-left bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5 hover:border-[#3F5189]">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('In progress') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $counts['in_progress'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-slate-900 dark:text-white' }}">{{ $counts['in_progress'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Started, not yet completed') }}</p>
        </button>
    </div>

    <div class="border-b border-slate-200 dark:border-slate-700 mb-6">
        <nav class="-mb-px flex flex-wrap gap-x-8" aria-label="{{ __('Sections') }}">
            <button type="button" wire:click="setView('')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('') }}">{{ __('All open') }}</button>
            <button type="button" wire:click="setView('overdue')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('overdue') }}">{{ __('Overdue or due') }}</button>
            <button type="button" wire:click="setView('due_soon')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('due_soon') }}">{{ __('Due soon') }}</button>
            <button type="button" wire:click="setView('in_progress')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('in_progress') }}">{{ __('In progress') }}</button>
            <button type="button" wire:click="setView('completed')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('completed') }}">{{ __('Completed') }}</button>
            <button type="button" wire:click="setView('cancelled')" class="inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $tab('cancelled') }}">{{ __('Cancelled') }}</button>
        </nav>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 p-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-3">
                <label for="typeFilter" class="sr-only">{{ __('Type') }}</label>
                <select id="typeFilter" wire:model.live="typeFilter" class="{{ $select }}">
                    <option value="">{{ __('Type: all') }}</option>
                    @foreach(\App\Models\EquipmentMaintenance::TYPES as $t)
                        <option value="{{ $t }}">{{ \App\Models\EquipmentMaintenance::typeLabel($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-5">
                <label for="equipmentFilter" class="sr-only">{{ __('Equipment') }}</label>
                <select id="equipmentFilter" wire:model.live="equipmentFilter" class="{{ $select }}">
                    <option value="">{{ __('Equipment: all') }}</option>
                    @foreach($equipmentOptions as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-4">
                <label for="projectFilter" class="sr-only">{{ __('Project') }}</label>
                <select id="projectFilter" wire:model.live="projectFilter" class="{{ $select }}">
                    <option value="">{{ __('Assigned to: anywhere') }}</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if($this->hasFilters())
            <div class="mt-4 flex justify-end"><x-ui.button variant="secondary" size="sm" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button></div>
        @endif
    </div>

    @if($grouped->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Nothing here') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if($closed)
                    {{ __('No maintenance has reached this state yet.') }}
                @else
                    {{ __('No open maintenance matches. Schedule one from the Maintenance tab of a piece of equipment.') }}
                @endif
            </p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($grouped as $month => $rows)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden" wire:key="month-{{ $month }}">
                    <div class="px-6 py-3 bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $month === 'none' ? __('No date — due by meter') : \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}
                        </h3>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count maintenance|:count maintenances', $rows->count(), ['count' => $rows->count()]) }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead>
                                <tr>
                                    <th scope="col" class="{{ $th }}">{{ __('Equipment') }}</th>
                                    <th scope="col" class="{{ $th }}">{{ __('Maintenance') }}</th>
                                    <th scope="col" class="{{ $th }}">{{ __('Due') }}</th>
                                    <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                                    <th scope="col" class="{{ $th }}">{{ __('Where') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($rows as $m)
                                    @php $u = $closed ? $m->status : $m->urgency(); @endphp
                                    <tr wire:key="maintenance-{{ $m->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-6 py-3">
                                            <a href="{{ route('equipment.show', ['equipment' => $m->equipment_id, 'tab' => 'maintenance']) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $m->equipment->name }}</a>
                                            @if($m->equipment->asset_tag || $m->equipment->plate)<div class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $m->equipment->asset_tag ?? $m->equipment->plate }}</div>@endif
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="text-sm text-slate-900 dark:text-white">{{ $m->title }}</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $m->getTypeLabel() }}@if($m->plan) &bull; {{ $m->plan->intervalLabel($m->equipment) }}@endif</div>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                                            @if($closed)
                                                {{ $m->completed_date?->appDate() ?? $m->updated_at->appDate() }}
                                            @else
                                                {{ $m->dueLabel() }}
                                                @if($m->meterRemaining() !== null)<div class="text-xs text-slate-500 dark:text-slate-400">{{ $m->meterRemaining() >= 0 ? __(':amount to go', ['amount' => $m->equipment->formatMeter($m->meterRemaining())]) : __('Meter reached') }}</div>@endif
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $urgencyChip[$u] ?? $urgencyChip['scheduled'] }}">{{ \App\Models\EquipmentMaintenance::urgencyLabel($u) }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300">
                                            {{ $m->equipment->project?->project_name ?? '—' }}@if($m->equipment->jobSite) / {{ $m->equipment->jobSite->job_site_name }}@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
