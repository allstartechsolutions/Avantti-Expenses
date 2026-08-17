<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\ResolvesContractBudget;
use App\Models\Contract;
use App\Models\ContractScheduleItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ContractSchedule extends Component
{
    use ResolvesContractBudget;

    public Contract $contract;

    // Grid editor (Excel-like, all rows at once)
    public $showGrid = false;

    public array $rows = [];

    public array $deletedRowIds = [];

    // Approval (vistoria / liberação) modal
    public $showReleaseModal = false;

    public $releasingId = null;

    /** Trigger of the parcela being approved — drives the modal wording. */
    public $releasingTrigger = '';

    public $releaseNotes = '';

    // Change history modal
    public $showHistoryModal = false;

    #[\Livewire\Attributes\On('change-orders-updated')]
    #[\Livewire\Attributes\On('payments-updated')]
    public function refreshContract()
    {
        // Percent-based parcelas re-flow with the adjusted amount and
        // parcela status follows the payments, so change-order and
        // payment activity in the sibling cards must refresh us.
        $this->contract->refresh();
    }

    // ---------------------------------------------------------------
    // Grid editor
    // ---------------------------------------------------------------

    public function openGrid()
    {
        $this->rows = $this->contract->scheduleItems()
            ->withCount(['payments', 'measurements'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ContractScheduleItem $item) => [
                'id' => $item->id,
                'description' => $item->description,
                'trigger_type' => $item->trigger_type,
                'due_date' => $item->due_date?->format('Y-m-d') ?? '',
                'value_type' => $item->isPercentBased() ? 'percent' : 'amount',
                'percent' => $item->isPercentBased() ? number_format((float) $item->percent, 2, '.', '') : '',
                'amount' => $item->isPercentBased() ? '' : number_format($item->amount ?? 0, 2, '.', ''),
                'budget_item_id' => (string) ($item->budget_item_id ?? ''),
                'notes' => $item->notes ?? '',
                'locked' => $item->payments_count > 0 || $item->measurements_count > 0,
            ])
            ->all();

        if ($this->rows === []) {
            $this->addRow();
        }

        $this->deletedRowIds = [];
        $this->resetValidation();
        $this->showGrid = true;
    }

    public function closeGrid()
    {
        $this->showGrid = false;
        $this->rows = [];
        $this->deletedRowIds = [];
        $this->resetValidation();
    }

    public function addRow()
    {
        $this->rows[] = [
            'id' => null,
            'description' => '',
            'trigger_type' => 'milestone',
            'due_date' => '',
            'value_type' => 'amount',
            'percent' => '',
            'amount' => '',
            'budget_item_id' => '',
            'notes' => '',
            'locked' => false,
        ];
    }

    public function removeRow(int $index)
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        if ($this->rows[$index]['locked']) {
            return;
        }

        if ($this->rows[$index]['id']) {
            $this->deletedRowIds[] = $this->rows[$index]['id'];
        }

        array_splice($this->rows, $index, 1);
        $this->resetValidation();
    }

    public function moveRow(int $index, string $direction)
    {
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($this->rows[$index], $this->rows[$target])) {
            return;
        }

        [$this->rows[$index], $this->rows[$target]] = [$this->rows[$target], $this->rows[$index]];
        $this->resetValidation();
    }

    /**
     * Live totals for the grid footer, recomputed on every keystroke.
     */
    public function getGridTotalsProperty(): array
    {
        $adjusted = $this->contract->getAdjustedAmount();
        $total = 0.0;

        foreach ($this->rows as $row) {
            $total += $this->rowScheduledAmount($row, $adjusted);
        }

        $total = round($total, 2);

        return [
            'scheduled' => $total,
            'adjusted' => $adjusted,
            'unscheduled' => round($adjusted - $total, 2),
            'percent' => $adjusted > 0 ? round($total / $adjusted * 100, 2) : 0,
        ];
    }

    public function rowScheduledAmount(array $row, float $adjusted): float
    {
        if ($row['value_type'] === 'percent') {
            return round($adjusted * (float) ($row['percent'] ?: 0) / 100, 2);
        }

        return round((float) ($row['amount'] ?: 0), 2);
    }

    public function saveGrid()
    {
        $budget = $this->locationBudget();

        $this->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.description' => ['required', 'string', 'max:255'],
            'rows.*.trigger_type' => ['required', 'in:date,milestone'],
            'rows.*.due_date' => ['nullable', 'date'],
            'rows.*.value_type' => ['required', 'in:amount,percent'],
            'rows.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'rows.*.percent' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'rows.*.budget_item_id' => [
                'nullable',
                Rule::exists('budget_items', 'id')->where('budget_id', $budget?->id ?? 0),
            ],
            'rows.*.notes' => ['nullable', 'string'],
        ], [
            'rows.*.description.required' => __('Description is required.'),
            'rows.*.amount.min' => __('An amount is required.'),
            'rows.*.percent.min' => __('A percent is required.'),
            'rows.*.percent.max' => __('% complete cannot exceed 100.'),
        ]);

        // Cross-field rules the validator cannot express per row.
        $hasRowErrors = false;
        foreach ($this->rows as $i => $row) {
            if ($row['trigger_type'] === 'date' && empty($row['due_date'])) {
                $this->addError("rows.{$i}.due_date", __('A due date is required for date-based installments.'));
                $hasRowErrors = true;
            }
            if ($row['value_type'] === 'amount' && (float) ($row['amount'] ?: 0) <= 0) {
                $this->addError("rows.{$i}.amount", __('An amount is required.'));
                $hasRowErrors = true;
            }
            if ($row['value_type'] === 'percent' && (float) ($row['percent'] ?: 0) <= 0) {
                $this->addError("rows.{$i}.percent", __('A percent is required.'));
                $hasRowErrors = true;
            }
        }

        if ($hasRowErrors) {
            return;
        }

        $lockedValueError = false;
        $refusedDeleteError = false;

        DB::transaction(function () use (&$lockedValueError, &$refusedDeleteError) {
            // Deletions first (only rows the UI allowed to be removed).
            // A row that gained payments/medições after the grid opened
            // survives, renumbered after the kept rows so sort_order
            // never collides.
            $survivorOrder = count($this->rows);

            if ($this->deletedRowIds !== []) {
                $items = ContractScheduleItem::where('contract_id', $this->contract->id)
                    ->whereIn('id', $this->deletedRowIds)
                    ->get();

                foreach ($items as $item) {
                    if ($item->payments()->exists() || $item->measurements()->exists()) {
                        $refusedDeleteError = true;
                        $item->update(['sort_order' => ++$survivorOrder]);

                        continue;
                    }
                    $item->delete();
                }
            }

            foreach ($this->rows as $index => $row) {
                $data = [
                    'sort_order' => $index + 1,
                    'description' => $row['description'],
                    'trigger_type' => $row['trigger_type'],
                    'due_date' => $row['due_date'] ?: null,
                    'amount' => $row['value_type'] === 'amount' ? ($row['amount'] ?: null) : null,
                    'percent' => $row['value_type'] === 'percent' ? ($row['percent'] ?: null) : null,
                    'budget_item_id' => $row['budget_item_id'] ?: null,
                    'notes' => $row['notes'] ?: null,
                ];

                if ($row['id']) {
                    $item = ContractScheduleItem::where('contract_id', $this->contract->id)->find($row['id']);

                    if (! $item) {
                        continue;
                    }

                    // Value and trigger of a parcela with linked money stay
                    // locked; the UI disables these cells, this re-checks
                    // (and the model guard backstops any other path).
                    if (($item->payments()->exists() || $item->measurements()->exists())
                        && $this->valueOrTriggerChanged($item, $data)) {
                        unset($data['amount'], $data['percent'], $data['trigger_type']);
                        $lockedValueError = true;
                    }

                    $item->update($data);
                } else {
                    ContractScheduleItem::create($data + ['contract_id' => $this->contract->id]);
                }
            }
        });

        $this->closeGrid();
        $this->contract->refresh();

        if ($refusedDeleteError) {
            session()->flash('error', __('Some installments could not be deleted because they have payments or measurements linked.'));
        } elseif ($lockedValueError) {
            session()->flash('error', __('Some locked installments kept their original value and trigger — they have payments or measurements linked.'));
        } else {
            session()->flash('message', __('Schedule saved successfully.'));
        }

        $this->dispatch('schedule-updated');
    }

    protected function valueOrTriggerChanged(ContractScheduleItem $item, array $data): bool
    {
        $oldPercent = $item->isPercentBased() ? round((float) $item->percent, 2) : null;
        $newPercent = $data['percent'] !== null && $data['percent'] !== '' ? round((float) $data['percent'], 2) : null;
        $oldAmount = $item->isPercentBased() ? null : round((float) $item->amount, 2);
        $newAmount = $data['amount'] !== null && $data['amount'] !== '' ? round((float) $data['amount'], 2) : null;

        return $data['trigger_type'] !== $item->trigger_type
            || $oldPercent !== $newPercent
            || $oldAmount !== $newAmount;
    }

    // ---------------------------------------------------------------
    // Vistoria release
    // ---------------------------------------------------------------

    public function openReleaseModal($id)
    {
        $item = ContractScheduleItem::where('contract_id', $this->contract->id)->findOrFail($id);

        if ($item->isReleased()) {
            return;
        }

        $this->releasingId = $item->id;
        $this->releasingTrigger = $item->trigger_type;
        $this->releaseNotes = '';
        $this->showReleaseModal = true;
    }

    public function closeReleaseModal()
    {
        $this->showReleaseModal = false;
        $this->releasingId = null;
        $this->releasingTrigger = '';
        $this->releaseNotes = '';
    }

    public function release()
    {
        $this->validate(['releaseNotes' => ['nullable', 'string']]);

        $item = $this->releasingId
            ? ContractScheduleItem::where('contract_id', $this->contract->id)->find($this->releasingId)
            : null;

        // Friendly exit for the races: modal already closed, item gone,
        // or another user released it first.
        if (! $item || $item->isReleased()) {
            $this->closeReleaseModal();
            $this->contract->refresh();
            session()->flash('error', __('This installment could not be released — it may already have been released.'));
            $this->dispatch('schedule-updated');

            return;
        }

        $isMilestone = $item->trigger_type === 'milestone';

        try {
            $item->release(Auth::user(), $this->releaseNotes ?: null);
        } catch (\LogicException) {
            // Lost the race between the check above and this write.
            $this->closeReleaseModal();
            $this->contract->refresh();
            session()->flash('error', __('This installment could not be released — it may already have been released.'));
            $this->dispatch('schedule-updated');

            return;
        }

        $this->closeReleaseModal();
        $this->contract->refresh();
        session()->flash('message', $isMilestone
            ? __('Stage confirmed as completed — installment released for payment.')
            : __('Installment approved and released for payment.'));
        $this->dispatch('schedule-updated');
    }

    /**
     * Undo a mistaken approval. Allowed only while nothing is settled
     * against the parcela — with a payment or a medição linked the
     * payment has to be removed first.
     */
    public function revertRelease($id)
    {
        $item = ContractScheduleItem::where('contract_id', $this->contract->id)->find($id);

        if (! $item || ! $item->isReleased()) {
            $this->contract->refresh();
            session()->flash('error', __('This approval could not be reverted — it may already have been reverted.'));
            $this->dispatch('schedule-updated');

            return;
        }

        if ($item->payments()->exists() || $item->measurements()->exists()) {
            $this->contract->refresh();
            session()->flash('error', __('This installment already has payments or measurements linked — remove them before reverting the approval.'));
            $this->dispatch('schedule-updated');

            return;
        }

        try {
            $item->revertRelease();
        } catch (\LogicException) {
            // Lost the race: someone paid or reverted it in between.
            $this->contract->refresh();
            session()->flash('error', __('This approval could not be reverted — it may already have been reverted.'));
            $this->dispatch('schedule-updated');

            return;
        }

        $this->contract->refresh();
        session()->flash('message', __('Approval reverted — the installment is pending again.'));
        $this->dispatch('schedule-updated');
    }

    // ---------------------------------------------------------------
    // Change history
    // ---------------------------------------------------------------

    public function openHistoryModal()
    {
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal()
    {
        $this->showHistoryModal = false;
    }

    public function render()
    {
        $this->contract->loadMissing('changeOrders');

        $items = $this->contract->scheduleItems()
            ->with(['budgetItem', 'releasedBy', 'payments', 'measurements.payments'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Share the loaded items and this contract instance so the
        // totals below and percent-based rows reuse loaded relations.
        $this->contract->setRelation('scheduleItems', $items);

        $scheduledTotal = $this->contract->getScheduledTotal();

        return view('livewire.contract.contract-schedule', [
            'items' => $items,
            'budgetItems' => $this->showGrid ? $this->budgetItemOptions() : collect(),
            'scheduledTotal' => $scheduledTotal,
            'adjustedAmount' => $this->contract->getAdjustedAmount(),
            'unscheduledAmount' => $this->contract->getUnscheduledAmount(),
            'history' => $this->showHistoryModal
                ? $this->contract->scheduleChanges()->with('changedBy')->limit(100)->get()
                : collect(),
        ]);
    }
}
