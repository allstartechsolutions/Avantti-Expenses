<?php

namespace Tests\Feature\Auth;

use App\Livewire\SystemSettings\NotificationSettings;
use App\Mail\SecurityAlertMail;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Login activity, stage 5: suspicious sign-ins mailed to whoever reads the log (docs/login-activity.md). */
class SecurityAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->withoutTwoFactor()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->alice = User::factory()->withoutTwoFactor()->create([
            'password' => Hash::make('secret-pass'),
            'role_id' => Role::where('name', 'employee')->value('id'),
        ]);
    }

    private function attempt(string $email, string $password, string $ip = '203.0.113.10', string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36')
    {
        return $this->withHeader('User-Agent', $agent)->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.store'), ['email' => $email, 'password' => $password]);
    }

    public function test_a_lockout_is_mailed_to_the_admins_once(): void
    {
        foreach (range(1, 9) as $ignored) {
            $this->attempt($this->alice->email, 'wrong');
        }

        Mail::assertSent(SecurityAlertMail::class, 1);
        Mail::assertSent(SecurityAlertMail::class, fn ($m) => $m->kind === 'lockout' && $m->recipient->is($this->admin) && $m->hasTo($this->admin->email));
        Mail::assertNotSent(SecurityAlertMail::class, fn ($m) => $m->recipient->is($this->alice));
    }

    public function test_one_address_trying_many_accounts_is_spraying(): void
    {
        foreach (range(1, 5) as $n) {
            $this->attempt("person{$n}@example.test", 'guess', '192.0.2.66');
        }

        Mail::assertSent(SecurityAlertMail::class, fn ($m) => $m->kind === 'spraying' && $m->accounts === 5);
    }

    public function test_a_sign_in_from_a_new_device_is_mailed(): void
    {
        $this->attempt($this->alice->email, 'secret-pass');
        $this->post(route('logout'));
        Mail::assertNothingSent();

        $this->attempt($this->alice->email, 'secret-pass', '198.51.100.7', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile/15E148 Safari/604.1');

        Mail::assertSent(SecurityAlertMail::class, fn ($m) => $m->kind === 'new_device' && $m->event->user_id === $this->alice->id);
    }

    public function test_the_company_switch_and_a_personal_opt_out_silence_it(): void
    {
        Livewire::actingAs($this->admin)->test(NotificationSettings::class)->call('toggle', NotificationSetting::SECURITY_ALERT);
        $this->assertFalse(NotificationSetting::enabled(NotificationSetting::SECURITY_ALERT));

        foreach (range(1, 6) as $ignored) {
            $this->attempt($this->alice->email, 'wrong');
        }
        Mail::assertNothingSent();

        Livewire::actingAs($this->admin)->test(NotificationSettings::class)->call('toggle', NotificationSetting::SECURITY_ALERT);
        $this->admin->forceFill(['notification_preferences' => [NotificationSetting::SECURITY_ALERT => false]])->save();

        foreach (range(1, 6) as $ignored) {
            $this->attempt('bob@example.test', 'wrong', '198.51.100.20');
        }
        Mail::assertNothingSent();
    }

    public function test_the_settings_toggle_refuses_an_unknown_key(): void
    {
        Livewire::actingAs($this->admin)->test(NotificationSettings::class)->call('toggle', 'made_up_key')->assertNotFound();
        $this->assertNull(NotificationSetting::query()->where('key', 'made_up_key')->first());
    }
}
