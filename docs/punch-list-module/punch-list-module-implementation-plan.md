# Punch List / Lista de Pendências — Implementation Plan

Handoff spec for Claude Code. Target: the multi-tenant construction platform (Laravel, Postgres, nwidart/laravel-modules, Livewire + Tailwind/Alpine, Cloudflare R2 per tenant, NativePHP for mobile).

First customer is an **incorporadora building an apartment complex for sale**, so the unit-handover flow (`vistoria de entrega`) is the core, not an add-on. US punch-list semantics are a locale variant of the same engine.

---

## 0. Conventions Claude Code must follow

- PSR-12.
- **Incremental migrations only.** Never edit a shipped migration; add a new one.
- Feature-grouped component folders. Full-page Livewire components for main features, inline components for reusable UI.
- PascalCase component classes, kebab-case views.
- Postgres. Use native types: `jsonb`, `uuid`, `generated always as identity`, partial indexes, `citext` where appropriate.
- All new tables live in the tenant database, not the landlord database.
- Module name: `PunchList`. Namespace `Modules\PunchList`.

### Hard prohibitions

These are not style preferences — violating them creates legal or operational failures:

1. **Never proxy media bytes through PHP.** All uploads go browser/device → R2 directly via presigned URLs. PHP only signs, verifies, and records.
2. **Never mutate an original media object.** Derivatives (poster, transcode, thumbnail) are separate R2 objects. Originals keep their EXIF/GPS — that metadata is evidence in a perícia.
3. **Never hard-delete media or pendências.** Soft-delete with actor and reason. The warranty tail is 5+ years.
4. **Never build a flow where the buyer signs the termo de recebimento before or in order to perform the vistoria.** That is an abusive practice under CDC and a live source of lawsuits against construtoras. Order is always: vistoria → outcome → signature.
5. **Never ship a termo template that disclaims responsibility for aesthetic defects.** Void under CDC art. 51, I.
6. A unit never reaches a terminal "closed forever" state. After handover it transitions to warranty/assistência técnica.

---

## 1. Legal and normative constraints to encode

These drive fields and state transitions. Do not treat them as documentation-only.

| Rule | Source | Encoded as |
|---|---|---|
| 90 days to claim vícios aparentes, counted from effective delivery | CDC art. 26, II | `unidades.cdc_aparente_deadline_at`, set on key handover |
| Vício oculto: term starts when the defect becomes apparent | CDC art. 26 §3 | `chamados.vicio_type`, deadline computed from `discovered_at` |
| 5 years for solidez e segurança | Código Civil art. 618 | `garantias` row seeded per unit at habite-se |
| Repair does not renew warranty; remaining term or 90 days, whichever is greater | NBR 17170:2022 | `garantia_eventos` recompute rule |
| Termo de garantia definitivo + manual de uso, operação e manutenção delivered at handover | NBR 17170:2022 | required artifacts on the delivery checklist |
| Table of visible failures the buyer should catch at handover (16 examples) | NBR 17170:2022, Tabela 3 | seeded checklist template |
| Unregistered apparent defects can later be attributed to the user, not the builder | NBR 17170 guidance | makes the signed, photographed termo mandatory, not optional |
| Common-area 90-day clock runs from delivery of the empreendimento / condomínio's receiving vistoria | CDC applied to condomínio | separate `vistoria` type and warranty owner |
| Habite-se and CND da Obra must exist **before vistorias begin** | practice; blocks repasse | campaign gate, see Phase 4 |

Public/contract work (`recebimento provisório` → `definitivo`, Lei 14.133/2021 art. 140) is the same engine with a different `vistoria.type` and termo type. Build it, but it is not the first customer's path.

---

## 2. Domain model

Migrations, in order. One migration per table. Prefix `punch_` is unnecessary — the module owns these names.

### 2.1 Structure

```
locations
  id, parent_id (nullable, self-ref), project_id
  type            enum: torre | bloco | pavimento | unidade | ambiente | area_comum
  name, code
  path            materialized path (e.g. "T1/12/1204/banho-suite")
  sort_order
  index on (project_id, type), index on path (text_pattern_ops)

unidades
  id, location_id (unique), project_id
  numero, tipologia, area_privativa
  adquirente_id (nullable)
  repasse_status    enum: pendente | em_analise | aprovado | contratado
  entrega_status    enum: bloqueada | apta | vistoriada | pendente_reparo
                        | aceita | aceita_com_ressalvas | recusada | entregue
  chaves_entregues_at, cdc_aparente_deadline_at
  index on (project_id, entrega_status)

adquirentes
  id, nome, cpf (encrypted), email, telefone, portal_token_hash
  LGPD: CPF encrypted at rest; log every read in an access audit table

trades              -- disciplinas, tenant-configurable, NOT CSI divisions
  id, name, slug, color, default_responsible_id
  seed: alvenaria, revestimento, pintura, hidraulica, eletrica, esquadrias,
        impermeabilizacao, gesso, marcenaria, vidros, louças e metais, limpeza
```

`locations` is a generic tree because the US locale needs building → floor → area. Do not hardcode Brazilian level names; drive them from a `location_level_preset` on the project.

### 2.2 Inspection and items

```
vistorias
  id, project_id, location_id, unidade_id (nullable)
  type      enum: obra | recebimento_provisorio | recebimento_definitivo
                | entrega_unidade | re_vistoria | entrega_area_comum
                | assistencia_tecnica
  parent_vistoria_id (nullable)   -- re-vistoria points at the original
  scheduled_at, started_at, finished_at
  inspector_user_id (nullable)
  external_inspector jsonb        -- {nome, empresa, art_rrt, conselho}
  outcome   enum: aceita | aceita_com_ressalvas | recusada | (null while open)
  status    enum: rascunho | em_campo | aguardando_assinatura | concluida
  checklist_template_id (nullable)

vistoria_participantes
  id, vistoria_id, nome, cpf (encrypted), papel (adquirente|sindico|
      engenheiro|preposto|terceiro), signature_media_id, signed_at, ip, user_agent

pendencias
  id, vistoria_id, location_id, trade_id
  numero          -- per-vistoria sequential, shown to the buyer
  title, description
  severity        enum: leve | media | grave | impeditiva
  origin          enum: interna | adquirente | laudo_terceiro | assistencia
  responsible_type/responsible_id   -- morph: subcontractor, internal crew, supplier
  due_date, status enum: aberta | em_execucao | resolvida | reinspecao
                       | fechada | reaberta | improcedente
  closed_at, closed_by_id, reopened_count
  rnc_id (nullable)
  index on (status, due_date), index on (location_id)

pendencia_medicoes
  id, pendencia_id, grandeza (umidade|fissura|nivel|prumo|ruido|temperatura)
  valor numeric, unidade, instrumento, norma_ref, taken_at

pendencia_eventos          -- append-only audit, never updated
  id, pendencia_id, actor_id, actor_type, event, payload jsonb, created_at

rncs                       -- ISO 9001 / PBQP-H SiAC extension of a pendência
  id, pendencia_id, descricao_nc, causa_raiz, acao_corretiva,
  acao_preventiva, verificacao_eficacia, verificado_por_id, verificado_at

checklist_templates / checklist_template_items
  scoped by tipologia and location type; seed the NBR 17170 visible-failure set
```

### 2.3 Documents and warranty

```
termos
  id, vistoria_id (nullable), unidade_id (nullable)
  type   enum: termo_vistoria | termo_recebimento | termo_recusa
             | trp | trd | termo_garantia
  pdf_media_id, content_hash (sha256 of the rendered PDF)
  generated_at, signed_at, revoked_at, revoked_reason

garantias
  id, unidade_id (nullable), project_id, escopo enum: privativa | comum
  sistema, componente          -- per NBR 17170 tables
  prazo_meses, start_event enum: habite_se | entrega_chaves | assembleia
  starts_at, expires_at

garantia_eventos
  id, garantia_id, chamado_id, tipo enum: reparo | substituicao
  executed_at, new_expires_at   -- max(remaining original, 90 days)

chamados                        -- assistência técnica, post-handover
  id, unidade_id, adquirente_id, abertura_canal enum: portal | email | telefone
  descricao, vicio_type enum: aparente | oculto
  discovered_at, opened_at, cdc_deadline_at
  garantia_id (nullable), garantia_avaliacao enum: em_garantia | fora_garantia
       | uso_indevido | falta_manutencao
  sla_visita_at, resolvido_at
```

---

## 3. Media subsystem — images **and** video

This is Phase 0 and the highest-risk part. Build it first and build it well; everything else attaches to it.

### 3.1 R2 facts that constrain the design

Verified against current Cloudflare R2 docs:

- **R2 does not implement the S3 POST Object API — it returns 501.** Browser form-POST uploads will fail on large files. Use presigned `PUT` for small objects and **S3 multipart with presigned part URLs** for video. This is the single most common way people break video upload on R2.
- Multipart parts: **minimum 5 MiB, maximum 5 GiB, all parts equal size except the last, maximum 10,000 parts.**
- Max single-request upload 4.995 GiB; max object 4.995 TiB. Not a real constraint for us.
- Incomplete multipart uploads are **auto-aborted after 7 days** by default; configurable via lifecycle policy.
- Presigned PUT URLs **cannot enforce a maximum content length** the way S3 POST policies can. Size enforcement has to happen server-side after the fact.
- Presigned URLs generated with a `ContentType` require the client to send a matching `Content-Type` header.
- Max 1 write per second to the same object key. Use UUID keys and never overwrite.

### 3.2 Tables

```
media
  id uuid pk
  attachable_type / attachable_id      -- pendencia, vistoria, chamado, termo
  kind      enum: image | video | signature | document | audio
  role      enum: original | poster | playback | thumb | web
  parent_media_id (nullable)           -- derivatives point at the original
  r2_key, bucket, mime, bytes
  sha256                               -- integrity anchor
  width, height, duration_ms, codec, rotation
  captured_at        -- device clock, UNTRUSTED
  received_at        -- server clock, the legal anchor
  device jsonb       -- {device_id, model, os, app_version}
  gps  jsonb         -- {lat, lng, accuracy_m, captured_at}
  status    enum: pending | uploading | uploaded | verifying | ready
                | quarantined | failed
  quarantine_reason
  deleted_at, deleted_by_id, delete_reason

media_upload_sessions
  id, media_id, r2_upload_id, part_size_bytes, expected_bytes, expected_sha256
  parts jsonb            -- [{part_number, etag, bytes}]
  expires_at, completed_at, aborted_at

media_usage              -- per tenant + per project quota accounting
  scope_type/scope_id, bytes_originals, bytes_derivatives, object_count
```

### 3.3 Key layout

```
t/{tenant}/p/{project}/u/{unidade}/v/{vistoria}/{media_uuid}/original.{ext}
                                                            /poster.webp
                                                            /playback.mp4
                                                            /thumb.webp
```

Prefix-per-media makes lifecycle rules and bulk deletion trivial and keeps derivative names collision-free.

### 3.4 Upload flow

**Images** (typically < 8 MB): single presigned PUT, 15-minute TTL, Content-Type pinned in the signature.

**Video**: always multipart, even for small clips, so the resume path is exercised on every upload.

```
1. Client → POST /api/media/uploads
     {kind, mime, bytes, sha256, duration_ms, captured_at, device, gps,
      attachable_type, attachable_id}
   Server validates: mime allowlist, bytes <= per-kind cap, tenant quota,
   attachable exists and is writable. Creates `media` (status=pending) and
   `media_upload_sessions` with an R2 CreateMultipartUpload. Returns
   media_id, upload_id, part_size, and presigned URLs for parts 1..N.

2. Client PUTs each part directly to R2, collecting ETags. Persists
   {upload_id, part_number, etag} locally after every part so a killed app
   or a dropped connection resumes instead of restarting.

3. Client → POST /api/media/uploads/{id}/complete  {parts: [...]}
   Server calls CompleteMultipartUpload, sets status=uploaded, dispatches
   VerifyMediaJob.

4. Client can → POST /api/media/uploads/{id}/parts  to re-sign expired part
   URLs mid-upload. Signatures expire; large uploads over bad connections
   will outlive them.
```

Part size: **8 MiB**. Gives 80 GB headroom against the 10,000-part ceiling with room to spare, and is small enough that a failed part on 3G is cheap to retry.

### 3.5 Verification job (this is what makes the media defensible)

`VerifyMediaJob`:
1. `HeadObject` — confirm the object exists, and that `bytes` and `Content-Type` match what was declared. Mismatch → delete from R2, `status=quarantined`.
2. Stream the object and recompute SHA-256. Compare to the client-declared hash. Mismatch → quarantine. (Stream in chunks; never `file_get_contents` a 400 MB video.)
3. `ffprobe` for duration, dimensions, codec, rotation. Reject anything that isn't a real media file regardless of declared MIME.
4. Dispatch `GenerateDerivativesJob`.
5. Set `status=ready`. Only `ready` media renders in the UI or appears in a termo.

### 3.6 Derivatives

- **Video poster**: frame at `min(1s, duration/2)` → WebP, 1280px wide.
- **Playback copy**: if the original is not H.264/AAC, or exceeds 1080p, or exceeds 40 Mbps, transcode to 720p H.264 + AAC with `-movflags +faststart`. Otherwise reuse the original for playback. **The original is never touched.**
- **Images**: 400px `thumb.webp` and 1600px `web.webp`. Original retains full EXIF including GPS.
- ffmpeg runs on a dedicated queue (`media`) with its own worker and a concurrency cap. Do not run transcodes on the same workers that serve vistoria sync.

**R2 is the system of record. Nothing media-related persists on local disk.** The Laravel media disk is always the R2 driver; never `local`.

But the transcode job does need ephemeral scratch. ffmpeg cannot reliably process phone-recorded video straight from an HTTP stream — the `moov` atom is frequently at the end of the file, so ffmpeg needs to seek. Job shape:

```
1. Stream original from R2 → $scratch/{media_uuid}/original.{ext}
2. ffprobe, then ffmpeg → $scratch/{media_uuid}/{poster,playback}
3. Upload derivatives to R2
4. rm -rf $scratch/{media_uuid}   (also in a finally/failed handler)
```

Size the scratch volume at `max_video_bytes × worker_concurrency × 2.5` and set `TMPDIR` to it. Register a `failed()` handler on the job that cleans scratch — a crashed transcode that leaves 400 MB behind will fill the disk within a week of a delivery campaign.

Cost note: pulling originals back out of R2 for transcoding is cheap specifically because **R2 egress is free**. This pattern would be expensive on S3.

### 3.7 Capture rules (mobile and web)

- Record at 720p / 30fps, hardware H.264.
- **Soft cap 90 seconds per clip, hard block at 3 minutes.** Enforce in the UI and again server-side at session creation. Long walkthrough videos are useless as evidence and ruinous for storage; several short clips beat one long one.
- Per-kind size caps at session creation: image 25 MB, video 500 MB, document 50 MB.
- Default to upload-on-wifi with an explicit "send now over mobile data" override. Technicians on site will not thank you for burning their data plan.
- Every capture records device id, app version, GPS, and device timestamp — with the server's `received_at` as the authoritative time.

### 3.8 Bucket topology — DECIDED

**One bucket per tenant. No separate buyer-portal bucket. No public bucket.**

Rationale: R2 has no per-object ACLs. A bucket is either private or genuinely public via custom domain / `r2.dev` — there is no middle setting. Buyer media contains unit interiors, GPS coordinates, and termos bearing CPF, so public is not an option. Everything is already served via short-TTL signed URLs, and the buyer portal reads the same objects through the same mechanism with a narrower policy. A second bucket would only create a duplicate copy of documents that may end up in a perícia.

Separate by **prefix and audience** instead:

```
t/{tenant}/evidence/...   originals + derivatives. Never expires. Never public.
t/{tenant}/docs/...       termos, laudos, manuais, termo de garantia
t/{tenant}/tmp/...        scratch and abandoned uploads. Lifecycle: expire 7 days.
```

R2 lifecycle rules are prefix-scoped (max 1000 rules per bucket), which is exactly what this buys: `tmp/` expires automatically, `evidence/` never does.

**Do not implement storage tiering.** Infrequent Access halves storage to $0.010/GB-month but doubles Class A ($9.00/M) and Class B ($0.90/M) operations, adds a $0.01/GB retrieval fee, imposes a 30-day minimum billable duration, receives no free tier, and cannot be transitioned back to Standard via lifecycle. IA only wins for data written once and read almost never — and assistência técnica pulls old originals precisely during disputes. At Standard ($0.015/GB-month, free egress) a 200-unit delivery is roughly 40–50 GB, well under $1/month. This is not a cost worth engineering against.

### 3.9 Signed URL issuer

Build a `SignedUrlIssuer` with explicit audiences rather than a bare `Storage::temporaryUrl()` call scattered through the codebase:

```
audiences: internal | adquirente | sindico | external_auditor
per audience: TTL, allowed roles (original vs derivative), allowed prefixes
every issuance writes a row: media_id, audience, actor, ip, issued_at, expires_at
```

The access log is the point. When counsel asks who viewed which evidence and when, that table is the answer.

### 3.10 Housekeeping

- Nightly `AbortOrphanedUploadsJob`: abort R2 multipart uploads with no matching open session, or sessions untouched for 24 hours. Do not rely on R2's 7-day default.
- Nightly quota rollup into `media_usage`; block new upload sessions over quota with a clear, actionable error.
- Video plays from the signed URL directly so the player gets HTTP range requests without touching PHP.
- Optional later: expire derivatives (`poster`, `playback`, `thumb`) older than 18 months via lifecycle and regenerate on demand. Derivatives are reproducible; originals are not. Only worth doing if volume genuinely grows.

---

## 4. Offline behaviour

Web (Livewire) gets optimistic local buffering via Alpine + IndexedDB for the current vistoria. The real offline story is the NativePHP app in Phase 7.

Device outbox state machine, persisted in local SQLite:

```
captured → queued → uploading → uploaded → verified → ready
                 ↘ failed (retry with backoff, surfaced in a "pendências de envio" screen)
```

Rules:
- The vistoria is completable offline. Sync is a separate concern from completion.
- Sync order is deterministic: vistoria → pendências → media. A pendência never syncs before its vistoria exists server-side; use client-generated UUIDs as primary keys throughout so there is no ID remapping.
- Conflict policy: last-write-wins on pendência fields, **union on media** (never drop an uploaded asset), and server wins on `status`/`outcome` transitions.
- Show the technician an explicit unsent-item count. Do not hide sync state.

---

## 5. Phases

Each phase ends with migrations, Pest tests, and a working screen. Do not start the next phase before the previous one's tests pass.

### Phase 0 — Module scaffold + media subsystem
- `nwidart` module `PunchList`, tenant-aware service provider, queue connections.
- `media` + `media_upload_sessions` + `media_usage` tables.
- `MediaUploadController` (create session, re-sign parts, complete), `VerifyMediaJob`, `GenerateDerivativesJob`, `AbortOrphanedUploadsJob`.
- Livewire `<x-media-capture>` inline component: camera/file input, image + video, progress per part, retry.
- Tests: multipart happy path, resumed upload, hash mismatch quarantine, oversize rejection, orphan abort, ffprobe rejection of a renamed text file.
- **Acceptance:** a 300 MB video uploads from a laptop on a throttled connection, survives a mid-upload page reload, and ends `ready` with a poster.

### Phase 1 — Structure and taxonomy
- `locations`, `unidades`, `adquirentes`, `trades`, checklist templates.
- CSV/XLSX importer for the unit roster (they will hand you a spreadsheet — expect torre/pavimento/unidade/tipologia columns and dirty data).
- Seeder for the NBR 17170 visible-failure checklist and the default trade list.

### Phase 2 — Vistoria capture
- Full-page Livewire `VistoriaExecutar`: checklist walk, add pendência with photos and video, measurements, assign trade and responsible party.
- Room-by-room navigation matching how someone actually walks a unit.
- Tests: pendência creation, media attachment, checklist completion, draft resume.

### Phase 3 — Termo, signature, outcomes
- PDF generation with photo grid and video QR/short-link (a PDF can't embed playable video usefully — link to a signed viewer page instead).
- Three outcomes wired: `aceita`, `aceita_com_ressalvas`, `recusada`. **`recusada` is a first-class path with its own termo, not an error.**
- Re-vistoria spawns a child vistoria carrying forward only open pendências.
- Content hash of the rendered PDF stored on `termos`.

#### Signatures — DECIDED: both levels, split by signer

Lei 14.063/2020 recognises three levels (simples, avançada, qualificada). All are legally valid; only the **qualificada** (ICP-Brasil e-CPF/e-CNPJ) carries a presumption of authenticity without additional proof. The STJ's 3ª Turma has held that the absence of an ICP-Brasil certificate does not by itself invalidate an advanced signature, and that platform-signed contracts hold where the parties agreed to that method.

The operative constraint: **a buyer standing in an empty apartment does not have an e-CPF.** Requiring qualified signatures from adquirentes would break the handover flow. So the level is chosen per signer, not per document:

| Signer | Level | Mechanism |
|---|---|---|
| Adquirente | simples → avançada | On-site canvas + evidence pack; optional post-visit email/OTP confirmation to strengthen |
| Síndico (áreas comuns) | simples → avançada | Same |
| Engenheiro responsável / construtora | **qualificada** | ICP-Brasil e-CNPJ or e-CPF (A1 server-side, or A3 cloud) |
| Termo de garantia | **qualificada** | ICP-Brasil |

Result: every termo carries a qualified signature from the party bearing the liability, plus a strong evidentiary trail on the buyer's side.

Build the abstraction now even if only the canvas driver ships in Phase 3 — retrofitting is what makes this expensive:

```
signature_requests
  id, termo_id, signer (name, cpf, papel), driver enum: canvas | provider
  provider_name, provider_envelope_id, status, requested_at, signed_at
  evidence jsonb   -- ip, user_agent, device, geo, otp_confirmed_at
  signed_media_id  -- the resulting artifact

interface SignatureDriver {
    request(Termo $termo, Signer $signer): SignatureRequest;
    handleCallback(array $payload): void;
    verify(SignatureRequest $r): VerificationResult;
}
```

Provider driver targets: Clicksign, D4Sign, ZapSign, Autentique — all expose REST plus webhooks. Roughly 2–3 days for the canvas driver, 3–5 for the first provider driver.

**Immutability rule:** the PDF is rendered once, hashed, and stored. Signatures attach as *separate artifacts* — either an appended flattened page or a PAdES-signed copy stored as a new `media` row with `role=signed`. **Never re-render a PDF after signing.** Doing so invalidates both the content hash and any PAdES signature, which is exactly the failure a perícia will find.

Have the client's counsel confirm which level they want on which document before the provider integration is built.

### Phase 4 — Delivery campaign dashboard
- Unit grid as the primary screen: aprovada / total, blocked count by cause, by trade, by responsible party.
- Gates: `habite_se_at` and `cnd_obra_at` on the project block campaign start; `repasse_status` blocks key handover per unit.
- Daily KPI: units with vistoria aprovada / total per bloco — the metric the delivery manager actually watches.
- Bulk scheduling of vistorias with buyer notification.

### Phase 5 — Common areas
- `entrega_area_comum` vistoria type, síndico as signatory, condomínio as warranty owner, its own claim queue and its own 90-day clock.

### Phase 6 — Buyer portal + assistência técnica
- Magic-link / CPF portal access for adquirentes. **Not licensed seats** — there will be hundreds of buyers touching the system a handful of times each.
- Buyer sees their vistoria, their pendências, status, and can open a chamado.
- Warranty engine: evaluate a chamado against `garantias`, apply the NBR 17170 90-day floor on repairs, compute CDC deadlines.
- External laudo ingestion: upload a third-party inspector's PDF, register the inspector with ART/RRT, create pendências in batch.

### Phase 7 — NativePHP mobile
- Local SQLite outbox, resumable multipart from device, background upload, wifi preference.
- Camera integration with the capture caps from §3.7.
- **Validate early that NativePHP gives you adequate camera and background-upload access on iOS.** If it doesn't, the fallback is a PWA with IndexedDB plus background sync, and that decision should be made before Phase 7 starts, not during it.

---

## 6. Testing requirements

- Pest. Feature tests per state machine, not per controller method.
- Local R2 stand-in: MinIO in docker-compose with `S3_PATH_STYLE=true`. Confirm multipart behaviour against real R2 in staging before Phase 2 ships — MinIO is more permissive about part sizes than R2 is.
- A dedicated integrity test: upload, tamper with the R2 object out-of-band, re-run verification, assert quarantine.
- Seed a realistic fixture: 1 torre, 12 pavimentos, 4 units per floor, ~8 ambientes each. That's ~384 locations and is the smallest dataset where the dashboard's performance problems become visible.

---

## 7. Decisions — resolved

1. **Transcoding**: self-hosted ffmpeg workers. R2 is the system of record; local disk is ephemeral scratch only. See §3.6.
2. **Signatures**: both levels, split by signer — canvas for adquirente/síndico, ICP-Brasil for the construtora and every termo de garantia. Driver abstraction lands in Phase 3. See Phase 3.
3. **Storage tiering**: none. Standard class only. Client pays storage and it is a rounding error at this volume. See §3.8.
4. **Bucket topology**: one bucket per tenant, prefix-separated, no public bucket, audience-scoped signed URL issuer with an access log. See §3.8–3.9.

### Still open

- **NativePHP camera and background upload on iOS.** Validate before Phase 7 is planned in detail, not during it. Fallback is a PWA with IndexedDB plus background sync, which reshapes §4.
- **Which termo types the client's counsel wants at qualificada level.** Affects only the provider driver, not the abstraction.
- **Unit roster import format.** They will hand over a spreadsheet; shape the importer once you have seen it.
