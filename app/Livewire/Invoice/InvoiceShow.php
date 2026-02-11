<?php

namespace App\Livewire\Invoice;

use App\Exceptions\CardPointeException;
use App\Models\ClientPaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\CardPointeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class InvoiceShow extends Component
{
    public Invoice $invoice;

    // Payment modal
    public bool $showPaymentModal = false;
    public $paymentAmount = '';
    public $paymentMethod = 'check';
    public $paymentDate = '';
    public $paymentReference = '';
    public $paymentNotes = '';

    // Card payment
    public string $paymentType = 'manual';
    public string $cardToken = '';
    public string $cardName = '';
    public string $cardExpiry = '';
    public string $cardCvv = '';
    public string $cardZip = '';
    public bool $saveCard = false;
    public $selectedPaymentMethodId = null;
    public string $cardPaymentError = '';
    public array $clientPaymentMethods = [];
    public bool $cardPointegConfigured = false;

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice->load(['client', 'project', 'jobSite', 'items', 'createdBy', 'emailsSent.sentBy', 'estimate', 'statusHistories.changedBy', 'payments.createdBy']);
        $this->cardPointegConfigured = app(CardPointeService::class)->isConfigured();
    }

    public function markAsSent()
    {
        if (!$this->invoice->isDraft()) {
            session()->flash('error', 'Only draft invoices can be marked as sent.');
            return;
        }

        $oldStatus = $this->invoice->status;
        $this->invoice->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $this->invoice->recordStatusChange(Auth::user(), $oldStatus, 'sent');

        $this->refreshInvoice();
        session()->flash('message', 'Invoice marked as sent!');
    }

    public function markAsPending()
    {
        if (!$this->invoice->isSent()) {
            session()->flash('error', 'Only sent invoices can be marked as pending.');
            return;
        }

        $oldStatus = $this->invoice->status;
        $this->invoice->update([
            'status' => 'pending',
        ]);
        $this->invoice->recordStatusChange(Auth::user(), $oldStatus, 'pending');

        $this->refreshInvoice();
        session()->flash('message', 'Invoice marked as pending!');
    }

    // Payment methods

    public function openPaymentModal()
    {
        $this->paymentAmount = number_format($this->invoice->getBalanceDue(), 2, '.', '');
        $this->paymentMethod = 'check';
        $this->paymentDate = now()->format('Y-m-d');
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->paymentType = 'manual';
        $this->resetCardFields();

        // Load saved payment methods for the client
        $this->clientPaymentMethods = $this->invoice->client
            ->paymentMethods()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ClientPaymentMethod $pm) => [
                'id' => $pm->id,
                'display_name' => $pm->getDisplayName(),
                'profileid' => $pm->cardpointe_profile_id,
                'acctid' => $pm->acctid,
            ])
            ->toArray();

        $this->showPaymentModal = true;
    }

    public function recordPayment()
    {
        $balanceDue = $this->invoice->getBalanceDue();

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01', 'max:' . $balanceDue],
            'paymentMethod' => ['required', 'in:cash,check,credit_card,debit_card,bank_transfer,pix,other'],
            'paymentDate' => ['required', 'date'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'paymentNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'paymentAmount.max' => 'Payment amount cannot exceed the balance due ($' . number_format($balanceDue, 2) . ').',
        ]);

        $nextPaymentNumber = ($this->invoice->payments()->max('payment_number') ?? 0) + 1;

        InvoicePayment::create([
            'invoice_id' => $this->invoice->id,
            'payment_number' => $nextPaymentNumber,
            'amount' => $this->paymentAmount,
            'payment_method' => $this->paymentMethod,
            'payment_date' => $this->paymentDate,
            'status' => 'completed',
            'reference_number' => $this->paymentReference ?: null,
            'notes' => $this->paymentNotes ?: null,
            'gateway' => 'manual',
            'created_by' => Auth::id(),
        ]);

        $this->invoice->updateStatusFromPayments();

        $this->showPaymentModal = false;
        $this->refreshInvoice();
        session()->flash('message', 'Payment recorded successfully!');
    }

    // Credit card payment

    public function setCardToken(string $token)
    {
        $this->cardToken = $token;
        $this->cardPaymentError = '';
    }

    public function resetCardFields()
    {
        $this->cardToken = '';
        $this->cardName = '';
        $this->cardExpiry = '';
        $this->cardCvv = '';
        $this->cardZip = '';
        $this->saveCard = false;
        $this->selectedPaymentMethodId = null;
        $this->cardPaymentError = '';
    }

    public function processCardPayment()
    {
        $balanceDue = $this->invoice->getBalanceDue();
        $usingSavedCard = !empty($this->selectedPaymentMethodId);

        $rules = [
            'paymentAmount' => ['required', 'numeric', 'min:0.01', 'max:' . $balanceDue],
        ];

        // All card fields required for new cards only
        if (!$usingSavedCard) {
            $rules['cardName'] = ['required', 'string', 'max:255'];
            $rules['cardExpiry'] = ['required', 'string', 'size:4'];
            $rules['cardCvv'] = ['required', 'string', 'min:3', 'max:4'];
            $rules['cardZip'] = ['required', 'string', 'max:10'];
        }

        $this->validate($rules, [
            'paymentAmount.max' => 'Payment amount cannot exceed the balance due ($' . number_format($balanceDue, 2) . ').',
            'cardName.required' => 'Name on card is required.',
            'cardExpiry.required' => 'Expiration date is required.',
            'cardExpiry.size' => 'Expiration must be 4 digits (MMYY).',
            'cardCvv.required' => 'CVV is required.',
            'cardZip.required' => 'Billing zip code is required.',
        ]);

        $service = app(CardPointeService::class);
        $amountCents = (string) round((float) $this->paymentAmount * 100);

        // Build authorize params
        $params = [
            'amount' => $amountCents,
        ];

        if ($usingSavedCard) {
            // Find the saved card
            $savedCard = collect($this->clientPaymentMethods)
                ->firstWhere('id', $this->selectedPaymentMethodId);

            if (!$savedCard) {
                $this->cardPaymentError = 'Selected payment method not found.';
                return;
            }

            $params['profile'] = $savedCard['profileid'] . '/' . $savedCard['acctid'];
        } else {
            // New card from iFrame
            if (empty($this->cardToken)) {
                $this->cardPaymentError = 'Please enter your card details.';
                return;
            }

            $params['account'] = $this->cardToken;
            $params['name'] = $this->cardName;
            $params['expiry'] = $this->cardExpiry;
            $params['cvv2'] = $this->cardCvv;
            $params['postal'] = $this->cardZip;

            if ($this->saveCard && !$this->invoice->client->cardpointe_profile_id) {
                $params['profile'] = 'y';
            }
        }

        try {
            $result = $service->authorize($params);
        } catch (CardPointeException $e) {
            $this->cardPaymentError = $e->getMessage();
            return;
        }

        if (!$result['success']) {
            $this->cardPaymentError = $result['resptext'];
            return;
        }

        // Create the payment record
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
            'created_by' => Auth::id(),
        ]);

        // Save card if requested
        if ($this->saveCard && !$usingSavedCard) {
            $client = $this->invoice->client;

            if (!$client->cardpointe_profile_id && !empty($result['profileid'])) {
                // First card — profile created during auth
                $client->update(['cardpointe_profile_id' => $result['profileid']]);

                ClientPaymentMethod::create([
                    'client_id' => $client->id,
                    'cardpointe_profile_id' => $result['profileid'],
                    'acctid' => $result['acctid'] ?? '1',
                    'card_last_four' => $result['card_last_four'],
                    'card_brand' => $result['card_brand'],
                    'card_name' => $this->cardName,
                    'expiry' => $this->cardExpiry ?: null,
                    'token' => $result['token'],
                    'is_default' => true,
                    'created_by' => Auth::id(),
                ]);
            } elseif ($client->cardpointe_profile_id) {
                // Subsequent card — add to existing profile via profile endpoint
                try {
                    $profileResult = $service->addCardToProfile($client->cardpointe_profile_id, [
                        'account' => $this->cardToken,
                        'name' => $this->cardName,
                        'email' => $client->email,
                        'expiry' => $this->cardExpiry,
                    ]);

                    if ($profileResult['success']) {
                        ClientPaymentMethod::create([
                            'client_id' => $client->id,
                            'cardpointe_profile_id' => $client->cardpointe_profile_id,
                            'acctid' => $profileResult['acctid'] ?? '1',
                            'card_last_four' => $result['card_last_four'],
                            'card_brand' => $result['card_brand'],
                            'card_name' => $this->cardName,
                            'expiry' => $this->cardExpiry ?: null,
                            'token' => $profileResult['token'] ?: $result['token'],
                            'is_default' => false,
                            'created_by' => Auth::id(),
                        ]);
                    }
                } catch (CardPointeException $e) {
                    // Payment succeeded, card save failed — non-critical
                    Log::warning('Card save to profile failed after payment', ['error' => $e->getMessage()]);
                }
            }
        }

        $this->invoice->updateStatusFromPayments();

        $this->showPaymentModal = false;
        $this->refreshInvoice();
        session()->flash('message', 'Card payment of $' . number_format((float) $this->paymentAmount, 2) . ' processed successfully!');
    }

    public function voidPayment(int $paymentId)
    {
        $payment = $this->invoice->payments()->where('id', $paymentId)->first();

        if (!$payment || !$payment->isCompleted()) {
            session()->flash('error', 'Payment cannot be voided.');
            return;
        }

        // If it's a CardPointe payment, try void first, fall back to refund
        if ($payment->gateway === 'cardpointe' && $payment->gateway_transaction_id) {
            $service = app(CardPointeService::class);

            try {
                // Try void first (works before batch settles)
                $result = $service->void($payment->gateway_transaction_id);

                if ($result['success']) {
                    $payment->markAsVoided();
                    $this->refreshInvoice();
                    session()->flash('message', 'Payment voided successfully.');
                    return;
                }

                // Void failed — transaction likely already settled, try refund
                $amountCents = (int) round($payment->getRawOriginal('amount'));
                $refundResult = $service->refund($payment->gateway_transaction_id, $amountCents);

                if (!$refundResult['success']) {
                    session()->flash('error', 'Refund failed: ' . $refundResult['resptext']);
                    return;
                }

                $payment->update([
                    'status' => 'refunded',
                    'refund_amount' => $payment->amount,
                    'refunded_at' => now(),
                    'refund_transaction_id' => $refundResult['retref'],
                ]);
                $this->invoice->updateStatusFromPayments();
                $this->refreshInvoice();
                session()->flash('message', 'Payment refunded successfully (batch already settled).');
                return;
            } catch (CardPointeException $e) {
                session()->flash('error', $e->getMessage());
                return;
            }
        }

        $payment->markAsVoided();

        $this->refreshInvoice();
        session()->flash('message', 'Payment voided successfully.');
    }

    public function deleteInvoice()
    {
        if (!$this->invoice->canBeEdited()) {
            session()->flash('error', 'Only draft or sent invoices can be deleted.');
            return;
        }

        if ($this->invoice->estimate_id) {
            $this->invoice->estimate->update(['converted_to_invoice_id' => null]);
        }

        $this->invoice->items()->delete();
        $this->invoice->delete();

        session()->flash('message', 'Invoice deleted successfully!');

        return redirect()->route('invoices.index');
    }

    protected function refreshInvoice()
    {
        $this->invoice = $this->invoice->fresh(['client', 'project', 'jobSite', 'items', 'createdBy', 'emailsSent.sentBy', 'estimate', 'statusHistories.changedBy', 'payments.createdBy']);
    }

    public function render()
    {
        return view('livewire.invoice.invoice-show', [
            'iframeUrl' => $this->cardPointegConfigured ? app(CardPointeService::class)->getIframeUrl() : '',
        ])
            ->layout('components.layouts.app');
    }
}
