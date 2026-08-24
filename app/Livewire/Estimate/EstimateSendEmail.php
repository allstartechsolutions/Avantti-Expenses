<?php

namespace App\Livewire\Estimate;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Mail\EstimateMail;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class EstimateSendEmail extends Component
{
    use AuthorizesAbility;

    public Estimate $estimate;

    public string $emailTo = '';
    public string $cc = '';
    public string $subject = '';
    public string $body = '';
    public bool $sending = false;

    public function mount(Estimate $estimate): void
    {
        $this->estimate = $estimate;
        $this->emailTo = $estimate->client->email ?? '';

        $company = Company::first();
        $companyName = $company?->name ?? config('app.name');

        $this->subject = __('Estimate :number from :company', [
            'number' => $estimate->estimate_number,
            'company' => $companyName,
        ]);

        $this->body = __('Dear :name,', ['name' => $estimate->client->contact_name]).'<br><br>'
            .__('Please find attached the estimate :number for your review.', [
                'number' => '<strong>'.$estimate->estimate_number.'</strong>',
            ]).'<br><br>'
            .__('Total Amount:').' <strong>$'.$this->formatMoney($estimate->total_amount).'</strong><br><br>'
            .__("If you have any questions, please don't hesitate to reach out.").'<br><br>'
            .__('Best regards,').'<br>'
            .$companyName;
    }

    public function sendEmail(): void
    {
        $this->authorizeAbility('estimates.send');

        $this->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'cc' => 'nullable|string',
        ]);

        if (empty($this->emailTo)) {
            $this->dispatch('close-modal', 'send-email-modal');
            session()->flash('error', __('Client does not have an email address.'));
            return;
        }

        $this->sending = true;

        $this->estimate->load(['client', 'project', 'jobSite', 'items', 'createdBy']);
        $company = Company::first();
        $trackingToken = Str::uuid()->toString();

        $pdf = Pdf::loadView('pdf.estimate', [
            'estimate' => $this->estimate,
            'company' => $company,
        ]);
        $pdf->setPaper('letter', 'portrait');
        $pdfContent = $pdf->output();

        Mail::to($this->emailTo)->send(
            new EstimateMail(
                estimate: $this->estimate,
                emailSubject: $this->subject,
                emailBody: $this->body,
                ccAddresses: $this->cc ?: null,
                pdfContent: $pdfContent,
                trackingToken: $trackingToken,
            )
        );

        EstimateEmail::create([
            'estimate_id' => $this->estimate->id,
            'sent_to' => $this->emailTo,
            'cc' => $this->cc ?: null,
            'subject' => $this->subject,
            'body' => $this->body,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
            'tracking_token' => $trackingToken,
        ]);

        if ($this->estimate->isDraft()) {
            $this->estimate->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $this->estimate->recordStatusChange(Auth::user(), 'draft', 'sent');
        }

        $this->sending = false;
        $this->dispatch('close-modal', 'send-email-modal');
        session()->flash('message', __('Estimate emailed successfully!'));

        $this->redirect(route('estimates.show', $this->estimate->id));
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2);
    }

    public function render()
    {
        return view('livewire.estimate.estimate-send-email');
    }
}
