<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCode extends Model
{
    protected $fillable = [
        'template_id',
        'parent_id',
        'code',
        'name',
        'description',
        'sort_order',
        'requires_approval',
        'default_approval_type',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'requires_approval' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CostCodeTemplate::class, 'template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CostCode::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Check if this cost code is a parent (has no parent_id).
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this cost code is a child (has a parent_id).
     */
    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Get the full code path (parent code + this code).
     */
    public function getFullCodeAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->code . '.' . $this->code;
        }

        return $this->code;
    }

    /**
     * Get the full name path (parent name > this name).
     */
    public function getFullNameAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->name . ' > ' . $this->name;
        }

        return $this->name;
    }
}
