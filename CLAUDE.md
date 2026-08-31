# Project Guidelines and Standards

## Tech Stack (STRICT - Do not add other technologies unless explicitly requested)
- Laravel 12+
- Livewire 3.7+
- Alpine.js (latest version)
- Tailwind CSS
- MySQL (as configured)

## Design Standard (applies to EVERYTHING)

**Never ship the bare minimum.** This is a commercial product sold to real companies; every
screen is judged by a paying user. When a request could be satisfied cheaply or properly,
build it properly and say what was built.

Concretely, for every feature:
1. **Full-page modals for real work** — use `<x-ui.modal maxWidth="full">` for forms and
   detail views that carry more than a couple of fields. Sticky header (title + context +
   close), a spacious body on a `max-w-7xl` centre column, sticky footer with the actions.
   Small dialogs are for confirmations, not for data entry.
2. **Detail views show EVERYTHING the record knows** — every stored field, every derived
   figure, every related record (splits, payments, history, attachments), plus audit facts
   (created by / created at / last updated). If the database knows it, the detail view shows
   it. Never a subset.
3. **Show the numbers, not just the inputs** — running totals, remainders, percentages,
   progress bars. The user should never need a calculator to check the screen.
4. **Bulk and shortcut actions where a user would repeat themselves** — select all, split
   evenly, take remainder, and equivalents.
5. **Empty, partial and error states are designed too** — say what is missing and what to do
   about it, never a blank panel.
6. **Both themes, both locales, every screen size** — dark mode, `__()` on every string with
   the pt_BR translation added in the same change, and no horizontal scroll on mobile.
7. **Consistency beats novelty** — reuse the existing components (`x-ui.*`) and the layout
   patterns already in the codebase; when a level (project / job site) gains a UI
   improvement, the other level gains it too.

## Every Module Ships With Its Permissions

**A new module is not finished when its screens work.** The permission module
(`docs/permissions-module.md`) is complete and deployed: **33 areas, 170 abilities**, no role
checks left anywhere in the application. (It was 30/147 when the sweep finished; the
collaboration and procurement-assignment modules added the rest.) Anything built from now on joins it **as it is
built**, never afterwards — retro-fitting permissions onto eighteen modules took a week and
found forty-odd holes that had been live in production.

**Before writing the first screen of a new module, read
`docs/permissions-for-new-modules.md`** and copy its checklist into the module's plan. The
whole of it, in five lines:

1. **Declare the area** in `config/permissions.php` — its `levels` (`global` for a company
   record, plus `project` / `job_site` for anything that belongs to one), its `money` flag,
   its actions, and `swept => false` until the rest is done. Nothing exists until it is
   declared: the Gate refuses an undeclared ability.
2. **Guard every action method**, not only the destructive ones. Hiding a button is not
   protection — the `wire:click` behind it is a public endpoint. A route with no `mount()`
   gets `ability:` middleware instead. **Every PDF controller needs the same grant as its
   screen**, and every file directory goes into `FileController::authorizeFile()`.
3. **Filter every list**, with a `visibleTo()` scope. A guard answers "may you open this
   record?"; only a filter answers "which records may you see?" — and a total across projects
   somebody cannot open is a leak by aggregate.
4. **Money through `<x-ui.money>`**, with `rollup` on totals. `can_see_money` hides roll-ups,
   not records: a project total is the company's financial picture, the amount on an expense
   somebody filed is not a secret from them.
5. **Write the test** — reproduced, revocable, scoped, separate — and add the pt_BR strings in
   the same change. Then flip `swept => true` **in that same change**, not in a later phase: an
   area whose screens are guarded and filtered is finished, and leaving the flag behind is how
   work comes to look done while advertising that it is not.

**A declared ability that guards nothing is a lie the permission matrix tells.** Seven of them
accumulated before anybody noticed — each one grantable on the access screens and changing no
behaviour at all — because `swept` tracks whole *areas* and nothing checked individual
*actions*. `AbilityCatalogTest::test_every_declared_ability_is_enforced_somewhere` now fails
when an ability appears nowhere in `app/`, `routes/` or a view. Declaring an action you have
not built yet will break the suite, which is the point: build it, or do not declare it.

Two rules that hold everywhere and were each learned the hard way:

- **Never act on an id that came from the browser without checking which project it belongs
  to.** `findOrFail($id)` proves the record exists, not that this person may touch it.
- **Reproduce first, then make it revocable.** A change that quietly widens or narrows who
  can do something is a bug even when the new behaviour seems more sensible. Say what moved.

## Dates and Times Come From the Macros, Never From `format()`

**A Brazilian install showed `Aug 31, 2026`** on 144 screens until the sweep of 31 Aug 2026:
US order *and* English month names, which no locale setting fixes because `format()` never
translates. Four habits were in the codebase at once — 133 hardcoded `M d, Y`, 11 `m/d/Y`, 29
`d/m/Y` (wrong the other way round, on a US install), and 39 copies of the same country
ternary. There is one answer now, registered in `AppServiceProvider::registerDateMacros()`:

| Call | Brazil | United States |
|---|---|---|
| `->appDate()` | 31 ago 2026 | Aug 31, 2026 |
| `->appTime()` | 14:30 | 2:30 PM |
| `->appDateTime()` | 31 ago 2026 14:30 | Aug 31, 2026 2:30 PM |
| `->appDateLong()` | 31 de agosto de 2026 | August 31, 2026 |
| `->appDateShort()` | 31 ago | Aug 31 |
| `->appDateNumeric()` | 31/08/2026 | 08/31/2026 |

They work on every Carbon flavour — `Carbon\Carbon`, `Illuminate\Support\Carbon`,
`CarbonImmutable` — so a model attribute, `now()` and a parsed string all take them.

The month is a word on purpose — it is what this product has always shown, and it cannot be
misread the way a bare `08/31` can. `appDateNumeric()` is for the date **input** and nothing
else.

### Date fields are `<x-ui.date-input>`, never `<input type="date">`

A native date input renders in the **browser's** locale, which has nothing to do with
`app.country`: a Brazilian company whose staff run an en-US browser were typing into
`mm/dd/yyyy` all day, and no attribute can change that. `<x-ui.date-input wire:model="…">`
is a text field in this install's order (`dd/mm/aaaa` / `mm/dd/yyyy`) with the native control
kept beside it, hidden, for its picker alone. **The value crossing to Livewire is `Y-m-d`
exactly as before**, so nothing server-side changes; `.live` is carried through.

**Machine formats stay as they are.** `Y-m-d` fills a date input, `Y-m` is a grouping key,
`G` is an hour of the day. None is read by a person, and none may move when the country does.

`DateFormatSweepTest` fails if a display format is written by hand again, or if a native
`type="date"` comes back, anywhere in `app/` or `resources/views/` — PDFs and e-mail templates
included, which is where a wrong date is most likely to reach a client.

## Every Module Ships Translated

**A screen is not built until it is translatable.** The pt_BR sweep of 24 Aug 2026 found
**773 user-facing strings that had never been wrapped in `__()`** across eighteen modules —
a week of retro-fitting that would have been minutes per screen if done as the screens were
written. Translation is a build step, not a clean-up phase. Never leave it "for later".

**Wrap it as you write it, and add the pt_BR value in the same change.** A string added to
`en.json` without its `pt_BR.json` counterpart is unfinished work. See
`docs/pt-br-translation-audit.md` for the full findings; the rules below are what they taught.

1. **Every user-visible string goes through `__()`** — headings, labels, table headers, button
   text, empty states, help text, `placeholder=` / `title=` / `alt=` / `wire:confirm=`,
   `<option>` labels, flash messages, `addError()`, custom validation messages, `abort()`
   messages, Mailable subjects and e-mail bodies. If a person can read it, it is translatable.
2. **Never build a sentence by concatenation or by splitting it around a tag.** Use one key
   with placeholders — `__('Your payment of :amount for invoice :number has been processed.')`.
   Portuguese agreement and word order do not follow English, so a sentence assembled from
   fragments cannot be translated correctly.
3. **Counted nouns use `trans_choice(':count thing|:count things', $n, ['count' => $n])`.**
   Never `Str::plural()` — pt_BR plurals are not a bare "+s" (*item → itens*,
   *imagem → imagens*, *ordem de compra → **ordens** de compra*).
4. **Never print a stored enum.** No `ucfirst($model->status)`. Put a `getStatusLabel()` on the
   model, with a `static ...Label(?string $value)` beside it so filter values and history rows
   can be labelled without an instance. Mind grammatical gender: the shared status words are
   masculine, so a feminine noun (*despesa*) needs its own keys — see `Expense::getStatusLabel()`.
5. **Validation comes from the shared map.** `lang/pt_BR/validation.php` and
   `lang/en/validation.php` already carry the messages and the field names. Declare a
   `validationAttributes()` entry only when a name differs from that map, and use the method
   form — a `protected $validationAttributes` property cannot call `__()`.
6. **Check the glossary before inventing a term.** `pt_BR.json` already fixes the vocabulary:
   Job Site → *Local*, Project → *Projeto*, Purchase Orders → *Ordens de Compra*,
   Subcontractor → *Subempreiteiro*. Consistency across screens beats a better word on one.
7. **The things people forget**, all of which shipped English to real users: PDF templates,
   CSV exports, e-mail subjects and bodies, `abort()` messages, and empty-value fallbacks
   (`?? 'Not provided'`). None of these show up in a walk-through of the screens.

Two traps worth knowing before you trust a search:

- **A key-diff cannot see `__($variable)`.** Labels pulled from `config/permissions.php`, the
  nav config, enum `label()` methods or database columns look translated and are not. Check
  those sources directly.
- **Proper nouns stay as they are** — `PIX`, `PDF`, `SKU`, `CEP`, card brands. Wrapping them
  adds noise without adding meaning.

## Every Module Ends With a Review Phase

**No module is finished when its features are.** Every module gets one extra, explicit
final phase — **Review and Improvements** — planned from the start and never skipped:

1. **Code review of the whole module**, not just the last change: correctness, the guards,
   the money maths, N+1s, and anything keyed in by hand that the server should compute.
2. **Walk the real screens** in both themes, both locales and on a phone: empty states,
   partial states, error states, long names, many rows.
3. **Close the gap between what the screens say and what the code does** — wording that
   promises something the code does not enforce is a bug.
4. **Sweep the notations** collected while building (see `docs/permissions-notes.md` and
   the module's own review backlog in `docs/review-and-improvements.md`) and either fix
   them, schedule them, or record the decision not to.
5. **Docs and pt_BR** brought level with what was actually built.

Items noticed mid-build go into the module's review backlog rather than derailing the
feature in hand — but the backlog is worked, not archived.

## Critical Rules
1. **PRODUCTION CODE ONLY** - Treat all code as production-ready
2. **NO FRESH MIGRATIONS** - NEVER use `migrate:fresh` or `migrate:refresh`. Only use `php artisan migrate` for incremental changes
3. **SIMPLEST SOLUTION FIRST** - Always choose the simplest, most maintainable approach
4. **CHECK EXISTING CODE** - Before creating new files/components, check if similar functionality already exists
5. **CLEAN UP** - Remove all test code, debug statements, and commented code before finalizing
6. **ASK WHEN UNCERTAIN** - If unsure about implementation details, ask for clarification instead of guessing
7. **DO NOT CREATE ALL FILES AT THE SAME TIME** when I request to create a CRUD, we are going to to one page at a time and only move to the next one after properly tested.

## Code Organization

### Livewire Components Structure
Organize components in logical folders by feature:
```
app/Livewire/
├── Company/
│   ├── CompanyIndex.php       # List view
│   ├── CompanyCreate.php      # Create form
│   ├── CompanyEdit.php        # Edit form
│   ├── CompanyShow.php        # Detail view
│   └── CompanyDelete.php      # Delete confirmation
├── Shared/
│   ├── SearchBar.php          # Reusable search
│   ├── Notification.php       # Reusable alerts
│   └── Modal.php              # Reusable modal
```

Corresponding views:
```
resources/views/livewire/
├── company/
│   ├── company-index.blade.php
│   ├── company-create.blade.php
│   ├── company-edit.blade.php
│   ├── company-show.blade.php
│   └── company-delete.blade.php
├── shared/
│   ├── search-bar.blade.php
│   ├── notification.blade.php
│   └── modal.blade.php
```

## Livewire Component Guidelines

### Full-Page Components (Main Features)
- Use for complete pages/routes (dashboards, CRUD operations, reports)
- Always include layout: `->layout('layouts.app')`
- Route directly in web.php: `Route::get('/companies', CompanyIndex::class)`

### Inline Components (Reusable Elements)
- Use for reusable UI elements (search bars, modals, filters)
- Embed in Blade templates: `<livewire:shared.search-bar />`
- Keep them focused on single responsibility

## Coding Standards

### PHP/Laravel
- Follow PSR-12 coding standards
- Use Laravel's built-in conventions and helpers
- Implement proper model relationships
- Use Laravel's validation rules
- Apply proper type hints and return types

### Naming Conventions
- **Livewire Components**: PascalCase (e.g., `CompanyCreate.php`)
- **View files**: kebab-case (e.g., `company-create.blade.php`)
- **Routes**: kebab-case (e.g., `/company-management`)
- **Database**: snake_case for tables and columns
- **Models**: Singular PascalCase (e.g., `Company`)

### Database Operations
- Create migration files with descriptive names
- Use `php artisan migrate` only (NEVER fresh or refresh)
- Include proper indexes in migrations
- Always add foreign key constraints where applicable
- Use database transactions for complex operations

## Implementation Approach

### Before Starting Any Task
1. Review existing code for similar functionality
2. Check current database structure
3. Identify reusable components
4. Plan the simplest approach

### When Creating Features
1. Start with database migration (if needed)
2. Create/update models with relationships
3. Build Livewire component logic
4. Create Blade views with Tailwind CSS
5. Add Alpine.js interactions where needed
6. Implement validation and error handling
7. for buttons use the components already setup and use the welcome blade to get the layouts for columns and titles.
8. Always save the documentation inside de docs folder unless you are told different.

### Error Handling
- For simple CRUD: Use Laravel/Livewire's default error handling
- For complex operations: Ask about specific error handling requirements
- Always validate user input
- Use database transactions for multi-step operations

## Alpine.js Integration
- Use for client-side interactions that don't need server trips
- Combine with Livewire using `@entangle` when needed
- Keep Alpine components simple and focused
- Use `x-data`, `x-show`, `x-if` for UI state management

## UI Components (ALWAYS USE THESE)

### Buttons
Always use the `x-ui.button` component for buttons:
```blade
<x-ui.button variant="primary" href="{{ route('items.create') }}" icon="plus">Add Item</x-ui.button>
<x-ui.button variant="secondary" href="{{ route('items.index') }}" icon="arrow-left">Back</x-ui.button>
<x-ui.button type="submit" variant="primary" icon="save">Save</x-ui.button>
```
- Variants: `primary`, `secondary`, `success`, `warning`, `danger`, `ghost`, `outline`
- Sizes: `sm`, `md`, `lg`, `xl`
- Icons: `plus`, `arrow-left`, `save`, `edit`, `eye`, `x`, etc.

### View/Edit Buttons in Index Tables
Always use `x-ui.view-edit-buttons` for action columns in tables:
```blade
<x-ui.view-edit-buttons
    :viewRoute="route('items.show', $item->id)"
    :editRoute="route('items.edit', $item->id)" />
```
- Use `:viewRoute` for view button (optional)
- Use `:editRoute` for edit button (optional)
- Can also use `:viewAction` and `:editAction` for wire:click actions

### Delete Buttons with Confirmation
Use `wire:confirm` for delete confirmations (not custom modals):
```blade
<x-ui.button
    variant="danger"
    size="sm"
    wire:click="deleteItem({{ $item->id }})"
    wire:confirm="Are you sure you want to delete this item?"
    icon="trash">
    Delete
</x-ui.button>
```

### Address Format (Country-based)
Use `config('app.country')` to determine address format:
- **US**: Street, Address Line 2, City, State, ZIP Code
- **BR**: Street, Complement, Neighborhood (Bairro), City, State (UF), CEP

Conditionally show fields based on country:
```blade
@if(config('app.country') === 'BR')
    <!-- Show Neighborhood field -->
@endif
```

## Tailwind CSS Guidelines
- Use utility classes directly in Blade files
- Avoid creating custom CSS unless absolutely necessary
- Maintain consistent spacing and sizing
- Follow mobile-first responsive design
- Use Tailwind's color palette consistently

## File Handling
- Store uploads in `storage/app/public`
- When user request some files and images should be private
- Always validate file types and sizes
- Use Laravel's Storage facade
- Implement proper file cleanup when records are deleted

### Every upload is drag-and-drop — no bare `<input type="file">` anywhere

A file field is a drop zone with a click-to-choose fallback, never a naked browser
file input. People drag a ficha técnica out of a folder; making them hunt through a
file dialog for it is the bare minimum, and the bare minimum is not what this
product ships. Two components cover every case — use one of them, never roll a third:

| Component | Use it when | How it sends |
|---|---|---|
| `<x-ui.file-drop wire:model="newUploads">` | The record may not exist yet — a create/edit form that holds its files until save. | Livewire `wire:model`; the form stores them in `save()`. |
| `<x-ui.file-uploader :targetType="..." :targetId="...">` | The record already exists — attach now, to a task, a note, a document. | Straight to storage with presigned URLs; the bytes never pass through PHP, so gigabyte files work. |

`file-drop` takes the queue list as its slot, so every screen shows what is waiting,
its size, and a trash icon-button for each — **whose method must not be called
`removeUpload()`**: that name belongs to Livewire's own `$wire` API, so the click is
intercepted in the browser and dies server-side with `Property [$0] not found`. Call it
`discardUpload()`. Both zones are dark-mode, translated, and show real
upload progress. `resources/views/components/ui/README.md` documents the props;
`ApprovalForm` + `approval-form.blade.php` are the reference implementation, including
the `updatedNewUploads()` hook that makes a **second drop add to the queue instead of
replacing the first** — without it a user loses a batch with nothing on screen to say so.

**The old plain inputs are being replaced module by module as each module is reviewed**
— the remaining ones are listed in `docs/review-and-improvements.md` (A2). New screens
have no excuse: use the component from the first commit.

## Performance Considerations
- Use eager loading to prevent N+1 queries
- Implement pagination for large datasets
- Use Livewire's lazy loading for heavy components
- Cache expensive queries when appropriate
- Optimize images and assets

## Security
- Always use Laravel's built-in authentication
- Implement proper authorization with policies/gates
- Sanitize all user inputs
- Use CSRF protection
- Never expose sensitive data in Livewire properties

## Questions to Ask When Unclear
1. "What should happen when [edge case]?"
2. "Do you need [specific validation rules]?"
3. "Should this include [audit trails/soft deletes/versioning]?"
4. "What level of user permissions are needed?"
5. "Are there any specific business rules for this feature?"

## Remember
- NO package installations without explicit permission
- NO fresh migrations ever
- Delete ALL test/debug code
- Check existing code FIRST
- Ask questions instead of making assumptions
- Keep solutions SIMPLE and MAINTAINABLE
- Pay attention to the requests to minimize errors.

## API
- Google geocoding api.
- Google Places API.
- Visual Crossing for weather data.
