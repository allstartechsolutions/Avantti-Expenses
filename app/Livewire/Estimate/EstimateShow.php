<?php

namespace App\Livewire\Estimate;

use App\Models\DocumentMessage;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EstimateShow extends Component
{
    public Estimate $estimate;

    public function mount(Estimate $estimate)
    {
        $this->estimate = $estimate->load(['client', 'project', 'jobSite', 'items', 'createdBy', 'emailsSent.sentBy', 'invoice', 'statusHistories.changedBy']);
    }

    public function markAsSent()
    {
        if (!$this->estimate->isDraft()) {
            session()->flash('error', __('Only draft estimates can be marked as sent.'));
            return;
        }

        $oldStatus = $this->estimate->status;
        $this->estimate->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $this->estimate->recordStatusChange(Auth::user(), $oldStatus, 'sent');

        $this->refreshEstimate();
        session()->flash('message', __('Estimate marked as sent!'));
    }

    public function markAsAccepted()
    {
        if (!$this->estimate->isSent()) {
            session()->flash('error', __('Only sent estimates can be accepted.'));
            return;
        }

        $oldStatus = $this->estimate->status;
        $this->estimate->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        $this->estimate->recordStatusChange(Auth::user(), $oldStatus, 'accepted');

        $this->refreshEstimate();
        session()->flash('message', __('Estimate marked as accepted!'));
    }

    public function markAsDeclined()
    {
        if (!$this->estimate->isSent()) {
            session()->flash('error', __('Only sent estimates can be declined.'));
            return;
        }

        $oldStatus = $this->estimate->status;
        $this->estimate->update([
            'status' => 'declined',
            'declined_at' => now(),
        ]);
        $this->estimate->recordStatusChange(Auth::user(), $oldStatus, 'declined');

        $this->refreshEstimate();
        session()->flash('message', __('Estimate marked as declined.'));
    }

    public function deleteEstimate()
    {
        if (!$this->estimate->canBeEdited()) {
            session()->flash('error', __('Only draft or sent estimates can be deleted.'));
            return;
        }

        $this->estimate->items()->delete();
        $this->estimate->delete();

        session()->flash('message', __('Estimate deleted successfully!'));

        return redirect()->route('estimates.index');
    }

    public function convertToInvoice()
    {
        if (!$this->estimate->isAccepted()) {
            session()->flash('error', __('Only accepted estimates can be converted to invoices.'));
            return;
        }

        if ($this->estimate->converted_to_invoice_id) {
            session()->flash('error', __('This estimate has already been converted to an invoice.'));
            return;
        }

        $invoice = DB::transaction(function () {
            $defaultMessage = DocumentMessage::where('type', 'invoice')
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            $invoice = Invoice::create([
                'client_id' => $this->estimate->client_id,
                'project_id' => $this->estimate->project_id,
                'job_site_id' => $this->estimate->job_site_id,
                'estimate_id' => $this->estimate->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'invoice_date' => now()->toDateString(),
                'terms' => $this->estimate->terms,
                'due_date' => Invoice::calculateDueDate(now()->toDateString(), $this->estimate->terms),
                'status' => 'draft',
                'message_title' => $defaultMessage?->title,
                'message_body' => $defaultMessage?->body,
                'discount_type' => $this->estimate->discount_type,
                'discount_value' => $this->estimate->discount_value,
                'discount_amount' => $this->estimate->getRawOriginal('discount_amount') / 100,
                'subtotal' => $this->estimate->getRawOriginal('subtotal') / 100,
                'tax_total' => $this->estimate->getRawOriginal('tax_total') / 100,
                'total_amount' => $this->estimate->getRawOriginal('total_amount') / 100,
                'notes' => $this->estimate->notes,
                'created_by' => Auth::id(),
            ]);

            foreach ($this->estimate->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'catalog_item_id' => $item->catalog_item_id,
                    'item_type' => $item->item_type,
                    'item_name' => $item->item_name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->getRawOriginal('unit_price') / 100,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'discount_amount' => $item->getRawOriginal('discount_amount') / 100,
                    'is_taxable' => $item->is_taxable,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->getRawOriginal('tax_amount') / 100,
                    'total_amount' => $item->getRawOriginal('total_amount') / 100,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $this->estimate->update(['converted_to_invoice_id' => $invoice->id]);

            return $invoice;
        });

        session()->flash('message', __('Invoice :number created from estimate!', ['number' => $invoice->invoice_number]));

        return redirect()->route('invoices.show', $invoice->id);
    }

    protected function refreshEstimate()
    {
        $this->estimate = $this->estimate->fresh(['client', 'project', 'jobSite', 'items', 'createdBy', 'emailsSent.sentBy', 'invoice', 'statusHistories.changedBy']);
    }

    public function render()
    {
        return view('livewire.estimate.estimate-show')
            ->layout('components.layouts.app');
    }
}
