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
 * "This round is yours" — sent to the owner of a quotation round, or to
 * somebody added to one as a collaborator.
 *
 * One mailable for both because the facts are identical; only the sentence
 * saying what the person is on the hook for differs, and `$owns` decides it.
 */
class QuotationAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public User $recipient,
        public User $actor,
        public bool $owns = true,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->owns
            ? __('Run :number — :title', [
                'number' => $this->quotation->quotation_number,
                'title' => $this->quotation->title,
            ])
            : __('Help with :number — :title', [
                'number' => $this->quotation->quotation_number,
                'title' => $this->quotation->title,
            ]));
    }

    public function content(): Content
    {
        $quotation = $this->quotation->loadMissing(['project', 'jobSite', 'items', 'quotationVendors']);

        $link = $quotation->job_site_id
            ? route('jobsites.quotations', $quotation->job_site_id)
            : route('projects.quotations', $quotation->project_id);

        return new Content(view: 'emails.quotation-assigned', with: [
            'quotation' => $quotation,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'owns' => $this->owns,
            'link' => $link.'?quotation='.$quotation->id,
        ]);
    }
}
