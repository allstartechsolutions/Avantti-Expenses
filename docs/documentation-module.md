# Documentation Module — as built

A reading area inside the app at **`/documentation`**, holding two kinds of guide in one library.
Built 2026-08-20, uncommitted.

---

## 1. Two sources, one library

| | Shipped guides | Company guides |
|---|---|---|
| Written as | markdown files under `docs/` | rich text in the app |
| Registered in | `config/documentation.php` | the `doc_articles` table |
| Who writes them | whoever ships the code | an admin or manager |
| Why | they version with the code, so a guide can never describe a release it does not match | each install has its own procedures and should not wait for a release |

A reader is not meant to care which is which, beyond a small badge — *Product guide* or
*Written here*. `DocumentationService` merges both into one ordered library, groups it by category,
and searches across titles, summaries and **body text** of both kinds.

### Adding a shipped guide

Write the markdown under `docs/`, then add one entry to `config/documentation.php`:

```php
'closing-a-job-site' => [
    'title' => 'Closing a Job Site',
    'summary' => 'One line for the card in the library.',
    'category' => 'projects',
    'file' => 'docs/closing-a-job-site.md',
    'order' => 20,
],
```

Nothing else. Images go under `docs/images/…` and are referenced relatively from the markdown.

---

## 2. Screens

| Route | What |
|---|---|
| `documentation.index` | The library: cards grouped by category, search, counts |
| `documentation.show` | One guide, with a contents list, previous/next, and the rest of the library beside it |
| `documentation.create` / `edit` | Writing a company guide, in the TinyMCE editor |
| `documentation.asset` | Serves images referenced by shipped guides |

Everyone signed in can **read**; admins and managers **write**; only an admin **deletes**. A guide
kept as a **draft** is visible only to those who can write.

---

## 3. The two things worth knowing

### Images live in cloud storage

Guide images go to the same Cloudflare R2 bucket as everything else, under the
`documentation/` prefix, as `file_uploads` rows owned by **no record** — a shipped guide is a
markdown file, not a row, so its images belong to the library itself. (`file_uploads.attachable_*`
was made nullable for exactly this.)

**Getting them there.** The markdown keeps *relative* paths, so a guide stays portable across
installs, and each install pushes its own copy up with:

```
php artisan documentation:sync-images
```

The object key mirrors the path the guide writes (`documentation/docs/images/meetings/01.png`), so
rendering finds it without a lookup table. Files already stored with the same checksum are skipped,
so running it twice costs nothing. With no cloud storage configured the command says so and changes
nothing.

**Serving them.** `documentation.image` takes the file's uuid and redirects to a **freshly signed
URL** each time. The URL in the guide is permanent; the signature is not. Baking a signed URL into
the markdown would give a guide images that stop working after five minutes.

**Adding images while writing.** The guide editor has an image button — opt-in via
`<x-ui.tinymce-editor :uploads="true">`, so every other editor in the app keeps exactly the
toolbar it had. What is dropped in posts to `documentation.images.store`, which checks the role,
the type and a 10 MB cap, stores it in the bucket and hands TinyMCE back the permanent URL.

**The fallback, and the old route.** If an image has not been synced — or the install has no cloud
storage — rendering falls back to `documentation.asset`, which serves it from disk.
`DocumentationImageController` checks, in this order:

1. the path resolves with `realpath()`;
2. the resolved path sits **inside** one of `documentation.image_roots` (`docs/images`);
3. the extension is one of `documentation.image_extensions`.

Anything else is a 404 — the same 404 in every case, so probing tells an attacker nothing.
Verified: a real image returns 200, `docs/meetings-module-plan.md` returns 404, `../.env` returns
404, `.env` returns 404.

### Rendered HTML goes through the sanitiser

Markdown output and editor output both pass through `App\Support\RichText`, which drops every tag
outside a narrow allowlist and **every attribute** except a scheme-checked `href`/`src` — and, in
document mode, the `id` on a heading that the contents list jumps to. Editor content is sanitised
on save **and** on display.

---

## 4. Typography

Tailwind's reset removes list markers and heading sizes, and this install has no typography
plugin, so rendered content needs real CSS or it arrives as one undifferentiated block. Two
classes in `resources/css/app.css`:

- **`.doc-body`** — a full guide: headings with anchors, tables that scroll inside their own box,
  code blocks, blockquotes, framed images.
- **`.rich-text`** — a short note, used by the meeting notes.

Both are written for light and dark.

---

## 5. Files

```
config/documentation.php                        the categories and the shipped guides
app/Models/DocArticle.php                       a company guide
app/Services/DocumentationService.php           merges, searches, renders
app/Http/Controllers/DocumentationImageController.php    disk fallback
app/Http/Controllers/DocumentationFileController.php     serves from the bucket
app/Http/Controllers/DocumentationUploadController.php   the editor's image button
app/Console/Commands/SyncDocumentationImages.php
app/Livewire/Documentation/                     index, article, form
resources/views/livewire/documentation/
resources/css/app.css                           .doc-body and .rich-text
database/migrations/2026_08_20_100000_create_doc_articles_table.php
database/migrations/2026_08_20_100001_add_documentation_module_to_module_access.php
database/migrations/2026_08_20_110000_allow_file_uploads_without_an_owner.php
```

**Deploy:** `php artisan migrate`, `npm run build`, `php artisan config:clear`, then
`php artisan documentation:sync-images` to put the guide images in this install's bucket.

---

## 6. Verified

Shipped guide renders with 18 headings, images routed, tables wrapped, anchors added; a company
guide written through the form appears beside it and is found by searching its body text; editor
content is sanitised on save (`<p onclick>` and `<script>` both stripped); drafts are hidden from a
plain reader; an employee gets 200 on the library and **403** on the writer, and is not shown the
button; an unknown slug 404s; the module toggle takes the whole area to 403 and back; 251 Blade
views compile. The eleven guide images were uploaded to R2 and the guide was loaded in a real
browser: every `<img>` returned **302 from the app → 200 from R2** and decoded at full resolution
(2880×2000). The editor's upload endpoint returned the permanent URL for a PNG and **422** for a
text file, and the estimate and invoice editors were checked to confirm they did **not** gain the
image button.
