<?php

namespace App\Models;

use App\Models\Concerns\Collaboration\BallInCourt;
use App\Models\Concerns\Collaboration\HasDistributionList;
use App\Models\Concerns\Collaboration\HasSequentialNumber;
use App\Models\Concerns\Collaboration\HasSignatures;
use App\Models\Concerns\Collaboration\LogsCollaborationActivity;
use App\Models\Collaboration\ActivityLogEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * A formal question, and the answer somebody is accountable for.
 *
 * The rule that shapes this class: **once an RFI closes, its answer is
 * frozen.** A record that has been e-mailed to a projetista and quoted in a
 * change order cannot quietly say something different a month later. A
 * correction is a new entry in the history, made by somebody holding
 * `rfis.revise`, and it says on the document that it was corrected — the same
 * treatment a published meeting minute gets, for the same reason.
 *
 * That rule is enforced in a saving observer, not in the form. A form guard
 * protects the form; this protects the record.
 */
class Rfi extends Model
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
        'subject',
        'question',
        'discipline',
        'spec_section',
        'drawing_ref',
        'status',
        'priority',
        'ball_in_court_id',
        'due_date',
        'cost_impact',
        'cost_impact_amount',
        'schedule_impact',
        'schedule_impact_days',
        'answer',
        'valid_reply_id',
        'answered_by_id',
        'answered_at',
        'change_order_id',
        'change_order_answer',
        'change_order_linked_at',
        'created_by_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'answered_at' => 'datetime',
        'change_order_linked_at' => 'datetime',
        'cost_impact' => 'boolean',
        'cost_impact_amount' => 'integer',
        'schedule_impact' => 'boolean',
        'schedule_impact_days' => 'integer',
    ];

    /**
     * Get/set the estimated cost impact in the major unit (stored as signed
     * cents), the same way `ChangeOrder::amount` works.
     *
     * Signed on purpose: an answer that saves money is as real an impact as
     * one that costs it.
     */
    protected function costImpactAmount(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => $value === null ? null : round($value / 100, 2),
            set: fn ($value) => $value === null || $value === '' ? null : (int) round((float) $value * 100),
        );
    }

    /*
    |---------------------------------------------------------------------------
    | Status
    |---------------------------------------------------------------------------
    | Canonical values, shared with approvals. Business logic reads these;
    | screens read getStatusLabel().
    */

    public const DRAFT = 'draft';
    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';
    public const VOID = 'void';

    public const STATUSES = [self::DRAFT, self::OPEN, self::ANSWERED, self::CLOSED, self::VOID];

    /** Still somebody's move. */
    public const LIVE_STATUSES = [self::DRAFT, self::OPEN, self::ANSWERED];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /*
    |---------------------------------------------------------------------------
    | The freeze
    |---------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        /*
         * Deleting an RFI has to take its debris with it.
         *
         * Three of the four children hang off polymorphic columns with no
         * foreign key, so nothing cleans them up: the distribution list, the
         * activity log, and — the expensive one — the uploaded files, whose R2
         * objects Cloudflare goes on billing long after the row is gone.
         *
         * The replies DO have a cascading foreign key, and are still deleted
         * here on purpose: a database-level cascade is enforced by MySQL in
         * production and not by SQLite under test, so leaving it to the schema
         * means the two environments quietly disagree about what a delete
         * does. Doing it in one place makes them agree.
         *
         * A file that has also been filed into the project repository is left
         * on R2 deliberately: the Document that points at it is a record of its
         * own and must not be broken by tidying up an RFI.
         */
        static::deleting(function (self $rfi) {
            $storage = app(\App\Services\DocumentStorageService::class);

            foreach ($rfi->files as $file) {
                if ($file->document_id === null) {
                    try {
                        $storage->deleteObject($file);
                    } catch (\Throwable $e) {
                        // A bucket that is unreachable must not strand the
                        // record on screen; the object becomes prunable
                        // debris, which is the lesser problem.
                        \Illuminate\Support\Facades\Log::warning('RFI file could not be removed from storage', [
                            'rfi' => $rfi->id, 'file' => $file->id, 'error' => $e->getMessage(),
                        ]);
                    }
                }

                $file->forceDelete();
            }

            // Before the replies: the RFI points at one of them as the
            // answer, and that reference has to go first.
            $rfi->forceFill(['valid_reply_id' => null])->saveQuietly();

            $rfi->replies()->delete();
            $rfi->distribution()->delete();
            $rfi->activity()->delete();
        });

        static::saving(function (self $rfi) {
            if (! $rfi->exists) {
                return;
            }

            $wasClosed = $rfi->getOriginal('status') === self::CLOSED;

            if (! $wasClosed || $rfi->reviseUnlocked) {
                return;
            }

            // Reopening is a decision of its own and is allowed; changing what
            // the closed record SAYS is not.
            $frozen = array_intersect(
                array_keys($rfi->getDirty()),
                ['answer', 'answered_at', 'answered_by_id', 'question', 'subject'],
            );

            if ($frozen !== []) {
                throw ValidationException::withMessages([
                    'answer' => __('collaboration.help.rfi_closed_question_answer_can'),
                ]);
            }
        });
    }

    /**
     * Set for the duration of one save by revise(), and by nothing else.
     *
     * Not a column and not fillable: the only way past the freeze is the
     * method that logs the correction.
     */
    protected bool $reviseUnlocked = false;

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

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }

    /** Every reply, newest first — the order somebody reads them in. */
    public function replies(): HasMany
    {
        return $this->hasMany(RfiReply::class)->orderByDesc('replied_at')->orderByDesc('id');
    }

    /** The reply the work is built to. */
    public function validReply(): BelongsTo
    {
        return $this->belongsTo(RfiReply::class, 'valid_reply_id');
    }

    /** Attachments, on the same R2-backed system every other module uses. */
    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    /**
     * Only the files that finished uploading.
     *
     * A row exists from the moment an upload starts, so listing `files()` on a
     * screen shows half-arrived bytes as though they were attachments. Same
     * shape as Task::availableFiles().
     */
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
        return 'rfi';
    }

    /** So a BR template can render SI-ARQ-014. */
    /** So a BR template can render SI-ARQ-014. */
    public function numberTokens(): array
    {
        return array_filter([
            'prefix' => config('app.country') === 'BR' ? 'SI' : 'RFI',
            'discipline' => static::disciplineCode($this->discipline),
        ]);
    }

    /**
     * The short code a number uses — ARQ, EST, HID.
     *
     * Not derived from the key: a document number is printed, quoted and
     * filed, so it must not change because a label was reworded.
     */
    public static function disciplineCode(?string $discipline): ?string
    {
        return match ($discipline) {
            'architecture' => 'ARQ',
            'structure' => 'EST',
            'plumbing' => 'HID',
            'electrical' => 'ELE',
            'hvac' => 'CLI',
            'fire' => 'INC',
            'foundations' => 'FUN',
            'landscaping' => 'PAI',
            'other' => 'OUT',
            default => $discipline ? mb_strtoupper(mb_substr($discipline, 0, 3)) : null,
        };
    }

    /**
     * What a signer is putting their name to.
     *
     * The words, and nothing the workflow moves on its own. `status` was in
     * here and should not have been: signing happens once an RFI is answered,
     * closing it is the very next step, and including the status meant every
     * signature was reported as broken by the ordinary flow — on a document
     * nobody had touched. A correction to the answer must still break it, and
     * does.
     */
    public function signaturePayload(): array
    {
        return [
            'number' => $this->number,
            'subject' => $this->subject,
            'question' => $this->question,
            'answer' => $this->answer,
        ];
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
        return $this->status === self::CLOSED;
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    /** Whether the answer can still be edited in place. */
    public function answerIsEditable(): bool
    {
        return ! $this->isClosed();
    }

    /**
     * Whether closing this RFI should offer to raise a change order.
     *
     * Offer, never create. Money-touching artifacts are confirmed by a person
     * (docs/RFI-Submittals-modules.md, guardrails).
     */
    public function suggestsChangeOrder(): bool
    {
        return ($this->cost_impact || $this->schedule_impact)
            && $this->change_order_id === null;
    }

    /**
     * Tie this RFI to the change order that came out of it.
     *
     * The answer is copied across as it reads now. That copy is the point: it
     * is what the aditivo was argued from, and a correction to the RFI
     * afterwards must not quietly rewrite the justification of a change order
     * somebody has already approved.
     */
    public function linkChangeOrder(ChangeOrder $changeOrder): void
    {
        $this->fill([
            'change_order_id' => $changeOrder->id,
            'change_order_answer' => $this->answer,
            'change_order_linked_at' => now(),
        ])->save();

        $this->logActivity(ActivityLogEntry::UPDATED, [
            'change_order' => $changeOrder->co_number,
        ]);
    }

    /**
     * True when the answer has been corrected since the aditivo was raised.
     *
     * The screen says so rather than showing two different justifications and
     * leaving the reader to notice.
     */
    public function answerChangedSinceChangeOrder(): bool
    {
        return $this->change_order_id !== null
            && $this->change_order_answer !== null
            && $this->change_order_answer !== $this->answer;
    }

    /*
    |---------------------------------------------------------------------------
    | Which answer is the one that counts
    |---------------------------------------------------------------------------
    */

    /**
     * Whether this RFI's answer has ever been corrected.
     *
     * When it has, the answer on screen is one of several the record has
     * carried, and the page says which is the valid one rather than leaving
     * the reader to work it out from the history.
     */
    public function hasBeenCorrected(): bool
    {
        return $this->activity()
            ->where('action', ActivityLogEntry::REVISED)
            ->exists();
    }

    /** How many times, for the note beside the answer. */
    public function correctionCount(): int
    {
        return $this->activity()
            ->where('action', ActivityLogEntry::REVISED)
            ->count();
    }

    /*
    |---------------------------------------------------------------------------
    | Transitions
    |---------------------------------------------------------------------------
    */

    /**
     * Add a reply, and hand the SI back to whoever asked.
     *
     * The first reply becomes the valid one by default — with a single answer
     * there is nothing to choose between, and making somebody pick would be
     * ceremony. A second reply does **not** take over: which answer counts is
     * then a decision, and `markReplyValid()` is where it is made.
     */
    public function addReply(string $body, User $actor): RfiReply
    {
        $reply = $this->replies()->create([
            'body' => $body,
            'replied_by_id' => $actor->id,
            'replied_at' => now(),
        ]);

        if ($this->valid_reply_id === null) {
            $this->markReplyValid($reply, $actor, log: false);
        }

        $this->fill([
            'status' => self::ANSWERED,
            'ball_in_court_id' => $this->created_by_id,
        ])->save();

        $this->logActivity(ActivityLogEntry::ANSWERED, ['reply' => $reply->id]);

        return $reply->fresh();
    }

    /** Kept for callers that answer in one step; the reply is still recorded. */
    public function recordAnswer(string $answer, User $actor): void
    {
        $this->addReply($answer, $actor);
    }

    /**
     * Say which reply the work is built to.
     *
     * `answer`, `answered_by_id` and `answered_at` mirror it: they are what
     * the PDF prints, what the list searches and what a change order is argued
     * from, so they follow the choice rather than being a second opinion.
     */
    public function markReplyValid(RfiReply $reply, User $actor, bool $log = true): void
    {
        if ($reply->rfi_id !== $this->id) {
            throw ValidationException::withMessages([
                'reply' => __('collaboration.message.reply_not_on_this_rfi'),
            ]);
        }

        $this->reviseUnlocked = true;

        try {
            $this->fill([
                'valid_reply_id' => $reply->id,
                'answer' => $reply->body,
                'answered_by_id' => $reply->replied_by_id,
                'answered_at' => $reply->replied_at,
            ])->save();
        } finally {
            $this->reviseUnlocked = false;
        }

        if ($log) {
            $this->logActivity(ActivityLogEntry::REVISED, [
                'reply' => $reply->id,
                'chosen' => true,
                'author' => $reply->getAuthorName(),
            ]);
        }
    }

    /**
     * Correct the wording of a reply.
     *
     * Kept on the reply rather than replacing it: the point of a reply being a
     * record is that it stays one. The edit is stamped with who and when, and
     * the mirror follows if this was the valid reply.
     */
    public function editReply(RfiReply $reply, string $body, User $actor, ?string $reason = null): void
    {
        if ($reply->rfi_id !== $this->id) {
            throw ValidationException::withMessages([
                'reply' => __('collaboration.message.reply_not_on_this_rfi'),
            ]);
        }

        $before = $reply->body;

        $reply->update([
            'body' => $body,
            'edited_by_id' => $actor->id,
            'edited_at' => now(),
        ]);

        if ($this->valid_reply_id === $reply->id) {
            $this->markReplyValid($reply->fresh(), $actor, log: false);
        }

        $this->logActivity(ActivityLogEntry::REVISED, array_filter([
            'reply' => $reply->id,
            'previous_answer' => $before,
            'reason' => $reason,
        ], fn ($value) => $value !== null));
    }

    /** Close it. Nobody's move after this. */
    public function close(): void
    {
        $this->fill(['status' => self::CLOSED, 'ball_in_court_id' => null])->save();

        $this->logActivity(ActivityLogEntry::CLOSED);
    }

    public function reopen(): void
    {
        $this->fill(['status' => self::ANSWERED])->save();

        $this->logActivity(ActivityLogEntry::REOPENED);
    }

    /**
     * Withdraw the RFI without destroying it.
     *
     * The `void` status has existed since the module was built and nothing
     * could reach it. This is what reaches it: an RFI that has been sent to an
     * outside projetista is a record — it keeps its number, its question, its
     * replies and its distribution history, and simply stops being live.
     *
     * The ball is dropped at the same time: a voided RFI is nobody's turn.
     */
    public function void(string $reason): void
    {
        $this->fill([
            'status' => self::VOID,
            'ball_in_court_id' => null,
        ])->save();

        $this->logActivity(ActivityLogEntry::VOIDED, ['reason' => $reason]);
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }

    /**
     * Whether it can be voided: anything still alive.
     *
     * A closed RFI can be voided too — closing says "answered", voiding says
     * "this should never have been asked", and the second is sometimes the
     * truth after the fact.
     */
    public function canBeVoided(): bool
    {
        return ! $this->isVoid();
    }

    /**
     * Whether it can be destroyed outright.
     *
     * Only a record nobody outside has seen: a **draft**, which was never sent
     * anywhere, or one already **voided**, where the decision to let it go has
     * been taken and recorded once already. Everything else is somebody else's
     * evidence, and the honest way to retire it is to void it.
     *
     * The same rule requisitions use, in the same words: delete is for records
     * that never became real.
     */
    public function canBeDeleted(): bool
    {
        return $this->isDraft() || $this->isVoid();
    }

    /**
     * Correct a closed RFI, on the record.
     *
     * The only route past the freeze, and it exists so that the correction is
     * visible rather than silent. Requires `rfis.revise`, which is declared
     * `sensitive`; the component guards it, and this logs what moved.
     */
    public function revise(string $answer, string $reason, User $actor): void
    {
        // Corrects the valid reply rather than the mirror column. Writing
        // `answer` straight would leave the replies list showing one wording
        // and the PDF printing another — the record disagreeing with itself.
        $reply = $this->validReply;

        if (! $reply) {
            $reply = $this->addReply($answer, $actor);
            $this->logActivity(ActivityLogEntry::REVISED, ['reason' => $reason, 'reply' => $reply->id]);

            return;
        }

        $this->editReply($reply, $answer, $actor, $reason);
    }

    /*
    |---------------------------------------------------------------------------
    | Labels — never print a stored value
    |---------------------------------------------------------------------------
    | *Solicitação* is feminine, so the participles agree: "Encerrada", not
    | "Encerrado". The shared status words in this codebase are masculine,
    | which is why these have keys of their own — the same reason
    | Expense::statusLabel() does.
    */

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::DRAFT => __('collaboration.rfi.status.draft'),
            self::OPEN => __('collaboration.rfi.status.open'),
            self::ANSWERED => __('collaboration.rfi.status.answered'),
            self::CLOSED => __('collaboration.rfi.status.closed'),
            self::VOID => __('collaboration.rfi.status.void'),
            default => (string) $status,
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public static function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            'low' => __('collaboration.rfi.priority.low'),
            'normal' => __('collaboration.rfi.priority.normal'),
            'high' => __('collaboration.rfi.priority.high'),
            'urgent' => __('collaboration.rfi.priority.urgent'),
            default => (string) $priority,
        };
    }

    public function getPriorityLabel(): string
    {
        return static::priorityLabel($this->priority);
    }

    /** @return array<string, string> value => label */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)
            ->mapWithKeys(fn (string $s) => [$s => static::statusLabel($s)])
            ->all();
    }

    /** @return array<string, string> value => label */
    public static function priorityOptions(): array
    {
        return collect(self::PRIORITIES)
            ->mapWithKeys(fn (string $p) => [$p => static::priorityLabel($p)])
            ->all();
    }

    /**
     * The disciplines an RFI can be filed under.
     *
     * **Stable keys, translated labels.** These used to be the words
     * themselves, chosen by the install's country and written into the column
     * — so the value stored depended on where the app was deployed, the same
     * discipline read differently on two installs, and no amount of `__()`
     * could translate a row that already said "Architectural". A key is stored
     * once and reads correctly in either language for ever.
     *
     * @return array<int, string>
     */
    public const DISCIPLINES = [
        'architecture', 'structure', 'plumbing', 'electrical',
        'hvac', 'fire', 'foundations', 'landscaping', 'other',
    ];

    /** Never print the stored key. */
    public static function disciplineLabel(?string $discipline): string
    {
        return match ($discipline) {
            'architecture' => __('collaboration.discipline.architecture'),
            'structure' => __('collaboration.discipline.structure'),
            'plumbing' => __('collaboration.discipline.plumbing'),
            'electrical' => __('collaboration.discipline.electrical'),
            'hvac' => __('collaboration.discipline.hvac'),
            'fire' => __('collaboration.discipline.fire_protection'),
            'foundations' => __('collaboration.discipline.foundations'),
            'landscaping' => __('collaboration.discipline.landscaping'),
            'other' => __('collaboration.discipline.other'),
            // A discipline typed in before this list existed still reads as
            // itself rather than disappearing off the screen.
            default => (string) $discipline,
        };
    }

    public function getDisciplineLabel(): string
    {
        return static::disciplineLabel($this->discipline);
    }

    /** @return array<string, string> value => label */
    public static function disciplineOptions(): array
    {
        return collect(self::DISCIPLINES)
            ->mapWithKeys(fn (string $d) => [$d => static::disciplineLabel($d)])
            ->all();
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    */

    /**
     * Which RFIs this person may see at all.
     *
     * A guard answers "may you open this one?"; only a filter answers "which
     * ones may you see?" — and a count across projects somebody cannot open is
     * a leak by aggregate. Every list and every total goes through this.
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

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    /** Live work only — what the index shows before anybody filters. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', self::LIVE_STATUSES);
    }
}
