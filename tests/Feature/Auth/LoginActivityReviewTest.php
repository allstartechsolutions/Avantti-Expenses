<?php

namespace Tests\Feature\Auth;

use App\Livewire\LoginActivity\LoginActivityIndex;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Services\UserAgent;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** The five findings of the Codex review of login activity (5 Nov 2026), pinned. */
class LoginActivityReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->withoutTwoFactor()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    /** P1: a grant taken away while the page is open stops the next request. */
    public function test_a_revoked_reader_cannot_keep_filtering(): void
    {
        $role = Role::create(['name' => 'auditor']);
        $role->syncAbilities(['login-activity.view']);
        $auditor = User::factory()->create(['role_id' => $role->id]);

        $page = Livewire::actingAs($auditor)->test(LoginActivityIndex::class)->assertOk();

        $role->syncAbilities([]);
        app(PermissionResolver::class)->flush();

        $page->set('search', '10.0.0.1')->assertForbidden();
    }

    /** P1: a formula typed into the email box or the browser string is exported as text. */
    public function test_the_csv_neutralises_spreadsheet_formulas(): void
    {
        LoginEvent::create([
            'email' => '=HYPERLINK("http://evil.test","click")',
            'event' => LoginEvent::FAILED,
            'ip_address' => '192.0.2.66',
            'user_agent' => '@SUM(1+1)',
            'created_at' => now(),
        ]);

        $download = Livewire::actingAs($this->admin)->test(LoginActivityIndex::class)->call('exportCsv')->effects['download'] ?? null;
        $csv = base64_decode($download['content'] ?? '');

        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil.test\"\",\"\"click\"\")\"", $csv);
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    /** P2: signing out your own other sessions also cancels "remember me" on them. */
    public function test_ending_your_own_other_sessions_rotates_your_remember_token(): void
    {
        $this->admin->forceFill(['remember_token' => 'old-token'])->save();
        DB::table('sessions')->insert([
            'id' => 'admin-other-laptop', 'user_id' => $this->admin->id, 'ip_address' => '198.51.100.7',
            'user_agent' => 'Mozilla/5.0 Chrome/130.0', 'payload' => base64_encode(serialize([])), 'last_activity' => now()->getTimestamp(),
        ]);

        Livewire::actingAs($this->admin)->test(LoginActivityIndex::class)->call('endAllSessions', $this->admin->id);

        $this->assertSame(0, DB::table('sessions')->where('id', 'admin-other-laptop')->count());
        $this->assertNotSame('old-token', $this->admin->fresh()->remember_token);
    }

    /** P2: ticking "remember me" on a password sign-in is not a sign-in by the cookie. */
    public function test_a_password_sign_in_with_remember_ticked_is_not_via_remember(): void
    {
        $user = User::factory()->withoutTwoFactor()->create(['password' => Hash::make('secret-pass')]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-pass', 'remember' => 'on'])->assertRedirect();

        $this->assertFalse(LoginEvent::where('user_id', $user->id)->sole()->via_remember);
    }

    /** P2: one IPv6 /48 is one network, however the address is written. */
    public function test_ipv6_networks_compare_by_their_first_48_bits(): void
    {
        $this->assertSame(UserAgent::network('2001:db8::1'), UserAgent::network('2001:db8:0:1::1'));
        $this->assertNotSame(UserAgent::network('2001:db8::1'), UserAgent::network('2001:db9::1'));
        $this->assertSame(
            UserAgent::fingerprint('Chrome', 'macOS', '2001:db8::1'),
            UserAgent::fingerprint('Chrome', 'macOS', '2001:0db8:0000:00ff::42'),
        );
    }
}
