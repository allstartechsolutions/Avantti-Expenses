<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A task was completed or cancelled. */
class TaskClosedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $recipient,
        public User $actor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->task->status === 'cancelled'
            ? __('Cancelled: :code — :title', ['code' => $this->task->code(), 'title' => $this->task->title])
            : __('Completed: :code — :title', ['code' => $this->task->code(), 'title' => $this->task->title]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-closed', with: [
            'task' => $this->task,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'cancelled' => $this->task->status === 'cancelled',
        ]);
    }
}
