<?php

namespace App\Livewire\Equipment;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesEquipmentForm;
use App\Models\Equipment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class EquipmentCreate extends Component
{
    use AuthorizesAbility, ManagesEquipmentForm, WithFileUploads;

    /** A starting reading, written as the first meter reading. */
    public string $initial_meter = '';

    public string $initial_meter_at = '';

    public function mount(): void
    {
        $this->authorizeAbility('equipment.create');

        $this->initial_meter_at = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return $this->equipmentRules() + [
            'initial_meter' => ['nullable', 'numeric', 'min:0'],
            'initial_meter_at' => ['nullable', 'date'],
        ];
    }

    public function validationAttributes(): array
    {
        return $this->equipmentValidationAttributes() + [
            'initial_meter' => __('starting reading'),
            'initial_meter_at' => __('reading date'),
        ];
    }

    public function save()
    {
        $this->authorizeAbility('equipment.create');

        $this->validate();

        $equipment = DB::transaction(function () {
            $data = $this->equipmentData() + ['created_by' => Auth::id()];

            if ($this->photo) {
                $data['photo_path'] = $this->photo->store('equipment/photos', 'local');
            }

            $equipment = Equipment::create($data);

            if ($equipment->hasMeter() && $this->initial_meter !== '') {
                $reading = $equipment->readings()->create([
                    'reading' => (float) $this->initial_meter,
                    'read_at' => $this->initial_meter_at ?: now()->toDateString(),
                    'source' => 'manual',
                    'notes' => __('Starting reading'),
                    'recorded_by' => Auth::id(),
                ]);

                $equipment->update([
                    'current_meter' => $reading->reading,
                    'current_meter_at' => $reading->read_at,
                ]);
            }

            $equipment->recordHistory('created');

            return $equipment;
        });

        session()->flash('message', __('Equipment added.'));

        return redirect()->route('equipment.show', $equipment);
    }

    public function render()
    {
        return view('livewire.equipment.equipment-create', [
            'supplierResults' => $this->supplierResults(),
            'supplierCount' => $this->supplierCount(),
            'options' => $this->equipmentOptions(),
        ])->layout('components.layouts.app');
    }
}
