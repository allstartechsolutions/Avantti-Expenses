@php
    $fmt = fn ($v) => $v === null ? '—' : Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $row = 'flex items-start justify-between gap-4 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0';
    $k = 'text-sm text-slate-500 dark:text-slate-400';
    $v = 'text-sm text-slate-900 dark:text-white text-right';
    $age = $equipment->purchase_date ? (int) $equipment->purchase_date->diffInYears(now()) : null;
    $warranty = $equipment->warrantyState();
    $urgencyChip = [
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'scheduled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
@endphp
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Identity') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8">
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Name') }}</span><span class="{{ $v }}">{{ $equipment->name }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Type') }}</span><span class="{{ $v }}">{{ $equipment->getTypeLabel() }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Asset tag') }}</span><span class="{{ $v }} font-mono">{{ $equipment->asset_tag ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Make') }}</span><span class="{{ $v }}">{{ $equipment->make ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Model') }}</span><span class="{{ $v }}">{{ $equipment->model ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Year') }}</span><span class="{{ $v }}">{{ $equipment->year ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Serial number') }}</span><span class="{{ $v }} font-mono">{{ $equipment->serial_number ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Plate') }}</span><span class="{{ $v }} font-mono">{{ $equipment->plate ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('VIN / chassis') }}</span><span class="{{ $v }} font-mono">{{ $equipment->vin ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Status') }}</span><span class="{{ $v }}">{{ $equipment->getStatusLabel() }}@if($equipment->retired_at) <span class="text-xs text-slate-500">({{ $equipment->retired_at->appDate() }})</span>@endif</span></div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Ownership and purchase') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8">
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Ownership') }}</span><span class="{{ $v }}">{{ $equipment->getOwnershipLabel() }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Supplier') }}</span><span class="{{ $v }}">{{ $equipment->supplier?->name ?? '—' }}</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Purchase date') }}</span><span class="{{ $v }}">{{ $equipment->purchase_date?->appDate() ?? '—' }}@if($age !== null) <span class="text-xs text-slate-500">({{ trans_choice(':count year old|:count years old', $age, ['count' => $age]) }})</span>@endif</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Purchase cost') }}</span><span class="{{ $v }}">@if($equipment->purchase_cost === null)—@else<x-ui.money :amount="$equipment->purchase_cost" rollup />@endif</span></div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Warranty until') }}</span>
                    <span class="{{ $v }}">
                        {{ $equipment->warranty_until?->appDate() ?? '—' }}
                        @if($warranty === 'active')<span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('In warranty') }}</span>
                        @elseif($warranty === 'expired')<span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Warranty expired') }}</span>@endif
                    </span>
                </div>
                <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Total cost of ownership') }}</span><span class="{{ $v }} font-semibold"><x-ui.money :amount="$equipment->totalCostOfOwnership()" rollup /></span></div>
            </div>
        </div>

        @if($equipment->notes)
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">{{ __('Notes') }}</h3>
                <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $equipment->notes }}</p>
            </div>
        @endif

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('History') }}</h3>
            @if($histories->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing has happened to this equipment yet.') }}</p>
            @else
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @foreach($histories as $entry)
                        <div class="flex items-start gap-3 text-sm" wire:key="history-{{ $entry->id }}">
                            <span class="mt-1.5 h-2 w-2 rounded-full flex-shrink-0
                                @switch($entry->getActionColor())
                                    @case('green') bg-green-500 @break
                                    @case('yellow') bg-amber-500 @break
                                    @case('red') bg-red-500 @break
                                    @case('blue') bg-blue-500 @break
                                    @default bg-slate-400
                                @endswitch"></span>
                            <div class="min-w-0 flex-1">
                                <div class="text-slate-900 dark:text-white">{{ $entry->getActionLabel() }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $entry->changedBy?->name ?? __('System') }} &bull; {{ $entry->created_at->appDateTime() }}</div>
                                @if(is_array($entry->changes) && $entry->changes !== [])
                                    <ul class="mt-1 text-xs text-slate-600 dark:text-slate-300 space-y-0.5">
                                        @foreach($entry->changes as $fieldName => $change)
                                            <li><span class="font-medium">{{ __(ucfirst(str_replace('_', ' ', $fieldName))) }}:</span>
                                                @if(is_array($change) && array_key_exists('old', $change))
                                                    <span class="line-through text-slate-400">{{ is_scalar($change['old']) ? $change['old'] : '—' }}</span> → {{ is_scalar($change['new']) ? $change['new'] : '—' }}
                                                @else
                                                    {{ is_scalar($change) ? $change : '' }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Right now') }}</h3>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Where') }}</span>
                <span class="{{ $v }}">
                    @if($equipment->project || $equipment->jobSite)
                        {{ $equipment->project?->project_name }}@if($equipment->jobSite) / {{ $equipment->jobSite->job_site_name }}@endif
                    @else
                        <span class="text-slate-400 dark:text-slate-500">{{ __('Unassigned') }}</span>
                    @endif
                </span>
            </div>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Responsible') }}</span><span class="{{ $v }}">{{ $equipment->responsible?->name ?? '—' }}</span></div>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Since') }}</span><span class="{{ $v }}">{{ $equipment->assigned_at?->appDate() ?? '—' }}</span></div>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ $equipment->meterLabel() }}</span>
                <span class="{{ $v }}">
                    @if($equipment->hasMeter())
                        {{ $equipment->formatMeter($equipment->current_meter !== null ? (float) $equipment->current_meter : null) }}
                        @if($equipment->current_meter_at)<div class="text-xs text-slate-500">{{ trans_choice(':count day ago|:count days ago', (int) $equipment->current_meter_at->diffInDays(now()), ['count' => (int) $equipment->current_meter_at->diffInDays(now())]) }}</div>@endif
                    @else
                        <span class="text-slate-400 dark:text-slate-500">{{ __('No meter') }}</span>
                    @endif
                </span>
            </div>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Next maintenance') }}</span>
                <span class="{{ $v }}">
                    @if($nextMaintenance)
                        @php $u = $nextMaintenance->urgency(); @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $urgencyChip[$u] ?? $urgencyChip['scheduled'] }}">{{ \App\Models\EquipmentMaintenance::urgencyLabel($u) }}</span>
                        <div class="text-xs text-slate-500 mt-0.5">{{ $nextMaintenance->title }} &bull; {{ $nextMaintenance->dueLabel() }}</div>
                    @else
                        <span class="text-slate-400 dark:text-slate-500">{{ __('Nothing scheduled') }}</span>
                    @endif
                </span>
            </div>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Open findings') }}</span><span class="{{ $v }} {{ $openFindings > 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' : '' }}">{{ $openFindings }}</span></div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">{{ __('Record') }}</h3>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Created by') }}</span><span class="{{ $v }}">{{ $equipment->createdBy?->name ?? '—' }}</span></div>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Created at') }}</span><span class="{{ $v }}">{{ $equipment->created_at?->appDateTime() ?? '—' }}</span></div>
            <div class="{{ $row }}"><span class="{{ $k }}">{{ __('Last updated') }}</span><span class="{{ $v }}">{{ $equipment->updated_at?->appDateTime() ?? '—' }}</span></div>
        </div>
    </div>
</div>
