<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The published minute, sent to everyone who was invited.
 *
 * Each recipient gets their own copy so the mail can open with the items they
 * personally own — the thing most people are reading it for.
 */
class MeetingMinuteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public MeetingAttendee $attendee,
        public string $pdfContent,
        public string $pdfName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':number — :title, :date', [
                'number' => $this->meeting->number,
                'title' => $this->meeting->title,
                'date' => $this->meeting->meeting_date->format(
                    config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y'
                ),
            ]),
        );
    }

    public function content(): Content
    {
        // The action items this person owns, as at the meeting.
        $theirs = $this->meeting->allItems
            ->filter(fn ($item) => $item->task
                && $this->attendee->user_id
                && $item->task->owner_id === $this->attendee->user_id);

        return new Content(
            view: 'emails.meeting-minute',
            with: [
                'meeting' => $this->meeting,
                'attendee' => $this->attendee,
                'theirs' => $theirs,
                'actionItems' => $this->meeting->allItems->where('type', 'action'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfName)
                ->withMime('application/pdf'),
        ];
    }
}
