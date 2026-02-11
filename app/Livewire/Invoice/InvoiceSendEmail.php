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

        $amountPaidLine = '';
        $amountPaid = $invoice->getAmountPaid();
        if ($amountPaid > 0) {
            $amountPaidLine = "<tr><td style='padding:4px 0;font-size:13px;color:#28a745;'>Amount Paid:</td>"
                . "<td style='padding:4px 0;font-size:13px;color:#28a745;text-align:right;'>- \${$this->formatMoney($amountPaid)}</td></tr>";
        }

        $summaryBlock = "<table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;margin:15px 0;'>"
            . "<tr><td style='padding:20px;'>"
            . "<strong style='color:#3F5189;font-size:15px;'>Invoice Summary</strong>"
            . "<table width='100%' cellpadding='0' cellspacing='0' style='margin-top:10px;'>"
            . "<tr><td style='padding:4px 0;font-size:13px;color:#666;'>Invoice Number:</td><td style='padding:4px 0;font-size:13px;color:#333;text-align:right;font-weight:bold;'>{$invoice->invoice_number}</td></tr>"
            . "<tr><td style='padding:4px 0;font-size:13px;color:#666;'>Date:</td><td style='padding:4px 0;font-size:13px;color:#333;text-align:right;'>{$invoice->invoice_date->format('M d, Y')}</td></tr>"
            . "<tr><td style='padding:4px 0;font-size:13px;color:#666;'>Due Date:</td><td style='padding:4px 0;font-size:13px;color:#333;text-align:right;'>{$invoice->due_date->format('M d, Y')}</td></tr>"
            . "<tr><td style='padding:4px 0;font-size:13px;color:#666;'>Total:</td><td style='padding:4px 0;font-size:13px;color:#333;text-align:right;font-weight:bold;'>\${$this->formatMoney($invoice->total_amount)}</td></tr>"
            . $amountPaidLine
            . "<tr><td colspan='2' style='padding:8px 0 0;'><div style='border-top:1px solid #dee2e6;'></div></td></tr>"
            . "<tr><td style='padding:8px 0 4px;font-size:15px;color:#3F5189;font-weight:bold;'>Balance Due:</td><td style='padding:8px 0 4px;font-size:15px;color:#3F5189;text-align:right;font-weight:bold;'>\${$this->formatMoney($invoice->getBalanceDue())}</td></tr>"
            . "</table>"
            . "</td></tr></table>";

        $this->body = "Dear {$invoice->client->contact_name},<br><br>"
            . "Please find attached the invoice <strong>{$invoice->invoice_number}</strong> "
            . "for your review.<br><br>"
            . $summaryBlock
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
