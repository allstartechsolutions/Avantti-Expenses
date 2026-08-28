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
 * "This is still waiting for you" — the chase for a submitted requisition
 * nobody has decided on.
 *
 * The site is blocked until somebody answers, so the subject says how long it
 * has been rather than merely that it exists.
 */
class RequisitionAwaitingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequisition $requisition,
        public User $recipient,
        public int $daysWaiting,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans_choice(
            'Waiting :count day for your decision: :number|Waiting :count days for your decision: :number',
            $this->daysWaiting,
            ['count' => $this->daysWaiting, 'number' => $this->requisition->requisition_number],
        ));
    }

    public function content(): Content
    {
        $requisition = $this->requisition->loadMissing(['project', 'jobSite', 'items']);

        $link = $requisition->job_site_id
            ? route('jobsites.requisitions', $requisition->job_site_id)
            : route('projects.requisitions', $requisition->project_id);

        return new Content(view: 'emails.requisition-awaiting', with: [
            'requisition' => $requisition,
            'recipient' => $this->recipient,
            'daysWaiting' => $this->daysWaiting,
            'link' => $link.'?requisition='.$requisition->id,
        ]);
    }
}
