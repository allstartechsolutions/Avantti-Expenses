<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $emailSubject,
        public string $emailBody,
        public ?string $ccAddresses = null,
        public string $pdfContent = '',
        public ?string $trackingToken = null,
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: $this->emailSubject,
        );

        if ($this->ccAddresses) {
            $parsed = array_filter(array_map('trim', explode(',', $this->ccAddresses)));
            foreach ($parsed as $address) {
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $envelope->cc[] = new Address($address);
                }
            }
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'emailBody' => $this->emailBody,
                'trackingToken' => $this->trackingToken,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $clientName = $this->invoice->client->company_name ?? 'client';
        $filename = sprintf('invoice-%s-%s.pdf', $this->invoice->invoice_number, $clientName);
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $filename);

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
