<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One task e-mail that was sent, or tried. Written before the mail leaves, so
 * a scheduled command that runs twice in a day mails nobody twice and "why
 * did I not get an e-mail?" is a query rather than a guess.
 */
class TaskNotification extends Model
{
    public const TYPE_CREATED = 'created';
    public const TYPE_CLOSED = 'closed';
    public const TYPE_OVERDUE = 'overdue';
    public const TYPE_WEEKLY_DIGEST = 'weekly_digest';

    protected $fillable = [
        'task_id',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
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
