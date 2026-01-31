<?php

namespace App\Livewire\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PurchaseOrderShow extends Component
{
    public PurchaseOrder $purchaseOrder;

    // Modal states
    public $showRejectModal = false;
    public $showCancelModal = false;
    public $rejectReason = '';
    public $cancelReason = '';

    public function mount(PurchaseOrder $purchaseOrder)
    {
        $this->purchaseOrder = $purchaseOrder->load([
            'project',
            'jobSite',
            'supplier',
            'items.budgetItem',
            'statusHistories.changedBy',
            'createdBy',
            'approvedBy',
            'expense',
        ]);
    }

    /**
     * Submit the PO for approval (draft → pending)
     */
    public function submitForApproval()
    {
        if (!$this->purchaseOrder->canBeSubmitted()) {
            session()->flash('error', 'This purchase order cannot be submitted for approval.');
            return;
        }

        $result = $this->purchaseOrder->submitForApproval(Auth::user());

        if ($result) {
            $this->refreshPurchaseOrder();
            session()->flash('message', 'Purchase order submitted for approval.');
        } else {
            session()->flash('error', 'Failed to submit purchase order for approval.');
        }
    }

    /**
     * Approve the PO (pending → approved) and create expense
     */
    public function approve()
    {
        if (!$this->purchaseOrder->canBeApproved()) {
            session()->flash('error', 'This purchase order cannot be approved.');
            return;
        }

        $result = $this->purchaseOrder->approve(Auth::user());

        if ($result) {
            $this->refreshPurchaseOrder();
            session()->flash('message', 'Purchase order approved! An expense has been created.');
        } else {
            session()->flash('error', 'Failed to approve purchase order.');
        }
    }

    /**
     * Open reject modal
     */
    public function openRejectModal()
    {
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
        if (!$this->purchaseOrder->canBeRejected()) {
            session()->flash('error', 'This purchase order cannot be rejected.');
            $this->closeRejectModal();
            return;
        }

        $result = $this->purchaseOrder->reject(Auth::user(), $this->rejectReason ?: null);

        if ($result) {
            $this->refreshPurchaseOrder();
            $this->closeRejectModal();
            session()->flash('message', 'Purchase order rejected.');
        } else {
            session()->flash('error', 'Failed to reject purchase order.');
            $this->closeRejectModal();
        }
    }

    /**
     * Open cancel modal
     */
    public function openCancelModal()
    {
        // Check if we can cancel (for approved POs, check expense status)
        if ($this->purchaseOrder->isApproved() && !$this->purchaseOrder->canChangeStatusFromApproved()) {
            session()->flash('error', 'Cannot cancel this PO because the linked expense has payments or is paid.');
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
        if ($this->purchaseOrder->isCancelled()) {
            session()->flash('error', 'This purchase order is already cancelled.');
            $this->closeCancelModal();
            return;
        }

        $result = $this->purchaseOrder->cancel(Auth::user(), $this->cancelReason ?: null);

        if ($result) {
            $this->refreshPurchaseOrder();
            $this->closeCancelModal();
            session()->flash('message', 'Purchase order cancelled.');
        } else {
            session()->flash('error', 'Failed to cancel purchase order. The linked expense may have payments.');
            $this->closeCancelModal();
        }
    }

    /**
     * Revise and resubmit a rejected PO
     */
    public function reviseAndResubmit()
    {
        if (!$this->purchaseOrder->canReviseAndResubmit()) {
            session()->flash('error', 'This purchase order cannot be revised and resubmitted.');
            return;
        }

        $result = $this->purchaseOrder->reviseAndResubmit(Auth::user());

        if ($result) {
            $this->refreshPurchaseOrder();
            session()->flash('message', 'Purchase order revised and resubmitted for approval (Revision ' . $this->purchaseOrder->revision_number . ').');
        } else {
            session()->flash('error', 'Failed to resubmit purchase order.');
        }
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
        ]);
    }

    public function render()
    {
        return view('livewire.purchase-order.purchase-order-show')
            ->layout('components.layouts.app');
    }
}
