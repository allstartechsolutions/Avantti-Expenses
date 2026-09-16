{{--
    The optional equipment tag on an expense. Included by the shared expense
    form and both legacy modals. Expects: $equipmentOptions, $maintenanceOptions
--}}
@php $eqField = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white'; @endphp
@if($equipmentOptions->isNotEmpty() || $expense_equipment_id)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="expense-equipment" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Equipment') }}</label>
            <select id="expense-equipment" wire:model.live="expense_equipment_id" class="{{ $eqField }}">
                <option value="">{{ __('Not for a piece of equipment') }}</option>
                @foreach($equipmentOptions as $eq)
                    <option value="{{ $eq->id }}">{{ $eq->name }}@if($eq->asset_tag || $eq->plate) ({{ $eq->asset_tag ?? $eq->plate }})@endif</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Fuel, parts, a repair: tag the machine and it shows on its cost history.') }}</p>
            @error('expense_equipment_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
        @if($expense_equipment_id)
            <div>
                <label for="expense-maintenance" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('For which maintenance') }}</label>
                <select id="expense-maintenance" wire:model="expense_equipment_maintenance_id" class="{{ $eqField }}">
                    <option value="">{{ __('None in particular') }}</option>
                    @foreach($maintenanceOptions as $m)
                        <option value="{{ $m->id }}">{{ $m->title }} — {{ \App\Models\EquipmentMaintenance::statusLabel($m->status) }}@if($m->completed_date) ({{ $m->completed_date->appDate() }})@elseif($m->scheduled_date) ({{ $m->scheduled_date->appDate() }})@endif</option>
                    @endforeach
                </select>
                @error('expense_equipment_maintenance_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        @endif
    </div>
@endif
