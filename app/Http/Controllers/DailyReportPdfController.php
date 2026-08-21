<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DailyReport;
use App\Services\PermissionResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DailyReportPdfController extends Controller
{
    /**
     * P22 (docs/review-and-improvements.md): this was behind `auth` and
     * nothing else, so any signed-in person could fetch any project's daily
     * report by changing the number in the URL. The report is answered by the
     * project or job site it belongs to.
     */
    private function authorizeReport(DailyReport $dailyReport): void
    {
        abort_unless(
            app(PermissionResolver::class)->allows(auth()->user(), 'daily-reports.view', $dailyReport),
            403,
            __('You do not have permission to do that.'),
        );
    }

    /**
     * Generate and download the daily report PDF.
     */
    public function download(DailyReport $dailyReport)
    {
        $this->authorizeReport($dailyReport);

        // Load all necessary relationships
        $dailyReport->load([
            'project',
            'jobSite',
            'preparedBy',
            'weather',
            'weatherObservations',
            'manpowerLogs.images',
            'tasks.images',
        ]);

        // Get company info (first company or null)
        $company = Company::first();

        $pdf = Pdf::loadView('pdf.daily-report', [
            'dailyReport' => $dailyReport,
            'company' => $company,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('letter', 'portrait');

        // Generate filename - use job site name if available, otherwise project name
        $locationName = $dailyReport->jobSite?->job_site_name ?? $dailyReport->project?->project_name ?? 'report';
        $filename = sprintf(
            'daily-report-%s-%s.pdf',
            $locationName,
            $dailyReport->report_date->format('Y-m-d')
        );

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $filename);

        return $pdf->download($filename);
    }

    /**
     * Stream the daily report PDF (view in browser).
     */
    public function stream(DailyReport $dailyReport)
    {
        $this->authorizeReport($dailyReport);

        // Load all necessary relationships
        $dailyReport->load([
            'project',
            'jobSite',
            'preparedBy',
            'weather',
            'weatherObservations',
            'manpowerLogs.images',
            'tasks.images',
        ]);

        // Get company info (first company or null)
        $company = Company::first();

        $pdf = Pdf::loadView('pdf.daily-report', [
            'dailyReport' => $dailyReport,
            'company' => $company,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('daily-report.pdf');
    }
}
