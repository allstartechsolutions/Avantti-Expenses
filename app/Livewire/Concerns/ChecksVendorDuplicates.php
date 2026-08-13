<?php

namespace App\Livewire\Concerns;

use App\Models\Vendor;

/**
 * Duplicate-company suggestions for the Supplier/Subcontractor create forms.
 * Matches across the whole unified vendors table regardless of
 * classification. Shared partial: livewire/shared/vendor-duplicate-matches.
 */
trait ChecksVendorDuplicates
{
    public $duplicateMatches = [];

    /**
     * Call only from the name field's updated hook, so edits to other fields
     * do not re-run the query on every Livewire round trip.
     */
    protected function refreshDuplicateMatches($term): void
    {
        $term = trim((string) $term);

        $this->duplicateMatches = mb_strlen($term) < 3
            ? []
            : Vendor::where('name', 'like', '%' . $term . '%')
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'is_supplier', 'is_subcontractor'])
                ->map(fn ($vendor) => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'is_supplier' => (bool) $vendor->is_supplier,
                    'is_subcontractor' => (bool) $vendor->is_subcontractor,
                ])
                ->all();
    }
}
