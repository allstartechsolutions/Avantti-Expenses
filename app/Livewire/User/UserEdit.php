<?php

namespace App\Livewire\User;

use App\Enums\AccessScope;
use App\Enums\UserStatus;
use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserEdit extends Component
{
    use AuthorizesAbility;

    public User $user;
    public $name = '';
    public $email = '';
    public $phone = '';
    public $role_id = '';
    public $status = '';

    /**
     * Which projects this person can reach: '' means follow their role, which
     * is the normal case and what everybody was migrated to. The other two are
     * a deliberate override on this one person.
     */
    public string $accessScope = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:active,inactive,suspended',
            'accessScope' => 'nullable|in:,company,assigned',
        ];
    }

    public function validationAttributes()
    {
        return [
            'name' => __('name'),
            'email' => __('email address'),
            'phone' => __('phone number'),
            'role_id' => __('role'),
            'status' => __('status'),
            'accessScope' => __('project access'),
        ];
    }

    public function mount(User $user)
    {
        $this->authorizeAbility('users.edit');

        $this->user = $user;
        $this->accessScope = $user->access_scope?->value ?? '';
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
        $this->authorizeAbility('users.edit');

        // Suspending or reactivating somebody is held apart from editing them:
        // it is what stops a person working, not a change of details.
        if ($this->status !== $this->user->status->value) {
            $this->authorizeAbility('users.suspend');
        }

        $this->validate();

        $wasScope = $this->user->effectiveAccessScope();

        $this->user->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role_id' => $this->role_id,
            'status' => $this->status,
            // A guest is confined by definition and has no say in the matter.
            'access_scope' => $this->user->is_guest
                ? AccessScope::ASSIGNED
                : ($this->accessScope === '' ? null : $this->accessScope),
        ]);

        $this->user->refresh();

        if ($wasScope !== $this->user->effectiveAccessScope()) {
            PermissionAudit::record(
                subjectType: 'user',
                subjectId: $this->user->id,
                action: 'scope-changed',
                summary: __(':name — project access changed to :scope', [
                    'name' => $this->user->name,
                    'scope' => __($this->user->effectiveAccessScope()->label()),
                ]),
                subjectUserId: $this->user->id,
                before: ['scope' => $wasScope->value],
                after: ['scope' => $this->user->effectiveAccessScope()->value],
            );
        }

        app(\App\Services\PermissionResolver::class)->flush();

        session()->flash('message', __('User updated successfully!'));

        return redirect()->route('users.show', $this->user->id);
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
        $roles = Role::all();
        $statuses = UserStatus::cases();

        return view('livewire.user.user-edit', [
            'roles' => $roles,
            'statuses' => $statuses,
        ])->layout('components.layouts.app');
    }
}
