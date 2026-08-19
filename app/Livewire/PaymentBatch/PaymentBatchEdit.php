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

    /**
     * What each contract's payment settles, keyed by contract id:
     * "parcela:{id}" or "medicao:{id}" ("" = neither). Contracts paid
     * through a cronograma must carry one, exactly as on the contract
     * page — otherwise batch money would bypass the cronograma and leave
     * the parcela payable a second time.
     */
    public array $payTargets = [];

    public function mount(): void
    {
        if (! $this->paymentBatch->canBeEdited()) {
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

        $this->payTargets = [];

        foreach ($items as $item) {
            $key = (string) $item->contract_id;
            $this->payAmounts[$key] = $item->getRawOriginal('amount') ? $item->amount : '';
            $this->payMethods[$key] = $item->payment_method ?? '';
            $this->payPhases[$key] = $item->phase ?? '';
            $this->payNotes[$key] = $item->notes ?? '';
            $this->payTargets[$key] = match (true) {
                $item->contract_schedule_item_id !== null => 'parcela:'.$item->contract_schedule_item_id,
                $item->contract_measurement_id !== null => 'medicao:'.$item->contract_measurement_id,
                default => '',
            };
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
            ->orderBy('name')
            ->get(['id', 'name']);
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
            ->merge(collect($this->payTargets)->keys())
            ->unique();

        $rowsToSave = $allContractIds->filter(function ($contractId) {
            $amount = $this->payAmounts[$contractId] ?? null;
            $phase = $this->payPhases[$contractId] ?? null;
            $notes = $this->payNotes[$contractId] ?? null;
            $method = $this->payMethods[$contractId] ?? null;

            $hasAmount = $amount !== null && $amount !== '' && (float) $amount > 0;
            $hasPhase = ! empty($phase);
            $hasNotes = ! empty($notes);
            $hasMethod = ! empty($method);
            $hasTarget = ! empty($this->payTargets[$contractId] ?? '');

            return $hasAmount || $hasPhase || $hasNotes || $hasMethod || $hasTarget;
        });

        $rowsToRemove = $allContractIds->diff($rowsToSave);

        // The screen lists committed contracts only, but these ids come back
        // from the browser: a draft must not be written into the batch, or it
        // would sit there waiting to be approved as if it were money owed.
        $draftIds = Contract::whereIn('id', $rowsToSave)
            ->whereIn('status', Contract::UNCOMMITTED_STATUSES)
            ->pluck('id');

        if ($draftIds->isNotEmpty()) {
            $rowsToSave = $rowsToSave->reject(fn ($id) => $draftIds->contains($id))->values();

            session()->flash('error', trans_choice(
                '{1} :count contract was left out: it is still a draft.|[2,*] :count contracts were left out: they are still drafts.',
                $draftIds->count(),
                ['count' => $draftIds->count()]
            ));
        }

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
                        'contract_schedule_item_id' => $this->targetScheduleItemId($contractId),
                        'contract_measurement_id' => $this->targetMeasurementId($contractId),
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
        session()->flash('message', __('Draft saved successfully!'));
    }

    // ---------------------------------------------------------------
    // Cronograma / medição targets
    // ---------------------------------------------------------------

    protected function targetScheduleItemId($contractId): ?int
    {
        $target = $this->payTargets[$contractId] ?? '';

        return str_starts_with($target, 'parcela:') ? (int) substr($target, 8) : null;
    }

    protected function targetMeasurementId($contractId): ?int
    {
        $target = $this->payTargets[$contractId] ?? '';

        return str_starts_with($target, 'medicao:') ? (int) substr($target, 8) : null;
    }

    /**
     * What a contract can pay in this batch: its payable parcelas and its
     * approved medições that still owe net — the same lists the contract
     * page offers, so both routes obey the same rules. Keyed by contract
     * id for the rows on screen.
     */
    public function payableTargetsFor(Contract $contract): array
    {
        $options = [];

        $contract->loadMissing([
            'changeOrders',
            'payments',
            'scheduleItems.payments',
            'scheduleItems.measurements.payments',
            'measurements.payments',
        ]);

        $contract->scheduleItems->each(fn ($item) => $item->setRelation('contract', $contract));

        foreach ($contract->scheduleItems as $item) {
            $measured = $item->measurements->contains(
                fn ($measurement) => $measurement->isApproved() && $measurement->getRemainingNet() > 0.009
            );

            if ($item->getBalance() > 0.009 && $item->isDue() && ! $measured) {
                $options['parcela:'.$item->id] = [
                    'label' => $item->description,
                    'amount' => $item->getBalance(),
                ];
            }
        }

        foreach ($contract->measurements as $measurement) {
            if ($measurement->isApproved() && $measurement->getRemainingNet() > 0.009) {
                $options['medicao:'.$measurement->id] = [
                    'label' => __('Measurement').' #'.$measurement->measurement_number,
                    'amount' => $measurement->getRemainingNet(),
                ];
            }
        }

        // A saved target that stopped being payable (paid elsewhere,
        // cancelled) must stay on the list, flagged — otherwise the row
        // keeps a link the user can see no trace of and cannot change.
        $saved = $this->payTargets[$contract->id] ?? '';

        if ($saved !== '' && ! isset($options[$saved])) {
            $options[$saved] = [
                'label' => $this->targetLabel($contract, $saved).' — '.__('no longer payable'),
                'amount' => 0.0,
                'stale' => true,
            ];
        }

        return $options;
    }

    protected function targetLabel(Contract $contract, string $target): string
    {
        if (str_starts_with($target, 'parcela:')) {
            $item = $contract->scheduleItems->firstWhere('id', (int) substr($target, 8));

            return $item?->description ?? __('Installment');
        }

        $measurement = $contract->measurements->firstWhere('id', (int) substr($target, 8));

        return __('Measurement').' #'.($measurement?->measurement_number ?? '?');
    }

    /**
     * Can this contract take a batch payment at all? A contract paid
     * through a cronograma with nothing approved yet cannot — the row
     * would never pass approval, so the page must say so instead of
     * offering an amount box that leads nowhere.
     */
    public function batchBlockReason(Contract $contract): ?string
    {
        if ($contract->scheduleItems->isEmpty() && $contract->measurements->isEmpty()) {
            return null;
        }

        if ($this->payableTargetsFor($contract) !== [] || $contract->getUnscheduledRemaining() > 0.009) {
            return null;
        }

        return __('No approved installment or measurement');
    }

    /**
     * Choosing a target fills in what it still owes, so the batch row
     * matches the cronograma without retyping.
     */
    public function updatedPayTargets($value, $key): void
    {
        $contract = Contract::find($key);

        if (! $contract || ! $value) {
            return;
        }

        $options = $this->payableTargetsFor($contract);

        $current = (float) ($this->payAmounts[$key] ?? 0);

        // Only fill an empty amount: a deliberate partial payment must
        // survive picking the parcela it settles.
        if (isset($options[$value]) && $current <= 0) {
            $this->payAmounts[$key] = number_format($options[$value]['amount'], 2, '.', '');
        }
    }

    /**
     * A batch row must respect the same gate as the contract page: with a
     * cronograma, the money settles a parcela or a medição, and never
     * more than that item still owes.
     */
    protected function targetError(PaymentBatchItem $item, Contract $contract): ?string
    {
        $options = $this->payableTargetsFor($contract);
        $target = match (true) {
            $item->contract_schedule_item_id !== null => 'parcela:'.$item->contract_schedule_item_id,
            $item->contract_measurement_id !== null => 'medicao:'.$item->contract_measurement_id,
            default => '',
        };

        if ($target === '') {
            if ($contract->scheduleItems->isEmpty() && $contract->measurements->isEmpty()) {
                return null;
            }

            // A cronograma that does not cover the whole contract leaves a
            // remainder that no parcela can settle — payable unlinked, up
            // to that much, exactly as on the contract page.
            $remaining = $contract->getUnscheduledRemaining();

            if ($remaining <= 0.009) {
                return __('Contract :number is paid through its schedule — choose the installment or measurement this pays.', [
                    'number' => $contract->contract_number,
                ]);
            }

            return $item->amount > $remaining + 0.009
                ? __('Contract :number: without an installment the payment cannot exceed the unscheduled balance of :balance.', [
                    'number' => $contract->contract_number,
                    'balance' => number_format($remaining, 2),
                ])
                : null;
        }

        if (! isset($options[$target]) || ($options[$target]['stale'] ?? false)) {
            return __('The installment or measurement selected for contract :number is no longer payable.', [
                'number' => $contract->contract_number,
            ]);
        }

        if ($item->amount > $options[$target]['amount'] + 0.009) {
            return __('Contract :number: the amount exceeds the :balance still owed on the selected item.', [
                'number' => $contract->contract_number,
                'balance' => number_format($options[$target]['amount'], 2),
            ]);
        }

        return null;
    }

    public function approveItem(int $itemId): void
    {
        $item = PaymentBatchItem::where('payment_batch_id', $this->paymentBatch->id)
            ->where('id', $itemId)
            ->where('status', 'pending')
            ->firstOrFail();

        if (! $item->getRawOriginal('amount') || $item->amount <= 0) {
            session()->flash('error', __('Cannot approve an item without a payment amount.'));

            return;
        }

        $contract = Contract::withSum('payments as total_paid_cents', 'amount')
            ->withSum('changeOrders as change_orders_total_cents', 'amount')
            ->find($item->contract_id);

        // The list only offers committed contracts, but the item id arrives
        // from the browser and a contract can become a draft — or be raised as
        // one by a quotation award — after the row was added. A draft owes
        // nothing yet, so it cannot be paid. Same rule as ContractPayments.
        if (! $contract || $contract->isDraft()) {
            session()->flash('error', __(':number is still a draft and cannot be paid.', [
                'number' => $contract?->contract_number ?? '',
            ]));

            return;
        }

        $changeOrdersTotal = ($contract->change_orders_total_cents ?? 0) / 100;
        $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
        $balance = round($contract->amount + $changeOrdersTotal - $totalPaidDollars, 2);

        if ($item->amount > $balance + 0.01) {
            session()->flash('error', __('Payment of $:amount for :number exceeds balance of $:balance.', [
                'amount' => $item->amount,
                'number' => $contract->contract_number,
                'balance' => number_format($balance, 2),
            ]));

            return;
        }

        if ($error = $this->targetError($item, $contract)) {
            session()->flash('error', $error);

            return;
        }

        DB::transaction(function () use ($item) {
            $this->processApprovedItem($item);
        });

        $this->updateBatchStatus();
        unset($this->batchSummary);

        // Remove from inline arrays since it's now approved
        unset($this->payAmounts[$item->contract_id]);
        unset($this->payMethods[$item->contract_id]);
        unset($this->payPhases[$item->contract_id]);
        unset($this->payNotes[$item->contract_id]);
        unset($this->payTargets[$item->contract_id]);

        session()->flash('message', __('Payment for :number approved and processed.', ['number' => $contract->contract_number]));
    }

    /**
     * Single mapping from a batch item to its ContractPayment, shared by
     * approveItem() and approveAll() so the two paths can never drift.
     */
    protected function processApprovedItem(PaymentBatchItem $item): void
    {
        ContractPayment::create([
            'contract_id' => $item->contract_id,
            'contract_schedule_item_id' => $item->contract_schedule_item_id,
            'contract_measurement_id' => $item->contract_measurement_id,
            'is_retention_release' => $item->is_retention_release,
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

        Contract::find($item->contract_id)?->updateStatusFromPayments();
    }

    public function approveAll(): void
    {
        $pendingItems = $this->paymentBatch->items()->where('status', 'pending')->get();

        if ($pendingItems->isEmpty()) {
            session()->flash('error', __('No pending items to approve.'));

            return;
        }

        // Only approve items that have an amount
        $approvableItems = $pendingItems->filter(fn ($item) => $item->getRawOriginal('amount') && $item->amount > 0);

        if ($approvableItems->isEmpty()) {
            session()->flash('error', __('No pending items with amounts to approve.'));

            return;
        }

        $errors = [];
        foreach ($approvableItems as $item) {
            $contract = Contract::withSum('payments as total_paid_cents', 'amount')
                ->withSum('changeOrders as change_orders_total_cents', 'amount')
                ->find($item->contract_id);

            if (! $contract || $contract->isDraft()) {
                $errors[] = __(':number is still a draft and cannot be paid.', [
                    'number' => $contract?->contract_number ?? '',
                ]);

                continue;
            }

            $changeOrdersTotal = ($contract->change_orders_total_cents ?? 0) / 100;
            $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
            $balance = round($contract->amount + $changeOrdersTotal - $totalPaidDollars, 2);

            if ($item->amount > $balance + 0.01) {
                $errors[] = __(':number: payment $:amount exceeds balance $:balance', [
                    'number' => $contract->contract_number,
                    'amount' => $item->amount,
                    'balance' => number_format($balance, 2),
                ]);

                continue;
            }

            if ($error = $this->targetError($item, $contract)) {
                $errors[] = $error;
            }
        }

        if (! empty($errors)) {
            session()->flash('error', __('Cannot approve all:').' '.implode('; ', $errors));

            return;
        }

        DB::transaction(function () use ($approvableItems) {
            foreach ($approvableItems as $item) {
                $this->processApprovedItem($item);
            }
        });

        $this->updateBatchStatus();
        unset($this->batchSummary);
        $this->loadExistingItems();

        session()->flash('message', __(':count payment(s) approved and processed.', ['count' => $approvableItems->count()]));
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
        unset($this->payTargets[$item->contract_id]);

        session()->flash('message', __('Item rejected.'));
    }

    public function cancelBatch(): void
    {
        $approvedCount = $this->paymentBatch->items()->where('status', 'approved')->count();

        if ($approvedCount > 0) {
            session()->flash('error', __('Cannot cancel a batch that has approved items.'));

            return;
        }

        $this->paymentBatch->update(['status' => 'cancelled']);
        unset($this->batchSummary);

        session()->flash('message', __('Batch cancelled.'));
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
        $contracts = Contract::committed()
            ->with([
            'project.client', 'jobSite', 'subcontractor', 'latestPayment',
            // payableTargetsFor() runs per row: eager-load what it needs so
            // 50 contracts don't become hundreds of queries.
            'changeOrders', 'payments',
            'scheduleItems.payments', 'scheduleItems.measurements.payments',
            'measurements.payments',
        ])
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
            ->with(['scheduleItem', 'measurement'])
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->get()
            ->keyBy('contract_id');

        return view('livewire.payment-batch.payment-batch-edit', [
            'contracts' => $contracts,
            'batchItems' => $batchItems,
        ])->layout('components.layouts.app');
    }
}
