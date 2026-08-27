<?php

namespace App\Models\Collaboration;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of a document's history, including the times somebody only looked.
 *
 * Written once, never edited — hence no `updated_at`.
 */
class ActivityLogEntry extends Model
{
    protected $table = 'collaboration_activity_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'context',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public const VIEWED = 'viewed';
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';
    public const REOPENED = 'reopened';
    public const REVISED = 'revised';
    public const SUBMITTED = 'submitted';
    public const RESPONDED = 'responded';
    public const DISTRIBUTED = 'distributed';
    public const SIGNED = 'signed';
    public const EXPORTED = 'exported';

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Never print the stored action.
     *
     * Static twin so a history filter can label a value without a row. The
     * subjects are feminine in pt_BR (*a solicitação*, *a aprovação*), so the
     * participles agree — "Encerrada", not "Encerrado". Both document types
     * this module has are feminine, which is why one set of keys is enough;
     * a masculine subject would need its own, as Expense::statusLabel() does.
     */
    public static function actionLabel(?string $action): string
    {
        return match ($action) {
            self::VIEWED => __('collaboration.activity.viewed'),
            self::CREATED => __('collaboration.activity.created'),
            self::UPDATED => __('collaboration.activity.updated'),
            self::ANSWERED => __('collaboration.activity.answered'),
            self::CLOSED => __('collaboration.activity.closed'),
            self::REOPENED => __('collaboration.activity.reopened'),
            self::REVISED => __('collaboration.activity.revised'),
            self::SUBMITTED => __('collaboration.activity.submitted'),
            self::RESPONDED => __('collaboration.activity.responded'),
            self::DISTRIBUTED => __('collaboration.activity.distributed'),
            self::SIGNED => __('collaboration.activity.signed'),
            self::EXPORTED => __('collaboration.activity.exported'),
            default => (string) $action,
        };
    }

    public function getActionLabel(): string
    {
        return static::actionLabel($this->action);
    }

    /** Who did it — or "Removed user" once the account is gone. */
    public function getActorName(): string
    {
        return $this->user?->name ?? __('collaboration.label.removed_user');
    }
}
