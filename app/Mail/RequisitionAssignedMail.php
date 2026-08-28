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
 * "Go and quote this" — sent to the buyer when an approved requisition is
 * handed to them.
 *
 * The subject is imperative on purpose: this is a work order, not a notice.
 */
class RequisitionAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequisition $requisition,
        public User $recipient,
        public User $actor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Quote :number — :title', [
            'number' => $this->requisition->requisition_number,
            'title' => $this->requisition->title,
        ]));
    }

    public function content(): Content
    {
        $requisition = $this->requisition->loadMissing(['project', 'jobSite', 'items']);

        // Straight to the round, not to the list: a person who has to navigate
        // is a person who does it tomorrow.
        $link = $requisition->job_site_id
            ? route('jobsites.quotations', $requisition->job_site_id)
            : route('projects.quotations', $requisition->project_id);

        return new Content(view: 'emails.requisition-assigned', with: [
            'requisition' => $requisition,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'link' => $link.'?requisition='.$requisition->id,
        ]);
    }
}
