<?php

namespace App\Livewire\Supplier;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesVendorDocuments;
use App\Livewire\Concerns\TogglesVendorActive;
use App\Models\Supplier;
use App\Models\Vendor;
use Livewire\Component;
use Livewire\WithFileUploads;

class SupplierShow extends Component
{
    use AuthorizesAbility;
    use ManagesVendorDocuments;
    use TogglesVendorActive;
    use WithFileUploads;

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

    /**
     * W-9, COI and the other compliance documents, as on a subcontractor
     * (client request 27, Nov 2026). They belong to the vendor row, so a
     * vendor that is both shows the same documents on both pages.
     */
    protected function documentVendorId(): int
    {
        return $this->supplier->id;
    }

    public function render()
    {
        return view('livewire.supplier.supplier-show', $this->documentViewData())
            ->layout('components.layouts.app');
    }
}
