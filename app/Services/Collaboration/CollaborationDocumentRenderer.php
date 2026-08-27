<?php

namespace App\Services\Collaboration;

use App\Models\Approval;
use App\Models\Company;
use App\Models\Rfi;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Renders an RFI or an approval as the document people print, file and post.
 *
 * One place, because the same bytes are downloaded from the screen and
 * attached to the e-mail — two routes to one document, which must not drift.
 * Same reasoning as `MeetingMinuteRenderer`, which this follows.
 *
 * **One renderer, two templates.** The BR sheet carries the empreendimento
 * header, the responsible professional's CREA/CAU registration and the ART
 * number, with a signature block; the US sheet is a transmittal cover. Which
 * one is used is decided by `config('app.country')` at render time — a
 * presentation choice, the same as every other country difference in this
 * module.
 */
class CollaborationDocumentRenderer
{
    public function pdf(Model $document): PdfWrapper
    {
        $pdf = Pdf::loadView($this->template($document), $this->data($document));
        $pdf->setPaper(config('app.country') === 'BR' ? 'a4' : 'letter', 'portrait');

        return $pdf;
    }

    /**
     * The document as HTML.
     *
     * Worth having on its own: asserting on what the sheet says means reading
     * this rather than the compressed bytes of a rendered PDF.
     */
    public function html(Model $document): string
    {
        return view($this->template($document), $this->data($document))->render();
    }

    public function bytes(Model $document): string
    {
        return $this->pdf($document)->output();
    }

    public function filename(Model $document): string
    {
        $title = $document instanceof Rfi ? $document->subject : $document->title;

        return Str::slug($document->number.'-'.$title).'.pdf';
    }

    /** BR gets its own sheet; everywhere else gets the transmittal. */
    protected function template(Model $document): string
    {
        $kind = $document instanceof Rfi ? 'rfi' : 'approval';
        $market = config('app.country') === 'BR' ? 'br' : 'us';

        return "pdf.collaboration.{$kind}-{$market}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(Model $document): array
    {
        if ($document instanceof Rfi) {
            $document->loadMissing([
                'project', 'jobSite', 'ballInCourt', 'createdBy', 'answeredBy',
                'changeOrder', 'distribution.user', 'signatures.user',
            ]);
        } else {
            $document->loadMissing([
                'project', 'jobSite', 'ballInCourt', 'createdBy', 'certificate',
                'budgetItem', 'supplier', 'catalogItem', 'distribution.user', 'signatures.user',
                'revisions.reviewers.user', 'revisions.responseCode', 'revisions.respondedBy',
                'revisions.submittedBy',
            ]);
        }

        $company = Company::first();

        return [
            'document' => $document,
            'company' => $company,
            'logoData' => $this->logo($company),
            // A signature that no longer matches what it signed must say so on
            // the sheet, or the printed copy claims more than it can.
            'signatures' => $document->signatures->map(fn ($signature) => [
                'signature' => $signature,
                'intact' => $document->signatureIsIntact($signature),
            ]),
        ];
    }

    /**
     * The company logo as a data URI.
     *
     * dompdf cannot fetch a URL, so the logo travels inline. Same resolution
     * as `MeetingMinuteRenderer::logo()` — the column is `logo` and the file
     * sits under the public disk — because two renderers disagreeing about
     * where the logo lives is how one document ends up without it.
     */
    protected function logo(?Company $company): string
    {
        if (! $company?->logo) {
            return '';
        }

        $path = storage_path('app/public/'.$company->logo);

        if (! is_file($path)) {
            return '';
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
