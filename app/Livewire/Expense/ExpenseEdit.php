<?php

namespace App\Livewire\Expense;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesExpenseForm;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Correct an expense after the fact — most often its cost code, which until now
 * could only be fixed by deleting the expense and keying it in again.
 *
 * Every change is written to the expense's history, so a figure that moves on a
 * budget screen can always be traced back to who moved it.
 */
class ExpenseEdit extends Component
{
    use WithFileUploads, ManagesExpenseForm, AuthorizesAbility;

    public Expense $expense;

    public bool $removeReceipt = false;

    public function mount(Expense $expense)
    {
        $expense->load(['items.budgetItem', 'items.catalogItem', 'supplier', 'payments', 'purchaseOrder', 'jobSite', 'project']);

        $this->authorizeAbility('expenses.edit', $expense);

        // Correcting money that has already been settled is a grant of its own.
        if ($this->expenseIsSettled($expense)) {
            $this->authorizeAbility('expenses.edit_paid', $expense);
        }

        $this->expense = $expense;
        $this->fillFormFromExpense($expense);
    }

    protected function expenseProjectId(): int
    {
        return $this->expense->project_id;
    }

    /**
     * Paid in full, or with at least one installment already paid.
     */
    private function expenseIsSettled(Expense $expense): bool
    {
        return $expense->status === 'paid'
            || $expense->payments->contains(fn ($payment) => $payment->status === 'paid');
    }

    /**
     * What was spent stays as it was recorded when the money has started
     * moving, or when the expense mirrors an approved purchase order. The cost
     * codes stay editable in both cases — that is the point of this screen.
     */
    public function amountsAreLocked(): bool
    {
        return $this->expense->isFromPurchaseOrder()
            || $this->expense->payments->contains(fn ($payment) => $payment->status === 'paid');
    }

    public function getLockReasonProperty(): ?string
    {
        if ($this->expense->isFromPurchaseOrder()) {
            return __('This expense was created from a purchase order, so its amounts follow the order. Cost codes can still be corrected.');
        }

        if ($this->amountsAreLocked()) {
            return __('An installment on this expense has been paid, so its amounts are fixed. Cost codes can still be corrected.');
        }

        return null;
    }

    public function getBackUrlProperty(): string
    {
        return $this->expense->job_site_id
            ? route('jobsites.show', ['jobSite' => $this->expense->job_site_id, 'tab' => 'expenses'])
            : route('projects.expenses', $this->expense->project_id);
    }

    public function save()
    {
        $this->validateExpenseForm();

        $this->authorizeAbility('expenses.edit', $this->expense);

        if ($this->expenseIsSettled($this->expense)) {
            $this->authorizeAbility('expenses.edit_paid', $this->expense);
        }

        // Moving an expense to another job site is filing it there.
        $this->authorizeAbility('expenses.edit', $this->expenseDestination());

        $beforeLines = $this->lineSnapshot($this->expense);

        DB::transaction(function () use ($beforeLines) {
            $data = $this->expenseHeaderData();

            // What was spent is not up for editing once the money has moved, or
            // when the expense mirrors a purchase order.
            if ($this->amountsAreLocked()) {
                unset($data['total_amount'], $data['status'], $data['total_installments'],
                    $data['payment_frequency'], $data['payment_due_date'], $data['paid_date'],
                    $data['payment_method'], $data['is_auto_payment']);
            }

            if ($this->removeReceipt && $this->expense->receipt_path) {
                Storage::delete($this->expense->receipt_path);
                $data['receipt_path'] = null;
            }

            if ($this->expense_receipt) {
                if ($this->expense->receipt_path) {
                    Storage::delete($this->expense->receipt_path);
                }
                $data['receipt_path'] = $this->expense_receipt->store('expenses', 'local');
            }

            $this->syncExpenseItems($this->expense);
            $this->expense->load('items.budgetItem');

            $lineChanges = $this->lineDiff($beforeLines, $this->lineSnapshot($this->expense));

            // One history entry for the whole save, header and lines together.
            $this->expense->updateWithHistory($data, $lineChanges);
        });

        session()->flash('message', __('Expense updated. The change is recorded in its history.'));

        return redirect()->to($this->backUrl);
    }

    /**
     * Each line's coding and money, in order, for the history diff.
     */
    private function lineSnapshot(Expense $expense): array
    {
        return $expense->items->map(fn ($item) => [
            'item_name' => $item->item_name,
            'cost_code' => $item->budgetItem ? $item->budgetItem->code . ' - ' . $item->budgetItem->name : __('Unassigned'),
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total_amount' => (float) $item->total_amount,
        ])->values()->all();
    }

    /**
     * Lines compared one by one, so "the cost code on line 2 changed" reads as
     * exactly that rather than as a wall of json.
     */
    private function lineDiff(array $before, array $after): array
    {
        $changes = [];
        $lineCount = max(count($before), count($after));

        for ($i = 0; $i < $lineCount; $i++) {
            $old = $before[$i] ?? null;
            $new = $after[$i] ?? null;

            if ($old === $new) {
                continue;
            }

            $label = __('Line :number', ['number' => $i + 1]);

            if (! $old) {
                $changes[$label] = ['old' => null, 'new' => $this->describeLine($new)];

                continue;
            }

            if (! $new) {
                $changes[$label] = ['old' => $this->describeLine($old), 'new' => null];

                continue;
            }

            foreach ($old as $field => $oldValue) {
                if ($oldValue !== $new[$field]) {
                    $changes[$label . ' — ' . $field] = ['old' => $oldValue, 'new' => $new[$field]];
                }
            }
        }

        return $changes;
    }

    private function describeLine(array $line): string
    {
        return $line['item_name'] . ' (' . $line['cost_code'] . ')';
    }

    public function render()
    {
        return view('livewire.expense.expense-edit', [
            'suppliers' => $this->supplierSearchResults(),
            'budgetItems' => $this->budgetItemSearchResults(),
            'catalogItems' => $this->catalogItemSearchResults(),
            'jobSites' => $this->selectableJobSites('expenses.edit'),
        ])->layout('components.layouts.app');
    }
}
