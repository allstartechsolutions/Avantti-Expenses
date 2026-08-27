<?php

namespace App\Mail;

use App\Models\Approval;
use App\Models\Rfi;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * An RFI or an approval, sent to its distribution list with the sheet attached.
 *
 * Subject and body are translated, which is the item the pt_BR audit found
 * people forget most often — nobody walks through an e-mail when checking the
 * screens.
 */
class CollaborationDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Rfi|Approval $document,
        public string $pdf,
        public string $filename,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $isRfi = $this->document instanceof Rfi;

        return new Envelope(
            subject: $isRfi
                ? __('collaboration.label.rfi', [
                    'number' => $this->document->number,
                    'subject' => $this->document->subject,
                ])
                : __('collaboration.label.approval', [
                    'number' => $this->document->number,
                    'title' => $this->document->title,
                ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.collaboration-document');
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->pdf, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
