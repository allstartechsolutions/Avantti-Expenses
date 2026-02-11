<?php

namespace App\Livewire\Invoice;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InvoiceShow extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice)
    {
        $this->invoice = $invoice->load(['client', 'project', 'jobSite', 'items', 'createdBy', 'emailsSent.sentBy', 'estimate', 'statusHistories.changedBy']);
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

    public function markAsPaid()
    {
        if (!$this->invoice->isPending() && !$this->invoice->isSent()) {
            session()->flash('error', 'Only sent or pending invoices can be marked as paid.');
            return;
        }

        $oldStatus = $this->invoice->status;
        $this->invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->invoice->recordStatusChange(Auth::user(), $oldStatus, 'paid');

        $this->refreshInvoice();
        session()->flash('message', 'Invoice marked as paid!');
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
        $this->invoice = $this->invoice->fresh(['client', 'project', 'jobSite', 'items', 'createdBy', 'emailsSent.sentBy', 'estimate', 'statusHistories.changedBy']);
    }

    public function render()
    {
        return view('livewire.invoice.invoice-show')
            ->layout('components.layouts.app');
    }
}
