<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\JobSite\JobSiteTeam;
use App\Livewire\Project\ProjectTeam;
use App\Mail\InvitationMail;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $jobSite;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $client = Client::create([
            'company_name' => 'Invite Client', 'contact_name' => 'C',
            'email' => 'c@example.test', 'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Invite Project', 'client_id' => $client->id,
            'contact_person' => 'C', 'email' => 'p@example.test', 'created_by' => $this->admin->id,
        ]);

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id, 'job_site_name' => 'Invite Site',
            'contact_person' => 'C', 'email' => 's@example.test', 'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Sending
    |---------------------------------------------------------------------------
    */

    public function test_inviting_somebody_writes_no_user_and_sends_one_email(): void
    {
        $template = PermissionTemplate::where('key', 'procurement')->first();

        Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('inviteSomebody', false)
            ->set('inviteEmail', 'New.Person@Example.test')
            ->set('inviteName', 'New Person')
            ->set('templateId', $template->id)
            ->call('saveMember')
            ->assertHasNoErrors();

        $invitation = UserInvitation::first();

        $this->assertNotNull($invitation);
        $this->assertSame('new.person@example.test', $invitation->email, 'The address should be normalised.');
        $this->assertFalse($invitation->is_guest);
        $this->assertSame(AccessScope::ASSIGNED, $invitation->access_scope);
        $this->assertSame($this->admin->id, $invitation->invited_by);

        // Nothing exists in users until they accept.
        $this->assertNull(User::where('email', 'new.person@example.test')->first());
        $this->assertSame(0, Membership::count());

        Mail::assertSent(InvitationMail::class, 1);
        Mail::assertSent(InvitationMail::class, fn ($mail) => $mail->hasTo('new.person@example.test'));
    }

    public function test_the_token_is_never_stored_in_the_clear(): void
    {
        app(\App\Services\InvitationService::class)->invite(
            email: 'token@example.test', name: null, roleId: null, isGuest: false,
        );

        $invitation = UserInvitation::first();

        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $invitation->token_hash);
    }

    public function test_the_invitation_carries_the_abilities_that_were_chosen(): void
    {
        Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->call('inviteSomebody', false)
            ->set('inviteEmail', 'site@example.test')
            ->set('granted.expenses.view', true)
            ->set('granted.daily-reports.create', true)
            // Not holdable on a job site — must not survive.
            ->set('granted.estimates.view', true)
            ->set('approvalLimit', '1000.00')
            ->call('saveMember');

        $payload = UserInvitation::first()->payload[0];

        $this->assertSame(JobSite::class, $payload['scopeable_type']);
        $this->assertSame($this->jobSite->id, $payload['scopeable_id']);
        $this->assertEqualsCanonicalizing(['expenses.view', 'daily-reports.create'], $payload['abilities']);
        $this->assertSame(100_000, $payload['approval_limit']);
    }

    public function test_a_guest_invitation_can_never_carry_money_or_a_sensitive_action(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('inviteSomebody', true)
            ->assertSet('inviteAsGuest', true)
            ->assertSet('canSeeMoney', false)
            ->set('inviteEmail', 'client@example.test')
            ->set('granted.documents.view', true)
            ->set('granted.documents.share', true)   // sensitive
            ->set('canSeeMoney', true)               // and try to force money on
            ->call('saveMember');

        $invitation = UserInvitation::first();
        $payload = $invitation->payload[0];

        $this->assertTrue($invitation->is_guest);
        $this->assertNull($invitation->role_id, 'A guest holds no company-wide role.');
        $this->assertFalse($payload['can_see_money']);
        $this->assertContains('documents.view', $payload['abilities']);
        $this->assertNotContains('documents.share', $payload['abilities']);
    }

    public function test_somebody_who_already_has_a_login_cannot_be_invited_again(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);

        Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('inviteSomebody', false)
            ->set('inviteEmail', 'existing@example.test')
            ->call('saveMember')
            ->assertHasErrors('inviteEmail');
    }

    /*
    |---------------------------------------------------------------------------
    | Accepting
    |---------------------------------------------------------------------------
    */

    protected function inviteToProject(bool $guest = false, array $abilities = ['expenses.view', 'documents.view']): string
    {
        $token = null;

        Mail::assertNothingSent();

        app(\App\Services\InvitationService::class)->invite(
            email: 'invitee@example.test',
            name: 'Invitee',
            roleId: $guest ? null : Role::where('name', 'employee')->value('id'),
            isGuest: $guest,
            membership: [
                'scope' => $this->project,
                'template_id' => null,
                'title' => 'Engenheiro',
                'can_see_money' => ! $guest,
                'approval_limit' => null,
                'abilities' => $abilities,
            ],
            invitedBy: $this->admin->id,
        );

        Mail::assertSent(InvitationMail::class, function ($mail) use (&$token) {
            $token = $mail->token;

            return true;
        });

        return $token;
    }

    public function test_accepting_creates_the_account_and_its_memberships(): void
    {
        $token = $this->inviteToProject();

        Livewire::test(AcceptInvitation::class, ['token' => $token])
            ->assertSet('problem', null)
            ->assertSet('name', 'Invitee')
            ->set('password', 'a-very-good-password')
            ->set('password_confirmation', 'a-very-good-password')
            ->call('accept')
            ->assertHasNoErrors();

        $user = User::where('email', 'invitee@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame(AccessScope::ASSIGNED, $user->access_scope);
        $this->assertFalse($user->is_guest);
        $this->assertTrue($user->isConfined());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-very-good-password', $user->password));

        $membership = $user->memberships()->first();
        $this->assertNotNull($membership);
        $this->assertSame($this->project->id, $membership->scopeable_id);
        $this->assertSame('Engenheiro', $membership->title);
        $this->assertEqualsCanonicalizing(['expenses.view', 'documents.view'], $membership->abilities());

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(UserInvitation::first()->accepted_at);
        $this->assertNotNull(PermissionAudit::where('action', 'accepted')->first());
    }

    public function test_an_accepted_invitation_cannot_be_used_twice(): void
    {
        $token = $this->inviteToProject();

        Livewire::test(AcceptInvitation::class, ['token' => $token])
            ->set('password', 'a-very-good-password')
            ->set('password_confirmation', 'a-very-good-password')
            ->call('accept');

        Livewire::test(AcceptInvitation::class, ['token' => $token])
            ->assertSet('problem', 'accepted')
            ->call('accept')
            ->assertForbidden();

        $this->assertSame(1, User::where('email', 'invitee@example.test')->count());
    }

    public function test_an_expired_or_withdrawn_invitation_says_which(): void
    {
        $token = $this->inviteToProject();
        UserInvitation::first()->update(['expires_at' => now()->subDay()]);

        Livewire::test(AcceptInvitation::class, ['token' => $token])->assertSet('problem', 'expired');

        UserInvitation::first()->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);

        Livewire::test(AcceptInvitation::class, ['token' => $token])->assertSet('problem', 'revoked');
    }

    public function test_a_made_up_token_is_refused(): void
    {
        Livewire::test(AcceptInvitation::class, ['token' => 'not-a-real-token'])
            ->assertSet('problem', 'unknown')
            ->assertSet('invitation', null);

        $this->get(route('invitations.accept', 'not-a-real-token'))
            ->assertOk()
            ->assertSee(__('This link is not valid.'));
    }

    public function test_resending_replaces_the_previous_link(): void
    {
        $first = $this->inviteToProject();

        Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('resendInvitation', UserInvitation::first()->id);

        $this->assertSame(2, UserInvitation::first()->send_count);

        // The old link is dead.
        Livewire::test(AcceptInvitation::class, ['token' => $first])->assertSet('problem', 'unknown');
    }

    public function test_withdrawing_kills_the_link_immediately(): void
    {
        $token = $this->inviteToProject();

        Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('withdrawInvitation', UserInvitation::first()->id);

        Livewire::test(AcceptInvitation::class, ['token' => $token])->assertSet('problem', 'revoked');
    }

    public function test_abilities_are_re_checked_at_acceptance_not_trusted_from_the_payload(): void
    {
        // The catalogue can change between sending and accepting.
        $token = $this->inviteToProject(abilities: ['expenses.view', 'made.up.ability']);

        Livewire::test(AcceptInvitation::class, ['token' => $token])
            ->set('password', 'a-very-good-password')
            ->set('password_confirmation', 'a-very-good-password')
            ->call('accept');

        $membership = User::where('email', 'invitee@example.test')->first()->memberships()->first();

        $this->assertSame(['expenses.view'], $membership->abilities());
    }

    /*
    |---------------------------------------------------------------------------
    | What a guest gets
    |---------------------------------------------------------------------------
    */

    public function test_a_guest_lands_on_their_project_and_gets_no_sidebar(): void
    {
        $token = $this->inviteToProject(guest: true, abilities: ['project.view', 'documents.view']);

        Livewire::test(AcceptInvitation::class, ['token' => $token])
            ->set('password', 'a-very-good-password')
            ->set('password_confirmation', 'a-very-good-password')
            ->call('accept')
            ->assertRedirect(route('projects.overview', $this->project));

        $guest = User::where('email', 'invitee@example.test')->first();

        $this->assertTrue($guest->is_guest);
        $this->assertTrue($guest->isConfined());
        $this->assertSame([], app(\App\Services\Navigation::class)->sidebar($guest));

        // The company-wide screens are not theirs, whatever they type.
        $this->actingAs($guest)->get(route('access.index'))->assertForbidden();
        $this->actingAs($guest)->get(route('users.index'))->assertForbidden();
    }

    public function test_a_guests_abilities_are_exactly_what_they_were_given(): void
    {
        $token = $this->inviteToProject(guest: true, abilities: ['project.view', 'documents.view']);

        Livewire::test(AcceptInvitation::class, ['token' => $token])
            ->set('password', 'a-very-good-password')
            ->set('password_confirmation', 'a-very-good-password')
            ->call('accept');

        $guest = User::where('email', 'invitee@example.test')->first();

        config()->set('permissions.areas.documents.swept', true);
        config()->set('permissions.areas.expenses.swept', true);
        AbilityCatalog::flush();
        app(PermissionResolver::class)->flush();

        $resolver = app(PermissionResolver::class);

        $this->assertTrue($resolver->allows($guest, 'documents.view', $this->project));
        $this->assertFalse($resolver->allows($guest, 'documents.create', $this->project));
        $this->assertFalse($resolver->allows($guest, 'expenses.view', $this->project));
        $this->assertFalse($resolver->canSeeMoney($guest, $this->project));

        AbilityCatalog::flush();
    }
}
