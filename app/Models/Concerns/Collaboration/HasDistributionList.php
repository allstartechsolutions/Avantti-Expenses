<?php

namespace App\Models\Concerns\Collaboration;

use App\Models\Collaboration\DistributionEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Who gets a copy of this document.
 *
 * Separate from who may *open* it, which is a permission question answered by
 * the membership. A distribution list is a transmittal: it may name people
 * with no account at all.
 */
trait HasDistributionList
{
    public function distribution(): MorphMany
    {
        return $this->morphMany(DistributionEntry::class, 'distributable');
    }

    /**
     * Replace the list in one go.
     *
     * Each entry is ['user_id' => int] or ['external_name' => …,
     * 'external_email' => …], optionally with 'role'. Rows naming nobody
     * reachable are dropped rather than stored: a distribution line with
     * neither a user nor an address is a line that will silently fail to send.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function syncDistribution(array $entries): void
    {
        $rows = collect($entries)
            ->map(fn (array $entry) => [
                'user_id' => $entry['user_id'] ?? null,
                'external_name' => $entry['external_name'] ?? null,
                'external_email' => $entry['external_email'] ?? null,
                'role' => $entry['role'] ?? null,
            ])
            ->filter(fn (array $row) => $row['user_id'] || $row['external_email'])
            // One person, one copy — the same address added twice is one line.
            ->unique(fn (array $row) => $row['user_id'] ?? strtolower((string) $row['external_email']))
            ->values();

        $this->distribution()->delete();

        foreach ($rows as $row) {
            $this->distribution()->create($row);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Whether it was ever actually sent
    |---------------------------------------------------------------------------
    | A distribution list is a list of intent. Nothing leaves until somebody
    | presses Send, and a screen that shows the names without saying whether
    | they ever received anything reads as though adding a name were enough —
    | which is exactly how a person ends up believing a drawing went out when
    | it did not.
    |
    | Needs `LogsCollaborationActivity` alongside, which both documents use.
    */

    /** The last time this document was posted out, if it ever was. */
    public function lastDistribution(): ?\App\Models\Collaboration\ActivityLogEntry
    {
        return $this->activity()
            ->where('action', \App\Models\Collaboration\ActivityLogEntry::DISTRIBUTED)
            ->first();
    }

    public function hasBeenDistributed(): bool
    {
        return $this->lastDistribution() !== null;
    }

    /**
     * People on the list now who were not on the last send.
     *
     * Somebody added after the document went out has received nothing, and
     * the screen has to say so — the list changing does not re-send anything.
     *
     * @return \Illuminate\Support\Collection<string, string> address => name
     */
    public function recipientsAwaitingFirstSend(): Collection
    {
        $current = $this->distributionRecipients();
        $last = $this->lastDistribution();

        if (! $last) {
            return $current;
        }

        $already = array_map('strtolower', $last->context['recipients'] ?? []);

        return $current->reject(fn (string $name, string $address) => in_array($address, $already, true));
    }

    /**
     * Every address a copy should go to, deduplicated.
     *
     * A person on the list twice — once as a user, once by address — is one
     * recipient. Keyed by lowercased address so that differs only in case does
     * not send twice.
     *
     * @return Collection<string, string> address => name
     */
    public function distributionRecipients(): Collection
    {
        return $this->distribution()
            ->with('user')
            ->get()
            ->mapWithKeys(fn (DistributionEntry $entry) => $entry->getEmail()
                ? [strtolower($entry->getEmail()) => $entry->getName()]
                : [])
            ->filter();
    }
}
