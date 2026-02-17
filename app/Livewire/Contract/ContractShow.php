<?php

namespace App\Livewire\Contract;

use App\Models\Contract;
use App\Models\ContractPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Livewire\Component;

class ContractShow extends Component
{
    public Contract $contract;

    // Status change modal
    public $showStatusModal = false;
    public $newStatus = '';
    public $statusReason = '';

    // Payment modal
    public $showPaymentModal = false;
    public $paymentAmount = '';
    public $paymentMethod = 'check';
    public $paymentDate = '';
    public $paymentReference = '';
    public $paymentNotes = '';

    public function mount(Contract $contract)
    {
        $this->contract = $contract->load([
            'project',
            'jobSite',
            'subcontractor',
            'createdBy',
            'statusHistories.changedBy',
            'payments.createdBy',
            'changeOrders.createdBy',
        ]);
    }

    public function getAvailableStatusesProperty(): array
    {
        return match ($this->contract->status) {
            'active' => ['completed' => 'Completed', 'cancelled' => 'Cancelled'],
            'completed' => ['paid' => 'Paid', 'partially_paid' => 'Partially Paid'],
            'partially_paid' => ['paid' => 'Paid'],
            default => [],
        };
    }

    public function openStatusModal()
    {
        if (empty($this->availableStatuses)) {
            return;
        }

        $this->newStatus = array_key_first($this->availableStatuses);
        $this->statusReason = '';
        $this->showStatusModal = true;
    }

    public function closeStatusModal()
    {
        $this->showStatusModal = false;
        $this->newStatus = '';
        $this->statusReason = '';
    }

    public function changeStatus()
    {
        $allowed = array_keys($this->availableStatuses);

        if (!in_array($this->newStatus, $allowed)) {
            session()->flash('error', 'Invalid status transition.');
            $this->closeStatusModal();
            return;
        }

        $oldStatus = $this->contract->status;
        $this->contract->update(['status' => $this->newStatus]);
        $this->contract->recordStatusChange(Auth::user(), $oldStatus, $this->newStatus, $this->statusReason ?: null);

        $this->refreshContract();
        $this->closeStatusModal();
        session()->flash('message', 'Contract status updated successfully.');
    }

    public function openPaymentModal()
    {
        $this->paymentAmount = number_format($this->contract->getBalanceDue(), 2, '.', '');
        $this->paymentMethod = 'check';
        $this->paymentDate = now()->format('Y-m-d');
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->paymentAmount = '';
        $this->paymentMethod = 'check';
        $this->paymentDate = '';
        $this->paymentReference = '';
        $this->paymentNotes = '';
    }

    public function recordPayment()
    {
        $balanceDue = $this->contract->getBalanceDue();

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01', 'max:' . $balanceDue],
            'paymentDate' => ['required', 'date'],
            'paymentMethod' => ['required', 'in:cash,check,credit_card,debit_card,bank_transfer,pix,other'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'paymentNotes' => ['nullable', 'string'],
        ], [
            'paymentAmount.max' => 'Payment amount cannot exceed the balance due of ' . Number::currency($balanceDue, config('app.currency'), config('app.locale')) . '.',
        ]);

        ContractPayment::create([
            'contract_id' => $this->contract->id,
            'amount' => $this->paymentAmount,
            'payment_date' => $this->paymentDate,
            'payment_method' => $this->paymentMethod,
            'reference_number' => $this->paymentReference ?: null,
            'notes' => $this->paymentNotes ?: null,
            'created_by' => Auth::id(),
        ]);

        $this->contract->updateStatusFromPayments();
        $this->refreshContract();
        $this->closePaymentModal();
        session()->flash('message', 'Payment recorded successfully.');
    }

    public function deletePayment($id)
    {
        $payment = ContractPayment::where('contract_id', $this->contract->id)->findOrFail($id);
        $payment->delete();

        $this->contract->updateStatusFromPayments();
        $this->refreshContract();
        session()->flash('message', 'Payment deleted successfully.');
    }

    #[\Livewire\Attributes\On('change-orders-updated')]
    public function refreshContract()
    {
        $this->contract = $this->contract->fresh([
            'project',
            'jobSite',
            'subcontractor',
            'createdBy',
            'statusHistories.changedBy',
            'payments.createdBy',
            'changeOrders.createdBy',
        ]);
    }

    public function delete()
    {
        // Clean up contract file before deleting
        if ($this->contract->contract_file_path && Storage::exists($this->contract->contract_file_path)) {
            Storage::delete($this->contract->contract_file_path);
        }

        // Clean up change order files (cascade delete won't trigger Eloquent events)
        foreach ($this->contract->changeOrders as $changeOrder) {
            if ($changeOrder->file_path && Storage::exists($changeOrder->file_path)) {
                Storage::delete($changeOrder->file_path);
            }
        }

        $jobSiteId = $this->contract->job_site_id;
        $projectId = $this->contract->project_id;

        $this->contract->delete();

        session()->flash('message', 'Contract deleted successfully.');

        if ($jobSiteId) {
            return redirect()->route('jobsites.contracts', $jobSiteId);
        }

        return redirect()->route('projects.contracts', $projectId);
    }

    public function render()
    {
        return view('livewire.contract.contract-show')
            ->layout('components.layouts.app');
    }
}
