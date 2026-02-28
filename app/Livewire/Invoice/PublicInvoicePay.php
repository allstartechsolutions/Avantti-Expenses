<?php

namespace App\Livewire\Invoice;

use App\Exceptions\CardPointeException;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\CardPointeService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PublicInvoicePay extends Component
{
    public Invoice $invoice;

    public string $paymentAmount = '';
    public string $cardToken = '';
    public string $cardName = '';
    public string $cardExpiryMonth = '';
    public string $cardExpiryYear = '';
    public string $cardCvv = '';
    public string $cardZip = '';
    public string $cardPaymentError = '';
    public bool $paymentSuccess = false;
    public string $paidAmountDisplay = '';

    public function mount(string $token)
    {
        $invoice = Invoice::where('payment_token', $token)
            ->with(['client', 'items'])
            ->first();

        if (!$invoice || $invoice->isDraft()) {
            abort(404);
        }

        $this->invoice = $invoice;
        $this->paymentAmount = number_format($invoice->getBalanceDue(), 2, '.', '');
    }

    public function setCardToken(string $token): void
    {
        $this->cardToken = $token;
        $this->cardPaymentError = '';
    }

    public function processPayment(): void
    {
        $this->invoice->refresh();
        $balanceDue = $this->invoice->getBalanceDue();

        if ($balanceDue <= 0) {
            $this->cardPaymentError = 'This invoice has already been paid in full.';
            return;
        }

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01', 'max:' . $balanceDue],
            'cardName' => ['required', 'string', 'max:255'],
            'cardExpiryMonth' => ['required', 'string', 'size:2', 'in:01,02,03,04,05,06,07,08,09,10,11,12'],
            'cardExpiryYear' => ['required', 'string', 'size:2'],
            'cardCvv' => ['required', 'string', 'min:3', 'max:4'],
            'cardZip' => ['required', 'string', 'max:10'],
        ], [
            'paymentAmount.max' => 'Payment amount cannot exceed the balance due ($' . number_format($balanceDue, 2) . ').',
            'cardName.required' => 'Name on card is required.',
            'cardExpiryMonth.required' => 'Expiration month is required.',
            'cardExpiryMonth.in' => 'Invalid month.',
            'cardExpiryYear.required' => 'Expiration year is required.',
            'cardCvv.required' => 'CVV is required.',
            'cardZip.required' => 'Billing zip code is required.',
        ]);

        if (empty($this->cardToken)) {
            $this->cardPaymentError = 'Please enter your card details.';
            return;
        }

        $service = app(CardPointeService::class);
        $amountCents = (string) round((float) $this->paymentAmount * 100);

        try {
            $result = $service->authorize([
                'amount' => $amountCents,
                'account' => $this->cardToken,
                'name' => $this->cardName,
                'expiry' => $this->cardExpiryMonth . $this->cardExpiryYear,
                'cvv2' => $this->cardCvv,
                'postal' => $this->cardZip,
            ]);
        } catch (CardPointeException $e) {
            $this->cardPaymentError = $e->getMessage();
            return;
        }

        if (!$result['success']) {
            $this->cardPaymentError = $result['resptext'];
            return;
        }

        $nextPaymentNumber = ($this->invoice->payments()->max('payment_number') ?? 0) + 1;

        InvoicePayment::create([
            'invoice_id' => $this->invoice->id,
            'payment_number' => $nextPaymentNumber,
            'amount' => $this->paymentAmount,
            'payment_method' => 'credit_card',
            'payment_date' => now()->format('Y-m-d'),
            'status' => 'completed',
            'gateway' => 'cardpointe',
            'gateway_transaction_id' => $result['retref'],
            'gateway_auth_code' => $result['authcode'],
            'gateway_status' => $result['respstat'],
            'card_last_four' => $result['card_last_four'],
            'card_brand' => $result['card_brand'],
            'created_by' => null,
        ]);

        $this->invoice->updateStatusFromPayments();

        $this->paidAmountDisplay = number_format((float) $this->paymentAmount, 2);
        $this->paymentSuccess = true;
        $this->invoice->refresh();
    }

    public function render()
    {
        $service = app(CardPointeService::class);

        return view('livewire.invoice.public-invoice-pay', [
            'iframeUrl' => $service->isConfigured() ? $service->getIframeUrl() : '',
            'cardPointeConfigured' => $service->isConfigured(),
        ])
            ->layout('components.layouts.guest');
    }
}
