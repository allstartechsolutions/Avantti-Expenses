<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bundle of approvals submitted together — the US submittal package. */
class ApprovalPackage extends Model
{
    protected $fillable = ['project_id', 'number', 'title', 'status', 'created_by_id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'package_id');
    }
}
