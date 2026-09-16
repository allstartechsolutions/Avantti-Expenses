<?php

namespace App\Livewire\Concerns;

use App\Livewire\Equipment\EquipmentEdit;
use App\Models\Equipment;
use App\Models\Vendor;
use Illuminate\Validation\Rule;

/**
 * The equipment form — identity, ownership and purchase, meter, status,
 * photo — shared by EquipmentCreate and EquipmentEdit so the two screens can
 * never drift apart. The host owns what happens on save.
 */
trait ManagesEquipmentForm
{
    // Identity
    public string $name = '';

    public string $asset_tag = '';

    public string $equipment_type = 'machinery';

    public string $make = '';

    public string $model = '';

    public string $year = '';

    public string $serial_number = '';

    public string $plate = '';

    public string $vin = '';

    // Ownership and purchase
    public string $ownership = 'owned';

    public ?int $supplier_id = null;

    public string $supplierSearch = '';

    public string $purchase_date = '';

    public string $purchase_cost = '';

    public string $warranty_until = '';

    // Meter
    public string $meter_type = 'none';

    // Status
    public string $status = 'active';

    // Photo
    public $photo = null;

    public string $notes = '';

    protected function equipmentRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'asset_tag' => ['nullable', 'string', 'max:60'],
            'equipment_type' => ['required', Rule::in(Equipment::TYPES)],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'plate' => ['nullable', 'string', 'max:20'],
            'vin' => ['nullable', 'string', 'max:40'],
            'ownership' => ['required', Rule::in(Equipment::OWNERSHIPS)],
            'supplier_id' => ['nullable', Rule::exists('vendors', 'id')->where('is_supplier', 1)],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'warranty_until' => ['nullable', 'date'],
            'meter_type' => ['required', Rule::in(Equipment::METER_TYPES)],
            'status' => ['required', Rule::in(Equipment::STATUSES)],
            'photo' => ['nullable', 'image', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function equipmentValidationAttributes(): array
    {
        return [
            'asset_tag' => __('asset tag'),
            'equipment_type' => __('type'),
            'serial_number' => __('serial number'),
            'supplier_id' => __('supplier'),
            'purchase_date' => __('purchase date'),
            'purchase_cost' => __('purchase cost'),
            'warranty_until' => __('warranty until'),
            'meter_type' => __('meter'),
        ];
    }

    protected function fillFormFromEquipment(Equipment $equipment): void
    {
        $equipment->loadMissing('supplier');

        $this->name = $equipment->name;
        $this->asset_tag = (string) $equipment->asset_tag;
        $this->equipment_type = $equipment->equipment_type;
        $this->make = (string) $equipment->make;
        $this->model = (string) $equipment->model;
        $this->year = (string) ($equipment->year ?? '');
        $this->serial_number = (string) $equipment->serial_number;
        $this->plate = (string) $equipment->plate;
        $this->vin = (string) $equipment->vin;
        $this->ownership = $equipment->ownership;
        $this->supplier_id = $equipment->supplier_id;
        $this->supplierSearch = $equipment->supplier?->name ?? '';
        $this->purchase_date = $equipment->purchase_date?->format('Y-m-d') ?? '';
        $this->purchase_cost = $equipment->purchase_cost === null ? '' : number_format((float) $equipment->purchase_cost, 2, '.', '');
        $this->warranty_until = $equipment->warranty_until?->format('Y-m-d') ?? '';
        $this->meter_type = $equipment->meter_type;
        $this->status = $equipment->status;
        $this->notes = (string) $equipment->notes;
    }

    /** The columns the form describes, ready for create or update. */
    protected function equipmentData(): array
    {
        return [
            'name' => trim($this->name),
            'asset_tag' => trim($this->asset_tag) ?: null,
            'equipment_type' => $this->equipment_type,
            'make' => trim($this->make) ?: null,
            'model' => trim($this->model) ?: null,
            'year' => $this->year !== '' ? (int) $this->year : null,
            'serial_number' => trim($this->serial_number) ?: null,
            'plate' => strtoupper(trim($this->plate)) ?: null,
            'vin' => strtoupper(trim($this->vin)) ?: null,
            'ownership' => $this->ownership,
            'supplier_id' => $this->supplier_id,
            'purchase_date' => $this->purchase_date ?: null,
            'purchase_cost' => $this->purchase_cost !== '' ? (float) $this->purchase_cost : null,
            'warranty_until' => $this->warranty_until ?: null,
            'meter_type' => $this->meter_type,
            'status' => $this->status,
            'notes' => trim($this->notes) ?: null,
        ];
    }

    /**
     * A vehicle reads an odometer in this country's unit unless the form
     * already says otherwise; anything else starts with no meter.
     */
    public function updatedEquipmentType(): void
    {
        if ($this->equipment_type === 'vehicle' && $this->meter_type === 'none') {
            $this->meter_type = config('app.country') === 'BR' ? 'km' : 'mi';
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Supplier picker (x-ui.search-select)
    |---------------------------------------------------------------------------
    */

    public function updatedSupplierSearch(): void
    {
        // Typing over the chosen name unlinks it: the record must never point
        // at a vendor whose name is no longer on the screen.
        if ($this->supplier_id && $this->supplierSearch !== (Vendor::find($this->supplier_id)?->name ?? '')) {
            $this->supplier_id = null;
        }
    }

    public function selectSupplier(int $vendorId): void
    {
        $vendor = Vendor::where('is_supplier', true)->find($vendorId);

        if ($vendor) {
            $this->supplier_id = $vendor->id;
            $this->supplierSearch = $vendor->name;
        }
    }

    public function clearSupplier(): void
    {
        $this->supplier_id = null;
        $this->supplierSearch = '';
    }

    protected function supplierResults(): array
    {
        if ($this->supplier_id || mb_strlen($this->supplierSearch) < 2) {
            return [];
        }

        return Vendor::where('is_supplier', true)
            ->where('name', 'like', '%'.$this->supplierSearch.'%')
            ->orderBy('name')
            ->take(10)
            ->get(['id', 'name', 'city', 'state'])
            ->map(fn (Vendor $v) => [
                'id' => $v->id,
                'label' => $v->name,
                'meta' => trim(($v->city ?? '').($v->city && $v->state ? ', ' : '').($v->state ?? '')) ?: null,
            ])
            ->all();
    }

    protected function supplierCount(): int
    {
        return Vendor::where('is_supplier', true)->count();
    }

    /*
    |---------------------------------------------------------------------------
    | Photo (x-ui.file-drop, single file)
    |---------------------------------------------------------------------------
    */

    public function clearPhoto(): void
    {
        // The temporary upload is the caller's own; the grant that opened the
        // screen is the one that may drop it.
        $this->authorizeAbility($this instanceof EquipmentEdit ? 'equipment.edit' : 'equipment.create');

        $this->photo?->delete();
        $this->photo = null;
    }

    /** Shared select options for the view. */
    protected function equipmentOptions(): array
    {
        return [
            'types' => collect(Equipment::TYPES)->mapWithKeys(fn ($t) => [$t => Equipment::typeLabel($t)])->all(),
            'ownerships' => collect(Equipment::OWNERSHIPS)->mapWithKeys(fn ($o) => [$o => Equipment::ownershipLabel($o)])->all(),
            'meterTypes' => [
                'none' => __('No meter'),
                'km' => __('Odometer (km)'),
                'mi' => __('Odometer (mi)'),
                'hours' => __('Hour meter'),
            ],
            'statuses' => collect(Equipment::STATUSES)->mapWithKeys(fn ($s) => [$s => Equipment::statusLabel($s)])->all(),
            'platePlaceholder' => config('app.country') === 'BR' ? 'ABC1D23' : 'ABC-1234',
        ];
    }
}
