<?php

namespace App\Livewire\PurchaseOrder;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PurchaseOrderShow extends Component
{
    use AuthorizesAbility;

    public PurchaseOrder $purchaseOrder;

    // Modal states
    public $showRejectModal = false;
    public $showCancelModal = false;
    public $rejectReason = '';
    public $cancelReason = '';

    // Receiving (M9)
    public $showReceiptModal = false;
    public $receiptDate = '';
    public $receiptNote = '';

    /** Ordered-line id => the quantity being booked in on this delivery. */
    public array $receiptQuantities = [];

    public function mount(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAbility('purchase-orders.view', $purchaseOrder);

        $this->purchaseOrder = $purchaseOrder->load([
            'project',
            'jobSite',
            'supplier',
            'items.budgetItem',
            'statusHistories.changedBy',
            'createdBy',
            'approvedBy',
            'expense',
            'receipts.receivedBy',
            'receipts.lines.item',
        ]);
    }

    /**
     * Submit the PO for approval (draft → pending)
     */
    public function submitForApproval()
    {
        $this->authorizeAbility('purchase-orders.edit', $this->purchaseOrder);

        if (!$this->purchaseOrder->canBeSubmitted()) {
            session()->flash('error', __('This purchase order cannot be submitted for approval.'));
            return;
        }

        $result = $this->purchaseOrder->submitForApproval(Auth::user());

        if ($result) {
            $this->refreshPurchaseOrder();
            session()->flash('message', __('Purchase order submitted for approval.'));
        } else {
            session()->flash('error', __('Failed to submit purchase order for approval.'));
        }
    }

    /**
     * Approve the PO (pending → approved) and create expense
     */
    public function approve()
    {
        // Approving turns the order into an expense — this is the moment the
        // money is committed, so it is the moment the ceiling binds.
        $this->authorizeAbilityWithin(
            'purchase-orders.approve',
            $this->purchaseOrder->totalInCents(),
            $this->purchaseOrder,
        );

        if (!$this->purchaseOrder->canBeApproved()) {
            session()->flash('error', __('This purchase order cannot be approved.'));
            return;
        }

        $result = $this->purchaseOrder->approve(Auth::user());

        if ($result) {
            $this->refreshPurchaseOrder();
            session()->flash('message', __('Purchase order approved! An expense has been created.'));
        } else {
            session()->flash('error', __('Failed to approve purchase order.'));
        }
    }

    /**
     * Open reject modal
     */
    public function openRejectModal()
    {
        $this->authorizeAbility('purchase-orders.approve', $this->purchaseOrder);

        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    /**
     * Close reject modal
     */
    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->rejectReason = '';
    }

    /**
     * Reject the PO (pending → rejected)
     */
    public function reject()
    {
        // Turning an order down commits nothing, so it needs the review grant
        // and not the ceiling.
        $this->authorizeAbility('purchase-orders.approve', $this->purchaseOrder);

        if (!$this->purchaseOrder->canBeRejected()) {
            session()->flash('error', __('This purchase order cannot be rejected.'));
            $this->closeRejectModal();
            return;
        }

        $result = $this->purchaseOrder->reject(Auth::user(), $this->rejectReason ?: null);

        if ($result) {
            $this->refreshPurchaseOrder();
            $this->closeRejectModal();
            session()->flash('message', __('Purchase order rejected.'));
        } else {
            session()->flash('error', __('Failed to reject purchase order.'));
            $this->closeRejectModal();
        }
    }

    /**
     * Open cancel modal
     */
    public function openCancelModal()
    {
        $this->authorizeAbility('purchase-orders.edit', $this->purchaseOrder);

        // Check if we can cancel (for approved POs, check expense status)
        if ($this->purchaseOrder->isApproved() && !$this->purchaseOrder->canChangeStatusFromApproved()) {
            session()->flash('error', __('Cannot cancel this PO because the linked expense has payments or is paid.'));
            return;
        }

        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    /**
     * Close cancel modal
     */
    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->cancelReason = '';
    }

    /**
     * Cancel the PO
     */
    public function cancel()
    {
        $this->authorizeAbility('purchase-orders.edit', $this->purchaseOrder);

        // Cancelling an order that was already approved unwinds committed
        // money, which is the reviewer's call rather than the raiser's.
        if ($this->purchaseOrder->isApproved()) {
            $this->authorizeAbility('purchase-orders.approve', $this->purchaseOrder);
        }

        if ($this->purchaseOrder->isCancelled()) {
            session()->flash('error', __('This purchase order is already cancelled.'));
            $this->closeCancelModal();
            return;
        }

        $result = $this->purchaseOrder->cancel(Auth::user(), $this->cancelReason ?: null);

        if ($result) {
            $this->refreshPurchaseOrder();
            $this->closeCancelModal();
            session()->flash('message', __('Purchase order cancelled.'));
        } else {
            session()->flash('error', __('Failed to cancel purchase order. The linked expense may have payments.'));
            $this->closeCancelModal();
        }
    }

    /**
     * Destroy the order outright.
     *
     * `purchase-orders.delete` has been in the catalogue since the module's
     * permission pass and enforced nothing until now — it showed as a real
     * grant on the access screens and did nothing when handed out.
     *
     * Only a draft or a cancelled order with no expense behind it: the model
     * decides, and it takes the items, the status history and the attachments
     * with it. There is no undo, so the button confirms.
     */
    public function deletePurchaseOrder()
    {
        $this->authorizeAbility('purchase-orders.delete', $this->purchaseOrder);

        abort_unless(
            $this->purchaseOrder->canBeDeleted(),
            403,
            __('Only a draft or a cancelled purchase order with no expense against it can be deleted.'),
        );

        $project = $this->purchaseOrder->project_id;
        $number = $this->purchaseOrder->po_number;

        $this->purchaseOrder->delete();

        session()->flash('message', __('Purchase order :number was deleted.', ['number' => $number]));

        return $this->redirect(route('projects.purchase-orders', $project), navigate: true);
    }

    /** Read by the view before it renders the button. Never the guard itself. */
    public function getCanDeleteProperty(): bool
    {
        return $this->purchaseOrder->canBeDeleted()
            && $this->allowsAbility('purchase-orders.delete', $this->purchaseOrder);
    }

    /**
     * Revise and resubmit a rejected PO
     */
    public function reviseAndResubmit()
    {
        $this->authorizeAbility('purchase-orders.edit', $this->purchaseOrder);

        if (!$this->purchaseOrder->canReviseAndResubmit()) {
            session()->flash('error', __('This purchase order cannot be revised and resubmitted.'));
            return;
        }

        $result = $this->purchaseOrder->reviseAndResubmit(Auth::user());

        if ($result) {
            $this->refreshPurchaseOrder();
            session()->flash('message', __('Purchase order revised and resubmitted for approval (Revision :revision).', ['revision' => $this->purchaseOrder->revision_number]));
        } else {
            session()->flash('error', __('Failed to resubmit purchase order.'));
        }
    }

    // =========================================================================
    // RECEIVING
    //
    // Approving an order commits the money; receiving it records that the
    // goods turned up. On a real site those are two people, so this is its own
    // grant and the storeman does not need `approve`.
    // =========================================================================

    public function openReceiptModal(): void
    {
        $this->authorizeAbility('purchase-orders.receive', $this->purchaseOrder);

        abort_unless(
            $this->purchaseOrder->canBeReceived(),
            403,
            __('This purchase order is not awaiting delivery.'),
        );

        $this->receiptDate = now()->format('Y-m-d');
        $this->receiptNote = '';

        // Pre-filled with what is still outstanding, because the whole
        // delivery arriving is the common case and typing it again is work.
        $this->receiptQuantities = $this->purchaseOrder->items
            ->mapWithKeys(fn ($item) => [$item->id => $item->outstandingQuantity() ?: ''])
            ->all();

        $this->resetErrorBag();
        $this->showReceiptModal = true;
        $this->dispatch('open-modal', 'po-receipt-modal');
    }

    public function closeReceiptModal(): void
    {
        $this->showReceiptModal = false;
        $this->reset(['receiptDate', 'receiptNote', 'receiptQuantities']);
        $this->dispatch('close-modal', 'po-receipt-modal');
    }

    public function recordReceipt(): void
    {
        $this->authorizeAbility('purchase-orders.receive', $this->purchaseOrder);

        abort_unless(
            $this->purchaseOrder->canBeReceived(),
            403,
            __('This purchase order is not awaiting delivery.'),
        );

        $this->validate([
            'receiptDate' => 'required|date',
            'receiptNote' => 'nullable|string|max:2000',
            'receiptQuantities.*' => 'nullable|numeric|min:0',
        ], [], [
            'receiptDate' => __('delivery date'),
            'receiptNote' => __('note'),
        ]);

        $receipt = $this->purchaseOrder->recordReceipt(
            Auth::user(),
            $this->receiptQuantities,
            $this->receiptDate,
            $this->receiptNote,
        );

        if (! $receipt) {
            $this->addError('receiptQuantities', __('Enter what actually arrived on at least one line.'));

            return;
        }

        $this->closeReceiptModal();
        $this->refreshPurchaseOrder();

        session()->flash('message', $this->purchaseOrder->isFullyReceived()
            ? __('Delivery recorded. This order is now fully received.')
            : __('Delivery recorded. Some lines are still outstanding.'));
    }

    /**
     * Refresh the purchase order data
     */
    protected function refreshPurchaseOrder()
    {
        $this->purchaseOrder = $this->purchaseOrder->fresh([
            'project',
            'jobSite',
            'supplier',
            'items.budgetItem',
            'statusHistories.changedBy',
            'createdBy',
            'approvedBy',
            'expense',
            'receipts.receivedBy',
            'receipts.lines.item',
        ]);
    }

    public function render()
    {
        return view('livewire.purchase-order.purchase-order-show')
            ->layout('components.layouts.app');
    }
}
