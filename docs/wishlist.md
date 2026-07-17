# Wishlist / Improvement Backlog

Items agreed as good additions but not yet scheduled. Keep entries short; move to its own doc when implemented.

## Code Quality

- **Consolidate subcontractor delete logic** — the deletability rule (`no contracts && no payment batches`) and the transactional delete with document-file cleanup are duplicated in `SubcontractorIndex` and `SubcontractorShow`. Move to `Subcontractor::isDeletable()` / `deleteWithDocuments()` model methods, and extract the duplicated delete-confirmation modal markup (3 copies incl. `client-index`) into a shared Blade component. Also cache the contract-form employee dropdown query (populate on select/clear instead of per-render). *(from code review, July 2026)*

- **`<x-ui.phone-input>` component** — the `x-phone-mask` attribute is hand-added to 16 phone inputs, which already drifted (`type="text"` vs `type="tel"`, inconsistent placeholders). A shared Blade component would make the mask, `type=tel`, and country-aware placeholder automatic for every future phone field. *(from code review, July 2026)*

- **`fputcsv()` PHP 8.4 deprecation** — all report CSV exports (Expense, Accounts Payable, Payment Schedule) call `fputcsv()` without the explicit `$escape` parameter, which logs deprecation notices on PHP 8.4. One-line fix per call site when touching those files.

## Open Decisions

- **Employee management permissions** — subcontractor employee add/delete is currently open to all users (same as documents); deleting an employee unlinks it from contracts. Decide: leave open, delete admin-only, or all employee management admin-only.
