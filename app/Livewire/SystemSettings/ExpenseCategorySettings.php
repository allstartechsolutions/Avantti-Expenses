<?php

namespace App\Livewire\SystemSettings;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\ExpenseCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The categories a company (general) expense is filed under, each with the
 * 4-digit account code the customer's accounting software knows it by.
 *
 * A category is never deleted while an expense is filed under it: it is
 * **retired**, which takes it off the picker while every expense already
 * filed keeps its category. Deleting is only offered for a category nothing
 * was ever filed under — a typo, in practice.
 */
class ExpenseCategorySettings extends Component
{
    use AuthorizesAbility;

    // Form fields
    public string $name = '';
    public string $account_code = '';
    public string $description = '';
    public int $sort_order = 0;
    public bool $is_active = true;

    public ?int $editingId = null;
    public bool $showFormModal = false;

    public function mount(): void
    {
        $this->authorizeAbility('settings.view');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($this->editingId)],
            // Blank generates one; typed, it is four digits starting 1–9.
            'account_code' => ['nullable', 'regex:/^[1-9][0-9]{3}$/', Rule::unique('expense_categories', 'account_code')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'account_code.regex' => __('The account code must be four digits, from 1000 to 9999.'),
            'account_code.unique' => __('Another category already uses this account code.'),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'account_code' => __('account code'),
            'sort_order' => __('sort order'),
        ];
    }

    public function create(): void
    {
        $this->authorizeAbility('settings.edit');

        $this->resetForm();
        $this->sort_order = (int) ExpenseCategory::query()->where('sort_order', '<', 99)->max('sort_order') + 1;
        $this->showFormModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeAbility('settings.edit');

        $category = ExpenseCategory::findOrFail($id);

        $this->resetForm();
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->account_code = $category->account_code;
        $this->description = (string) $category->description;
        $this->sort_order = $category->sort_order;
        $this->is_active = $category->is_active;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->authorizeAbility('settings.edit');

        // Trimmed before the unique rules look, or '  Rent  ' slips past 'Rent'.
        $this->name = trim($this->name);
        $this->account_code = trim($this->account_code);
        $this->description = trim($this->description);

        $this->validate();

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $attributes['account_code'] = $this->account_code ?: ExpenseCategory::generateAccountCode();
            ExpenseCategory::findOrFail($this->editingId)->update($attributes);
            session()->flash('message', __('Expense category updated.'));
        } else {
            $this->createWithCode($attributes, $this->account_code);
            session()->flash('message', __('Expense category created.'));
        }

        $this->closeForm();
    }

    /**
     * A generated code can, in theory, be drawn twice by two people saving
     * at once; the unique index refuses the second, and one more draw
     * settles it.
     */
    protected function createWithCode(array $attributes, string $typedCode): ExpenseCategory
    {
        $attempts = 0;

        do {
            $attributes['account_code'] = $typedCode ?: ExpenseCategory::generateAccountCode();

            try {
                return DB::transaction(fn () => ExpenseCategory::create($attributes));
            } catch (UniqueConstraintViolationException $e) {
                if ($typedCode !== '' || ++$attempts > 1) {
                    throw $e;
                }
            }
        } while (true);
    }

    /** Retire or bring back a category. Expenses filed under it are untouched either way. */
    public function toggleActive(int $id): void
    {
        $this->authorizeAbility('settings.edit');

        $category = ExpenseCategory::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        session()->flash('message', $category->is_active
            ? __(':name is offered on the expense picker again.', ['name' => __($category->name)])
            : __(':name is retired: it no longer appears on the expense picker, and every expense already filed under it is kept.', ['name' => __($category->name)]));
    }

    /** Only for a category nothing was ever filed under. Anything else is retired instead. */
    public function delete(int $id): void
    {
        $this->authorizeAbility('settings.edit');

        $category = ExpenseCategory::withCount('expenses')->findOrFail($id);

        if ($category->expenses_count > 0) {
            session()->flash('error', __(':name has expenses filed under it and cannot be deleted. Retire it instead.', ['name' => __($category->name)]));

            return;
        }

        $category->delete();

        session()->flash('message', __('Expense category deleted.'));
    }

    public function closeForm(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->account_code = '';
        $this->description = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderByDesc('is_active')
            ->ordered()
            ->get();

        return view('livewire.system-settings.expense-category-settings', [
            'categories' => $categories,
            'activeCount' => $categories->where('is_active', true)->count(),
            'filedCount' => $categories->sum('expenses_count'),
        ]);
    }
}
