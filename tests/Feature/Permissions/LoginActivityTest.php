<?php

namespace Tests\Feature\Permissions;

use App\Livewire\LoginActivity\LoginActivityIndex;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Company → Login Activity (docs/login-activity.md): admin-only by seed,
 * revocable, and its CSV answers to the same grant as the screen.
 */
class LoginActivityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->alice = User::factory()->create(['name' => 'Alice Ramos', 'role_id' => Role::where('name', 'employee')->value('id')]);

        LoginEvent::create(['user_id' => $this->alice->id, 'email' => $this->alice->email, 'event' => LoginEvent::LOGIN, 'ip_address' => '203.0.113.10', 'browser' => 'Chrome', 'platform' => 'Windows', 'device' => 'desktop', 'created_at' => now()->subHour()]);
        LoginEvent::create(['user_id' => $this->alice->id, 'email' => $this->alice->email, 'event' => LoginEvent::LOGIN, 'ip_address' => '198.51.100.7', 'browser' => 'Safari', 'platform' => 'iPhone', 'device' => 'mobile', 'is_new_device' => true, 'created_at' => now()->subMinutes(30)]);
        LoginEvent::create(['user_id' => null, 'email' => 'attacker@example.test', 'event' => LoginEvent::FAILED, 'ip_address' => '192.0.2.66', 'created_at' => now()->subMinutes(10)]);
        LoginEvent::create(['user_id' => $this->alice->id, 'email' => $this->alice->email, 'event' => LoginEvent::LOCKOUT, 'ip_address' => '192.0.2.66', 'created_at' => now()->subMinutes(9)]);
        LoginEvent::create(['user_id' => $this->alice->id, 'email' => $this->alice->email, 'event' => LoginEvent::LOGIN, 'ip_address' => '203.0.113.10', 'created_at' => now()->subDays(30)]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id')]);
    }

    public function test_the_screen_answers_as_seeded_for_every_role(): void
    {
        $this->actingAs($this->admin)->get(route('login-activity.index'))->assertOk()->assertSee(__('Login Activity'));
        $this->actingAs($this->user('manager'))->get(route('login-activity.index'))->assertForbidden();
        $this->actingAs($this->user('employee'))->get(route('login-activity.index'))->assertForbidden();
    }

    public function test_the_grant_can_be_given_and_taken_away(): void
    {
        $role = Role::create(['name' => 'security-officer']);
        $role->syncAbilities(['login-activity.view']);
        $officer = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($officer)->get(route('login-activity.index'))->assertOk();

        $role->syncAbilities([]);
        // The resolver memoises per request; a test is one long request.
        app(PermissionResolver::class)->flush();
        $this->actingAs($officer->fresh())->get(route('login-activity.index'))->assertForbidden();
        Livewire::actingAs($officer->fresh())->test(LoginActivityIndex::class)->assertForbidden();
    }

    public function test_the_list_the_cards_and_the_filters(): void
    {
        Livewire::actingAs($this->admin)
            ->test(LoginActivityIndex::class)
            // Last 7 days by default: the 30-day-old sign-in is out.
            ->assertViewHas('events', fn ($e) => $e->total() === 4)
            ->assertViewHas('summary', fn ($s) => $s['logins'] === 2 && $s['failed'] === 1 && $s['lockouts'] === 1 && $s['new_devices'] === 1 && $s['people'] === 1)
            ->assertSee('attacker@example.test')
            ->assertSee(__('No account with this email'))
            ->assertSee('Safari on iPhone')
            ->call('showProblems')
            ->assertViewHas('events', fn ($e) => $e->pluck('event')->sort()->values()->all() === [LoginEvent::FAILED, LoginEvent::LOCKOUT])
            ->call('showProblems')
            ->set('search', '192.0.2.66')
            ->assertViewHas('events', fn ($e) => $e->total() === 2)
            ->call('clearFilters')
            ->assertViewHas('events', fn ($e) => $e->total() === 5)
            ->set('newDevicesOnly', true)
            ->assertViewHas('events', fn ($e) => $e->total() === 1);
    }

    public function test_the_csv_follows_the_filters_and_the_grant(): void
    {
        Livewire::actingAs($this->admin)
            ->test(LoginActivityIndex::class)
            ->set('eventFilter', LoginEvent::FAILED)
            ->call('exportCsv')
            ->assertFileDownloaded('login-activity-'.now()->format('Y-m-d').'.csv');

        Livewire::actingAs($this->user('manager'))->test(LoginActivityIndex::class)->assertForbidden();
    }

    public function test_the_menu_entry_follows_the_grant(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('login-activity.index'), false);
        $this->actingAs($this->user('manager'))->get(route('dashboard'))->assertDontSee(route('login-activity.index'), false);
    }

    /** Stage 3: last login on Users and the person's history on their page, for whoever reads the log. */
    public function test_users_list_and_user_page_show_sign_ins_to_admins_only(): void
    {
        $this->alice->forceFill(['last_login_at' => now()->subMinutes(30), 'last_login_ip' => '198.51.100.7'])->save();

        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->assertSee(__('Last login'))->assertSee('198.51.100.7');
        $this->actingAs($this->admin)->get(route('users.show', $this->alice))->assertOk()
            ->assertSee(__('Recent sign-ins'))->assertSee('Safari on iPhone');

        // Somebody who may manage users but not read the log sees neither.
        $role = Role::create(['name' => 'hr']);
        $role->syncAbilities(['users.view']);
        $hr = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($hr)->get(route('users.index'))->assertOk()->assertDontSee(__('Last login'))->assertDontSee('198.51.100.7');
        $this->actingAs($hr)->get(route('users.show', $this->alice))->assertOk()->assertDontSee(__('Recent sign-ins'));
    }

    /** Stage 3: everybody sees their own sign-ins, and only their own. */
    public function test_each_person_sees_their_own_sign_ins_in_settings(): void
    {
        $bob = $this->user('employee');
        LoginEvent::create(['user_id' => $bob->id, 'email' => $bob->email, 'event' => LoginEvent::LOGIN, 'ip_address' => '10.9.8.7', 'created_at' => now()]);

        // The settings pages' full-page layout is not registered under the test
        // runner (the same reason ProfileUpdateTest fails), so render the page's own component.
        Volt::actingAs($this->alice)->test('settings.sign-ins')
            ->assertSee('198.51.100.7')
            ->assertDontSee('10.9.8.7')
            ->assertSee(trans_choice(':count failed attempt on your account in the last 7 days.|:count failed attempts on your account in the last 7 days.', 1, ['count' => 1]));

        Volt::actingAs($bob)->test('settings.sign-ins')
            ->assertSee('10.9.8.7')
            ->assertDontSee('198.51.100.7');
    }

    /** Stage 4: who is signed in now, and ending a session. */
    private function liveSession(string $id, User $user, string $ip = '203.0.113.10'): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => $ip,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36',
            'payload' => base64_encode(serialize([])), 'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_an_admin_sees_live_sessions_and_ends_one(): void
    {
        $this->liveSession('alice-laptop', $this->alice);
        $this->liveSession('alice-phone', $this->alice, '198.51.100.7');
        $this->alice->forceFill(['remember_token' => 'old-token'])->save();

        $page = Livewire::actingAs($this->admin)
            ->test(LoginActivityIndex::class)
            ->assertSee(__('Signed in now'))
            ->assertSee('Chrome on Windows')
            ->assertDontSee('alice-laptop'); // the raw session id never reaches the page

        $page->call('endSession', hash('sha256', 'alice-laptop'))->assertHasNoErrors();

        $this->assertSame(['alice-phone'], DB::table('sessions')->where('user_id', $this->alice->id)->pluck('id')->all());
        $this->assertNotSame('old-token', $this->alice->fresh()->remember_token, 'A "remember me" cookie must not bring the session back.');

        $ended = LoginEvent::where('event', LoginEvent::SESSION_ENDED)->sole();
        $this->assertSame([$this->alice->id, $this->admin->id], [$ended->user_id, $ended->actor_id]);

        $page->call('endAllSessions', $this->alice->id);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->alice->id)->count());
    }

    public function test_ending_sessions_is_its_own_grant(): void
    {
        $this->liveSession('alice-laptop', $this->alice);

        $role = Role::create(['name' => 'auditor']);
        $role->syncAbilities(['login-activity.view']);
        $auditor = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($auditor)
            ->test(LoginActivityIndex::class)
            ->assertSee('Chrome on Windows')
            ->assertDontSee(__('Sign out everywhere'))
            ->call('endSession', hash('sha256', 'alice-laptop'))
            ->assertForbidden();

        $this->assertSame(1, DB::table('sessions')->count());
    }

    public function test_an_unknown_handle_is_a_404(): void
    {
        Livewire::actingAs($this->admin)->test(LoginActivityIndex::class)->call('endSession', str_repeat('a', 64))->assertNotFound();
    }
}
