# Vendor Unification (Suppliers + Subcontractors)

Suppliers and subcontractors are now one `vendors` table. A single company can be classified as a supplier, a subcontractor, or **both** (`is_supplier` / `is_subcontractor` flags), so it only has to be entered once. Requested by client, Aug 2026 — they were entering the same company twice.

## Status

- **Phase 1 (done, 2026-08-13):** unified table, data migration with FK remapping, models repointed. Zero visible GUI change; both menu pages keep working exactly as before.
- **Phase 2 (done, 2026-08-13):** "This company is also a supplier/subcontractor" checkboxes on all four create/edit forms; duplicate check while typing the company name on both create forms ("Use this company" flags the existing record instead of creating a new one — MySQL ci collation makes the match case/accent-insensitive); flag-aware delete (removing a dual company from one page only removes that classification; the record survives on the other page); classification badges (Supplier / Subcontractor) under the company name on both index pages — every category the record has is shown, a format that extends to future vendor types. Unchecking a classification is blocked while records reference that side (contracts/batches for subcontractor; expenses/catalog/POs for supplier).
- **Phase 3 (done, 2026-08-13):** admin-only Merge Duplicates page at `vendors/duplicates` (`Vendor\VendorDuplicates`), linked from both index headers. Shows groups of vendors whose normalized names match (`Vendor::normalizeName()` — lowercase, accent-stripped, alphanumerics only) with type badges and linked-record counts; "Keep this one" merges the rest of the group into it. A manual-merge section handles duplicates written too differently to auto-match. `Vendor::mergeInto($survivor)` combines flags, fills the survivor's empty fields from the loser, repoints all 7 FK tables, and deletes the loser — all in one transaction. Nothing is ever merged automatically.

## Schema

`vendors` (migration `2026_08_13_100000`): superset of the two legacy tables — `name`, `is_supplier`, `is_subcontractor`, contact person fields (`website`, `contact_name`, `contact_email`, `title`), `phone`, `email`, `description`, full address + coordinates, `created_by`, plus `legacy_supplier_id` / `legacy_subcontractor_id` (original row ids, kept for audit/rollback).

**No unique index on `name`** — pre-existing duplicates must be able to coexist until merged.

## Data migration (`2026_08_13_100001`)

1. Drops the FK constraints pointing at the legacy tables (by looking up constraint names in information_schema — safe to re-run).
2. Copies all suppliers (flagged `is_supplier`) and all subcontractors (flagged `is_subcontractor`; `company_name` → `name`) into `vendors`, then remaps every FK to the new vendor ids — all inside one guarded transaction so a partial failure can never remap twice.
3. Re-adds the FK constraints pointing at `vendors`, mirroring each column's original delete behavior.

FKs remapped: `expenses.supplier_id`, `catalog_items.supplier_id`, `purchase_orders.supplier_id` (SET NULL); `contracts.subcontractor_id`, `payment_batches.subcontractor_id` (SET NULL); `subcontractor_documents.subcontractor_id`, `subcontractor_employees.subcontractor_id` (CASCADE).

**The legacy `suppliers` and `subcontractors` tables are left in place, untouched, as a safety net.** The app no longer reads or writes them. Drop them in a future release once production is verified.

## Model behavior

- `Supplier` and `Subcontractor` both have `$table = 'vendors'`, each with a global scope on its flag and a `creating` hook that sets the flag. A dual-flagged row is returned by both models (same id).
- `Subcontractor->company_name` is an **accessor/mutator over the `name` column**. Property access and mass assignment with `company_name` still work everywhere.
- **Rule for queries:** any SQL-level reference for subcontractors — `where()`, `orderBy()`, `get([...])`, `pluck()`, and constrained eager loads like `subcontractor:id,name` — must use `name`, never `company_name`. (Watch out: `Client` also has a real `company_name` column; client queries are unrelated and unchanged.)
- Validation rules that referenced the old tables now use `exists:vendors,id`.

## Hardening round 2 (second code review, 2026-08-13)

- **Guards centralized on the model**: `Vendor::hasSupplierRecords()` / `hasSubcontractorRecords()` are the single source of truth used by every component — no more hand-copied exists() blocks.
- **The SubcontractorShow delete path** (missed in round 1) now unflags dual companies instead of hard-deleting, with matching modal text.
- **Plain supplier delete is now guarded too**: a supplier referenced by expenses/catalog items/POs cannot be deleted at all (mirrors the existing rule that subs with contracts can't be), so historical records never lose their vendor.
- **Document files always cleaned up**: every model over the vendors table (`Vendor`, `Supplier`, `Subcontractor`) shares a `deleting` hook (`DeletesVendorDocuments`) that removes subcontractor documents via Eloquent before the row goes, so the DB CASCADE can never orphan files on disk.
- **Migration rollback refuses to destroy data**: `down()` throws when post-migration vendors exist instead of silently deleting them; docblock states the destructive semantics plainly.
- **Module gating corrected**: middleware is first-match-wins, so `vendors.*` is owned solely by `projects`; the Merge Duplicates button on the Suppliers page (catalog module) checks `ModuleAccess::isEnabled('projects')` before rendering.
- Stale merge pages get "nothing to merge" instead of a false success; create/edit flag changes are single-write; supplier delete resets pagination; the shared duplicate partial uses `x-ui.button`.

## Hardening (first code review, 2026-08-13)

- **Classification removal is guarded on every path** (edit forms AND deletes): a classification cannot be removed while records reference that side — contracts/payment batches for subcontractor; expenses/catalog items/purchase orders for supplier. This prevents historical records from silently losing their vendor through the flag-scoped relations.
- **Removing a classification never destroys child data**: deleting a dual company from the Subcontractors page keeps its documents and employees (restored by re-flagging); the modal and confirm texts state exactly what will happen in the dual case.
- **Supplier delete is admin-only** (button `@admin`-gated + `authorizeAdmin()` server-side), matching the subcontractor side.
- **Validation is classification-scoped**: all vendor FK rules use `exists:vendors,id,is_supplier,1` / `...,is_subcontractor,1`, so a tampered payload can't attach the wrong vendor type.
- **Group merges are atomic**: one outer transaction wraps the whole group; a stale page (record already merged elsewhere) gets a friendly error, not a partial merge.
- Vendors whose names normalize to an empty string (all symbols) are never grouped as duplicates.
- The referencing-table list lives in `Vendor::SUPPLIER_FK_TABLES` / `SUBCONTRACTOR_FK_TABLES` — **any new table with a vendor FK must be added there** or merges will strand its rows.
- The duplicate check is a shared concern (`ChecksVendorDuplicates` + `livewire/shared/vendor-duplicate-matches` partial) and only queries when the name field changes.
- `vendors.*` is registered under **both** the `projects` and `catalog` modules, since the merge tool is reachable from both sides.

## Gotchas

- A company flagged as subcontractor via the checkbox (from the supplier side) starts with no contact person; the Subcontractor edit form requires those fields on its next save there. Merge fills empty fields from the merged record automatically.
- Merges are irreversible (confirmed twice in the UI); the loser row is deleted after its history is repointed.
- New-install migration order is safe: legacy create-table migrations still run first, then the vendors migrations find both tables empty and just set up the schema.

## Deployment

Standard `php artisan migrate` + `php artisan view:clear`. The data migration runs inside the deploy migration; on large installs it is a single set-based INSERT/UPDATE pass (no row loops). Verified locally with overlapping ids across the two legacy tables (the collision case) — all FK relationships confirmed intact by name comparison before/after.
