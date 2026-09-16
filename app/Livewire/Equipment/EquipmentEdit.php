<?php

namespace App\Livewire\Equipment;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesEquipmentForm;
use App\Models\Equipment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class EquipmentEdit extends Component
{
    use AuthorizesAbility, ManagesEquipmentForm, WithFileUploads;

    public Equipment $equipment;

    public bool $removePhoto = false;

    public function mount(Equipment $equipment): void
    {
        $this->authorizeAbility('equipment.edit');

        $this->equipment = $equipment;
        $this->fillFormFromEquipment($equipment);
    }

    protected function rules(): array
    {
        return $this->equipmentRules();
    }

    public function validationAttributes(): array
    {
        return $this->equipmentValidationAttributes();
    }

    public function save()
    {
        $this->authorizeAbility('equipment.edit');

        $this->validate();

        DB::transaction(function () {
            $data = $this->equipmentData();

            if ($this->removePhoto && $this->equipment->photo_path) {
                Storage::delete($this->equipment->photo_path);
                $data['photo_path'] = null;
            }

            if ($this->photo) {
                if ($this->equipment->photo_path) {
                    Storage::delete($this->equipment->photo_path);
                }
                $data['photo_path'] = $this->photo->store('equipment/photos', 'local');
            }

            // Retiring or selling stamps the date; coming back clears it.
            if (in_array($data['status'], ['retired', 'sold'], true) && ! $this->equipment->isRetired()) {
                $data['retired_at'] = now()->toDateString();
            } elseif (! in_array($data['status'], ['retired', 'sold'], true)) {
                $data['retired_at'] = null;
            }

            $before = $this->equipment->only(array_keys($data));
            $this->equipment->update($data);

            $changes = [];
            foreach ($data as $field => $value) {
                $old = $before[$field] ?? null;
                $old = $old instanceof CarbonInterface ? $old->toDateString() : $old;
                if ((string) $old !== (string) $value) {
                    $changes[$field] = ['old' => $old, 'new' => $value];
                }
            }

            if (isset($changes['status'])) {
                $this->equipment->recordHistory('status_changed', ['status' => $changes['status']]);
                unset($changes['status']);
            }

            if ($changes !== []) {
                $this->equipment->recordHistory('edited', $changes);
            }
        });

        session()->flash('message', __('Equipment updated.'));

        return redirect()->route('equipment.show', $this->equipment);
    }

    public function render()
    {
        return view('livewire.equipment.equipment-edit', [
            'supplierResults' => $this->supplierResults(),
            'supplierCount' => $this->supplierCount(),
            'options' => $this->equipmentOptions(),
        ])->layout('components.layouts.app');
    }
}
