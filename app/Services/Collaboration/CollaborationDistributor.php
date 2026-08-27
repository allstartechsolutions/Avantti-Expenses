<?php

namespace App\Services\Collaboration;

use App\Mail\CollaborationDocumentMail;
use App\Models\Approval;
use App\Models\Collaboration\ActivityLogEntry;
use App\Models\Rfi;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a document to its distribution list, and records that it was sent.
 *
 * Follows `MeetingMinuteDistributor`: the same bytes the screen downloads are
 * the bytes that go out, and every send is on the record. "The projetista was
 * sent this on the 4th" is a sentence this module has to be able to say, and
 * it cannot be reconstructed from anything else afterwards.
 *
 * **One failed address does not stop the rest.** A wrong e-mail on one line of
 * a distribution list is not a reason for the other five not to receive the
 * drawing; the failure is counted, logged and reported back.
 */
class CollaborationDistributor
{
    public function __construct(private readonly CollaborationDocumentRenderer $renderer) {}

    /**
     * @return array{sent:int, failed:int, recipients:array<int, string>}
     */
    public function distribute(Rfi|Approval $document, User $actor, ?string $note = null): array
    {
        $recipients = $document->distributionRecipients();

        if ($recipients->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'recipients' => []];
        }

        $pdf = $this->renderer->bytes($document);
        $filename = $this->renderer->filename($document);

        $sent = 0;
        $failed = 0;
        $delivered = [];

        foreach ($recipients as $address => $name) {
            try {
                Mail::to($address, $name)->send(
                    new CollaborationDocumentMail($document, $pdf, $filename, $note)
                );

                $sent++;
                $delivered[] = $address;
            } catch (\Throwable $e) {
                $failed++;

                Log::warning('Collaboration document could not be sent.', [
                    'document' => $document::class.':'.$document->id,
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $document->logActivity(ActivityLogEntry::DISTRIBUTED, [
            'sent' => $sent,
            'failed' => $failed,
            'recipients' => $delivered,
            'note' => $note,
        ]);

        return ['sent' => $sent, 'failed' => $failed, 'recipients' => $delivered];
    }
}
