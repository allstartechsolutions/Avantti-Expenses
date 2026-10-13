# Vendor documents: the "expiring soon" window is set per document type (13 Oct 2026)

Every vendor document counted as **expiring soon** 30 days before its date, whatever it
was — a one-year insurance certificate and a 30-day certidão alike. A certidão that is only
ever valid for 30 days was therefore *expiring soon* from the day it was filed.

Now each document type chooses its window on **System Settings › Document Types** —
**7, 15 or 30 days** before the date, **default 30**. Full reference:
[vendor-documents.md](./vendor-documents.md).

## What follows the setting

| Where | Effect |
|---|---|
| Document badge and countdown (vendor page) | Amber from the type's window, not from day 30 |
| Vendor badge, *Documents* filter and counts on the Subcontractors list | Same rule, computed in the query |
| Contract screen notice (`documentsNeedingAttention()`) | Same rule |
| Reminder e-mails | The sequence starts at the type's window: a 15-day type gets the 15 and 7-day mails, a 7-day type only the 7-day one, then the day after. Skipped stages are not stamped, so *Reminders sent* never lists a mail that did not go out |

## Files

| File | What |
|---|---|
| `database/migrations/2026_10_13_100000_add_expiry_warning_days_to_document_types_table.php` | **New.** `expiry_warning_days` smallint, default 30 |
| `app/Models/DocumentType.php` | `EXPIRY_WARNING_OPTIONS`, `DEFAULT_EXPIRY_WARNING_DAYS`, fillable + cast |
| `app/Models/SubcontractorDocument.php` | `EXPIRING_SOON_DAYS` removed; `expiry_warning_days` accessor; `dueWithinWarning()` scope; `expiringWithin()` and `expiry_status` use the type's window |
| `app/Models/Subcontractor.php` | `documentsNeedingAttention()` uses `dueWithinWarning()` |
| `app/Services/VendorDocumentNotifier.php`, `NotifyVendorDocumentExpiry.php` | Stages wider than the type's window are skipped |
| `app/Livewire/SystemSettings/DocumentTypeSettings.php` + blade | *Consider expiring soon* select (dated types only), validated to 7/15/30; *Warns N days before* on each row |
| `resources/views/livewire/subcontractor/subcontractor-show.blade.php` | Countdown colour reads the document's window |
| `resources/views/emails/vendor-document-expiry.blade.php` | Footer says the sequence starts at the type's warning |
| `lang/en.json`, `lang/pt_BR.json` | 6 strings (one replaced) |
| `tests/Feature/Permissions/VendorDocumentsTest.php`, `VendorDocumentRemindersTest.php`, `DocumentTypeSettingsTest.php` | 3 new tests |

## What moved on production

Nothing on deploy: every existing type gets 30, which is what the system used before. Behaviour
changes only for a type someone sets to 15 or 7. No permission changes — the field is on a
screen already behind `settings.edit`.

## Deploy

`php artisan migrate`.
