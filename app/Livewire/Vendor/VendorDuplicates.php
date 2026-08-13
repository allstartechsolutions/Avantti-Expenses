<?php

namespace App\Livewire\Vendor;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class VendorDuplicates extends Component
{
    use AuthorizesAdmin;

    // Manual merge selection
    public $keepVendorId = '';
    public $mergeVendorId = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    /**
     * Merge every other member of a duplicate group into the chosen survivor.
     */
    public function mergeGroup(int $survivorId, array $vendorIds): void
    {
        $this->authorizeAdmin();

        // find(), not findOrFail: a concurrent merge from another session may
        // have removed records this page still displays.
        $survivor = Vendor::find($survivorId);

        if (! $survivor) {
            session()->flash('error', __('That record no longer exists. The list has been refreshed.'));

            return;
        }

        // One outer transaction so a failure mid-group leaves nothing merged.
        $merged = DB::transaction(function () use ($survivor, $survivorId, $vendorIds) {
            $count = 0;

            foreach ($vendorIds as $id) {
                if ((int) $id === $survivorId) {
                    continue;
                }

                $loser = Vendor::find((int) $id);

                if ($loser) {
                    $loser->mergeInto($survivor);
                    $count++;
                }
            }

            return $count;
        });

        if ($merged === 0) {
            session()->flash('error', __('Nothing to merge — these records were already merged in another session.'));

            return;
        }

        session()->flash('message', __('Records merged into :name.', ['name' => $survivor->name]));
    }

    /**
     * Merge one manually selected vendor into another.
     */
    public function mergeManual(): void
    {
        $this->authorizeAdmin();

        $this->validate([
            'keepVendorId' => 'required|different:mergeVendorId|exists:vendors,id',
            'mergeVendorId' => 'required|exists:vendors,id',
        ], [
            'keepVendorId.different' => __('Select two different companies.'),
        ], [
            'keepVendorId' => __('company to keep'),
            'mergeVendorId' => __('company to merge'),
        ]);

        $survivor = Vendor::findOrFail((int) $this->keepVendorId);
        Vendor::findOrFail((int) $this->mergeVendorId)->mergeInto($survivor);

        $this->reset(['keepVendorId', 'mergeVendorId']);

        session()->flash('message', __('Records merged into :name.', ['name' => $survivor->name]));
    }

    public function render()
    {
        $vendors = Vendor::withCount([
            'expenses', 'catalogItems', 'purchaseOrders',
            'contracts', 'paymentBatches', 'documents', 'employees',
        ])->orderBy('name')->get();

        // Names with no alphanumerics all normalize to '' — never group those
        // together, they are unrelated companies.
        $duplicateGroups = $vendors
            ->groupBy(fn ($vendor) => Vendor::normalizeName($vendor->name))
            ->filter(fn ($group, $key) => $key !== '' && $group->count() > 1)
            ->values();

        return view('livewire.vendor.vendor-duplicates', [
            'duplicateGroups' => $duplicateGroups,
            'allVendors' => $vendors,
        ])->layout('components.layouts.app');
    }
}
