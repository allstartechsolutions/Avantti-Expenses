<?php

namespace App\Models\Concerns\Collaboration;

use App\Models\Collaboration\ActivityLogEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Gives a document a history, including the times somebody only looked at it.
 *
 * Views are logged deliberately. "The projetista was sent this on the 4th and
 * opened it on the 5th" is the sentence the table exists to be able to say,
 * and it cannot be reconstructed later from anything else.
 */
trait LogsCollaborationActivity
{
    public function activity(): MorphMany
    {
        return $this->morphMany(ActivityLogEntry::class, 'subject')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Record something that happened to this document.
     *
     * The actor defaults to whoever is signed in, and the IP is taken from the
     * request when there is one — a queued job or a command has neither, and
     * both columns are nullable for that reason.
     *
     * @param  array<string, mixed>  $context
     */
    public function logActivity(string $action, array $context = []): ActivityLogEntry
    {
        return $this->activity()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'context' => $context ?: null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Record a view, but not the same person's view twice in a row.
     *
     * Somebody reading a long RFI on a phone will reload it, and a history in
     * which every second line is the same person viewing is a history nobody
     * reads. A view by somebody else, or a later visit after any other action,
     * is recorded normally.
     */
    public function logView(): ?ActivityLogEntry
    {
        $last = $this->activity()->first();

        if ($last
            && $last->action === ActivityLogEntry::VIEWED
            && $last->user_id === Auth::id()) {
            return null;
        }

        return $this->logActivity(ActivityLogEntry::VIEWED);
    }
}
