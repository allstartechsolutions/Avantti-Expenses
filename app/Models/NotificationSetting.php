<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Which task e-mails this install sends, and when.
 *
 * Read on nearly every task action, so the answers are cached for a few
 * minutes the way ModuleAccess does it. Saving clears the cache.
 */
class NotificationSetting extends Model
{
    public const TASK_CREATED = 'task_created';
    public const TASK_CLOSED = 'task_closed';
    public const TASK_OVERDUE = 'task_overdue';
    public const TASK_WEEKLY_DIGEST = 'task_weekly_digest';

    // --- Procurement: who runs the cotação -----------------------------------
    // Same table, same switches, so an install turns these off exactly the way
    // it turns the task ones off. They are listed separately on the settings
    // screen because they are a different job, not a different mechanism.
    public const REQUISITION_SUBMITTED = 'requisition_submitted';
    public const REQUISITION_AWAITING = 'requisition_awaiting_approval';
    public const REQUISITION_DECIDED = 'requisition_decided';
    public const REQUISITION_ASSIGNED = 'requisition_assigned';
    public const REQUISITION_CANCELLED = 'requisition_cancelled';
    public const REQUISITION_STALLED = 'requisition_stalled';
    public const QUOTATION_ASSIGNED = 'quotation_assigned';
    public const QUOTATION_DUE_SOON = 'quotation_due_soon';
    public const QUOTATION_OVERDUE = 'quotation_overdue';
    public const QUOTATION_CANCELLED = 'quotation_cancelled';

    // --- Vendors: compliance documents running out ---------------------------
    // One switch for the whole sequence (30, 15, 7 days before, the day
    // after); who receives it lives in `options.recipients`.
    public const VENDOR_DOCUMENT_EXPIRY = 'vendor_document_expiry';

    /** Every task trigger, in the order the settings screen lists them. */
    public const KEYS = [self::TASK_CREATED, self::TASK_CLOSED, self::TASK_OVERDUE, self::TASK_WEEKLY_DIGEST];

    /** Every procurement trigger, in the order the settings screen lists them. */
    public const PROCUREMENT_KEYS = [
        self::REQUISITION_SUBMITTED,
        self::REQUISITION_AWAITING,
        self::REQUISITION_DECIDED,
        self::REQUISITION_ASSIGNED,
        self::REQUISITION_CANCELLED,
        self::REQUISITION_STALLED,
        self::QUOTATION_ASSIGNED,
        self::QUOTATION_DUE_SOON,
        self::QUOTATION_OVERDUE,
        self::QUOTATION_CANCELLED,
    ];

    /** Every vendor trigger, in the order the settings screen lists them. */
    public const VENDOR_KEYS = [self::VENDOR_DOCUMENT_EXPIRY];

    /**
     * How long a submitted requisition may wait before its approver is chased.
     *
     * Shorter than the quoting stall on purpose: approving is a minute's work
     * and the site is blocked until it happens, whereas collecting three
     * quotes genuinely takes days.
     */
    public const DEFAULT_AWAITING_DAYS = 3;

    public const DEFAULT_AWAITING_REMINDERS = 4;

    /** How many days of silence before the stall reminder goes out. */
    public const DEFAULT_STALL_DAYS = 7;

    /** How many times it repeats before it stops shouting. */
    public const DEFAULT_STALL_REMINDERS = 4;

    /** How many days before the response date the warning goes out. */
    public const DEFAULT_DUE_LEAD_DAYS = 3;

    private const CACHE_KEY = 'notification_settings';

    protected $fillable = ['key', 'is_enabled', 'options', 'updated_by'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'options' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return \Illuminate\Support\Collection<string, self> */
    public static function all($columns = ['*'])
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => static::query()->get()->keyBy('key'));
    }

    /**
     * Is this install sending this one?
     *
     * An unknown key is treated as on: a trigger added in code should send
     * until somebody deliberately switches it off.
     */
    public static function enabled(string $key): bool
    {
        return (bool) (static::all()->get($key)?->is_enabled ?? true);
    }

    /** @return array<string, mixed> */
    public static function optionsFor(string $key): array
    {
        return (array) (static::all()->get($key)?->options ?? []);
    }

    /** Which weekday the digest goes out on: 1 = Monday. */
    public static function digestDay(): int
    {
        return (int) (static::optionsFor(self::TASK_WEEKLY_DIGEST)['day'] ?? 1);
    }

    public static function digestHour(): int
    {
        return (int) (static::optionsFor(self::TASK_WEEKLY_DIGEST)['hour'] ?? 7);
    }

    /** Days a submitted requisition may sit before its approver is chased. */
    public static function awaitingDays(): int
    {
        return max(1, (int) (static::optionsFor(self::REQUISITION_AWAITING)['days'] ?? self::DEFAULT_AWAITING_DAYS));
    }

    /** How many times that chase repeats. */
    public static function awaitingMaxReminders(): int
    {
        return max(1, (int) (static::optionsFor(self::REQUISITION_AWAITING)['max_reminders'] ?? self::DEFAULT_AWAITING_REMINDERS));
    }

    /** Days of silence before the stall reminder goes out. The owner's number. */
    public static function stallDays(): int
    {
        return max(1, (int) (static::optionsFor(self::REQUISITION_STALLED)['days'] ?? self::DEFAULT_STALL_DAYS));
    }

    /**
     * How many times it repeats.
     *
     * A requisition nobody is going to quote should stop shouting and start
     * showing up in a review, so the reminder is capped rather than eternal.
     */
    public static function stallMaxReminders(): int
    {
        return max(1, (int) (static::optionsFor(self::REQUISITION_STALLED)['max_reminders'] ?? self::DEFAULT_STALL_REMINDERS));
    }

    /** Days before the response date that the warning goes out. */
    public static function dueLeadDays(): int
    {
        return max(1, (int) (static::optionsFor(self::QUOTATION_DUE_SOON)['lead_days'] ?? self::DEFAULT_DUE_LEAD_DAYS));
    }

    /**
     * The people chosen for the vendor document reminders. Empty means "fall
     * back to everyone who may upload and renew vendor documents".
     *
     * @return array<int, int>
     */
    public static function vendorDocumentRecipientIds(): array
    {
        return array_values(array_map(
            'intval',
            (array) (static::optionsFor(self::VENDOR_DOCUMENT_EXPIRY)['recipients'] ?? []),
        ));
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::TASK_CREATED => __('Task created or assigned to you'),
            self::TASK_CLOSED => __('Task closed'),
            self::TASK_OVERDUE => __('Task went past due'),
            self::TASK_WEEKLY_DIGEST => __('Weekly open-tasks digest'),
            self::REQUISITION_SUBMITTED => __('A requisition is waiting for your decision'),
            self::REQUISITION_AWAITING => __('A requisition has been waiting for a decision'),
            self::REQUISITION_DECIDED => __('Your requisition was approved or rejected'),
            self::REQUISITION_ASSIGNED => __('You were asked to quote a requisition'),
            self::REQUISITION_CANCELLED => __('A requisition you were working on was cancelled'),
            self::REQUISITION_STALLED => __('An approved requisition is still not being quoted'),
            self::QUOTATION_ASSIGNED => __('You were put on a quotation round'),
            self::QUOTATION_DUE_SOON => __('Quotation responses are due soon'),
            self::QUOTATION_OVERDUE => __('Quotation responses are past due'),
            self::QUOTATION_CANCELLED => __('A quotation round you were working on was cancelled'),
            self::VENDOR_DOCUMENT_EXPIRY => __('A vendor document is expiring or has expired'),
            default => $key,
        };
    }

    public static function description(string $key): string
    {
        return match ($key) {
            self::TASK_CREATED => __('Goes to the owner and everyone assigned, never to the person who did it.'),
            self::TASK_CLOSED => __('Goes to the owner, the assignees, whoever raised it, and the chair of the meeting it came from.'),
            self::TASK_OVERDUE => __('Sent once, the morning after the due date passes — not every day.'),
            self::TASK_WEEKLY_DIGEST => __('One e-mail per person listing everything of theirs still open.'),
            self::REQUISITION_SUBMITTED => __('Goes to whoever is named as the approver for that location, or to everybody who may approve there when nobody is named.'),
            self::REQUISITION_AWAITING => __('Goes to whoever can approve it while a submitted requisition still has no decision.'),
            self::REQUISITION_DECIDED => __('Goes to whoever asked for it, carrying the decision and the reason given.'),
            self::REQUISITION_ASSIGNED => __('Goes to the buyer when an approved requisition is handed to them, never to the person who handed it over and never on a draft.'),
            self::REQUISITION_CANCELLED => __('Goes to whoever asked for it and whoever was quoting it — the work stops, so the people doing it are told.'),
            self::REQUISITION_STALLED => __('Goes to the buyer, copying whoever approved it, while an approved requisition still has no quotation round.'),
            self::QUOTATION_ASSIGNED => __('Goes to whoever is put on a round — the owner, or just the person added.'),
            self::QUOTATION_DUE_SOON => __('Goes to the owner and collaborators before the response date, once, and again if the date moves.'),
            self::QUOTATION_OVERDUE => __('Goes to the owner and collaborators once the response date has passed and the round is still open.'),
            self::QUOTATION_CANCELLED => __('Goes to the owner and collaborators when a round is cancelled, so nobody keeps chasing vendors for it.'),
            self::VENDOR_DOCUMENT_EXPIRY => __('One e-mail per morning listing every subcontractor document that reached a stage: 30, 15 and 7 days before its date, and the day after. Renewing or archiving a document stops its reminders.'),
            default => '',
        };
    }
}
