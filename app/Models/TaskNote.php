<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One entry on a task's timeline. A note written during a meeting carries
 * that meeting, so the task screen shows "said at ATA-2026-014" and the
 * minute and the task can never tell different stories.
 */
class TaskNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'task_id',
        'user_id',
        'meeting_id',
        'body',
        'progress_snapshot',
        'edited_at',
        'edited_by',
    ];

    protected $casts = [
        'progress_snapshot' => 'integer',
        'edited_at' => 'datetime',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    public function availableFiles(): MorphMany
    {
        return $this->files()->where('upload_status', FileUpload::STATUS_AVAILABLE);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function wasWrittenInMeeting(): bool
    {
        return $this->meeting_id !== null;
    }

    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * The author may correct a note shortly after writing it; after that the
     * timeline is history and an edit has to show as one.
     */
    public function canEdit(?User $user): bool
    {
        return $user !== null
            && $this->user_id === $user->id
            && $this->created_at?->gt(now()->subMinutes(30));
    }
}
