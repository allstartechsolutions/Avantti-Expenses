<?php

namespace App\Services;

use App\Mail\MeetingMinuteMail;
use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\FileUpload;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\User;
use App\Contracts\StoredFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What happens to a minute once it is published: it is kept, it is filed, and
 * it is sent.
 *
 * Kept — a copy of the PDF as it read on the day, stored against the meeting,
 * so a correction later cannot quietly change what people were sent.
 *
 * Filed — into the project's document repository when the meeting belongs to
 * exactly one project, which is where somebody looking for it will go.
 *
 * Sent — to every attendee with an address, internal or not.
 */
class MeetingMinuteDistributor
{
    public function __construct(
        private readonly MeetingMinuteRenderer $renderer,
        private readonly DocumentStorageService $storage,
    ) {}

    /**
     * @return array{stored:bool, filed:bool, sent:int, failed:int}
     */
    public function distribute(Meeting $meeting, User $actor): array
    {
        $pdf = $this->renderer->bytes($meeting);
        $name = $this->renderer->filename($meeting);

        $file = $this->store($meeting, $pdf, $name, $actor);
        $document = $this->fileToRepository($meeting, $pdf, $name, $actor);
        $sent = $this->email($meeting, $pdf, $name);

        return [
            'stored' => $file !== null,
            'filed' => $document !== null,
            'sent' => $sent['sent'],
            'failed' => $sent['failed'],
        ];
    }

    // =========================================================================
    // KEEPING IT
    // =========================================================================

    /**
     * The PDF as it read when it was published, kept against the meeting.
     */
    public function store(Meeting $meeting, string $pdf, string $name, User $actor): ?FileUpload
    {
        $key = sprintf('meetings/%d/minutes/%s/%s', $meeting->id, (string) Str::uuid(), $name);

        try {
            Storage::disk(DocumentSettings::disk())->put($key, $pdf);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return FileUpload::create([
            'uuid' => (string) Str::uuid(),
            'attachable_type' => Meeting::class,
            'attachable_id' => $meeting->id,
            'disk' => DocumentSettings::disk(),
            'object_key' => $key,
            'original_name' => $name,
            'size_bytes' => strlen($pdf),
            'mime_type' => 'application/pdf',
            'checksum' => md5($pdf),
            'upload_status' => FileUpload::STATUS_AVAILABLE,
            'uploaded_by' => $actor->id,
        ]);
    }

    // =========================================================================
    // FILING IT
    // =========================================================================

    /**
     * The one project this meeting was about, or null when it spanned several
     * or none.
     *
     * A minute covering three projects has no single home in the repository,
     * and inventing one would file it where nobody looks. Those stay on the
     * meeting, which is where they are found from.
     */
    public function singleProject(Meeting $meeting): ?array
    {
        $scopes = $meeting->allItems()
            ->whereNotNull('project_id')
            ->get(['project_id', 'job_site_id']);

        $projects = $scopes->pluck('project_id')->unique();

        if ($projects->count() !== 1) {
            return null;
        }

        $jobSites = $scopes->pluck('job_site_id')->unique();

        return [
            'project_id' => (int) $projects->first(),
            // Only pin it to a job site if every item was about that one site.
            'job_site_id' => $jobSites->count() === 1 ? $jobSites->first() : null,
        ];
    }

    public function fileToRepository(Meeting $meeting, string $pdf, string $name, User $actor): ?Document
    {
        $scope = $this->singleProject($meeting);

        if (! $scope) {
            return null;
        }

        // Already filed once — a resend, or a re-file after a correction. The
        // repository versions a document rather than growing a second copy of
        // it, so that is what happens here too.
        if ($meeting->document_id && $existing = Document::find($meeting->document_id)) {
            return $this->fileNewVersion($existing, $pdf, $name, $actor, $meeting);
        }

        try {
            return DB::transaction(function () use ($meeting, $pdf, $name, $actor, $scope) {
                $document = Document::create([
                    'project_id' => $scope['project_id'],
                    'job_site_id' => $scope['job_site_id'],
                    'name' => $meeting->number.' — '.$meeting->title,
                    'description' => __('Meeting minutes, filed automatically when the minute was published.'),
                    'category' => 'correspondence',
                    'uploaded_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $key = DocumentSettings::objectKey($scope['project_id'], $document->uuid, 1, $name);

                Storage::disk(DocumentSettings::disk())->put($key, $pdf);

                $version = $document->versions()->create([
                    'version_number' => 1,
                    'disk' => DocumentSettings::disk(),
                    'object_key' => $key,
                    'original_name' => $name,
                    'size_bytes' => strlen($pdf),
                    'mime_type' => 'application/pdf',
                    'checksum' => md5($pdf),
                    'upload_status' => StoredFile::STATUS_AVAILABLE,
                    'uploaded_by' => $actor->id,
                ]);

                $document->update([
                    'current_version_id' => $version->id,
                    'current_size_bytes' => $version->size_bytes,
                    'current_mime_type' => $version->mime_type,
                    'current_version_number' => 1,
                ]);

                DocumentActivity::record(
                    DocumentActivity::UPLOADED,
                    ['document_id' => $document->id],
                    ['version' => 1, 'size' => $version->size_bytes, 'source' => $meeting->number]
                );

                $meeting->update(['document_id' => $document->id]);

                return $document;
            });
        } catch (\Throwable $e) {
            // Filing is a convenience; failing it must not undo a publication.
            report($e);

            return null;
        }
    }

    /**
     * Another version of a minute already in the repository.
     *
     * Only when the record actually changed. Comparing the PDF bytes does not
     * work — dompdf stamps a creation date, so re-rendering the same minute
     * produces different bytes every time — so the test is whether a
     * correction has been recorded since the filed version was written. A
     * resend to a mistyped address must not make the document history look
     * like the minute changed.
     */
    private function fileNewVersion(Document $document, string $pdf, string $name, User $actor, Meeting $meeting): ?Document
    {
        $filedAt = $document->currentVersion?->created_at;

        $correctedSince = $filedAt && $meeting->revisions()
            ->where('created_at', '>', $filedAt)
            ->exists();

        if (! $correctedSince) {
            return $document;
        }

        try {
            return DB::transaction(function () use ($document, $pdf, $name, $actor, $meeting) {
                $next = (int) $document->versions()->max('version_number') + 1;
                $key = DocumentSettings::objectKey($document->project_id, $document->uuid, $next, $name);

                Storage::disk(DocumentSettings::disk())->put($key, $pdf);

                $version = $document->versions()->create([
                    'version_number' => $next,
                    'disk' => DocumentSettings::disk(),
                    'object_key' => $key,
                    'original_name' => $name,
                    'size_bytes' => strlen($pdf),
                    'mime_type' => 'application/pdf',
                    'checksum' => md5($pdf),
                    'notes' => __('Re-filed from :number', ['number' => $meeting->number]),
                    'upload_status' => StoredFile::STATUS_AVAILABLE,
                    'uploaded_by' => $actor->id,
                ]);

                $document->update([
                    'current_version_id' => $version->id,
                    'current_size_bytes' => $version->size_bytes,
                    'current_version_number' => $next,
                    'updated_by' => $actor->id,
                ]);

                DocumentActivity::record(
                    DocumentActivity::VERSION_ADDED,
                    ['document_id' => $document->id],
                    ['version' => $next, 'size' => $version->size_bytes, 'source' => $meeting->number]
                );

                return $document;
            });
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // =========================================================================
    // SENDING IT
    // =========================================================================

    /**
     * @return array{sent:int, failed:int}
     */
    public function email(Meeting $meeting, string $pdf, string $name): array
    {
        $meeting->loadMissing(['attendees.user', 'allItems.task.owner', 'chair', 'series']);

        $sent = $failed = 0;

        foreach ($meeting->attendees as $attendee) {
            $address = $attendee->displayEmail();

            if (! $address || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($address)->send(new MeetingMinuteMail($meeting, $attendee, $pdf, $name));

                $attendee->update(['notified_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                // One bad address must not stop the rest of the room being told.
                Log::warning('Meeting minute could not be e-mailed', [
                    'meeting' => $meeting->number,
                    'attendee' => $attendee->id,
                    'error' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** Who would receive it, for the screen to show before it is sent. */
    public function recipients(Meeting $meeting): \Illuminate\Support\Collection
    {
        return $meeting->attendees
            ->filter(fn (MeetingAttendee $a) => filter_var((string) $a->displayEmail(), FILTER_VALIDATE_EMAIL))
            ->values();
    }
}
