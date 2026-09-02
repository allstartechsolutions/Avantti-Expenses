<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'requires_expiration',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'requires_expiration' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get all documents of this type
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SubcontractorDocument::class);
    }

    /**
     * Types still offered on the upload picker. A retired type keeps every
     * document already filed under it.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
