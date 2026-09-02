# Company Branding

**Status: built 2 Sep 2026.** Lets a customer put their own name and icons on
the screens they look at all day, with the product's own mark underneath as the
fallback. An install that uploads nothing looks exactly as it did before.

## What a customer can set

All four live on the **Company Information** screen (`/company-info`), in a
**Branding** card below the existing Company Logo card.

| Field | Column | Where it shows |
|---|---|---|
| Display Name | `companies.brand_name` | Beside the icon in the sidebar and header, the browser-tab title, the guest header and footer, the e-mail header, the invitation e-mail's subject and body |
| App Icon | `companies.app_icon` | Sidebar, header, sign-in page, welcome page, guest header, apple-touch-icon, top of outgoing e-mails |
| Dark Mode Icon | `companies.app_icon_dark` | The same places, when the app is in dark mode. Optional — falls back to the App Icon |
| Favicon | `companies.favicon` | Browser tab and bookmarks |

The pre-existing **Company Logo** (`companies.logo`) is unchanged and still
belongs to the printed page — the wide wordmark at the top of estimates,
invoices, reports and the two document renderers. It was never on screen and
still is not. The card's help text now says so, because the two were easy to
confuse.

## The fallback chain

Everything goes through `App\Services\Branding`:

```
Branding::name()        brand_name → company name → config('app.name')
Branding::iconUrl()     app_icon → config('app.logo_url')
Branding::darkIconUrl() app_icon_dark → null (callers then use iconUrl())
Branding::faviconUrl()  favicon → config('app.logo_url')
Branding::faviconType() image/x-icon for a .ico, image/png otherwise
```

`config('app.logo_url')` is the product mark on the brand CDN, exactly as
before; nothing about the default changed.

Three properties of the service matter and are all deliberate:

1. **It is read before a session exists.** The sign-in page and the guest
   layout call it. The row is cached (`Cache::rememberForever('branding.v1')`)
   and memoised per request, so a page with a header, a sidebar and a footer
   costs one read rather than ten — the old code ran `Company::first()` inline
   in six templates.
2. **It never takes a page down.** Every read is wrapped: no database, no
   `companies` table, no cache table, and the product defaults come back. An
   install mid-migration still shows its login screen.
3. **The cache clears itself.** `Company::booted()` drops it on `saved` and
   `deleted`, so an upload is live on the next page load. No `cache:clear`.

URLs carry `?v=<company updated_at>` — a replaced favicon is otherwise pinned
in the browser for weeks and the upload looks like it did nothing.

## Rules the uploads follow

- **Both drop zones, no bare file inputs** — `<x-ui.file-drop>`, per CLAUDE.md.
- **`.ico` is not validated with the `image` rule**, which rejects it. The
  favicon uses `file|mimes:ico,png|max:512`; the icons use
  `image|mimes:png,jpg,jpeg,webp|max:1024`.
- **`.ico` has no thumbnail.** Livewire's `temporaryUrl()` *throws* for a file
  it cannot preview, which would have taken the screen down on a perfectly
  valid favicon. `previewUpload()` checks `isPreviewable()` first and the tile
  names the file instead of showing a picture of it.
- **SVG is not accepted.** An SVG served from our own origin on the pre-auth
  sign-in page is a script-injection vector; PNG, JPG and WebP cover the need.
- **The field name is checked, not trusted.** `removeStoredFile($field)` and
  `discardUpload($field)` take a name from the browser, so both `abort_unless`
  it is one of the four known fields before touching anything.
- **Files are replaced, not accumulated** — the old file is deleted when the
  column stops pointing at it, and when the mark is removed.

## Permissions

No new area. Reading the screen is `company.view` and every write —
`saveCompany`, `removeStoredFile` — is `company.edit`, which is what guarded
the company logo already. `discardUpload` drops an unsaved upload from the
component's own state and needs no grant.

## What this deliberately does not do

- **PDFs are untouched.** They still print `companies.logo` and the legal
  `companies.name`. A brand name is for screens; a document says who you are.
- **Estimate, invoice and quotation e-mail subjects** still carry the legal
  company name rather than the display name, for the same reason. Only the
  invitation e-mail, which was showing the *product's* name to a new user,
  moved to `Branding::name()`.
- **No accent colour.** `#3F5189` is still hardcoded across the components; a
  brand colour is a larger job and was scoped out.
- **The favicon has no dark-mode twin.** Only the app icon does.
- **One company per install.** `Branding` reads `Company::first()`, which is
  what the whole application already assumed.

## Tests

`tests/Feature/Branding/CompanyBrandingTest.php` — 12 tests covering the
fallback with no company at all, name precedence, upload and removal round
trips, the `.ico` rule, the sign-in page actually carrying the marks, the two
`<img>` tags of the dark-mode swap, and both authorization refusals.
