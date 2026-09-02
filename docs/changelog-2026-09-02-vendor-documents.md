# Changelog — vendor documents: renewal, badges, reminders (2026-09-02)

A subcontractor's insurance, licences and certidões were filed with an expiration date
that nothing read except a badge on the vendor page — and the badge was wrong: Carbon 3
made `diffInDays()` signed, so **every dated document showed "Expiring Soon"**. There was
no way to replace a document except deleting it, nothing on the vendor list, and no e-mail
to anyone.

Now: a **renewal chain** (the old document steps aside and points at the new one),
**archive** with a reason, **badges** on the list and the page, **reminder e-mails** at
30, 15 and 7 days before the date and the day after, to people chosen in System Settings,
a **Document Types** screen, and a country-aware seeder. Two abilities join the permission
module, and two holes are closed on the way.

Full reference: **[Vendor Documents](./vendor-documents.md)**. Plan and phase log:
[vendor-document-expiry-plan.md](./vendor-document-expiry-plan.md).

---

## 1. What changed

| File | What |
|---|---|
| `database/migrations/2026_09_02_130000_add_renewal_to_subcontractor_documents_table.php` | **New.** `status`, `superseded_by_id`, `archived_at/by/reason`, four `notified_*_at` stamps, index. Every existing row becomes `active` by default |
| `database/migrations/2026_09_02_130001_add_is_active_to_document_types_table.php` | **New.** Retire a type without touching its documents |
| `database/migrations/2026_09_02_130002_grant_vendor_document_abilities.php` | **New.** Hands `vendors.renew_documents` + `vendors.archive_documents` to every role and override holding `vendors.edit` |
| `database/migrations/2026_09_02_130003_seed_document_types_for_country.php` | **New, empty.** Superseded by `130004` before it shipped |
| `database/migrations/2026_09_02_130004_add_key_to_document_types_table.php` | **New.** Stable `key` on `document_types`, then the add-only seed for the install's country |
| `database/seeders/DocumentTypeSeeder.php` | Add-only, keyed (`us.w9`, `br.cnd_federal`, `other`), `typesFor('US' \| 'BR')`; claims pre-key rows by name once |
| `config/permissions.php` | `vendors.renew_documents`, `vendors.archive_documents` (sensitive). Catalogue 33 / 172 |
| `app/Models/SubcontractorDocument.php` | Lifecycle (`status`) vs expiry (`expiry_status`); scopes; `supersedeWith()`, `archive()`, `reactivate()`; the signed-diff bug fixed |
| `app/Models/DocumentType.php` | `is_active`, `active()` |
| `app/Models/Concerns/HasDocumentHealth.php` | **New.** `withDocumentHealth()`, `documentHealth()`, `document_health` — on `Subcontractor` and `Vendor` |
| `app/Livewire/Subcontractor/SubcontractorShow.php` | Guards on upload/delete; `startUpload`, `startRenewal`, `startArchive`, `archiveDocument`, `cancelArchive`, `reactivateDocument`; per-type groups, required-types card, counts |
| `resources/views/livewire/subcontractor/subcontractor-show.blade.php` | Documents tab rebuilt; upload/renew `2xl` dialog; archive dialog; header badge |
| `app/Livewire/Subcontractor/SubcontractorIndex.php` + blade | Documents column, `?documents=` filter, Clear Filters, empty states |
| `resources/views/components/vendor/document-health.blade.php` | **New.** The badge chip |
| `app/Http/Controllers/FileController.php` | `subcontractor-documents/` now answers to `vendors.view`; unclaimed paths are 404 |
| `app/Services/VendorDocumentNotifier.php` | **New.** The four stages, one mail per person per morning, recipients + fallback |
| `app/Mail/VendorDocumentExpiryMail.php` + `resources/views/emails/vendor-document-expiry.blade.php` | **New.** Grouped by vendor, both locales |
| `app/Console/Commands/NotifyVendorDocumentExpiry.php`, `routes/console.php` | `vendors:notify-document-expiry` at 07:15 |
| `app/Models/NotificationSetting.php`, `NotificationLogEntry.php` | `vendor_document_expiry`, `VENDOR_KEYS`, `vendorDocumentRecipientIds()` |
| `app/Livewire/SystemSettings/NotificationSettings.php` + blade | **Vendor E-mails** card with the recipients picker |
| `resources/views/livewire/settings/notifications.blade.php` | Personal opt-out under *Vendors* |
| `app/Livewire/SystemSettings/DocumentTypeSettings.php` + blade, `settings-index.blade.php` | **New.** The Document Types tab |
| `lang/en.json`, `lang/pt_BR.json` | ~110 strings, including every seeded type name and description |
| `tests/Feature/Permissions/VendorDocumentsTest.php`, `VendorDocumentRemindersTest.php`, `DocumentTypeSettingsTest.php` | **New.** 45 tests |
| `docs/permissions-module.md`, `CLAUDE.md` | Ability count 170 → 172 |
| `database/migrations/2026_09_02_130006_retire_foreign_document_types.php` | **New.** Gives every seeded row its key (from either list) and retires the other country's types that hold no documents |
| `database/migrations/2026_09_02_130005_add_file_upload_id_to_subcontractor_documents_table.php` | **New.** `file_upload_id` → `file_uploads`; `file_path` nullable. Legacy rows untouched |
| `app/Http/Controllers/SubcontractorDocumentController.php`, `routes/web.php` | **New.** `subcontractors/{vendor}/documents/{document}/download` behind `ability:vendors.view`, serving legacy paths and uploaded files alike; the vendor in the URL is checked |
| `app/Services/FileUploadService.php` | Target `vendor` (guard `vendors.renew_documents`, key `vendors/{id}/…`); `storeThroughPhp()` for installs without a bucket; orphans waiting on a vendor pruned |
| `app/Livewire/Subcontractor/SubcontractorShow.php` + blade | The dialog carries `<x-ui.file-uploader>` with a bucket (file up first, row on save, abort on cancel) and the `<x-ui.file-drop>` without one; every download link is `downloadUrl()` |
| `resources/views/components/ui/toggle.blade.php` | **Pre-existing bug fixed.** The switch dropped `wire:click` and never rendered `checked`, so every company switch on System Settings › Notifications (task, purchasing and now vendor) neither fired nor showed its state. Click handlers are forwarded and the input carries `:checked`; the rows rebuild on a flip |

## 2. What moved on production

- **Uploading a vendor document now needs `vendors.renew_documents`** and deleting one
  needs `vendors.delete`. Both were open to anyone who could open the page. The grant
  migration keeps every editor able to upload; deleting was always meant to be admin-only.
- **Vendor document files now need `vendors.view`.** They were served to anyone signed in.
- **A Brazilian install gains nine certidões** as document types, and the American ones
  it was set up with are **retired** unless a document is filed under them — those stay
  active so nothing leaves the watch behind the owner's back. Nothing is renamed or removed;
  the Document Types tab reactivates anything wanted back.
- **New vendor documents no longer pass through PHP** on an install with a bucket: the
  10 MB cap is gone and the shared attachment limit applies. Files filed before stay on the
  server's disk and download exactly as before, through the new route.
- **Nothing is mailed until the scheduler runs the new command**, and by default the mail
  reaches everyone who may renew vendor documents (administrators included). Pick the
  recipients on System Settings › Notifications if that is too many.

## 3. The review pass (phase 7, same day)

A code review of the whole diff found fourteen things, all fixed before the branch was
handed over: the digest crashed for every recipient when any candidate belonged to a vendor
that had lost its subcontractor flag (and stamped the stages anyway); deleting a renewal
stranded the document it replaced; a document under a retired type could never be renewed;
an SMTP outage swallowed the stage for good; a padded name slipped past the unique check;
the required card ignored retired types while the badge and reminders counted them; an
undated document under a dated type passed for current; the seeder found rows by a name the
screen can rename; `whereDate()` defeated the new index. Details in
`docs/vendor-document-expiry-plan.md` §9.

## 4. Still to do

The screen walk in both themes, both locales and on a phone, and the items parked in
`docs/review-and-improvements.md` under *Vendor documents*.
