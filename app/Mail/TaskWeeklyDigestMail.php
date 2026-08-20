<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * One mail a week per person: everything of theirs still open, worst first.
 */
class TaskWeeklyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Collection $tasks,
        public ?array $company = null,
    ) {}

    public function envelope(): Envelope
    {
        $overdue = $this->tasks->filter->isOverdue()->count();

        return new Envelope(subject: $overdue > 0
            ? trans_choice(
                'Your open tasks — :overdue overdue|Your open tasks — :overdue overdue',
                $overdue, ['overdue' => $overdue])
            : trans_choice('Your open tasks — :count item|Your open tasks — :count items',
                $this->tasks->count(), ['count' => $this->tasks->count()]));
    }

    public function content(): Content
    {
        $endOfWeek = now()->endOfWeek();

        return new Content(view: 'emails.task-weekly-digest', with: [
            'recipient' => $this->recipient,
            'overdue' => $this->tasks->filter->isOverdue()->values(),
            'awaiting' => $this->tasks->where('status', 'ready')->reject->isOverdue()->values(),
            'thisWeek' => $this->tasks->reject->isOverdue()->reject(fn ($t) => $t->status === 'ready')
                ->filter(fn ($t) => $t->due_date && $t->due_date->lte($endOfWeek))->values(),
            'later' => $this->tasks->reject->isOverdue()->reject(fn ($t) => $t->status === 'ready')
                ->filter(fn ($t) => ! $t->due_date || $t->due_date->gt($endOfWeek))->values(),
            'company' => $this->company,
        ]);
    }
}
