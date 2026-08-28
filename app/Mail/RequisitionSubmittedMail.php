<?php

namespace App\Mail;

use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "This needs your decision" — sent when a requisition is submitted for
 * approval.
 *
 * The subject is imperative and the link lands on the requisition itself, not
 * on the list: the whole point is that somebody acts on it today.
 */
class RequisitionSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequisition $requisition,
        public User $recipient,
        public User $actor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Approve :number — :title', [
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

        return new Content(view: 'emails.requisition-submitted', with: [
            'requisition' => $requisition,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'link' => $link.'?requisition='.$requisition->id,
        ]);
    }
}
