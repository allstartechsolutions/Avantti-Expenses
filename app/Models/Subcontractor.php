<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcontractor extends Model
{
    use HasFormattedPhone, HasFactory;
    use \App\Models\Concerns\DeletesVendorDocuments;
    use \App\Models\Concerns\HasDocumentHealth;

    /**
     * Subcontractors live in the unified `vendors` table (shared with
     * Supplier), scoped to rows flagged is_subcontractor. One vendor row can
     * be flagged as both. See docs/vendor-unification.md.
     */
    protected $table = 'vendors';

    protected static function booted(): void
    {
        static::addGlobalScope('subcontractor', function (Builder $query) {
            $query->where('vendors.is_subcontractor', true);
        });

        static::creating(function ($subcontractor) {
            $subcontractor->is_subcontractor = true;
        });
    }

    /**
     * The legacy `company_name` attribute maps to the unified `name` column.
     * Queries (where/orderBy/select) must use `name`; property access and
     * mass assignment may keep using `company_name`.
     */
    protected function companyName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['name'] ?? null,
            set: fn ($value) => ['name' => $value],
        );
    }

    protected $fillable = [
        'company_name',
        'website',
        'contact_name',
        'contact_email',
        'title',
        'phone',
        'street',
        'address_2',
        'neighborhood',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this subcontractor
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all contracts for this subcontractor
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get all documents for this subcontractor
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SubcontractorDocument::class);
    }

    /**
     * Get all employees for this subcontractor
     */
    public function employees(): HasMany
    {
        return $this->hasMany(SubcontractorEmployee::class);
    }

    /**
     * Get all payment batches for this subcontractor
     */
    public function paymentBatches(): HasMany
    {
        return $this->hasMany(PaymentBatch::class);
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

    /**
     * Get subcontractor initials for avatar
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->company_name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($this->company_name, 0, 2));
    }
}
