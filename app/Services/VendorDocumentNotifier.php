<?php

namespace App\Services;

use App\Mail\VendorDocumentExpiryMail;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\SubcontractorDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Warning people that a vendor's compliance document is about to lapse.
 *
 * Four fixed stages — 30, 15 and 7 days before the expiration date, and the
 * day after it passes — each stamped on the document once it has gone out,
 * so a stage can never repeat. Only **active** documents whose type requires
 * a date take part: renewing or archiving a document ends its sequence,
 * because the row that carried the stamps is no longer active.
 *
 * One e-mail per recipient per morning, listing everything that reached a
 * stage that day grouped by vendor — a company with forty subcontractors
 * does not want forty separate mails. A document that reaches several stages
 * at once (uploaded with ten days left, say) is listed once, under the most
 * urgent, and every stage it passed is stamped.
 *
 * Who receives it is a setting: the people picked on the Notification
 * Settings screen, or, when nobody is picked, everyone who may upload and
 * renew vendor documents. The same three checks the other notifiers make
 * apply: the install has the trigger on, the person has not opted out, and
 * today's mail has not already gone to them.
 */
class VendorDocumentNotifier
{
    /** Days before the date → the column that records the stage went out. */
    public const STAGES = [
        30 => 'notified_30_at',
        15 => 'notified_15_at',
        7 => 'notified_7_at',
    ];

    public const EXPIRED_STAGE = 'notified_expired_at';

    /** Deliveries that raised an exception this run — as opposed to people who simply did not want the mail. */
    protected int $failures = 0;

    public function __construct(protected BuyerDirectory $directory) {}

    /**
     * @return array{documents:int, recipients:int, sent:int}
     */
    public function sendExpiryReminders(): array
    {
        $result = ['documents' => 0, 'recipients' => 0, 'sent' => 0];
        $this->failures = 0;

        if (! NotificationSetting::enabled(NotificationSetting::VENDOR_DOCUMENT_EXPIRY)) {
            return $result;
        }

        $today = Carbon::today();
        $furthest = max(array_keys(self::STAGES));

        // Everything that could be at a stage today: watched (the scope), and
        // either inside the widest window with a stage still unstamped, or
        // past its date with the expired stage unstamped. A missed morning is
        // caught the next one rather than lost — the date condition is "on or
        // before", never "exactly".
        $candidates = SubcontractorDocument::query()
            ->active()
            ->requiringExpiry()
            ->where(function (Builder $query) use ($today, $furthest) {
                $query->where(function (Builder $q) use ($today, $furthest) {
                    $q->where('expiration_date', '>=', $today->toDateString())
                        ->where('expiration_date', '<', $today->copy()->addDays($furthest + 1)->toDateString())
                        ->where(function (Builder $stamps) {
                            foreach (self::STAGES as $column) {
                                $stamps->orWhereNull($column);
                            }
                        });
                })->orWhere(function (Builder $q) use ($today) {
                    $q->where('expiration_date', '<', $today->toDateString())
                        ->whereNull(self::EXPIRED_STAGE);
                });
            })
            ->with(['documentType', 'vendor'])
            ->orderBy('expiration_date')
            ->get();

        $expiring = collect();
        $expired = collect();
        $stampsToWrite = [];   // document id => [column, ...]

        foreach ($candidates as $document) {
            $days = $document->days_until_expiry;

            if ($days < 0) {
                $expired->push(['document' => $document, 'days' => $days]);
                $stampsToWrite[$document->id] = [self::EXPIRED_STAGE];

                continue;
            }

            // Every stage whose threshold this document is already inside of
            // and that has not gone out yet; the tightest one names the row.
            $due = collect(self::STAGES)
                ->filter(fn (string $column, int $threshold) => $days <= $threshold && $document->{$column} === null);

            if ($due->isEmpty()) {
                continue;
            }

            $expiring->push(['document' => $document, 'days' => $days, 'stage' => $due->keys()->min()]);
            $stampsToWrite[$document->id] = $due->values()->all();
        }

        if ($expiring->isEmpty() && $expired->isEmpty()) {
            return $result;
        }

        $result['documents'] = $expiring->count() + $expired->count();

        $recipients = $this->recipients();
        $result['recipients'] = $recipients->count();

        foreach ($recipients as $user) {
            if ($this->send($user, new VendorDocumentExpiryMail($user, $expiring, $expired), 'digest:'.$today->toDateString())) {
                $result['sent']++;
            }
        }

        // Stamped when the stage went out, and also when there was nobody to
        // send it to — a document nobody is set up to hear about must not
        // retry every morning forever. Not stamped when every delivery
        // *failed*: an SMTP outage at 07:15 would otherwise swallow the stage
        // for good, and tomorrow's run should try again.
        $everyDeliveryFailed = $result['sent'] === 0 && $this->failures > 0;

        if (! $everyDeliveryFailed) {
            $now = now();

            foreach ($stampsToWrite as $id => $columns) {
                SubcontractorDocument::whereKey($id)->update(array_fill_keys($columns, $now));
            }
        }

        return $result;
    }

    /**
     * Who is told: the people picked in System Settings, or everyone who may
     * upload and renew vendor documents when nobody is picked.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        $ids = NotificationSetting::vendorDocumentRecipientIds();

        if ($ids !== []) {
            return $this->directory->activeStaff()
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->get();
        }

        return $this->directory->holdersOf('vendors.renew_documents', null);
    }

    /** One mail to one person, if they should get it and have not already had today's. */
    protected function send(User $user, VendorDocumentExpiryMail $mail, string $window): bool
    {
        $key = NotificationSetting::VENDOR_DOCUMENT_EXPIRY;

        if (! $user->email || ! $user->isActive() || ! $user->wantsNotification($key)) {
            return false;
        }

        $already = NotificationLogEntry::query()
            ->where('user_id', $user->id)
            ->where('type', $key)
            ->whereJsonContains('meta->window', $window)
            ->whereNotNull('sent_at')
            ->exists();

        if ($already) {
            return false;
        }

        $record = NotificationLogEntry::create([
            'user_id' => $user->id,
            'type' => $key,
            'email' => $user->email,
            'meta' => ['window' => $window],
        ]);

        try {
            Mail::to($user->email)->send($mail);

            $record->update(['sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            $this->failures++;

            $record->update(['error' => substr($e->getMessage(), 0, 500)]);

            Log::warning('Vendor document reminder could not be sent', [
                'user' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
