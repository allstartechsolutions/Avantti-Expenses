# Company (general) expenses

Rent, utilities, insurance, fuel, office supplies, administrative payroll: what the company
pays that belongs to no project. Until 16 Sep 2026 every expense had to sit on a project
(`expenses.project_id` was NOT NULL) and take a cost code from that project's budget, so
overhead was either filed against the wrong job or kept outside the system. Deploy summary:
**[changelog](./changelog-2026-09-16-company-expenses.md)**.

## What a company expense is

**The same `expenses` row with no project.** `project_id` is nullable now; a row with it
null is a company expense. It reuses everything a project expense has — line items,
one-time or installment terms, mark-paid, revert, due-date changes, history, attachments,
the receipt — and differs in exactly two things:

| | Project expense | Company expense |
|---|---|---|
| Belongs to | a project, optionally a job site | the company |
| Broken down by | a cost code on each line (`expense_items.budget_item_id`) | a **category** on the header (`expenses.expense_category_id`); lines carry no cost code |
| Permission area | `expenses` | `company-expenses` |
| Where it is filed | the project / job-site Expenses tab | **Projects → Company Expenses** (`/company-expenses`) |

The one rule, enforced by `Expense::booted()` on every driver and by a MySQL `CHECK` as
belt-and-braces (`2026_09_16_100001`): a company row has **no job site and must carry a
category**; a project row never carries a category. `Expense::isCompanyLevel()` is the
question every screen asks.

## Categories and account codes

`expense_categories` — `name`, `account_code` (4 digits, unique), `description`,
`is_active`, `sort_order`, and a stable seed `key`. Managed on **Settings → Expense
Categories** (`ExpenseCategorySettings`, `settings.view` to open, `settings.edit` to change).

- **The account code is what the customer's accounting software knows the category by.**
  Typed in, it must be four digits from 1000; left blank, `ExpenseCategory::generateAccountCode()`
  draws a free one. The unique index is the final arbiter, and the settings screen draws
  once more if two people collide.
- **Retire, never delete, a category with expenses.** Retiring takes it off the picker;
  every expense already filed keeps it, and the edit screen still offers the retired one
  the expense carries. Deleting is only offered for a category nothing was ever filed under.
- **Seeded add-only** (`ExpenseCategorySeeder`, run by the table's own migration and by
  `db:seed`): a 6xxx chart-of-accounts range shared by both countries — 6100 Rent, 6110
  Utilities, 6120 Phone and internet, 6200 Insurance, 6300 Vehicle fuel, 6310 Vehicle
  maintenance, 6400 Equipment maintenance, 6410 Equipment rental, 6500 Office supplies,
  6510 Software and subscriptions, 6600 Accounting and legal fees, 6700 Marketing, 6800
  Administrative payroll, 6810 Payroll taxes, 6900 Bank fees, 6910 Taxes and licences,
  6950 Travel and meals, 6990 Other overhead — plus **6820 Pró-labore** on a Brazilian
  install. A seeded row is found by its `key`, never by its name or code, so a rename or
  recode on the screen is never undone; a seeded code the owner had already taken is left
  to them and the seeded row gets a generated one. Names are English in the database and
  translated on display; `ExpenseCategorySettingsTest` fails if a seeded name has no pt_BR.

## Why a separate permission area

`expenses.*` is declared with `project` and `job_site` levels, so `PermissionResolver::
isScopedAbility()` treats every one of its abilities as project-scoped, and a record with
no project is answered by `heldOnAnyScope()`: a supervisor holding `expenses.view` on one
job site would have passed the guard on every company expense. `company-expenses` is
`levels => ['global']`, so it takes the company-wide branch and is the role's business
alone. `PermissionResolver` needed no change.

| Ability | What it guards | Seeded to |
|---|---|---|
| `company-expenses.view` | the list, the view modal, the receipt and attachments, the menu entry, the company bucket on every report | managers, admins |
| `company-expenses.create` | `/company-expenses/create` | managers, admins |
| `company-expenses.edit` | `ExpenseEdit` on a company row, changing an installment's due date | managers, admins |
| `company-expenses.pay` | mark paid, flag overdue | managers, admins |
| `company-expenses.edit_paid` *(sensitive)* | revert a paid expense or installment, edit a settled one | admins |
| `company-expenses.delete` | delete | admins |

Employees hold none by default — the owner's decision on 16 Sep 2026: overhead is the
company's own financial picture, not the site costs field staff key in. A bookkeeper on the
employee role gets it by a per-person override on the Access screen.

**Every guard on an expense goes through the row.** `Expense::permissionArea()` answers
`expenses` or `company-expenses`, `Expense::ability('pay')` builds the string, and the
shared `HandlesExpensePaymentActions` trait, `ExpenseEdit`, `FileController::authorizeFile()`,
the `Attachments` component and the history partial all use it. A new guard written as a
literal `expenses.*` on something that may be a company row is the bug this design exists
to prevent — grep before merging.

**Filtering:** `Expense::visibleTo($user)` — the first `visibleTo()` the model has had —
gives a company-wide reader every project row and the company rows only with the grant; a
confined member gets their memberships' rows, plus the company rows only with the grant.
That is the deliberate inversion of the Task precedent (`whereNull('project_id')` there
means *personal*): a row with no project is the company's, not the reader's.

## The screens

- **`/company-expenses`** (`CompanyExpenseIndex`) — summary cards (total, paid, pending,
  overdue), filters (search, category, vendor, status, date range — the current year by
  default), a table with the category and account code, and the same view modal and
  payment actions as the project Expenses tab. The modal shows everything: category and
  code, vendor, terms, installments, items, receipt, attachments, history, and who created
  it and when.
- **`/company-expenses/create`** (`CompanyExpenseCreate`) — the shared expense form
  (`ManagesExpenseForm`) pointed at no project: the Location picker becomes a Category
  picker and the item dialog has no cost-code search. `ManagesExpenseForm::expenseProjectId()`
  is nullable now, `isCompanyExpense()` branches the partials, and
  `extraHeaderData()` is the hook the Equipment module hangs its tag on.
- **Edit** is the existing `ExpenseEdit` screen; the row says which area it answers to and
  where *Back* goes.
- **Settings → Expense Categories** — the category list.

Two pieces were extracted so the two screens cannot drift: `HandlesExpensePaymentActions`
(the view modal and every payment action, from `ProjectExpenses`) and
`resources/views/livewire/expense/partials/view-modal.blade.php` (the modal markup, which
also fixed a stored status being printed through `ucfirst()`).

## On the reports

One rule, on every money report (`App\Services\Concerns\ScopesCompanyExpenses`):

1. **A project, job-site or client filter excludes company rows by construction** —
   `where('project_id', X)` and `whereHas('project')` never match NULL.
2. **Unfiltered, company rows appear only for a reader holding `company-expenses.view`.**
   The screen and the PDF controller say so through `->includeCompany($grant)`; the
   default is *not* to include, so a caller that forgets can only under-report.
3. **"Company (general)" on the Project dropdown** narrows a report to company rows alone
   (`companyScopeFrom('company')`); it is offered only to somebody with the grant, and
   typing it into the address bar without the grant shows nothing.
4. A company row prints **Company (general)** where a project name goes and its
   **category** (`6100 - Rent`) where a job site goes, so the Expense Report's *By project*
   tab nests the company bucket by category the way a project is nested by site.

| Report | Service | Notes |
|---|---|---|
| Expense Report | `ExpenseReportService` | company bucket nested by category; no contracts folded in under "Company (general)" |
| Company Financials | `CompanyFinancialService` | only expenses can be company rows, so "Company (general)" empties income, invoices and contracts |
| Payment Schedule | `PaymentScheduleService` | |
| Payment Details | `PaymentDetailReportService` | |
| Accounts Payable | `AccountsPayableService` | |
| Payments dashboard | `PaymentDashboard::expenseScope()` | the category rides in the job-site line under the project |
| Dashboard | `DashboardIndex::withoutCompanyExpenses()` | cash to pay, overdue list and cashflow |

Untouched on purpose: the cost-code ledger (a company row has no budget), the project and
job-site financial reports (relation-scoped), and receipt clean-up on project delete.

## Tests

- `tests/Feature/Permissions/ExpenseCategorySettingsTest.php` — the settings screen, the
  4-digit code, the add-only seeder, every seeded name translated.
- `tests/Feature/Permissions/CompanyExpensesTest.php` — reproduced, revocable, **scoped**
  (a site member with `expenses.view` sees none of the company's rows by list, guard,
  receipt or attachments), separate, create/edit, the one rule, money masking.
- `tests/Feature/Expense/CompanyExpenseReportsTest.php` — the rule on each service, the
  payments dashboard, the dashboard KPI and the Expense Report screen and PDF.
- `ExpensesTest` and `ExpenseCategorySettingsTest` pin that project expenses behave as
  before.

## Decisions and backlog

Backlog items are in `docs/review-and-improvements.md` under *Company expenses (2026-09-16)*.
The owner's decisions: same table rather than a second one; a category list rather than
the cost-code templates; managers and admins only by default.
