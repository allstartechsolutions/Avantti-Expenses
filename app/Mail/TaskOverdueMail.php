<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent once, the morning after a task passes its date. */
class TaskOverdueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: trans_choice(
            'Past due by :count day: :code — :title|Past due by :count days: :code — :title',
            $this->task->daysOverdue(),
            ['count' => $this->task->daysOverdue(), 'code' => $this->task->code(), 'title' => $this->task->title]
        ));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-overdue', with: [
            'task' => $this->task,
            'recipient' => $this->recipient,
            'lastNote' => $this->task->notes()->first(),
        ]);
    }
}
