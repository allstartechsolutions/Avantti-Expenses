<?php

namespace App\Livewire\Contract;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\ContractPaymentItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /**
     * Cost-code lines for the payment being recorded. One row per code in
     * the contract's schedule; a row participates when it gets an amount
     * or a new % complete. Entering a new % suggests the amount
     * (Δ% × scheduled value), which stays editable.
     */
    public array $paymentItems = [];

    public function mount(Contract $contract)
    {
        $this->contract = $contract->load([
            'project',
            'jobSite',
            'subcontractor',
            'subcontractorEmployee',
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

        $this->paymentItems = $this->hasCostCoding()
            ? $this->contract->costCodeSchedule()
                ->filter(fn (array $row) => $row['budget_item_id'] !== null)
                ->map(fn (array $row) => [
                    'budget_item_id' => $row['budget_item_id'],
                    'code_display' => $row['code_display'],
                    'scheduled' => $row['scheduled'],
                    'prior_paid' => $row['paid'],
                    'prior_percent' => $row['percent_complete'],
                    'percent' => '',
                    'amount' => '',
                ])
                ->values()
                ->all()
            : [];

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
        $this->paymentItems = [];
    }

    public function updatedPaymentItems($value, $key)
    {
        [$index, $field] = explode('.', $key, 2);

        if ($field === 'percent' && is_numeric($value) && isset($this->paymentItems[$index])) {
            $row = $this->paymentItems[$index];
            $delta = (float) $value - (float) ($row['prior_percent'] ?? 0);
            $suggested = round($delta * $row['scheduled'] / 100, 2);
            $this->paymentItems[$index]['amount'] = $suggested > 0 ? number_format($suggested, 2, '.', '') : '';
        }

        $this->syncPaymentAmountFromItems();
    }

    protected function syncPaymentAmountFromItems(): void
    {
        $total = round(collect($this->paymentItems)->sum(fn ($row) => (float) ($row['amount'] ?: 0)), 2);

        if ($total > 0) {
            $this->paymentAmount = number_format($total, 2, '.', '');
        }
    }

    /**
     * Rows taking part in this payment: any with an amount or a new %.
     */
    protected function activePaymentItems(): array
    {
        return array_values(array_filter($this->paymentItems, function ($row) {
            return (float) ($row['amount'] ?: 0) > 0 || $row['percent'] !== '';
        }));
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
            'paymentItems.*.amount' => ['nullable', 'numeric', 'min:0'],
            'paymentItems.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'paymentAmount.max' => 'Payment amount cannot exceed the balance due of ' . Number::currency($balanceDue, config('app.currency'), config('app.locale')) . '.',
            'paymentItems.*.percent.max' => __('% complete cannot exceed 100.'),
        ]);

        $activeItems = $this->activePaymentItems();

        if ($activeItems !== []) {
            $itemsTotal = round(collect($activeItems)->sum(fn ($row) => (float) ($row['amount'] ?: 0)), 2);
            if (abs($itemsTotal - (float) $this->paymentAmount) > 0.009) {
                $this->addError('paymentItems', __('The cost code lines must add up to the payment amount.'));

                return;
            }
        }

        DB::transaction(function () use ($activeItems) {
            $payment = ContractPayment::create([
                'contract_id' => $this->contract->id,
                'amount' => $this->paymentAmount,
                'payment_date' => $this->paymentDate,
                'payment_method' => $this->paymentMethod,
                'reference_number' => $this->paymentReference ?: null,
                'notes' => $this->paymentNotes ?: null,
                'created_by' => Auth::id(),
            ]);

            foreach ($activeItems as $row) {
                $payment->items()->create([
                    'budget_item_id' => $row['budget_item_id'],
                    'amount' => $row['amount'] ?: 0,
                    'percent_complete' => $row['percent'] !== '' ? $row['percent'] : null,
                ]);
            }
        });

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
            'subcontractorEmployee',
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

    /**
     * The schedule-of-values grid is only shown once the contract has
     * some cost coding (allocations, coded change orders, or itemized
     * payments) — an entirely uncoded contract adds no information.
     */
    protected function hasCostCoding(): bool
    {
        return $this->contract->allocations()->exists()
            || $this->contract->changeOrders()->whereNotNull('budget_item_id')->exists()
            || ContractPaymentItem::whereIn(
                'contract_payment_id',
                $this->contract->payments()->select('id')
            )->exists();
    }

    public function render()
    {
        return view('livewire.contract.contract-show', [
            'costCodeSchedule' => $this->hasCostCoding() ? $this->contract->costCodeSchedule() : null,
        ])->layout('components.layouts.app');
    }
}
