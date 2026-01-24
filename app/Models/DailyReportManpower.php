<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DailyReportManpower extends Model
{
    use HasFactory;

    protected $table = 'daily_report_manpower';

    protected $fillable = [
        'daily_report_id',
        'contact_company',
        'workers',
        'works',
        'hours',
        'comments',
        'order',
    ];

    protected $casts = [
        'workers' => 'integer',
        'hours' => 'decimal:2',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the daily report that owns this manpower log entry.
     */
    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * Get the images for this manpower log entry.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(DailyReportImage::class, 'imageable');
    }

    /**
     * Scope to order entries by order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
