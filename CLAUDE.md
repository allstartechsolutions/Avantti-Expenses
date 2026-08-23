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
(`docs/permissions-module.md`) is complete and deployed: 30 areas, 147 abilities, no role
checks left anywhere in the application. Anything built from now on joins it **as it is
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
   the same change. Then flip `swept => true`.

Two rules that hold everywhere and were each learned the hard way:

- **Never act on an id that came from the browser without checking which project it belongs
  to.** `findOrFail($id)` proves the record exists, not that this person may touch it.
- **Reproduce first, then make it revocable.** A change that quietly widens or narrows who
  can do something is a bug even when the new behaviour seems more sensible. Say what moved.

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
