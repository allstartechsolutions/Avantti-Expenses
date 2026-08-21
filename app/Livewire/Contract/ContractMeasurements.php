<?php

namespace App\Livewire\Contract;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Contract;
use App\Models\ContractMeasurement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Medição por produção (Regime B): the boletim de medição measures how
 * much of each cost code was executed in a period, and its value — minus
 * the contract's retention — is what becomes payable.
 *
 * Percentages are cumulative (% anterior → % atual) and the period value
 * is derived from them, never typed: valor = (% atual − % anterior) ×
 * previsto. A medição can therefore never take a cost code backwards
 * (period_amount is unsigned), which the validation enforces explicitly.
 */
class ContractMeasurements extends Component
{
    use AuthorizesAbility;

    public Contract $contract;

    // Boletim editor (full screen, one row per cost code)
    public $showEditor = false;

    public $editingId = null;

    public $editingNumber = null;

    public $readOnly = false;

    public array $rows = [];

    public $periodStart = '';

    public $periodEnd = '';

    public $notes = '';

    /**
     * Optional cronograma parcela this medição measures. Approving a
     * linked medição makes that parcela payable, and the medição's
     * payments settle it — the money reaches the cronograma through the
     * medição, never twice.
     */
    public $scheduleItemId = '';

    #[\Livewire\Attributes\On('change-orders-updated')]
    #[\Livewire\Attributes\On('payments-updated')]
    #[\Livewire\Attributes\On('schedule-updated')]
    public function refreshContract()
    {
        $this->contract->refresh();
    }

    // ---------------------------------------------------------------
    // Draft lifecycle
    // ---------------------------------------------------------------

    /**
     * Start a medição. Only one draft may be open per contract — a second
     * click reopens the existing draft instead of creating a rival one.
     */
    public function createDraft()
    {
        $this->authorizeAbility('contracts.measure', $this->contract);

        $existing = $this->contract->measurements()->where('status', 'draft')->first();

        if ($existing) {
            session()->flash('error', __('This contract already has an open measurement — finish or delete it first.'));
            $this->openEditor($existing->id);

            return;
        }

        $previous = $this->lastApprovedMeasurement();

        // Continue the day after the last approved period; when that
        // period already runs to today (or beyond), the new one starts and
        // ends there — never start > end, which no one could save.
        $periodStart = $previous?->period_end?->copy()->addDay()
            ?? $this->contract->start_date->copy();
        $periodEnd = $periodStart->gt(today()) ? $periodStart->copy() : today();

        $measurement = DB::transaction(function () use ($previous, $periodStart, $periodEnd) {
            $measurement = ContractMeasurement::createNumbered([
                'contract_id' => $this->contract->id,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'created_by' => Auth::id(),
            ]);

            foreach ($this->costCodeRows($previous) as $row) {
                $measurement->items()->create([
                    'budget_item_id' => $row['budget_item_id'],
                    'scheduled_amount' => $row['scheduled'],
                    'previous_percent' => $row['previous_percent'],
                    'current_percent' => $row['previous_percent'],
                    'period_amount' => 0,
                ]);
            }

            return $measurement;
        });

        $this->openEditor($measurement->id);
    }

    /**
     * One row per cost code of the contract's schedule of values, carrying
     * the % already measured: from the last approved medição when there is
     * one, otherwise from the payment history (so contracts that were being
     * paid before medições existed continue from where they are).
     */
    protected function costCodeRows(?ContractMeasurement $previous): array
    {
        $previousPercents = $previous
            ? $previous->items->mapWithKeys(fn ($item) => [(int) $item->budget_item_id => (float) $item->current_percent])
            : collect();

        return $this->contract->costCodeSchedule()
            ->map(fn (array $row) => [
                'budget_item_id' => $row['budget_item_id'],
                'code_display' => $row['code_display'],
                'scheduled' => $row['scheduled'],
                'previous_percent' => $previous
                    ? ($previousPercents[(int) $row['budget_item_id']] ?? 0)
                    : (float) ($row['percent_complete'] ?? 0),
            ])
            ->values()
            ->all();
    }

    protected function lastApprovedMeasurement(): ?ContractMeasurement
    {
        return $this->contract->measurements()
            ->with('items')
            ->where('status', 'approved')
            ->orderByDesc('measurement_number')
            ->first();
    }

    // ---------------------------------------------------------------
    // Boletim editor
    // ---------------------------------------------------------------

    public function openEditor($id)
    {
        $this->authorizeAbility('contracts.measure', $this->contract);

        $measurement = $this->findMeasurement($id);

        if (! $measurement) {
            return;
        }

        $this->editingId = $measurement->id;
        $this->editingNumber = $measurement->measurement_number;
        $this->readOnly = ! $measurement->isDraft();
        $this->periodStart = $measurement->period_start->format('Y-m-d');
        $this->periodEnd = $measurement->period_end->format('Y-m-d');
        $this->notes = $measurement->notes ?? '';
        $this->scheduleItemId = (string) ($measurement->contract_schedule_item_id ?? '');

        $this->rows = $measurement->items->map(fn ($item) => [
            'id' => $item->id,
            'budget_item_id' => $item->budget_item_id,
            'code_display' => $item->cost_code_display,
            'scheduled' => $item->scheduled_amount,
            'previous_percent' => (float) $item->previous_percent,
            'current_percent' => number_format((float) $item->current_percent, 2, '.', ''),
            'period_amount' => $item->period_amount > 0
                ? number_format((float) $item->period_amount, 2, '.', '')
                : '',
        ])->all();

        $this->resetValidation();
        $this->showEditor = true;
    }

    public function closeEditor()
    {
        $this->showEditor = false;
        $this->editingId = null;
        $this->editingNumber = null;
        $this->readOnly = false;
        $this->rows = [];
        $this->periodStart = '';
        $this->periodEnd = '';
        $this->notes = '';
        $this->scheduleItemId = '';
        $this->resetValidation();
    }

    /**
     * The boletim can be filled from either side: type a % and the value
     * follows, or type the value and the % is derived from it
     * (% = anterior + valor ÷ previsto). The cumulative percentage stays
     * the stored truth, so a typed value snaps to the nearest 0,01% —
     * visibly, because the field is rewritten with the snapped figure.
     */
    public function updatedRows($value, $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, null);

        if (! isset($this->rows[$index]) || ! in_array($field, ['current_percent', 'period_amount'], true)) {
            return;
        }

        $row = $this->rows[$index];

        if ($field === 'period_amount') {
            $scheduled = (float) $row['scheduled'];

            if ($scheduled <= 0) {
                return;
            }

            $percent = (float) $row['previous_percent'] + ((float) ($value ?: 0) / $scheduled * 100);
            $percent = max((float) $row['previous_percent'], min(100, round($percent, 2)));

            $this->rows[$index]['current_percent'] = number_format($percent, 2, '.', '');
        }

        $amount = $this->rowPeriodAmount($this->rows[$index]);
        $this->rows[$index]['period_amount'] = $amount > 0 ? number_format($amount, 2, '.', '') : '';
    }

    /**
     * Value executed in this period for a row: the cumulative percentage
     * gained, applied to the cost code's scheduled value.
     */
    public function rowPeriodAmount(array $row): float
    {
        $delta = (float) ($row['current_percent'] ?: 0) - (float) $row['previous_percent'];

        return $delta > 0 ? round($delta * $row['scheduled'] / 100, 2) : 0.0;
    }

    /**
     * Live boletim totals for the editor footer.
     */
    public function getEditorTotalsProperty(): array
    {
        $gross = round(collect($this->rows)->sum(fn (array $row) => $this->rowPeriodAmount($row)), 2);
        $percent = (float) ($this->contract->retention_percent ?? 0);
        $retention = round($gross * $percent / 100, 2);

        return [
            'gross' => $gross,
            'retention' => $retention,
            'net' => round($gross - $retention, 2),
            'retention_percent' => $percent,
        ];
    }

    /**
     * The contract's cronograma as context for the boletim: totals plus
     * the parcelas themselves, so measuring can be compared against what
     * was agreed instead of being done blind.
     */
    public function getScheduleSummaryProperty(): array
    {
        $items = $this->contract->scheduleItems()
            ->with(['payments', 'measurements.payments'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Share the loaded contract so percent parcelas don't lazy-load one each.
        $items->each(fn ($item) => $item->setRelation('contract', $this->contract));

        $scheduled = round($items->sum(fn ($item) => $item->getScheduledAmount()), 2);
        $settled = round($items->sum(fn ($item) => $item->getSettledAmount()), 2);

        return [
            'items' => $items,
            'scheduled' => $scheduled,
            'settled' => $settled,
            'balance' => round($scheduled - $settled, 2),
            'unscheduled' => $this->contract->getUnscheduledAmount(),
        ];
    }

    public function getSelectedScheduleItemProperty()
    {
        if ($this->scheduleItemId === '' || $this->scheduleItemId === null) {
            return null;
        }

        return $this->scheduleSummary['items']->firstWhere('id', (int) $this->scheduleItemId);
    }

    public function saveDraft()
    {
        $this->authorizeAbility('contracts.measure', $this->contract);

        if (! $this->persistDraft()) {
            return;
        }

        $this->closeEditor();
        $this->contract->refresh();
        session()->flash('message', __('Measurement saved.'));
    }

    /**
     * Approving from the editor must bank what is on screen first —
     * otherwise it would snapshot the previously saved percentages.
     */
    public function saveAndApprove()
    {
        $this->authorizeAbility('contracts.measure', $this->contract);

        if (! $this->persistDraft()) {
            return;
        }

        $this->approve($this->editingId);
    }

    /**
     * Writes the on-screen boletim to the draft. Returns false when the
     * measurement is no longer editable or a row is invalid.
     */
    protected function persistDraft(): bool
    {
        $measurement = $this->findMeasurement($this->editingId);

        if (! $measurement || ! $measurement->isDraft()) {
            $this->closeEditor();
            session()->flash('error', __('This measurement can no longer be edited.'));

            return false;
        }

        $this->validate([
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
            'notes' => ['nullable', 'string'],
            'scheduleItemId' => [
                'nullable',
                Rule::exists('contract_schedule_items', 'id')->where('contract_id', $this->contract->id),
            ],
            'rows.*.current_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'periodEnd.after_or_equal' => __('The period end must be on or after the period start.'),
            'rows.*.current_percent.max' => __('% complete cannot exceed 100.'),
        ]);

        // A medição measures progress, so it can never take a cost code
        // backwards (its period value would be negative).
        $hasErrors = false;
        foreach ($this->rows as $i => $row) {
            if ((float) ($row['current_percent'] ?: 0) < (float) $row['previous_percent'] - 0.009) {
                $this->addError("rows.{$i}.current_percent", __('% complete cannot be lower than the previously measured :percent%.', [
                    'percent' => rtrim(rtrim(number_format((float) $row['previous_percent'], 2, '.', ''), '0'), '.'),
                ]));
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            return false;
        }

        DB::transaction(function () use ($measurement) {
            $measurement->update([
                'period_start' => $this->periodStart,
                'period_end' => $this->periodEnd,
                'contract_schedule_item_id' => $this->scheduleItemId ?: null,
                'notes' => $this->notes ?: null,
            ]);

            foreach ($this->rows as $row) {
                $measurement->items()->whereKey($row['id'])->update([
                    'current_percent' => $row['current_percent'] ?: 0,
                    'period_amount' => (int) round($this->rowPeriodAmount($row) * 100),
                ]);
            }
        });

        return true;
    }

    // ---------------------------------------------------------------
    // Approve / delete / cancel
    // ---------------------------------------------------------------

    public function approve($id)
    {
        $this->authorizeAbility('contracts.measure', $this->contract);

        $measurement = $this->findMeasurement($id);

        if (! $measurement || ! $measurement->isDraft()) {
            $this->contract->refresh();
            session()->flash('error', __('This measurement can no longer be approved.'));

            return;
        }

        if ((int) $measurement->items()->sum('period_amount') <= 0) {
            session()->flash('error', __('Measure at least one cost code before approving.'));

            return;
        }

        try {
            $measurement->approve(Auth::user());
        } catch (\LogicException) {
            $this->contract->refresh();
            session()->flash('error', __('This measurement can no longer be approved.'));

            return;
        }

        $this->closeEditor();
        $this->contract->refresh();
        session()->flash('message', __('Measurement :number approved.', ['number' => $measurement->measurement_number]));
        $this->dispatch('measurements-updated');
    }

    public function deleteDraft($id)
    {
        $this->authorizeAbility('contracts.measure', $this->contract);

        $measurement = $this->findMeasurement($id);

        if (! $measurement || ! $measurement->isDraft()) {
            session()->flash('error', __('Only a draft measurement can be deleted.'));

            return;
        }

        $measurement->delete();

        $this->closeEditor();
        $this->contract->refresh();
        session()->flash('message', __('Draft measurement deleted.'));
    }

    /**
     * Cancelling an approved medição un-measures the period, so it is
     * refused while any payment still points at it.
     */
    public function cancelMeasurement($id)
    {
        $this->authorizeAbility('contracts.measure', $this->contract);

        $measurement = $this->findMeasurement($id);

        if (! $measurement || ! $measurement->isApproved()) {
            session()->flash('error', __('Only an approved measurement can be cancelled.'));

            return;
        }

        if ($measurement->payments()->exists()) {
            session()->flash('error', __('This measurement has payments linked — delete them before cancelling it.'));

            return;
        }

        $measurement->update(['status' => 'cancelled']);

        $this->closeEditor();
        $this->contract->refresh();
        session()->flash('message', __('Measurement :number cancelled.', ['number' => $measurement->measurement_number]));
        $this->dispatch('measurements-updated');
    }

    /**
     * Hand the medição to the contract's Record Payment modal, which owns
     * payment recording for the whole contract.
     */
    public function payMeasurement($id)
    {
        $this->authorizeAbility('contracts.pay', $this->contract);

        $measurement = $this->findMeasurement($id);

        if (! $measurement || ! $measurement->isApproved() || $measurement->getRemainingNet() <= 0.009) {
            $this->contract->refresh();
            session()->flash('error', __('This measurement has nothing left to pay.'));

            return;
        }

        $this->dispatch('pay-measurement', measurementId: $measurement->id);
    }

    protected function findMeasurement($id): ?ContractMeasurement
    {
        return $id
            ? ContractMeasurement::where('contract_id', $this->contract->id)->with('items.budgetItem')->find($id)
            : null;
    }

    public function render()
    {
        $measurements = $this->contract->measurements()
            ->with(['createdBy', 'approvedBy', 'payments', 'scheduleItem'])
            ->orderByDesc('measurement_number')
            ->get();

        return view('livewire.contract.contract-measurements', [
            'measurements' => $measurements,
            'scheduleItems' => $this->showEditor ? $this->scheduleSummary['items'] : collect(),
        ]);
    }
}
