<?php

namespace App\Mail;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One suspicious sign-in, told to whoever reads the login log
 * (docs/login-activity.md). $kind is lockout, spraying or new_device.
 */
class SecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public string $kind,
        public LoginEvent $event,
        public int $accounts = 0,
    ) {}

    public function envelope(): Envelope
    {
        $who = $this->event->user?->name ?? $this->event->email ?? $this->event->ip_address;

        return new Envelope(subject: match ($this->kind) {
            'lockout' => __('Security alert: :who was locked out after too many wrong passwords', ['who' => $who]),
            'spraying' => __('Security alert: :ip tried :count different accounts', ['ip' => $this->event->ip_address, 'count' => $this->accounts]),
            default => __('Security alert: :who signed in from a new device', ['who' => $who]),
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.security-alert', with: [
            'recipient' => $this->recipient,
            'kind' => $this->kind,
            'event' => $this->event,
            'accounts' => $this->accounts,
        ]);
    }
}
