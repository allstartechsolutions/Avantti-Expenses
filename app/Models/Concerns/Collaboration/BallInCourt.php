<?php

namespace App\Models\Concerns\Collaboration;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whose move it is, and whether they are late.
 *
 * The one question a project manager asks of a list of RFIs. It is a column
 * rather than a derived value because the answer moves independently of the
 * status: an RFI can be open and sitting with the projetista, or open and back
 * with the contractor for clarification.
 *
 * The party may be a guest — in Brazil that is the normal case, since the
 * person who answers an RFI is usually the external projetista.
 */
trait BallInCourt
{
    public function ballInCourt(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ball_in_court_id');
    }

    /**
     * Hand the document to somebody, with a date they are expected by.
     *
     * Passing null for the user parks it with nobody, which is what a closed
     * document is: there is no move to make.
     */
    public function passTo(?User $user, ?string $dueDate = null): void
    {
        $this->ball_in_court_id = $user?->id;

        if ($dueDate !== null) {
            $this->due_date = $dueDate;
        }

        $this->save();
    }

    /**
     * Late, and still somebody's move.
     *
     * A closed document is never overdue however long its due date has been
     * past — the work is done, and a list that keeps flagging it is a list
     * people stop reading.
     *
     * **Late means past the due date, not on it.** `due_date` is cast to a
     * date, so it is midnight; asking `isPast()` on that would make a document
     * due today "late" from one second after midnight and print "0 days late".
     * The day it is due is the day it is due.
     */
    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->isOpenForBallInCourt()
            && $this->due_date->copy()->endOfDay()->isPast();
    }

    /** Days late, or null when it is not. */
    public function daysOverdue(): ?int
    {
        return $this->isOverdue()
            ? $this->due_date->diffInDays(now())
            : null;
    }

    /** Days remaining, negative when overdue, null with no due date. */
    public function daysRemaining(): ?int
    {
        return $this->due_date
            ? now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false)
            : null;
    }

    /**
     * The statuses that still count as somebody's move.
     *
     * Read from the model's own `LIVE_STATUSES` where it declares one, so the
     * method and the scope below cannot disagree. They did: the scope used to
     * hardcode "not closed or void", which is right for an RFI and wrong for an
     * approval, whose settled statuses are `approved` and `rejected` — so an
     * approved approval past its date was counted in the Overdue card while the
     * row it belonged to rendered as not overdue.
     *
     * @return array<int, string>|null  null when the model declares none
     */
    protected function ballInCourtLiveStatuses(): ?array
    {
        return defined(static::class.'::LIVE_STATUSES') ? static::LIVE_STATUSES : null;
    }

    /** Whether this document is still waiting on somebody. */
    public function isOpenForBallInCourt(): bool
    {
        $live = $this->ballInCourtLiveStatuses();

        return $live === null
            ? ! in_array($this->status, ['closed', 'void'], true)
            : in_array($this->status, $live, true);
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    */

    /**
     * Past due and still live — `isOverdue()` in SQL, and it has to stay that
     * way: a card counting one thing while the rows below it show another is
     * worse than either answer on its own.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        $live = $this->ballInCourtLiveStatuses();

        return $query
            ->whereNotNull('due_date')
            // '<' today, so a document due today is not yet late.
            ->whereDate('due_date', '<', now()->toDateString())
            ->when(
                $live === null,
                fn (Builder $q) => $q->whereNotIn('status', ['closed', 'void']),
                fn (Builder $q) => $q->whereIn('status', $live),
            );
    }

    /** Waiting on this person. */
    public function scopeWaitingOn(Builder $query, User|int|null $user): Builder
    {
        return $query->where('ball_in_court_id', $user instanceof User ? $user->id : $user);
    }

    /** Due within the next :days days and not yet late. */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        $live = $this->ballInCourtLiveStatuses();

        return $query
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->when(
                $live === null,
                fn (Builder $q) => $q->whereNotIn('status', ['closed', 'void']),
                fn (Builder $q) => $q->whereIn('status', $live),
            );
    }
}
