# Punch List / Lista de Pendências — Plan, revision 2

**Target codebase:** Despesas (Laravel 12 / Livewire 3.7 / Alpine / Tailwind / **MySQL**)
**Owner:** Jr — AllStar Tech Solutions
**Primary market:** Brazil. The first customer is an **incorporadora** delivering an apartment
complex, so the unit handover (**vistoria de entrega**) is the core flow. US punch-list
semantics are a locale variant of the same engine, exactly as in the original spec.
**Revision:** 2 — reconciled against the codebase on 2 Sep 2026, and extended with the
**connection to the tasks module** the owner asked for.

> **Revision 1 is kept unchanged** as
> [`punch-list-module-implementation-plan.md`](./punch-list-module-implementation-plan.md).
> It is the *specification*: its legal table (§1), its hard prohibitions (§0), its domain model
> (§2), its media rules (§3) and its phase content all stand. **This document changes only the
> layer that assumed a different platform** — Postgres, `nwidart/laravel-modules`, one database
> per tenant, a separate media subsystem, a separate buyer portal, NativePHP — and folds in the
> three "every module ships with" rules from `CLAUDE.md` that revision 1 did not know about.
> This is the same exercise the RFI plan went through in
> [`rfi-aprovacoes-discovery.md`](../rfi-aprovacoes-discovery.md); the findings there (MySQL,
> one install per customer, the guest system, `file_uploads`) all apply here too.

---

## 0. What this revision changes, and why

| # | Revision 1 assumed | The codebase has | Effect on the plan |
|---|---|---|---|
| A | Postgres — `jsonb`, `citext`, partial indexes, identity columns | **MySQL** (MariaDB locally) | `json` columns, plain indexes, `id()` + a `uuid` column where the client generates ids. No advisory locks: numbering goes through the existing `NumberSequenceService` (`SELECT … FOR UPDATE`). |
| B | `nwidart` module `Modules\PunchList`, tenant database, `t/{tenant}` key prefix | **One installation per customer**, one company, one country (`config('app.country')`), one R2 bucket per install (`config/documents.php`) | Code lives in `app/` under `App\Models\PunchList`, `App\Livewire\PunchList`, `App\Services\PunchList`. No tenant segment anywhere. Module switch is a row in `config/modules.php`. |
| C | A new `media` / `media_upload_sessions` / `media_usage` subsystem with its own controller | **`file_uploads` + `FileUploadService` + `DocumentStorageService`** already do presigned single-PUT and **S3 multipart to R2**, with resume, re-sign of part URLs, HeadObject verification on complete, stale-upload pruning and orphaned-multipart abort. `mp4`, `mov` and `heic` are already on the allowlist. | **Phase 0 of revision 1 is mostly built.** What is missing is the *evidence* layer: sha256, EXIF/GPS, captured vs received time, device, verification status, derivatives, an issued-URL log. That becomes a **sidecar table** on `file_uploads`, not a second pipeline. See §3. |
| D | A `signature_requests` table and a `SignatureDriver` interface to build | **`collaboration_signatures` + `HasSignatures`** exist: polymorphic, `payload_hash` (sha256 of the record), `method` already a string shaped for `drawn | gov_br | icp_brasil`, "changed since signed" detection on screen and in the PDF | Reuse it. Add the columns it lacks (`user_agent`, `signer_role`, the drawn-signature artifact). The driver interface is still built (§5.3); the canvas pad is greenfield — today's "drawn" method collects only CREA/ART text. |
| E | A separate buyer portal with `portal_token_hash` | **Guests are `users` rows** (`is_guest`), confined by `memberships`, invited by magic link (`user_invitations`, token hashed), with guest permission templates and `visibleTo()` everywhere | Buyers are guest users with a job-site or project membership **narrowed by the module to their own unit**. No second auth stack. §6. |
| F | NativePHP mobile app (phase 7) | No mobile app; Livewire + Alpine web app | Deferred to a decision phase (§9). The design keeps client-generated UUIDs and the deterministic sync order so a PWA or NativePHP client can be added without touching the schema. |
| G | Self-hosted ffmpeg workers, dedicated `media` queue | `QUEUE_CONNECTION=database`; **no `app/Jobs/` exists yet**, no worker is documented as running in production; ffmpeg is not installed locally; PHP has `imagick`, `gd`, `exif` | Verification and derivatives are the **first queued jobs in the app** — the deploy must start a worker (§3.7). ffmpeg is **optional per install**: without it, video has no poster and no transcode, and the UI says so rather than failing. |
| H | Plan omitted CLAUDE.md's ship-with rules | Permissions as it is built, `__()` + pt_BR in the same change, a final Review and Improvements phase | Phase 1 declares the area; every phase carries its strings; phase 10 is the review. |
| I | Tasks not mentioned | A complete tasks module: `TaskService`, owner + assignees, progress roll-up, notes, R2 files, activity log, four e-mails, weekly digest, meeting agendas | **New §7 — the connection.** A punch item can spawn a task; the two stay in sync under rules that keep the punch item the legal record and the task the working record. |

**Everything in revision 1 that is not in this table stands as written.** In particular the six
hard prohibitions and the legal table are repeated here in §1 so nobody has to hold two files
open to know what must not be built.

---

## 1. Legal and normative constraints — unchanged, and encoded

### 1.1 Hard prohibitions (from revision 1 §0)

1. **Never proxy media bytes through PHP.** Browser → R2 via presigned URLs; PHP signs, verifies
   and records. (`FileUploadService` already works this way.)
2. **Never mutate an original media object.** Derivatives are separate objects. Originals keep
   EXIF/GPS — that metadata is evidence in a perícia.
3. **Never hard-delete media or pendências.** Soft-delete with actor and reason. The warranty
   tail is five years or more.
4. **Never build a flow where the buyer signs the termo de recebimento before or in order to
   perform the vistoria.** Order is always vistoria → outcome → signature. The state machine in
   §4.3 makes the signature step unreachable before an outcome exists.
5. **Never ship a termo template that disclaims responsibility for aesthetic defects.** Void
   under CDC art. 51, I. The seeded templates are reviewed for this in phase 5 and the review
   phase re-checks them.
6. **A unit never reaches a terminal "closed forever" state.** After handover it transitions to
   warranty / assistência técnica.

### 1.2 Rules encoded as fields and transitions (from revision 1 §1)

| Rule | Source | Encoded as |
|---|---|---|
| 90 days to claim vícios aparentes, from effective delivery | CDC art. 26, II | `project_units.cdc_apparent_deadline_at`, set on key handover |
| Vício oculto: term starts when the defect becomes apparent | CDC art. 26 §3 | `warranty_claims.defect_type`, deadline computed from `discovered_at` |
| 5 years for solidez e segurança | Código Civil art. 618 | `warranties` rows seeded per unit at habite-se |
| Repair does not renew warranty; remaining term or 90 days, whichever is greater | NBR 17170:2022 | `warranty_events` recompute rule |
| Termo de garantia + manual de uso, operação e manutenção delivered at handover | NBR 17170:2022 | required artifacts on the delivery checklist; handover is blocked without them |
| Table of visible failures the buyer should catch at handover | NBR 17170:2022, Tabela 3 | seeded checklist template |
| Unregistered apparent defects can later be attributed to the user | NBR 17170 guidance | the signed, photographed termo is mandatory, not optional |
| Common-area 90-day clock runs from the condomínio's receiving vistoria | CDC applied to condomínio | separate inspection type and warranty owner |
| Habite-se and CND da Obra must exist before vistorias begin | practice; blocks repasse | campaign gate (§8, phase 6) |
| Signature levels: simples / avançada / qualificada | Lei 14.063/2020 | `collaboration_signatures.method`, level chosen **per signer** (§5.3) |

Public/contract work (`recebimento provisório` → `definitivo`, Lei 14.133/2021 art. 140) is the
same engine with different inspection and term types. Built, not first.

---

## 2. Conventions for this module

- **Names in the schema are English**, like every other table in this install (`rfis`,
  `approvals`, `tasks`); the Portuguese is in `lang/pt_BR/punch-list.php`. The glossary below
  fixes the mapping so nobody invents a second word. Domain terms that have no good English
  (`habite-se`, `repasse`, `RNC`, `ART/RRT`) stay as they are.
- **Table prefix:** the schema is shared with 33 other areas, so generic names are prefixed.
  Structure tables are `project_*` (they belong to the project and may be reused by other
  modules later); the module's own tables are `punch_*`, `inspection_*`, `warranty_*`.
- **Statuses are `string(30)` columns with model constants**, not MySQL enums, so a value can be
  added without a migration. Every status has `getStatusLabel()` + `static statusLabel()`
  (CLAUDE.md, translation rule 4), minding gender: *pendência* and *vistoria* are feminine.
- **Client-generated UUIDs** on `inspections`, `punch_items` and every media row, beside the
  bigint id. This is what makes an offline client possible later without id remapping (§9).
- **`project_id` required, `job_site_id` nullable** on every record, per
  `docs/project-jobsite-parity-rule.md`; every screen ships at both levels through one shared
  `Manages*` trait and shared partials, the way `ProjectRfis` / `JobSiteRfis` do.
- **Translations live in the module's own file**, `lang/en/punch-list.php` +
  `lang/pt_BR/punch-list.php`, for the reason the RFI module learned: *Due* is "A Pagar" on the
  payment screens and "Prazo" here. Universal chrome stays global.
- **Dates through the macros** (`appDate()` and friends), **money through `<x-ui.money>`**
  (this module has almost none — `money => false`), **date fields are `<x-ui.date-input>`**,
  **uploads are the drop-zone components**.

### Glossary

| pt_BR (UI) | Schema / code | Notes |
|---|---|---|
| Local (hierarquia) | `project_locations` | tree: torre / bloco / pavimento / unidade / ambiente / área comum |
| Unidade | `project_units` | one row per sellable unit, 1:1 with a location of type `unit` |
| Adquirente | `unit_buyers` | the buyer; CPF encrypted |
| Disciplina | `trades` | company reference data |
| Vistoria | `inspections` | |
| Pendência | `punch_items` | |
| Medição | `punch_item_measurements` | |
| RNC | `punch_item_ncrs` | non-conformance record |
| Termo | `inspection_terms` | the PDF + hash + signatures |
| Garantia | `warranties`, `warranty_events` | |
| Chamado | `warranty_claims` | assistência técnica |

---

## 3. Media — evidence on top of `file_uploads`

**This is still the highest-risk part** and still comes first (phase 2), but it is a much
smaller build than revision 1 planned because the transport already exists.

### 3.1 What already exists and is reused as is

| Need | Existing piece |
|---|---|
| Presigned PUT for small files, multipart for large; part re-sign; resume | `DocumentStorageService::planUpload/presignParts/completeUpload` |
| Polymorphic file row with status lifecycle, soft delete, ETag checksum | `file_uploads` (`FileUpload` model, `StoredFile` contract) |
| Browser uploader with per-part progress and retry | `<x-ui.file-uploader>` + `createUploader` in `resources/js/app.js` |
| Server-side size/mime re-check on complete (HeadObject) | `DocumentStorageService::completeUpload()` |
| Stale upload prune, orphaned multipart abort | `pruneStaleUploads()`, `abortOrphanedMultipartUploads()` — already scheduled |
| Allowlist incl. `jpg/png/webp/heic/mp4/mov` | `config/documents.php` |
| Signed GET URLs with inline/download disposition | `FileUploadService::temporaryUrl()` |
| Local-disk fallback when R2 is not configured | `planUpload()` mode `local` |

Two small changes to the shared service, both additive:

- `FileUploadService::TARGETS` gains `punch_item`, `inspection`, `inspection_term`,
  `warranty_claim`, `signature`; `objectKey()` gains their prefixes (below).
- `maxBytes()` is capped at 100 MB by `config/tasks.php`. It becomes `maxBytesFor(Model $target)`,
  falling back to today's value, so this module can declare **image 25 MB / video 500 MB /
  document 50 MB** in `config/punch-list.php` without raising the limit for tasks.

### 3.2 What is new: the sidecar

```
file_upload_media                      -- 1:1 with file_uploads, only for evidence media
  id, file_upload_id (unique, cascade)
  kind          string: image | video | signature | document | audio
  role          string: original | poster | playback | thumb | web
  parent_file_upload_id (nullable)     -- derivatives point at their original
  sha256 (64, nullable until verified)
  client_sha256 (64, nullable)         -- what the device declared, if it could compute one
  width, height, duration_ms, codec, rotation
  captured_at   datetime, nullable     -- device clock, UNTRUSTED
  received_at   datetime               -- server clock, the legal anchor (= upload complete)
  device        json                   -- {device_id, model, os, app_version, user_agent}
  gps           json                   -- {lat, lng, accuracy_m, captured_at}
  status        string: uploaded | verifying | ready | quarantined | failed
  quarantine_reason
  delete_reason, deleted_by_id         -- file_uploads.deleted_at is the soft delete
  index (status), index (parent_file_upload_id)
```

Why a sidecar and not columns on `file_uploads`: the columns are meaningless for an RFI
attachment, and `file_uploads` is the one table every module shares. A punch item's `files()`
relation eager-loads `->with('media')` and the accessor `isReady()` reads the sidecar. **Only
`ready` media renders in the UI or appears in a termo.**

Quota accounting (`media_usage` in revision 1) is dropped: `DocumentSettings::wouldExceedQuota()`
already enforces a per-install quota on every `init`.

### 3.3 Key layout — one bucket per install, separated by prefix and audience

```
punch/evidence/{project}/{unit-or-location}/{inspection}/{file_uuid}/original.{ext}
                                                              /poster.webp
                                                              /playback.mp4
                                                              /thumb.webp
                                                              /web.webp
punch/docs/{project}/{unit}/{term_uuid}/termo.pdf                -- rendered once, never re-rendered
punch/docs/{project}/{unit}/{term_uuid}/signed-{n}.pdf           -- a signature is a NEW object
punch/signatures/{signature_id}.png                              -- drawn signature artifacts
```

No `tmp/` prefix is needed: abandoned uploads are `file_uploads.pending` rows and the existing
prune already aborts them. **No storage tiering** and **no public bucket**, for the reasons
revision 1 §3.8 gives; they hold unchanged.

### 3.4 Upload flow

Unchanged from revision 1 §3.4 in shape, but the endpoints are the existing
`uploads.init / parts / complete / abort`. The `init` payload for a punch target carries the
extra evidence fields (`kind, captured_at, device, gps, client_sha256, duration_ms`) and
`FileUploadController::init()` hands them to `PunchMediaService::attachSidecar()` after
`begin()`. **Video is always multipart** even when small, so the resume path is exercised on
every upload — `planUpload()` gets a `forceMultipart` flag for the punch targets.

Part size stays the install's `config('documents.part_size')` (64 MB) for documents; the punch
targets use **8 MiB** so a failed part on a bad site connection is cheap to retry. Both satisfy
R2's 5 MiB minimum.

### 3.5 `VerifyMediaJob` — what makes the media defensible

The first queued job in the application. Dispatched from `complete()` for punch targets.

1. `HeadObject` — object exists, `bytes` and `Content-Type` match what was declared. Mismatch →
   delete from R2, `status = quarantined`.
2. Stream the object in chunks and compute **sha256** (never `file_get_contents` a 400 MB video).
   Store it. If the client declared a hash and it differs → quarantine.
3. Inspect the bytes: images through `getimagesize` / Imagick (dimensions, orientation) and
   `exif_read_data` (captured time, GPS, device model — written to the sidecar; the original is
   untouched). Video through `ffprobe` when `config('punch-list.ffprobe_path')` is set; otherwise
   a container sniff (`finfo`) so a renamed text file is still rejected.
4. Dispatch `GenerateDerivativesJob`.
5. `status = ready`.

### 3.6 `GenerateDerivativesJob`

- **Images:** `thumb.webp` 400 px and `web.webp` 1600 px through Imagick (present), GD as the
  fallback. Orientation applied on the derivative only.
- **Video, when ffmpeg is configured:** poster at `min(1s, duration/2)` → WebP 1280 px;
  playback copy at 720p H.264 + AAC with `+faststart` only when the original is not H.264/AAC,
  exceeds 1080p or 40 Mbps. Otherwise playback is the original.
- **Video, when ffmpeg is absent:** no poster and no transcode. The player shows a generic
  poster with the duration the browser reports, plays the original from the signed URL (range
  requests go straight to R2), and the install's System Settings page says *"Video posters are
  off on this install — ffmpeg is not configured"* so the gap is visible to the administrator.
- Scratch: stream original → `storage_path('app/private/punch-scratch/{uuid}')`, process, upload
  derivatives as **new `file_uploads` rows** with the sidecar `role` set, `rm -rf` in `finally`
  **and** in `failed()`. `TMPDIR` documented in the deploy notes.
- Queue name `media`, with the `queue:work --queue=media,default` line in `docs/deployment-scheduler.md`.

### 3.7 Deploy requirements this introduces (new for this app)

- A **queue worker** must be running in production (supervisor / systemd), the same way the
  scheduler had to be. Mail today is sent synchronously, so this is the first thing that
  genuinely depends on it. Documented with the phase, with a Sentry monitor on the queue like
  the scheduled jobs have.
- `ffmpeg` / `ffprobe` on the server is **optional** — the plan degrades without it (§3.6).
- The R2 CORS policy already exposes `ETag` (`docs/deployment-cloudflare-r2.md`); nothing new.

### 3.8 Capture rules — unchanged from revision 1 §3.7

720p/30fps hint; **soft cap 90 s per clip, hard block at 3 minutes**, enforced in the capture
component and again server-side at `init`; per-kind size caps; wifi-preferred with an explicit
"send now over mobile data" override; every capture records device, GPS and device time, with
`received_at` authoritative. The capture UI is `<x-punch.media-capture>` — an inline Livewire +
Alpine component wrapping the existing uploader with `capture="environment"` inputs for camera
and video, a per-file evidence payload, and the clip timer.

### 3.9 Issued-URL log — the audience log without a new table

Every signed URL handed out for evidence media is logged in the existing
`collaboration_activity_log` (subject = the `FileUpload`, action `url_issued`, context
`{audience, role, expires_at, ip}`), through a `PunchMediaUrlIssuer` with explicit audiences:

| Audience | Who | TTL | May receive |
|---|---|---|---|
| `internal` | staff | 60 s (install default) | original + derivatives |
| `buyer` | guest user who is the unit's buyer | 60 s | derivatives; original only through the termo viewer |
| `sindico` | guest user on the common-area scope | 60 s | same as buyer |
| `external_auditor` | guest with `punch-list.view_evidence` | 5 min | original + derivatives, every issuance logged with the reason given |

When counsel asks who saw which photo and when, that table is the answer — the same table that
already answers "the projetista opened the RFI on the 5th".

---

## 4. Domain model — revision 1 §2, translated to this schema

Migrations in this order, one table each, all additive. FK names in MySQL are auto-generated
and can exceed 64 characters on the morph indexes — name them, as `collaboration_distribution_entries` had to.

### 4.1 Structure (phase 1)

```
project_locations
  id, uuid, project_id, job_site_id (nullable), parent_id (nullable, self)
  type        string(20): tower | block | floor | unit | room | common_area
  name, code, path (varchar 500, materialized: "T1/12/1204/banho-suite"), sort_order
  timestamps, soft deletes
  index (project_id, type), index (path(191)), index (parent_id)

project_units
  id, uuid, project_id, job_site_id (nullable), location_id (unique → project_locations)
  number, typology, private_area (decimal 10,2)
  buyer_id (nullable → unit_buyers)
  transfer_status   string(20): pending | under_review | approved | contracted        -- repasse
  handover_status   string(30): blocked | eligible | inspected | pending_repair
                                | accepted | accepted_with_reservations | refused | delivered
  keys_delivered_at, cdc_apparent_deadline_at, habite_se_at (copied from project at seeding)
  timestamps, soft deletes
  index (project_id, handover_status), index (job_site_id, handover_status)

unit_buyers
  id, project_id, name, cpf (encrypted cast — never in a WHERE), email, phone
  user_id (nullable → users)              -- the guest login, when one was issued (§6)
  timestamps, soft deletes
  -- every read of `cpf` that shows it unmasked is logged (§6.3)

trades
  id, name, slug (unique), color, default_responsible_user_id (nullable), sort_order, is_active
  seed: alvenaria, revestimento, pintura, hidráulica, elétrica, esquadrias,
        impermeabilização, gesso, marcenaria, vidros, louças e metais, limpeza

checklist_templates      id, name, scope_type string(20) (unit | common_area | contract), typology (nullable), is_active
checklist_template_items id, template_id, location_type, group, text, norm_ref, sort_order
```

`project_locations.type` is driven by a `location_level_preset` on the project (`br_vertical`:
torre → pavimento → unidade → ambiente; `br_horizontal`: quadra → lote → ambiente; `us`:
building → floor → unit/area → room). The preset decides labels and the allowed nesting; the
code never hard-codes Brazilian level names.

**Roster importer:** CSV/XLSX (`maatwebsite/excel` is *not* installed — use the CSV path first
with `fgetcsv`, and ask before adding a package for XLSX). Expects torre / pavimento / unidade /
tipologia / área columns, tolerant of dirty data, previews before committing, never overwrites a
unit that already has an inspection.

### 4.2 Inspection and items (phase 3)

```
inspections
  id, uuid, number (via NumberSequenceService, type 'inspection': "VIS-0001")
  project_id, job_site_id (nullable), location_id, unit_id (nullable)
  type       string(30): site | provisional_acceptance | final_acceptance | unit_handover
                        | re_inspection | common_area_handover | technical_assistance
  parent_inspection_id (nullable)      -- a re-inspection points at the original
  scheduled_at, started_at, finished_at
  inspector_user_id (nullable), external_inspector json {name, company, art_rrt, council}
  outcome    string(30), nullable while open: accepted | accepted_with_reservations | refused
  status     string(30): draft | in_field | awaiting_signature | completed
  checklist_template_id (nullable)
  created_by, updated_by, timestamps, soft deletes
  index (project_id, status), index (unit_id, type), index (scheduled_at)

inspection_participants
  id, inspection_id, name, cpf (encrypted, nullable), email (nullable)
  role       string(20): buyer | sindico | engineer | representative | third_party
  signature_id (nullable → collaboration_signatures), signed_at
  -- ip / user_agent live on the signature row

inspection_checklist_answers
  id, inspection_id, template_item_id, result string(10): ok | nok | na
  note, punch_item_id (nullable)       -- a `nok` normally raises an item
  unique (inspection_id, template_item_id)

punch_items
  id, uuid, inspection_id, project_id, job_site_id (nullable), location_id, unit_id (nullable)
  trade_id (nullable)
  number      unsignedInteger          -- per inspection, shown to the buyer as "PL-0012 · 3"
  title, description (longText)
  severity    string(20): minor | moderate | serious | blocking
  origin      string(20): internal | buyer | third_party_report | technical_assistance
  responsible_vendor_id (nullable → vendors)      -- the contractual party
  responsible_user_id   (nullable → users)        -- the person who will act
  task_id (nullable, unique → tasks)              -- §7
  due_date
  status      string(20): open | in_progress | resolved | re_inspection | closed
                          | reopened | unfounded
  closed_at, closed_by_id, closed_in_inspection_id (nullable), reopened_count
  ncr_id (nullable)
  created_by, updated_by, timestamps, soft deletes (+ delete_reason, deleted_by)
  index (status, due_date), index (location_id), index (responsible_user_id, status),
  index (responsible_vendor_id, status), index (task_id)

punch_item_measurements
  id, punch_item_id, quantity string(20): humidity | crack | level | plumb | noise | temperature
  value decimal(10,3), unit string(10), instrument, norm_ref, taken_at

punch_item_ncrs
  id, punch_item_id (unique), nc_description, root_cause, corrective_action,
  preventive_action, effectiveness_check, verified_by_id, verified_at
```

**Audit trail:** `punch_item_events` from revision 1 is **not** a new table. Items, inspections,
terms and claims use `LogsCollaborationActivity` → `collaboration_activity_log` (subject morph,
actor, action, context json, ip, `created_at` only — append-only by construction). Actions:
`created, status_changed, responsible_changed, due_date_changed, media_added, media_quarantined,
measurement_added, task_created, task_synced, reopened, closed, viewed, exported, url_issued`.

`responsible_vendor_id` goes into `Vendor::SUBCONTRACTOR_FK_TABLES` in the same migration, or a
vendor merge strands the rows (`app/Models/Vendor.php`).

### 4.3 Documents and warranty (phases 5 and 8)

```
inspection_terms
  id, uuid, inspection_id (nullable), unit_id (nullable), project_id
  type   string(30): inspection | acceptance | refusal | provisional_receipt
                     | definitive_receipt | warranty
  pdf_file_upload_id, content_hash (sha256 of the rendered PDF)
  generated_at, generated_by, revoked_at, revoked_by, revoked_reason
  -- signatures: HasSignatures → collaboration_signatures (signable = the term)
  -- the signed artifacts: file_uploads with sidecar kind=document role=signed, parent = pdf

warranties
  id, project_id, unit_id (nullable), scope string(10): private | common
  system, component (NBR 17170 tables), term_months
  start_event string(20): habite_se | keys_delivered | assembly
  starts_at, expires_at

warranty_events
  id, warranty_id, claim_id, type string(20): repair | replacement
  executed_at, new_expires_at            -- max(remaining original, 90 days)

warranty_claims                          -- chamados, assistência técnica
  id, uuid, number ("AT-0001"), project_id, unit_id, buyer_id
  channel string(20): portal | email | phone
  description, defect_type string(10): apparent | hidden
  discovered_at, opened_at, cdc_deadline_at
  warranty_id (nullable), assessment string(20): in_warranty | out_of_warranty
                                                  | misuse | lack_of_maintenance
  status, sla_visit_at, resolved_at, task_id (nullable → tasks)   -- §7 applies here too
```

**Term immutability:** the PDF is rendered once, hashed and stored. A signature attaches as a
**separate artifact** (an appended flattened page or a PAdES-signed copy, as a new `file_uploads`
row). **Never re-render a PDF after signing** — it invalidates the hash and any qualified
signature, which is exactly what a perícia would find.

### 4.4 State machines the server enforces

**Unit `handover_status`**

```
blocked ──(habite_se + cnd_obra on project, repasse contracted)──► eligible
eligible ──(inspection completed)──► inspected
inspected ──outcome accepted──────────────────────────► accepted ──(keys)──► delivered
inspected ──outcome accepted_with_reservations───────► accepted_with_reservations ──(keys)──► delivered
inspected ──outcome refused───────────────────────────► refused ──(repairs)──► pending_repair ──(re-inspection)──► inspected
delivered ──► (warranty; never terminal — prohibition 6)
```

**Inspection `status`**: `draft → in_field → awaiting_signature → completed`. `awaiting_signature`
is reachable **only after `outcome` is set** (prohibition 4). `completed` requires the
construtora's signature and, for a handover, the buyer's or a recorded refusal to sign (with
reason — a buyer who will not sign is a real outcome, not an error).

**Punch item `status`**

```
open ──► in_progress ──► resolved ──► re_inspection ──► closed
  │                          ▲              │
  │                          └──(failed)────┴──► reopened ──► in_progress
  └──► unfounded (with reason; reversible by the inspector)
```

Only an inspection closes an item — `closed` is written by the re-inspection screen with
`closed_in_inspection_id`, never by a button on the item and never by a task (§7.4).

---

## 5. Termo, signatures, outcomes (phase 5)

### 5.1 PDF

dompdf (the only PDF engine in the app), through a `PunchDocumentRenderer` that follows
`CollaborationDocumentRenderer`: one renderer, per-country templates chosen by
`config('app.country')`, A4 for BR, the company wordmark inlined as a data URI exactly the way
`MeetingMinuteRenderer::logo()` resolves it (`companies.logo` on the public disk — **not**
`Branding`, which is the app icon). Photo grid from the `web` derivatives; **video as a QR code
and short link** to a signed viewer page (a PDF cannot embed video usefully). QR generated
server-side with **`bacon/bacon-qr-code`, which is already installed** as a dependency of
`laravel/fortify` — rendered as inline SVG for dompdf. No new package.

A term prints **`DRAFT — NOT YET ISSUED`** until generated, exactly as an RFI does.

### 5.2 Outcomes

`accepted`, `accepted_with_reservations`, `refused` — all first-class. **`refused` has its own
term (`refusal`)**, and a re-inspection spawns a child inspection carrying forward only the open
items.

### 5.3 Signatures — DECIDED in revision 1: both levels, split by signer

| Signer | Level (Lei 14.063) | Mechanism |
|---|---|---|
| Buyer (adquirente) | simples → avançada | On-site canvas on the inspector's device + evidence pack (ip, user_agent, device, geo, photo of the moment optional); optional e-mail/OTP confirmation afterwards to strengthen |
| Síndico (common areas) | simples → avançada | same |
| Engenheiro responsável / construtora | **qualificada** | ICP-Brasil e-CNPJ / e-CPF through a provider |
| Termo de garantia | **qualificada** | ICP-Brasil |

**Built on `collaboration_signatures`**, extended by one migration: `user_agent`,
`signer_role` (buyer / sindico / engineer / representative), `signer_cpf` (encrypted, nullable),
`artifact_file_upload_id` (the drawn PNG or the provider's signed PDF), `provider_name`,
`provider_envelope_id`, `evidence` json (`otp_confirmed_at`, geo). `method` already exists and
gains the values `canvas` and `provider`.

```php
interface SignatureDriver {
    public function request(InspectionTerm $term, Signer $signer): Signature;
    public function handleCallback(array $payload): void;
    public function verify(Signature $signature): VerificationResult;
}
```

`CanvasDriver` ships in phase 5 (an Alpine pointer-events pad → PNG → `file_uploads` under
`punch/signatures/`; **no new JS package**). A provider driver (Clicksign, D4Sign, ZapSign,
Autentique — REST + webhooks) is a later, separate change once **the client's counsel confirms
which term types they want at qualificada level** (still open, as in revision 1).

The `HasSignatures` "changed since signed" check already exists and is shown on screen and in the
PDF; the term's `signaturePayload()` is the content hash plus the outcome and the item list.

---

## 6. Buyers, síndicos and external inspectors — the guest system (phase 8)

Revision 1 wanted magic-link portal access that is "not licensed seats — hundreds of buyers
touching the system a handful of times". **This install already has that:** a guest is a
`users` row with `is_guest = true`, created from a hashed-token invitation, holding a
`Membership` on a project or job site with a permission template, seeing nothing company-wide by
construction (`PermissionResolver`, the guest header), and every screen already filters through
`visibleTo()`. Building a second portal would mean a second auth stack, a second audit trail and
a second place for LGPD to go wrong.

### 6.1 How a buyer is narrowed to one unit

A membership scopes to a project or job site, not a unit. The module adds the second filter:

```php
// PunchItem, Inspection, InspectionTerm, WarrantyClaim
public function scopeVisibleTo(Builder $q, ?User $user): Builder
{
    // 1. the usual confinement (project / job-site memberships)
    // 2. if the user is a buyer ( unit_buyers.user_id ), only rows on their unit(s)
    // 3. if the user is a síndico guest, only rows on common-area locations
}
```

A guest with the `buyer-unit` template gets `punch-list.view` + `punch-list.view_evidence` +
`warranty.create_claim` on the job site and is narrowed by (2). The template is seeded in
`PermissionSeeder::systemTemplates()` beside `client-project`.

### 6.2 What a buyer can do

See their inspection(s), their items with photos and status, their term(s), open a **chamado**
(warranty claim) with photos, and confirm a canvas signature by e-mail/OTP. Nothing else.

### 6.3 LGPD

- `cpf` is an `encrypted` cast on `unit_buyers` and `inspection_participants`; shown **masked**
  (`***.***.123-45`) everywhere; the unmask action requires `punch-list.view_personal_data`
  (`sensitive`) and writes `personal_data_viewed` to the activity log with the actor and ip.
- Invitations carry the buyer's name and e-mail only; the CPF never travels in a payload.
- The buyer's own `visibleTo()` narrowing is tested with two buyers on the same floor.

### 6.4 External laudo

A third-party inspector's PDF is uploaded as a `file_uploads` document on the inspection with
the inspector registered in `external_inspector` (name, company, ART/RRT, council); items are
created in batch from a review screen and carry `origin = third_party_report`.

---

## 7. Connection to the tasks module — NEW

The owner's requirement: *the punch list must be connected with our tasks module*. What that
buys, all from the existing module: the responsible person sees the repair in **My Tasks** and
on the dashboard, gets the **assigned / overdue / weekly digest** e-mails, can add notes and
files, and the item can be **put on a meeting agenda** and carried forward until closed — with
the minute showing what was said about it each week.

### 7.1 The shape: one item, at most one task, the item stays the legal record

| Question | Decision (recommended; owner to confirm) |
|---|---|
| Is every punch item a task? | **No.** A pendência is a legal record with a buyer, a severity, photos and a re-inspection; most of a 384-item delivery never needs a person's to-do list. A task is spawned **on demand**, individually or in bulk. |
| Cardinality | **1 : 0..1** — `punch_items.task_id`, unique. A task can belong to one item. The inverse is `Task::punchItem()` (`hasOne`), so the tasks module needs no column. |
| Grouping | The bulk action can create **one parent task per group** (trade × unit, or trade × inspection) with a sub-task per item. Sub-task progress rolls up on the parent automatically, and one line on the agenda covers the group. Two levels is the tasks module's maximum, which is exactly this. |
| Who owns the task | `punch_items.responsible_user_id` if set, else `trades.default_responsible_user_id`, else chosen in the bulk dialog. The vendor's people are guest users when the subcontractor works in the system; otherwise the internal person who manages that subcontractor. |
| Source of truth | **Work progress lives on the task; closure lives on the item.** A task's `ready` means "I say it is fixed"; only a re-inspection says it is closed. |

### 7.2 Creating tasks

- **From an item:** *Criar tarefa* on the item's detail. Requires `punch-list.assign` **and**
  `tasks.create` on the item's scope — the second because the button really does create a task.
- **In bulk:** on the inspection and on the punch-list page, filtered by trade / responsible /
  severity: select all → *Gerar tarefas* → dialog with owner, assignees, due date (defaults to
  each item's own `due_date`), parent-grouping toggle, and a preview line per task.
- Field mapping, through `TaskService::create()` so numbering, activity, e-mails and My Tasks all
  happen exactly as for any task:

| Task field | From |
|---|---|
| `title` | `"{inspection number} · {item number} — {title}"`, e.g. `VIS-0012 · 3 — Trinca na parede da sala` |
| `description` | location path, severity label, the item description, a link to the item |
| `project_id` / `job_site_id` | the inspection's |
| `owner_id`, assignees | the dialog |
| `priority` | severity: blocking → urgent, serious → high, moderate → normal, minor → low |
| `due_date` | the item's |
| `origin_meeting_id` | null — a punch task is a **direct** task and reaches an agenda only when somebody adds it, exactly like a project task |

The item logs `task_created`; the task's first activity note says where it came from.

### 7.3 Keeping the two in sync — `PunchItemTaskSync`

One service, called from two places: `TaskService::transition()` (one added line, a hook for
linked records) and the punch item's own state changes.

**Task → item**

| Task moves to | Item becomes | Notes |
|---|---|---|
| `in_progress` (or progress > 0) | `in_progress` | |
| `blocked` | unchanged | the item shows a *blocked* badge with the task's reason |
| `ready` | `resolved` | "the responsible says it is fixed" — queues the item for re-inspection |
| `completed` | **refused while the item is open** | see §7.4 |
| `cancelled` | **refused while the item is open** | cancel from the punch side (`unfounded`) instead; the button explains |

**Item → task**

| Item moves to | Task becomes | Notes |
|---|---|---|
| `closed` (after re-inspection) | `completed` | system transition; activity note "Fechada na reinspeção VIS-0013 por {inspector}" |
| `reopened` (failed re-inspection) | reopened to `in_progress` | reason = the inspector's note; the task's overdue clock restarts from the new due date |
| `unfounded` | `cancelled` | reason carried over |
| `due_date` changed | `due_date` updated | logged on both |
| `responsible_user_id` changed | owner changed | logged on both; e-mail goes out as any reassignment does |
| soft-deleted | `cancelled` with reason "Pendência excluída" | never deleted — a task that was on an agenda must stay in the minute |

Conflict rule, as revision 1 §4 already says: **server wins on status transitions**, and here the
item's transition is the server's word.

### 7.4 The rule that keeps the record honest

**A task linked to an open punch item cannot be completed from the tasks side** — not by the
chair in a meeting, not by an admin. `Task::canConfirmCompletion()` gains one condition, and the
button in the task detail and on the running-meeting screen says *"Fecha na reinspeção da
pendência"* with a link. The reason is legal, not technical: NBR 17170 makes the unregistered
defect the buyer's problem later; a defect that shows as *done* because somebody pressed a
button in a meeting, with no inspector having looked, is the opposite of what the signed record
is for. `Task::canDelete()` refuses while linked, the same way it refuses inside a published minute.

`TaskService` needs a **system-actor path** for the item-driven transitions (`completed` from
`ready` is normally chair-only; `reopen` is normally reason + grant). One method,
`applyLinkedTransition(Task, string $to, User $actor, string $reason)`, which logs the actor and
the punch reason and bypasses only the person-based checks, never the state machine.

### 7.5 What each side shows

- **Task detail modal** gains a *Pendência* card: unit / location path, severity, the first three
  `thumb` derivatives, inspection number and status, re-inspection date if scheduled, link to the
  item. `MyTasks` rows carry a small `PL` badge, and the list can filter *punch items only*.
- **Punch item detail** gains a *Tarefa* card: status, progress bar, owner and assignees, last
  note, every meeting where it was discussed (from `meetingItems`), link to the task.
- **Meeting minute item** for a linked task shows the unit and item number beside the task code,
  and `status_at_meeting` also snapshots the item's status so the minute stays truthful.
- **Project / job-site punch-list page** stats row: open items, items with a task, items
  resolved awaiting re-inspection, overdue tasks — the numbers a delivery manager checks before
  the weekly meeting.

### 7.6 Warranty claims use the same link

`warranty_claims.task_id` follows §7.2–7.4 unchanged: a chamado spawns a task for the technical
assistance team, and only closing the chamado (with the buyer's confirmation or the SLA record)
completes it.

### 7.7 Known gap inherited from the tasks module

`docs/to-review/2026-08-26-task-assignment-confinement-gap.md`: the owner/assignee pickers offer
every active user, including people `Task::visibleTo()` will hide. The bulk dialog here filters
its pickers to people who can see the item's scope, and the fix for the tasks module proper is
scheduled in its own review, not here.

---

## 8. Delivery campaign, common areas — phases 6 and 7, unchanged in content

- **Unit grid** as the primary screen: accepted / total, blocked count by cause, by trade, by
  responsible party; the daily KPI the delivery manager watches — units with an accepted
  inspection over total, per block.
- **Gates:** `habite_se_at` and `cnd_obra_at` on the project block campaign start;
  `transfer_status` blocks key handover per unit. Both shown as explicit blocked states with what
  is missing, never a greyed button.
- **Bulk scheduling** of inspections with buyer notification (Mailable, logged like every other
  send, respecting `notification_preferences`).
- **Common areas:** `common_area_handover` inspection type, síndico as signer, condomínio as
  warranty owner, its own claim queue and its own 90-day clock.

Where it lives: a `handover` tab (`field` group) at project level, with the job-site twin showing
that site's units; a summary card on both Overview pages, mirroring `open-tasks-card`.

---

## 9. Offline and mobile — decision deferred, schema ready

Revision 1's NativePHP phase becomes a **decision phase** after the web flow is real:

- **Web now:** the inspection screen buffers the current inspection in IndexedDB through Alpine
  (items, answers, queued captures), so a dropped connection mid-walk loses nothing; sync order
  is inspection → items → media; client-generated UUIDs mean no id remapping; conflict policy is
  last-write-wins on item fields, **union on media**, server wins on status. The technician sees
  an explicit unsent count.
- **Then decide:** PWA with background sync **vs** NativePHP, on the evidence of a field trial —
  camera access, background upload on iOS, battery. Validate before phase 9 is planned in
  detail, not during it.

---

## 10. Permissions — declared in phase 1, guarded as each phase is built

Two areas, both under a new `punch-list` module key in `config/modules.php` (declared **before**
`projects`, or `projects.*` claims its routes):

```php
'punch-list' => [
    'name'   => 'Punch List',
    'module' => 'punch-list',
    'levels' => ['global', 'project', 'job_site'],
    'money'  => false,
    'swept'  => true,                    // every area is swept now; a new one ships enforced
    'actions' => [
        'view', 'create', 'edit', 'delete',
        'manage_structure' => ['name' => 'Manage locations, units and buyers', 'sensitive' => true],
        'manage_templates' => ['name' => 'Manage trades and checklists'],
        'inspect'          => ['name' => 'Run and complete an inspection'],
        'assign'           => ['name' => 'Set the responsible party and generate tasks'],
        'close'            => ['name' => 'Close an item on re-inspection'],
        'issue_term'       => ['name' => 'Generate and sign a term', 'sensitive' => true],
        'export'           => ['name' => 'Export and print'],
        'distribute'       => ['name' => 'E-mail a term', 'sensitive' => true],
        'view_evidence'    => ['name' => 'Open original photos and video'],
        'view_personal_data' => ['name' => 'Reveal a CPF', 'sensitive' => true],
        'revise'           => ['name' => 'Revoke a signed term', 'sensitive' => true],
    ],
],
'warranty' => [                         // declared in phase 8, not before (an unused ability fails the suite)
    'name' => 'Warranty', 'module' => 'punch-list',
    'levels' => ['global', 'project', 'job_site'], 'money' => false, 'swept' => true,
    'actions' => ['view', 'create', 'edit', 'delete',
        'assess' => ['name' => 'Assess a claim against the warranty'],
        'close'  => ['name' => 'Close a claim']],
],
```

**Only the actions a phase actually guards are declared in that phase** —
`AbilityCatalogTest::test_every_declared_ability_is_enforced_somewhere` fails otherwise, which is
the point. Phase 1 declares `view / create / edit / delete / manage_structure / manage_templates`;
each later phase adds its own.

Held back in `PermissionSeeder`: `delete`, `manage_structure`, `view_personal_data`, `revise` are
**admin-only** (personal data, and undoing a signed record); `issue_term`, `distribute`, `close`
are **manager-only**. Every entry gets its one-line reason. Templates: *Site Supervisor* gains
`view / create / edit / inspect / assign`; new guest templates `buyer-unit` and
`subcontractor-punch` (view + edit on their own items, so a vendor's person can work the task).

Tabs: `punch-list` and `handover`, both `field` group, both levels, labels in
`lang/{en,pt_BR}/navigation.php`. PDF controllers guard **in the controller against the record's
own scope**, as `CollaborationPdfController` does. File keys are never served by `FileController`
(signed URLs only), so nothing is added to `ALLOWED_DIRECTORIES`.

The full checklist from `docs/permissions-for-new-modules.md` is copied into §12 and ticked per
phase.

---

## 11. Phases

Each phase ends with migrations, Pest tests, pt_BR strings in the same change, the abilities it
guards declared and enforced, and a working screen at **both** levels. **Do not start the next
phase before the previous one's tests pass.** Work happens on the **`punch-list`** branch; the
owner merges.

| # | Phase | Delivers | Depends on |
|---|---|---|---|
| **0** | Discovery ✅ | this document | — |
| **1** | Permissions, module registration, structure | `config/modules.php`, area + tabs + nav labels, `project_locations`, `project_units`, `unit_buyers`, `trades`, checklist templates, presets, the roster importer (CSV), seeders for the NBR 17170 visible-failure checklist and the default trades, the structure screens (tree editor, unit grid skeleton, buyer form with masked CPF), permission test | — |
| **2** | Media evidence layer | `file_upload_media`, `FileUploadService` target + per-target caps, `VerifyMediaJob`, `GenerateDerivativesJob`, `PunchMediaUrlIssuer` + log, `<x-punch.media-capture>`, queue worker deploy note. **Acceptance (from revision 1):** a 300 MB video uploads from a laptop on a throttled connection, survives a page reload mid-upload, and ends `ready` with a poster (or, without ffmpeg, `ready` with the "no poster on this install" notice) | 1 |
| **3** | Inspection capture | `inspections`, `inspection_checklist_answers`, `punch_items`, measurements, `InspectionRun` full-page component with room-by-room navigation, add-item-with-photos, trade + responsible, IndexedDB buffer; project / job-site punch-list pages with filters and stats; item detail (everything the record knows) | 2 |
| **4** | **Tasks connection** | `punch_items.task_id`, `PunchItemTaskSync`, `TaskService` hook + system-actor path, the two rule changes on `Task`, create-task and bulk-generate dialogs, the *Pendência* card in the task modal, the *Tarefa* card in the item, `PL` badge and filter in My Tasks, minute snapshot | 3 |
| **5** | Terms, signatures, outcomes | `inspection_terms`, signature columns, `CanvasDriver`, outcomes wired (`refused` first-class), re-inspection spawning, `PunchDocumentRenderer` BR + US templates with photo grid and video QR, PDF controllers, distribution | 3 |
| **6** | Delivery campaign | unit grid dashboard, gates, KPI, bulk scheduling + buyer notification, Overview cards | 5 |
| **7** | Common areas | inspection type, síndico signer, condomínio warranty owner, own clock | 6 |
| **8** | Buyer access, warranty, technical assistance | `buyer-unit` template + unit narrowing, buyer screens, `warranties` / `warranty_events` / `warranty_claims`, the NBR 17170 90-day floor, CDC deadlines, external laudo ingestion, `warranty` area | 5 |
| **9** | Field mode decision | PWA vs NativePHP trial; whichever wins is its own plan | 3 |
| **10** | **Review and Improvements** | code review of the whole module (guards, N+1s, the sync rules, anything keyed by hand), walk every screen in both themes, both locales, on a phone, with 384 locations seeded; close the gap between what screens say and what code enforces; re-read every term template against prohibition 5; sweep this module's rows in `docs/review-and-improvements.md`; docs and pt_BR level with what was built | all |

Phase 4 sits before the term phase deliberately: the owner asked for the tasks link, and it
depends only on items existing. Phases 5 and 8 depend on 3, not on 4.

### Testing (revision 1 §6, adapted)

- Pest, feature tests per **state machine**, not per method: unit handover, inspection,
  punch item, the task sync (both directions, plus the refusals in §7.4), warranty recompute.
- Permissions test in `tests/Feature/Permissions/PunchListTest.php`: reproduced, revocable,
  scoped, separate — plus *"a buyer on 1204 cannot see 1203"*.
- Media: multipart happy path, resumed upload, hash mismatch → quarantine, oversize refused at
  `init`, orphan abort, a renamed text file refused, **tamper test** (alter the R2 object out of
  band, re-verify, assert quarantine). Local stand-in is the `local` mode plus a fake S3 client;
  **confirm multipart against real R2 in staging before phase 3 ships** — MinIO and fakes are
  more permissive about part sizes than R2 is.
- Seed fixture: 1 tower, 12 floors, 4 units per floor, ~8 rooms each — ~384 locations, the
  smallest dataset where the dashboard's performance problems show. Run the suite as a **BR
  install** (`APP_COUNTRY=BR`, locale `pt_BR`) as well as US — the RFI module found 28 tests that
  only passed in English.

---

## 12. Permissions checklist (from `docs/permissions-for-new-modules.md`)

- [ ] Area declared in `config/permissions.php` — `levels`, `money`, actions, `swept => true`
- [ ] Menu entry / project tab declared in the same file, labels in both `navigation.php`
- [ ] `ADMIN_ONLY_ABILITIES` / `MANAGER_ONLY_ABILITIES` updated, each with a reason
- [ ] Added to system templates (Site Supervisor; new guest templates)
- [ ] `mount()` guarded on every component
- [ ] **Every** action method guarded — including every bulk action and every `wire:click`
- [ ] Records fetched by id checked against their own scope, never the screen's
- [ ] Routes without a `mount()` carry `ability:` middleware
- [ ] **Every PDF controller guarded with the same grant as its screen, against the record**
- [ ] `visibleTo()` on every model listed across projects; the unit grid's aggregates narrowed too
- [ ] Buyer / síndico narrowing tested
- [ ] `tests/Feature/Permissions/PunchListTest.php`: reproduced, revocable, scoped, separate
- [ ] pt_BR strings in `lang/pt_BR/punch-list.php` in the same change
- [ ] `AbilityCatalogTest`, `LegacyBehaviourTest`, `SecurityStateTest` updated; full suite green

---

## 13. Decisions to confirm with the owner before phase 1

| # | Question | Recommendation |
|---|---|---|
| 1 | **Is a torre a job site or a location?** In this product a job site is a *Local* inside a project. For an incorporadora the empreendimento is the project; its towers could be job sites (each with its own team, budget, daily reports) **or** locations under one job site. | Support both: `project_locations.job_site_id` nullable. The preset decides the top level, and the importer asks once per project. |
| 2 | Responsible party on an item: the vendor, the person, or both? | **Both** — `responsible_vendor_id` (contractual) + `responsible_user_id` (acts, becomes the task owner). |
| 3 | Tasks: one per item, spawned on demand, with optional parent grouping (§7.1)? | Yes. |
| 4 | **A linked task cannot be completed from a meeting** — only the re-inspection closes it (§7.4). | Yes; this is the legal-integrity rule. |
| 5 | Buyers as guest users (§6) rather than a token-only portal? | Guest users — one auth, one audit trail, one LGPD surface. |
| 6 | Will `ffmpeg` be installed on the production server? | Optional; the plan degrades without it. Yes if video posters in the termo matter to the customer. |
| 7 | A **queue worker** in production (new requirement). | Required from phase 2. |
| 8 | Roster import: CSV only in phase 1; XLSX needs a package. | CSV first; decide XLSX when the customer's spreadsheet is seen. |
| 9 | Which term types at qualificada level, and which provider. | Counsel's call; affects only the provider driver. |
| 10 | Field mode: PWA vs NativePHP. | Decide at phase 9 on trial evidence. |

---

## 14. Where this sits in the queue, and related documents

Behind nothing that blocks it: the permissions module is deployed, the collaboration module is
merged, and the tasks module's remaining phase 8 does not touch the pieces this plan uses.
Build on the **`punch-list`** branch from `main`.

- [`punch-list-module-implementation-plan.md`](./punch-list-module-implementation-plan.md) — revision 1, the specification this revision adapts
- [`../rfi-aprovacoes-discovery.md`](../rfi-aprovacoes-discovery.md) — the earlier reconciliation whose findings apply here
- [`../permissions-for-new-modules.md`](../permissions-for-new-modules.md) — the checklist in §12
- [`../meetings-module-plan.md`](../meetings-module-plan.md) §3.5, §4 — the tasks data model and state machine §7 hooks into
- [`../file-repository-plan.md`](../file-repository-plan.md) — the R2 pipeline §3 builds on
- [`../deployment-cloudflare-r2.md`](../deployment-cloudflare-r2.md) — bucket, CORS, `ETag`
- [`../project-jobsite-parity-rule.md`](../project-jobsite-parity-rule.md) — both levels, always
- [`../review-and-improvements.md`](../review-and-improvements.md) — where mid-build findings go
