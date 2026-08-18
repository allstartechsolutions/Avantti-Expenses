<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One job site's share of a project-level income.
 *
 * @see Income::syncDistributions() — the only supported way to write these.
 */
class IncomeDistribution extends Model
{
    protected $fillable = [
        'income_id',
        'job_site_id',
        'amount',
    ];

    /**
     * Get/Set amount as dollars (stored as cents) — same as Income.
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }
}
