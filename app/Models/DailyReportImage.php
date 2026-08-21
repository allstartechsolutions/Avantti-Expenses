<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DailyReportImage extends Model
{
    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'file_path',
        'file_name',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The daily report this photo ultimately belongs to.
     *
     * An image hangs off a task or a manpower log, never off the report
     * directly, so the walk goes one step further than usual. Used by
     * `FileController` to answer who may fetch the file (M14).
     */
    public function owningReport(): ?DailyReport
    {
        $owner = $this->imageable;

        return match (true) {
            $owner instanceof DailyReportTask => $owner->dailyReport,
            $owner instanceof DailyReportManpower => $owner->dailyReport,
            $owner instanceof DailyReport => $owner,
            default => null,
        };
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
