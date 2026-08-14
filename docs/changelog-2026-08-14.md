# Changelog — 2026-08-14

## UI: Icon-Only Table Action Buttons

All action buttons inside index/listing table rows converted from text+icon (`x-ui.button`) to icon-only (`x-ui.icon-button`) with `title` tooltips. Text buttons made action columns too wide.

- `x-ui.view-edit-buttons` shared component converted to icon-only (affects every table using it).
- Converted index pages: user, supplier, client, project, invoice, estimate, payment-batch, catalog items, catalog categories, cost code templates.
- Kept `x-ui.button` (with text) for page headers, empty states, filters, modals, and forms.
- Pattern guide: `docs/table-action-buttons.md`.

## UI: Name Truncation with Hover Tooltips

Long names truncate at 30 characters (`Str::limit(..., 30)`) with the full name in a `title` hover tooltip:

- **Contract Payments** (`contract-payments.blade.php`): Subcontractor, Project (+ client sub-line), Job Site / Lot.
- **Payments Dashboard** (`payment-dashboard.blade.php`): Project and Job Site.
- **Projects index**: Client company name.

## Translations: Full Codebase Coverage

Completed translation coverage for the entire application (see `docs/translation-system.md` for details):

1. **Missing keys backfilled** for already-wrapped pages: Contract Payments (page, CSV, PDF), sidebar, auth, settings/2FA, dashboard, all reports + report PDFs (~330 keys).
2. **Previously-unwrapped modules wrapped in `__()` and translated**: Payments Dashboard, Payment Batches (all 4 pages), Cost Code Templates (all 4 pages), Estimates, Invoices (incl. public payment page), Catalog, Clients, Contracts, Budgets, Daily Reports, System Settings, invoice/estimate/daily-report PDFs, estimate email template (~70 blade files, 683 keys).
3. **All Livewire flash messages wrapped** (115 total); interpolated messages use `:placeholder` syntax.
4. `pt_BR.json` now holds 2,010 keys; `en.json` 1,461. Zero missing keys verified by full-codebase scan.

Terminology follows Brazilian standards (per user preference, saved to memory): Lote de Pagamento, Código de Custo, Subempreiteiro, Alíquota, Regime de Caixa/Competência, Contas a Pagar, etc.

**Intentionally untranslated**: `welcome.blade.php` (internal style guide) and `resources/views/flux/` (vendor components).

**Follow-up to review**: new `en.json` keys containing "Project"/"Job Site" were added as identity mappings; the client terminology overrides (Project → Job Site, Job Site → Lot) have not been applied to them yet.
