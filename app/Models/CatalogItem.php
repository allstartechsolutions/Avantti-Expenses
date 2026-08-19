<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class CatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'sku',
        'description',
        'category_id',
        'supplier_id',
        'is_active',
        'purchase_unit',
        'usage_unit',
        'units_per_purchase',
        'current_cost',
        'billing_type',
        'is_taxable',
        'tax_rate_id',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
        'units_per_purchase' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get/Set current_cost as dollars (stored as cents)
     */
    protected function currentCost(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($item) {
            // Track price changes
            if ($item->isDirty('current_cost') && $item->getOriginal('current_cost') !== null) {
                CatalogItemPriceHistory::create([
                    'catalog_item_id' => $item->id,
                    'old_cost' => $item->getOriginal('current_cost'),
                    'new_cost' => $item->current_cost,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);
            }
        });
    }

    /**
     * Get the tax rate
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    /**
     * Get the preferred supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who created this item
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get price history
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(CatalogItemPriceHistory::class)->orderBy('changed_at', 'desc');
    }

    /**
     * What this item last actually cost, and when.
     *
     * The catalog's own `current_cost` is a figure somebody typed; the price
     * history also carries what was really paid, written by the quotation
     * module when a round is awarded. Buyers price the next round against the
     * last real one, so that is what the pickers show.
     */
    public function lastPaid(): ?CatalogItemPriceHistory
    {
        return $this->priceHistory()
            ->whereNotNull('notes')
            ->orderBy('changed_at', 'desc')
            ->first();
    }

    /**
     * Scope for active items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for products
     */
    public function scopeProducts($query)
    {
        return $query->where('type', 'product');
    }

    /**
     * Scope for services
     */
    public function scopeServices($query)
    {
        return $query->where('type', 'service');
    }

    /**
     * Scope for rentals
     */
    public function scopeRentals($query)
    {
        return $query->where('type', 'rental');
    }

    /**
     * Calculate unit cost for products
     */
    public function getUnitCostAttribute()
    {
        if ($this->type === 'product' && $this->units_per_purchase > 0) {
            return round($this->current_cost / $this->units_per_purchase, 2);
        }
        return $this->current_cost;
    }

    /**
     * Get display name with type badge
     */
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . ucfirst($this->type) . ')';
    }
}
