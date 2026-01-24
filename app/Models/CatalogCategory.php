<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CatalogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'applicable_types',
        'parent_id',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'applicable_types' => 'array',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Get the parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'parent_id');
    }

    /**
     * Get child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(CatalogCategory::class, 'parent_id')->orderBy('display_order');
    }

    /**
     * Get catalog items in this category
     */
    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'category_id');
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for root categories (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
