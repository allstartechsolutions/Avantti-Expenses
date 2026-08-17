<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\ResolvesContractBudget;
use App\Models\Contract;
use App\Models\ContractChangeOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractChangeOrders extends Component
{
    use ResolvesContractBudget;
    use WithFileUploads;

    public Contract $contract;

    public $showModal = false;

    public $editingId = null;

    public $title = '';

    public $date = '';

    public $amount = '';

    public $budget_item_id = '';

    public $description = '';

    public $file;

    public function openCreateModal()
    {
        $this->resetForm();
        $this->date = now()->format('Y-m-d');
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $changeOrder = ContractChangeOrder::where('contract_id', $this->contract->id)->findOrFail($id);

        $this->editingId = $changeOrder->id;
        $this->title = $changeOrder->title;
        $this->date = $changeOrder->date->format('Y-m-d');
        $this->amount = number_format($changeOrder->amount, 2, '.', '');
        $this->budget_item_id = $changeOrder->budget_item_id ?? '';
        $this->description = $changeOrder->description ?? '';
        $this->file = null;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save()
    {
        $budget = $this->locationBudget();

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric'],
            'budget_item_id' => [
                'nullable',
                Rule::exists('budget_items', 'id')->where('budget_id', $budget?->id ?? 0),
            ],
            'description' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $data = [
            'title' => $this->title,
            'date' => $this->date,
            'amount' => $this->amount,
            'budget_item_id' => $this->budget_item_id ?: null,
            'description' => $this->description ?: null,
        ];

        if ($this->file) {
            $data['file_path'] = $this->file->store('contract-change-orders', 'local');
        }

        if ($this->editingId) {
            $changeOrder = ContractChangeOrder::where('contract_id', $this->contract->id)->findOrFail($this->editingId);

            if ($this->file && $changeOrder->file_path && Storage::exists($changeOrder->file_path)) {
                Storage::delete($changeOrder->file_path);
            }

            $changeOrder->update($data);
        } else {
            $data['contract_id'] = $this->contract->id;
            $data['created_by'] = Auth::id();
            ContractChangeOrder::create($data);
        }

        $this->closeModal();
        $this->contract->refresh();
        $this->contract->updateStatusFromPayments();
        $this->dispatch('change-orders-updated');
        session()->flash('message', __('Change order saved successfully.'));
    }

    public function delete($id)
    {
        $changeOrder = ContractChangeOrder::where('contract_id', $this->contract->id)->findOrFail($id);

        if ($changeOrder->file_path && Storage::exists($changeOrder->file_path)) {
            Storage::delete($changeOrder->file_path);
        }

        $changeOrder->delete();

        $this->contract->refresh();
        $this->contract->updateStatusFromPayments();
        $this->dispatch('change-orders-updated');
        session()->flash('message', __('Change order deleted successfully.'));
    }

    private function resetForm()
    {
        $this->editingId = null;
        $this->title = '';
        $this->date = '';
        $this->amount = '';
        $this->budget_item_id = '';
        $this->description = '';
        $this->file = null;
        $this->resetValidation();
    }

    public function render()
    {
        $changeOrders = $this->contract->changeOrders()->with(['createdBy', 'budgetItem'])->get();

        return view('livewire.contract.contract-change-orders', [
            'changeOrders' => $changeOrders,
            'budgetItems' => $this->showModal ? $this->budgetItemOptions() : collect(),
        ]);
    }
}
