# Manpower Log System

## Overview

The Manpower Log is a feature within Daily Reports that allows tracking of workers, contractors, and companies working on job sites. Each entry captures who worked, what they did, how long they worked, and can include supporting images.

## Database Schema

### daily_report_manpower Table

Stores manpower/labor entries for each daily report.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `daily_report_id` | foreignId | FK to daily_reports (cascade delete) |
| `contact_company` | string | Name of worker, contractor, or company |
| `workers` | unsignedInteger | Number of workers (default: 1) |
| `works` | text | Description of work performed |
| `hours` | decimal(5,2) | Number of hours worked |
| `comments` | text | Additional comments (nullable) |
| `order` | integer | Display order (default: 0) |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record update time |

### Images

Manpower log entries can have images attached using the existing polymorphic `daily_report_images` table:

| Column | Type | Description |
|--------|------|-------------|
| `imageable_type` | string | `App\Models\DailyReportManpower` |
| `imageable_id` | bigint | FK to manpower log entry |
| `file_path` | string | Storage path of the image |
| `file_name` | string | Original filename |
| `file_size` | integer | File size in bytes |
| `uploaded_by` | foreignId | User who uploaded the image |

## Model

### DailyReportManpower

**Location**: `app/Models/DailyReportManpower.php`

**Relationships**:
- `dailyReport()`: BelongsTo DailyReport
- `images()`: MorphMany DailyReportImage

**Fillable Fields**:
- `daily_report_id`
- `contact_company`
- `workers`
- `works`
- `hours`
- `comments`
- `order`

**Scopes**:
- `ordered()`: Orders entries by the `order` field

### DailyReport (Updated)

**New Relationship**:
- `manpowerLogs()`: HasMany DailyReportManpower (ordered)

## UI Components

### Manpower Log Section

Located in the Daily Report form, between "Observed Weather Conditions" and "Tasks" sections.

**Features**:
- List view showing all manpower entries with:
  - Entry number badge
  - Contact/Company name
  - Hours worked
  - Works description
  - Comments (if any)
  - Image thumbnails with remove option
  - Edit and Remove buttons
- Empty state with call-to-action button
- Add Entry button in section header

### Manpower Log Modal

Form fields for adding/editing entries:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| Contact/Company | text input | Yes | Worker, contractor, or company name |
| # of Workers | number input | Yes | Number of workers (min: 1) |
| Hours | number input | Yes | Hours worked (0-24, step 0.5) |
| Works Performed | textarea | Yes | Description of work done |
| Comments | textarea | No | Additional notes |
| Images | file upload | No | Multiple images supported |

**Image Features**:
- Drag and drop support
- Multiple file upload
- Preview of existing images
- Preview of new uploads (green border)
- Remove individual images
- 10MB max per image
- Supported formats: PNG, JPG, GIF

## Usage

### Adding a Manpower Entry

1. Navigate to create/edit a daily report
2. In the "Manpower Log" section, click "Add Entry"
3. Fill in the required fields:
   - Contact/Company: Who performed the work
   - Hours: Time spent working
   - Works Performed: What was done
4. Optionally add comments and images
5. Click "Add Entry" to save
6. Save the report to persist all entries

### Editing a Manpower Entry

1. Find the entry in the Manpower Log section
2. Click the "Edit" button
3. Modify the fields as needed
4. Add or remove images
5. Click "Update Entry"
6. Save the report to persist changes

### Removing a Manpower Entry

1. Find the entry in the Manpower Log section
2. Click the "Remove" button
3. Confirm the removal
4. Save the report to persist the deletion

## Files Created/Modified

### New Files
- `database/migrations/2026_01_20_123813_create_daily_report_manpower_table.php`
- `database/migrations/2026_01_20_124724_add_workers_to_daily_report_manpower_table.php`
- `app/Models/DailyReportManpower.php`
- `docs/manpower-log.md`

### Modified Files
- `app/Models/DailyReport.php` - Added `manpowerLogs()` relationship
- `app/Livewire/DailyReport/DailyReportForm.php` - Added manpower log handling
- `resources/views/livewire/daily-report/daily-report-form.blade.php` - Added UI section and modal

## Code Examples

### Accessing Manpower Logs

```php
// Get all manpower logs for a daily report
$dailyReport = DailyReport::with('manpowerLogs.images')->find($id);

foreach ($dailyReport->manpowerLogs as $log) {
    echo $log->contact_company;
    echo $log->workers . ' workers';
    echo $log->works;
    echo $log->hours . ' hours';

    // Access images
    foreach ($log->images as $image) {
        echo $image->file_path;
    }
}
```

### Creating a Manpower Entry

```php
$dailyReport->manpowerLogs()->create([
    'contact_company' => 'ABC Electrical',
    'workers' => 3,
    'works' => 'Installed electrical panels in Building A',
    'hours' => 8.5,
    'comments' => 'Completed ahead of schedule',
    'order' => 0,
]);
```

### Calculating Total Hours

```php
$totalHours = $dailyReport->manpowerLogs->sum('hours');
```

### Calculating Total Workers

```php
$totalWorkers = $dailyReport->manpowerLogs->sum('workers');
```

### Calculating Total Man-Hours

```php
// Total man-hours = sum of (workers * hours) for each entry
$totalManHours = $dailyReport->manpowerLogs->sum(function ($log) {
    return $log->workers * $log->hours;
});
```

## Future Considerations

- Add worker/contractor management (link to contacts table)
- Add labor cost tracking (hourly rates)
- Generate manpower summary reports
- Export to PDF with images
- Add time-in/time-out tracking
- Track equipment usage per manpower entry
