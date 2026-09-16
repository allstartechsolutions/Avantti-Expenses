<?php

namespace App\Livewire\Concerns;

use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\ModuleAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The optional equipment tag on an expense form — which machine the money
 * was for, and which of its maintenances, if any. Shared by the three
 * expense forms (the shared create/edit form and the two legacy modals) and
 * one partial, so the tag reads the same everywhere. docs/equipment-module.md
 */
trait PicksEquipment
{
    public $expense_equipment_id = '';

    public $expense_equipment_maintenance_id = '';

    public function updatedExpenseEquipmentId(): void
    {
        $this->expense_equipment_maintenance_id = '';
    }

    /** The tag is offered only where the Equipment module is on and the person may see equipment. */
    protected function mayTagEquipment(): bool
    {
        return ModuleAccess::isEnabled('equipment') && (bool) auth()->user()?->can('equipment.view');
    }

    /** The fleet, for the picker: in service, plus the one the expense already carries. */
    protected function equipmentOptions(): Collection
    {
        if (! $this->mayTagEquipment()) {
            // Only what the row already carries, so the select can still show it.
            return $this->expense_equipment_id ? Equipment::whereKey($this->expense_equipment_id)->get() : collect();
        }

        return Equipment::query()
            ->where(fn ($q) => $q->inService()->orWhere('id', (int) $this->expense_equipment_id ?: 0))
            ->orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'plate', 'status']);
    }

    /** The maintenances of the chosen equipment a cost can be filed against: open ones and the last completed. */
    protected function maintenanceOptions(): Collection
    {
        if (! $this->expense_equipment_id) {
            return collect();
        }

        return EquipmentMaintenance::query()
            ->where('equipment_id', (int) $this->expense_equipment_id)
            ->where(fn ($q) => $q->open()->orWhere('status', 'completed')->orWhere('id', (int) $this->expense_equipment_maintenance_id ?: 0))
            ->orderByRaw("CASE WHEN status IN ('scheduled', 'in_progress') THEN 0 ELSE 1 END")
            ->orderByDesc('completed_date')
            ->orderByDesc('scheduled_date')
            ->limit(30)
            ->get(['id', 'title', 'status', 'scheduled_date', 'completed_date']);
    }

    protected function equipmentRules(): array
    {
        if (! $this->mayTagEquipment()) {
            // Nothing offered, nothing accepted — unless the row already carried it.
            return [
                'expense_equipment_id' => ['nullable', Rule::in([(string) $this->expense_equipment_id === '' ? '' : $this->expense_equipment_id])],
                'expense_equipment_maintenance_id' => ['nullable'],
            ];
        }

        return [
            'expense_equipment_id' => ['nullable', Rule::exists('equipment', 'id')],
            // A maintenance of THE CHOSEN equipment and no other.
            'expense_equipment_maintenance_id' => [
                'nullable',
                Rule::exists('equipment_maintenances', 'id')->where('equipment_id', (int) $this->expense_equipment_id),
            ],
        ];
    }

    protected function equipmentHeaderData(): array
    {
        return [
            'equipment_id' => $this->expense_equipment_id ? (int) $this->expense_equipment_id : null,
            'equipment_maintenance_id' => $this->expense_equipment_id && $this->expense_equipment_maintenance_id
                ? (int) $this->expense_equipment_maintenance_id
                : null,
        ];
    }

    protected function fillEquipmentFrom($expense): void
    {
        $this->expense_equipment_id = (string) ($expense->equipment_id ?? '');
        $this->expense_equipment_maintenance_id = (string) ($expense->equipment_maintenance_id ?? '');
    }

    /** `?equipment=&maintenance=` from a link on the equipment page. */
    protected function preselectEquipmentFromRequest(): void
    {
        $equipmentId = (int) request()->query('equipment');

        if ($equipmentId && Equipment::whereKey($equipmentId)->exists()) {
            $this->expense_equipment_id = (string) $equipmentId;

            $maintenanceId = (int) request()->query('maintenance');

            if ($maintenanceId && EquipmentMaintenance::whereKey($maintenanceId)->where('equipment_id', $equipmentId)->exists()) {
                $this->expense_equipment_maintenance_id = (string) $maintenanceId;
            }
        }
    }

    /** For the views. */
    protected function equipmentPickerData(): array
    {
        return [
            'equipmentOptions' => $this->equipmentOptions(),
            'maintenanceOptions' => $this->maintenanceOptions(),
        ];
    }
}
