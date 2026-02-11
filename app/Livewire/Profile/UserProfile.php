<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class UserProfile extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';
    }

    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore(Auth::id())],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        Auth::user()->update($validated);

        session()->flash('profile-message', 'Profile updated successfully.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password',
                Password::min(8)->letters()->mixedCase()->symbols(),
            ],
        ]);

        Auth::user()->update([
            'password' => Hash::make($this->password),
        ]);

        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('login'));
    }

    public function render()
    {
        return view('livewire.profile.user-profile')
            ->layout('components.layouts.app');
    }
}
