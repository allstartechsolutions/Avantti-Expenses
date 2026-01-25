# Header Search

## Overview

The header search provides a quick way to find and navigate to projects from any page in the application. The search is optimized for performance to handle thousands of projects without slowing down page loads.

## Key Features

- **Debounced Search**: Only queries the database after 300ms of no typing
- **Minimum Characters**: Requires at least 2 characters before searching
- **Limited Results**: Returns maximum of 8 projects to keep the dropdown manageable
- **Lazy Loading**: No database queries on page load - only when user actively searches
- **Indexed Column**: The `project_name` column is indexed for fast searches

## How It Works

1. User types in the search field
2. After 2 characters and 300ms pause, a Livewire request is made
3. Database is queried using `LIKE %search%` on `project_name`
4. Results show project name and address
5. Clicking a result navigates to the project detail page

## Files

### Livewire Component

**Location**: `app/Livewire/Shared/HeaderSearch.php`

```php
namespace App\Livewire\Shared;

class HeaderSearch extends Component
{
    public string $search = '';
    public bool $showResults = false;

    // Computed property - only queries when accessed
    public function getResultsProperty()
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        return Project::where('project_name', 'like', '%' . $this->search . '%')
            ->select('id', 'project_name', 'street', 'city', 'state')
            ->orderBy('project_name')
            ->limit(8)
            ->get();
    }
}
```

### View

**Location**: `resources/views/livewire/shared/header-search.blade.php`

Key features:
- Uses `wire:model.live.debounce.300ms` for debounced input
- Alpine.js `@click.away` to close dropdown when clicking outside
- Clear button (X) to reset search
- Smooth transitions for dropdown appearance

### Header Integration

**Location**: `resources/views/components/layouts/inc/header.blade.php`

```blade
<!-- Search Projects -->
<livewire:shared.header-search />
```

## Performance Considerations

### Why This Approach is Efficient

1. **No Preloading**: Projects are never loaded until user searches
2. **Debounce**: Prevents excessive queries while typing
3. **Minimum Characters**: Avoids broad queries that return too many results
4. **Result Limit**: Only 8 results are fetched, regardless of total matches
5. **Database Index**: The `project_name` column has an index for fast lookups

### Database Index

The index was created in the projects migration:

```php
// database/migrations/2025_12_26_152439_create_projects_table.php
$table->index('project_name');
```

## Search Results Display

Each result shows:
- **Project Name**: Bold, primary text
- **Address**: Street, City, State in smaller secondary text

Example result:
```
Johnson Residence
123 Main St, Miami, FL
```

## Extending the Search

### Adding More Searchable Fields

To search by additional fields (e.g., client name, city):

```php
// In HeaderSearch.php getResultsProperty()
return Project::where('project_name', 'like', '%' . $this->search . '%')
    ->orWhere('city', 'like', '%' . $this->search . '%')
    ->orWhereHas('client', function ($query) {
        $query->where('name', 'like', '%' . $this->search . '%');
    })
    ->select('id', 'project_name', 'street', 'city', 'state')
    ->orderBy('project_name')
    ->limit(8)
    ->get();
```

### Changing Result Limit

Modify the `limit()` value in `getResultsProperty()`:

```php
->limit(10)  // Show 10 results instead of 8
```

### Adjusting Debounce Time

In the view, modify the debounce value:

```blade
wire:model.live.debounce.500ms="search"  <!-- 500ms instead of 300ms -->
```

## Usage

1. Click on the search field in the header
2. Type at least 2 characters of a project name
3. Wait briefly for results to appear
4. Click on a project to navigate to its detail page
5. Click the X button or click outside to close the dropdown
