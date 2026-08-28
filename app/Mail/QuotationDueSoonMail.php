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
 * "Responses are due" — sent to the owner and collaborators of an open round,
 * either shortly before the response date or once it has passed.
 *
 * One mailable for both, because the facts are the same and only the urgency
 * differs; `$overdue` decides the wording.
 */
class QuotationDueSoonMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public User $recipient,
        public bool $overdue = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->overdue
            ? __('Past due: :number — :title', [
                'number' => $this->quotation->quotation_number,
                'title' => $this->quotation->title,
            ])
            : __('Chase :number — responses due :date', [
                'number' => $this->quotation->quotation_number,
                'date' => $this->quotation->responses_due_at?->format(
                    config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y'
                ),
            ]));
    }

    public function content(): Content
    {
        $quotation = $this->quotation->loadMissing(['project', 'jobSite', 'quotationVendors.vendor']);

        $link = $quotation->job_site_id
            ? route('jobsites.quotations', $quotation->job_site_id)
            : route('projects.quotations', $quotation->project_id);

        return new Content(view: 'emails.quotation-due', with: [
            'quotation' => $quotation,
            'recipient' => $this->recipient,
            'overdue' => $this->overdue,
            'link' => $link.'?quotation='.$quotation->id,
        ]);
    }
}
