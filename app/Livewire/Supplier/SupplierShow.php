<?php

namespace App\Livewire\Supplier;

use App\Models\Supplier;
use Livewire\Component;

class SupplierShow extends Component
{
    public Supplier $supplier;

    public function mount(Supplier $supplier)
    {
        $this->supplier = $supplier->load('createdBy');
    }

    public function render()
    {
        return view('livewire.supplier.supplier-show')
            ->layout('components.layouts.app');
    }
}
