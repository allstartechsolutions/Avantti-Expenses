<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * One meeting, and once published, one minute (ata de reunião).
 *
 * A meeting is company-level: it spans as many projects as were on the
 * agenda, and the project scope lives on the items rather than here.
 *
 * A published meeting is a frozen record. Corrections are written to
 * meeting_revisions and shown on the document; nothing changes silently.
 */
class Meeting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'meeting_series_id',
        'number',
        'title',
        'meeting_date',
        'started_at',
        'ended_at',
        'location',
        'meeting_url',
        'chair_id',
        'secretary_id',
        'status',
        'previous_meeting_id',
        'next_meeting_id',
        'next_meeting_date',
        'summary',
        'published_at',
        'published_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'document_id',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'next_meeting_date' => 'date',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // =========================================================================
    // NUMBERING
    // =========================================================================

    /**
     * OBRA-2026-014 — the series code, the year, and a sequence that restarts
     * each year within each series. Ad-hoc meetings use the ATA prefix.
     */
    public static function nextNumber(?MeetingSeries $series, int $year): string
    {
        $prefix = ($series?->code ?: 'ATA').'-'.$year.'-';

        $last = DB::table('meetings')
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function series(): BelongsTo
    {
        return $this->belongsTo(MeetingSeries::class, 'meeting_series_id');
    }

    /** Top-level agenda lines; sub-items hang off each one. */
    public function items(): HasMany
    {
        return $this->hasMany(MeetingItem::class)->whereNull('parent_id')->orderBy('position');
    }

    /** Every line including sub-items — for counting and for the PDF. */
    public function allItems(): HasMany
    {
        return $this->hasMany(MeetingItem::class)->orderBy('position');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(MeetingRevision::class)->orderByDesc('revision_number');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    public function previousMeeting(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_meeting_id');
    }

    public function nextMeeting(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_meeting_id');
    }

    /** The rendered minute, once filed into the project repository. */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Tasks raised at this meeting for the first time. */
    public function tasksRaised(): HasMany
    {
        return $this->hasMany(Task::class, 'origin_meeting_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
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

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Everything that is still a real meeting — cancelled ones are not. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /** Meetings that put a given project on the agenda. */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->whereHas('allItems', fn (Builder $q) => $q->where('project_id', $projectId));
    }

    public function scopeForJobSite(Builder $query, int $jobSiteId): Builder
    {
        return $query->whereHas('allItems', fn (Builder $q) => $q->where('job_site_id', $jobSiteId));
    }

    // =========================================================================
    // STATE
    // =========================================================================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** A published minute is a record: it stops being ordinarily editable. */
    public function isLocked(): bool
    {
        return $this->status !== 'draft';
    }

    // =========================================================================
    // GUARDS
    // =========================================================================

    public function canEdit(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->isDraft() && ($user->is_admin || $user->is_manager);
    }

    /** The chair signs off the minute; admins and managers may stand in. */
    public function canPublish(?User $user): bool
    {
        if ($user === null || ! $this->isDraft()) {
            return false;
        }

        return $user->is_admin || $user->is_manager || $this->chair_id === $user->id;
    }

    /** Only an admin corrects a published record, and it is logged. */
    public function canRevise(?User $user): bool
    {
        return $user !== null && $this->isPublished() && $user->is_admin;
    }

    public function canCancel(?User $user): bool
    {
        return $user !== null && ! $this->isCancelled() && ($user->is_admin || $user->is_manager);
    }

    /**
     * Every action item needs somebody's name against it and a date, or the
     * minute promises something nobody owns.
     */
    public function actionItemsMissingOwnerOrDate(): \Illuminate\Support\Collection
    {
        return $this->allItems()
            ->where('type', 'action')
            ->with('task')
            ->get()
            ->filter(fn (MeetingItem $item) => $item->task === null
                || $item->task->owner_id === null
                || $item->task->due_date === null);
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => __('Draft'),
            'published' => __('Published'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'yellow',
            'published' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function presentCount(): int
    {
        return $this->attendees->where('attendance', 'present')->count();
    }

    /** How many action items from this meeting are still open today. */
    public function openActionCount(): int
    {
        return Task::query()
            ->open()
            ->whereIn('id', $this->allItems()->whereNotNull('task_id')->pluck('task_id'))
            ->count();
    }
}
