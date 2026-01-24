<?php

namespace App\Livewire\Supplier;

use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierIndex extends Component
{
    use WithPagination;

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
        $supplier = Supplier::find($id);

        if ($supplier) {
            $supplier->delete();
            session()->flash('message', 'Supplier deleted successfully!');
        }
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
