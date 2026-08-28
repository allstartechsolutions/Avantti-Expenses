<?php

namespace App\Mail;

use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "Stop — this one is off." Sent when a requisition is cancelled. */
class RequisitionCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequisition $requisition,
        public User $recipient,
        public User $actor,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Cancelled: :number — :title', [
            'number' => $this->requisition->requisition_number,
            'title' => $this->requisition->title,
        ]));
    }

    public function content(): Content
    {
        $requisition = $this->requisition->loadMissing(['project', 'jobSite', 'items']);

        $link = $requisition->job_site_id
            ? route('jobsites.requisitions', $requisition->job_site_id)
            : route('projects.requisitions', $requisition->project_id);

        return new Content(view: 'emails.requisition-cancelled', with: [
            'requisition' => $requisition,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'reason' => $this->reason,
            // Whether this person was buying it or asked for it changes what
            // they now have to do about it.
            'wasQuoting' => $requisition->assigned_buyer_id === $this->recipient->id,
            'link' => $link.'?requisition='.$requisition->id,
        ]);
    }
}
