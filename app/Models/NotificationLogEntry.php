<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One e-mail that was sent, or tried, for anything that is not a task.
 *
 * Written before the mail leaves. A row with `sent_at` is proof it went; a row
 * with `error` says who was not reached and why, and does not block the next
 * attempt.
 *
 * Named for the entry rather than the table because `NotificationLog` reads
 * like the log itself, and this is one line of it.
 */
class NotificationLogEntry extends Model
{
    protected $table = 'notification_log';

    /** A requisition was submitted and somebody has to decide on it. */
    public const REQUISITION_SUBMITTED = 'requisition_submitted';

    /** Submitted, and still nobody has decided on it. */
    public const REQUISITION_AWAITING = 'requisition_awaiting_approval';

    /** Approved or rejected — the answer, back to whoever asked. */
    public const REQUISITION_DECIDED = 'requisition_decided';

    /** The buyer was handed an approved requisition to quote. */
    public const REQUISITION_ASSIGNED = 'requisition_assigned';

    /** Approved, assigned, and still no round after N days. */
    public const REQUISITION_STALLED = 'requisition_stalled';

    /** A round was handed to somebody, or somebody was added to one. */
    public const QUOTATION_ASSIGNED = 'quotation_assigned';

    /** Responses are due within the lead time and the round is still open. */
    public const QUOTATION_DUE_SOON = 'quotation_due_soon';

    /** The response date passed and the round is still open. */
    public const QUOTATION_OVERDUE = 'quotation_overdue';

    /** Every procurement trigger, in the order the settings screen lists them. */
    public const PROCUREMENT_KEYS = [
        self::REQUISITION_SUBMITTED,
        self::REQUISITION_AWAITING,
        self::REQUISITION_DECIDED,
        self::REQUISITION_ASSIGNED,
        self::REQUISITION_STALLED,
        self::QUOTATION_ASSIGNED,
        self::QUOTATION_DUE_SOON,
        self::QUOTATION_OVERDUE,
    ];

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'user_id',
        'type',
        'email',
        'sent_at',
        'error',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta' => 'array',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wasSent(): bool
    {
        return $this->sent_at !== null;
    }
}
