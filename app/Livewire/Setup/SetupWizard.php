<?php

namespace App\Livewire\Setup;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DefaultSupplierSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.auth')]
class SetupWizard extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        // Hard guard: once any user exists, the wizard is sealed for good.
        // Running systems all have users, so this route is effectively invisible.
        if (User::query()->exists()) {
            abort(404);
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function register(): void
    {
        // Re-check inside the action — protects against a race where someone
        // creates the first user between mount() and submit.
        if (User::query()->exists()) {
            abort(404);
        }

        $this->validate();

        $adminRole = Role::where('name', 'admin')->first();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'email_verified_at' => now(),
            'role_id' => $adminRole?->id,
        ]);

        // Default supplier needs a user FK; safe to run now.
        (new DefaultSupplierSeeder)->run();

        Auth::login($user);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.setup.setup-wizard');
    }
}
