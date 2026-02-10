<?php

namespace App\Livewire\SystemSettings;

use App\Models\TaxRate;
use App\Models\TaxRateHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TaxRateSettings extends Component
{
    // Form fields
    public string $state = '';
    public string $rate = '';
    public bool $is_default = false;

    // Editing state
    public ?int $editingTaxRateId = null;

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public bool $showHistoryModal = false;

    // Delete state
    public ?int $deletingTaxRateId = null;
    public array $deleteTaxRateData = [];

    // History state
    public ?int $historyTaxRateId = null;
    public array $historyEntries = [];

    protected function rules(): array
    {
        return [
            'state' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'is_default' => 'boolean',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
        $this->dispatch('open-modal', 'tax-rate-form-modal');
    }

    public function edit(int $id): void
    {
        $taxRate = TaxRate::findOrFail($id);

        $this->editingTaxRateId = $taxRate->id;
        $this->state = $taxRate->state;
        $this->rate = (string) ($taxRate->rate * 100);
        $this->is_default = $taxRate->is_default;

        $this->showFormModal = true;
        $this->dispatch('open-modal', 'tax-rate-form-modal');
    }

    public function save(): void
    {
        $this->validate();

        $rateDecimal = $this->rate / 100;

        DB::transaction(function () use ($rateDecimal) {
            if ($this->editingTaxRateId) {
                $taxRate = TaxRate::findOrFail($this->editingTaxRateId);
                $original = $taxRate->getOriginal();

                // Track changes
                if ($original['state'] !== $this->state) {
                    TaxRate::logHistory($taxRate->id, 'updated', 'state', $original['state'], $this->state);
                }
                if ((float) $original['rate'] !== (float) $rateDecimal) {
                    TaxRate::logHistory($taxRate->id, 'updated', 'rate', number_format($original['rate'] * 100, 2) . '%', number_format($rateDecimal * 100, 2) . '%');
                }
                if ((bool) $original['is_default'] !== $this->is_default) {
                    TaxRate::logHistory($taxRate->id, 'updated', 'is_default', $original['is_default'] ? 'Yes' : 'No', $this->is_default ? 'Yes' : 'No');
                }

                // Unset other defaults if this one is being set as default
                if ($this->is_default && !$original['is_default']) {
                    TaxRate::where('is_default', true)->where('id', '!=', $taxRate->id)->update(['is_default' => false]);
                }

                $taxRate->update([
                    'state' => $this->state,
                    'rate' => $rateDecimal,
                    'is_default' => $this->is_default,
                ]);

                session()->flash('message', 'Tax rate updated successfully!');
            } else {
                // Unset other defaults if this one is being set as default
                if ($this->is_default) {
                    TaxRate::where('is_default', true)->update(['is_default' => false]);
                }

                $taxRate = TaxRate::create([
                    'state' => $this->state,
                    'rate' => $rateDecimal,
                    'is_default' => $this->is_default,
                    'created_by' => Auth::id(),
                ]);

                TaxRate::logHistory($taxRate->id, 'created');

                session()->flash('message', 'Tax rate created successfully!');
            }
        });

        $this->closeFormModal();
    }

    public function confirmDelete(int $id): void
    {
        $taxRate = TaxRate::findOrFail($id);

        $this->deletingTaxRateId = $id;
        $this->deleteTaxRateData = [
            'state' => $taxRate->state,
            'rate' => $taxRate->formatted_rate,
        ];

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'tax-rate-delete-modal');
    }

    public function delete(): void
    {
        $taxRate = TaxRate::findOrFail($this->deletingTaxRateId);

        TaxRate::logHistory($taxRate->id, 'deleted', null, $taxRate->state . ' - ' . $taxRate->formatted_rate, null);

        $taxRate->delete();

        $this->showDeleteModal = false;
        $this->deletingTaxRateId = null;
        $this->deleteTaxRateData = [];

        session()->flash('message', 'Tax rate deleted successfully!');
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingTaxRateId = null;
        $this->deleteTaxRateData = [];
        $this->dispatch('close-modal', 'tax-rate-delete-modal');
    }

    public function viewHistory(int $id): void
    {
        $this->historyTaxRateId = $id;
        $this->historyEntries = TaxRateHistory::where('tax_rate_id', $id)
            ->with('changedBy')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'action' => $entry->action,
                    'field_changed' => $entry->field_changed,
                    'old_value' => $entry->old_value,
                    'new_value' => $entry->new_value,
                    'changed_by' => $entry->changedBy->name ?? 'Unknown',
                    'created_at' => $entry->created_at->format('M d, Y h:i A'),
                ];
            })
            ->toArray();

        $this->showHistoryModal = true;
        $this->dispatch('open-modal', 'tax-rate-history-modal');
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyTaxRateId = null;
        $this->historyEntries = [];
        $this->dispatch('close-modal', 'tax-rate-history-modal');
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
        $this->dispatch('close-modal', 'tax-rate-form-modal');
    }

    private function resetForm(): void
    {
        $this->editingTaxRateId = null;
        $this->state = '';
        $this->rate = '';
        $this->is_default = false;
        $this->resetValidation();
    }

    public function render()
    {
        $taxRates = TaxRate::query()
            ->with('createdBy')
            ->orderBy('state')
            ->get();

        return view('livewire.system-settings.tax-rate-settings', [
            'taxRates' => $taxRates,
        ]);
    }
}
