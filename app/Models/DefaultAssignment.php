<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Who gets a piece of work here, when nobody says otherwise.
 *
 * One row per (context, role). The context is the install (`global`), a
 * project, or a job site; the role is a constant below. `resolve()` walks
 * **job site → project → global** and returns the first level that names
 * somebody who can still be given work.
 *
 * Read on every requisition approval, so the table is cached whole for a few
 * minutes the way NotificationSetting does it — it is a handful of rows and
 * the alternative is three queries per approval. Saving clears the cache.
 *
 * Adding a role for the next module is a constant and a label, nothing else.
 */
class DefaultAssignment extends Model
{
    /** Who runs the quotation round for an approved requisition. */
    public const QUOTATION_BUYER = 'quotation_buyer';

    /**
     * Who is told when a requisition is submitted and needs a decision.
     *
     * **This is not a permission.** Who *may* approve is `requisitions.approve`
     * and nothing else; this row only decides who gets the e-mail, so that a
     * submitted requisition lands on one named person's desk instead of
     * copying everybody who happens to hold the grant. Set nobody here and it
     * still works — it just goes to all of them.
     */
    public const REQUISITION_APPROVER = 'requisition_approver';

    /** Every role this table answers for, in the order a settings screen lists them. */
    public const ROLE_KEYS = [self::REQUISITION_APPROVER, self::QUOTATION_BUYER];

    public const CONTEXT_GLOBAL = 'global';
    public const CONTEXT_PROJECT = 'project';
    public const CONTEXT_JOB_SITE = 'job_site';

    private const CACHE_KEY = 'default_assignments';

    protected $fillable = ['context_type', 'context_id', 'role_key', 'user_id', 'set_by'];

    protected $casts = [
        'context_id' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /**
     * The whole table, keyed `context_type:context_id:role_key`.
     *
     * @return \Illuminate\Support\Collection<string, self>
     */
    public static function cached(): \Illuminate\Support\Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            300,
            fn () => static::query()->get()->keyBy(
                fn (self $row) => self::cacheKeyFor($row->context_type, $row->context_id, $row->role_key)
            )
        );
    }

    private static function cacheKeyFor(string $contextType, ?int $contextId, string $roleKey): string
    {
        return $contextType.':'.((int) $contextId).':'.$roleKey;
    }

    /**
     * The person this role falls to here.
     *
     * Walks job site → project → global and stops at the first level that
     * names somebody. A level naming a **deactivated** user is skipped rather
     * than returned: disabling somebody must not silently route every new
     * requisition into a dead inbox. Returning null is a legal answer — it
     * means unassigned, which is a state the queue shows rather than hides.
     */
    public static function resolve(string $roleKey, ?JobSite $jobSite = null, ?Project $project = null): ?User
    {
        // A job site knows its project, so a caller who passes only the site
        // still gets the middle tier walked.
        if ($jobSite && ! $project) {
            $project = $jobSite->project;
        }

        $candidates = [];

        if ($jobSite) {
            $candidates[] = [self::CONTEXT_JOB_SITE, $jobSite->id];
        }

        if ($project) {
            $candidates[] = [self::CONTEXT_PROJECT, $project->id];
        }

        $candidates[] = [self::CONTEXT_GLOBAL, 0];

        $rows = self::cached();

        foreach ($candidates as [$contextType, $contextId]) {
            $row = $rows->get(self::cacheKeyFor($contextType, $contextId, $roleKey));

            if (! $row || ! $row->user_id) {
                continue;
            }

            $user = User::find($row->user_id);

            if ($user && $user->status === UserStatus::ACTIVE) {
                return $user;
            }
        }

        return null;
    }

    /**
     * The row set at exactly this level — no walking.
     *
     * What a settings panel shows in its own select, so a project that
     * inherits from the install displays an empty select and the inherited
     * name beside it, rather than pretending the project set it.
     */
    public static function at(string $roleKey, string $contextType, ?int $contextId = null): ?self
    {
        return self::cached()->get(self::cacheKeyFor($contextType, $contextId, $roleKey));
    }

    /** The user id set at exactly this level, or null. */
    public static function userIdAt(string $roleKey, string $contextType, ?int $contextId = null): ?int
    {
        return self::at($roleKey, $contextType, $contextId)?->user_id;
    }

    /**
     * Set — or clear, with a null user — the default at one level.
     *
     * Clearing writes a row with a null `user_id` rather than deleting: "this
     * level deliberately defers upward" and "nobody ever looked at this level"
     * resolve identically, but only one of them carries who decided it and when.
     */
    public static function set(
        string $roleKey,
        string $contextType,
        ?int $contextId,
        ?int $userId,
        ?int $setBy = null,
    ): self {
        $row = static::updateOrCreate(
            [
                'context_type' => $contextType,
                'context_id' => (int) $contextId,
                'role_key' => $roleKey,
            ],
            [
                'user_id' => $userId,
                'set_by' => $setBy,
            ],
        );

        // updateOrCreate fires `saved` only when something changed; a no-op
        // save would otherwise leave a stale cache behind.
        Cache::forget(self::CACHE_KEY);

        return $row;
    }

    /** The label for a role, for a settings screen. */
    public static function roleLabel(string $roleKey): string
    {
        return match ($roleKey) {
            self::REQUISITION_APPROVER => __('Who approves it'),
            self::QUOTATION_BUYER => __('Who quotes it'),
            default => $roleKey,
        };
    }

    /** What the role means, under the select. */
    public static function roleDescription(string $roleKey): string
    {
        return match ($roleKey) {
            self::REQUISITION_APPROVER => __('The person told when a requisition is submitted and needs a decision. Anybody who may approve still can — this only decides who is asked first.'),
            self::QUOTATION_BUYER => __('The person a requisition is handed to when it is approved, unless the approver picks somebody else.'),
            default => '',
        };
    }

    /**
     * The ability somebody must hold before this role may name them.
     *
     * The picker offers nobody who would hit a 403, and the endpoint behind it
     * re-checks against the same list. Note the direction of the dependency:
     * the ability decides who is *eligible* to be named here — being named
     * here never grants anything.
     */
    public static function abilityFor(string $roleKey): string
    {
        return match ($roleKey) {
            self::REQUISITION_APPROVER => 'requisitions.approve',
            self::QUOTATION_BUYER => 'quotations.create',
            default => 'quotations.create',
        };
    }
}
