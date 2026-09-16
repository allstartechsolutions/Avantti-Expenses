# Changelog — Company (general) expenses (2026-09-16)

Expenses that belong to the company rather than a project: rent, utilities, insurance,
fuel, office, administrative payroll. Filed by category with the account code the customer's
accounting software knows it by, on their own screen and their own permission area, and
counted on every money report for readers who hold the grant. Reference:
**[Company expenses](./company-expenses.md)**. First half of the plan that continues with
the Equipment module.

## What changed

| File | What |
|---|---|
| `database/migrations/2026_09_16_100000_create_expense_categories_table.php` | **New.** `expense_categories` (name, 4-digit `account_code` unique, description, active, sort, seed `key`), `expenses.expense_category_id` (restrict on delete), seeds the categories |
| `database/migrations/2026_09_16_100001_make_expenses_project_id_nullable.php` | **New.** `expenses.project_id` nullable; three indexes on `expenses` (it had none); MySQL `CHECK` on the company/project rule |
| `app/Models/ExpenseCategory.php`, `database/seeders/ExpenseCategorySeeder.php` | **New.** The category, `generateAccountCode()`, the add-only country-aware seeder (18 shared + pró-labore for BR) |
| `app/Models/Expense.php` | `category()`, `isCompanyLevel()`, `permissionArea()` / `ability()`, `scopeCompany()`, `scopeOnProjects()`, the first `scopeVisibleTo()`, the `saving` rule, *Category* in `fieldLabel()`, `isEditableBy()` by row |
| `app/Livewire/SystemSettings/ExpenseCategorySettings.php` + view, `settings-index.blade.php` | **New** Settings tab: create, edit, retire, delete, generated codes |
| `app/Livewire/CompanyExpense/CompanyExpenseIndex.php` + view | **New** `/company-expenses`: cards, filters, table, view modal, payment actions |
| `app/Livewire/CompanyExpense/CompanyExpenseCreate.php` + view | **New** `/company-expenses/create` |
| `app/Livewire/Concerns/HandlesExpensePaymentActions.php` | **New**, extracted from `ProjectExpenses`: the view modal and every payment action, guards by row |
| `resources/views/livewire/expense/partials/view-modal.blade.php` | **New**, extracted from `project-expenses.blade.php`: category for company rows, no cost-code column, audit facts, `wire:key` on rows, status through `statusLabel()` |
| `app/Livewire/Project/ProjectExpenses.php`, `project-expenses.blade.php` | Use the trait and the partial; behaviour pinned by `ExpensesTest` |
| `app/Livewire/Concerns/ManagesExpenseForm.php` | `expenseProjectId(): ?int`, `isCompanyExpense()`, category field and rules (job site prohibited on a company row, category prohibited on a project row, a retired category refused unless the row already carries it), `extraHeaderData()` hook, no default cost code on company lines |
| `app/Livewire/Expense/ExpenseEdit.php`, `ExpenseCreate.php`, `partials/form-body.blade.php`, `partials/item-modal.blade.php` | Guards by row, *Back* to the company list, category picker instead of Location, cost-code column and search hidden on company rows |
| `app/Http/Controllers/FileController.php`, `app/Livewire/Shared/Attachments.php`, `partials/expense-history.blade.php` | Area by row |
| `app/Services/BudgetService.php` | `ensureBudgetItem()` leaves a company line uncoded |
| `app/Services/Concerns/ScopesCompanyExpenses.php` | **New.** `includeCompany()`, `onlyCompany()`, `companyScopeFrom()`, `applyCompanyScope()`, `expenseLocation()` |
| `app/Services/{ExpenseReport,CompanyFinancial,PaymentSchedule,PaymentDetailReport,AccountsPayable}Service.php` | The rule; company bucket and labels; category eager-loaded |
| `app/Livewire/Report/*Report.php`, `app/Http/Controllers/*ReportPdfController.php` | `->includeCompany($grant)`; "Company (general)" on the Project dropdown; job-site list empty for it |
| `app/Livewire/Payment/PaymentDashboard.php` + view | `expenseScope()` replaces eight inline project filters; company rows by grant; category under the project; null-safe project names (the first company row would have 500'd) |
| `app/Livewire/Dashboard/DashboardIndex.php` | `withoutCompanyExpenses()` on cash to pay, the overdue list and the cashflow chart |
| `config/permissions.php` | Area **`company-expenses`** (global, money, six actions), menu entry order 48 in Projects, group pattern. Catalogue **35 / 180** |
| `config/modules.php`, `database/seeders/PermissionSeeder.php` | `company-expenses.*` under `projects`; view/create/edit/pay manager-only, delete/edit_paid admin-only |
| `routes/web.php` | `company-expenses.index`, `company-expenses.create` behind `ability:` |
| `lang/en.json`, `lang/pt_BR.json` | ~100 strings; **Company Expenses → Despesas da Empresa**, **Account code → Código contábil**, **Company (general) → Empresa (geral)** |
| `tests/Feature/Permissions/ExpenseCategorySettingsTest.php`, `CompanyExpensesTest.php`, `tests/Feature/Expense/CompanyExpenseReportsTest.php` | 7 + 12 + 7 tests |
| `tests/Feature/Permissions/{Navigation,SecurityState,LegacyBehaviour}Test.php` | Bookkeeping |
| `CLAUDE.md`, `docs/permissions-module.md`, `docs/seeders.md`, `docs/review-and-improvements.md`, `docs/README.md` | Docs |

## What moved

- **Nothing for project expenses.** Every screen, guard, report and PDF answers as it did;
  `ExpensesTest` (46 cases) is the proof. The one visible difference on the project
  Expenses tab is the view modal now printing *Created by / Created at / Last updated /
  Payment terms* and showing the status through its label.
- **Employees do not see the new screen or its menu entry** until somebody grants
  `company-expenses.view`; managers and admins do.
- **Reports gain a "Company (general)" bucket** for readers with the grant and nothing for
  anybody else. A project, job-site or client filter never shows company rows.

## Deploy

`php artisan migrate` — two migrations, both incremental. The first seeds the categories for
`config('app.country')`; the second alters `expenses.project_id` (Laravel rebuilds the
table on sqlite, `MODIFY`s it on MySQL) and adds the `CHECK` on MySQL only. Then
`php artisan permissions:sync` offers the new area to the seeded roles as declared.
