# pt_BR translation audit — 24 August 2026

Eight parallel agents audited every module for user-facing English that a Brazilian user
sees. **773 confirmed findings across 8 module groups.** Every finding was read on its
source line before being recorded; nothing here is a guess.

The headline is not the number. It is that the gap is **not** in the translation files —
those are in good shape — but in strings that were never wrapped in `__()` at all, and in
six repeating patterns that account for most of the total. Fix the patterns and the count
collapses.

## Where the count came from

| Module group | Findings |
| ------------ | -------- |
| Projects / job sites / clients | 198 |
| Contracts / estimates / invoices | 191 |
| Procurement (quotations, requisitions, POs, catalog, vendors) | 164 |
| Reports / dashboard / PDFs | 105 |
| Money modules (expenses, payments, budgets, cost codes) | 46 |
| Users / company / settings / permissions | 34 |
| Shared components and layouts | 31 |
| Meetings / tasks / documentation / share | 4 |

Meetings and documentation being near-clean is the useful signal: those were built most
recently, with `__()` applied as the work was done. The older modules were not.

## What the translation files actually look like

`lang/pt_BR.json` holds 4,104 keys against `lang/en.json`'s 2,521. Nothing in `en.json` is
absent from `pt_BR.json`. Of the 3,795 distinct literal `__()` keys called across `app/`,
`resources/views/`, `routes/` and `config/`, only **seven** have no pt_BR entry:

- `Images added here are uploaded to the same cloud storage…` — `documentation-form.blade.php`
- `Images in a guide are limited to 10 MB.` — `DocumentationUploadController.php`
- `Only images can be added to a guide this way.` — `DocumentationUploadController.php`
- `Re-filed from :number` — `MeetingMinuteDistributor.php`
- `Without this, totals, budgets and financial reports are hidden…` — `user-access.blade.php`
- `delivery date`, `note` — `PurchaseOrderShow.php`

A further 43 keys carry a pt_BR value identical to the English key. Most are correct as-is
(`Total`, `Subtotal`, `PDF`, `PIX`, `CEP`, `Bairro`, `SKU`, `Status`, `Material`,
`Supervisor`, `Acre`). Worth a second look: `Starter`, `incl.`, `Sub-Total`, `kg, m, un`,
and `:number — :title, :date`.

### The key-diff blind spot

A literal-key scan **cannot see `__()` called with a variable**, and there are 25+ such
sites — `AbilityCatalog.php:131`, `nav/item.blade.php:14`, `access-index.blade.php:96`,
`document-category-badge.blade.php:23` and others. Their keys live in `config/permissions.php`,
the nav config, enum `label()` methods and database columns.

Checking those sources directly: **`config/permissions.php` declares 98 human labels, 58
translated, 40 missing.** So the real missing-key count is 47, not 7. The access screens
currently render `Approve or reject`, `Award a round`, `Merge duplicates`, `Lock the budget`
and 36 others in English.

> **Partly closed, 27 Aug 2026.** The 17 tab labels and the 4 tab-group labels of the project
> / job-site bar were the worst of this blind spot, and they now live as literal keys in
> `lang/en/navigation.php` and `lang/pt_BR/navigation.php`, where a diff can see them
> (`docs/changelog-2026-08-27-nav-grouping.md`). The blind spot itself is unchanged: the ~40
> **ability** labels in `config/permissions.php` are still read through `__($variable)` and
> still English — that is P38 in `docs/review-and-improvements.md`. `Navigation::label()`
> falling back to the config `name` means a *new* tab can slip back into this silently, so a
> new tab's two `navigation.php` lines are part of the same change that adds it.

## The six patterns worth fixing first

Ordered by findings-per-edit, not by count.

### 1. Framework validation messages — 550 `@error` blocks across 89 files

`lang/` contains only `en.json` and `pt_BR.json`. There is **no `lang/pt_BR/validation.php`**
and no per-locale directories at all, so Laravel falls back to its built-in English strings.
Confirmed by booting the application and validating in each locale:

```
[en]     The title field is required.
[pt_BR]  The title field is required.          ← identical
         The amount field must be a number.
         The job site id field is required.    ← raw column name
```

Two failures in one: the message stays English, and `:attribute` interpolates the humanized
**database column**. This is the single largest gap in the application and it is **one file**
to fix, not 550 edits.

### 2. `$validationAttributes` — 22 components, ~83 entries

Every one hardcodes English attribute names (`'company name'`, `'postal code'`,
`'purchase unit'`). These feed the same validation messages as above, so even after fixing
(1), errors on the client, project, budget, supplier, subcontractor and catalog forms stay
half-English until these are wrapped or moved into the `attributes` array of
`lang/pt_BR/validation.php`.

### 3. `ucfirst($model->status)` — 58 sites in blades, only 9 wrapped

The stored enum is English and prints raw. The inconsistency is the tell:
`project-expenses.blade.php:192` and `expense-modal.blade.php:112` do `__(ucfirst(...))`,
while `project-show.blade.php:938`, `job-site-show.blade.php:813/890`,
`purchase-order-show.blade.php:480` and both financial reports do not — the same value
renders translated on one screen and English on the next. Two sites
(`quotation/partials/view-modal.blade.php:273`, `requisition/…:272`) inject a raw English
status *into an already-translated sentence*.

`pdf/payment-detail-report.blade.php` is the one that got it right and is the pattern to copy.

### 4. `Str::plural()` — 35 sites across 20 files

Emits an English noun straight into the page: `expense`/`expenses`, `report`, `task`,
`image`, `contract`, `purchase order`, `change order`, `record`, `worker`, `item`. These can
**never** translate as written; they need `trans_choice()` with a pt_BR plural key. Note that
pt_BR pluralisation is not always the `+s` that `Str::plural()` assumes.

### 5. Derived labels that cannot be wrapped

Three shared sites build a label from a key rather than from a string, so there is nothing to
wrap — each needs a key→label map:

- `components/project-layout.blade.php:12` and `components/jobsite-layout.blade.php:14` —
  `ucwords(str_replace('-', ' ', $active))`. **25 blade files** use these layouts, so every
  project and job-site breadcrumb ends in an English word while the tab bar directly beneath
  it is correctly translated (`project-nav.blade.php:28` wraps `$tab['name']`).
- `components/layouts/inc/sidebar.blade.php:74` —
  `ucfirst(Auth::user()->role->name)`. Seeded English role names under the user's name on
  **every authenticated page**. The `__('User')` fallback on the same line is wrapped; the
  actual value is not.
- `project/partials/expense-history.blade.php:38` — `ucfirst(str_replace('_',' ',$field))`
  renders a database column name as a human label.

### 6. `'Not provided'` and sibling fallbacks — 39 sites

`?? 'Not provided'`, `?? 'Unknown'`, `?? 'System'`, `?? 'unit'`, `'N/A'`. Individually
trivial, collectively the most common single string in the audit. A detail view of a record
with empty optional fields is currently a column of English.

## Client-facing output — fix ahead of internal screens

These leave the building and reach people who are not your users.

- **`InvoiceSendEmail.php:36–64` and `EstimateSendEmail.php:36–43`** build the whole default
  e-mail — subject, greeting, summary labels, sign-off — as concatenated English:
  `"Dear {$invoice->client->contact_name},"`, `"Best regards,"`. A Brazilian company invoicing
  a Brazilian client sends it in English.
- **`livewire/invoice/public-invoice-pay.blade.php` + `PublicInvoicePay.php`** — the public
  payment page, all seven card-validation messages hardcoded.
- **`pdf/daily-report.blade.php`** — 16 hardcoded strings, and they are the structural ones:
  the document `<title>`, the `Daily Log:` heading, `Project:`, the "Prepared by … on …"
  attribution, `2 Days`/`3 Days` weather headers, `# Hours`, `Works:`/`Comments:`, `Task {n}`
  and the `Printed On:` footer. Everything around them *is* wrapped, so the client receives a
  Portuguese document with English seams through the middle.
- **`pdf/project-financial-report.blade.php` and `pdf/job-site-financial-report.blade.php`** —
  status columns print raw enum values (`partially_paid` → "Partially paid").
- **`pdf/estimate.blade.php` / `pdf/invoice.blade.php`** — document `<title>` and the
  `Message` fallback heading.
- **CSV exports — 47 findings, 100% English.** Every report component
  (`ExpenseReport`, `PaymentDetailReport`, `SalesTaxReport`, `CompanyFinancialReport`,
  `PaymentScheduleReport`, `AccountsPayableReport`) hardcodes its header row, section titles
  and totals labels. Invisible on screen, so screen-walking will never find these.
- **`CardPointeService`** — 12 raw English gateway errors surfaced verbatim through
  `$this->cardError = $e->getMessage()`.

## English persisted into the database

`BudgetService.php:26–28` writes `'Job Site Budget'`, `'Project Budget'` and
`'Auto-created for expense tracking'` as stored record values. Translation alone will not fix
rows that already exist — this needs a decision about backfill, not just a `__()`.

The seeded role names (`RoleSeeder.php:18–26`) have the same shape.

## Bugs found while auditing — not translation issues

1. **`components/ui/file-uploader.blade.php:25–34`** defines `messages` with `type`, `size`,
   `empty`, `network`, `cancelled`, `failed` — but `resources/js/app.js:405` rejects with
   `this.config.messages.etag`, which is never defined. An ETag failure shows the user
   literally **`undefined`**. `livewire/documents/partials/upload-modal.blade.php:103` defines
   it correctly; the shared component needs the same key.
2. **`livewire/client/client-create.blade.php:7`** ends
   `…novo cliente no sistema/p>` — a **malformed closing tag**, rendered as literal text.
3. **`livewire/client/client-create.blade.php:6`** is `{{ __('Novo Cliente') }}` — correctly
   wrapped, but the *key itself* is Portuguese, so this page's title is Portuguese even under
   `en`. Every other page keys on English.
4. **Date formats are not localized.** 44 uses of `format('M d, Y')` plus
   `format('M d, Y - h:i A')`, `format('l n/j/Y')` and others render English month and day
   names regardless of locale. Only `budget-cost-grid.blade.php` and the dashboard `welcome`
   partial use `translatedFormat()`. Three settings components hardcode a US format
   (`DocumentMessageSettings.php:212`, `ModuleAccessSettings.php:60`, `TaxRateSettings.php:182`)
   while `notification-settings.blade.php:32` correctly branches on `config('app.country')`.

## Progress

| # | Item | State |
| - | ---- | ----- |
| — | The three bugs (uploader `etag`, malformed `/p>`, Portuguese key) | **Done** |
| 1 | `lang/pt_BR/validation.php` + `lang/en/validation.php` | **Done** — 550 `@error` sites |
| 2 | `config/permissions.php` labels | **Done** — 82/82 resolve, 0 missing |
| 3 | `ucfirst($model->status)` | **Done** — 49 unwrapped sites → 2, both correct as-is |
| 4 | `Str::plural()` → `trans_choice()` | **Done** — 35 sites, 0 `Str::plural` left in views |
| 5 | `$validationAttributes` arrays | **Done** — 78 of 85 entries deleted as redundant, 7 kept as translated overrides |
| 6 | CSV export headers | **Done** — all 6 exports render in pt_BR |
| 7a | Empty-value fallbacks | **Done** — 63 sites wrapped |
| 7b | Client-facing e-mails + public payment page | **Done** |
| 7c | Remaining per-screen literals | **Done** — `pdf/daily-report`, the 4 estimate/invoice forms, send-email notices, budget-create, daily-report-form. `welcome.blade.php` deliberately skipped |

### Notes from the work done so far

**Label accessors now exist on every model that needed one.** `Expense`, `ExpensePayment`,
`Contract`, `CatalogItem`, `Quotation`, `PurchaseRequisition` and `Role`. Each exposes a
`static ...Label(?string $value)` alongside the instance method, so a screen can label a bare
string — a filter value, a history row, an array key — without instantiating a model. Prefer
the static over `Model::make([...])->getLabel()`.

**`payment_method` / `payment_frequency` live in a trait**, `Models\Concerns\HasPaymentMethodLabel`,
because Expense, ExpensePayment and PurchaseOrder all store the same enum.

**Two accessors were returning untranslated English and no one had noticed:**
`Invoice::getStatusLabelAttribute()` and `Estimate::getStatusLabelAttribute()` had bare
`'Draft'`, `'Sent'`, `'Past Due'` strings. Both are now wrapped. `Invoice`'s feeds the public
payment page, so that was client-facing.

**The audit trail reuses the validation attributes map.** `Expense::fieldLabel()` translates a
changed column name through `validation.attributes.*` instead of duplicating the schema, with
overrides where an expense means something different from a form (`supplier_id` → Vendor, not
Preferred supplier) and a humanised fallback for anything unmapped.

**The fallback sweep had to distinguish labels from data.** A naive count of `'unit'`
found 29 hits, but 20 were array *keys* (`$item['unit']`), not labels — only literals in a
fallback position (`?? 'X'` / `?: 'X'`) qualify. The same filter excluded enum values
(`'approved'`, `'invited'`), state keys and CSS classes (`'bg-gray-100 text-gray-800'`).
63 genuine fallbacks were wrapped: `Not provided`, `Unknown`, `N/A` (→ *N/D*), `Message`,
`System`, `unit`, `No Supplier`, `No email address on file`.

**Both client-facing surfaces are translated.** The estimate and invoice send-email builders
now compose from `__()` with `:number` / `:name` / `:company` placeholders instead of
concatenated English, and the public payment page — page copy plus all nine card-validation
messages — is done. These were the highest-priority items in this audit because they reach
people who are not users of the system.

**All six CSV exports now translate** — header rows, section headings, totals labels and the
row values (`Project-level`, `No vendor`, statuses via the model label accessors). Verified by
generating each file in both locales, not by reading the source.

**Wrapping a string can change the English output, because `en.json` remaps some terms.**
`"Project"` is mapped to `"Job Site"` in `en.json` (the same family as `"Add New Job Site"` →
`"Add New Lot"`), so an English CSV now prints `"Job Site","Job Site"` for the Project and Job
Site columns. This is **not** a regression introduced here: `expense-report.blade.php:362`
already renders the on-screen Project column through `__('Project')`, so the export now matches
the screen. pt_BR is unaffected (`Projeto` / `Local`).

**Decision (24 Aug 2026): leave the mapping as it is.** The owner reviewed it and chose to keep
`"Project" => "Job Site"` in `en.json`. Do not "fix" this — 36 blades render through
`__('Project')`, and changing the key would move the wording on every one of them. If an
English screen or export reads oddly, that is the intended vocabulary for this install, not a
translation bug.

**Most `$validationAttributes` entries were redundant once the shared map existed.**
Of 85 entries across 22 components, **78 declared exactly what `lang/<locale>/validation.php`
already says** — so 11 components had their whole property deleted and now fall through to the
shared map, which is the only version that translates. The 7 genuinely component-specific
names (`name` → *supplier name* / *budget name*, `address_2` → *complement*,
`employee_email` → *email*) moved from a `protected $validationAttributes` **property** into a
`validationAttributes()` **method**, because a property cannot call `__()`. The 6 components
that already used the method form were correct and untouched.

Rule for new components: declare a name here only when it differs from the shared map.

**pt_BR plurals are not `+s`, which is exactly why `Str::plural()` could never work.**
The 12 counted nouns needed: `item → itens`, `imagem → imagens`, `trabalhador → trabalhadores`,
and the compound ones pluralise the *head*, not the tail — `ordem de compra → **ordens** de
compra`, `ordem de alteração → **ordens** de alteração`. All follow
`trans_choice(':count noun|:count nouns', $n, ['count' => $n])`, the convention already used
by 83 keys in the file. Zero takes the plural form in both locales ("0 despesas"), which is
correct pt_BR.

**Only two `ucfirst()` calls remain in blades and both are right:** a card brand
(`Visa`, proper noun) and a fallback for an unmapped catalog category, where the mapped
values are already wrapped.

**Grammatical gender is a real constraint, not a detail.** `Task::getStatusLabel()` already
documented it: the shared status words are translated in the masculine ("Pago", "Vencido",
"Cancelado") because contracts and quotations are masculine. A *despesa* is feminine, so the
expense screens were rendering "Pago" where correct pt_BR is "Paga" — wrong even at the nine
sites that were already wrapped in `__()`. `Expense::getStatusLabel()` therefore uses
expense-specific keys (`Expense status: paid` → "Paga"), following the Task precedent.
`Contract::getStatusLabel()` reuses the shared keys, because *contrato* is masculine and they
are already right.

**Follow the app's own glossary.** `pt_BR.json` establishes Job Site → **Local** and
Project → **Projeto**. A first draft of `validation.php` invented "canteiro de obras" and
"obra"; both were corrected. Any new pt_BR string should be checked against the existing keys
before being written.

**18 models already have `getStatusLabel()`.** Prefer extending that pattern over wrapping
`ucfirst()` at the call site — it puts the label in one place and lets each model choose the
right gender. Expense and Contract were the two big ones missing it.

### What was deliberately left in English

- **`resources/views/welcome.blade.php`** — a layout reference page with hardcoded demo data
  (`JD` initials, a fake profile), not a screen any user reaches. ~53 strings. Translating it
  would add keys nobody reads; if it is dead code it should be deleted instead.
- **`PIX`, `PDF`, `SKU`, `CEP`, card brands (`Visa`)** — proper nouns, identical in pt_BR.
- **Example placeholders** — `contact@example.com`, `CO-0001`, `Rua Example, 123`.
- **`ucfirst($categoryFilter)`** in `pdf/expense-report.blade.php` — a fallback for an unmapped
  catalog category; the mapped values are already wrapped.

## Suggested order of work

1. `lang/pt_BR/validation.php` with its `attributes` array — one file, 550 rendering sites.
2. The 40 `config/permissions.php` labels — data-only, no code changes.
3. Client-facing output: the two send-email builders, the public payment page, `pdf/daily-report`.
4. The two breadcrumb layouts and the sidebar role label — three edits, every screen.
5. `Str::plural()` → `trans_choice()`, 35 sites.
6. `ucfirst($status)` → a translated label accessor, ~49 unwrapped sites.
7. The `$validationAttributes` arrays, 22 components.
8. CSV export headers, 6 report components.
9. The long tail of `'Not provided'` fallbacks and per-screen literals.

Items 1–4 are a small number of edits with disproportionate reach; 5–7 are mechanical and
well-suited to being done module by module during each module's Review and Improvements phase.

## Method, and what this audit does not cover

Eight agents, one per module group, each running multiple independent passes (blade text
nodes with directives stripped, user-visible attributes, string literals inside `{{ }}` and
`@php` blocks, and a PHP pass over flash messages, `dispatch`, `addError`, custom validation
messages, `abort*`, `throw` and Mailable subjects). Every reported line was read in context.
Excluded by instruction: CSS, route names, wire targets, Alpine/JS expressions, config keys,
example placeholders (`contact@example.com`), icon and variant names, and proper nouns that
are identical in pt_BR (`PIX`, `PDF`, `SKU`).

Not covered: JavaScript strings in `resources/js/`, seeded and migrated database content
beyond the two cases named above, and the quality of existing pt_BR translations — this audit
found what is missing, not what is wrong.
