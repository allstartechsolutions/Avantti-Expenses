<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Stop chasing this one." Sent when a quotation round is cancelled.
 *
 * The vendors matter here: somebody who has already asked four merchants for
 * prices needs to know to tell them, and the mail says how many were invited.
 */
class QuotationCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public User $recipient,
        public User $actor,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Cancelled: :number — :title', [
            'number' => $this->quotation->quotation_number,
            'title' => $this->quotation->title,
        ]));
    }

    public function content(): Content
    {
        $quotation = $this->quotation->loadMissing(['project', 'jobSite', 'quotationVendors.vendor', 'requisition']);

        $link = $quotation->job_site_id
            ? route('jobsites.quotations', $quotation->job_site_id)
            : route('projects.quotations', $quotation->project_id);

        return new Content(view: 'emails.quotation-cancelled', with: [
            'quotation' => $quotation,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'reason' => $this->reason,
            'link' => $link.'?quotation='.$quotation->id,
        ]);
    }
}
