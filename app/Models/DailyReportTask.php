<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DailyReportTask extends Model
{
    protected $fillable = [
        'daily_report_id',
        'description',
        'order',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(DailyReportImage::class, 'imageable');
    }
}
