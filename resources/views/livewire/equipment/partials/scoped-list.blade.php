{{--
    The equipment on one project or job site. Included by both pages so the
    two levels cannot drift apart. Expects: $here, $past, $available, $showLocation
--}}
@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $select = 'block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $canAssign = auth()->user()->can('equipment.assign');
    $statusChip = [
        'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'in_maintenance' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    ];
@endphp
<div class="space-y-6">
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Equipment here now') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ trans_choice(':count piece of equipment|:count pieces of equipment', $here->count(), ['count' => $here->count()]) }}</p>
        </div>
        @if($canAssign)
            <form wire:submit="assignHere" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
                <div class="min-w-64">
                    <label for="assignEquipmentId" class="sr-only">{{ __('Equipment') }}</label>
                    <select id="assignEquipmentId" wire:model="assignEquipmentId" class="{{ $select }}">
                        <option value="">{{ __('Send equipment here…') }}</option>
                        @foreach($available as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}@if($item->asset_tag) ({{ $item->asset_tag }})@endif</option>
                        @endforeach
                    </select>
                    @error('assignEquipmentId') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div class="w-40">
                    <label for="assignStartedAt" class="sr-only">{{ __('From') }}</label>
                    <x-ui.date-input id="assignStartedAt" wire:model="assignStartedAt" class="{{ $select }}" />
                    @error('assignStartedAt') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <x-ui.button type="submit" variant="primary" icon="plus">{{ __('Send here') }}</x-ui.button>
            </form>
        @endif
    </div>

    @if($here->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No equipment here') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if($canAssign)
                    {{ __('Pick a piece of equipment above to send it here; it stays on the register and shows on this page until it is sent back or elsewhere.') }}
                @else
                    {{ __('Nothing from the register is assigned here at the moment.') }}
                @endif
            </p>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Equipment') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Type') }}</th>
                            @if($showLocation)<th scope="col" class="{{ $th }}">{{ __('Location') }}</th>@endif
                            <th scope="col" class="{{ $th }}">{{ __('Responsible') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Since') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Meter') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($here as $item)
                            <tr wire:key="here-{{ $item->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('equipment.show', $item) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $item->name }}</a>
                                    @if($item->asset_tag || $item->plate)<div class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $item->asset_tag ?? $item->plate }}</div>@endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $item->getTypeLabel() }}</td>
                                @if($showLocation)
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $item->jobSite?->job_site_name ?? __('Project (General)') }}</td>
                                @endif
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $item->responsible?->name ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $item->assigned_at?->appDate() ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusChip[$item->status] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">{{ $item->getStatusLabel() }}</span></td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $item->formatMeter($item->current_meter !== null ? (float) $item->current_meter : null) }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-right">
                                    @if($canAssign)
                                        <x-ui.button variant="secondary" size="sm" icon="arrow-left" wire:click="sendBack({{ $item->id }})" wire:confirm="{{ __('Send :name back? It will be unassigned until it is sent somewhere else.', ['name' => $item->name]) }}">{{ __('Send back') }}</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($past->isNotEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Been here before') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead>
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Equipment') }}</th>
                            @if($showLocation)<th scope="col" class="{{ $th }}">{{ __('Location') }}</th>@endif
                            <th scope="col" class="{{ $th }}">{{ __('From') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('To') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Responsible') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Sent by') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($past as $stay)
                            <tr wire:key="past-{{ $stay->id }}">
                                <td class="px-6 py-3 text-sm"><a href="{{ route('equipment.show', $stay->equipment_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $stay->equipment?->name ?? '—' }}</a></td>
                                @if($showLocation)<td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->jobSite?->job_site_name ?? __('Project (General)') }}</td>@endif
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->started_at->appDate() }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->ended_at?->appDate() }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->responsible?->name ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->assignedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
