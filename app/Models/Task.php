<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The living work behind a meeting item — and the whole task system.
 *
 * A task outlives every meeting it is discussed in, so there is one owner,
 * one progress figure and one answer to "is this done?" however many minutes
 * mention it. See docs/meetings-module-plan.md.
 *
 * Scope, per the parity rule extended by one step:
 *   project + job site — work on one site
 *   project only       — work on the project as a whole
 *   neither            — a standalone task, somebody's own work, which never
 *                        reaches a meeting agenda
 */
class Task extends Model
{
    use SoftDeletes;

    /** Statuses that count as open — the single definition used everywhere. */
    public const OPEN_STATUSES = ['open', 'in_progress', 'blocked', 'ready'];

    /** Tasks may nest one level: a sub-task cannot have children of its own. */
    public const MAX_DEPTH = 2;

    protected $fillable = [
        'uuid',
        'number',
        'title',
        'description',
        'project_id',
        'job_site_id',
        'parent_task_id',
        'owner_id',
        'priority',
        'status',
        'progress',
        'start_date',
        'due_date',
        'blocked_reason',
        'origin_meeting_id',
        'origin_item_id',
        'ready_at',
        'ready_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'overdue_notified_at',
        'created_by',
        'updated_by',
    ];

    /**
     * Defaults the database also applies. Kept here so a task that has not
     * been reloaded still answers isOpen(), getStatusLabel() and the guards
     * correctly.
     */
    protected $attributes = [
        'status' => 'open',
        'priority' => 'normal',
        'progress' => 0,
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'progress' => 'integer',
        'number' => 'integer',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $task) {
            $task->uuid ??= (string) Str::uuid();
            $task->number ??= static::nextNumber();
        });
    }

    /**
     * The next display code. Locked for the length of the insert so two
     * people raising a task in the same meeting cannot take the same number.
     */
    protected static function nextNumber(): int
    {
        return (int) DB::table('tasks')->lockForUpdate()->max('number') + 1;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id')->orderBy('due_date')->orderBy('id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Everyone working on it besides the owner, who is an implicit assignee. */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }

    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    /** Only the files that finished uploading. */
    public function availableFiles(): MorphMany
    {
        return $this->files()->where('upload_status', FileUpload::STATUS_AVAILABLE);
    }

    /** Every agenda line that ever discussed this task, oldest first. */
    public function meetingItems(): HasMany
    {
        return $this->hasMany(MeetingItem::class);
    }

    /** The meetings this task has been discussed in. */
    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_items', 'task_id', 'meeting_id')
            ->withPivot(['discussion', 'decision', 'status_at_meeting'])
            ->orderBy('meeting_date');
    }

    public function originMeeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'origin_meeting_id');
    }

    public function originItem(): BelongsTo
    {
        return $this->belongsTo(MeetingItem::class, 'origin_item_id');
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * The one definition of "open". Everything that counts open items — the
     * carry-forward, the badges, the digests — goes through here so the
     * rules cannot drift apart.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', ['completed', 'cancelled']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * Tasks that have reached an agenda at least once. Being meeting-tracked
     * is derived, never stored: the moment a task gains a meeting item it is
     * tracked, and it stays tracked, because dropping an item out of the
     * follow-up after it appeared in a published minute would break the
     * record.
     */
    public function scopeMeetingTracked(Builder $query): Builder
    {
        return $query->whereHas('meetingItems');
    }

    /**
     * Tasks raised on a project page, a job site page or standalone, which no
     * meeting has ever discussed. These are never proposed by the
     * carry-forward — the ata is what management committed to, not everyone's
     * to-do list.
     */
    public function scopeDirect(Builder $query): Builder
    {
        return $query->whereDoesntHave('meetingItems');
    }

    /** Top-level tasks only — sub-tasks are shown under their parent. */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_task_id');
    }

    /** Everything on a project, including its job sites' tasks. */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForJobSite(Builder $query, int $jobSiteId): Builder
    {
        return $query->where('job_site_id', $jobSiteId);
    }

    /** Tasks a user owns or is assigned to. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('owner_id', $userId)
                ->orWhereHas('assignees', fn (Builder $a) => $a->where('users.id', $userId));
        });
    }

    // =========================================================================
    // STATE
    // =========================================================================

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * Ready-but-unconfirmed still counts as overdue — otherwise a task parks
     * in 'ready' forever and the report says everything is fine.
     */
    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->due_date !== null
            && $this->due_date->startOfDay()->lt(Carbon::today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->startOfDay()->diffInDays(Carbon::today()) : 0;
    }

    public function daysUntilDue(): ?int
    {
        if ($this->due_date === null || $this->isClosed()) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->due_date->startOfDay(), false);
    }

    public function isMeetingTracked(): bool
    {
        return $this->meetingItems()->exists();
    }

    public function hasSubtasks(): bool
    {
        return $this->subtasks()->exists();
    }

    public function isSubtask(): bool
    {
        return $this->parent_task_id !== null;
    }

    public function isProjectLevel(): bool
    {
        return $this->project_id !== null && $this->job_site_id === null;
    }

    /** No project and no job site — somebody's own work. */
    public function isStandalone(): bool
    {
        return $this->project_id === null && $this->job_site_id === null;
    }

    public function code(): string
    {
        return '#'.$this->number;
    }

    // =========================================================================
    // GUARDS
    //
    // Called by the Blade (to hide the control) and by the Livewire action
    // (to refuse it). Neither may decide on its own.
    // =========================================================================

    /**
     * Only the owner may declare their own work ready — not an admin, not a
     * manager. An admin who has to force it changes the owner first, which is
     * logged.
     */
    public function canMarkReady(?User $user): bool
    {
        return $user !== null
            && $this->owner_id === $user->id
            && in_array($this->status, ['open', 'in_progress', 'blocked'], true)
            && ! $this->hasOpenSubtasks();
    }

    /** The chair of the meeting it came from, or any admin or manager. */
    public function canConfirmCompletion(?User $user): bool
    {
        if ($user === null || $this->status !== 'ready') {
            return false;
        }

        return $user->is_admin
            || $user->is_manager
            || $this->originMeeting?->chair_id === $user->id;
    }

    public function canReopen(?User $user): bool
    {
        return $user !== null
            && $this->status === 'completed'
            && ($user->is_admin || $user->is_manager || $this->originMeeting?->chair_id === $user->id);
    }

    public function canEdit(?User $user): bool
    {
        return $user !== null
            && $this->isOpen()
            && ($user->is_admin || $user->is_manager
                || $this->owner_id === $user->id
                || $this->created_by === $user->id);
    }

    /** Progress is the working figure: the owner and the assignees move it. */
    public function canChangeProgress(?User $user): bool
    {
        if ($user === null || ! $this->isOpen() || $this->hasSubtasks()) {
            return false;
        }

        return $user->is_admin
            || $user->is_manager
            || $this->owner_id === $user->id
            || $this->assignees->contains('id', $user->id);
    }

    public function canCancel(?User $user): bool
    {
        return $user !== null
            && $this->isOpen()
            && ($user->is_admin || $user->is_manager || $this->created_by === $user->id);
    }

    public function hasOpenSubtasks(): bool
    {
        return $this->subtasks()->open()->exists();
    }

    // =========================================================================
    // PROGRESS
    // =========================================================================

    /**
     * A task with sub-tasks does not carry a hand-keyed number: its progress
     * is the mean of its children. Completed children count 100, cancelled
     * children are left out of the average entirely.
     */
    public function isProgressDerived(): bool
    {
        return $this->hasSubtasks();
    }

    public function calculateProgressFromSubtasks(): int
    {
        $children = $this->subtasks()->where('status', '!=', 'cancelled')->get();

        if ($children->isEmpty()) {
            return $this->progress;
        }

        $total = $children->sum(fn (self $child) => $child->status === 'completed' ? 100 : $child->progress);

        return (int) round($total / $children->count());
    }

    /** Recompute and store the roll-up. Called whenever a sub-task moves. */
    public function refreshProgressFromSubtasks(): void
    {
        if (! $this->hasSubtasks()) {
            return;
        }

        $calculated = $this->calculateProgressFromSubtasks();

        if ($calculated !== $this->progress) {
            $this->forceFill(['progress' => $calculated])->save();
        }
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'open' => __('Open'),
            'in_progress' => __('In Progress'),
            'blocked' => __('Blocked'),
            'ready' => __('Awaiting Confirmation'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'open' => 'gray',
            'in_progress' => 'blue',
            'blocked' => 'red',
            'ready' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            'low' => __('Low'),
            'normal' => __('Normal'),
            'high' => __('High'),
            'urgent' => __('Urgent'),
            default => ucfirst($this->priority),
        };
    }

    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            'low' => 'gray',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    /** "Project › Job Site", or "General" for a standalone task. */
    public function getScopeLabel(): string
    {
        if ($this->isStandalone()) {
            return __('General');
        }

        if ($this->job_site_id !== null) {
            return $this->project?->project_name.' › '.$this->jobSite?->job_site_name;
        }

        return (string) $this->project?->project_name;
    }
}
