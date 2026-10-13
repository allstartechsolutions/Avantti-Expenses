<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    /** The windows a type can choose for "expiring soon" — they match the reminder stages. */
    public const EXPIRY_WARNING_OPTIONS = [7, 15, 30];

    public const DEFAULT_EXPIRY_WARNING_DAYS = 30;

    protected $fillable = [
        'key',
        'name',
        'description',
        'requires_expiration',
        'expiry_warning_days',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'requires_expiration' => 'boolean',
        'expiry_warning_days' => 'integer',
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
