<?php

namespace App\Livewire\Supplier;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierIndex extends Component
{
    use AuthorizesAbility;

    use AuthorizesAdmin, WithPagination;

    public $search = '';
    public $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function deleteSupplier($id)
    {
        $this->authorizeAbility('vendors.delete');

        $supplier = Supplier::find($id);

        if (! $supplier) {
            return;
        }

        // Expenses, catalog items and purchase orders must never lose their
        // supplier — matching how subcontractors with contracts cannot be
        // deleted. Merge or reassign first.
        if (\App\Models\Vendor::hasSupplierRecords($supplier->id)) {
            session()->flash('error', $supplier->is_subcontractor
                ? __('This company has expenses, catalog items or purchase orders and cannot stop being a supplier.')
                : __('This company has expenses, catalog items or purchase orders and cannot be deleted.'));

            return;
        }

        // A company that is also a subcontractor only loses its supplier
        // classification — the record survives on the Subcontractors page.
        if ($supplier->is_subcontractor) {
            $supplier->is_supplier = false;
            $supplier->save();
            session()->flash('message', __('Supplier classification removed. The company still exists as a subcontractor.'));
        } else {
            $supplier->delete();
            session()->flash('message', __('Supplier deleted successfully!'));
        }

        $this->resetPage();
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->with('createdBy')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%')
                      ->orWhere('city', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.supplier.supplier-index', [
            'suppliers' => $suppliers,
        ])->layout('components.layouts.app');
    }
}
