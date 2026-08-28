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
 * "This is still waiting" — the nudge for an approved requisition that has
 * been handed to somebody and still has no quotation round.
 *
 * Copied to whoever approved it. Not to shame anybody: the approver is the
 * person who can reassign it if the buyer has too much on, and a nudge that
 * only reaches somebody already overloaded changes nothing.
 */
class RequisitionStalledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequisition $requisition,
        public User $recipient,
        public int $daysWaiting,
        public ?User $copyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Still waiting: :number — :title', [
                'number' => $this->requisition->requisition_number,
                'title' => $this->requisition->title,
            ]),
            // Only when the approver is somebody else, and only when they are
            // still reachable.
            cc: $this->copyTo && $this->copyTo->id !== $this->recipient->id && $this->copyTo->email
                ? [$this->copyTo->email]
                : [],
        );
    }

    public function content(): Content
    {
        $requisition = $this->requisition->loadMissing(['project', 'jobSite', 'items']);

        $link = $requisition->job_site_id
            ? route('jobsites.quotations', $requisition->job_site_id)
            : route('projects.quotations', $requisition->project_id);

        return new Content(view: 'emails.requisition-stalled', with: [
            'requisition' => $requisition,
            'recipient' => $this->recipient,
            'daysWaiting' => $this->daysWaiting,
            'link' => $link.'?requisition='.$requisition->id,
        ]);
    }
}
