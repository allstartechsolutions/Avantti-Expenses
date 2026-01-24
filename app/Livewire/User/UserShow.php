<?php

namespace App\Livewire\User;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class UserShow extends Component
{
    public User $user;

    public function mount(User $user)
    {
        $this->user = $user;
    }

    public function sendPasswordReset()
    {
        $status = Password::sendResetLink(
            ['email' => $this->user->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('message', 'Password reset link sent to ' . $this->user->email);
        } else {
            session()->flash('error', 'Failed to send password reset link. Please try again.');
        }
    }

    public function render()
    {
        return view('livewire.user.user-show')
            ->layout('components.layouts.app');
    }
}
