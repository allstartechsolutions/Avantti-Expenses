# Vendor Documents — renewal, badges and reminders

Built 2 Sep 2026 on branch `vendor-document-expiry`. Plan and phase log:
[vendor-document-expiry-plan.md](./vendor-document-expiry-plan.md). Changelog:
[changelog-2026-09-02-vendor-documents.md](./changelog-2026-09-02-vendor-documents.md).

A subcontractor's compliance documents — insurance certificates, licences, certidões —
are filed on the vendor page with the date they stop being good. This module is what
happens around that date: the document can be **renewed** without losing the one it
replaces, the vendor list and the vendor page carry a **badge**, and the right people get
an **e-mail** at 30, 15 and 7 days before the date and the day after.

---

## 1. The model

`subcontractor_documents` carries two independent facts about a row:

| Fact | Column / accessor | Values |
|---|---|---|
| **Lifecycle** | `status` | `active` · `superseded` (a renewal replaced it; `superseded_by_id` points at the new row) · `archived` (no longer required; `archived_at`, `archived_by`, `archive_reason`) |
| **Expiry** (derived) | `expiry_status` | `valid` · `expiring_soon` (within `EXPIRING_SOON_DAYS` = 30) · `expired` · `undated` (a live dated type, no date — never passed off as current) — always `valid` for a row that is not active or whose type does not require a date or has been retired |

Only **active** documents under an **active type that requires an expiration date**,
owned by a vendor **still flagged as a subcontractor**, are *watched*: they alone drive the
badge, the required-documents card and the reminders (`scopeRequiringExpiry()` is the one
definition). Renewing or archiving a document ends its reminders; retiring a type takes all
of its documents out of the watch; a vendor that stops being a subcontractor keeps its files
but is nobody's compliance problem. **Deleting a renewal gives the document it replaced its
place back** (model `deleting` hook), so a wrong file cannot strand the previous certificate.

Scopes on `SubcontractorDocument`: `active()`, `requiringExpiry()`, `expired()`,
`expiringWithin($days = 30)` — plain comparisons on the DATE column, never `whereDate()`,
so the `(status, expiration_date)` index is used; the upper bound is "before the day after"
because sqlite keeps the date cast with a time suffix. `worstExpiry()` ranks a set of
states in one place; `reminded_stages` lists which reminders a row has had (shown on the
page). Actions: `supersedeWith($replacement)`, `archive($by,
$reason)`, `reactivate()`. Presentation: `expiry_status_label`,
`status_label`, `days_until_expiry` (negative once past).

`document_types.is_active` retires a type: it leaves the upload picker, the required card
and the watch, and every document already filed under it keeps it (the page marks the
group *Retired type*). A renewal under a retired type is still allowed and keeps that
type. `document_types.key` (`us.w9`, `br.cnd_federal`, `other`) is the stable identity the
seeder uses; a type made on the screen has none.

### Where the file is

Two kinds of row live side by side, and `downloadUrl()` hides the difference:

| Filed | Column | Bytes | Served by |
|---|---|---|---|
| **Before 2 Sep 2026** | `file_path` | `subcontractor-documents/{vendor}/…` on the server's private `local` disk | `SubcontractorDocumentController@download` streams it (and the legacy `FileController` path still answers to `vendors.view`) |
| **Since** | `file_upload_id` → `file_uploads` | `vendors/{vendor}/{uuid}/{name}` on `DocumentSettings::disk()` — R2 when configured, the private disk otherwise | the same controller: a short-lived bucket URL on R2, a stream otherwise |

Nothing is migrated: the old files stay where they are and keep working. Deleting a row
removes whichever it has (the stored object and the `file_uploads` row, or the local path).

**How a new file gets in.** With a bucket, the dialog carries `<x-ui.file-uploader
targetType="vendor">`: the bytes go straight to storage against the **vendor** (target
`vendor` in `FileUploadService`, guarded by `vendors.renew_documents`), the uploader calls
`documentFileUploaded($fileId)`, and saving the dialog writes the document row and moves the
file onto it. Cancelling, or dropping a second file, aborts the first (object and row).
Without a bucket the dialog keeps the `<x-ui.file-drop>` and `uploadDocument()` stores the
Livewire file through `FileUploadService::storeThroughPhp()` — same row shape, same key, same
disk rules. A file left waiting on a vendor past the stale window (a closed tab) is removed
by `documents:prune-uploads`. Note the trap in `abortPendingFile()`: Livewire memoises a
`get…Property` accessor for the request, so the abort must read the file fresh or it deletes
the document's own file right after the save.

`App\Models\Concerns\HasDocumentHealth` (on `Subcontractor` and `Vendor`) reduces a
vendor to one word — `expired` > `expiring_soon` > `valid` > `none` — through
`withDocumentHealth()` (three `withCount` sub-selects, so a list costs no query per row),
`documentHealth($state)` as a filter, and the `document_health` accessor.

## 2. Screens

**Subcontractor › Documents tab.** A *Required documents* card (one tile per active type
that requires a date: Missing / Expired / Expiring Soon / Valid, with the countdown and an
Upload or Renew shortcut), then one block per type: the active documents in a table with
Download · History · Renew · Archive · Delete, then an **Archived** list for that type
(reason, who, when; Reactivate). **History is per document, not per type**: the button
(labelled with the number of versions) opens a `2xl` dialog with that document's chain —
what replaced it, itself, what it replaced — newest first, with lifecycle and expiry chips,
file, expiry with countdown, uploaded by/on, replaced/replaced-by, archived by/when/why,
reminders sent, notes, and Download, Renew, Archive, Reactivate and Delete on each entry.
Two policies of the same type are two chains; a superseded document is reached only through
the History of the document that replaced it. Upload and Renew share a `2xl` dialog; renewing locks
the type and shows what is being replaced. Archive is a small dialog with a required
reason.

**Subcontractor index.** A Documents column (`<x-vendor.document-health mode="full">`), a
*Documents: all / expired / expiring soon / current / no dated documents* filter carried as
`?documents=` in the URL, and empty states that name which filter emptied the list.

**Subcontractor header.** The same chip beside the company name, clickable to the tab.

**System Settings › Document Types.** Name, description, requires-expiration, sort order,
active flag, and the count of documents under each. Retire / Reactivate on every row;
Delete only when nothing was ever filed under it.

**System Settings › Notifications › Vendor E-mails.** One switch for the sequence, and the
*Who receives the reminders* picker (active staff, never guests). Empty means the fallback,
and the screen names who that reaches.

**Profile › Notifications › Vendors.** The personal opt-out.

## 3. Reminders

`vendors:notify-document-expiry`, scheduled `dailyAt('07:15')` in `routes/console.php`,
runs `App\Services\VendorDocumentNotifier::sendExpiryReminders()`:

- **Stages** are fixed: `notified_30_at`, `notified_15_at`, `notified_7_at`,
  `notified_expired_at`. Each is written once. The date test is "on or before", so a
  morning the scheduler missed is caught the next one. A document inside several windows at
  once is listed once, under the tightest stage, and every stage it passed is stamped.
- **One e-mail per recipient per morning** (`VendorDocumentExpiryMail`), grouped by vendor
  with a link to each vendor page and to the index pre-filtered. The `notification_log`
  window `digest:<date>` refuses a second copy the same day, so a double run is safe.
- **Recipients**: `NotificationSetting::vendorDocumentRecipientIds()` (the picker), else
  everyone holding `vendors.renew_documents` (`BuyerDirectory::holdersOf`, active and not a
  guest). `wantsNotification('vendor_document_expiry')` is the personal opt-out.
- Stamps are written when the stage went out and when nobody wanted it (so a document
  nobody is set up to hear about does not retry every morning forever) — but **not when
  every delivery failed**: an SMTP outage leaves the stage owed for the next morning.

## 4. Permissions

Two actions on the existing `vendors` area (catalogue: 33 areas, 172 abilities):

| Ability | Guards |
|---|---|
| `vendors.renew_documents` | `uploadDocument()`, `startUpload()`, `startRenewal()` |
| `vendors.archive_documents` (sensitive) | `startArchive()`, `archiveDocument()`, `reactivateDocument()` |
| `vendors.delete` (existing) | `deleteDocument()` |
| `vendors.view` (existing) | the page, and the file itself through `FileController::authorizeFile()` |
| `settings.view` / `settings.edit` | Document Types, the recipients picker |

Migration `2026_09_02_130002` handed the two new abilities to every role and per-person
override holding `vendors.edit`. **Before this module, upload and delete had no guard of
their own** — any `vendors.view` holder could do both, and the files were served to anyone
signed in.

## 5. Seeding

`DocumentTypeSeeder` is add-only and country-aware: `typesFor('US')` is the original
eight, `typesFor('BR')` nine certidões plus *Other*. Each seeded row is found by its stable
`key`, never by the name the screen can change; rows seeded before the key existed are
claimed once by their original name. Before the `key` column exists (the table's own
migration seeds it) it falls back to name. Migration `2026_09_02_130004` adds the column and
runs the seed on deploy (`130003` is an empty placeholder that never shipped); `130006`
claims keys for rows from *either* list and retires the other country's types that hold no
documents, once (`DocumentTypeSeeder::retireForeignUnused()`). Names and
descriptions are translated on display through `__()`; `DocumentTypeSettingsTest` fails if
a seeded row lacks a lang entry.

## 6. Tests

| File | Covers |
|---|---|
| `tests/Feature/Permissions/VendorDocumentsTest.php` | abilities, the grant migration, upload/renew/archive/delete guards, cross-vendor ids, the file guard, the 30-day line, the tab in every state, the header badge, the index column + filter with no query per row |
| `tests/Feature/Permissions/VendorDocumentRemindersTest.php` | the four stages, a double run, a missed morning, stop-on-renew/archive, recipients and fallback, opt-outs, the command, the settings screen |
| `tests/Feature/Permissions/DocumentTypeSettingsTest.php` | the screen's grants, create/edit/retire/reactivate, retire-not-delete, the add-only country-aware seeder, lang coverage |

## 7. Decisions

- **No blocking** of purchase orders or contracts on an expired document. Flag only.
- **Fixed stages** (30/15/7/+1), not editable on screen.
- **Renewal chain, not versioning**: one pointer, one history list.
- **Types are never deleted while in use**; retire instead.
- **Supplier screens are untouched**: documents are a subcontractor feature.
