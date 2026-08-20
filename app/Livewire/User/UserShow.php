<?php

namespace App\Livewire\User;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UserShow extends Component
{
    use AuthorizesAbility;

    public User $user;

    public function mount(User $user)
    {
        $this->authorizeAbility('users.view');

        $this->user = $user;
    }

    /**
     * Every project and job site this person has been added to, with what they
     * may do there. Without this the Users screen can tell you somebody is
     * confined but not what they are confined *to*.
     */
    #[Computed]
    public function memberships()
    {
        return Membership::with(['scopeable', 'template', 'abilityRows'])
            ->where('user_id', $this->user->id)
            ->get()
            ->sortBy(fn (Membership $m) => $m->scopeable?->scopeLabel() ?? '')
            ->values();
    }

    /** What has been done to this person's access, and by whom. */
    #[Computed]
    public function accessHistory()
    {
        return PermissionAudit::with('actor')
            ->where('subject_user_id', $this->user->id)
            ->latest('created_at')
            ->limit(15)
            ->get();
    }

    public function sendPasswordReset()
    {
        $status = Password::sendResetLink(
            ['email' => $this->user->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('message', __('Password reset link sent to :email', ['email' => $this->user->email]));
        } else {
            session()->flash('error', __('Failed to send password reset link. Please try again.'));
        }
    }

    public function render()
    {
        return view('livewire.user.user-show')
            ->layout('components.layouts.app');
    }
}
