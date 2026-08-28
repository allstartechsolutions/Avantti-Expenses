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
 * The answer, back to whoever asked for it.
 *
 * One mailable for both outcomes: the facts are the same and the reason field
 * matters either way — more on a rejection, where until now the text somebody
 * was required to write reached nobody at all.
 */
class RequisitionDecidedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequisition $requisition,
        public User $recipient,
        public User $actor,
    ) {}

    public function approved(): bool
    {
        return $this->requisition->status === 'approved';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->approved()
            ? __('Approved: :number — :title', [
                'number' => $this->requisition->requisition_number,
                'title' => $this->requisition->title,
            ])
            : __('Rejected: :number — :title', [
                'number' => $this->requisition->requisition_number,
                'title' => $this->requisition->title,
            ]));
    }

    public function content(): Content
    {
        $requisition = $this->requisition->loadMissing(['project', 'jobSite', 'items', 'assignedBuyer']);

        $link = $requisition->job_site_id
            ? route('jobsites.requisitions', $requisition->job_site_id)
            : route('projects.requisitions', $requisition->project_id);

        return new Content(view: 'emails.requisition-decided', with: [
            'requisition' => $requisition,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'approved' => $this->approved(),
            'link' => $link.'?requisition='.$requisition->id,
        ]);
    }
}
