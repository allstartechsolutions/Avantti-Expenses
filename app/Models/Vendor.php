<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Unscoped view of the unified `vendors` table (see docs/vendor-unification.md).
 * Supplier and Subcontractor are flag-scoped models over this same table; use
 * Vendor when a record must be seen regardless of classification — duplicate
 * detection and merging.
 */
class Vendor extends Model
{
    use \App\Models\Concerns\DeletesVendorDocuments;
    use \App\Models\Concerns\HasDocumentHealth;

    /**
     * Every table referencing vendors, by FK column. Any new table with a
     * supplier_id/subcontractor_id column MUST be added here or merges will
     * strand its rows (SET NULL / CASCADE instead of repointing).
     */
    public const SUPPLIER_FK_TABLES = ['expenses', 'catalog_items', 'purchase_orders'];

    public const SUBCONTRACTOR_FK_TABLES = ['contracts', 'payment_batches', 'subcontractor_documents', 'subcontractor_employees'];

    protected $fillable = [];

    protected $casts = [
        'is_supplier' => 'boolean',
        'is_subcontractor' => 'boolean',
    ];

    /**
     * True when supplier-side records (expenses, catalog items, purchase
     * orders) reference this vendor. Guard for removing the supplier
     * classification or deleting the record — the single source of truth
     * used by every component.
     */
    public static function hasSupplierRecords(int $vendorId): bool
    {
        foreach (self::SUPPLIER_FK_TABLES as $table) {
            if (DB::table($table)->where('supplier_id', $vendorId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when contracts or payment batches reference this vendor. Documents
     * and employees intentionally do not block reclassification — they are
     * kept and restored by re-flagging.
     */
    public static function hasSubcontractorRecords(int $vendorId): bool
    {
        foreach (['contracts', 'payment_batches'] as $table) {
            if (DB::table($table)->where('subcontractor_id', $vendorId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalized company name used for duplicate matching: lowercase,
     * accent-stripped, alphanumerics only.
     */
    public static function normalizeName(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii((string) $name)));
    }

    // =========================================================================
    // RELATIONSHIPS (used for linked-record counts on the merge page)
    // =========================================================================

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'supplier_id');
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'supplier_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'subcontractor_id');
    }

    public function paymentBatches(): HasMany
    {
        return $this->hasMany(PaymentBatch::class, 'subcontractor_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SubcontractorDocument::class, 'subcontractor_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(SubcontractorEmployee::class, 'subcontractor_id');
    }

    /**
     * Merge this vendor into $survivor: combines classification flags, fills
     * the survivor's empty fields, repoints every referencing record, then
     * deletes this row. Caller decides which record survives.
     */
    public function mergeInto(Vendor $survivor): void
    {
        if ($survivor->id === $this->id) {
            return;
        }

        DB::transaction(function () use ($survivor) {
            $survivor->is_supplier = $survivor->is_supplier || $this->is_supplier;
            $survivor->is_subcontractor = $survivor->is_subcontractor || $this->is_subcontractor;

            foreach (['website', 'contact_name', 'contact_email', 'title', 'phone', 'email', 'description',
                'street', 'address_2', 'neighborhood', 'city', 'state', 'postal_code', 'country',
                'latitude', 'longitude'] as $field) {
                if ($survivor->$field === null || $survivor->$field === '') {
                    $survivor->$field = $this->$field;
                }
            }

            $survivor->save();

            foreach (self::SUPPLIER_FK_TABLES as $table) {
                DB::table($table)->where('supplier_id', $this->id)->update(['supplier_id' => $survivor->id]);
            }
            foreach (self::SUBCONTRACTOR_FK_TABLES as $table) {
                DB::table($table)->where('subcontractor_id', $this->id)->update(['subcontractor_id' => $survivor->id]);
            }

            $this->delete();
        });
    }
}
