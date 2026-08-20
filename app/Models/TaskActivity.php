<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The audit trail of a task, in the same spirit as the *_status_histories
 * tables elsewhere in the app: "who moved my due date?" has to be answerable
 * from the database.
 */
class TaskActivity extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'notes',
        'meeting_id',
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

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'created' => __('Task created'),
            'status_changed' => __('Status changed'),
            'progress_changed' => __('Progress updated'),
            'due_date_changed' => __('Due date changed'),
            'owner_changed' => __('Owner changed'),
            'assignee_added' => __('Assignee added'),
            'assignee_removed' => __('Assignee removed'),
            'note_added' => __('Note added'),
            'file_added' => __('File attached'),
            'discussed' => __('Discussed in meeting'),
            'reopened' => __('Reopened'),
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
