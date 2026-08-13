<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFormattedPhone, HasFactory;
    use \App\Models\Concerns\DeletesVendorDocuments;

    /**
     * Suppliers live in the unified `vendors` table (shared with
     * Subcontractor), scoped to rows flagged is_supplier. One vendor row can
     * be flagged as both. See docs/vendor-unification.md.
     */
    protected $table = 'vendors';

    protected static function booted(): void
    {
        static::addGlobalScope('supplier', function (Builder $query) {
            $query->where('vendors.is_supplier', true);
        });

        static::creating(function ($supplier) {
            $supplier->is_supplier = true;
        });
    }

    protected $fillable = [
        'name',
        'street',
        'address_2',
        'city',
        'state',
        'postal_code',
        'neighborhood',
        'country',
        'phone',
        'email',
        'description',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this supplier
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all catalog items for this supplier
     */
    public function catalogItems(): HasMany
    {
        return $this->hasMany(CatalogItem::class);
    }

    /**
     * Get the full address as a formatted string
     */
    public function getFullAddressAttribute(): string
    {
        if ($this->country === 'BR') {
            $addressParts = array_filter([
                $this->street,
                $this->address_2,
                $this->neighborhood,
                $this->city,
                $this->state,
                $this->postal_code,
            ]);
        } else {
            $addressParts = array_filter([
                $this->street,
                $this->address_2,
                $this->city,
                $this->state,
                $this->postal_code,
            ]);
        }

        return implode(', ', $addressParts);
    }
}
