<?php

namespace App\Livewire\Concerns;

use App\Models\Vendor;

/**
 * The enable/disable switch on every vendor screen — the Vendors, Suppliers
 * and Subcontractors lists and the two detail pages. It is an edit, so it
 * answers to `vendors.edit`; the `wire:click` behind the switch is a public
 * endpoint and is guarded here, not by hiding the control.
 */
trait TogglesVendorActive
{
    public function toggleActive(int $vendorId): void
    {
        $this->authorizeAbility('vendors.edit');

        // Vendors are company records (the area is `global`), so there is no
        // project to check the id against — the grant is the whole check.
        $vendor = Vendor::findOrFail($vendorId);

        if ($vendor->is_active) {
            $vendor->deactivate(auth()->id());
            session()->flash('message', __(':name is now inactive. Records that already name it are kept; new expenses, orders, contracts and quotations will not offer it.', ['name' => $vendor->name]));
        } else {
            $vendor->activate();
            session()->flash('message', __(':name is active again and can be picked for new records.', ['name' => $vendor->name]));
        }

        $this->afterVendorToggled($vendor);
    }

    /** Hook for a page holding the vendor as a model property to reload it. */
    protected function afterVendorToggled(Vendor $vendor): void
    {
    }
}
