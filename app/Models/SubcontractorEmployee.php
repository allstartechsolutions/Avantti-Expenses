<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcontractorEmployee extends Model
{
    protected $fillable = [
        'subcontractor_id',
        'title',
        'name',
        'phone',
        'email',
        'notes',
    ];

    /**
     * Get the subcontractor that owns this employee
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    /**
     * Get the contracts linked to this employee
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
