<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Meeting;
use App\Services\MeetingAgendaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Str;

/**
 * Renders the minute.
 *
 * One place, because the same bytes are downloaded from the screen, attached
 * to the e-mail and filed into the document repository — three routes to the
 * same document, which must not drift.
 */
class MeetingMinuteRenderer
{
    public function pdf(Meeting $meeting): PdfWrapper
    {
        $pdf = Pdf::loadView('pdf.meeting-minute', $this->data($meeting));
        $pdf->setPaper('letter', 'portrait');

        return $pdf;
    }

    /**
     * The minute as HTML, which is what dompdf is handed.
     *
     * Useful on its own: asserting on the document's wording means reading this
     * rather than the compressed bytes of a rendered PDF.
     */
    public function html(Meeting $meeting): string
    {
        return view('pdf.meeting-minute', $this->data($meeting))->render();
    }

    public function bytes(Meeting $meeting): string
    {
        return $this->pdf($meeting)->output();
    }

    public function filename(Meeting $meeting): string
    {
        return Str::slug($meeting->number.'-'.$meeting->title).'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Meeting $meeting): array
    {
        $meeting->load([
            'series', 'chair', 'secretary', 'publishedBy',
            'attendees.user', 'revisions.revisedBy', 'nextMeeting',
        ]);

        $roots = $meeting->items()
            ->with(['task.owner', 'project', 'jobSite', 'carriedFrom.meeting', 'children.task.owner', 'children.project', 'children.jobSite', 'children.carriedFrom.meeting'])
            ->get();

        // Parents and their children in reading order, so the PDF numbers read
        // 1, 1.1, 2 the way the screen does.
        $flatten = fn ($items) => collect($items)->flatMap(fn ($item) => collect([$item])->concat($item->children));

        // Under the same location headings the agenda was built with: the ata
        // is where the complaint about the order came from, so grouping that
        // stopped at the screen would not have answered it.
        $blocks = app(MeetingAgendaService::class)->blocksFrom($roots)
            ->map(fn (array $block) => ['label' => $block['label'], 'items' => $flatten($block['items'])]);

        $company = Company::first();

        return [
            'meeting' => $meeting,
            'items' => $flatten($roots),
            'blocks' => $blocks,
            'company' => $company,
            'logoData' => $this->logo($company),
        ];
    }

    /** dompdf cannot fetch a URL, so the logo travels as a data URI. */
    private function logo(?Company $company): string
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
