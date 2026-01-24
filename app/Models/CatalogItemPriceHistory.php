<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItemPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'catalog_item_price_history';

    public $timestamps = false;

    protected $fillable = [
        'catalog_item_id',
        'old_cost',
        'new_cost',
        'changed_by',
        'changed_at',
        'notes',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Get/Set old_cost as dollars (stored as cents)
     */
    protected function oldCost(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get/Set new_cost as dollars (stored as cents)
     */
    protected function newCost(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the catalog item
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * Get the user who changed the price
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the price change amount
     */
    public function getPriceChangeAttribute()
    {
        return $this->new_cost - $this->old_cost;
    }

    /**
     * Get the price change percentage
     */
    public function getPriceChangePercentageAttribute()
    {
        if ($this->old_cost > 0) {
            return (($this->new_cost - $this->old_cost) / $this->old_cost) * 100;
        }
        return 0;
    }
}
