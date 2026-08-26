<?php

namespace App\Models;

use App\Services\PermissionResolver;
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

    /**
     * These read the grants rather than the role (F2). `meetings.edit` and
     * `meetings.freeze` are seeded to exactly the people the old
     * `is_admin || is_manager` covered, so nothing moved — but they can now be
     * given to somebody else, or taken away, which is the whole difference.
     */
    protected function allows(?User $user, string $ability): bool
    {
        // Asked without a scope: a meeting belongs to no project — its ITEMS
        // carry the projects, which is the whole point of the module (M13) —
        // so `meetings` is a company-wide area.
        return $user !== null
            && app(PermissionResolver::class)->allows($user, $ability);
    }

    public function canEdit(?User $user): bool
    {
        return $this->isDraft() && $this->allows($user, 'meetings.edit');
    }

    /** The chair signs off the minute; whoever may freeze one can stand in. */
    public function canPublish(?User $user): bool
    {
        if ($user === null || ! $this->isDraft()) {
            return false;
        }

        return $this->allows($user, 'meetings.freeze') || $this->chair_id === $user->id;
    }

    /** Correcting a published record. Seeded to administrators, and it is logged. */
    public function canRevise(?User $user): bool
    {
        return $this->isPublished() && $this->allows($user, 'meetings.revise');
    }

    public function canCancel(?User $user): bool
    {
        return ! $this->isCancelled() && $this->allows($user, 'meetings.edit');
    }

    /**
     * A meeting can be deleted while it is not yet a record.
     *
     * **A published minute never can.** It has been frozen, filed into the
     * project repository and e-mailed to every attendee — deleting it would
     * leave the system disagreeing with the document people are holding. A
     * published minute that is wrong is *corrected* (`canRevise`); a meeting
     * that did not happen is *cancelled*, which keeps it in the record with its
     * reason. Deleting is only for the one this does not cover: a meeting
     * created by mistake.
     */
    public function canDelete(?User $user): bool
    {
        return ! $this->isPublished() && $this->allows($user, 'meetings.delete');
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
        // Its own keys for the same reason as Task: a *reunião* is feminine and
        // the shared "Cancelled" is translated in the masculine.
        return match ($this->status) {
            'draft' => __('Meeting status: draft'),
            'published' => __('Meeting status: published'),
            'cancelled' => __('Meeting status: cancelled'),
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
