<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ResolvesContractBudget;
use App\Models\Contract;
use App\Models\ContractChangeOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractChangeOrders extends Component
{
    use AuthorizesAbility;

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
        $this->authorizeAbility('contracts.edit', $this->contract);

        $this->resetForm();
        $this->date = now()->format('Y-m-d');
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $this->authorizeAbility('contracts.edit', $this->contract);

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

    /** Take the chosen file back off before it is stored. */
    public function clearFile()
    {
        $this->file?->delete();

        $this->file = null;
    }

    public function save()
    {
        $this->authorizeAbility('contracts.edit', $this->contract);

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

        DB::transaction(function () use ($data) {
            if ($this->editingId) {
                $changeOrder = ContractChangeOrder::with('budgetItem')->where('contract_id', $this->contract->id)->findOrFail($this->editingId);
                $before = $this->auditValues($changeOrder);

                if ($this->file && $changeOrder->file_path && Storage::exists($changeOrder->file_path)) {
                    Storage::delete($changeOrder->file_path);
                }

                $changeOrder->update($data);
                $after = $this->auditValues($changeOrder->fresh('budgetItem'));

                $changes = [];
                foreach ($after as $field => $value) {
                    if ($before[$field] !== $value) {
                        $changes[$field] = ['old' => $before[$field], 'new' => $value];
                    }
                }

                if ($this->file) {
                    $changes['file'] = ['old' => null, 'new' => $before['has_file'] ? 'replaced' : 'added'];
                }
                unset($changes['has_file']);

                if ($changes !== []) {
                    // Name the change order even when its title did not move.
                    $changes = ['title' => $changes['title'] ?? $after['title']] + $changes;
                }

                $action = 'change_order_updated';
            } else {
                $changeOrder = ContractChangeOrder::create($data + [
                    'contract_id' => $this->contract->id,
                    'created_by' => Auth::id(),
                ]);
                $changes = $this->auditValues($changeOrder->load('budgetItem'));
                unset($changes['has_file']);
                $action = 'change_order_added';
            }

            $this->contract->refresh();
            $changes += $this->statusChange('Auto-updated after a change order');
            $this->contract->recordChange($action, $changes, $changeOrder->id);
        });

        $this->closeModal();
        $this->dispatch('change-orders-updated');
        session()->flash('message', __('Change order saved successfully.'));
    }

    public function delete($id)
    {
        $this->authorizeAbility('contracts.delete', $this->contract);

        $changeOrder = ContractChangeOrder::where('contract_id', $this->contract->id)->findOrFail($id);

        $filePath = $changeOrder->file_path;

        DB::transaction(function () use ($changeOrder) {
            $changes = $this->auditValues($changeOrder->load('budgetItem'));
            unset($changes['has_file']);

            $changeOrder->delete();

            $this->contract->refresh();
            $changes += $this->statusChange('Auto-updated after a change order was deleted');
            $this->contract->recordChange('change_order_deleted', $changes, $changeOrder->id);
        });

        // After the commit: a rolled-back delete must not have lost its file.
        if ($filePath && Storage::exists($filePath)) {
            Storage::delete($filePath);
        }

        $this->dispatch('change-orders-updated');
        session()->flash('message', __('Change order deleted successfully.'));
    }

    /** A change order's terms as the contract history records them. */
    private function auditValues(ContractChangeOrder $changeOrder): array
    {
        return [
            'title' => $changeOrder->title,
            'date' => $changeOrder->date?->format('Y-m-d'),
            'amount' => (float) $changeOrder->amount,
            'cost_code' => $changeOrder->budgetItem
                ? $changeOrder->budgetItem->code.' - '.$changeOrder->budgetItem->name
                : null,
            'description' => $changeOrder->description,
            'has_file' => $changeOrder->file_path !== null,
        ];
    }

    /**
     * Re-derive the contract status from its money and return the move, if
     * any, for the history entry — a change order that raises a paid
     * contract's value leaves a balance to pay.
     */
    private function statusChange(string $reason): array
    {
        $oldStatus = $this->contract->status;
        $newStatus = $this->contract->updateStatusFromPayments($reason);

        return $newStatus ? ['status' => ['old' => $oldStatus, 'new' => $newStatus]] : [];
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
