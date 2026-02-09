# Subcontractors Module

This document describes the Subcontractors module implementation.

---

## Overview

The Subcontractors module allows managing subcontractor companies with contact information, addresses, and documents. It follows the same structure as the Clients module but includes:
- Full address support with Google Places autocomplete (like Projects/JobSites)
- Document management with expiration tracking for compliance documents (W9, Insurance, Licenses)

---

## Database Structure

### Table: `subcontractors`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint | No | Primary key |
| company_name | string | No | Subcontractor company name |
| website | string | Yes | Company website URL |
| contact_name | string | No | Primary contact person name |
| contact_email | string | No | Primary contact email address |
| title | string | Yes | Contact person's title/position |
| phone | string(20) | Yes | Contact phone number |
| street | string | Yes | Street address |
| address_2 | string | Yes | Suite, apt, unit, etc. |
| neighborhood | string | Yes | Neighborhood (Bairro) - Brazil only |
| city | string | Yes | City |
| state | string | Yes | State |
| postal_code | string(20) | Yes | Postal code / CEP |
| country | string(2) | No | Country code (default: 'US') |
| latitude | decimal(10,8) | Yes | GPS latitude from Google Places |
| longitude | decimal(11,8) | Yes | GPS longitude from Google Places |
| created_by | foreignId | No | User who created the record |
| created_at | timestamp | No | Creation timestamp |
| updated_at | timestamp | No | Last update timestamp |

### Table: `document_types`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint | No | Primary key |
| name | string | No | Document type name (e.g., "W9", "General Liability Insurance") |
| description | string | Yes | Description of the document type |
| requires_expiration | boolean | No | Whether this type requires an expiration date |
| sort_order | integer | No | Display order (default: 0) |
| created_at | timestamp | No | Creation timestamp |
| updated_at | timestamp | No | Last update timestamp |

### Table: `subcontractor_documents`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint | No | Primary key |
| subcontractor_id | foreignId | No | FK to subcontractors (cascade delete) |
| document_type_id | foreignId | No | FK to document_types (cascade delete) |
| file_path | string | No | Path to stored file (private storage) |
| file_name | string | No | Original filename |
| file_size | integer | Yes | File size in bytes |
| expiration_date | date | Yes | Document expiration date (required for some types) |
| notes | text | Yes | Optional notes about the document |
| uploaded_by | foreignId | No | FK to users (cascade delete) |
| created_at | timestamp | No | Creation timestamp |
| updated_at | timestamp | No | Last update timestamp |

### Indexes
- `subcontractors`: `company_name, contact_email` - Combined index for search
- `subcontractor_documents`: `subcontractor_id, document_type_id` - Composite index
- `subcontractor_documents`: `expiration_date` - For expiration queries

### Migration Files
- `database/migrations/2026_02_06_125335_create_subcontractors_table.php`
- `database/migrations/2026_02_06_141420_create_document_types_table.php`
- `database/migrations/2026_02_06_141420_create_subcontractor_documents_table.php`

---

## File Locations

### Models
| File | Description |
|------|-------------|
| `app/Models/Subcontractor.php` | Subcontractor model with relationships and accessors |
| `app/Models/DocumentType.php` | Document type model |
| `app/Models/SubcontractorDocument.php` | Document model with status tracking |

### Livewire Components
| File | Description |
|------|-------------|
| `app/Livewire/Subcontractor/SubcontractorIndex.php` | List view with search and pagination |
| `app/Livewire/Subcontractor/SubcontractorCreate.php` | Create form with address autocomplete |
| `app/Livewire/Subcontractor/SubcontractorEdit.php` | Edit form with address autocomplete |
| `app/Livewire/Subcontractor/SubcontractorShow.php` | Detail view with tabs (Overview, Documents) |

### Blade Views
| File | Description |
|------|-------------|
| `resources/views/livewire/subcontractor/subcontractor-index.blade.php` | Index page template |
| `resources/views/livewire/subcontractor/subcontractor-create.blade.php` | Create form template |
| `resources/views/livewire/subcontractor/subcontractor-edit.blade.php` | Edit form template |
| `resources/views/livewire/subcontractor/subcontractor-show.blade.php` | Show page with tabs |

### Seeders
| File | Description |
|------|-------------|
| `database/seeders/DocumentTypeSeeder.php` | Seeds default document types |

---

## Routes

All routes are protected by the `auth` middleware.

| Method | URI | Name | Component |
|--------|-----|------|-----------|
| GET | /subcontractors | subcontractors.index | SubcontractorIndex |
| GET | /subcontractors/create | subcontractors.create | SubcontractorCreate |
| GET | /subcontractors/{subcontractor} | subcontractors.show | SubcontractorShow |
| GET | /subcontractors/{subcontractor}/edit | subcontractors.edit | SubcontractorEdit |

---

## Document Types

Document types are seeded via `DocumentTypeSeeder`. Run with:
```bash
php artisan db:seed --class=DocumentTypeSeeder
```

### Default Document Types

| Name | Requires Expiration | Sort Order |
|------|---------------------|------------|
| W9 | No | 1 |
| General Liability Insurance | Yes | 2 |
| Workers Compensation Insurance | Yes | 3 |
| Certificate of Insurance (COI) | Yes | 4 |
| Business License | Yes | 5 |
| Contractor License | Yes | 6 |
| Auto Insurance | Yes | 7 |
| Other | No | 99 |

### Adding New Document Types

To add new document types, update the seeder and re-run:

```php
// In DocumentTypeSeeder.php
[
    'name' => 'New Document Type',
    'description' => 'Description here',
    'requires_expiration' => true, // or false
    'sort_order' => 10,
],
```

---

## Document Management

### Security Features

| Feature | Implementation |
|---------|----------------|
| **Private Storage** | Files stored in `storage/app/subcontractor-documents/` (not publicly accessible) |
| **Authentication Required** | Download route is inside `auth` middleware group |
| **Automatic File Cleanup** | Files are deleted from filesystem when document record is deleted |

### File Storage

Documents are stored using Laravel's local disk:
```php
$file->store('subcontractor-documents/' . $subcontractor->id, 'local');
```

This creates the structure:
```
storage/app/
└── subcontractor-documents/
    └── {subcontractor_id}/
        └── document_file.pdf
```

### File Deletion

When a document is deleted, the file is automatically removed from storage via the model's `booted()` method:

```php
protected static function booted(): void
{
    static::deleting(function (SubcontractorDocument $document) {
        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
    });
}
```

### Accepted File Types
- PDF (.pdf)
- Word Documents (.doc, .docx)
- Images (.jpg, .jpeg, .png)
- Maximum file size: 10MB

### Document Status

Documents have automatic status tracking based on expiration:

| Status | Condition | Color |
|--------|-----------|-------|
| Valid | No expiration or expiration > 30 days away | Green |
| Expiring Soon | Expiration within 30 days | Yellow |
| Expired | Expiration date has passed | Red |

Status is calculated via accessor in `SubcontractorDocument`:

```php
public function getStatusAttribute(): string
{
    if (!$this->documentType->requires_expiration || !$this->expiration_date) {
        return 'valid';
    }

    $now = Carbon::now();
    $expiration = $this->expiration_date;

    if ($expiration->isPast()) {
        return 'expired';
    }

    if ($expiration->diffInDays($now) <= 30) {
        return 'expiring_soon';
    }

    return 'valid';
}
```

---

## Model Relationships

### Subcontractor Model
```php
// User who created this subcontractor
public function createdBy(): BelongsTo

// All documents for this subcontractor
public function documents(): HasMany
```

### SubcontractorDocument Model
```php
// The subcontractor that owns this document
public function subcontractor(): BelongsTo

// The document type
public function documentType(): BelongsTo

// User who uploaded this document
public function uploadedBy(): BelongsTo
```

### DocumentType Model
```php
// All documents of this type
public function documents(): HasMany

// Scope to order by sort_order
public function scopeOrdered($query)
```

---

## SubcontractorDocument Accessors

| Accessor | Returns | Description |
|----------|---------|-------------|
| `status` | string | 'valid', 'expiring_soon', or 'expired' |
| `status_label` | string | Human-readable status: 'Valid', 'Expiring Soon', 'Expired' |
| `status_color` | string | Color for UI: 'green', 'yellow', 'red' |
| `formatted_file_size` | string | Human-readable file size (e.g., '2.5 MB') |

---

## Show Page Tabs

The subcontractor show page has two tabs:

### Overview Tab
- Company Information (name, website, avatar)
- Contact Person (name, title, email, phone)
- Address Information (full address with country-specific fields)
- Quick Actions sidebar (Edit, Email, Call)
- Subcontractor stats (ID, document count, dates, creator)

### Documents Tab
- Upload document form (collapsible)
- Documents table with:
  - File name and size
  - Document type
  - Expiration date
  - Status badge (Valid/Expiring Soon/Expired)
  - Upload date and uploader
  - Download and Delete actions
- Empty state when no documents

---

## Address Autocomplete

The Create and Edit forms use Google Places Autocomplete for address input.

### Requirements
- Google Maps API key set in `.env`:
  ```
  GOOGLE_MAPS_API_KEY=your_api_key_here
  ```

### How It Works
1. User starts typing in the Street Address field
2. Google Places shows address suggestions based on the configured country
3. When user selects an address, the following fields are auto-filled:
   - Street (street number + route)
   - City
   - State
   - Postal Code
   - Neighborhood (for Brazil)
   - Latitude
   - Longitude

### Country Configuration
The country is determined by `config('app.country')` which defaults to 'US'. For Brazil, set:
```php
// config/app.php
'country' => env('APP_COUNTRY', 'US'),
```

---

## Validation Rules

### Subcontractor Fields
| Field | Rules |
|-------|-------|
| company_name | required, string, max:255 |
| website | nullable, url, max:255 |
| contact_name | required, string, max:255 |
| contact_email | required, email, max:255 |
| title | nullable, string, max:255 |
| phone | nullable, string, max:20 |
| street | nullable, string, max:255 |
| address_2 | nullable, string, max:255 |
| neighborhood | nullable, string, max:255 |
| city | nullable, string, max:255 |
| state | nullable, string, max:255 |
| postal_code | nullable, string, max:20 |
| latitude | nullable, numeric |
| longitude | nullable, numeric |

### Document Upload Fields
| Field | Rules |
|-------|-------|
| document_type_id | required, exists:document_types,id |
| document_file | required, file, mimes:pdf,doc,docx,jpg,jpeg,png, max:10240 |
| expiration_date | required if type requires expiration, date, after:today |
| document_notes | nullable, string, max:500 |

---

## Navigation

The Subcontractors link is located in the left sidebar under the **Projects** submenu:

```
Projects (submenu)
├── All Projects
├── Subcontractors
├── Clients
└── Cost Codes
```

### Files Modified for Navigation
- `resources/views/components/layouts/inc/sidebar.blade.php` - Added menu item
- `resources/views/components/layouts/app.blade.php` - Added route to activeSubmenu

---

## UI Components Used

### Buttons
All buttons use the `x-ui.button` component:
```blade
<x-ui.button variant="primary" href="..." icon="plus">Add Subcontractor</x-ui.button>
<x-ui.button variant="secondary" href="..." icon="arrow-left">Back</x-ui.button>
<x-ui.button type="submit" variant="primary" icon="save">Save</x-ui.button>
```

### View/Edit Buttons
Index table uses `x-ui.view-edit-buttons`:
```blade
<x-ui.view-edit-buttons
    :viewRoute="route('subcontractors.show', $subcontractor->id)"
    :editRoute="route('subcontractors.edit', $subcontractor->id)" />
```

### Delete Confirmation
Document deletion uses `wire:confirm`:
```blade
<button wire:click="deleteDocument({{ $document->id }})"
        wire:confirm="Are you sure you want to delete this document?">
    Delete
</button>
```

---

## Features Summary

### Index Page
- Paginated list (10 items per page)
- Search by company name, contact name, email, or phone
- Displays: Company (with initials avatar), Contact, Email, Phone, Location
- View and Edit action buttons
- Empty state with "Add Subcontractor" button

### Create Page
- Three card sections:
  1. Company Information (company name, website)
  2. Contact Person (name, title, email, phone)
  3. Address Information (with Google Places autocomplete)
- Real-time validation
- Loading state on submit button

### Edit Page
- Same layout as Create page
- Pre-populated with existing data
- Address autocomplete works with existing address

### Show Page
- **Overview Tab:**
  - Company profile with avatar initials
  - Contact person details
  - Full address information
  - Quick actions sidebar (Edit, Send Email, Call)
  - Subcontractor stats (ID, document count, creation date, creator)
- **Documents Tab:**
  - Upload document with type selection
  - Expiration date (required for certain types)
  - Document list with status indicators
  - Download and delete actions
  - Private file storage with auth protection

---

## Differences from Clients Module

| Feature | Clients | Subcontractors |
|---------|---------|----------------|
| Address fields | Simple (street, city, state, postal_code) | Full (+ address_2, neighborhood, lat/lng, country) |
| Address autocomplete | No | Yes (Google Places) |
| Email field | `email` (in company section) | `contact_email` (in contact section) |
| Country support | No | Yes (US/BR formatting) |
| Geo coordinates | No | Yes (latitude, longitude) |
| Documents | No | Yes (with expiration tracking) |
| Show page tabs | No | Yes (Overview, Documents) |

---

## Future Considerations

### Link Subcontractors to Projects

If you need to link subcontractors to projects:

1. Create a pivot table migration:
   ```php
   Schema::create('project_subcontractor', function (Blueprint $table) {
       $table->id();
       $table->foreignId('project_id')->constrained()->onDelete('cascade');
       $table->foreignId('subcontractor_id')->constrained()->onDelete('cascade');
       $table->timestamps();
   });
   ```

2. Add relationships to models:
   ```php
   // In Project model
   public function subcontractors(): BelongsToMany
   {
       return $this->belongsToMany(Subcontractor::class);
   }

   // In Subcontractor model
   public function projects(): BelongsToMany
   {
       return $this->belongsToMany(Project::class);
   }
   ```

### User-Manageable Document Types

If you need to allow users to manage document types:

1. Create Livewire CRUD components for DocumentType
2. Add routes under `/settings/document-types`
3. Add navigation link in Company settings

### Document Expiration Notifications

To add email notifications for expiring documents:

1. Create a scheduled command to check expirations
2. Send notifications 30/14/7 days before expiration
3. Add to scheduler in `app/Console/Kernel.php`

---

## Created On
February 6, 2026

## Last Updated
February 6, 2026 - Added Documents feature with expiration tracking
