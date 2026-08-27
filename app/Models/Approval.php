<?php

namespace App\Models;

use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Collaboration\ResponseCode;
use App\Models\Concerns\Collaboration\BallInCourt;
use App\Models\Concerns\Collaboration\HasDistributionList;
use App\Models\Concerns\Collaboration\HasSequentialNumber;
use App\Models\Concerns\Collaboration\HasSignatures;
use App\Models\Concerns\Collaboration\LogsCollaborationActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aprovação — a material, a sample, a shop drawing or a certificate put
 * forward for somebody to accept.
 *
 * The approval is the subject; each round of it is an `ApprovalRevision`. That
 * split is the whole design: a rejection is a fact about the submission that
 * was rejected, not about the material, so a second attempt must not erase the
 * record of the first.
 *
 * **Business logic reads `canonical`, never the letter.** Whether a response
 * ends the cycle is `closes_cycle` on the code row; whether it opens the next
 * revision is `canonical === revise_resubmit`. A company that renames C to R,
 * or an install running the US letters, changes what the reviewer sees and
 * nothing about what happens.
 */
class Approval extends Model
{
    use BallInCourt;
    use HasDistributionList;
    use HasSequentialNumber;
    use HasSignatures;
    use LogsCollaborationActivity;
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'job_site_id',
        'number',
        'title',
        'description',
        'type',
        'spec_section',
        'budget_item_id',
        'catalog_item_id',
        'supplier_id',
        'current_revision',
        'status',
        'ball_in_court_id',
        'due_date',
        'package_id',
        'created_by_id',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    /*
    |---------------------------------------------------------------------------
    | Status and type
    |---------------------------------------------------------------------------
    */

    public const DRAFT = 'draft';
    public const IN_REVIEW = 'in_review';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const VOID = 'void';

    public const STATUSES = [self::DRAFT, self::IN_REVIEW, self::APPROVED, self::REJECTED, self::VOID];

    /**
     * Still somebody's move — the complement of `isClosed()`, and it has to
     * stay that way.
     *
     * `rejected` belongs here. A rejection ends the *revision*, not the
     * approval: `settleFrom()` hands it back to whoever raised it and
     * `submit()` accepts the next round. Leaving it out made a rejected
     * approval disappear from the default "Open approvals" list — the one
     * approval on the screen that actually needed somebody to do something.
     */
    public const LIVE_STATUSES = [self::DRAFT, self::IN_REVIEW, self::REJECTED];

    public const TYPE_MATERIAL = 'material';
    public const TYPE_SAMPLE = 'amostra';
    public const TYPE_SHOP_DRAWING = 'shop_drawing';
    public const TYPE_PROTOTYPE = 'prototipo';
    public const TYPE_DATA_SHEET = 'ficha_tecnica';
    public const TYPE_CERTIFICATE = 'laudo_certificado';
    public const TYPE_AS_BUILT = 'as_built';

    public const TYPES = [
        self::TYPE_MATERIAL,
        self::TYPE_SAMPLE,
        self::TYPE_SHOP_DRAWING,
        self::TYPE_PROTOTYPE,
        self::TYPE_DATA_SHEET,
        self::TYPE_CERTIFICATE,
        self::TYPE_AS_BUILT,
    ];

    /*
    |---------------------------------------------------------------------------
    | Relationships
    |---------------------------------------------------------------------------
    */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ApprovalRevision::class)->orderBy('id');
    }

    /** The round in hand. */
    public function currentRevisionRecord(): HasOne
    {
        return $this->hasOne(ApprovalRevision::class)->latestOfMany('id');
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(ApprovalCertificateDetail::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ApprovalPackage::class, 'package_id');
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    public function availableFiles(): MorphMany
    {
        return $this->files()->where('upload_status', FileUpload::STATUS_AVAILABLE);
    }

    /*
    |---------------------------------------------------------------------------
    | The collaboration engine's contract
    |---------------------------------------------------------------------------
    */

    public function documentType(): string
    {
        return 'approval';
    }

    public function numberTokens(): array
    {
        return ['prefix' => config('app.country') === 'BR' ? 'APR' : 'SUB'];
    }

    /**
     * What a signer is putting their name to.
     *
     * Neither `status` nor `current_revision`: both move as the cycle runs, so
     * including them meant a signature was reported broken the moment the next
     * response came in. What is signed is the approval's identity and what it
     * says it is.
     */
    public function signaturePayload(): array
    {
        return [
            'number' => $this->number,
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
        ];
    }


    /*
    |---------------------------------------------------------------------------
    | The revision cycle
    |---------------------------------------------------------------------------
    */

    /**
     * Put this approval forward for review.
     *
     * Opens revision 0 the first time and the next number each time after,
     * with the reviewers it is being asked of. Each entry is
     * ['user_id' => int, 'sequence' => int, 'role' => ?string]; equal
     * sequences review together, ascending ones in turn.
     *
     * @param  array<int, array<string, mixed>>  $reviewers
     *
     * @throws ValidationException
     */
    public function submit(array $reviewers, ?User $actor = null): ApprovalRevision
    {
        if ($this->isClosed()) {
            throw ValidationException::withMessages([
                'submit' => __('collaboration.help.approval_closed_nothing_further_can'),
            ]);
        }

        if ($open = $this->openRevision()) {
            throw ValidationException::withMessages([
                'submit' => __('collaboration.help.revision_still_reviewers_record_response', [
                    'revision' => $open->revision,
                ]),
            ]);
        }

        // A revision nobody has been asked to look at can never complete, and
        // would sit as "in review" for ever.
        $reviewers = array_values(array_filter($reviewers, fn (array $r) => ! empty($r['user_id'])));

        if ($reviewers === []) {
            throw ValidationException::withMessages([
                'reviewers' => __('collaboration.help.name_least_one_reviewer_submission'),
            ]);
        }

        return DB::transaction(function () use ($reviewers, $actor) {
            $revision = $this->revisions()->create([
                'revision' => $this->nextRevisionLabel(),
                'submitted_by_id' => $actor?->id,
                'submitted_at' => now(),
            ]);

            $seen = [];

            foreach ($reviewers as $entry) {
                $userId = (int) $entry['user_id'];

                // One person reviews a revision once, whatever the form sent.
                if (in_array($userId, $seen, true)) {
                    continue;
                }

                $seen[] = $userId;

                $revision->reviewers()->create([
                    'user_id' => $userId,
                    'sequence' => (int) ($entry['sequence'] ?? 1),
                    'role' => $entry['role'] ?? null,
                ]);
            }

            $this->fill([
                'current_revision' => $revision->revision,
                'status' => self::IN_REVIEW,
            ])->save();

            $revision->load('reviewers');
            $this->handToCurrentReviewers($revision);

            $this->logActivity(ActivityLogEntry::SUBMITTED, ['revision' => $revision->revision]);

            return $revision;
        });
    }

    /**
     * Record one reviewer's answer.
     *
     * The revision closes when the code's `closes_cycle` is true **and** every
     * reviewer in sequence has answered — the last word belongs to the last
     * reviewer, which is what makes a chain a chain rather than a race.
     *
     * A `revise_resubmit` is different: it ends the round immediately whoever
     * says it, because there is no point asking the engineer to review a
     * drawing the architect has already sent back.
     *
     * @throws ValidationException
     */
    public function recordResponse(
        ResponseCode $code,
        User $reviewer,
        ?string $comments = null,
    ): ApprovalRevision {
        $revision = $this->openRevision();

        if (! $revision) {
            throw ValidationException::withMessages([
                'response' => __('collaboration.message.there_open_revision_respond'),
            ]);
        }

        $revision->load('reviewers');

        $row = $revision->reviewers->firstWhere('user_id', $reviewer->id);

        if (! $row) {
            throw ValidationException::withMessages([
                'response' => __('collaboration.message.reviewer_revision'),
            ]);
        }

        if ($row->hasResponded()) {
            throw ValidationException::withMessages([
                'response' => __('collaboration.message.already_responded_revision'),
            ]);
        }

        if (! $revision->isWaitingOn($reviewer)) {
            throw ValidationException::withMessages([
                'response' => __('collaboration.help.turn_revision_still_earlier_reviewer'),
            ]);
        }

        return DB::transaction(function () use ($revision, $row, $code, $reviewer, $comments) {
            $row->update(['responded_at' => now()]);

            $revision->load('reviewers');

            $sendsBack = $code->opensRevision();
            $lastWord = $sendsBack || $revision->everyReviewerHasResponded();

            if ($lastWord) {
                $revision->update([
                    'response_code_id' => $code->id,
                    'responded_by_id' => $reviewer->id,
                    'responded_at' => now(),
                    'comments' => $comments,
                ]);

                $this->settleFrom($code);
            } elseif ($comments) {
                // An interim reviewer's remarks are kept, but the coded answer
                // is still the final reviewer's to give.
                $revision->update([
                    'comments' => trim(($revision->comments ? $revision->comments."\n\n" : '')
                        .$reviewer->name.': '.$comments),
                ]);
            }

            $this->logActivity(ActivityLogEntry::RESPONDED, [
                'revision' => $revision->revision,
                'canonical' => $code->canonical,
                'code' => $code->code,
                'final' => $lastWord,
            ]);

            if (! $lastWord) {
                $this->handToCurrentReviewers($revision->fresh('reviewers'));
            }

            return $revision->fresh();
        });
    }

    /**
     * Where the approval lands once a revision has its answer.
     *
     * `revise_resubmit` leaves it live and waiting for the next submission —
     * that is the round-trip the whole cycle exists for. `rejected` closes the
     * revision, not the approval: what follows a rejection is a fresh
     * submission, which is a new cycle.
     */
    protected function settleFrom(ResponseCode $code): void
    {
        [$status, $ball] = match (true) {
            $code->opensRevision() => [self::IN_REVIEW, $this->created_by_id],
            $code->canonical === ResponseCode::REJECTED => [self::REJECTED, $this->created_by_id],
            $code->closesCycle() => [self::APPROVED, null],
            default => [self::IN_REVIEW, $this->created_by_id],
        };

        $this->fill(['status' => $status, 'ball_in_court_id' => $ball])->save();
    }

    /** Park the approval with whoever is being waited on now. */
    protected function handToCurrentReviewers(ApprovalRevision $revision): void
    {
        $current = $revision->currentReviewers();

        // With several people reviewing together there is no single holder;
        // the first by sequence then id stands for the group, and the screen
        // lists them all.
        $this->fill(['ball_in_court_id' => $current->first()?->user_id])->save();
    }

    /** The round still out for review, if there is one. */
    public function openRevision(): ?ApprovalRevision
    {
        return $this->revisions()->whereNull('responded_at')->latest('id')->first();
    }

    /** 0, 1, 2… — the next label in this approval's own scheme. */
    protected function nextRevisionLabel(): string
    {
        $last = $this->revisions()->latest('id')->value('revision');

        if ($last === null) {
            return '0';
        }

        // Letters stay letters (0, A, B…), numbers stay numbers.
        if (! is_numeric($last)) {
            // Str::increment-style rollover rather than chr(ord+1), which turns
            // Z into '[' — a silent corruption where a refusal or a carry is
            // wanted. 'Z' becomes 'AA'.
            return strtoupper(++$last);
        }

        return (string) ((int) $last + 1);
    }

    /*
    |---------------------------------------------------------------------------
    | State
    |---------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::APPROVED, self::VOID], true);
    }

    public function isInReview(): bool
    {
        return $this->openRevision() !== null;
    }

    /** A certificate whose validity has run out, or is about to. */
    public function certificateNeedsAttention(): bool
    {
        return $this->type === self::TYPE_CERTIFICATE
            && $this->certificate
            && ($this->certificate->hasExpired() || $this->certificate->expiresWithin());
    }

    /*
    |---------------------------------------------------------------------------
    | Labels — never print a stored value
    |---------------------------------------------------------------------------
    | *Aprovação* is feminine, so the participles agree: "Aprovada", not
    | "Aprovado". The shared status words in this codebase are masculine, which
    | is why these carry keys of their own.
    */

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::DRAFT => __('collaboration.approval.status.draft'),
            self::IN_REVIEW => __('collaboration.approval.status.review'),
            self::APPROVED => __('collaboration.approval.status.approved'),
            self::REJECTED => __('collaboration.approval.status.rejected'),
            self::VOID => __('collaboration.approval.status.void'),
            default => (string) $status,
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::TYPE_MATERIAL => __('collaboration.approval.type.material'),
            self::TYPE_SAMPLE => __('collaboration.approval.type.sample'),
            self::TYPE_SHOP_DRAWING => __('collaboration.approval.type.shop_drawing'),
            self::TYPE_PROTOTYPE => __('collaboration.approval.type.prototype'),
            self::TYPE_DATA_SHEET => __('collaboration.approval.type.data_sheet'),
            self::TYPE_CERTIFICATE => __('collaboration.approval.type.certificate'),
            self::TYPE_AS_BUILT => __('collaboration.approval.type.built'),
            default => (string) $type,
        };
    }

    public function getTypeLabel(): string
    {
        return static::typeLabel($this->type);
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)
            ->mapWithKeys(fn (string $s) => [$s => static::statusLabel($s)])
            ->all();
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return collect(self::TYPES)
            ->mapWithKeys(fn (string $t) => [$t => static::typeLabel($t)])
            ->all();
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    */

    /**
     * Which approvals this person may see at all.
     *
     * Same shape as `Rfi::scopeVisibleTo()`, and pinned in
     * `BridgeRemovedTest` for the same reason: the resolver bypasses ability
     * checks for an administrator, so a list that hid records from one would
     * disagree with the guard that lets them open it.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || ! $user->isActive()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || ! $user->isConfined()) {
            return $query;
        }

        $projectIds = [];
        $jobSiteIds = [];

        foreach (app(\App\Services\PermissionResolver::class)->membershipsOf($user) as $membership) {
            if ($membership->scopeable_type === Project::class) {
                $projectIds[] = $membership->scopeable_id;
            } elseif ($membership->scopeable_type === JobSite::class) {
                $jobSiteIds[] = $membership->scopeable_id;
            }
        }

        return $query->where(function (Builder $q) use ($projectIds, $jobSiteIds) {
            $q->whereIn('project_id', $projectIds)
                ->orWhereIn('job_site_id', $jobSiteIds);
        });
    }

    /** Live work only — what the index shows before anybody filters. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', self::LIVE_STATUSES);
    }

    /** Approvals waiting on this person's own review right now. */
    public function scopeAwaitingReviewBy(Builder $query, User|int|null $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return $query->whereHas('revisions', fn (Builder $r) => $r
            ->whereNull('responded_at')
            ->whereHas('reviewers', fn (Builder $rv) => $rv
                ->where('user_id', $id)
                ->whereNull('responded_at')));
    }
}
