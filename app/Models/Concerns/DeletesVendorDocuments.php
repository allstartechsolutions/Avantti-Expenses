<?php

namespace App\Models\Concerns;

use App\Models\SubcontractorDocument;

/**
 * Used by every model over the `vendors` table (Vendor, Supplier,
 * Subcontractor). Deleting a vendor row through any of them removes its
 * subcontractor documents via Eloquent first, so the document model's
 * file-cleanup hook fires — the DB-level CASCADE would silently orphan the
 * files on disk.
 */
trait DeletesVendorDocuments
{
    protected static function bootDeletesVendorDocuments(): void
    {
        static::deleting(function ($model) {
            SubcontractorDocument::where('subcontractor_id', $model->id)->get()->each->delete();
        });
    }
}
