<?php

namespace App\Livewire\User;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UserCreate extends Component
{
    public $name = '';
    public $email = '';
    public $phone = '';
    public $password = '';
    public $password_confirmation = '';
    public $role_id = '';
    public $status = 'active';

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'phone' => 'nullable|string|max:20',
        'password' => 'required|string|min:8|confirmed',
        'role_id' => 'required|exists:roles,id',
        'status' => 'required|in:active,inactive,suspended',
    ];

    protected $validationAttributes = [
        'name' => 'name',
        'email' => 'email address',
        'phone' => 'phone number',
        'password' => 'password',
        'role_id' => 'role',
        'status' => 'status',
    ];

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function createUser()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'role_id' => $this->role_id,
            'status' => $this->status,
        ]);

        session()->flash('message', 'User created successfully!');

        return redirect()->route('users.index');
    }

    public function render()
    {
        $roles = Role::all();
        $statuses = UserStatus::cases();

        return view('livewire.user.user-create', [
            'roles' => $roles,
            'statuses' => $statuses,
        ])->layout('components.layouts.app');
    }
}
