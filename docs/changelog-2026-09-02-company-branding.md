# Changelog — the customer's own mark (2026-09-02)

Every install showed the same icon and the same name: `APP_LOGO_URL` on the tab,
in the sidebar, on the sign-in card, and `config('app.name')` beside it. A
customer could change it only by editing `.env`. They can now set their own
**display name, app icon, dark-mode icon and favicon** on the Company
Information screen, and **whatever they leave empty still shows the product's
mark** — an install that touches nothing looks exactly as it did yesterday.

Full reference: **[Company Branding](./company-branding.md)**.

---

## 1. What changed

| File | What |
|---|---|
| `database/migrations/2026_09_02_100000_add_branding_fields_to_companies_table.php` | **New.** Four nullable columns on `companies`: `brand_name`, `app_icon`, `app_icon_dark`, `favicon`. Additive only |
| `app/Services/Branding.php` | **New.** The single seam. `name()`, `iconUrl()`, `darkIconUrl()`, `faviconUrl()`, `faviconType()`, `hasCustomIcon()`, `forget()` |
| `app/Models/Company.php` | `booted()` drops the branding cache on `saved` / `deleted`; three URL accessors beside the existing `logo_url` |
| `app/Livewire/Company/CompanyInfo.php` | The four upload fields behind one `BRAND_FILES` map; `discardUpload()` / `removeStoredFile()` replace the two logo-only methods (kept as aliases); `previewUpload()` checks `isPreviewable()` |
| `resources/views/livewire/company/company-info.blade.php` | **New Branding card**: display name, a three-panel live preview (light, dark, browser tab), and three `x-ui.file-drop` tiles each showing what is in use, what is queued, or that the default is being used |
| `resources/views/components/app-logo-icon.blade.php` | Reads `Branding`; renders a second `<img>` with `dark:block` when a dark icon exists |
| `resources/views/components/email-brand.blade.php` | **New.** The mark at the top of an outgoing e-mail, on a white pad |
| `partials/head.blade.php`, `layouts/inc/head.blade.php`, `layouts/guest.blade.php` | Favicon, apple-touch-icon and `<title>` from `Branding` |
| `app-logo`, `layouts/app`, `inc/sidebar`, `inc/footer`, `inc/welcome_banner`, `inc/guest-header`, `auth/{simple,card,split}`, `welcome` | The name from `Branding::name()` instead of `config('app.name')` or an inline `Company::first()` |
| `email-shell`, `emails/{estimate,invoice,quotation-rfq,meeting-minute}` | The mark above the name in the header |
| `emails/invitation.blade.php`, `app/Mail/InvitationMail.php` | Was telling a brand-new user the **product's** name; now the customer's |
| `lang/en.json`, `lang/pt_BR.json` | 30 strings, both files, same change |
| `tests/Feature/Branding/CompanyBrandingTest.php` | **New.** 12 tests |

The guest top bar's coloured square with the company's first initial in it is
now the actual icon.

## 2. What the fallback protects

`Branding` is read on the **sign-in page**, before a session exists, and by six
templates on every authenticated page. Three things follow:

- **It is cached** — `Cache::rememberForever('branding.v1')` plus a per-request
  memo. This is a net *reduction* in queries: six templates ran their own
  inline `Company::first()` before.
- **It never throws.** Every read is wrapped. No database, no `companies`
  table, no cache table — the product defaults come back and the page renders.
- **The cache clears itself** from `Company::booted()`. No `cache:clear` after
  an upload.

URLs carry `?v=<company updated_at>`, because a replaced favicon is otherwise
pinned in the browser and the upload looks like it did nothing.

## 3. Two things that would have shipped broken

Both were caught by the tests, both are now covered by one:

- **Laravel's `image` validation rule rejects `.ico`** — the one format every
  browser is guaranteed to take for a favicon. The rule is
  `file|mimes:ico,png|max:512`.
- **Livewire's `temporaryUrl()` throws `FileNotPreviewableException` for a
  `.ico`.** Dropping a valid favicon took the whole screen down. The tile now
  checks `isPreviewable()` first and names the file instead.

While testing, `public/storage` turned out not to be linked on the local
machine — which would also have broken the *existing* company-logo preview.
`php artisan storage:link` fixes it; production already has it.

## 4. Permissions are untouched

No new area, no new ability. Reading the screen is `company.view`; every write
— `saveCompany`, `removeStoredFile` — is `company.edit`, exactly as the company
logo already was. `discardUpload()` only drops an unsaved file from the
component's own state.

Two guards worth naming: `removeStoredFile($field)` and `discardUpload($field)`
take a field name **from the browser**, so both `abort_unless` it is one of the
four known fields before touching a column or a file.

## 5. Deliberately not done

- **PDFs are untouched.** They still print the wide `companies.logo` and the
  legal `companies.name`. A display name is for screens.
- **Estimate, invoice and quotation e-mail subjects** keep the legal name, for
  the same reason. Only the invitation e-mail moved.
- **No brand accent colour.** `#3F5189` is still hardcoded across the
  components — a larger job, scoped out.
- **No dark-mode favicon**, and **no SVG uploads** (an SVG on our own origin on
  the pre-auth sign-in page is a script-injection vector).

## 6. Deploying

`php artisan migrate` and nothing else. The columns are nullable and every
reader falls back, so the change is invisible until somebody uploads something.
