<?php

namespace App\Livewire\User;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserEdit extends Component
{
    public User $user;
    public $name = '';
    public $email = '';
    public $phone = '';
    public $role_id = '';
    public $status = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:active,inactive,suspended',
        ];
    }

    protected $validationAttributes = [
        'name' => 'name',
        'email' => 'email address',
        'phone' => 'phone number',
        'role_id' => 'role',
        'status' => 'status',
    ];

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->role_id = $user->role_id;
        $this->status = $user->status->value;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function updateUser()
    {
        $this->validate();

        $this->user->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role_id' => $this->role_id,
            'status' => $this->status,
        ]);

        session()->flash('message', 'User updated successfully!');

        return redirect()->route('users.show', $this->user->id);
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
        $roles = Role::all();
        $statuses = UserStatus::cases();

        return view('livewire.user.user-edit', [
            'roles' => $roles,
            'statuses' => $statuses,
        ])->layout('components.layouts.app');
    }
}
