<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Rfi;
use App\Services\Collaboration\CollaborationDocumentRenderer;
use App\Services\PermissionResolver;

/**
 * An RFI or an approval as the document people print and file.
 *
 * **Guarded with the same grant as the screen, scoped to the record.** A PDF
 * controller left on `auth` alone is the hole the permission sweep found more
 * than once — four of them are still open elsewhere in
 * `docs/review-and-improvements.md`, and this module is not adding a fifth.
 *
 * The guard is here rather than in `ability:` middleware because that
 * middleware resolves a `project` or `jobSite` route parameter, and these
 * routes carry the document instead. Naming the bare ability without a scope
 * would be a *weaker* check than the screen's — anybody holding
 * `rfis.export` anywhere could print any project's RFI — so the record's own
 * project is what is asked about, exactly as `RfiShow::mount()` does.
 *
 * A draft prints stamped as a draft, so a paper copy can never be mistaken for
 * the record.
 */
class CollaborationPdfController extends Controller
{
    public function __construct(
        private readonly CollaborationDocumentRenderer $renderer,
        private readonly PermissionResolver $resolver,
    ) {}

    public function rfi(Rfi $rfi)
    {
        $this->authorizeDocument('rfis.export', $rfi);

        $rfi->logActivity(ActivityLogEntry::EXPORTED);

        return $this->renderer->pdf($rfi)->download($this->renderer->filename($rfi));
    }

    public function rfiStream(Rfi $rfi)
    {
        $this->authorizeDocument('rfis.export', $rfi);

        return $this->renderer->pdf($rfi)->stream($this->renderer->filename($rfi));
    }

    public function approval(Approval $approval)
    {
        $this->authorizeDocument('approvals.export', $approval);

        $approval->logActivity(ActivityLogEntry::EXPORTED);

        return $this->renderer->pdf($approval)->download($this->renderer->filename($approval));
    }

    public function approvalStream(Approval $approval)
    {
        $this->authorizeDocument('approvals.export', $approval);

        return $this->renderer->pdf($approval)->stream($this->renderer->filename($approval));
    }

    /** The ability, against the document's own job site or project. */
    protected function authorizeDocument(string $ability, Rfi|Approval $document): void
    {
        abort_unless(
            $this->resolver->allows(auth()->user(), $ability, $document->jobSite ?? $document->project),
            403,
            __('You do not have permission to do that.'),
        );
    }
}
