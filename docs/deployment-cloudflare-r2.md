# Cloudflare R2 — setting up document storage

The document repository (`docs/file-repository-plan.md`) stores its files on **Cloudflare R2**
when the install is configured for it, and on the server's private disk when it is not. Nothing
in the module names a disk directly — it asks `App\Services\DocumentSettings`, which reads
`config/documents.php`.

**Each install uses its own bucket.** Nothing here is shared between customers.

---

## 1. Why R2

| | R2 | S3 |
|---|---|---|
| Storage | $0.015 / GB-month | $0.023 / GB-month |
| **Egress (downloads)** | **free** | ~$0.09 / GB |
| Class A ops (writes, lists) | $4.50 / million | ~$5 / million |
| Class B ops (reads) | $0.36 / million | ~$0.40 / million |

A document repository is read constantly — drawings opened on site, photos downloaded by the
office. On S3 the download traffic costs more than the storage; on R2 it is free. 500 GB of
project documents costs about **$7.50 a month**, downloads included.

---

## 2. One-time setup in the Cloudflare dashboard

### 2.1 Create the bucket

**R2 → Create bucket.** Name it per install, e.g. `despesas-documents-<customer>`. Location:
pick the automatic hint, or the region nearest the customer (`ENAM`, `WNAM`, `EEUR`, `WEUR`,
`APAC`, `OC`). Leave public access **off** — every download the app hands out is a presigned URL
that expires; the bucket itself must never be world-readable.

### 2.2 Create an API token

**R2 → Manage R2 API Tokens → Create API token.**

- Permission: **Object Read & Write**
- Scope: **this bucket only** (never "all buckets")
- TTL: no expiry, unless the customer has a rotation policy

Cloudflare shows the **Access Key ID**, the **Secret Access Key** and the
**S3 endpoint** (`https://<account-id>.r2.cloudflarestorage.com`) exactly once. Copy all three.

### 2.3 CORS — required, uploads fail without it

Uploads go **straight from the browser to the bucket**, so the bucket has to accept cross-origin
`PUT`s from the application's domain and expose the `ETag` header — without `ETag` the browser
cannot report the parts back and every multipart upload fails at the last step.

**R2 → your bucket → Settings → CORS policy → Add CORS policy:**

```json
[
  {
    "AllowedOrigins": ["https://app.example.com"],
    "AllowedMethods": ["PUT", "POST", "GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag", "Content-Length"],
    "MaxAgeSeconds": 3600
  }
]
```

Replace `https://app.example.com` with the install's real origin (scheme + host + port, no
trailing slash). Add the local development origin as a second entry when testing uploads from a
developer machine.

### 2.4 Lifecycle rule for abandoned uploads (recommended)

**Settings → Object lifecycle rules → Abort incomplete multipart uploads after 1 day.**
The application's own `documents:prune-uploads` command does this too; the bucket rule is the
belt to its braces, because incomplete parts are billed as stored data.

---

## 3. Application configuration

In the install's `.env`:

```env
DOCUMENTS_DISK=r2
R2_ACCESS_KEY_ID=<access key id>
R2_SECRET_ACCESS_KEY=<secret access key>
R2_BUCKET=despesas-documents-acme
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
```

Optional:

```env
DOCUMENTS_PRESIGN_TTL=60          # seconds a download/preview link stays valid
DOCUMENTS_MAX_UPLOAD=5368709120   # 5 GB ceiling per file
DOCUMENTS_RETENTION_DAYS=30       # how long deleted documents stay recoverable
DOCUMENTS_SHARE_DAYS=14           # default expiry for a share link
DOCUMENTS_QUOTA=                  # optional storage ceiling, shown as a usage bar
```

**About the download links.** Files are served straight from R2 through presigned URLs, so the
bytes never pass through PHP and cost no egress. The signature in the URL *is* the credential:
while it is valid, anyone holding that link can fetch that one file, with or without a login.
Permission is checked by the application before the link is issued, and every download is
recorded, but the link itself is bearer access. `DOCUMENTS_PRESIGN_TTL` is the size of that
window — 60 seconds by default. Lowering it further is safe for large files, because the
signature is checked when the request starts and a download already in flight is not
interrupted.

Then:

```bash
php artisan config:clear
```

`R2_PUBLIC_URL` is only needed if the bucket is exposed through a custom public domain. The
repository does not need it — it hands out presigned URLs.

**Safety net:** if `DOCUMENTS_DISK=r2` but any of the four values above is blank,
`DocumentSettings::disk()` falls back to the local private disk rather than throwing on every
upload, and the upload panel tells the user the smaller limit applies.

---

## 4. Verifying it works

```bash
php artisan tinker
>>> App\Services\DocumentSettings::disk();              // "r2"
>>> App\Services\DocumentSettings::isCloudConfigured(); // true
>>> Storage::disk('r2')->put('healthcheck.txt', 'ok');  // true
>>> Storage::disk('r2')->get('healthcheck.txt');        // "ok"
>>> Storage::disk('r2')->delete('healthcheck.txt');     // true
```

Then upload a file larger than 100 MB through the repository page — that is the path that
exercises multipart, presigning and CORS together. A failure at the final "completing…" step is
almost always the missing `ExposeHeaders: ETag` in §2.3.

---

## 5. Operational notes

- **Scheduler must be running.** `documents:prune-uploads` (hourly) aborts stale multipart
  uploads; `documents:purge-deleted` (daily) removes documents past the retention window.
- **Backups.** The module's own version history plus the Trash window is the recovery story for
  user mistakes. For disaster recovery, configure bucket-level replication or a scheduled
  `rclone` copy — that is an infrastructure decision, not an application one.
- **Moving an existing install to R2.** Set the env values, then copy the existing
  `storage/app/private/projects/**` tree into the bucket preserving keys (`rclone copy` or
  `aws s3 sync --endpoint-url`), then flip `DOCUMENTS_DISK`. Each document version records the
  disk it lives on, so a partially migrated install keeps serving old files from local storage.
- **Never make the bucket public.** Sharing outside the app is what the module's expiring share
  links are for; they are revocable and logged, a public bucket is neither.
