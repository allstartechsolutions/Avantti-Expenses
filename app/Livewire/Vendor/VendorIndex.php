<?php

namespace App\Livewire\Vendor;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\TogglesVendorActive;
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
    use TogglesVendorActive;
    use WithPagination;

    public string $search = '';

    /** '' | suppliers | subcontractors | both */
    public string $type = '';

    /** Filter on the documents badge (subcontractors only): expired | expiring_soon | valid | none, or ''. */
    public string $documentHealth = '';

    /** '' | active | inactive — an inactive vendor is kept but not offered to new records. */
    public string $status = '';

    public int $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'documentHealth' => ['except' => '', 'as' => 'documents'],
        'status' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorizeAbility('vendors.view');
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'type', 'documentHealth', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'documentHealth', 'status']);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->type !== '' || $this->documentHealth !== '' || $this->status !== '';
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

    /**
     * The numbers on the type cards and the status filter, from one grouped
     * query: each card shows its total and, under it, how many of those are
     * active and how many switched off.
     */
    protected function cardCounts(): array
    {
        $counts = [];
        foreach (['all', 'suppliers', 'subcontractors', 'both'] as $key) {
            $counts[$key] = ['total' => 0, 'active' => 0, 'inactive' => 0];
        }
        $counts['active'] = 0;
        $counts['inactive'] = 0;

        $rows = Vendor::query()
            ->selectRaw('is_supplier, is_subcontractor, is_active, count(*) as n')
            ->groupBy('is_supplier', 'is_subcontractor', 'is_active')
            ->get();

        foreach ($rows as $row) {
            $state = $row->is_active ? 'active' : 'inactive';
            $n = (int) $row->n;

            $keys = ['all'];
            if ($row->is_supplier) {
                $keys[] = 'suppliers';
            }
            if ($row->is_subcontractor) {
                $keys[] = 'subcontractors';
            }
            if ($row->is_supplier && $row->is_subcontractor) {
                $keys[] = 'both';
            }

            foreach ($keys as $key) {
                $counts[$key]['total'] += $n;
                $counts[$key][$state] += $n;
            }
            $counts[$state] += $n;
        }

        return $counts;
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
            ->activeState($this->status)
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

        $counts = $this->cardCounts();

        return view('livewire.vendor.vendor-index', [
            'vendors' => $vendors,
            'counts' => $counts,
            'healthOptions' => collect(Subcontractor::documentHealthStates())
                ->mapWithKeys(fn ($state) => [$state => Subcontractor::documentHealthLabel($state)]),
        ])->layout('components.layouts.app');
    }
}
