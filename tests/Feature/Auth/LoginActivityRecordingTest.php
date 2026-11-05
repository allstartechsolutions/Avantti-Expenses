<?php

namespace Tests\Feature\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Services\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Login activity, stage 1: every sign-in event lands in login_events through
 * the real Fortify flow (docs/login-activity.md).
 */
class LoginActivityRecordingTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private function signIn(string $email, string $password, string $agent = self::CHROME_WINDOWS, string $ip = '203.0.113.10')
    {
        return $this->withHeader('User-Agent', $agent)
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.store'), ['email' => $email, 'password' => $password]);
    }

    public function test_a_sign_in_is_recorded_with_device_and_last_login(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['password' => Hash::make('secret-pass')]);

        $this->signIn($user->email, 'secret-pass')->assertRedirect();

        $event = LoginEvent::sole();
        $this->assertSame(LoginEvent::LOGIN, $event->event);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('203.0.113.10', $event->ip_address);
        $this->assertSame(['Chrome', 'Windows', 'desktop'], [$event->browser, $event->platform, $event->device]);
        $this->assertSame('Chrome on Windows', $event->getDeviceLabel());
        $this->assertFalse($event->is_new_device, 'The first sign-in has nothing to compare with.');

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('203.0.113.10', $user->last_login_ip);
    }

    public function test_a_wrong_password_and_an_unknown_email_are_recorded_as_failed(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['password' => Hash::make('secret-pass')]);

        $this->signIn($user->email, 'wrong');
        $this->signIn('nobody@example.test', 'whatever');

        $failed = LoginEvent::where('event', LoginEvent::FAILED)->orderBy('id')->get();
        $this->assertCount(2, $failed);
        $this->assertSame([$user->id, null], $failed->pluck('user_id')->all());
        $this->assertSame([$user->email, 'nobody@example.test'], $failed->pluck('email')->all());
        $this->assertGuest();
    }

    public function test_too_many_attempts_record_a_lockout(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['password' => Hash::make('secret-pass')]);

        foreach (range(1, 8) as $ignored) { // three blocked tries, one lockout
            $this->signIn($user->email, 'wrong');
        }

        $this->assertSame(5, LoginEvent::where('event', LoginEvent::FAILED)->count());
        $lockout = LoginEvent::where('event', LoginEvent::LOCKOUT)->sole();
        $this->assertSame($user->id, $lockout->user_id);
    }

    public function test_a_sign_out_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect();

        $this->assertSame(LoginEvent::LOGOUT, LoginEvent::where('user_id', $user->id)->sole()->event);
    }

    public function test_a_sign_in_from_a_new_browser_or_network_is_flagged(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['password' => Hash::make('secret-pass')]);

        $this->signIn($user->email, 'secret-pass');
        $this->post(route('logout'));
        $this->signIn($user->email, 'secret-pass', ip: '203.0.113.99'); // same /24 network
        $this->post(route('logout'));
        $this->signIn($user->email, 'secret-pass', self::SAFARI_IPHONE, '198.51.100.7');

        $this->assertSame([false, false, true], LoginEvent::where('event', LoginEvent::LOGIN)->orderBy('id')->pluck('is_new_device')->all());
    }

    public function test_the_two_factor_step_is_recorded(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['password' => Hash::make('secret-pass')]);
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one', 'recovery-two'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->signIn($user->email, 'secret-pass')->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['code' => '000000']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-one']);

        $this->assertSame(
            [LoginEvent::TWO_FACTOR_CHALLENGED, LoginEvent::TWO_FACTOR_FAILED, LoginEvent::TWO_FACTOR_PASSED, LoginEvent::LOGIN],
            LoginEvent::where('user_id', $user->id)->orderBy('id')->pluck('event')->all(),
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_events_older_than_the_retention_window_are_pruned(): void
    {
        $user = User::factory()->create();
        LoginEvent::create(['user_id' => $user->id, 'event' => LoginEvent::LOGIN, 'created_at' => now()->subDays(LoginEvent::RETENTION_DAYS + 1)]);
        $kept = LoginEvent::create(['user_id' => $user->id, 'event' => LoginEvent::LOGIN, 'created_at' => now()->subDays(LoginEvent::RETENTION_DAYS - 1)]);

        $this->artisan('auth:prune-login-events')->assertSuccessful();

        $this->assertSame([$kept->id], LoginEvent::pluck('id')->all());
    }

    public function test_the_user_agent_reader(): void
    {
        $this->assertSame(['browser' => 'Safari', 'platform' => 'iPhone', 'device' => 'mobile'], UserAgent::parse(self::SAFARI_IPHONE));
        $this->assertSame('Edge', UserAgent::parse('Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0')['browser']);
        $this->assertSame(['browser' => null, 'platform' => null, 'device' => null], UserAgent::parse(''));
        $this->assertSame(UserAgent::fingerprint('Chrome', 'Windows', '10.0.0.1'), UserAgent::fingerprint('Chrome', 'Windows', '10.0.0.254'));
    }
}
