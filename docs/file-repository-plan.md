# File Repository (Documents Module) — Plan

**Status: complete — phases 0–8 built and reviewed (2026-08-19), with the preview stage added
2026-08-20.** See §13 for the build log.

> **2026-08-19 — the storage layer is now shared.** `DocumentStorageService` works on the
> `App\Contracts\StoredFile` contract rather than on `DocumentVersion`, and the presign /
> multipart decision lives in the extracted `planUpload()`. The Alpine `documentUploader` is
> one factory registered under two names. The repository's behaviour, payloads and endpoints
> are unchanged — the meetings module reuses them for task attachments instead of growing a
> second copy. Note for phase 5: `abortOrphanedMultipartUploads()` must keep checking
> `file_uploads` as well as `document_versions`, or it will abort other modules' live uploads.
> See `docs/meetings-module-plan.md` §12.
Plan written 2026-08-19.

A document repository at the **Project** and **Job Site** level: folders, categories and tags,
versioning, and expiring share links for clients and vendors. Storage on **Cloudflare R2**,
configurable per install, with a local-disk fallback.

---

## 1. Decisions already taken with the owner

| Question | Decision |
|---|---|
| Storage backend | **Configurable disk.** `documents` disk driven by env: R2 in production, local private disk when R2 is not configured. Multi-install product — must degrade gracefully. |
| v1 scope | **Folders + categories/tags, versioning, share links.** |
| Absorbing existing attachments | **Deferred.** The quotation module (phase 7) is mid-build; `Shared/Attachments` is left alone. Designed so it is one additive migration + a backfill command later. |
| Access | All signed-in users **read**; admin/manager **write**; admin **delete**. Plus a per-document `is_internal` flag that hides a document from `employee` users. |
| File size | **Gigabytes.** This is the constraint that drives the upload architecture. |

---

## 2. Why "gigabytes" changes everything

The existing attachment flow (`Livewire\WithFileUploads` → `$upload->store()`) sends the file
**through PHP**: `upload_max_filesize`, `post_max_size`, `max_execution_time`, memory, a temp
copy on the web server's disk, then a second copy to storage. It is fine for a 10 MB receipt
and impossible for a 2 GB drawing set.

**v1 uploads go straight from the browser to R2** and never touch the app server:

```
browser                          app server                     Cloudflare R2
   |  POST /documents/uploads/init  |                                |
   |  (name, size, mime, folder)    |  authorize + validate          |
   |                                |  create document + version     |
   |                                |  (status = pending)            |
   |                                |  CreateMultipartUpload  ------>|
   |  <-- uploadId + presigned part URLs (batched)                   |
   |                                                                 |
   |  PUT part 1 .. part N  (direct, parallel, with progress)  ----->|
   |  <-- ETag per part                                              |
   |                                |                                |
   |  POST /documents/uploads/complete (uploadId, parts[])           |
   |                                |  CompleteMultipartUpload ----->|
   |                                |  HeadObject: verify size/mime  |
   |                                |  version status = available    |
```

Consequences to plan for:

- Needs `league/flysystem-aws-s3-v3` (pulls `aws/aws-sdk-php`) for presigning and multipart.
  **Requires the owner's explicit permission to install** (project rule: no packages without it).
- The R2 bucket needs **CORS** allowing `PUT` and `POST` from the app origin and exposing the
  `ETag` response header, otherwise the browser cannot read part ETags.
- R2 multipart requires **every part the same size except the last**; part size 64 MB
  (5 000 parts → 320 GB ceiling, far above anything real). Minimum part size 5 MB.
- Files under 100 MB skip multipart and use one presigned `PUT` — fewer round trips.
- **Incomplete multipart uploads are billed.** A scheduled command aborts stale uploads and
  deletes orphan `pending` version rows (see §7).
- Validation cannot be "trust the browser". The server records the declared size/mime, and on
  complete re-checks the real object with `HeadObject`; a mismatch fails the version.
- **Local-disk fallback cannot do this.** When R2 is not configured the module falls back to the
  ordinary Livewire chunked upload, capped by PHP limits (default 100 MB), and the upload panel
  says so plainly instead of failing at 90 %.

Downloads are the mirror image: a **presigned GET URL** (`Storage::temporaryUrl()`) with
`response-content-disposition` set for download vs inline preview. The window was planned at ~5
minutes and shipped at **60 seconds** at the owner's choice — see the bearer-access note in §13.
Bytes never pass through PHP, and R2 charges **no egress**, which is the main reason R2 beats S3
here.

---

## 3. Storage configuration

### 3.1 New disk

`config/filesystems.php` gains a `documents` disk; the app **never** references `r2` or `local`
directly, only `config('documents.disk')`.

```php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),          // https://<account>.r2.cloudflarestorage.com
    'url' => env('R2_PUBLIC_URL'),             // optional custom domain, only for public assets
    'use_path_style_endpoint' => true,
    'throw' => true,
    'report' => true,
],
```

**As built**, `config/documents.php`:

| Key | Env | Default | Meaning |
|---|---|---|---|
| `disk` | `DOCUMENTS_DISK` | `local` | `r2` in production installs |
| `max_upload_bytes` | `DOCUMENTS_MAX_UPLOAD` | 5 GB (R2) / PHP limit (local) | Server-enforced cap |
| `part_size` | — | 64 MB | Multipart part size |
| `multipart_threshold` | — | 100 MB | Below it, one presigned `PUT` |
| `presign_ttl` | `DOCUMENTS_PRESIGN_TTL` | **60 s** | Download/preview URL lifetime |
| `retention_days` | `DOCUMENTS_RETENTION_DAYS` | 30 | How long the trash is kept before purge |
| `stale_upload_hours` | — | 24 | When the prune command aborts an unfinished upload |
| `share_default_days` | `DOCUMENTS_SHARE_DAYS` | 14 | Default share-link expiry |
| `storage_quota_bytes` | `DOCUMENTS_QUOTA` | null | Optional per-install cap, enforced and surfaced in the UI |
| `allowed_extensions` | — | see §3.2 | Extension ⇒ accepted mime types |
| `blocked_extensions` | — | see §3.2 | Refused whatever the mime says |

Everything is read through `App\Services\DocumentSettings`, never from `config()` directly:
`disk()`, `isCloudConfigured()` (which upload path the UI offers), `maxUploadBytes()` (resolves
PHP's `upload_max_filesize` / `post_max_size` on the local disk), `needsMultipart()`,
`isAllowedFile()`, `acceptAttribute()`, `sanitizeFileName()`, `objectKey()`, `presignTtl()`,
`storageQuotaBytes()` / `installUsedBytes()` / `wouldExceedQuota()`, and `formatBytes()`.

Bucket credentials are per install — each customer uses their own R2 bucket, documented in
`docs/deployment-cloudflare-r2.md` (written in phase 0).

### 3.2 Allowed types

PDF; images (jpg, jpeg, png, webp, heic, gif); Office (docx, xlsx, pptx and the legacy trio);
CAD (dwg, dxf); plain text/csv; archives (zip, rar, 7z); video (mp4, mov) for site walkthroughs.
Blocked always: executables and scripts (`exe`, `bat`, `sh`, `php`, `js`, `html`, `svg` —
`svg`/`html` because they execute in a browser tab).

### 3.3 Object key layout

Keys are **server-generated, never derived from user input**:

```
projects/{project_id}/documents/{document_uuid}/v{version_number}/{sanitized-original-name}
```

Job-site documents live under the same project prefix — the job site is a column, not a path
segment, so moving a document between locations is a database update, not a copy.

---

## 4. Data model

Follows the parity rule (`docs/project-jobsite-parity-rule.md`): `project_id` required,
`job_site_id` nullable = project-level ("General").

### `document_folders`
`id`, `project_id` (FK, cascade), `job_site_id` (nullable FK, cascade), `parent_id` (nullable self
FK, cascade), `name`, `created_by`, timestamps, soft deletes.
Unique `(project_id, job_site_id, parent_id, name)`. Index `(project_id, job_site_id)`.
Depth capped at 5 in the component; move operations reject a folder into its own descendant.

### `documents`
`id`, `uuid` (unique), `project_id`, `job_site_id` (nullable), `folder_id` (nullable), `name`
(display, editable), `description` (nullable), `category` (string enum, §6), `is_internal`
(bool, default false), `current_version_id` (nullable FK), `current_size_bytes`,
`current_mime_type`, `current_version_number`, `uploaded_by`, `updated_by`, timestamps, soft
deletes + `deleted_by`.
Indexes `(project_id, job_site_id)`, `(folder_id)`, `(category)`, `(name)`.
The three `current_*` columns are denormalised so the list view sorts and totals without a join.

*Reserved for the deferred absorption phase:* nullable `attachable_type` / `attachable_id`,
added by a later additive migration — not created now.

### `document_versions`
`id`, `document_id` (FK, cascade), `version_number`, `disk`, `object_key`, `original_name`,
`size_bytes` (unsigned big int), `mime_type`, `checksum` (ETag, nullable), `notes` (nullable),
`upload_status` (`pending|available|failed`), `multipart_upload_id` (nullable),
`uploaded_by`, timestamps.
Unique `(document_id, version_number)`. Index `(upload_status, created_at)` for the cleanup job.

### `document_tags` + `document_tag` pivot
`document_tags`: `id`, `name`, `slug` (unique), `color` (nullable), `created_by`, timestamps.
Tags are global to the install (like cost codes), assigned per document.

### `document_shares`
`id`, `document_id` (nullable), `folder_id` (nullable — exactly one of the two set),
`token` (64-char random, unique, indexed), `expires_at`, `password_hash` (nullable),
`allow_download` (bool), `max_downloads` (nullable), `download_count`, `revoked_at` (nullable),
`created_by`, timestamps.

### `document_activities`
`id`, `document_id`/`folder_id`/`share_id` (all nullable), `user_id` (nullable — public share
access has no user), `action` (`uploaded`, `version_added`, `renamed`, `moved`, `recategorised`,
`downloaded`, `previewed`, `deleted`, `restored`, `shared`, `share_revoked`, `share_accessed`),
`ip_address`, `user_agent`, `context` (json, nullable), timestamps.
Indexes `(document_id, created_at)` and `(action)`.
This is what the detail view's History panel reads.

Model deletion behaviour: **soft delete only.** Objects stay in R2 until an admin purges from a
Trash view (or a retention command after 30 days) — a deleted 4 GB drawing set that nobody can
recover is a support incident waiting to happen. Purge deletes every version's object.

---

## 5. Access control and the security fix that comes with it

### 5.1 Rules

| Action | Who |
|---|---|
| List / preview / download | Any signed-in user, except documents with `is_internal` where the role is `employee` |
| Upload, new version, rename, move, tag, recategorise | admin or manager |
| Create / rename / move folder | admin or manager |
| Delete (soft), restore, purge | admin |
| Create / revoke share link | admin or manager |

Implemented with the patterns already in the codebase — `AuthorizesAdmin` and a new
`AuthorizesDocuments` concern with `authorizeDocumentWrite()` (admin ‖ manager), mirroring
`canReviewRequisitions()`. Every guard is server-side; the UI hides what it must, but the
component re-checks.

Anything finer (per-project access, per-folder permission) goes into
`docs/permissions-notes.md` as a notation rather than being invented here.

### 5.2 `FileController` is currently unauthenticated in effect — fix it in phase 0

`app/Http/Controllers/FileController.php` takes a raw `?path=` query string and checks only that
the user is signed in:

```php
$path = $request->query('path');
if (!$path || !Storage::exists($path)) { abort(404); }
return Storage::download($path, basename($path));
```

Any signed-in user can read **any** file on the private disk by guessing or copying a path, and
there is no `..` guard. Every attachment link in the app passes the stored path in the URL, so
paths are trivially observable. The new module must not repeat this, and the existing hole gets
closed in the same work:

- New routes address a **record id**, never a path: `documents/{document}/download`,
  `documents/{document}/preview`, `documents/{document}/versions/{version}/download`. The
  controller loads the model, authorizes, then presigns or streams.
- `FileController` keeps working for existing attachments but gains: rejection of any path
  containing `..` or a leading `/`, a whitelist of the five known attachment directories, and a
  lookup that the path actually exists in the `attachments` table.
- Logged as a notation in `docs/permissions-notes.md` too, since the same "auth-only" gap
  applies to the PDF controllers.

---

## 6. Categories

A fixed list, translated, filterable, with a colour and icon each — free tags cover everything
else:

`plans` (Plans & Drawings) · `permits` (Permits & Licenses) · `contracts` (Contracts) ·
`submittals` (Submittals & Shop Drawings) · `rfi` (RFIs) · `safety` (Safety) ·
`photos` (Photos) · `reports` (Reports) · `invoices` (Invoices & Financial) ·
`correspondence` (Correspondence) · `other` (Other)

---

## 7. Application pieces

### Routes (`routes/web.php`)

```
projects/{project}/documents               → Project\ProjectDocuments      projects.documents
job-sites/{jobSite}/documents              → JobSite\JobSiteDocuments      jobsites.documents
documents/{document}/download              → DocumentFileController@download
documents/{document}/preview               → DocumentFileController@preview
documents/{document}/versions/{v}/download → DocumentFileController@downloadVersion
documents/uploads/init|parts|complete|abort (POST) → DocumentUploadController

Public, no auth, each one throttled and re-checking the link:
s/{token}                                  → Share\SharedDocument            30/min
s/{token}/view/{document?}                 → SharedDocumentController@view    60/min
s/{token}/download/{document?}             → SharedDocumentController@download 30/min
```

`view` is the inline route the public preview stage points at — the same file, served with an
`inline` disposition, so a shared PDF can be read without downloading it. The optional
`{document}` is for folder links, where one token covers many files.

### Module registration (`config/modules.php`)

New `documents` module, **declared before `projects`** — the module check stops at the first
matching prefix and `projects.*` would otherwise claim `projects.documents`, exactly the gotcha
already documented for `quotations`. Prefixes: `projects.documents`, `jobsites.documents`,
`documents.*`. Both nav components hide the tab when the module is disabled.

### Livewire components

- `App\Livewire\Project\ProjectDocuments`
- `App\Livewire\JobSite\JobSiteDocuments`
- `App\Livewire\Concerns\ManagesDocuments` — the shared trait carrying folder navigation, upload
  state, filters, rename/move/delete and the guards, so the two pages stay identical by
  construction (the pattern used by `ManagesQuotations` / `ManagesRequisitions`).

The detail view is **not** a component of its own: `ManagesDocuments` holds `viewingDocumentId`,
`openDetail()` / `closeDetail()` and the `viewingDocument()` computed property, and every screen
of the module is a partial under `resources/views/livewire/documents/partials/` — `browser`,
`table`, `grid`, `toolbar`, `summary`, `empty-state`, `detail-modal`, `edit-modal`,
`folder-modal`, `share-modal`, `upload-modal`. Both pages include the same partials, which is
what keeps the two levels identical.

### Services / support

- `App\Services\DocumentStorageService` — presign single PUT, start/sign/complete/abort
  multipart, `temporaryUrl()`, delete objects, and the local-disk equivalents behind the same
  interface.
- `App\Console\Commands\PruneDocumentUploads` — hourly: abort R2 multipart uploads older than
  24 h, delete `pending`/`failed` version rows and any document left with no available version.
  Scheduled in `routes/console.php`.
- `App\Console\Commands\PurgeDeletedDocuments` — daily: purge soft-deleted documents past the
  retention window, removing their objects.

### Front end

One Alpine component does the direct upload: drag-and-drop zone, per-file progress from
`XMLHttpRequest.upload.onprogress`, parallel parts (3 at a time), per-part retry with backoff,
cancel (fires `abort`), and a queue that survives navigating between folders on the same page.
No new npm package.

It lives in `resources/js/app.js` as the `createUploader` factory, registered twice —
`documentUploader` for this module and `fileUploader` for anything else that stores a file
(tasks, task notes, meetings). The transport is identical; what differs is only what `init()` is
told to create and who is told when a file lands, and both come from the config object.

Blade components of the module:

- `<x-document-icon>` — the type icon.
- `<x-document-category-badge>` — the coloured category chip.
- `<x-document-preview>` — the preview stage and its controls (below), shared by the detail
  modal and the public share page.

---

## 8. UI — what the screens actually show

Both levels get the same page; the job-site page is the project page scoped to one site, and the
project page has the Location column and filter that the job-site page does not (parity rule).

**List page**
- Breadcrumb: Project / Job Site → folder path, each segment clickable.
- Left: folder tree (collapsible, with document counts); on mobile it becomes a dropdown.
- Toolbar: search (name, description, tag), category filter, tag filter, location filter
  (project-level pages: All / Project (General) / each job site), uploader filter, date range,
  list ⇄ grid toggle.
- Summary strip: document count, total size, storage used against quota when one is set, count
  by category as a small bar.
- Table columns: name (with type icon and version badge `v3`), category chip, tags, size,
  location, uploaded by, updated at, actions (`x-ui.view-edit-buttons` + download).
- Bulk selection: move to folder, tag, change category, download, delete (admin) — with the
  count shown on the button.
- Drag a file anywhere on the page to upload into the current folder.
- Empty state distinguishes *no documents at all* (explains the module, big upload button) from
  *no results for these filters* (offers to clear them).
- Error state for a failed upload keeps the row with a Retry and the reason.

**Upload panel** — `<x-ui.modal maxWidth="full">`: drop zone, per-file rows with progress bar,
speed and remaining time, category and tag pickers applied to the batch, folder target, an
"Internal only" toggle, and a plain warning when the install is on local storage with the
smaller cap.

**Document detail** — `<x-ui.modal maxWidth="full">`, sticky header with name + category, and it
shows **everything**: preview (PDF and images inline, video player, "no preview available" with
a download button otherwise); every stored field; size, mime, checksum; folder path; location;
description; tags; internal flag; full **version history** (number, size, uploader, date, notes,
download each, "restore this version" for admin/manager, which creates a new version rather than
rewinding); active **share links** with expiry, downloads used and Revoke; and the **activity
log** — uploaded/downloaded/renamed/moved/shared/deleted with user, timestamp and IP; plus
created by / created at / last updated by / last updated.

**The preview stage** (`<x-document-preview>`, shared by the detail modal and the public share
page) carries its own controls, because a PDF in a two-thirds column is unreadable:

- **Hide details** — the details column steps aside and the file takes the full width of the page
  (desktop only; the phone layout is already single column). The stage announces the change with a
  `viewer-wide` event so the page decides what to hide.
- **Full screen** — the native Fullscreen API on the stage itself, so the file gets the whole
  monitor with no app chrome. Esc leaves it, and an "Exit full screen" chip travels with the file
  for anyone who does not know that. While a preview is full screen, Esc no longer closes the
  modal behind it.
- **Open in new tab** — the browser's own PDF viewer, for people who prefer it.

The three heights (70vh, widened, full screen) are Alpine class bindings on the same element, so
nothing reloads and a PDF keeps its scroll position when the layout changes.

**Share link modal** — expiry date (default 14 days), optional password, allow-download toggle,
optional max downloads, the copyable URL and a QR-free plain link. Revoking is one click, and the
public page after expiry says the link expired rather than 404-ing.

**Public share page** — the company logo, the file name, size and type, a preview where possible,
one Download button, expiry shown. No app chrome, no navigation, no other document reachable
from it. Rate-limited, `noindex`, and every access written to `document_activities`. It uses the
same preview stage as the detail view, with full screen and open-in-new-tab but no hide-details
control: there is no details column on that page to step aside. The recipient is usually opening
this on a phone or a laptop with one browser tab, which is exactly the case full screen is for.

All of it in both themes, every string through `__()` with the pt_BR added in the same change,
and no horizontal scroll on a phone.

---

## 9. Phases

Built one page at a time; nothing starts before the previous piece is tested (project rule 7).

| # | Phase | Contents |
|---|---|---|
| 0 | **Plumbing & security** | Package permission + install, `r2`/`documents` disks, `config/documents.php`, env keys in `.env.example`, `docs/deployment-cloudflare-r2.md` (bucket, API token, CORS), and the `FileController` hardening from §5.2. |
| 1 | **Data model** | Five migrations + `DocumentFolder`, `Document`, `DocumentVersion`, `DocumentTag`, `DocumentShare`, `DocumentActivity`, relationships on `Project` and `JobSite`, module entry in `config/modules.php` + seeder row. |
| 2 | **Storage service & upload endpoints** | `DocumentStorageService`, `DocumentUploadController` (init/parts/complete/abort), local fallback, `PruneDocumentUploads`. Verified with a real multi-GB upload before any UI work. |
| 3 | **Project page** | `ProjectDocuments` + `ManagesDocuments`: folders, list, filters, upload, download, preview, rename/move/delete. Tested end to end. |
| 4 | **Job site page** | `JobSiteDocuments` — parity, same trait, same partials. |
| 5 | **Detail modal & versions** | The `detail-modal` partial with preview, full field dump, version history, restore, activity log. |
| 6 | **Categories & tags** | Category chips, tag CRUD, filters, search across name/description/tags. |
| 7 | **Share links** | Create/revoke, public page and download route, password, expiry, max downloads, rate limiting, activity. |
| 8 | **Review & Improvements** | Mandatory (`CLAUDE.md`): full-module code review, N+1 sweep on the list query, both themes, both locales, phone, empty/partial/error states, long names, many rows, big files, expired shares; docs and pt_BR brought level; backlog in `docs/review-and-improvements.md` worked. |
| — | **Later, not now** | Absorb `attachments` into the repository (see §10). |

**Deploy needs when it lands:** `composer install`, `php artisan migrate`,
`php artisan view:clear`, R2 bucket + token + CORS configured, `DOCUMENTS_DISK=r2`, and the
scheduler running for the two cleanup commands.

---

## 10. The deferred absorption (for the record)

When the quotation module is out of the way:

1. Additive migration: nullable `attachable_type` / `attachable_id` + index on `documents`.
2. `php artisan documents:import-attachments` — walks `attachments`, creates a `documents` row +
   one `available` version per record, copies the object to the documents disk (or leaves it on
   local and records the disk it is on), and links it back to the expense / PO / income /
   requisition / quotation.
3. `Shared\Attachments` is rewritten to read and write `documents` scoped to the record; the
   `attachments` table stays until the import is verified in production, then is dropped.
4. Effect for the user: a document uploaded against an expense shows up in the project
   repository, filtered by its source record.

Nothing in phases 0–8 blocks this, and nothing in it requires reworking them.

---

## 11. Cost and operational notes

- R2 pricing at the time of writing: **$0.015 per GB-month** stored, **no egress charge**,
  Class A operations (writes, multipart parts, listings) $4.50/million, Class B (reads)
  $0.36/million. 500 GB of project documents ≈ **$7.50/month**, downloads free — which is the
  reason to pick R2 over S3 for a document repository that people actually open.
- Multipart parts are Class A operations: a 2 GB file at 64 MB parts = 32 writes. Irrelevant at
  this scale, but it is why part size should not be 5 MB.
- Backup: R2 has no free versioning of its own in the way S3 does; the module's own version rows
  are the recovery story, plus soft deletes and the Trash purge window. If the owner wants
  off-site copies, that is a bucket-level lifecycle/replication decision, documented rather than
  coded.
- Latency: presigned URLs are signed locally, so the app makes no R2 round trip to render a list.

---

## 12. Open questions — answered

Defaults taken while building; say the word and any of them changes.

1. **Retention** — 30 days (`DOCUMENTS_RETENTION_DAYS`), purge by scheduled command.
2. **Storage quota** — displayed, not enforced. `DOCUMENTS_QUOTA` sets a bar; blank shows usage only.
3. **Folder share links** — supported alongside file links (phase 7).
4. **Video** — mp4 and mov allowed; they are exactly the files that need R2.
5. **Who may share** — admin and manager, matching every other write action.

## 13. Build log

| Phase | State |
|---|---|
| 0 — Plumbing & security | **Done.** `league/flysystem-aws-s3-v3` installed, `r2` disk, `config/documents.php`, env keys, `docs/deployment-cloudflare-r2.md`, `FileController` path traversal closed. |
| 1 — Data model | **Done.** Six migrations run, six models, `DocumentCategory` enum, relations on `Project` and `JobSite`, `documents` module registered. |
| 2 — Storage service & uploads | **Done.** `DocumentStorageService` (presign, multipart, complete, abort, temporary URLs, local fallback), `DocumentUploadController`, `DocumentFileController`, `documents:prune-uploads` + `documents:purge-deleted` scheduled. |
| 3 — Project page | **Done.** `ProjectDocuments` + `ManagesDocuments` + shared partials. |
| 4 — Job site page | **Done.** `JobSiteDocuments`, same trait and partials — parity by construction. |
| 5 — Detail modal & versions | **Done.** Full-page detail view: preview (PDF/image/video), every stored field, version history with per-version download and "make current", and the activity trail. |
| 6 — Categories & tags | Mostly delivered with phase 3 (chips, filters, tag CRUD by typing); the remainder rides with phase 5. |
| 7 — Share links | **Done.** Expiring public links for a document or a folder, with optional password, download limit, view-only mode, revocation and full logging. Folder links exclude internal documents. |
| 8 — Review & Improvements | **Done.** Full-diff code review; six document-module findings fixed and re-verified; query counts, long names, dark mode and both locales checked; notations N7/N8 recorded. See `docs/review-and-improvements.md`. |
| — Preview stage (2026-08-20) | **Done.** `<x-document-preview>` extracted from the detail modal and reused by the public share page: hide-details, native full screen and open-in-new-tab, with the three stage heights as class bindings. Esc in a full-screen preview no longer closes the modal behind it (`components/ui/modal.blade.php`). Ten strings added to both locales. |

**Verified so far:** both pages render 200 in English and pt_BR at both levels; folder create / duplicate refusal / rename / delete (contents move up, including trashed documents); local upload of several files; blocked file types refused; new version keeps the old one; rename, move, recategorise, tag; delete → trash → restore → purge; the full activity trail; employee blocked from every write; internal documents hidden from employees; ids from another project or another location refused at every entry point; module toggle hides the nav and 403s the route.

**Download links are bearer access.** Files are served by redirect to a presigned R2 URL, so the
signature in the link is the credential: while it is valid, anyone holding it can fetch that one
file with no login. Permission is checked before the link is issued and every download is logged,
but the link itself carries the access. `DOCUMENTS_PRESIGN_TTL` is 60 seconds (owner's choice,
2026-08-19). The signature is checked when the request starts, so a large download already
running is never cut off.

**R2 verified 2026-08-19** against a real bucket (`wkm-despesas`): a 130 MB file split into three
presigned parts, uploaded straight from the client to R2, completed and size-verified against
`HeadObject`, then downloaded through a presigned URL byte-identical to the source. An abandoned
upload was aborted and its billed parts released.

**Setup gotcha worth remembering:** the local site is served over TLS by Herd, so the browser
origin is `https://despesas.test`, not `http://`. A CORS policy listing only the `http` origin
fails every upload with a bare network error, because the browser blocks the request before it
is sent. `APP_URL` must carry the same scheme the site is actually served on. The bucket's CORS
policy allows `https://despesas.wkmsolutions.net`, `https://despesas.test` and
`http://despesas.test`.

**Also note:** the R2 API token is scoped *Object Read & Write*, which cannot change bucket
settings — CORS and lifecycle rules have to be set in the dashboard or with an admin token.

## 14. Still to decide

Nothing blocking. Two things left open on purpose:

- **Who may create a share link.** It is admin-or-manager today. It hands out access to someone
  with no login at all, so it may want to be admin-only — the owner's call, not a bug.
- **The deferred absorption of `attachments`** (§10), which is waiting on nothing in this module.
