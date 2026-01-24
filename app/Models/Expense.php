<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    protected $fillable = [
        'job_site_id',
        'catalog_item_id',
        'item_name',
        'item_type',
        'purchase_unit',
        'usage_unit',
        'unit_type_used',
        'quantity',
        'unit_price',
        'total_amount',
        'notes',
        'receipt_path',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expense_date' => 'date',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($expense) {
            if ($expense->receipt_path && Storage::exists($expense->receipt_path)) {
                Storage::delete($expense->receipt_path);
            }
        });
    }

    /**
     * Get/Set unit_price as dollars (stored as cents)
     */
    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get/Set total_amount as dollars (stored as cents)
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the job site this expense belongs to
     */
    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    /**
     * Get the catalog item (if from catalog)
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * Get the user who created this expense
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this is a custom (non-catalog) expense
     */
    public function isCustom(): bool
    {
        return is_null($this->catalog_item_id);
    }

    /**
     * Get the unit name that was actually used for this expense
     */
    public function getDisplayUnit(): string
    {
        if ($this->unit_type_used === 'purchase') {
            return $this->purchase_unit ?? '';
        } elseif ($this->unit_type_used === 'usage') {
            return $this->usage_unit ?? '';
        }
        return $this->usage_unit ?? ''; // fallback for custom items
    }
}
