<?php

namespace App\Livewire\Auth;

use App\Models\UserInvitation;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * The public end of an invitation: choose a name and a password, and the
 * account and its memberships are created.
 *
 * Public by necessity — the person has no login yet — so the token is the only
 * credential and the route is throttled. An invitation that is expired,
 * withdrawn or already used says so plainly rather than failing silently.
 */
class AcceptInvitation extends Component
{
    public string $token = '';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?UserInvitation $invitation = null;

    /** Why this link cannot be used, if it cannot. */
    public ?string $problem = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $invitation = UserInvitation::findByToken($token);

        if (! $invitation) {
            $this->problem = 'unknown';

            return;
        }

        $this->invitation = $invitation;
        $this->name = (string) $invitation->name;

        if ($invitation->accepted_at) {
            $this->problem = 'accepted';
        } elseif ($invitation->revoked_at) {
            $this->problem = 'revoked';
        } elseif ($invitation->isExpired()) {
            $this->problem = 'expired';
        } elseif (\App\Models\User::where('email', $invitation->email)->exists()) {
            // Somebody was given a login between the invitation and the click.
            $this->problem = 'already-a-user';
        }
    }

    public function accept(InvitationService $invitations): void
    {
        abort_if($this->problem !== null || ! $this->invitation, 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], [
            'name' => __('Name'),
            'password' => __('Password'),
        ]);

        $user = $invitations->accept($this->invitation, $this->name, $this->password);

        Auth::login($user);

        session()->regenerate();

        $this->redirect($invitations->landingFor($user), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.accept-invitation')->layout('components.layouts.guest');
    }
}
