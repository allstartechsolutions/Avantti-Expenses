<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractChangeOrder;
use App\Models\DailyReportImage;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use App\Models\QuotationVendor;
use App\Models\SubcontractorDocument;
use App\Services\PermissionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the files stored against records by the older modules — expense
 * receipts, purchase order receipts, contract files, change order files,
 * daily report images, subcontractor documents and polymorphic attachments.
 *
 * The path arrives in the query string, so it is treated as hostile: it must
 * be relative, free of traversal segments, and live under one of the
 * directories this application actually writes to. Without those checks a
 * signed-in user could walk out of the storage root and read .env or the
 * database file.
 *
 * New work does not use this controller. The document repository addresses
 * files by record id (see DocumentFileController) and never exposes a path.
 */
class FileController extends Controller
{
    /**
     * Directories the application stores files in on the private disk.
     * A path outside every one of these is refused.
     */
    private const ALLOWED_DIRECTORIES = [
        'expenses',
        'income',
        'purchase-orders',
        'requisitions',
        'quotations',
        'contracts',
        'contract-change-orders',
        'change_orders',
        'daily_reports',
        'temp_daily_reports',
        'subcontractor-documents',
        'company-logos',
        'livewire-tmp',
    ];

    public function download(Request $request): StreamedResponse
    {
        $path = $this->resolvePath($request);
        $this->authorizeFile($request, $path);

        return Storage::download($path, basename($path), [
            'Content-Type' => Storage::mimeType($path),
        ]);
    }

    public function show(Request $request)
    {
        $path = $this->resolvePath($request);
        $this->authorizeFile($request, $path);

        return response()->stream(function () use ($path) {
            $stream = Storage::readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => Storage::mimeType($path),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Whether this person may read this particular file.
     *
     * Being signed in used to be the whole of it: any user could read any
     * receipt, contract or daily-report photo by asking for its path. That is
     * closed one directory at a time, as each module has its permission pass —
     * `expenses/` here in M4. A directory whose module has not been swept keeps
     * the old answer, so nothing an existing customer does breaks mid-build.
     *
     * The owning record is found by the path itself, which is how it was
     * stored; a file on disk that no record claims is refused rather than
     * served, since nobody can be shown to have a right to it.
     */
    private function authorizeFile(Request $request, string $path): void
    {
        $directory = explode('/', $path)[0];

        [$area, $record] = match ($directory) {
            // M4 — the receipt is a column on the expense itself.
            'expenses' => ['expenses', Expense::where('receipt_path', $path)->first()],

            // M5 — income files are polymorphic attachments, so the owning
            // record is reached through the attachment.
            'income' => ['income', $this->attachedTo($path, Income::class)],

            // M7 — same shape as income.
            'requisitions' => ['requisitions', $this->attachedTo($path, PurchaseRequisition::class)],

            // M11 — the signed contract, and the aditivo that amends it. Two
            // directories, two owners, both reaching a project through their
            // own columns.
            'contracts' => ['contracts', Contract::where('contract_file_path', $path)->first()],
            'contract-change-orders' => [
                'contracts',
                ContractChangeOrder::where('file_path', $path)->first(),
            ],

            // M14 — a daily report's photos, filed under the report's id. The
            // path is `daily_reports/{id}/...`, so the report is named by the
            // path itself; the image rows carry the same path and either way
            // it is the report that governs it.
            'daily_reports' => ['daily-reports', $this->dailyReportFor($path)],

            // M10 — the signed change order, a column on the record itself.
            'change_orders' => ['change-orders', ChangeOrder::where('file_path', $path)->first()],

            // M9 — the supplier's order document, a column on the order itself.
            'purchase-orders' => ['purchase-orders', PurchaseOrder::where('receipt_path', $path)->first()],

            // M8 — this directory has two owners: the round itself carries the
            // RFQ's own files, and each vendor row carries the proposal that
            // came back. A row reaches its scope through its quotation.
            'quotations' => ['quotations', $this->attachedTo($path, [Quotation::class, QuotationVendor::class])],

            // A vendor belongs to no project, so the role alone answers; a
            // superseded or archived document is served on the same grant as
            // an active one — it is the history the audit wants to see.
            'subcontractor-documents' => ['vendors', SubcontractorDocument::where('file_path', $path)->first()],

            // Not yet swept: the old rule stands, and its own pass adds the
            // line here. Nothing fails if that is forgotten, so it is written
            // down in docs/review-and-improvements.md as well.
            default => [null, null],
        };

        if ($area === null) {
            return;
        }

        // A file on disk that no record claims is refused rather than served:
        // nobody can be shown to have a right to it.
        abort_if($record === null, 404, 'File not found');

        abort_unless(
            app(PermissionResolver::class)->allows($request->user(), $area.'.view', $record),
            403,
            __('You do not have permission to do that.'),
        );
    }

    /**
     * The daily report a photo belongs to.
     *
     * `daily_reports/{id}/…` names it in the path, but the id is taken from
     * the database rather than trusted: the image row is looked up by its
     * stored path, which is what the application wrote.
     */
    private function dailyReportFor(string $path): ?Model
    {
        return DailyReportImage::where('file_path', $path)->first()?->owningReport();
    }

    /**
     * The record a polymorphic attachment hangs on, when it is one of the
     * expected types. Anything else is treated as unclaimed.
     *
     * @param  string|array<int, string>  $expected
     */
    private function attachedTo(string $path, string|array $expected): ?Model
    {
        $attachment = Attachment::where('file_path', $path)
            ->whereIn('attachable_type', (array) $expected)
            ->first();

        return $attachment?->attachable;
    }

    /**
     * Validate the requested path and confirm the file exists, or abort.
     */
    private function resolvePath(Request $request): string
    {
        $path = (string) $request->query('path', '');

        abort_if($path === '' || ! $this->isSafePath($path), 404, 'File not found');
        abort_unless(Storage::exists($path), 404, 'File not found');

        return $path;
    }

    /**
     * A path is safe when it is relative, contains no traversal or null byte,
     * and sits inside one of the directories the application writes to.
     */
    private function isSafePath(string $path): bool
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '.')) {
            return false;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return in_array($segments[0], self::ALLOWED_DIRECTORIES, true);
    }
}
