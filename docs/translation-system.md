# Translation / Localization System

## Overview

This project uses Laravel's built-in JSON-based localization. The locale is set once via the `.env` file (`APP_LOCALE`) and does not change dynamically at runtime. There is no language switcher in the UI.

## How It Works

1. **Laravel's `__()` helper** is used in both Blade views and Livewire PHP components
2. When `APP_LOCALE=en`, `__('Create Project')` returns `"Create Project"` (the key itself — no translation needed)
3. When `APP_LOCALE=pt_BR`, Laravel looks up the key in `lang/pt_BR.json` and returns `"Criar Projeto"`

## File Structure

```
lang/
├── en.json       # Organizational comment keys only (structural reference)
└── pt_BR.json    # Comment keys + all actual Portuguese translations
```

### `en.json` — English (Structural + Project Modules)

Contains organizational comment keys prefixed with `_` (e.g., `_sidebar`, `_users_create`). For earlier modules (sidebar, company, users), only comment keys exist since the `__()` keys are already in English.

Starting from the **Projects module** onward, `en.json` also includes actual EN values (e.g., `"Add Expense": "Add Expense"`) to allow for potential future EN customizations. This applies to: project navigation, project overview, expenses, and all subsequent modules.

Purpose: serves as a structural map and EN reference for project-related modules.

### `pt_BR.json` — Brazilian Portuguese (Full Translations)

Contains both:
- Organizational comment keys (matching `en.json` structure)
- All actual translation strings (e.g., `"Create Project": "Criar Projeto"`)

## Translation Key Conventions

### Comment/Section Keys

Used to organize the JSON files into readable sections. They use `_` prefix and are never referenced in code:

```json
"_sidebar": "========== Sidebar Navigation ==========",
"_sidebar_menu": "---------- Menu Section ----------",
"_users_create_header": ".......... Page Header ..........",
```

Hierarchy:
- `==========` — Top-level module (e.g., `_sidebar`, `_users`, `_projects`)
- `----------` — Page within module (e.g., `_users_create`, `_users_edit`)
- `..........` — Section within page (e.g., `_users_create_header`, `_users_create_password`)

### Translation Keys

- Keys are the **English text itself** (not dot-notation like `users.create.title`)
- Keys should be natural, readable English
- Use the exact text as displayed in the UI

```json
"Create Project": "Criar Projeto",
"Add a new project to the system": "Adicionar um novo projeto ao sistema",
"User created successfully!": "Usuário criado com sucesso!"
```

### Validation Attributes

Lowercase keys used in Livewire `validationAttributes()`:

```json
"name": "nome",
"email address": "endereço de e-mail",
"project name": "nome do projeto"
```

### Parameterized Strings

Use Laravel's `:param` syntax for dynamic values:

```json
"Showing :from to :to of :total users": "Exibindo :from a :to de :total usuários",
"Password reset link sent to :email": "Link de redefinição de senha enviado para :email"
```

## Where Translations Are Applied

### Blade Views — `{{ __('Key') }}`

```blade
<h1>{{ __('Create Project') }}</h1>
<p>{{ __('Add a new project to the system') }}</p>
<input placeholder="{{ __('Enter project name') }}">
```

### Livewire Components — `__('Key')`

```php
// Flash messages
session()->flash('message', __('User created successfully!'));

// Validation attributes
public function validationAttributes() {
    return [
        'name' => __('name'),
        'email' => __('email address'),
    ];
}
```

## Configuration

In `.env`:
```
APP_LOCALE=en            # or pt_BR
APP_FALLBACK_LOCALE=en
```

In `config/app.php`:
```php
'locale' => env('APP_LOCALE', 'en'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
```

No middleware or dynamic switching is needed.

## Translation Progress

### Completed (views + blade + PHP components + JSON entries)

| Module           | Views Translated                                          |
|------------------|-----------------------------------------------------------|
| Sidebar          | Navigation menu, user section, footer                     |
| Company          | `company-index`, `company-info` (+ CompanyInfo.php)       |
| Users            | `user-index`, `user-create`, `user-edit`, `user-show` (+ UserCreate.php, UserEdit.php, UserShow.php) |
| Profile          | `user-profile` (+ UserProfile.php)                        |
| Projects (CRUD)  | `project-index`, `project-create`, `project-edit` (+ ProjectIndex.php, ProjectCreate.php, ProjectEdit.php) |
| Layout           | `app.blade.php`, `sidebar.blade.php`, `footer.blade.php`  |
| Project Nav      | `project-nav.blade.php`, `project-layout.blade.php`, `breadcrumb.blade.php` |
| Project Overview | `project-overview.blade.php` (+ ProjectOverview.php, ProjectStatus.php enum) |
| Expenses         | `project-expenses.blade.php`, `partials/expense-modal.blade.php`, `expense-create.blade.php`, `job-site-show.blade.php` (expenses tab only) (+ ProjectExpenses.php, ExpenseCreate.php, JobSiteShow.php) |
| Purchase Orders  | `project-purchase-orders.blade.php`, `purchase-order-create.blade.php`, `purchase-order-edit.blade.php`, `purchase-order-show.blade.php`, `job-site-show.blade.php` (PO tab) (+ PurchaseOrderCreate.php, PurchaseOrderEdit.php, PurchaseOrderShow.php, PurchaseOrder.php model, PurchaseOrderStatusHistory.php model) |
| Change Orders    | `project-change-orders.blade.php`, `job-site-show.blade.php` (CO tab + CO modal) (+ ProjectChangeOrders.php, JobSiteShow.php CO methods) |
| Job Site (index) | `project-job-sites.blade.php` (+ ProjectJobSites.php) |
| Job Site (overview) | `job-site-overview.blade.php` (+ JobSiteOverview.php) |
| Job Site Nav     | `jobsite-layout.blade.php`, `jobsite-nav.blade.php`  |

### Remaining Modules

| Module           | Views (count) | Files to Translate                                     |
|------------------|---------------|--------------------------------------------------------|
| Auth             | 7             | login, register, forgot-password, reset-password, etc. |
| Client           | 5             | client-index, create, edit, show, payment-methods      |
| Estimate         | 5             | estimate-index, create, edit, show, send                |
| Invoice          | 5             | invoice-index, create, edit, show, send                 |
| Project (inner)  | ~3            | project-show sub-views: daily-reports, budget, contracts |
| Job Site (tabs)  | ~2            | job-site-show (remaining tabs: daily-reports, budget)   |
| Job Site (other) | 1             | job-site-contracts                                      |
| Daily Report     | 1             | daily-report-index (complex, inline modals)             |
| Budget           | 3             | budget-index, create, edit                              |
| Cost Code        | 4             | cost-code-index, create, edit, templates                |
| Catalog          | 6             | catalog-index, create, edit, category-index, etc.       |
| Supplier         | 4             | supplier-index, create, edit, show                      |
| Subcontractor    | 4             | subcontractor-index, create, edit, show                 |
| Contract         | 3             | contract-index, create, edit                            |
| Payment          | 1             | payment-index                                           |
| Settings         | 6             | various settings pages                                  |
| System Settings  | 4             | tax rates, messages, etc.                               |
| Shared           | 1             | shared components                                       |

**Total remaining: ~55 blade files across 16 modules**

### Notes on Partially Translated Files

- **`job-site-show.blade.php`**: The **expenses tab**, **purchase orders tab**, and **change orders tab + modal** have been translated. Other tabs (daily-reports, budget) remain untranslated.
- **`ProjectStatus.php` enum**: Status labels (`Created`, `In Progress`, `Completed`, `Cancelled`) are now translated via `__()` in the `label()` method.

## Workflow for Adding Translations

When translating a new module:

1. **Read the blade view** — identify all user-facing text
2. **Wrap text with `__()`** in the blade file
3. **Update the Livewire PHP component** — wrap flash messages, validation messages, validation attributes
4. **Add keys to `en.json`** — section comment markers + EN values (for project modules and onward)
5. **Add translations to `pt_BR.json`** — comment keys + all translated Portuguese strings
6. **Validate JSON** — run `json_decode()` on both files to ensure valid JSON
7. **Test** — verify the page renders correctly in both locales

## EN Customization (Client-Specific Wording)

The `en.json` file is also used to customize English wording per client/deployment, not just for translating to another language. This allows different clients to use their own terminology while keeping the codebase unchanged.

### Current Client Mapping

This deployment uses the following terminology overrides:

| System Term (key) | Client Display (EN value) | Concept                  |
|--------------------|---------------------------|--------------------------|
| Project            | Job Site                  | Top-level entity         |
| Job Site           | Lot                       | Sub-level within project |

### Key Examples

**Project → Job Site** (sidebar, CRUD, headers):
- `"Projects"` → `"Job Sites"`, `"Create Project"` → `"Create Job Site"`, `"Project Name"` → `"Job Site Name"`

**Job Site → Lot** (nav, forms, messages):
- `"Job Sites"` → `"Lots"`, `"Add Job Site"` → `"Add Lot"`, `"Job Site Name"` → `"Lot Name"`

### Unchanged Terms

- **Project Manager** — kept as-is (role name, not a module)
- Generic field labels (`Contact Person`, `Email`, `Status`, etc.) — no renaming needed

### How It Works

When `APP_LOCALE=en`, Laravel's `__('Job Site Name')` looks up the key in `en.json` and returns `"Lot Name"`. Without the en.json entry, it would return the key itself (`"Job Site Name"`). This means only keys that need renaming require en.json entries for EN locale.

## Important Notes

- Enum labels (e.g., `ProjectStatus`) are translated via `__()` in the enum's `label()` method
- Date formatting should use `config('app.locale')` or Carbon's localization
- Currency symbols may differ by country — already handled via `config('app.country')`
- Do NOT translate route names, wire:model properties, or technical identifiers
- Placeholder text in inputs should also be translated
- `Str::plural()` calls are left as-is (English pluralization only)
- Dynamic status labels use `__(ucfirst($status))` pattern (e.g., `__(ucfirst($expense->status))`)
- Brand names like `PIX` are kept as-is (not translated)
- Payment method labels use `__(str_replace('_', ' ', ucfirst($method)))` for dynamic translation
- When a module exists at both project and job site level, translate both locations (same keys are reused)
