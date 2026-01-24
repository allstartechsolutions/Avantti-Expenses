<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_site_id',
        'title',
        'requested_date',
        'description',
        'amount',
        'file_path',
        'created_by',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get/Set amount as dollars (stored as cents)
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the job site that owns this change order
     */
    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    /**
     * Get the user who created this change order
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
