<?php

namespace App\Livewire\PaymentBatch;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentBatchEdit extends Component
{
    use WithPagination;

    public PaymentBatch $paymentBatch;

    public string $name = '';
    public string $payment_date = '';
    public string $notes = '';

    public string $clientFilter = '';
    public string $projectFilter = '';
    public string $subcontractorFilter = '';
    public string $projectManagerFilter = '';
    public string $statusFilter = '';
    public bool $showZeroBalance = false;

    public array $payAmounts = [];
    public array $payMethods = [];
    public array $payPhases = [];
    public array $payNotes = [];

    public function mount(): void
    {
        if (!$this->paymentBatch->canBeEdited()) {
            $this->redirect(route('payment-batches.show', $this->paymentBatch->id), navigate: true);
            return;
        }

        $this->name = $this->paymentBatch->name;
        $this->payment_date = $this->paymentBatch->payment_date->format('Y-m-d');
        $this->notes = $this->paymentBatch->notes ?? '';

        // Load saved filters from batch
        $this->clientFilter = (string) ($this->paymentBatch->client_id ?? '');
        $this->projectFilter = (string) ($this->paymentBatch->project_id ?? '');
        $this->subcontractorFilter = (string) ($this->paymentBatch->subcontractor_id ?? '');
        $this->projectManagerFilter = (string) ($this->paymentBatch->project_manager_id ?? '');
        $this->statusFilter = $this->paymentBatch->contract_status_filter ?? '';
        $this->showZeroBalance = $this->paymentBatch->show_zero_balance ?? false;

        $this->loadExistingItems();
    }

    public function loadExistingItems(): void
    {
        $items = $this->paymentBatch->items()->where('status', 'pending')->get();

        $this->payAmounts = [];
        $this->payMethods = [];
        $this->payPhases = [];
        $this->payNotes = [];

        foreach ($items as $item) {
            $key = (string) $item->contract_id;
            $this->payAmounts[$key] = $item->getRawOriginal('amount') ? $item->amount : '';
            $this->payMethods[$key] = $item->payment_method ?? '';
            $this->payPhases[$key] = $item->phase ?? '';
            $this->payNotes[$key] = $item->notes ?? '';
        }
    }

    public function updatedClientFilter(): void
    {
        if ($this->projectFilter) {
            $project = Project::find($this->projectFilter);
            if ($project && $this->clientFilter && $project->client_id != $this->clientFilter) {
                $this->projectFilter = '';
            }
        }
        $this->resetPage();
    }

    public function updatedProjectFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSubcontractorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedProjectManagerFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedShowZeroBalance(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function clients()
    {
        return Client::whereHas('projects.contracts')
            ->orderBy('company_name')
            ->get(['id', 'company_name']);
    }

    #[Computed]
    public function projects()
    {
        return Project::whereHas('contracts')
            ->when($this->clientFilter, fn ($q) => $q->where('client_id', $this->clientFilter))
            ->orderBy('project_name')
            ->get(['id', 'project_name', 'client_id']);
    }

    #[Computed]
    public function projectManagers()
    {
        return User::whereHas('managedProjects.contracts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function subcontractors()
    {
        return Subcontractor::whereHas('contracts')
            ->orderBy('company_name')
            ->get(['id', 'company_name']);
    }

    #[Computed]
    public function batchSummary(): array
    {
        $items = $this->paymentBatch->items;
        $totalCents = $items->sum(fn ($item) => $item->getRawOriginal('amount'));
        $approvedCents = $items->where('status', 'approved')->sum(fn ($item) => $item->getRawOriginal('amount'));
        $pendingCents = $items->where('status', 'pending')->sum(fn ($item) => $item->getRawOriginal('amount'));
        $rejectedCents = $items->where('status', 'rejected')->sum(fn ($item) => $item->getRawOriginal('amount'));

        return [
            'total_items' => $items->count(),
            'total_amount' => $totalCents / 100,
            'pending_count' => $items->where('status', 'pending')->count(),
            'pending_amount' => $pendingCents / 100,
            'approved_count' => $items->where('status', 'approved')->count(),
            'approved_amount' => $approvedCents / 100,
            'rejected_count' => $items->where('status', 'rejected')->count(),
            'rejected_amount' => $rejectedCents / 100,
        ];
    }

    public function saveDraft(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $this->paymentBatch->update([
            'name' => $this->name,
            'payment_date' => $this->payment_date,
            'notes' => $this->notes ?: null,
            'client_id' => $this->clientFilter ?: null,
            'project_id' => $this->projectFilter ?: null,
            'subcontractor_id' => $this->subcontractorFilter ?: null,
            'project_manager_id' => $this->projectManagerFilter ?: null,
            'contract_status_filter' => $this->statusFilter ?: null,
            'show_zero_balance' => $this->showZeroBalance,
        ]);

        // Collect all contract IDs that have any data (amount, phase, notes, or method)
        $allContractIds = collect($this->payAmounts)->keys()
            ->merge(collect($this->payPhases)->keys())
            ->merge(collect($this->payNotes)->keys())
            ->merge(collect($this->payMethods)->keys())
            ->unique();

        $rowsToSave = $allContractIds->filter(function ($contractId) {
            $amount = $this->payAmounts[$contractId] ?? null;
            $phase = $this->payPhases[$contractId] ?? null;
            $notes = $this->payNotes[$contractId] ?? null;
            $method = $this->payMethods[$contractId] ?? null;

            $hasAmount = $amount !== null && $amount !== '' && (float) $amount > 0;
            $hasPhase = !empty($phase);
            $hasNotes = !empty($notes);
            $hasMethod = !empty($method);

            return $hasAmount || $hasPhase || $hasNotes || $hasMethod;
        });

        $rowsToRemove = $allContractIds->diff($rowsToSave);

        DB::transaction(function () use ($rowsToSave, $rowsToRemove) {
            foreach ($rowsToSave as $contractId) {
                $amount = $this->payAmounts[$contractId] ?? null;
                $hasAmount = $amount !== null && $amount !== '' && (float) $amount > 0;

                PaymentBatchItem::updateOrCreate(
                    [
                        'payment_batch_id' => $this->paymentBatch->id,
                        'contract_id' => $contractId,
                    ],
                    [
                        'amount' => $hasAmount ? (float) $amount : null,
                        'payment_method' => ($this->payMethods[$contractId] ?? null) ?: null,
                        'phase' => ($this->payPhases[$contractId] ?? null) ?: null,
                        'notes' => ($this->payNotes[$contractId] ?? null) ?: null,
                        'status' => 'pending',
                    ]
                );
            }

            // Remove items that were cleared (only pending ones)
            if ($rowsToRemove->isNotEmpty()) {
                PaymentBatchItem::where('payment_batch_id', $this->paymentBatch->id)
                    ->whereIn('contract_id', $rowsToRemove->toArray())
                    ->where('status', 'pending')
                    ->delete();
            }
        });

        unset($this->batchSummary);
        $this->loadExistingItems();
        session()->flash('message', 'Draft saved successfully!');
    }

    public function approveItem(int $itemId): void
    {
        $item = PaymentBatchItem::where('payment_batch_id', $this->paymentBatch->id)
            ->where('id', $itemId)
            ->where('status', 'pending')
            ->firstOrFail();

        if (!$item->getRawOriginal('amount') || $item->amount <= 0) {
            session()->flash('error', 'Cannot approve an item without a payment amount.');
            return;
        }

        $contract = Contract::withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->find($item->contract_id);

        $changeOrdersTotal = ($contract->change_orders_total_cents ?? 0) / 100;
        $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
        $balance = round($contract->amount + $changeOrdersTotal - $totalPaidDollars, 2);

        if ($item->amount > $balance + 0.01) {
            session()->flash('error', "Payment of \${$item->amount} for {$contract->contract_number} exceeds balance of \$" . number_format($balance, 2) . ".");
            return;
        }

        DB::transaction(function () use ($item, $contract) {
            ContractPayment::create([
                'contract_id' => $item->contract_id,
                'amount' => $item->amount,
                'payment_date' => $this->paymentBatch->payment_date,
                'payment_method' => $item->payment_method,
                'phase' => $item->phase,
                'notes' => $item->notes,
                'created_by' => Auth::id(),
            ]);

            $item->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $contract->refresh();
            $contract->updateStatusFromPayments();
        });

        $this->updateBatchStatus();
        unset($this->batchSummary);

        // Remove from inline arrays since it's now approved
        unset($this->payAmounts[$item->contract_id]);
        unset($this->payMethods[$item->contract_id]);
        unset($this->payPhases[$item->contract_id]);
        unset($this->payNotes[$item->contract_id]);

        session()->flash('message', "Payment for {$contract->contract_number} approved and processed.");
    }

    public function approveAll(): void
    {
        $pendingItems = $this->paymentBatch->items()->where('status', 'pending')->get();

        if ($pendingItems->isEmpty()) {
            session()->flash('error', 'No pending items to approve.');
            return;
        }

        // Only approve items that have an amount
        $approvableItems = $pendingItems->filter(fn ($item) => $item->getRawOriginal('amount') && $item->amount > 0);

        if ($approvableItems->isEmpty()) {
            session()->flash('error', 'No pending items with amounts to approve.');
            return;
        }

        $errors = [];
        foreach ($approvableItems as $item) {
            $contract = Contract::withSum('payments as total_paid_cents', 'amount')
                ->withSum('changeOrders as change_orders_total_cents', 'amount')
                ->find($item->contract_id);

            $changeOrdersTotal = ($contract->change_orders_total_cents ?? 0) / 100;
            $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
            $balance = round($contract->amount + $changeOrdersTotal - $totalPaidDollars, 2);

            if ($item->amount > $balance + 0.01) {
                $errors[] = "{$contract->contract_number}: payment \${$item->amount} exceeds balance \$" . number_format($balance, 2);
            }
        }

        if (!empty($errors)) {
            session()->flash('error', 'Cannot approve all: ' . implode('; ', $errors));
            return;
        }

        DB::transaction(function () use ($approvableItems) {
            foreach ($approvableItems as $item) {
                ContractPayment::create([
                    'contract_id' => $item->contract_id,
                    'amount' => $item->amount,
                    'payment_date' => $this->paymentBatch->payment_date,
                    'payment_method' => $item->payment_method,
                    'phase' => $item->phase,
                    'notes' => $item->notes,
                    'created_by' => Auth::id(),
                ]);

                $item->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);

                $contract = Contract::find($item->contract_id);
                $contract->refresh();
                $contract->updateStatusFromPayments();
            }
        });

        $this->updateBatchStatus();
        unset($this->batchSummary);
        $this->loadExistingItems();

        session()->flash('message', $approvableItems->count() . ' payment(s) approved and processed.');
    }

    public function rejectItem(int $itemId): void
    {
        $item = PaymentBatchItem::where('payment_batch_id', $this->paymentBatch->id)
            ->where('id', $itemId)
            ->where('status', 'pending')
            ->firstOrFail();

        $item->update(['status' => 'rejected']);

        $this->updateBatchStatus();
        unset($this->batchSummary);

        unset($this->payAmounts[$item->contract_id]);
        unset($this->payMethods[$item->contract_id]);
        unset($this->payPhases[$item->contract_id]);
        unset($this->payNotes[$item->contract_id]);

        session()->flash('message', 'Item rejected.');
    }

    public function cancelBatch(): void
    {
        $approvedCount = $this->paymentBatch->items()->where('status', 'approved')->count();

        if ($approvedCount > 0) {
            session()->flash('error', 'Cannot cancel a batch that has approved items.');
            return;
        }

        $this->paymentBatch->update(['status' => 'cancelled']);
        unset($this->batchSummary);

        session()->flash('message', 'Batch cancelled.');
        $this->redirect(route('payment-batches.index'), navigate: true);
    }

    protected function updateBatchStatus(): void
    {
        $this->paymentBatch->refresh();
        $items = $this->paymentBatch->items;

        if ($items->isEmpty()) {
            return;
        }

        $pendingCount = $items->where('status', 'pending')->count();
        $approvedCount = $items->where('status', 'approved')->count();

        if ($pendingCount === 0 && $approvedCount > 0) {
            $this->paymentBatch->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        } elseif ($approvedCount > 0) {
            $this->paymentBatch->update(['status' => 'partially_approved']);
        }

        $this->paymentBatch->refresh();
    }

    public function render()
    {
        $contracts = Contract::with(['project.client', 'jobSite', 'subcontractor', 'latestPayment'])
            ->withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->when($this->clientFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientFilter)))
            ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
            ->when($this->subcontractorFilter, fn ($q) => $q->where('subcontractor_id', $this->subcontractorFilter))
            ->when($this->projectManagerFilter, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('project_manager_id', $this->projectManagerFilter)))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->unless($this->showZeroBalance, fn ($q) => $q->whereNotIn('status', ['paid', 'cancelled']))
            ->orderBy('project_id')
            ->orderBy('job_site_id')
            ->paginate(50);

        // Get batch items indexed by contract_id for quick lookup
        $batchItems = $this->paymentBatch->items()
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->get()
            ->keyBy('contract_id');

        return view('livewire.payment-batch.payment-batch-edit', [
            'contracts' => $contracts,
            'batchItems' => $batchItems,
        ])->layout('components.layouts.app');
    }
}
