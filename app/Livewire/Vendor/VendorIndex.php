<?php

namespace App\Livewire\Vendor;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Subcontractor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every vendor in one list — suppliers, subcontractors and the companies
 * that are both. The two flag-scoped lists (Suppliers, Subcontractors) still
 * exist behind their old routes; this is the Directory's front door.
 */
class VendorIndex extends Component
{
    use AuthorizesAbility;
    use WithPagination;

    public string $search = '';

    /** '' | suppliers | subcontractors | both */
    public string $type = '';

    /** Filter on the documents badge (subcontractors only): expired | expiring_soon | valid | none, or ''. */
    public string $documentHealth = '';

    public int $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'documentHealth' => ['except' => '', 'as' => 'documents'],
    ];

    public function mount(): void
    {
        $this->authorizeAbility('vendors.view');
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'type', 'documentHealth'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'documentHealth']);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->type !== '' || $this->documentHealth !== '';
    }

    /**
     * Delete a vendor that nothing depends on. A company with records on
     * either side, or with both classifications, is sent to its own page,
     * where the rules for each side are spelled out.
     */
    public function deleteVendor(int $vendorId): void
    {
        $this->authorizeAbility('vendors.delete');

        $vendor = Vendor::find($vendorId);

        if (! $vendor) {
            return;
        }

        if ($vendor->is_supplier && $vendor->is_subcontractor) {
            session()->flash('error', __('This company is both a supplier and a subcontractor. Open it and remove one classification at a time.'));

            return;
        }

        if (Vendor::hasSupplierRecords($vendor->id) || Vendor::hasSubcontractorRecords($vendor->id)) {
            session()->flash('error', __('This company has expenses, purchase orders, catalog items, contracts or payment batches and cannot be deleted. Merge it into another record instead.'));

            return;
        }

        $vendor->delete();

        $this->resetPage();

        session()->flash('message', __('Vendor deleted successfully!'));
    }

    public function render()
    {
        $term = trim($this->search);
        $health = in_array($this->documentHealth, Subcontractor::documentHealthStates(), true)
            ? $this->documentHealth
            : null;

        $vendors = Vendor::query()
            ->withCount(['contracts', 'paymentBatches', 'expenses', 'purchaseOrders', 'catalogItems', 'employees'])
            ->withDocumentHealth()
            ->when($this->type === 'suppliers', fn (Builder $q) => $q->where('is_supplier', true))
            ->when($this->type === 'subcontractors', fn (Builder $q) => $q->where('is_subcontractor', true))
            ->when($this->type === 'both', fn (Builder $q) => $q->where('is_supplier', true)->where('is_subcontractor', true))
            // The documents filter only means anything for a subcontractor.
            ->when($health, fn (Builder $q) => $q->where('is_subcontractor', true)->documentHealth($health))
            ->when($term !== '', function (Builder $query) use ($term) {
                $like = '%'.$term.'%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('contact_name', 'like', $like)
                        ->orWhere('contact_email', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('city', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        $counts = [
            'all' => Vendor::count(),
            'suppliers' => Vendor::where('is_supplier', true)->count(),
            'subcontractors' => Vendor::where('is_subcontractor', true)->count(),
            'both' => Vendor::where('is_supplier', true)->where('is_subcontractor', true)->count(),
        ];

        return view('livewire.vendor.vendor-index', [
            'vendors' => $vendors,
            'counts' => $counts,
            'healthOptions' => collect(Subcontractor::documentHealthStates())
                ->mapWithKeys(fn ($state) => [$state => Subcontractor::documentHealthLabel($state)]),
        ])->layout('components.layouts.app');
    }
}
