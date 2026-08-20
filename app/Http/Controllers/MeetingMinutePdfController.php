<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Meeting;
use App\Services\MeetingMinuteRenderer;

/**
 * The ata as a document: what gets printed, filed and e-mailed.
 *
 * A draft can be previewed — the secretary usually wants to read it before
 * publishing — but it is stamped as a draft on the page so a printed copy can
 * never be mistaken for the record.
 */
class MeetingMinutePdfController extends Controller
{
    public function __construct(private readonly MeetingMinuteRenderer $renderer)
    {
    }

    public function download(Meeting $meeting)
    {
        return $this->renderer->pdf($meeting)->download($this->renderer->filename($meeting));
    }

    public function stream(Meeting $meeting)
    {
        return $this->renderer->pdf($meeting)->stream($this->renderer->filename($meeting));
    }
}
