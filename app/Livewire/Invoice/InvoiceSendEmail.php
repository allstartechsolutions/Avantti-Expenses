<?php

namespace App\Livewire\Invoice;

use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class InvoiceSendEmail extends Component
{
    public Invoice $invoice;

    public string $emailTo = '';
    public string $cc = '';
    public string $subject = '';
    public string $body = '';
    public bool $sending = false;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
        $this->emailTo = $invoice->client->email ?? '';

        $company = Company::first();
        $companyName = $company?->name ?? config('app.name');

        $this->subject = "Invoice {$invoice->invoice_number} from {$companyName}";

        $this->body = "Dear {$invoice->client->contact_name},<br><br>"
            . "Please find attached the invoice <strong>{$invoice->invoice_number}</strong> "
            . "for your review.<br><br>"
            . "Total Amount: <strong>\${$this->formatMoney($invoice->total_amount)}</strong><br>"
            . "Due Date: <strong>{$invoice->due_date->format('M d, Y')}</strong><br><br>"
            . "If you have any questions, please don't hesitate to reach out.<br><br>"
            . "Best regards,<br>"
            . $companyName;
    }

    public function sendEmail(): void
    {
        $this->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'cc' => 'nullable|string',
        ]);

        if (empty($this->emailTo)) {
            $this->dispatch('close-modal', 'send-email-modal');
            session()->flash('error', 'Client does not have an email address.');
            return;
        }

        $this->sending = true;

        $this->invoice->load(['client', 'project', 'jobSite', 'items', 'createdBy']);
        $company = Company::first();
        $trackingToken = Str::uuid()->toString();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $this->invoice,
            'company' => $company,
        ]);
        $pdf->setPaper('letter', 'portrait');
        $pdfContent = $pdf->output();

        Mail::to($this->emailTo)->send(
            new InvoiceMail(
                invoice: $this->invoice,
                emailSubject: $this->subject,
                emailBody: $this->body,
                ccAddresses: $this->cc ?: null,
                pdfContent: $pdfContent,
                trackingToken: $trackingToken,
            )
        );

        InvoiceEmail::create([
            'invoice_id' => $this->invoice->id,
            'sent_to' => $this->emailTo,
            'cc' => $this->cc ?: null,
            'subject' => $this->subject,
            'body' => $this->body,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
            'tracking_token' => $trackingToken,
        ]);

        if ($this->invoice->isDraft()) {
            $this->invoice->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $this->invoice->recordStatusChange(Auth::user(), 'draft', 'sent');
        }

        $this->sending = false;
        $this->dispatch('close-modal', 'send-email-modal');
        session()->flash('message', 'Invoice emailed successfully!');

        $this->redirect(route('invoices.show', $this->invoice->id));
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2);
    }

    public function render()
    {
        return view('livewire.invoice.invoice-send-email');
    }
}
