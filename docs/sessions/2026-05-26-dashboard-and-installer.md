# Dashboard, Module Sync, and First-Install Setup Wizard

**Date:** 2026-05-26
**Goal:** Replace the placeholder `/dashboard` with a role-aware financial dashboard, ensure module access is consistent across installs, and automate first-time setup so customers without CLI access can get the system running.

---

## Overview

This session delivered four pieces of work:

1. **Admin Dashboard** — a financial/operations overview at `/dashboard` with KPIs, action lists, and a 6-month cashflow chart.
2. **Module-aware widgets** — the dashboard adapts to which modules are enabled in `module_access`. A projects-only company sees operations widgets; a financial company sees AR/AP widgets.
3. **Module access sync migration** — backfills `module_access` rows from `config/modules.php` on every install so System Settings → Modules always reflects the full module list.
4. **Setup wizard** — a one-time `/setup` route to create the first administrator account without needing CLI access. Initial seed data (roles, catalog categories, document types) now runs automatically via migration.

The manager and employee dashboards are deferred to the next session.

---

## 1. Admin Dashboard

### Routing

`routes/web.php` swapped:

```php
Route::view('dashboard', 'dashboard')
```

for:

```php
Route::get('dashboard', DashboardIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
```

### Component

**File:** `app/Livewire/Dashboard/DashboardIndex.php`

The component renders a role-aware view: admin gets the full dashboard, other roles get a placeholder until the manager/employee pages are built.

```php
public function render()
{
    $role = auth()->user()->role?->name ?? 'employee';
    return view('livewire.dashboard.dashboard-index', [
        'role' => $role,
        'modules' => $this->modules,
    ])->layout('components.layouts.app');
}
```

### Views

- `resources/views/livewire/dashboard/dashboard-index.blade.php` — wrapper that includes the role-specific partial.
- `resources/views/livewire/dashboard/partials/admin.blade.php` — full admin layout.

### Admin layout

**Month selector** (default: current month) — drives Cash to Pay, Receivables, and the cashflow chart range context.

**KPI cards (4-column grid):**

| Card | Source query |
|---|---|
| Cash to Pay | `ExpensePayment` not-paid + `Expense` unpaid one-time + open `Contract` balances (in selected month) |
| Receivables | `Invoice` balances in `sent/pending/partial` (due in selected month) + past-due chip |
| Open Estimates | sum of `Estimate` in `sent/pending` status |
| Active Projects | count of `Project` in `in_progress` status + at-risk chip |

"At risk" = project has a past-due invoice OR an overdue `ExpensePayment` row.

**Action lists (3-column grid):**

| Panel | Source |
|---|---|
| Overdue Payments | `ExpensePayment::where('status', 'overdue')` (top 10) |
| Past-Due Invoices | `Invoice::whereIn('status', ['sent','pending','partial'])` with `due_date < today` (top 10) |
| Pending Approvals | `PurchaseOrder::where('status', 'pending')` + `PaymentBatch::where('status', 'draft')` (top 5 each) |

**Cashflow chart (6 months):**

- AP outflow = `ExpensePayment` paid (by `paid_date`) + `ContractPayment` (by `payment_date`)
- AR inflow = `InvoicePayment` completed (by `payment_date`)
- Renders via **Chart.js 4.4.1 via CDN** (no package install). Loaded with `@push('scripts')` since the app layout has `@stack('scripts')`.

### Currency formatting

All money uses the project's standard:

```blade
{{ Number::currency($value, config('app.currency'), config('app.locale')) }}
```

Storage is integer cents in the DB; model accessors convert to dollars before display.

---

## 2. Module-aware widgets

The dashboard reads `ModuleAccess::isEnabled($key)` (cached for 5 min) for `invoices`, `estimates`, and `projects`. When a module is disabled, the relevant query is **skipped** (no wasted DB work) and the widget is **replaced**:

| Module state | Card 2 | Card 3 | Middle panel | Chart |
|---|---|---|---|---|
| `invoices` + `estimates` ON | Receivables | Open Estimates | Past-Due Invoices | AR + AP bars |
| `invoices` OFF | **Projects Over Budget** | Open Estimates | **Projects Over Budget** list | **AP only**, titled "Cash Out" |
| `estimates` OFF | Receivables | **Open Purchase Orders** | Past-Due Invoices | unchanged |
| Both OFF | Projects Over Budget | Open POs | Over Budget list | AP-only "Cash Out" |

The header subtitle also switches between "Financial overview" and "Operations overview".

"Over budget" definition: `Project.status = in_progress` AND `sum(expenses.quantity * expenses.unit_price) > initial_amount`. Uses the raw column `initial_amount` (cents) for the comparison; division by 100 is done only for display.

---

## 3. Module access sync migration

### Problem

The `module_access` table was only populated by one retro-fit migration (`2026_05_19_..._add_reports_module_to_module_access.php`) for the `reports` row. The other 6 modules had **no seeder** and **no migration** that inserted rows. `ModuleAccess::isEnabled()` returns `true` when a row is missing, so the sidebar still showed everything — but `System Settings → Modules` displayed an empty (or near-empty) toggle list because that page only lists existing rows.

This caused inconsistent behavior across installs: some had a full toggle list (rows were inserted manually at some point), others had nothing.

### Migration

**File:** `database/migrations/2026_05_26_100000_sync_module_access_with_config.php`

```php
foreach (config('modules', []) as $key => $module) {
    DB::table('module_access')->insertOrIgnore([
        'module_key' => $key,
        'module_name' => $module['name'],
        'description' => $module['description'] ?? null,
        'is_enabled' => true,
        'is_core' => $module['is_core'] ?? false,
        'created_by' => $firstUserId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}
```

`down()` is a deliberate no-op — removing rows would lose customer toggle state.

### Effect

- Fresh installs: all 7 module rows inserted on first migrate.
- Existing installs: missing rows backfilled; existing rows untouched (including their `is_enabled` value).
- Customer who had `invoices` disabled keeps it disabled.

---

## 4. Setup wizard for first install

### Problem

On a fresh install with no CLI access, the customer has no way to:
- Run `php artisan db:seed` to create roles/categories
- Create the first admin user (registration is disabled in `config/fortify.php`)

### Migration: seed lookup data

**File:** `database/migrations/2026_05_26_100001_seed_initial_lookup_data.php`

Runs the existing idempotent seeders from a migration so `php artisan migrate` is enough:

```php
(new RoleSeeder)->run();
(new CatalogCategorySeeder)->run();
(new DocumentTypeSeeder)->run();

if (DB::table('users')->exists()) {
    (new DefaultSupplierSeeder)->run();
}
```

`DefaultSupplierSeeder` is conditional because its row has a `created_by` FK to `users`. On a brand-new install there are no users yet — the supplier is seeded later by the wizard.

### Wizard component

**File:** `app/Livewire/Setup/SetupWizard.php`

Key safety mechanism: both `mount()` and `register()` check `User::query()->exists()`. If any user exists, the route aborts with 404. This protects running systems (which all have users) from anyone ever reaching the wizard.

```php
public function mount(): void
{
    if (User::query()->exists()) {
        abort(404);
    }
}
```

On submit:
1. Re-checks user-existence (race-condition guard)
2. Validates name / email / password (`Password::min(8)->mixedCase()->numbers()`, confirmed)
3. Creates the user with the `admin` role
4. Runs `DefaultSupplierSeeder` (FK is now valid)
5. Logs the user in via `Auth::login($user)`
6. Redirects to `/dashboard`

### Routes

**File:** `routes/web.php`

```php
Route::get('/', function () {
    if (! User::query()->exists()) {
        return redirect('/setup');
    }
    return redirect('/login');
})->name('home');

Route::get('setup', SetupWizard::class)->name('setup');
```

The home route only redirects to `/setup` when the `users` table is empty. Running systems always have users, so they keep redirecting to `/login`.

### Fresh-install flow

1. Customer (or host) runs `composer install` + `php artisan migrate`
2. Visits root URL → redirected to `/setup`
3. Fills wizard form → admin user created, default supplier seeded, logged in
4. Lands on `/dashboard`
5. `/setup` permanently returns 404 from this point forward

---

## 5. Bug fixes in this session

### Column name mismatch — `expense_payments.payment_date`

The `expense_payments` table uses `paid_date`, not `payment_date`. The cashflow chart query was updated:

```php
ExpensePayment::where('status', 'paid')
    ->whereBetween('paid_date', [$monthStart, $monthEnd])  // was 'payment_date'
    ->sum('amount');
```

`contract_payments` and `invoice_payments` use `payment_date` — those queries were already correct.

### Column name mismatch — `clients.name`

The `clients` table uses `company_name` / `contact_name`, not `name`. The Past-Due Invoices panel query and view were updated:

```php
// component
Invoice::with(['client:id,company_name', ...])

// view
{{ $invoice->client?->company_name ?? __('Client') }}
```

---

## Files changed in this session

### Created

- `app/Livewire/Dashboard/DashboardIndex.php`
- `resources/views/livewire/dashboard/dashboard-index.blade.php`
- `resources/views/livewire/dashboard/partials/admin.blade.php`
- `app/Livewire/Setup/SetupWizard.php`
- `resources/views/livewire/setup/setup-wizard.blade.php`
- `database/migrations/2026_05_26_100000_sync_module_access_with_config.php`
- `database/migrations/2026_05_26_100001_seed_initial_lookup_data.php`

### Modified

- `routes/web.php` — added `DashboardIndex` route, `SetupWizard` route, conditional home redirect

### Untouched (intentionally)

- `database/seeders/DatabaseSeeder.php` — still creates the developer admin (`jr@allstartechsolutions.com`) on manual `db:seed`. That account is never created in customer installs.
- `config/fortify.php` — registration still disabled (would be reachable by anyone). Account creation goes through `/setup` or the in-app `users.create` route.

---

## Safety guarantees on running systems

| Change | Why it doesn't affect existing installs |
|---|---|
| New `module_access` sync migration | Uses `insertOrIgnore`. Existing rows (and their on/off state) untouched. |
| New initial-lookup-data migration | All seeders use `firstOrCreate`. No row is modified if it already matches. |
| New `/setup` route | `mount()` aborts 404 the moment any user exists. Effectively invisible on every running install. |
| Modified `/` route | Only redirects to `/setup` when `users` table is empty. Running installs always have users. |
| Modified `/dashboard` route | Same name (`dashboard`), same auth middleware. Replaces a placeholder view with a real component. |
| Module-aware widgets | Module flags read from `module_access` (existing system). Disabled modules hide widgets; enabled modules keep current behavior. |

---

## Known caveats and intentional limitations

1. **Cash to Pay includes full contract balances** — there is no "due this month" date on `Contract.amount`, so the KPI includes every open contract's outstanding balance regardless of timing. If a per-phase due date is added later, the KPI can be tightened.
2. **At-risk chip ignores the month selector** — past-due conditions are always "as of today".
3. **Over Budget uses `initial_amount` as the budget basis**, not the formal `Budget` model with cost-code totals. Easy to swap if the cost-code total is preferred.
4. **Chart.js loaded from CDN** — no package install per project rules. Loads on every dashboard view.
5. **Setup wizard does not handle multi-tenant scenarios** — it creates the *first* user. If the system grows to multi-tenant, this needs revisiting.

---

## Next steps

- **Manager dashboard** — operations focus: project health, budget variance, project-scoped pending approvals, daily report compliance.
- **Employee dashboard** — task-oriented: "my job sites" (via `project_manager_id` on Project), my recent expenses, missing daily reports, quick actions.
- Both will reuse the same `DashboardIndex` component, branching in `render()`.
