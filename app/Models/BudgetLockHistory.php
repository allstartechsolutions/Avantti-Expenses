<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line per freeze or reopen of a budget.
 *
 * `budgets.locked_at` says what is true now; this says what happened, which is
 * the half that matters when somebody asks why a baseline moved.
 */
class BudgetLockHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'budget_id',
        'action',
        'user_id',
        'reason',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLock(): bool
    {
        return $this->action === 'locked';
    }

    public function label(): string
    {
        return $this->isLock() ? __('Locked') : __('Unlocked');
    }
}
