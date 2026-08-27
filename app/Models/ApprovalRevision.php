<?php

namespace App\Models;

use App\Models\Collaboration\ResponseCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One round of an approval: what was put forward, and what came back.
 */
class ApprovalRevision extends Model
{
    protected $fillable = [
        'approval_id',
        'revision',
        'submitted_by_id',
        'submitted_at',
        'response_code_id',
        'responded_by_id',
        'responded_at',
        'comments',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(ApprovalReviewer::class)->orderBy('sequence')->orderBy('id');
    }

    public function responseCode(): BelongsTo
    {
        return $this->belongsTo(ResponseCode::class, 'response_code_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }

    /** What was submitted for this round. */
    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    public function availableFiles(): MorphMany
    {
        return $this->files()->where('upload_status', FileUpload::STATUS_AVAILABLE);
    }

    public function isAnswered(): bool
    {
        return $this->responded_at !== null;
    }

    /*
    |---------------------------------------------------------------------------
    | The order of review
    |---------------------------------------------------------------------------
    | One mechanism, both shapes: reviewers sharing a `sequence` look at the
    | revision together, and a higher `sequence` waits for the one below to
    | finish. See the migration.
    */

    /**
     * The reviewers whose turn it is now.
     *
     * The lowest sequence that still has somebody outstanding. Everyone at
     * that level is waiting together; nobody above it has been asked yet.
     *
     * @return \Illuminate\Support\Collection<int, ApprovalReviewer>
     */
    public function currentReviewers()
    {
        $outstanding = $this->reviewers->whereNull('responded_at');

        if ($outstanding->isEmpty()) {
            return $outstanding;
        }

        $turn = $outstanding->min('sequence');

        return $outstanding->where('sequence', $turn)->values();
    }

    /** Whether this person is being waited on right now. */
    public function isWaitingOn(?User $user): bool
    {
        return $user !== null
            && $this->currentReviewers()->contains(fn (ApprovalReviewer $r) => $r->user_id === $user->id);
    }

    /** True once every reviewer has answered. */
    public function everyReviewerHasResponded(): bool
    {
        return $this->reviewers->isNotEmpty()
            && $this->reviewers->whereNull('responded_at')->isEmpty();
    }

    /**
     * Whether this round is finished.
     *
     * A revision with no reviewers at all is not finished — it has not been
     * asked of anybody. Treating "nobody was asked" as "everybody agreed"
     * would close approvals nobody ever looked at.
     */
    public function isComplete(): bool
    {
        return $this->isAnswered();
    }
}
