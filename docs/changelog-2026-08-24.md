# Changelog — branding, Forge scheduler, pt_BR sweep (2026-08-24)

Three unrelated pieces of work in one session. Nothing here changes a database table, a route
or a permission — it is branding, one deployment step, and a large translation pass.

---

## 1. Branding — the ManagerPro mark

The Laravel logo was still in place in two forms: the favicon set, and an inline SVG in the
`x-app-logo-icon` component that the login page and every auth screen rendered. Both now use
`https://media.managerpro.us/icon.png`, referenced **by URL** rather than copied into the repo.

The URL lives in one place, `config/app.php`:

```php
'logo_url' => env('APP_LOGO_URL', 'https://media.managerpro.us/icon.png'),
```

`APP_LOGO_URL` lets a given install override it. The old `favicon.ico` / `favicon.svg` were
deleted rather than replaced — leaving them would keep serving the Laravel mark to anything
requesting `/favicon.ico` by convention.

Also swapped: the gradient "A" placeholder plates in the sidebar, the mobile header and the
guest header. The app name in those headers now comes from `config('app.name')` instead of
three different hardcoded strings (`Avantti`, `Despesas`, `Avantti`).

**Consequence worth knowing:** `/favicon.ico` now 404s. Browsers use the `<link>` tags, but
anything probing the conventional path gets nothing, and the tab icon is a downscaled PNG
rather than a purpose-built 16px `.ico`.

**Sidebar:** the user block is now pinned to the bottom. The `<aside>` was `h-screen` but never
a flex container, so the `flex-1` and `overflow-y-auto` already on the nav were inert and a long
menu pushed the user block past the fold. Fixed with `flex flex-col` on the shell, `min-h-0` on
the nav (without it a flex item refuses to shrink below its content and never scrolls) and
`mt-auto shrink-0` on the footer.

---

## 2. Forge scheduler

Documented, not changed. **[`deployment-scheduler.md`](./deployment-scheduler.md)** covers it:
Forge needs **one** cron entry — `php8.4 /home/forge/<site>/artisan schedule:run`, every minute,
under **Scheduler**, not Background Process — and that single entry drives all four recurring
jobs (task overdue mail, weekly task digest, R2 upload pruning, document purge).

No queue worker is needed: there is no `ShouldQueue` anywhere, no `app/Jobs`, and every mail
call is synchronous. That changes the day someone queues a Mailable.

**Open:** `config/app.php` sets `'timezone' => 'EST'`, a fixed UTC−5 zone with no daylight
saving, and Laravel evaluates scheduled times in it. Overdue mail fires at 07:00 local in
winter and 08:00 in summer. Fixing it means moving every date in the application, so it needs
its own change — logged in `review-and-improvements.md`.

---

## 3. pt_BR translation sweep

Eight agents audited every module and found **773 user-facing English strings that were never
wrapped in `__()`**. Six repeating patterns accounted for most of them, so the fix was far
smaller than the count suggests. All seven items on the plan are now closed.

Full detail, including what was deliberately left alone, in
**[`pt-br-translation-audit.md`](./pt-br-translation-audit.md)**.

| Item | Outcome |
| ---- | ------- |
| Validation messages | `lang/pt_BR/validation.php` + a partial `lang/en/validation.php`. Two files, 550 `@error` sites across 89 files. There were no per-locale directories at all, so every form in the product showed English errors with raw column names ("job site id"). |
| Permission labels | 40 of 98 labels in `config/permissions.php` had no pt_BR. All 82 now resolve. |
| Status labels | Label accessors on 7 models; 49 raw `ucfirst($model->status)` sites down to 2 (both correct as-is). |
| Counted nouns | 35 `Str::plural()` calls → `trans_choice()`. None of the pt_BR plurals are "+s". |
| `$validationAttributes` | 78 of 85 entries were redundant with the new shared map and were deleted; 7 kept as translated overrides. |
| CSV exports | All 6 report exports were 100% English; all now render in pt_BR. |
| Fallbacks and screens | 63 empty-value fallbacks, both client-facing e-mails, the public payment page, the daily-report PDF, and the estimate/invoice forms. |

### Three bugs found while auditing, all fixed

- `components/ui/file-uploader.blade.php` never defined the `messages.etag` key that
  `resources/js/app.js` reads — an ETag failure showed the user literally `undefined`.
- `livewire/client/client-create.blade.php` had a malformed closing tag (`/p>`) rendering as
  literal text, and keyed its title on Portuguese (`__('Novo Cliente')`), so that page was
  Portuguese even in English.
- `Invoice` and `Estimate` both had a `getStatusLabelAttribute()` returning bare English
  (`'Draft'`, `'Past Due'`). Invoice's feeds the public payment page.

### Two rules that came out of it

1. **Grammatical gender is a constraint.** The shared status words are masculine because
   contracts and quotations are. A *despesa* is feminine, so expenses needed their own keys —
   the screens had been reading "Pago" where correct pt_BR is "Paga", including at sites that
   *were* already wrapped. `Task::getStatusLabel()` had documented this; follow it.
2. **Check the app's own glossary before inventing a term.** `pt_BR.json` already establishes
   Job Site → *Local*, Project → *Projeto*, Purchase Orders → *Ordens de Compra*.

### Decided, not pending

`en.json` maps `"Project"` to `"Job Site"`. Reviewed and **kept as is** — 36 blades render
through `__('Project')`, and that is the intended vocabulary for this install.

### Left in English on purpose

`welcome.blade.php` (~53 strings) is a layout reference page with hardcoded demo data, not a
screen anyone reaches. If it is dead code it should be deleted rather than translated.

---

## Deploying

Nothing to migrate. `php artisan view:clear` and `php artisan config:clear` after deploy, and
`npm run build` (the sidebar and login changes use utility classes that were not in the
previous bundle). Add the Forge scheduler entry if the site does not already have one.

Test suite at the end of the session: **564 passing**, 3 failures that pre-date this work
(the `register` route, which is not enabled on this install, and `ExampleTest` expecting 200
from a redirecting `home`).
