<?php

namespace App\Livewire\Concerns;

use App\Models\Expense;
use App\Models\ExpensePayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The view modal and the payment actions an expense list offers: open the
 * details, mark paid, revert, change a due date, flag overdue, delete.
 *
 * Extracted from ProjectExpenses so the company-expenses list is the same
 * screen with a different scope. The component supplies that scope through
 * `expenseInScope()` / `paymentInScope()` — an id from the browser is never
 * trusted past them — and every guard asks the record which area it belongs
 * to (`Expense::ability()`), so a project expense answers to `expenses.*`
 * and a company expense to `company-expenses.*` from one method body.
 */
trait HandlesExpensePaymentActions
{
    // View modal
    public $showExpenseModal = false;

    /**
     * The id, not the model: Livewire re-fetches a model property with
     * firstOrFail() on every request, so an expense deleted under an open
     * modal threw before any of this code ran. `viewingExpense()` resolves it.
     * Locked: only `openExpenseViewModal()`, which asks for `view`, may set it.
     */
    #[Locked]
    public ?int $viewingExpenseId = null;

    // View modal data
    public $expense_date = '';
    public $expense_job_site_id = null;
    public $expense_supplier_id = null;
    public $supplierSearch = '';
    public $expense_notes = '';
    public $expense_total_amount = 0;
    public $expense_status = '';
    public $expense_payment_method = null;
    public $expense_is_auto_payment = false;
    public $expense_has_installments = false;
    public $expense_total_installments = 0;
    public $expense_payment_frequency = '';
    public $expense_payment_due_date = '';
    public $expense_paid_date = '';
    public $existingReceiptPath = null;
    public $expenseItems = [];
    public $expenseHistory = [];

    // Mark-as-paid confirmation state (inline date picker)
    public $markPaidType = null; // 'expense' or 'payment'
    public $markPaidId = null;
    public $markPaidDate = '';

    // Installment due-date editing state
    public $editDueDateId = null;
    public $editDueDate = '';

    /**
     * The expense open in the view modal, or null once it is closed, gone, or
     * no longer viewable by this person — the modal body renders on every
     * request, so the lookup carries the same guard as opening it.
     */
    #[Computed]
    public function viewingExpense(): ?Expense
    {
        if (! $this->viewingExpenseId) {
            return null;
        }

        try {
            $expense = $this->expenseInScope($this->viewingExpenseId);
        } catch (ModelNotFoundException) {
            return null;
        }

        if (! auth()->user()?->can($expense->ability('view'), $expense)) {
            return null;
        }

        return $expense->load(['payments.paidBy', 'paidBy', 'createdBy', 'jobSite', 'category']);
    }

    /** The expense with this id, only if it belongs on this screen. */
    abstract protected function expenseInScope(int $expenseId): Expense;

    /** The installment with this id, only if its expense belongs on this screen. */
    abstract protected function paymentInScope(int $paymentId): ExpensePayment;

    public function openExpenseViewModal(int $expenseId): void
    {
        $expense = $this->expenseInScope($expenseId);
        $expense->load(['payments.paidBy', 'paidBy', 'createdBy', 'items.budgetItem', 'items.catalogItem', 'supplier', 'jobSite', 'category']);

        $this->authorizeAbility($expense->ability('view'), $expense);

        $this->fillViewModal($expense);

        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-view-modal');
    }

    /** Copy an expense into the view modal's fields; open and refresh share it. */
    protected function fillViewModal(Expense $expense): void
    {
        $expense->loadMissing(['items.budgetItem', 'supplier']);

        $this->viewingExpenseId = $expense->id;
        unset($this->viewingExpense);
        $this->expense_job_site_id = $expense->job_site_id;
        $this->expense_supplier_id = $expense->supplier_id;
        $this->supplierSearch = $expense->supplier?->name ?? '';
        $this->expense_notes = $expense->notes;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->existingReceiptPath = $expense->receipt_path;
        $this->expense_total_amount = $expense->total_amount;

        $this->expenseItems = [];
        foreach ($expense->items as $item) {
            $this->expenseItems[] = [
                'id' => $item->id,
                'budget_item_id' => $item->budget_item_id,
                'cost_code' => $item->cost_code_display,
                'catalog_item_id' => $item->catalog_item_id,
                'item_name' => $item->item_name,
                'item_type' => $item->item_type,
                'description' => $item->description ?? '',
                'quantity' => $item->quantity,
                'unit' => $item->unit ?? '',
                'unit_price' => $item->unit_price,
                'total_amount' => $item->total_amount,
            ];
        }

        // Expenses filed before line items existed keep their one item on the
        // header; show it as a line so the detail view is never empty of them.
        if ($this->expenseItems === [] && $expense->item_name) {
            $this->expenseItems[] = [
                'id' => 'header-'.$expense->id,
                'budget_item_id' => null,
                'cost_code' => null,
                'catalog_item_id' => $expense->catalog_item_id,
                'item_name' => $expense->item_name,
                'item_type' => $expense->item_type,
                'description' => '',
                'quantity' => $expense->quantity,
                'unit' => $expense->usage_unit ?: ($expense->purchase_unit ?? ''),
                'unit_price' => $expense->unit_price,
                'total_amount' => $expense->total_amount,
            ];
        }

        $this->expense_status = $expense->status;
        $this->expense_payment_method = $expense->payment_method;
        $this->expense_is_auto_payment = $expense->is_auto_payment;
        $this->expense_has_installments = $expense->isInstallment();
        $this->expense_total_installments = $expense->total_installments;
        $this->expense_payment_frequency = $expense->payment_frequency;
        $this->expense_payment_due_date = $expense->payment_due_date?->format('Y-m-d');
        $this->expense_paid_date = $expense->paid_date?->format('Y-m-d');

        $this->expenseHistory = $expense->changeHistories()
            ->with(['changedBy', 'expensePayment'])
            ->get()
            ->map(fn ($h) => [
                'label' => $h->getActionLabel(),
                'color' => $h->getActionColor(),
                'user' => $h->changedBy?->name,
                'date' => $h->created_at->appDateTime(),
                'changes' => $h->changes,
            ])
            ->toArray();
    }

    public function closeExpenseModal(): void
    {
        $this->showExpenseModal = false;
        $this->viewingExpenseId = null;
        unset($this->viewingExpense);
        $this->expenseHistory = [];
        $this->cancelMarkPaid();
        $this->cancelEditDueDate();
        $this->dispatch('close-modal', 'expense-view-modal');
    }

    public function startMarkPaid(string $type, int $id): void
    {
        $record = $type === 'payment' ? $this->paymentInScope($id) : $this->expenseInScope($id);
        $expense = $type === 'payment' ? $record->expense : $record;

        $this->authorizeAbility($expense->ability('pay'), $record);

        $this->markPaidType = $type;
        $this->markPaidId = $id;
        $this->markPaidDate = now()->format('Y-m-d');
    }

    public function cancelMarkPaid(): void
    {
        $this->reset(['markPaidType', 'markPaidId', 'markPaidDate']);
    }

    public function confirmMarkPaid(): void
    {
        $this->validate(['markPaidDate' => 'required|date']);

        $paidDate = Carbon::parse($this->markPaidDate);

        if ($this->markPaidType === 'payment') {
            $payment = $this->paymentInScope((int) $this->markPaidId);
            $this->authorizeAbility($payment->expense->ability('pay'), $payment);
            $payment->markAsPaid(null, $paidDate);
            session()->flash('message', __('Payment marked as paid.'));
        } else {
            $expense = $this->expenseInScope((int) $this->markPaidId);
            $this->authorizeAbility($expense->ability('pay'), $expense);
            if ($expense->isOneTime() && $expense->status !== 'paid') {
                $expense->markAsPaid(null, $paidDate);
            }
            session()->flash('message', __('Expense marked as paid.'));
        }

        $this->cancelMarkPaid();
        $this->refreshViewingExpense();
    }

    public function startEditDueDate(int $paymentId): void
    {
        $payment = $this->paymentInScope($paymentId);
        $this->authorizeAbility($payment->expense->ability('edit'), $payment);
        $this->editDueDateId = $payment->id;
        $this->editDueDate = $payment->due_date->format('Y-m-d');
    }

    public function cancelEditDueDate(): void
    {
        $this->reset(['editDueDateId', 'editDueDate']);
    }

    public function confirmEditDueDate(): void
    {
        $this->validate(['editDueDate' => 'required|date']);

        $payment = $this->paymentInScope((int) $this->editDueDateId);
        $this->authorizeAbility($payment->expense->ability('edit'), $payment);

        if (! $payment->isPaid()) {
            $payment->changeDueDate(Carbon::parse($this->editDueDate));
            session()->flash('message', __('Due date updated.'));
        }

        $this->cancelEditDueDate();
        $this->refreshViewingExpense();
    }

    public function unmarkExpensePaid(int $expenseId): void
    {
        $expense = $this->expenseInScope($expenseId);
        $this->authorizeAbility($expense->ability('edit_paid'), $expense);

        $expense->unmarkAsPaid();

        session()->flash('message', __('Expense payment reverted to unpaid.'));
        $this->refreshViewingExpense();
    }

    public function unmarkPaymentPaid(int $paymentId): void
    {
        $payment = $this->paymentInScope($paymentId);
        $this->authorizeAbility($payment->expense->ability('edit_paid'), $payment);

        if ($payment->isPaid()) {
            $payment->markAsPending();
            session()->flash('message', __('Payment reverted to pending.'));
        }

        $this->refreshViewingExpense();
    }

    public function markPaymentAsOverdue(int $paymentId): void
    {
        $payment = $this->paymentInScope($paymentId);
        $this->authorizeAbility($payment->expense->ability('pay'), $payment);

        $payment->markAsOverdue();

        session()->flash('message', __('Payment marked as overdue.'));
        $this->refreshViewingExpense();
    }

    public function deleteExpense(int $expenseId): void
    {
        $expense = $this->expenseInScope($expenseId);
        $this->authorizeAbility($expense->ability('delete'), $expense);

        if ($expense->receipt_path) {
            Storage::delete($expense->receipt_path);
        }

        $expense->items()->delete();
        $expense->payments()->delete();
        $expense->delete();

        session()->flash('message', __('Expense deleted successfully.'));
    }

    /**
     * Reload the open modal after a payment action, so the status, the
     * schedule and the history all show what was just recorded.
     */
    protected function refreshViewingExpense(): void
    {
        unset($this->viewingExpense);

        if (! $this->showExpenseModal || ! $this->viewingExpenseId) {
            return;
        }

        $expense = $this->viewingExpense;

        $expense ? $this->fillViewModal($expense) : $this->closeExpenseModal();
    }
}
