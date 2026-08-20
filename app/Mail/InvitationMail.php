<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You have been given access" — the one e-mail that carries a usable token.
 * The plain token is passed in and never stored, so this is the only place it
 * ever exists after the invitation is written.
 */
class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        $company = config('app.name');

        return new Envelope(subject: $this->invitation->is_guest
            ? __('You have been given access to :company', ['company' => $company])
            : __('Your :company account is ready to set up', ['company' => $company]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation', with: [
            'invitation' => $this->invitation,
            'url' => route('invitations.accept', $this->token),
            'invitedBy' => $this->invitation->invitedBy,
            'places' => $this->places(),
        ]);
    }

    /** The projects or job sites this invitation carries, by name. */
    protected function places(): array
    {
        $names = [];

        foreach ($this->invitation->payload ?? [] as $entry) {
            $scope = ($entry['scopeable_type'] ?? null)
                ? $entry['scopeable_type']::find($entry['scopeable_id'] ?? null)
                : null;

            if ($scope) {
                $names[] = $scope instanceof \App\Models\JobSite
                    ? $scope->job_site_name
                    : $scope->project_name;
            }
        }

        return $names;
    }
}
