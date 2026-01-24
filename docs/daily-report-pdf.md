# Daily Report PDF Export

## Overview

The application supports exporting Daily Reports to PDF format for printing, sharing, and archival purposes. The PDF follows a professional layout similar to industry-standard construction daily logs.

## Package Used

**barryvdh/laravel-dompdf** (v3.x)
- Pure PHP PDF generation
- Uses Blade templates for layout
- No external binary dependencies

## PDF Structure

The generated PDF includes:

### Page 1: Report Data

1. **Header**
   - Company logo and info (left)
   - Project name, job site, and address (right)

2. **Report Title**
   - "Daily Log: [Day] [Date]"

3. **Status Box**
   - Shows who prepared the report and when

4. **Weather Report** (if available)
   - Temperature: Low, High, Avg
   - Precipitation: Since Midnight, 2 Days, 3 Days
   - Humidity: Low, Avg, High, Dew Point
   - Wind Speed: Avg, Max, Gust

5. **Daily Snapshot** (if available)
   - 6 time periods: 6AM, 9AM, 12PM, 3PM, 6PM, 9PM
   - Shows condition and temperature for each

6. **Observed Weather Conditions** (if any)
   - Table with: Time, Weather Delay, Sky, Temp, Precipitation, Wind, Notes

7. **Manpower Log** (if any)
   - Summary: Total Workers | Total Man Hours
   - Table with: Contact/Company, Workers, Hours, Man Hours
   - Comments row for each entry

8. **Tasks** (if any)
   - Numbered task cards with descriptions

9. **Signature Section**
   - By, Date, Copies To lines

### Page 2: Attachments (if any images exist)

1. **Manpower Log Attachments**
   - Images grouped by company/contact
   - 2 images per row

2. **Task Attachments**
   - Images grouped by task number
   - 2 images per row

3. **Signature Section**

### Footer (all pages)

- Company name (left)
- Print date/time (right)

## Routes

| Route | Method | Description |
|-------|--------|-------------|
| `daily-reports/{dailyReport}/pdf` | GET | Download PDF |
| `daily-reports/{dailyReport}/pdf/view` | GET | View PDF in browser |

### Named Routes

```php
route('dailyreports.pdf.download', $dailyReport)  // Download
route('dailyreports.pdf.view', $dailyReport)      // View in browser
```

## Controller

**Location**: `app/Http/Controllers/DailyReportPdfController.php`

### Methods

```php
// Download PDF
public function download(DailyReport $dailyReport)

// Stream PDF (view in browser)
public function stream(DailyReport $dailyReport)
```

## Blade Template

**Location**: `resources/views/pdf/daily-report.blade.php`

The template uses:
- DejaVu Sans font (built into DomPDF)
- HTML tables for layout (DomPDF has limited flexbox support)
- Inline styles (DomPDF doesn't support external CSS files well)
- Fixed header/footer using @page margins and `position: fixed`
- `page-break-inside: avoid` to keep sections together
- Base64-encoded images for reliable rendering

## UI Integration

PDF download buttons are available in:

1. **Job Site Show Page** - Daily Reports tab
   - Small PDF icon button next to View/Edit buttons

2. **Daily Report Form** (edit mode only)
   - "PDF" button in the header next to Save

## Usage

### Downloading a PDF

```php
// In a controller
return redirect()->route('dailyreports.pdf.download', $dailyReport);

// In Blade
<a href="{{ route('dailyreports.pdf.download', $dailyReport) }}">Download PDF</a>
```

### Generating PDF Programmatically

```php
use App\Models\DailyReport;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;

$dailyReport = DailyReport::with([
    'jobSite.project',
    'preparedBy',
    'weather',
    'weatherObservations',
    'manpowerLogs.images',
    'tasks.images',
])->find($id);

$company = Company::first();

$pdf = Pdf::loadView('pdf.daily-report', [
    'dailyReport' => $dailyReport,
    'company' => $company,
]);

// Download
return $pdf->download('daily-report.pdf');

// Stream (view in browser)
return $pdf->stream('daily-report.pdf');

// Save to file
$pdf->save(storage_path('app/reports/daily-report.pdf'));
```

## Customization

### Paper Size

Default is Letter (8.5" x 11"). To change:

```php
$pdf->setPaper('a4', 'portrait');  // A4 paper
$pdf->setPaper('legal', 'landscape');  // Legal landscape
```

### Company Logo

The PDF displays the company logo if:
1. A Company record exists in the database
2. The company has a logo stored in `storage/app/public/`

To add a company logo:
1. Go to Company Settings
2. Upload a logo image
3. The logo will appear in PDF headers

## Files Created/Modified

### New Files
- `app/Http/Controllers/DailyReportPdfController.php`
- `resources/views/pdf/daily-report.blade.php`
- `config/dompdf.php` - Published config (paper size: letter)
- `docs/daily-report-pdf.md`

### Modified Files
- `routes/web.php` - Added PDF routes
- `resources/views/livewire/job-site/job-site-show.blade.php` - Added PDF button
- `resources/views/livewire/daily-report/daily-report-form.blade.php` - Added PDF button
- `composer.json` - Added barryvdh/laravel-dompdf

## Troubleshooting

### Images Not Showing

The template uses base64-encoded images for reliable rendering:
```php
$imagePath = storage_path('app/' . $image->file_path);
if (file_exists($imagePath)) {
    $imageContent = file_get_contents($imagePath);
    $imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
    $mimeType = $imageType === 'png' ? 'image/png' : 'image/jpeg';
    $imageData = 'data:' . $mimeType . ';base64,' . base64_encode($imageContent);
}
```

If images don't show:
1. Verify the file exists at the storage path
2. Check file permissions (readable by web server)
3. Ensure file extension matches actual format (png, jpg, jpeg)

### Fonts Not Rendering

DomPDF only supports TTF fonts. The template uses DejaVu Sans (built-in).

For custom fonts, add them to:
```
storage/fonts/
```

### PDF Too Large

If PDFs are too large (many images):
1. Consider compressing images before storage
2. Limit image dimensions in the template
3. Use image optimization packages

### Timeout on Large Reports

For reports with many images:
```php
// Increase memory limit
ini_set('memory_limit', '256M');

// Or use chunked processing for very large reports
```

### Content Splitting Across Pages

The template uses CSS techniques to prevent content from breaking incorrectly:
```css
/* Prevent section breaks */
.no-break {
    page-break-inside: avoid;
}

/* Keep header with content */
.section-header {
    page-break-after: avoid;
}

/* Force new page for attachments */
.image-section {
    page-break-before: always;
}
```

Wrap sections with `<div class="no-break">` to keep them together.

## Future Considerations

- Email PDF directly from the application
- Batch PDF generation for multiple reports
- PDF preview modal before download
- Custom PDF templates per company
- Include weather icons in Daily Snapshot
- Add page numbers to footer
