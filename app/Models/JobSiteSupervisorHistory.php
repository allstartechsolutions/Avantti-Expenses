<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSiteSupervisorHistory extends Model
{
    protected $fillable = [
        'job_site_id',
        'old_supervisor_id',
        'new_supervisor_id',
        'changed_by',
        'note',
    ];

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function oldSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_supervisor_id');
    }

    public function newSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_supervisor_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
