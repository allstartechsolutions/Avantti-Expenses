<?php

namespace App\Livewire\Contract;

use App\Models\Contract;
use App\Models\ContractMeasurement;
use App\Models\ContractPayment;
use App\Models\ContractPaymentItem;
use App\Models\ContractScheduleItem;
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
     * Optional cronograma parcela this payment settles. Empty means an
     * unlinked payment (the pre-cronograma behaviour, still valid).
     */
    public $paymentScheduleItemId = '';

    /** Approved medição this payment settles (Regime B). */
    public $paymentMeasurementId = '';

    /** Cost-code line pre-filled by the parcela choice, so it can be undone. */
    public $autoFilledItemIndex = null;

    /**
     * Indexes of the cost-code lines a medição filled in. Only these may
     * be cleared or re-split automatically; anything the user typed is
     * theirs. Tracking the indexes (not a bare flag) is what keeps that
     * promise when only some lines came from the boletim.
     */
    public array $measurementFilledIndexes = [];

    // Retention release modal (liberação de retenção)
    public $showRetentionModal = false;

    public $retentionAmount = '';

    public $retentionMethod = 'check';

    public $retentionDate = '';

    public $retentionReference = '';

    public $retentionNotes = '';

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
            'payments.scheduleItem',
            'changeOrders.createdBy',
        ]);
    }

    public function getAvailableStatusesProperty(): array
    {
        return match ($this->contract->status) {
            'draft' => ['active' => __('Active'), 'cancelled' => __('Cancelled')],
            'active' => ['completed' => __('Completed'), 'cancelled' => __('Cancelled')],
            'completed' => ['paid' => __('Paid'), 'partially_paid' => __('Partially Paid')],
            'partially_paid' => ['paid' => __('Paid')],
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

        if (! in_array($this->newStatus, $allowed)) {
            session()->flash('error', __('Invalid status transition.'));
            $this->closeStatusModal();

            return;
        }

        $oldStatus = $this->contract->status;
        $this->contract->update(['status' => $this->newStatus]);
        $this->contract->recordStatusChange(Auth::user(), $oldStatus, $this->newStatus, $this->statusReason ?: null);

        $this->refreshContract();
        $this->closeStatusModal();
        session()->flash('message', __('Contract status updated successfully.'));
    }

    /**
     * A contract with a cronograma is paid through it: every payment
     * must settle a parcela. Contracts without one keep the direct
     * payment behaviour.
     */
    public function getHasScheduleProperty(): bool
    {
        return $this->contract->scheduleItems()->exists();
    }

    /**
     * Contract money the cronograma does not cover and that has not been
     * paid off-schedule yet — a partial cronograma, or a change order
     * raising the adjusted amount after the parcelas were agreed. This
     * much may still be paid without a parcela, otherwise it would be
     * impossible to pay at all.
     */
    public function getUnscheduledRemainingProperty(): float
    {
        return $this->contract->getUnscheduledRemaining();
    }

    /**
     * Parcelas this payment may settle: still owing, and approved —
     * the vistoria for an evento, the liberação for a date parcela, or
     * an approved medição. Nothing is payable before its approval.
     */
    public function getPayableScheduleItemsProperty()
    {
        $items = $this->contract->scheduleItems()
            ->with(['budgetItem', 'payments', 'measurements.payments'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Share this contract instance (change orders already loaded) so
        // percent-based parcelas don't lazy-load a Contract per row.
        $items->each(fn (ContractScheduleItem $item) => $item->setRelation('contract', $this->contract));

        return $items
            ->filter(fn (ContractScheduleItem $item) => $item->getBalance() > 0.009
                && $item->isDue()
                // A parcela measured by a medição is paid through that
                // medição — offering both would let the same work be paid
                // twice.
                && ! $item->measurements->contains(
                    fn (ContractMeasurement $measurement) => $measurement->isApproved()
                        && $measurement->getRemainingNet() > 0.009
                ))
            ->values();
    }

    /**
     * Picking a parcela pre-fills its outstanding balance (never more
     * than the contract still owes) and, when the parcela is scoped to a
     * cost code and no line has been touched yet, that code's line.
     */
    public function updatedPaymentScheduleItemId($value)
    {
        // Whatever the previous choice pre-filled is not the user's own
        // input, so it must not survive into the new selection (it would
        // then clash with the "lines must add up" rule).
        $this->clearAutoFilledPaymentItem();

        if ($value !== '' && $value !== null) {
            $this->paymentMeasurementId = '';
            $this->clearMeasurementFilledItems();
        }

        if ($value === '' || $value === null) {
            $this->paymentAmount = $this->hasSchedule
                ? number_format($this->unscheduledRemaining, 2, '.', '')
                : number_format($this->contract->getBalanceDue(), 2, '.', '');

            return;
        }

        $item = $this->payableScheduleItems->firstWhere('id', (int) $value);

        if (! $item) {
            return;
        }

        $suggested = round(min($item->getBalance(), $this->contract->getBalanceDue()), 2);

        if ($suggested <= 0) {
            return;
        }

        $this->paymentAmount = number_format($suggested, 2, '.', '');

        if ($item->budget_item_id === null || $this->activePaymentItems() !== []) {
            return;
        }

        foreach ($this->paymentItems as $index => $row) {
            if ($row['budget_item_id'] === $item->budget_item_id) {
                $this->paymentItems[$index]['amount'] = number_format($suggested, 2, '.', '');
                $this->autoFilledItemIndex = $index;
                break;
            }
        }
    }

    /**
     * Approved medições still owing net cash. A fully retained medição
     * (net zero) is settled at approval and never appears here.
     */
    public function getPayableMeasurementsProperty()
    {
        return $this->contract->measurements()
            ->with(['items.budgetItem', 'payments'])
            ->where('status', 'approved')
            // reorder(): the relation itself sorts newest-first, and a
            // second ORDER BY term would never be reached.
            ->reorder('measurement_number', 'asc')
            ->get()
            ->filter(fn (ContractMeasurement $measurement) => $measurement->getRemainingNet() > 0.009)
            ->values();
    }

    /**
     * Paying a medição pays its net: the amount is pre-filled with what
     * it still owes and the cost-code lines are filled from the boletim,
     * so the money lands on the codes that were measured.
     */
    public function updatedPaymentMeasurementId($value)
    {
        $this->clearAutoFilledPaymentItem();
        $this->clearMeasurementFilledItems();

        // The boletim defines every line of a medição payment, so a line
        // typed by hand cannot survive alongside it — drop it with a note
        // rather than leaving totals that silently fail validation.
        if ($value !== '' && $value !== null && ($typed = $this->userTypedItemIndexes()) !== []) {
            foreach ($typed as $index) {
                $this->paymentItems[$index]['amount'] = '';
                $this->paymentItems[$index]['percent'] = '';
            }

            session()->flash('error', __('The cost code lines were replaced by the measurement boletim.'));
        }

        if ($value === '' || $value === null) {
            $this->paymentAmount = $this->hasSchedule
                ? number_format($this->unscheduledRemaining, 2, '.', '')
                : number_format($this->contract->getBalanceDue(), 2, '.', '');

            return;
        }

        $measurement = $this->payableMeasurements->firstWhere('id', (int) $value);

        if (! $measurement) {
            return;
        }

        // A medição is settled through itself, never through a parcela as
        // well — the cronograma reads it via the medição.
        $this->paymentScheduleItemId = '';
        $this->paymentAmount = number_format($measurement->getRemainingNet(), 2, '.', '');

        $this->fillItemsFromMeasurement($measurement, $measurement->getRemainingNet());
    }

    /**
     * Split a payment across the boletim's cost codes, proportionally to
     * what each measured this period (floor + largest remainder so the
     * lines add up to the payment to the cent). The % complete carried to
     * each line is the medição's own — it reports executed work, which a
     * partial payment does not change.
     */
    protected function fillItemsFromMeasurement(ContractMeasurement $measurement, float $amount): void
    {
        $amountCents = (int) round($amount * 100);
        $weights = [];

        foreach ($measurement->items as $item) {
            $cents = (int) $item->getRawOriginal('period_amount');
            if ($cents > 0 && $item->budget_item_id !== null) {
                $weights[$item->budget_item_id] = $cents;
            }
        }

        $total = array_sum($weights);

        if ($amountCents <= 0 || $total <= 0) {
            return;
        }

        $allocated = [];
        $remainders = [];

        foreach ($weights as $budgetItemId => $cents) {
            $exact = $amountCents * $cents / $total;
            $allocated[$budgetItemId] = (int) floor($exact);
            $remainders[$budgetItemId] = $exact - floor($exact);
        }

        arsort($remainders);
        $left = $amountCents - array_sum($allocated);

        foreach (array_keys($remainders) as $budgetItemId) {
            if ($left <= 0) {
                break;
            }
            $allocated[$budgetItemId]++;
            $left--;
        }

        $percents = $measurement->items
            ->whereNotNull('budget_item_id')
            ->mapWithKeys(fn ($item) => [(int) $item->budget_item_id => (float) $item->current_percent]);

        foreach ($this->paymentItems as $index => $row) {
            $budgetItemId = (int) $row['budget_item_id'];

            if (! isset($allocated[$budgetItemId]) || $allocated[$budgetItemId] <= 0) {
                continue;
            }

            $this->paymentItems[$index]['amount'] = number_format($allocated[$budgetItemId] / 100, 2, '.', '');
            $this->measurementFilledIndexes[] = $index;

            if (isset($percents[$budgetItemId])) {
                $this->paymentItems[$index]['percent'] = number_format($percents[$budgetItemId], 2, '.', '');
            }
        }

    }

    /**
     * Editing the amount of a medição payment re-splits its lines, so a
     * partial payment still satisfies the "lines must add up" rule.
     */
    public function updatedPaymentAmount($value): void
    {
        if ($this->paymentMeasurementId === '' || $this->paymentMeasurementId === null) {
            return;
        }

        $measurement = $this->payableMeasurements->firstWhere('id', (int) $this->paymentMeasurementId);

        // Hands off once the user has edited a line themselves.
        if (! $measurement || $this->measurementFilledIndexes === []) {
            return;
        }

        $this->clearMeasurementFilledItems();
        $this->fillItemsFromMeasurement($measurement, round((float) $value, 2));
    }

    /**
     * Clear only lines a medição filled in; hand-typed lines are left
     * alone (clearing them silently would break the "lines must add up"
     * rule with nothing on screen to explain it).
     */
    protected function clearMeasurementFilledItems(): void
    {
        foreach ($this->measurementFilledIndexes as $index) {
            if (isset($this->paymentItems[$index])) {
                $this->paymentItems[$index]['amount'] = '';
                $this->paymentItems[$index]['percent'] = '';
            }
        }

        $this->measurementFilledIndexes = [];
    }

    /**
     * Lines the user typed that a medição did not fill. Choosing a
     * medição replaces the whole grid with its boletim, so these have to
     * go — but never silently.
     */
    protected function userTypedItemIndexes(): array
    {
        $indexes = [];

        foreach ($this->paymentItems as $index => $row) {
            $touched = (float) ($row['amount'] ?: 0) > 0 || ($row['percent'] ?? '') !== '';

            if ($touched && ! in_array($index, $this->measurementFilledIndexes, true)) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    protected function clearAutoFilledPaymentItem(): void
    {
        if ($this->autoFilledItemIndex !== null && isset($this->paymentItems[$this->autoFilledItemIndex])) {
            $this->paymentItems[$this->autoFilledItemIndex]['amount'] = '';
        }

        $this->autoFilledItemIndex = null;
    }

    #[\Livewire\Attributes\On('pay-measurement')]
    public function openPaymentModalForMeasurement($measurementId)
    {
        $this->openPaymentModal();

        if ($this->payableMeasurements->firstWhere('id', (int) $measurementId)) {
            $this->paymentMeasurementId = (string) $measurementId;
            $this->updatedPaymentMeasurementId($this->paymentMeasurementId);
        }
    }

    public function openPaymentModal()
    {
        // With a cronograma the amount comes from the parcela the user
        // picks — or from the unscheduled balance, which is what the
        // empty choice pays; without one it defaults to the balance due.
        $this->paymentAmount = $this->hasSchedule
            ? ($this->unscheduledRemaining > 0 ? number_format($this->unscheduledRemaining, 2, '.', '') : '')
            : number_format($this->contract->getBalanceDue(), 2, '.', '');
        $this->paymentMethod = 'check';
        $this->paymentDate = now()->format('Y-m-d');
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->paymentScheduleItemId = '';
        $this->paymentMeasurementId = '';
        $this->autoFilledItemIndex = null;
        $this->measurementFilledIndexes = [];

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
        $this->paymentScheduleItemId = '';
        $this->paymentMeasurementId = '';
        $this->autoFilledItemIndex = null;
        $this->measurementFilledIndexes = [];
        $this->paymentItems = [];
    }

    public function updatedPaymentItems($value, $key)
    {
        [$index, $field] = explode('.', $key, 2);

        // Once the user touches a line it is their input, not ours to
        // clear or re-split.
        if ((int) $index === $this->autoFilledItemIndex) {
            $this->autoFilledItemIndex = null;
        }

        // A line the user edits stops being ours to clear or re-split.
        $this->measurementFilledIndexes = array_values(
            array_diff($this->measurementFilledIndexes, [(int) $index])
        );

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
            'paymentAmount' => ['required', 'numeric', 'min:0.01', 'max:'.$balanceDue],
            'paymentDate' => ['required', 'date'],
            'paymentMethod' => ['required', 'in:cash,check,credit_card,debit_card,bank_transfer,pix,other'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'paymentNotes' => ['nullable', 'string'],
            'paymentScheduleItemId' => [$this->hasSchedule && $this->unscheduledRemaining <= 0 && $this->paymentMeasurementId === '' ? 'required' : 'nullable'],
            'paymentMeasurementId' => ['nullable'],
            'paymentItems.*.amount' => ['nullable', 'numeric', 'min:0'],
            'paymentItems.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'paymentAmount.max' => __('Payment amount cannot exceed the balance due of :balance.', [
                'balance' => Number::currency($balanceDue, config('app.currency'), config('app.locale')),
            ]),
            'paymentItems.*.percent.max' => __('% complete cannot exceed 100.'),
            'paymentScheduleItemId.required' => __('Select the installment this payment settles.'),
        ]);

        $scheduleItem = null;
        $measurement = null;

        if ($this->paymentMeasurementId !== '' && $this->paymentMeasurementId !== null) {
            // Re-checked live: the medição may have been paid or cancelled
            // while the modal was open.
            $measurement = $this->payableMeasurements->firstWhere('id', (int) $this->paymentMeasurementId);

            if (! $measurement) {
                $this->addError('paymentMeasurementId', __('This measurement is no longer payable — reopen the payment form.'));

                return;
            }

            $remainingNet = $measurement->getRemainingNet();

            if ((float) $this->paymentAmount > $remainingNet + 0.009) {
                $this->addError('paymentAmount', __('The amount cannot exceed the measurement net of :balance.', [
                    'balance' => Number::currency($remainingNet, config('app.currency'), config('app.locale')),
                ]));

                return;
            }

            // The cronograma reads a medição payment through the medição
            // itself, so the parcela link stays empty.
            $this->paymentScheduleItemId = '';
        } elseif ($this->paymentScheduleItemId !== '' && $this->paymentScheduleItemId !== null) {
            // Re-checked against the live list: the parcela may have been
            // paid, edited or deleted while the modal was open.
            $scheduleItem = $this->payableScheduleItems->firstWhere('id', (int) $this->paymentScheduleItemId);

            if (! $scheduleItem) {
                $this->addError('paymentScheduleItemId', __('This installment is no longer payable — reopen the payment form.'));

                return;
            }

            if ((float) $this->paymentAmount > $scheduleItem->getBalance() + 0.009) {
                $this->addError('paymentAmount', __('The amount cannot exceed the installment balance of :balance.', [
                    'balance' => Number::currency($scheduleItem->getBalance(), config('app.currency'), config('app.locale')),
                ]));

                return;
            }
        } elseif ($this->hasSchedule) {
            // No parcela and no medição: only the part of the contract the
            // cronograma does not cover may be paid this way.
            $remaining = $this->unscheduledRemaining;

            if ((float) $this->paymentAmount > $remaining + 0.009) {
                $this->addError('paymentAmount', __('Without an installment the payment cannot exceed the unscheduled balance of :balance.', [
                    'balance' => Number::currency($remaining, config('app.currency'), config('app.locale')),
                ]));

                return;
            }
        }

        $activeItems = $this->activePaymentItems();

        if ($activeItems !== []) {
            $itemsTotal = round(collect($activeItems)->sum(fn ($row) => (float) ($row['amount'] ?: 0)), 2);
            if (abs($itemsTotal - (float) $this->paymentAmount) > 0.009) {
                $this->addError('paymentItems', __('The cost code lines must add up to the payment amount.'));

                return;
            }
        }

        DB::transaction(function () use ($activeItems, $scheduleItem, $measurement) {
            $payment = ContractPayment::create([
                'contract_id' => $this->contract->id,
                'contract_schedule_item_id' => $scheduleItem?->id,
                'contract_measurement_id' => $measurement?->id,
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
        session()->flash('message', __('Payment recorded successfully.'));
        $this->dispatch('payments-updated');
    }

    // ---------------------------------------------------------------
    // Liberação de retenção
    // ---------------------------------------------------------------

    public function openRetentionModal()
    {
        $outstanding = $this->contract->getRetentionOutstanding();

        if ($outstanding <= 0) {
            session()->flash('error', __('There is no retention outstanding to release.'));

            return;
        }

        $this->retentionAmount = number_format($outstanding, 2, '.', '');
        $this->retentionMethod = 'check';
        $this->retentionDate = now()->format('Y-m-d');
        $this->retentionReference = '';
        $this->retentionNotes = '';
        $this->showRetentionModal = true;
    }

    public function closeRetentionModal()
    {
        $this->showRetentionModal = false;
        $this->retentionAmount = '';
        $this->retentionMethod = 'check';
        $this->retentionDate = '';
        $this->retentionReference = '';
        $this->retentionNotes = '';
    }

    /**
     * Give retention back to the vendor. The amount is capped at what is
     * actually outstanding, recomputed inside the transaction with the
     * contract row locked, so two concurrent liberações can never hand
     * back more than was withheld.
     */
    public function releaseRetention()
    {
        $this->validate([
            'retentionAmount' => ['required', 'numeric', 'min:0.01'],
            'retentionDate' => ['required', 'date'],
            'retentionMethod' => ['required', 'in:cash,check,credit_card,debit_card,bank_transfer,pix,other'],
            'retentionReference' => ['nullable', 'string', 'max:255'],
            'retentionNotes' => ['nullable', 'string'],
        ]);

        $outstandingNow = $this->contract->getRetentionOutstanding();

        if ((float) $this->retentionAmount > $outstandingNow + 0.009) {
            $this->addError('retentionAmount', __('The amount cannot exceed the retention outstanding of :balance.', [
                'balance' => Number::currency($outstandingNow, config('app.currency'), config('app.locale')),
            ]));

            return;
        }

        $released = null;
        $requested = round((float) $this->retentionAmount, 2);

        DB::transaction(function () use (&$released, $requested) {
            $contract = Contract::whereKey($this->contract->id)->lockForUpdate()->firstOrFail();
            $contract->load(['measurements.items', 'measurements.payments', 'payments.items']);

            $outstanding = $contract->getRetentionOutstanding();

            if ($outstanding <= 0) {
                return;
            }

            $released = min($requested, $outstanding);

            $payment = ContractPayment::create([
                'contract_id' => $contract->id,
                'is_retention_release' => true,
                'amount' => $released,
                'payment_date' => $this->retentionDate,
                'payment_method' => $this->retentionMethod,
                'reference_number' => $this->retentionReference ?: null,
                'notes' => $this->retentionNotes ?: null,
                'created_by' => Auth::id(),
            ]);

            foreach ($this->allocateRetention((int) round($released * 100), $contract->getRetentionOutstandingByCostCode()) as $budgetItemId => $cents) {
                $payment->items()->create([
                    'budget_item_id' => $budgetItemId,
                    'amount' => round($cents / 100, 2),
                ]);
            }
        });

        if ($released === null) {
            session()->flash('error', __('There is no retention outstanding to release.'));
            $this->closeRetentionModal();
            $this->refreshContract();

            return;
        }

        $this->contract->updateStatusFromPayments();
        $this->refreshContract();
        $this->closeRetentionModal();

        $amount = Number::currency($released, config('app.currency'), config('app.locale'));

        // The lock-time cap only bites when the outstanding dropped after
        // the form was filled — say so rather than reporting the smaller
        // amount as if it were what was asked for.
        if ($released < $requested - 0.009) {
            session()->flash('error', __('Only :amount was still retained, so that is what was released.', ['amount' => $amount]));
        } else {
            session()->flash('message', __('Retention released: :amount.', ['amount' => $amount]));
        }

        $this->dispatch('payments-updated');
    }

    /**
     * Split a retention release across the cost codes that still hold
     * retention, proportionally to what each holds. Floor + largest
     * remainder so the lines add up to the payment to the cent and no
     * code ever gets back more than it holds.
     *
     * @param  array<int, int>  $outstandingByCode
     * @return array<int, int>
     */
    protected function allocateRetention(int $amountCents, array $outstandingByCode): array
    {
        $total = array_sum($outstandingByCode);

        if ($amountCents <= 0 || $total <= 0) {
            return [];
        }

        $amountCents = min($amountCents, $total);
        $allocated = [];
        $remainders = [];

        foreach ($outstandingByCode as $budgetItemId => $cents) {
            $exact = $amountCents * $cents / $total;
            $allocated[$budgetItemId] = (int) floor($exact);
            $remainders[$budgetItemId] = $exact - floor($exact);
        }

        arsort($remainders);
        $left = $amountCents - array_sum($allocated);

        foreach (array_keys($remainders) as $budgetItemId) {
            if ($left <= 0) {
                break;
            }

            if ($allocated[$budgetItemId] < $outstandingByCode[$budgetItemId]) {
                $allocated[$budgetItemId]++;
                $left--;
            }
        }

        return array_filter($allocated, fn (int $cents) => $cents > 0);
    }

    public function deletePayment($id)
    {
        $payment = ContractPayment::where('contract_id', $this->contract->id)->findOrFail($id);
        $payment->delete();

        $this->contract->updateStatusFromPayments();
        $this->refreshContract();
        session()->flash('message', __('Payment deleted successfully.'));
        $this->dispatch('payments-updated');
    }

    #[\Livewire\Attributes\On('change-orders-updated')]
    #[\Livewire\Attributes\On('schedule-updated')]
    #[\Livewire\Attributes\On('measurements-updated')]
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
            'payments.scheduleItem',
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

        session()->flash('message', __('Contract deleted successfully.'));

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
