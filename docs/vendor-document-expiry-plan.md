# Vendor Documents — Renewal, Expiry Badges and Reminder E-mails

Implementation plan, written 2 Sep 2026. Status: **built and reviewed** on branch
`vendor-document-expiry` (see §9); only the owner's screen walk remains.

## 1. Why

A subcontractor's insurance certificates, licences and tax clearances are filed on the
vendor page with an expiration date, and until today the only thing that read that date
was a badge in the documents table — and the badge was wrong (Carbon 3 made `diffInDays()`
signed, so every dated document showed *Expiring Soon*). Nothing e-mails anyone, nothing
shows on the vendor list, and there is no way to replace a document except deleting it and
uploading again, which loses the history a compliance audit needs.

## 2. Decisions taken with the owner (2 Sep 2026)

| Decision | Answer |
|---|---|
| Block purchase orders / contracts for a vendor with an expired document? | **No.** Flag only. Revisit later. |
| Reminder schedule | **Fixed**: 30, 15 and 7 days before expiry, and 1 day after. Not editable on screen. |
| Who receives the reminders | **A setting** on the Notification Settings screen: a picker of users. Empty list falls back to everyone holding `vendors.renew_documents`, and the screen says so. |
| Who may renew / archive a document | **Two new abilities in the permission module**, not a role check and not piggy-backed on `vendors.edit`. |
| Document types | Already a **table** (`document_types`, 8 seeded US rows, no admin screen). Production has documents filed under them, so every change is additive and existing rows are never renamed or deleted. |
| Replacing a document | A **renewal chain** (old → superseded, pointer to the new one), not a versioning system. Plus **archive** for "no longer required", with a reason. |

## 3. Data changes (all additive, `php artisan migrate` only)

### 3.1 `subcontractor_documents`

```
status              string(20)  default 'active'   -- active | superseded | archived
superseded_by_id    FK → subcontractor_documents, nullable, nullOnDelete
archived_at         timestamp nullable
archived_by         FK → users, nullable, nullOnDelete
archive_reason      string nullable
notified_30_at      timestamp nullable
notified_15_at      timestamp nullable
notified_7_at       timestamp nullable
notified_expired_at timestamp nullable
index (status, expiration_date)
```

Every existing row becomes `active` through the default — nothing on production changes
behaviour until someone renews or archives. The four `notified_*_at` stamps follow the
`tasks.overdue_notified_at` / `quotations.due_notified_at` convention: one column per stage,
so "who still needs stage X" is a plain `whereNull()`, and a reminder can never repeat.

### 3.2 `document_types`

```
is_active   boolean default true
```

A retired type disappears from the upload picker but keeps every document already filed
under it. The seeder switches to *add-only*, keyed by a stable `document_types.key` rather
than the name the screen can change: it may add the Brazilian list on a BR install, it never
renames or removes a row that exists, and (once, from migration `130006`) it retires the
other country's types that hold no documents. The BR list
(to confirm with the owner before seeding): CND Federal, CND Estadual, CND Municipal, CRF do
FGTS, CNDT (Trabalhista), Alvará de Funcionamento, Contrato Social, Apólice de Seguro RC,
PCMSO/PGR. Seeded names get both lang entries so the picker translates; a custom name typed
on the admin screen shows as typed.

### 3.3 `notification_settings`

One new key, `vendor_document_expiry`, `is_enabled => true`, `options => ['recipients' => []]`,
inserted by migration exactly as the task and procurement keys were.

## 4. Model and status logic

- `SubcontractorDocument::EXPIRING_SOON_DAYS = 30` stays the badge window (already in place).
- `status` accessor (Expired / Expiring Soon / Valid) only makes sense on **active** rows; the
  accessor returns `valid` for superseded and archived documents so nothing downstream has to
  special-case them. Labels already go through `__()` with document-gendered pt_BR keys.
- Scopes: `active()`, `requiringExpiry()` (joins the type flag), `expiringWithin($days)`,
  `expired()`.
- `Vendor::documentHealth()` — worst state across active documents whose type requires
  expiry: `expired` > `expiring_soon` > `valid` > `none`. Loaded on the index through a
  `withDocumentHealth()` scope (one subquery for the earliest active expiring date), never
  by iterating documents per row.
- `renew(UploadedFile, Carbon $expires, ?string $notes, User $by)` on the document: creates
  the new row with the same type, marks `$this` superseded with `superseded_by_id`, inside a
  transaction. `archive(string $reason, User $by)` / `reactivate()` likewise.

## 5. Permissions (declared before the first screen, per `docs/permissions-for-new-modules.md`)

Two actions added to the existing `vendors` area, `swept` left `true` only once §5 of the
checklist below is complete in the same change:

```php
'renew_documents'   => ['name' => 'Upload and renew documents'],
'archive_documents' => ['name' => 'Archive and reactivate documents', 'sensitive' => true],
```

**Reproduce first.** Today upload is guarded by `vendors.edit` and delete by
`vendors.delete`. Moving upload under `renew_documents` would silently narrow who can
upload, so the seeder grants `renew_documents` and `archive_documents` to every role and
template that currently holds `vendors.edit`. After that grant, behaviour is identical and
the two abilities are revocable on the access screens. Delete stays on `vendors.delete`.

The Document Types admin screen and the new Notification Settings section sit under the
existing `settings.view` / `settings.edit`, as the rest of System Settings does.

## 6. Screens

### 6.1 Vendor detail — Documents section (rebuilt)

- **One row per active document**, grouped by type, showing file, expiry date (`appDate()`),
  status badge, uploaded by / at, notes.
- **Actions per row**: Download · Renew (`renew_documents`) · Archive (`archive_documents`) ·
  Delete (`vendors.delete`). Superseded and archived rows show Reactivate (archived only) and
  Download.
- **History expander** under each type: the superseded chain, oldest last, each with who
  replaced it and when; archived rows with their reason. Never hidden, never deleted.
- **Renew** opens a `2xl` dialog (a handful of fields on one record — see the modal size
  rule): the type shown read-only, `<x-ui.file-drop>` for the file, `<x-ui.date-input>` for
  the new date, notes. On save the old row is superseded, the badge recomputes, and the
  reminder sequence for the old row stops because it is no longer active.
- **Archive** is a small dialog with a required reason.
- **Header badge** on the vendor page: worst active status, red for expired, amber for
  expiring, nothing when all is current.
- **Empty and partial states**: no documents → what the required types are and a button to
  add the first; all present but one expired → the badge names the type.

### 6.2 Vendor index

A badge column (worst active status) with the count of affected documents in the tooltip,
plus a filter "Documents: expired / expiring / current". Loaded through
`withDocumentHealth()`, no N+1.

### 6.3 Notification Settings — new *Vendor Document E-mails* section

- One switch, `vendor_document_expiry`.
- The schedule stated in plain words: *30, 15 and 7 days before the expiry date, and the
  day after it passes. Each is sent once per document; renewing or archiving stops the
  sequence.*
- **Recipients**: a checkbox list of active users, saved into `options.recipients`. Below it:
  *Nobody selected — reminders go to everyone who may upload and renew vendor documents.*
- Save through the existing `settings.edit` guard, following `saveProcurementOptions()`.

### 6.4 System Settings — Document Types (new)

Full-page component `SystemSettings\DocumentTypes`: list with name, description,
*requires expiration*, sort order, active toggle, count of documents filed under each
(so an admin sees why a type cannot be deleted — it cannot: retire it instead). Create and
edit in a `2xl` dialog. Guarded by `settings.view` / `settings.edit`.

## 7. Reminder e-mails

- `App\Services\VendorDocumentNotifier::sendExpiryReminders(): array`, following
  `ProcurementNotifier`: bails if the key is disabled, resolves recipients (setting, else
  fallback), then for each stage selects active documents whose type requires expiry, whose
  date is exactly `today + 30/15/7` (or `today − 1` for the expired stage) **or earlier and
  still un-stamped for that stage**, so a day the scheduler missed is caught the next
  morning rather than lost.
- **One e-mail per recipient per run**, listing every document that hit a stage that day
  grouped by vendor, rather than one e-mail per document — a company with forty subs does
  not want forty mails on the same morning. Stamps are written per document after send.
- `App\Mail\VendorDocumentExpiryMail`, both locales, dates through the macros, a link to
  each vendor page. Subject: `:count vendor document(s) need attention` via `trans_choice`.
- `vendors:notify-document-expiry` command scheduled `dailyAt('07:15')` with
  `withoutOverlapping()->sentryMonitor()`, beside the task and procurement commands in
  `routes/console.php`.
- Recipients who switched the mail off in their own preferences
  (`wantsNotification('vendor_document_expiry')`) are skipped, as with the task mails.

## 8. Phases (one page at a time, tested before the next)

1. **Status fix** — done 2 Sep 2026: signed-diff bug, constant, translated labels.
2. **Data + permissions** — done 2 Sep 2026: migrations §3, abilities §5 with the
   reproduce-first grant, model scopes and lifecycle, guards, file guard, permission test.
3. **Vendor detail documents section** — done 2 Sep 2026: §6.1 as built (see §9).
4. **Badges** — done 2 Sep 2026: vendor header + index column + filter (see §9).
5. **Reminders** — done 2 Sep 2026: settings section §6.3, notifier, mail, command,
   schedule, per-user opt-out, tests (see §9).
6. **Document Types screen + country-aware seeder** — done 2 Sep 2026; BR list confirmed by
   the owner the same day (see §9).
7. **Review and Improvements** — code review done 2 Sep 2026 (see §9); docs level
   (`docs/vendor-documents.md`, changelog, backlog V1–V7 in
   `docs/review-and-improvements.md`); **the screen walk in both themes / locales / phone is
   the owner's**, nothing here was viewed in a browser.

## 9. Already done (2 Sep 2026)

**Phase 1** — the expiry accessor compares against `today + 30` on whole days;
`EXPIRING_SOON_DAYS` constant; labels through `__()` with `Document expired` /
`Document expiring soon` / `Document valid` in `en.json` and `pt_BR.json`
(Vencido / Vencendo em breve / Válido — masculine, *documento*).

**Phase 2** — branch `vendor-document-expiry`:

- Migrations `2026_09_02_130000` (lifecycle + reminder stamps on `subcontractor_documents`),
  `130001` (`document_types.is_active`) and `130002` (the grant). Run on the local database.
- `SubcontractorDocument`: `status` is now the **lifecycle** column (`active` /
  `superseded` / `archived`); the derived expiry moved to `expiry_status`,
  `expiry_status_label`, `expiry_status_color`, `days_until_expiry`. Scopes `active()`,
  `requiringExpiry()`, `expired()`, `expiringWithin()`. `supersedeWith()`, `archive()`,
  `reactivate()`. `DocumentType::active()`.
- `vendors.renew_documents` and `vendors.archive_documents` (sensitive) declared. Catalogue is
  now 33 areas / **172** abilities.
- `SubcontractorShow`: `uploadDocument()` guarded by `renew_documents` (it had **no guard**
  before — any `vendors.view` holder could upload), `deleteDocument()` by `vendors.delete`
  (also unguarded before). New server actions `startRenewal()`, `startArchive()`,
  `archiveDocument()`, `cancelArchive()`, `reactivateDocument()`; the upload picker only
  offers active types; a renewal keeps the old document's type whatever the browser sends.
  **The buttons for these arrive in phase 3** — the methods exist so the abilities guard
  something real from the first commit.
- `FileController::authorizeFile()` now claims `subcontractor-documents/` for `vendors.view`;
  an unclaimed path is a 404. It fell through to "signed in" before.
- `tests/Feature/Permissions/VendorDocumentsTest.php` — 14 tests: catalogue, fresh seed,
  the grant migration (idempotent, roles and per-person overrides), reader cannot upload,
  renew chain keeps type, cross-vendor id refused, retired type refused, archive needs its
  own grant and records who/why, superseded cannot be renewed or archived, delete answers to
  `vendors.delete`, file served on view and refused without, the 30-day line.

**Phase 3** — the Documents tab of the subcontractor page, rebuilt:

- **Required documents** card on top: one tile per active type that requires a date, with
  the state (Missing / Expired / Expiring Soon / Valid), the date and "expires in N days" /
  "expired N days ago", and an Upload or Renew shortcut where the holder may act.
- **Documents** card: summary counts (active / expiring / expired / in history), then one
  block per type: heading with the worst active state, a table of the active documents
  (file, size, notes, what it replaced; expiry date + countdown; state chip; uploaded by /
  on; Download · History · Renew · Archive · Delete), and an **Archived** list per type.
  **History is per document** (the owner's correction, 2 Sep 2026: a dialog, and one chain
  per document — two policies of the same type must not share a history): `chainOf()` walks
  the "replaced by" pointers up to the head and down through every predecessor; the button
  carries the version count. Superseded rows are reached only through History.
- **Upload / Renew** is a `2xl` dialog (was an inline form). Renewing shows a banner naming
  the document being replaced and locks the type. **Archive** is a small dialog with a
  required reason. Both follow the page's existing "modal rendered while its flag is set"
  pattern with explicit Cancel buttons.
- Buttons appear only with the matching ability (`@can` on top of the server guards).
  Every string through `__()`, pt_BR added, dates through the macros. Two render tests
  cover every state and the reader's view.
- Not in this phase: the **header badge** on the vendor page and the index column (phase 4).

**Phase 4** — badges:

- `App\Models\Concerns\HasDocumentHealth` on `Subcontractor` and `Vendor`:
  `withDocumentHealth()` (three `withCount` sub-selects: expired, expiring, tracked),
  `documentHealth($state)` filter scope, `document_health` accessor (`expired` >
  `expiring_soon` > `valid` > `none`) that reads the loaded counts or falls back to the
  database, and `documentHealthLabel()`.
- `<x-vendor.document-health>` blade component: chip with icon, label and a tooltip of the
  counts; `mode="quiet"` shows nothing when all is current (lists), `mode="full"` always.
- Subcontractor **index**: a Documents column (full mode), a *Documents: all / expired /
  expiring soon / current / no dated documents* filter carried in the query string as
  `documents=`, a Clear Filters button, and empty states that name which filter emptied the
  list. An unknown filter value is ignored. Tested to add no query per row.
- Subcontractor **detail header**: the same chip beside the company name, clickable to jump
  to the Documents tab.
- Supplier screens are untouched: documents are a subcontractor feature.

**Phase 5** — reminders:

- `App\Services\VendorDocumentNotifier::sendExpiryReminders()`: four fixed stages
  (`STAGES` 30/15/7 → `notified_*_at`, `EXPIRED_STAGE` → `notified_expired_at`), "on or
  before" so a missed morning is caught the next one; a document inside several windows is
  listed once under the tightest stage and every passed stage is stamped; stamps written
  whether or not anybody could be mailed. One `VendorDocumentExpiryMail` per recipient per
  morning (window `digest:<date>` in `notification_log`, so a double run mails nobody twice),
  grouped by vendor with links to each vendor page and to the index pre-filtered.
- Recipients: `NotificationSetting::vendorDocumentRecipientIds()` from
  `options.recipients`, else `BuyerDirectory::holdersOf('vendors.renew_documents', null)`
  (active, non-guest). Personal opt-out through the existing `wantsNotification()`.
- Key `vendor_document_expiry` (`NotificationSetting::VENDOR_KEYS`) — no migration: an
  unknown key is "on" and the screen `firstOrCreate`s the row on first save.
- `vendors:notify-document-expiry` scheduled `dailyAt('07:15')`.
- System Settings › Notification Settings: a **Vendor E-mails** card with the switch and a
  **Who receives the reminders** checkbox grid of active staff; empty shows the fallback
  names, or an amber warning when nobody holds the ability. Guests and inactive people are
  refused on save. (Note: `Rule::exists()->where('is_guest', false)` fails on sqlite —
  the verifier binds `false` as an empty string — so the rule uses `0`.)
- Profile › Notifications gains a *Vendors* group with the one switch.
- `tests/Feature/Permissions/VendorDocumentRemindersTest.php` — 11 tests.

**Phase 6** — document types:

- `DocumentTypeSeeder` is **add-only**, keyed (`us.w9`, `br.cnd_federal`, `other`) and
  **country-aware**: `typesFor('US')` is the original eight, `typesFor('BR')` the nine
  certidões plus *Other*. Migration `130004` adds the key and seeds; `130006` claims keys for
  rows from either list and retires the other country's types that hold no documents, once
  (the owner did not want W9 and friends on the BR install). A type holding documents stays
  active; the screen retires or reactivates anything after that. `130003` never shipped.
- System Settings › **Document Types** tab (`SystemSettings\DocumentTypeSettings`): name,
  description, requires-expiration, sort order, active, and the count of documents filed
  under each. Create/edit in an `lg` dialog. **Retire / Reactivate** on every row; **Delete**
  only when nothing was ever filed under the type (the trash icon is greyed with a tooltip
  otherwise, and the server refuses it regardless). `settings.view` to look,
  `settings.edit` for every action.
- Seeded names and descriptions are translated on display through `__()`; every seeded
  string has both lang entries, and a test fails if a future seeded row lacks one. A custom
  name typed on the screen shows as typed.
- `tests/Feature/Permissions/DocumentTypeSettingsTest.php` — 5 tests.

**Phase 7** — the code review (14 confirmed findings, all fixed the same day):

| Finding | Fix |
|---|---|
| Digest crashed for every recipient when a candidate's vendor had lost `is_subcontractor` (scoped relation → null), and stamped the stages anyway | `requiringExpiry()` requires the owning vendor to be flagged; the mail uses the unscoped `vendor()` relation and is null-safe |
| Deleting an active renewal left its predecessor stuck as superseded | `deleting` hook restores the predecessor to active |
| Renewing under a retired type always failed validation | Renewals validate the type's existence only; first filings still need a live type |
| Stages stamped even when every delivery failed | Stamped unless `sent === 0 && failures > 0` |
| Unique rule saw the untrimmed name | Trimmed before validating |
| Required card ignored retired types; badge, filter and reminders counted them | Retired types are out of the watch everywhere (`requiringExpiry()`, `expiry_status`); the page marks the group *Retired type* |
| Undated document under a dated type read as valid and could be the Renew target | New `undated` state, ranked between expiring and valid; the shortcut targets the soonest dated document |
| Seeder keyed by a renameable name | `document_types.key`; migration `130004`; pre-key rows claimed by name once; `130003` emptied |
| `whereDate()` defeated the indexes; `whereHas` nested `EXISTS` per row | Plain comparisons and `whereIn` sub-selects; "before the day after" upper bound for sqlite |
| Third copy of `send()`; dead `usesFallbackRecipients()` | Dead method removed; the copy is V1 in the backlog (a deliberate decision, not an oversight) |
| Stamps never shown on the page | `reminded_stages` line under the status chip |
| `toggleUploadForm()` dead and unguarded | Removed |
| Expiry precedence written in several places | `SubcontractorDocument::worstExpiry()` |
| Active-staff predicate written three times | `BuyerDirectory::activeStaff()` made public and reused |
| Tests hard-coded seeded counts under the `APP_COUNTRY=US` pin | Country-agnostic names; seeder tests clear the table first |

**V2 (same day)** — new files through the shared upload path (`file_upload_id`), legacy
`file_path` rows untouched, one download route for both, drop-zone fallback without a
bucket, orphans pruned. See `docs/vendor-documents.md` › *Where the file is*.

**What moved on production, in one line:** uploading a vendor document now needs
`vendors.renew_documents` (granted to every role and override holding `vendors.edit`), and
deleting one needs `vendors.delete` — both were open to anyone who could open the page.

## 10. Open questions for the owner

- ~~Confirm the Brazilian document type list in §3.2 before it is seeded.~~ Confirmed 2 Sep 2026.
- Should the vendor index badge also count documents of types that do **not** require
  expiry but carry a date anyway? Plan says no: only required types drive badges and mails.
- Later: blocking POs / contracts on an expired required document (declined for now).

## 11. Permissions checklist (copied from `docs/permissions-for-new-modules.md`)

- [ ] `renew_documents` / `archive_documents` declared on the `vendors` area
- [ ] No menu change (the vendor pages already exist); Document Types added under the gear
- [ ] `ADMIN_ONLY_ABILITIES` / `MANAGER_ONLY_ABILITIES` reviewed — `archive_documents` sensitive
- [ ] Seeder grants both to every role / template holding `vendors.edit` (reproduce first)
- [ ] `mount()` guarded on `DocumentTypes`
- [ ] **Every** action method guarded: upload, renew, archive, reactivate, delete, type CRUD, settings save
- [ ] Document id from the browser checked against the vendor on screen, never trusted
- [ ] `FileController::authorizeFile()` covers superseded / archived files the same as active
- [ ] Index badge computed through a scope, never a per-row query
- [ ] No money on these screens
- [ ] `@can` on the buttons, never instead of the guard
- [ ] `tests/Feature/Permissions/VendorDocumentsTest.php`: reproduced, revocable, scoped, separate
- [ ] pt_BR strings in the same change, including the mail
- [ ] `AbilityCatalogTest` green (no declared ability left unguarded), full suite green
