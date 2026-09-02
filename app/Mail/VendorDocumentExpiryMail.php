<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * "These vendor documents need attention" — one morning's worth, to one person.
 *
 * `$expiring` rows are `['document', 'days', 'stage']`; `$expired` rows are
 * `['document', 'days']` with a negative number. Both are grouped by vendor
 * in the template so a subcontractor with three lapsing certificates reads
 * as one block, not three.
 */
class VendorDocumentExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Collection $expiring,
        public Collection $expired,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->expiring->count() + $this->expired->count();

        return new Envelope(subject: $this->expired->isNotEmpty()
            ? trans_choice(':count vendor document has expired|:count vendor documents need attention', $count, ['count' => $count])
            : trans_choice(':count vendor document is expiring soon|:count vendor documents are expiring soon', $count, ['count' => $count]));
    }

    public function content(): Content
    {
        $byVendor = fn (Collection $rows) => $rows
            ->groupBy(fn (array $row) => $row['document']->subcontractor_id)
            ->map(fn (Collection $rows) => [
                'vendor' => $rows->first()['document']->vendor,
                'rows' => $rows,
            ])
            ->sortBy(fn (array $group) => $group['vendor']?->name ?? '')
            ->values();

        return new Content(view: 'emails.vendor-document-expiry', with: [
            'recipient' => $this->recipient,
            'expiredGroups' => $byVendor($this->expired),
            'expiringGroups' => $byVendor($this->expiring),
        ]);
    }
}
