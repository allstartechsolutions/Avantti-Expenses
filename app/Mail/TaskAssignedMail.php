<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "You are on this task" — sent when somebody is made owner or assignee. */
class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $recipient,
        public User $actor,
    ) {}

    public function envelope(): Envelope
    {
        $owns = $this->task->owner_id === $this->recipient->id;

        return new Envelope(subject: $owns
            ? __('You own :code — :title', ['code' => $this->task->code(), 'title' => $this->task->title])
            : __('You are on :code — :title', ['code' => $this->task->code(), 'title' => $this->task->title]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-assigned', with: [
            'task' => $this->task,
            'recipient' => $this->recipient,
            'actor' => $this->actor,
            'owns' => $this->task->owner_id === $this->recipient->id,
        ]);
    }
}
