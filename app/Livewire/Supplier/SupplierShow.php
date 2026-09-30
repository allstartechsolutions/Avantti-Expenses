<?php

namespace App\Livewire\Supplier;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\TogglesVendorActive;
use App\Models\Supplier;
use App\Models\Vendor;
use Livewire\Component;

class SupplierShow extends Component
{
    use AuthorizesAbility;
    use TogglesVendorActive;

    public Supplier $supplier;

    public function mount(Supplier $supplier)
    {
        $this->authorizeAbility('vendors.view');

        $this->supplier = $supplier->load(['createdBy', 'deactivatedBy']);
    }

    protected function afterVendorToggled(Vendor $vendor): void
    {
        $this->supplier = $this->supplier->fresh(['createdBy', 'deactivatedBy']);
    }

    public function render()
    {
        return view('livewire.supplier.supplier-show')
            ->layout('components.layouts.app');
    }
}
