# Project Guidelines and Standards

## Tech Stack (STRICT - Do not add other technologies unless explicitly requested)
- Laravel 12+
- Livewire 3.7+
- Alpine.js (latest version)
- Tailwind CSS
- MySQL (as configured)

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
7. for buttons use the componets already setup and use the welcome blade to get the layouts for columns and titles.

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
