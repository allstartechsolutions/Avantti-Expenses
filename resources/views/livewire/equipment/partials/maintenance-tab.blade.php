@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2';
    $err = 'text-sm text-red-600 dark:text-red-400';
    $canMaintain = auth()->user()->can('equipment.maintain') && ! $equipment->isRetired();
    $urgencyChip = [
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'due_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'scheduled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'cancelled' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
    ];
@endphp
<div class="space-y-6">
    <!-- Plans -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Maintenance plans') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Recurring services — every N days, every N km or hours, or both; the next one is always scheduled.') }}</p>
            </div>
            @if($canMaintain)
                <x-ui.button variant="secondary" icon="plus" wire:click="startPlan">{{ __('Add plan') }}</x-ui.button>
            @endif
        </div>
        @if($plans->isEmpty())
            <p class="p-6 text-sm text-slate-500 dark:text-slate-400">{{ __('No recurring plan yet. Oil changes, inspections and certifications belong here so they are never forgotten.') }}</p>
        @else
            <div class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach($plans as $plan)
                    @php $next = $plan->openOccurrence; if ($next) { $next->setRelation('equipment', $equipment); $next->setRelation('plan', $plan); } @endphp
                    <div class="px-6 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 {{ $plan->is_active ? '' : 'opacity-60' }}" wire:key="plan-{{ $plan->id }}">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $plan->title }} <span class="text-xs font-normal text-slate-500 dark:text-slate-400">&bull; {{ \App\Models\EquipmentMaintenance::typeLabel($plan->maintenance_type) }}</span></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $plan->intervalLabel($equipment) }}
                                @if($plan->last_completed_date) &bull; {{ __('last done') }} {{ $plan->last_completed_date->appDate() }}@if($plan->last_completed_meter !== null) ({{ $equipment->formatMeter((float) $plan->last_completed_meter) }})@endif @endif
                            </div>
                            @if($plan->notes)<div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $plan->notes }}</div>@endif
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            @if(! $plan->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Inactive') }}</span>
                            @elseif($next)
                                @php $u = $next->urgency(); @endphp
                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Next') }}: {{ $next->dueLabel() }}</span>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $urgencyChip[$u] ?? $urgencyChip['scheduled'] }}">{{ \App\Models\EquipmentMaintenance::urgencyLabel($u) }}</span>
                            @else
                                <span class="text-xs text-amber-600 dark:text-amber-400">{{ __('Log a meter reading to schedule the first one.') }}</span>
                            @endif
                            @if($canMaintain)
                                <x-ui.icon-button variant="ghost" size="sm" icon="edit" wire:click="startPlan({{ $plan->id }})" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}" />
                                <x-ui.button variant="ghost" size="sm" wire:click="togglePlan({{ $plan->id }})">{{ $plan->is_active ? __('Deactivate') : __('Reactivate') }}</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Upcoming -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Upcoming and in progress') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Due when the date arrives or the meter is reached — whichever comes first.') }}</p>
            </div>
            @if($canMaintain)
                <x-ui.button variant="primary" icon="plus" wire:click="startSchedule">{{ __('Schedule a one-off') }}</x-ui.button>
            @endif
        </div>
        @if($openMaintenances->isEmpty())
            <p class="p-6 text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing scheduled. Add a plan for recurring services, or schedule a one-off repair.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Maintenance') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Due') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($openMaintenances as $m)
                            @php $u = $m->urgency(); @endphp
                            <tr wire:key="open-{{ $m->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-3">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $m->title }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $m->getTypeLabel() }}@if($m->plan) &bull; {{ __('from plan') }} "{{ $m->plan->title }}"@endif</div>
                                    @if($m->description)<div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $m->description }}</div>@endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                                    {{ $m->dueLabel() }}
                                    @if($m->daysUntilDue() !== null)<div class="text-xs text-slate-500 dark:text-slate-400">{{ $m->daysUntilDue() >= 0 ? trans_choice('in :count day|in :count days', $m->daysUntilDue(), ['count' => $m->daysUntilDue()]) : trans_choice(':count day ago|:count days ago', abs($m->daysUntilDue()), ['count' => abs($m->daysUntilDue())]) }}</div>@endif
                                    @if($m->meterRemaining() !== null)<div class="text-xs text-slate-500 dark:text-slate-400">{{ $m->meterRemaining() >= 0 ? __(':amount to go', ['amount' => $equipment->formatMeter($m->meterRemaining())]) : __('Meter reached') }}</div>@endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $urgencyChip[$u] ?? $urgencyChip['scheduled'] }}">{{ \App\Models\EquipmentMaintenance::urgencyLabel($u) }}</span></td>
                                <td class="px-6 py-3 whitespace-nowrap text-right">
                                    @if($canMaintain)
                                        <div class="flex items-center justify-end gap-2">
                                            @if($m->status === 'scheduled')
                                                <x-ui.button variant="secondary" size="sm" wire:click="startMaintenance({{ $m->id }})">{{ __('Start') }}</x-ui.button>
                                            @else
                                                <x-ui.button variant="secondary" size="sm" wire:click="startFinding({{ $m->id }})">{{ __('Record finding') }}</x-ui.button>
                                            @endif
                                            @can('company-expenses.create')
                                                <x-ui.button variant="ghost" size="sm" icon="plus" href="{{ route('company-expenses.create', ['equipment' => $equipment->id, 'maintenance' => $m->id]) }}">{{ __('Add cost') }}</x-ui.button>
                                            @endcan
                                            <x-ui.button variant="success" size="sm" icon="check" wire:click="startComplete({{ $m->id }})">{{ __('Complete') }}</x-ui.button>
                                            <x-ui.icon-button variant="ghost" size="sm" icon="x" wire:click="startCancel({{ $m->id }})" title="{{ __('Cancel maintenance') }}" aria-label="{{ __('Cancel maintenance') }}" />
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- History -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Service history') }}</h3>
        </div>
        @if($closedMaintenances->isEmpty())
            <p class="p-6 text-sm text-slate-500 dark:text-slate-400">{{ __('No maintenance has been completed yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Date') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Maintenance') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Meter') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Performed by') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Findings') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Cost') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($closedMaintenances as $m)
                            <tr wire:key="closed-{{ $m->id }}" class="{{ $m->status === 'cancelled' ? 'opacity-60' : '' }}">
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $m->completed_date?->appDate() ?? $m->updated_at->appDate() }}</td>
                                <td class="px-6 py-3">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $m->title }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $m->getTypeLabel() }}@if($m->completion_notes) &bull; {{ $m->completion_notes }}@endif @if($m->cancel_reason) &bull; {{ $m->cancel_reason }}@endif</div>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $m->meter_at_completion !== null ? $equipment->formatMeter((float) $m->meter_at_completion) : '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                                    {{ $m->performed_by === 'vendor' ? ($m->supplier?->name ?? \App\Models\EquipmentMaintenance::performerLabel('vendor')) : ($m->performedByUser?->name ?? ($m->performed_by ? \App\Models\EquipmentMaintenance::performerLabel($m->performed_by) : '—')) }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $m->findings_count }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-right"><x-ui.money class="text-sm text-slate-900 dark:text-white" :amount="($costByMaintenance[$m->id] ?? 0) / 100" rollup /></td>
                                <td class="px-6 py-3 whitespace-nowrap"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $urgencyChip[$m->status] ?? $urgencyChip['scheduled'] }}">{{ $m->getStatusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Plan dialog --}}
    @if($showPlanModal)
        <x-ui.modal name="plan-modal" :show="true" maxWidth="2xl">
            <form wire:submit="savePlan">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $editingPlanId ? __('Edit plan') : __('Add maintenance plan') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Give an interval in days, in meter units, or both — whichever comes first is when it is due.') }}</p>
                    </div>
                    <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="cancelPlan" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label for="plan-title" class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                            <input id="plan-title" type="text" wire:model="plan_title" maxlength="150" class="{{ $field }}" placeholder="{{ __('e.g. Oil change, Annual inspection') }}">
                            @error('plan_title') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="plan-type" class="{{ $label }}">{{ __('Type') }}</label>
                            <select id="plan-type" wire:model="plan_type" class="{{ $field }}">
                                @foreach(\App\Models\EquipmentMaintenancePlan::TYPES as $t)
                                    <option value="{{ $t }}">{{ \App\Models\EquipmentMaintenance::typeLabel($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="plan-days" class="{{ $label }}">{{ __('Every (days)') }}</label>
                            <input id="plan-days" type="number" min="1" max="3650" wire:model="plan_interval_days" class="{{ $field }}" placeholder="180">
                            @error('plan_interval_days') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="plan-meter" class="{{ $label }}">{{ __('Every (:unit)', ['unit' => $equipment->meterUnit() ?: __('meter')]) }}</label>
                            <input id="plan-meter" type="number" min="1" wire:model="plan_interval_meter" class="{{ $field }}" placeholder="10000" @if(! $equipment->hasMeter()) disabled @endif>
                            @if(! $equipment->hasMeter())<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('This equipment has no meter.') }}</p>@endif
                            @error('plan_interval_meter') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="plan-lead" class="{{ $label }}">{{ __('Due-soon margin (:unit)', ['unit' => $equipment->meterUnit() ?: __('meter')]) }}</label>
                            <input id="plan-lead" type="number" min="0" wire:model="plan_meter_lead" class="{{ $field }}" placeholder="{{ __('a tenth of the interval') }}" @if(! $equipment->hasMeter()) disabled @endif>
                            @error('plan_meter_lead') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="plan-notes" class="{{ $label }}">{{ __('Notes') }}</label>
                        <textarea id="plan-notes" wire:model="plan_notes" rows="2" class="{{ $field }}" placeholder="{{ __('Parts, checklist, who usually does it…') }}"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelPlan" icon="x">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save">{{ __('Save plan') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- One-off dialog --}}
    @if($showScheduleModal)
        <x-ui.modal name="schedule-modal" :show="true" maxWidth="2xl">
            <form wire:submit="saveSchedule">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Schedule a maintenance') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('A repair or a one-time service. Due on the date, at the reading, or whichever comes first.') }}</p>
                    </div>
                    <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="cancelSchedule" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label for="m-title" class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                            <input id="m-title" type="text" wire:model="m_title" maxlength="150" class="{{ $field }}" placeholder="{{ __('e.g. Replace hydraulic hose') }}">
                            @error('m_title') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="m-type" class="{{ $label }}">{{ __('Type') }}</label>
                            <select id="m-type" wire:model="m_type" class="{{ $field }}">
                                @foreach(\App\Models\EquipmentMaintenance::TYPES as $t)
                                    <option value="{{ $t }}">{{ \App\Models\EquipmentMaintenance::typeLabel($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="m-date" class="{{ $label }}">{{ __('Date') }}</label>
                            <x-ui.date-input id="m-date" wire:model="m_scheduled_date" class="{{ $field }}" />
                            @error('m_scheduled_date') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="m-meter" class="{{ $label }}">{{ __('Or at (:unit)', ['unit' => $equipment->meterUnit() ?: __('meter')]) }}</label>
                            <input id="m-meter" type="number" step="0.1" min="0" wire:model="m_due_meter" class="{{ $field }}" @if(! $equipment->hasMeter()) disabled @endif>
                            @error('m_due_meter') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="m-description" class="{{ $label }}">{{ __('Description') }}</label>
                        <textarea id="m-description" wire:model="m_description" rows="3" class="{{ $field }}" placeholder="{{ __('What needs doing and why.') }}"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelSchedule" icon="x">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save">{{ __('Schedule') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- Complete dialog --}}
    @if($showCompleteModal)
        @php $completing = $openMaintenances->firstWhere('id', $completingId); @endphp
        <x-ui.modal name="complete-modal" :show="true" maxWidth="2xl">
            <form wire:submit="saveComplete">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Complete: :title', ['title' => $completing?->title ?? '']) }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('The meter reading is logged, the findings you tick are closed, and a plan schedules its next service from today.') }}</p>
                    </div>
                    <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="cancelComplete" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="c-date" class="{{ $label }}">{{ __('Completed on') }} <span class="text-red-500">*</span></label>
                            <x-ui.date-input id="c-date" wire:model="c_completed_date" class="{{ $field }}" />
                            @error('c_completed_date') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="c-meter" class="{{ $label }}">{{ $equipment->meterLabel() }} @if($equipment->hasMeter())<span class="text-red-500">*</span>@endif</label>
                            <input id="c-meter" type="number" step="0.1" min="0" wire:model="c_meter" class="{{ $field }}" @if(! $equipment->hasMeter()) disabled @endif>
                            @if($equipment->hasMeter() && $equipment->current_meter !== null)<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Last reading: :reading', ['reading' => $equipment->formatMeter((float) $equipment->current_meter)]) }}</p>@endif
                            @error('c_meter') <span class="{{ $err }}">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="c-performer" class="{{ $label }}">{{ __('Performed by') }}</label>
                            <select id="c-performer" wire:model.live="c_performed_by" class="{{ $field }}">
                                <option value="internal">{{ \App\Models\EquipmentMaintenance::performerLabel('internal') }}</option>
                                <option value="vendor">{{ \App\Models\EquipmentMaintenance::performerLabel('vendor') }}</option>
                            </select>
                        </div>
                        <div>
                            @if($c_performed_by === 'vendor')
                                <label for="c-supplier" class="{{ $label }}">{{ __('Vendor') }} <span class="text-red-500">*</span></label>
                                <select id="c-supplier" wire:model="c_supplier_id" class="{{ $field }}">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach($this->suppliers as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                    @endforeach
                                </select>
                                @error('c_supplier_id') <span class="{{ $err }}">{{ $message }}</span> @enderror
                            @else
                                <label for="c-person" class="{{ $label }}">{{ __('Person') }}</label>
                                <select id="c-person" wire:model="c_performed_by_user_id" class="{{ $field }}">
                                    <option value="">{{ __('Not recorded') }}</option>
                                    @foreach($this->assignPeople as $person)
                                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label for="c-notes" class="{{ $label }}">{{ __('What was done') }}</label>
                        <textarea id="c-notes" wire:model="c_notes" rows="3" class="{{ $field }}" placeholder="{{ __('Parts replaced, checks made, anything for the next person.') }}"></textarea>
                    </div>
                    @if($openFindingsList->isNotEmpty())
                        <div>
                            <p class="{{ $label }}">{{ __('Open findings fixed in this maintenance') }}</p>
                            <div class="space-y-2">
                                @foreach($openFindingsList as $f)
                                    <label class="flex items-start gap-3 cursor-pointer" wire:key="resolve-{{ $f->id }}">
                                        <input type="checkbox" value="{{ $f->id }}" wire:model="c_resolve" class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] dark:bg-slate-700">
                                        <span class="text-sm text-slate-700 dark:text-slate-300"><span class="font-medium">{{ $f->getSeverityLabel() }}</span> — {{ $f->description }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelComplete" icon="x">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="success" icon="check">{{ __('Mark completed') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- Cancel dialog --}}
    @if($showCancelModal)
        <x-ui.modal name="cancel-maintenance-modal" :show="true" maxWidth="lg">
            <form wire:submit="saveCancel" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Cancel this maintenance') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('A plan\'s service cancelled is a skip: the next one is scheduled from the point this one was due.') }}</p>
                <div>
                    <label for="cancel-reason" class="{{ $label }}">{{ __('Reason') }} <span class="text-red-500">*</span></label>
                    <input id="cancel-reason" type="text" wire:model="cancel_reason" maxlength="255" class="{{ $field }}">
                    @error('cancel_reason') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelCancel" icon="x">{{ __('Keep it') }}</x-ui.button>
                    <x-ui.button type="submit" variant="danger" icon="trash">{{ __('Cancel maintenance') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @include('livewire.equipment.partials.finding-dialogs')
</div>
