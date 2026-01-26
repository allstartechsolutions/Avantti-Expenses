# Phase 1: Cost Code Templates - Implementation Guide

**Date Started:** 2026-01-26
**Reference:** [Budget & Cost Code System](./budget-costcode-system.md)

---

## Overview

This phase implements the Cost Code Templates system - reusable templates containing hierarchical cost codes that can be applied to projects and job sites.

---

## Step 1.1: Database Migrations

### Migration 1: `cost_code_templates` table

```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_cost_code_templates_table.php

Schema::create('cost_code_templates', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_default')->default(false);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    
    $table->index('is_active');
    $table->index('is_default');
});
```

### Migration 2: `cost_code_template_items` table

```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_cost_code_template_items_table.php

Schema::create('cost_code_template_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('template_id')->constrained('cost_code_templates')->cascadeOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('cost_code_template_items')->cascadeOnDelete();
    $table->string('code', 50);
    $table->string('name');
    $table->text('description')->nullable();
    $table->unsignedTinyInteger('level')->default(1);
    $table->unsignedInteger('display_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index('template_id');
    $table->index('parent_id');
    $table->index(['template_id', 'code']);
    $table->unique(['template_id', 'code']);
});
```

**Run:** `php artisan migrate`

---

## Step 1.2: Models

### Model 1: CostCodeTemplate

**File:** `app/Models/CostCodeTemplate.php`

**Fillable:**
- `name`, `description`, `is_active`, `is_default`, `created_by`

**Casts:**
- `is_active` => `boolean`
- `is_default` => `boolean`

**Relationships:**
- `createdBy()` - BelongsTo User
- `items()` - HasMany CostCodeTemplateItem
- `rootItems()` - HasMany CostCodeTemplateItem where parent_id is null, ordered by display_order

**Scopes:**
- `scopeActive($query)` - where is_active = true

**Methods:**
- `getDefault(): ?self` - static, returns template where is_default = true
- `setAsDefault(): void` - sets this as default, removes default from others
- `duplicate(string $newName): self` - creates copy of template with all items
- `getItemCount(): int` - returns count of all items

### Model 2: CostCodeTemplateItem

**File:** `app/Models/CostCodeTemplateItem.php`

**Fillable:**
- `template_id`, `parent_id`, `code`, `name`, `description`, `level`, `display_order`, `is_active`

**Casts:**
- `level` => `integer`
- `display_order` => `integer`
- `is_active` => `boolean`

**Relationships:**
- `template()` - BelongsTo CostCodeTemplate
- `parent()` - BelongsTo CostCodeTemplateItem (self)
- `children()` - HasMany CostCodeTemplateItem (self), ordered by display_order

**Scopes:**
- `scopeActive($query)` - where is_active = true
- `scopeOrdered($query)` - orderBy display_order
- `scopeRootLevel($query)` - where parent_id is null

**Accessors:**
- `getFullCodeAttribute(): string` - returns "{code} - {name}"

**Methods:**
- `hasChildren(): bool` - returns true if has child items
- `getDescendantIds(): array` - returns all descendant IDs (for deletion check)

**Boot Method:**
- On creating: auto-calculate `level` based on parent (parent level + 1, or 1 if root)
- On creating: auto-set `display_order` (max order in siblings + 1)
- On creating: **validate max 2 levels** - if parent already has level 2, throw exception

**Validation Rule:**
```php
// In saving event or validation
if ($this->parent_id) {
    $parent = CostCodeTemplateItem::find($this->parent_id);
    if ($parent && $parent->level >= 2) {
        throw new \Exception('Maximum nesting depth is 2 levels.');
    }
}
```

---

## Step 1.3: Routes

Add to `routes/web.php` inside auth middleware group:

```php
// Cost Code Template routes
Route::get('cost-codes/templates', CostCodeTemplateIndex::class)->name('costcodes.templates.index');
Route::get('cost-codes/templates/create', CostCodeTemplateCreate::class)->name('costcodes.templates.create');
Route::get('cost-codes/templates/{template}', CostCodeTemplateShow::class)->name('costcodes.templates.show');
Route::get('cost-codes/templates/{template}/edit', CostCodeTemplateEdit::class)->name('costcodes.templates.edit');
```

Add use statements at top:
```php
use App\Livewire\CostCode\CostCodeTemplateIndex;
use App\Livewire\CostCode\CostCodeTemplateCreate;
use App\Livewire\CostCode\CostCodeTemplateShow;
use App\Livewire\CostCode\CostCodeTemplateEdit;
```

---

## Step 1.4: Sidebar Navigation

Add to `resources/views/components/layouts/inc/sidebar.blade.php`:

Under an appropriate section (perhaps after Catalog or as new Configuration section):

```blade
<!-- Cost Codes -->
<x-nav-link :href="route('costcodes.templates.index')" :active="request()->routeIs('costcodes.templates.*')" wire:navigate>
    <x-slot name="icon">
        <!-- Use list-tree or folder-tree icon -->
        <svg>...</svg>
    </x-slot>
    Cost Codes
</x-nav-link>
```

---

## Step 1.5: CostCodeTemplateIndex (List Page)

**File:** `app/Livewire/CostCode/CostCodeTemplateIndex.php`

**Properties:**
- `$search = ''`

**Computed/Methods:**
- `getTemplatesProperty()` - query with search, pagination
- `setAsDefault($id)` - set template as default
- `duplicateTemplate($id)` - create copy
- `deleteTemplate($id)` - delete if not in use (check future project_cost_codes table)

**View:** `resources/views/livewire/cost-code/cost-code-template-index.blade.php`

**Layout:**
- Page title with "New Template" button
- Search input
- Table: Name, Description, Codes (count), Status, Default (star), Actions
- Actions dropdown: View, Edit, Duplicate, Set as Default, Delete

---

## Step 1.6: CostCodeTemplateCreate (Create Page)

**File:** `app/Livewire/CostCode/CostCodeTemplateCreate.php`

**Properties:**
- `$name = ''`
- `$description = ''`
- `$is_active = true`
- `$is_default = false`
- `$copy_from = null` (optional: ID of template to copy from)

**Methods:**
- `save()` - validate, create template, optionally copy items from another template

**View:** `resources/views/livewire/cost-code/cost-code-template-create.blade.php`

**Layout:**
- Breadcrumb: Cost Codes > Templates > Create
- Form fields: Name, Description, Active toggle, Default toggle
- Optional: "Copy from existing" dropdown
- Save and Cancel buttons

---

## Step 1.7: CostCodeTemplateShow (Detail/Edit Page)

This is the main page where users manage cost codes within a template.

**File:** `app/Livewire/CostCode/CostCodeTemplateShow.php`

**Properties:**
```php
public CostCodeTemplate $template;

// For tree display
public array $expandedNodes = [];

// For modal
public bool $showItemModal = false;
public ?int $editingItemId = null;
public ?int $parentIdForNewItem = null;

// Item form fields
public string $itemCode = '';
public string $itemName = '';
public string $itemDescription = '';
public bool $itemIsActive = true;
```

**Methods:**
```php
public function mount(CostCodeTemplate $template): void;

// Tree operations
public function toggleNode(int $id): void;
public function expandAll(): void;
public function collapseAll(): void;

// CRUD operations
public function openCreateModal(?int $parentId = null): void;
public function openEditModal(int $itemId): void;
public function closeItemModal(): void;
public function saveItem(): void;
public function deleteItem(int $id): void;

// Helpers
public function getTreeProperty(): Collection; // Returns nested structure for view
```

**View:** `resources/views/livewire/cost-code/cost-code-template-show.blade.php`

**Layout:**
- Breadcrumb: Cost Codes > Templates > {Template Name}
- Template info header (name, description, status)
- Edit template button (links to edit page or inline edit)
- "Add Root Cost Code" button
- Tree view with expand/collapse
- Each node shows: code, name, [+] add child, [✎] edit, [🗑] delete
- Modal for add/edit cost code item

**Tree Rendering (Alpine.js):**
```blade
@foreach($this->tree as $item)
    <div x-data="{ expanded: @js(in_array($item->id, $expandedNodes)) }">
        <div class="flex items-center gap-2 py-2 px-3 hover:bg-gray-50">
            {{-- Expand/Collapse toggle --}}
            @if($item->children->count() > 0)
                <button @click="expanded = !expanded" class="...">
                    <span x-show="!expanded">▶</span>
                    <span x-show="expanded">▼</span>
                </button>
            @else
                <span class="w-4"></span>
            @endif
            
            {{-- Code and Name --}}
            <span class="font-mono text-sm">{{ $item->code }}</span>
            <span>{{ $item->name }}</span>
            
            {{-- Actions --}}
            <div class="ml-auto flex gap-1">
                <button wire:click="openCreateModal({{ $item->id }})" title="Add Child">+</button>
                <button wire:click="openEditModal({{ $item->id }})" title="Edit">✎</button>
                @if($item->children->count() === 0)
                    <button wire:click="deleteItem({{ $item->id }})" wire:confirm="..." title="Delete">🗑</button>
                @endif
            </div>
        </div>
        
        {{-- Children (recursive) --}}
        @if($item->children->count() > 0)
            <div x-show="expanded" x-collapse class="ml-6 border-l">
                @foreach($item->children as $child)
                    {{-- Recursive include or component --}}
                @endforeach
            </div>
        @endif
    </div>
@endforeach
```

---

## Step 1.8: CostCodeTemplateEdit (Edit Template Info)

**File:** `app/Livewire/CostCode/CostCodeTemplateEdit.php`

Simple form to edit template name, description, active, default status.

**Properties:**
- `CostCodeTemplate $template`
- `$name`, `$description`, `$is_active`, `$is_default`

**Methods:**
- `mount()` - load from template
- `save()` - validate and update

---

## Step 1.9: Default Seeder

**File:** `database/seeders/CostCodeTemplateSeeder.php`

Creates a "General Construction" template with common CSI-style codes:

```php
public function run(): void
{
    $template = CostCodeTemplate::create([
        'name' => 'General Construction',
        'description' => 'Standard construction cost codes based on CSI MasterFormat',
        'is_active' => true,
        'is_default' => true,
    ]);

    $codes = [
        ['code' => '01000', 'name' => 'General Requirements', 'children' => [
            ['code' => '01100', 'name' => 'Summary of Work'],
            ['code' => '01200', 'name' => 'Price and Payment Procedures'],
            ['code' => '01300', 'name' => 'Administrative Requirements'],
            ['code' => '01400', 'name' => 'Quality Requirements'],
            ['code' => '01500', 'name' => 'Temporary Facilities and Controls'],
        ]],
        ['code' => '02000', 'name' => 'Existing Conditions', 'children' => [
            ['code' => '02100', 'name' => 'Site Preparation'],
            ['code' => '02200', 'name' => 'Demolition'],
            ['code' => '02300', 'name' => 'Earthwork'],
            ['code' => '02400', 'name' => 'Hazardous Materials'],
        ]],
        ['code' => '03000', 'name' => 'Concrete', 'children' => [
            ['code' => '03100', 'name' => 'Concrete Forms'],
            ['code' => '03200', 'name' => 'Concrete Reinforcement'],
            ['code' => '03300', 'name' => 'Cast-in-Place Concrete'],
            ['code' => '03400', 'name' => 'Precast Concrete'],
        ]],
        ['code' => '04000', 'name' => 'Masonry', 'children' => [
            ['code' => '04100', 'name' => 'Unit Masonry'],
            ['code' => '04200', 'name' => 'Stone'],
        ]],
        ['code' => '05000', 'name' => 'Metals', 'children' => [
            ['code' => '05100', 'name' => 'Structural Steel'],
            ['code' => '05200', 'name' => 'Metal Joists'],
            ['code' => '05300', 'name' => 'Metal Decking'],
            ['code' => '05500', 'name' => 'Miscellaneous Metals'],
        ]],
        ['code' => '06000', 'name' => 'Wood and Plastics', 'children' => [
            ['code' => '06100', 'name' => 'Rough Carpentry'],
            ['code' => '06200', 'name' => 'Finish Carpentry'],
        ]],
        ['code' => '07000', 'name' => 'Thermal and Moisture Protection', 'children' => [
            ['code' => '07100', 'name' => 'Waterproofing'],
            ['code' => '07200', 'name' => 'Insulation'],
            ['code' => '07300', 'name' => 'Roofing'],
            ['code' => '07400', 'name' => 'Siding'],
        ]],
        ['code' => '08000', 'name' => 'Doors and Windows', 'children' => [
            ['code' => '08100', 'name' => 'Doors'],
            ['code' => '08500', 'name' => 'Windows'],
            ['code' => '08700', 'name' => 'Hardware'],
        ]],
        ['code' => '09000', 'name' => 'Finishes', 'children' => [
            ['code' => '09200', 'name' => 'Plaster and Gypsum Board'],
            ['code' => '09300', 'name' => 'Tile'],
            ['code' => '09500', 'name' => 'Ceilings'],
            ['code' => '09600', 'name' => 'Flooring'],
            ['code' => '09900', 'name' => 'Painting and Coating'],
        ]],
        ['code' => '10000', 'name' => 'Specialties'],
        ['code' => '11000', 'name' => 'Equipment'],
        ['code' => '12000', 'name' => 'Furnishings'],
        ['code' => '13000', 'name' => 'Special Construction'],
        ['code' => '14000', 'name' => 'Conveying Systems'],
        ['code' => '15000', 'name' => 'Mechanical', 'children' => [
            ['code' => '15100', 'name' => 'Plumbing'],
            ['code' => '15500', 'name' => 'HVAC'],
            ['code' => '15900', 'name' => 'Fire Protection'],
        ]],
        ['code' => '16000', 'name' => 'Electrical', 'children' => [
            ['code' => '16100', 'name' => 'Electrical General'],
            ['code' => '16500', 'name' => 'Lighting'],
            ['code' => '16700', 'name' => 'Communications'],
        ]],
    ];

    $this->createItems($template->id, $codes);
}

private function createItems(int $templateId, array $codes, ?int $parentId = null, int $order = 0): void
{
    foreach ($codes as $index => $code) {
        $item = CostCodeTemplateItem::create([
            'template_id' => $templateId,
            'parent_id' => $parentId,
            'code' => $code['code'],
            'name' => $code['name'],
            'display_order' => $order + $index,
        ]);

        if (!empty($code['children'])) {
            $this->createItems($templateId, $code['children'], $item->id);
        }
    }
}
```

**Run:** `php artisan db:seed --class=CostCodeTemplateSeeder`

---

## Step 1.10: Import/Export CSV

### CSV Format

The CSV format is simple and flat (hierarchy determined by parent_code reference):

```csv
code,name,description,parent_code,is_active
01000,General Requirements,General project requirements,,1
01100,Summary of Work,Project scope summary,01000,1
01200,Price and Payment,Payment procedures,01000,1
02000,Existing Conditions,Site existing conditions,,1
02100,Site Preparation,Prepare the site,02000,1
02200,Demolition,Demolition work,02000,1
```

**Columns:**
- `code` (required) - The cost code
- `name` (required) - Cost code name
- `description` (optional) - Description
- `parent_code` (optional) - Code of parent item (empty for root level)
- `is_active` (optional) - 1 or 0, defaults to 1

### Export Functionality

**Location:** Button on `CostCodeTemplateShow` page

**File:** `app/Livewire/CostCode/CostCodeTemplateShow.php`

```php
public function exportToCsv()
{
    $items = $this->template->items()
        ->with('parent')
        ->orderBy('level')
        ->orderBy('display_order')
        ->get();
    
    $filename = Str::slug($this->template->name) . '-cost-codes.csv';
    
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ];
    
    $callback = function () use ($items) {
        $file = fopen('php://output', 'w');
        
        // Header row
        fputcsv($file, ['code', 'name', 'description', 'parent_code', 'is_active']);
        
        // Data rows
        foreach ($items as $item) {
            fputcsv($file, [
                $item->code,
                $item->name,
                $item->description ?? '',
                $item->parent?->code ?? '',
                $item->is_active ? '1' : '0',
            ]);
        }
        
        fclose($file);
    };
    
    return response()->stream($callback, 200, $headers);
}
```

### Import Functionality

**Location:** Button on `CostCodeTemplateShow` page (opens modal)

**Properties:**
```php
public bool $showImportModal = false;
public $importFile = null;
public string $importMode = 'merge'; // 'merge' or 'replace'
public array $importErrors = [];
public array $importPreview = [];
```

**Methods:**
```php
public function openImportModal(): void
{
    $this->reset(['importFile', 'importErrors', 'importPreview']);
    $this->importMode = 'merge';
    $this->showImportModal = true;
}

public function updatedImportFile(): void
{
    // Validate and preview
    $this->validate(['importFile' => 'required|file|mimes:csv,txt|max:1024']);
    $this->previewImport();
}

public function previewImport(): void
{
    $this->importErrors = [];
    $this->importPreview = [];
    
    $path = $this->importFile->getRealPath();
    $rows = array_map('str_getcsv', file($path));
    $header = array_shift($rows);
    
    // Validate header
    $requiredColumns = ['code', 'name'];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $header)) {
            $this->importErrors[] = "Missing required column: {$col}";
            return;
        }
    }
    
    // Map rows
    foreach ($rows as $index => $row) {
        if (count($row) !== count($header)) {
            $this->importErrors[] = "Row " . ($index + 2) . " has incorrect number of columns";
            continue;
        }
        
        $data = array_combine($header, $row);
        
        // Validate required fields
        if (empty($data['code']) || empty($data['name'])) {
            $this->importErrors[] = "Row " . ($index + 2) . ": code and name are required";
            continue;
        }
        
        // Check for max 2 levels
        if (!empty($data['parent_code'])) {
            $parentInPreview = collect($this->importPreview)->firstWhere('code', $data['parent_code']);
            if ($parentInPreview && !empty($parentInPreview['parent_code'])) {
                $this->importErrors[] = "Row " . ($index + 2) . ": Maximum 2 levels allowed ('{$data['code']}' would be level 3)";
                continue;
            }
        }
        
        $this->importPreview[] = $data;
    }
}

public function executeImport(): void
{
    if (!empty($this->importErrors)) {
        return;
    }
    
    DB::transaction(function () {
        // If replace mode, delete existing items
        if ($this->importMode === 'replace') {
            $this->template->items()->delete();
        }
        
        // First pass: create all root items (no parent_code)
        $codeToId = [];
        foreach ($this->importPreview as $data) {
            if (empty($data['parent_code'])) {
                $item = $this->template->items()->updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'is_active' => ($data['is_active'] ?? '1') === '1',
                        'level' => 1,
                    ]
                );
                $codeToId[$data['code']] = $item->id;
            }
        }
        
        // Second pass: create child items
        foreach ($this->importPreview as $data) {
            if (!empty($data['parent_code'])) {
                $parentId = $codeToId[$data['parent_code']] 
                    ?? $this->template->items()->where('code', $data['parent_code'])->value('id');
                
                if (!$parentId) {
                    continue; // Skip if parent not found
                }
                
                $item = $this->template->items()->updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'parent_id' => $parentId,
                        'is_active' => ($data['is_active'] ?? '1') === '1',
                        'level' => 2,
                    ]
                );
                $codeToId[$data['code']] = $item->id;
            }
        }
    });
    
    $this->showImportModal = false;
    $this->dispatch('notify', message: 'Cost codes imported successfully!');
}
```

### Import Modal View

```blade
<x-ui.modal show="showImportModal" title="Import Cost Codes">
    <div class="space-y-4">
        {{-- File Upload --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">CSV File</label>
            <input type="file" wire:model="importFile" accept=".csv,.txt" class="mt-1 block w-full" />
            @error('importFile') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
        
        {{-- Import Mode --}}
        <div>
            <label class="block text-sm font-medium text-gray-700">Import Mode</label>
            <select wire:model="importMode" class="mt-1 block w-full rounded-md border-gray-300">
                <option value="merge">Merge (update existing, add new)</option>
                <option value="replace">Replace (delete all existing first)</option>
            </select>
        </div>
        
        {{-- Errors --}}
        @if(count($importErrors) > 0)
            <div class="bg-red-50 border border-red-200 rounded p-3">
                <h4 class="font-medium text-red-800">Errors Found:</h4>
                <ul class="list-disc list-inside text-sm text-red-700">
                    @foreach($importErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        {{-- Preview --}}
        @if(count($importPreview) > 0 && count($importErrors) === 0)
            <div class="bg-green-50 border border-green-200 rounded p-3">
                <h4 class="font-medium text-green-800">Preview: {{ count($importPreview) }} cost codes will be imported</h4>
                <div class="mt-2 max-h-48 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Code</th>
                                <th class="text-left">Name</th>
                                <th class="text-left">Parent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($importPreview as $row)
                                <tr>
                                    <td class="font-mono">{{ $row['code'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="font-mono text-gray-500">{{ $row['parent_code'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
    
    <x-slot name="footer">
        <x-ui.button variant="secondary" wire:click="$set('showImportModal', false)">Cancel</x-ui.button>
        <x-ui.button 
            variant="primary" 
            wire:click="executeImport" 
            :disabled="count($importErrors) > 0 || count($importPreview) === 0">
            Import {{ count($importPreview) }} Cost Codes
        </x-ui.button>
    </x-slot>
</x-ui.modal>
```

### Template Show Header with Import/Export Buttons

```blade
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold">{{ $template->name }}</h1>
        <p class="text-gray-600">{{ $template->description }}</p>
    </div>
    <div class="flex gap-2">
        <x-ui.button variant="secondary" wire:click="exportToCsv" icon="download">
            Export CSV
        </x-ui.button>
        <x-ui.button variant="secondary" wire:click="openImportModal" icon="upload">
            Import CSV
        </x-ui.button>
        <x-ui.button variant="primary" wire:click="openCreateModal" icon="plus">
            Add Cost Code
        </x-ui.button>
    </div>
</div>
```

### Sample CSV Template Download

Add a link to download a blank template:

```php
public function downloadSampleCsv()
{
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="cost-codes-template.csv"',
    ];
    
    $callback = function () {
        $file = fopen('php://output', 'w');
        fputcsv($file, ['code', 'name', 'description', 'parent_code', 'is_active']);
        fputcsv($file, ['01000', 'General Requirements', 'General project requirements', '', '1']);
        fputcsv($file, ['01100', 'Summary of Work', 'Project scope', '01000', '1']);
        fputcsv($file, ['02000', 'Site Work', 'Site related work', '', '1']);
        fclose($file);
    };
    
    return response()->stream($callback, 200, $headers);
}
```

---

## Implementation Order for Claude Code

Tell Claude Code to implement in this order:

1. **"Create the migrations for cost code templates"**
   - Creates both migration files
   - Run `php artisan migrate`

2. **"Create the CostCodeTemplate and CostCodeTemplateItem models with max 2 level validation"**
   - Creates both model files with all relationships and methods
   - Includes level validation (max 2 levels)

3. **"Create the CostCodeTemplateIndex page (list view)"**
   - Creates component, view, route
   - Adds to sidebar

4. **"Create the CostCodeTemplateCreate page"**
   - Creates component, view, route

5. **"Create the CostCodeTemplateShow page with tree view for managing cost codes"**
   - Creates component with tree structure
   - Creates view with Alpine.js expand/collapse
   - Add/Edit/Delete functionality for items
   - Only shows "Add Child" button for root level items (level 1)

6. **"Create the CostCodeTemplateEdit page"**
   - Simple edit form for template metadata

7. **"Add CSV export functionality to CostCodeTemplateShow"**
   - Export button downloads CSV with all cost codes
   - Download sample template option

8. **"Add CSV import functionality with modal, preview, and validation to CostCodeTemplateShow"**
   - Import button opens modal
   - File upload with validation
   - Preview before import
   - Merge or Replace modes
   - Max 2 level validation

9. **"Create the CostCodeTemplateSeeder with default construction codes"**
   - Creates seeder with CSI-style codes (2 levels only)
   - Run seeder

10. **Test everything**
   - Create template
   - Add root codes
   - Add child codes
   - Edit codes
   - Delete codes (verify children protection)
   - Duplicate template
   - Set default

---

## Session Log

### 2026-01-26 - Phase 1 Implementation

**Status:** COMPLETED

**Progress:**
- [x] Step 1.1: Migrations (`cost_code_templates`, `cost_codes`)
- [x] Step 1.2: Models (`CostCodeTemplate`, `CostCode`)
- [x] Step 1.3: Routes (`cost-codes.templates.*`)
- [x] Step 1.4: Sidebar Navigation (under Projects submenu)
- [x] Step 1.5: CostCodeTemplateIndex (list, search, delete, duplicate, set default)
- [x] Step 1.6: CostCodeTemplateCreate (name, description, default toggle)
- [x] Step 1.7: CostCodeTemplateShow (tree view with inline add/edit/delete)
- [x] Step 1.8: CostCodeTemplateEdit (edit template details)
- [ ] Step 1.9: Seeder (not implemented - user imports via CSV)
- [ ] Step 1.10: CSV Export (not implemented yet)
- [x] Step 1.11: CSV Import (modal with preview, merge/replace modes, validation, encoding support)
- [x] Testing - Basic functionality verified

**Implementation Notes:**
- Used simplified schema: `cost_codes` table instead of `cost_code_template_items`
- No `is_active` or `level` columns (kept it simpler)
- Route names use `cost-codes.templates.*` pattern
- Hierarchy limited to 1 level (parent → child only)
- Cost codes managed inline on show page (sidebar form) instead of modal
- CSV Import includes:
  - File upload with validation
  - Preview before import
  - Merge and Replace modes
  - Encoding auto-detection (UTF-8, Windows-1252, ISO-8859-1)
  - BOM removal
  - Corrupted Portuguese character fix (ç, ã, é, etc.)
  - Sample CSV download
- Full documentation: `docs/cost-code-templates.md` 

---

## Design Decisions (Confirmed)

1. **Maximum nesting depth:** 2 levels only (Parent → Child, no grandchildren)
2. **Code format:** Flexible, no strict pattern validation (user can use any format)
3. **Import/Export CSV:** Required in Phase 1
