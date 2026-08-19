<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Models\QuotationVendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The request for quotation (pedido de cotação) sent to one vendor, with the
 * scope attached as a PDF the vendor can price and send back.
 */
class QuotationRfqMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public QuotationVendor $quotationVendor,
        public string $emailSubject,
        public string $emailBody,
        public ?string $ccAddresses = null,
        public string $pdfContent = '',
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(subject: $this->emailSubject);

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
            view: 'emails.quotation-rfq',
            with: [
                'quotation' => $this->quotation,
                'quotationVendor' => $this->quotationVendor,
                'emailBody' => $this->emailBody,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename())
                ->withMime('application/pdf'),
        ];
    }

    protected function filename(): string
    {
        $number = $this->quotation->quotation_number ?: $this->quotation->id;
        $vendor = $this->quotationVendor->vendor?->name ?? 'vendor';

        return preg_replace('/[^a-zA-Z0-9\-_.]/', '-', sprintf('cotacao-%s-%s.pdf', $number, $vendor));
    }
}
