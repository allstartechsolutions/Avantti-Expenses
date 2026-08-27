<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Livewire\Approval\ApprovalForm;
use App\Livewire\Approval\ApprovalShow;
use App\Livewire\Project\ProjectApprovals;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Collaboration\ResponseCode;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\CollaborationResponseCodeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The approvals module's permission pass, in the four groups
 * docs/permissions-for-new-modules.md §6 asks for.
 */
class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();
        $this->seed(CollaborationResponseCodeSeeder::class);

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->project = $this->makeProject('Obra Central');
        $this->otherProject = $this->makeProject('Obra Norte');
    }

    protected function makeProject(string $name): Project
    {
        $client = Client::create([
            'company_name' => $name.' Client',
            'contact_name' => 'Contact',
            'email' => str($name)->slug().'@example.test',
            'created_by' => $this->admin->id,
        ]);

        return Project::create([
            'project_name' => $name,
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => str($name)->slug().'-p@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function approval(?Project $project = null, array $attributes = []): Approval
    {
        return Approval::create(array_merge([
            'project_id' => ($project ?? $this->project)->id,
            'title' => 'Porcelanato do hall',
            'type' => Approval::TYPE_MATERIAL,
            'created_by_id' => $this->admin->id,
        ], $attributes));
    }

    protected function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id')]);
    }

    protected function member(Project $project, array $abilities, bool $isGuest = false): User
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
            'is_guest' => $isGuest,
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $project->id,
            'can_see_money' => false,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $this->admin->id,
            'accepted_at' => now(),
        ]);

        $membership->syncAbilities(AbilityCatalog::filter($abilities, 'project'));

        return $user;
    }

    protected function code(string $canonical): ResponseCode
    {
        return ResponseCode::offered('approval')->firstWhere('canonical', $canonical);
    }

    /*
    |---------------------------------------------------------------------------
    | 1. REPRODUCED — the seeded roles land where the hold-back lists say
    |---------------------------------------------------------------------------
    */

    public function test_the_seeded_roles_hold_what_the_hold_back_lists_say(): void
    {
        $resolver = app(PermissionResolver::class);
        $manager = $this->user('manager');
        $employee = $this->user('employee');

        $this->assertFalse($resolver->allows($manager, 'approvals.delete'));
        $this->assertFalse($resolver->allows($employee, 'approvals.delete'));

        foreach (['approvals.respond', 'approvals.seed', 'approvals.manage_packages', 'approvals.distribute'] as $ability) {
            $this->assertTrue($resolver->allows($manager, $ability), $ability);
            $this->assertFalse($resolver->allows($employee, $ability), $ability);
        }

        foreach (['approvals.view', 'approvals.create', 'approvals.edit', 'approvals.submit'] as $ability) {
            $this->assertTrue($resolver->allows($manager, $ability), $ability);
            $this->assertTrue($resolver->allows($employee, $ability), $ability);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | 2. REVOCABLE
    |---------------------------------------------------------------------------
    */

    public function test_the_area_can_be_taken_away(): void
    {
        $role = Role::create(['name' => 'no-approvals-'.uniqid()]);
        $role->syncAbilities(['projects.view', 'project.view']);

        $stranger = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($stranger)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('projects.approvals', $this->project))
            ->assertForbidden();
    }

    public function test_revoking_a_membership_closes_the_door_at_once(): void
    {
        $member = $this->member($this->project, ['project.view', 'approvals.view']);
        $approval = $this->approval();

        Livewire::actingAs($member)->test(ApprovalShow::class, ['approval' => $approval])->assertOk();

        $member->memberships()->first()->update([
            'status' => MembershipStatus::SUSPENDED,
            'revoked_at' => now(),
        ]);
        app(PermissionResolver::class)->flush();

        Livewire::actingAs($member)->test(ApprovalShow::class, ['approval' => $approval])->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | 3. SCOPED
    |---------------------------------------------------------------------------
    */

    public function test_an_approval_on_another_project_is_not_reachable_by_its_id(): void
    {
        $member = $this->member($this->project, ['project.view', 'approvals.view', 'approvals.edit']);
        $theirs = $this->approval($this->otherProject);

        Livewire::actingAs($member)->test(ApprovalShow::class, ['approval' => $theirs])->assertForbidden();
        Livewire::actingAs($member)->test(ApprovalForm::class, ['approval' => $theirs])->assertForbidden();
        $this->actingAs($member)->get(route('approvals.show', $theirs))->assertForbidden();
    }

    public function test_another_projects_approvals_are_not_in_the_list_or_its_totals(): void
    {
        $this->approval($this->project, ['title' => 'Ours']);
        $this->approval($this->otherProject, ['title' => 'Theirs']);

        $member = $this->member($this->project, ['project.view', 'approvals.view']);

        Livewire::actingAs($member)
            ->test(ProjectApprovals::class, ['project' => $this->project])
            ->set('approvalStatusFilter', 'all')
            ->assertSee('Ours')
            ->assertDontSee('Theirs');

        $this->assertSame(1, Approval::visibleTo($member)->count());
    }

    /*
    |---------------------------------------------------------------------------
    | 4. SEPARATE
    |---------------------------------------------------------------------------
    */

    public function test_viewing_gives_neither_submitting_nor_responding(): void
    {
        $viewer = $this->member($this->project, ['project.view', 'approvals.view']);
        $approval = $this->approval();

        Livewire::actingAs($viewer)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->assertOk()
            ->set('reviewerRows', [['user_id' => (string) $viewer->id, 'sequence' => 1, 'role' => '']])
            ->call('submitRevision')
            ->assertForbidden();

        $this->assertSame(0, $approval->fresh()->revisions()->count());
    }

    public function test_submitting_does_not_carry_the_right_to_respond(): void
    {
        $submitter = $this->member($this->project, [
            'project.view', 'approvals.view', 'approvals.create', 'approvals.submit',
        ]);
        $approval = $this->approval();

        Livewire::actingAs($submitter)
            ->test(ApprovalShow::class, ['approval' => $approval])
            ->set('reviewerRows', [['user_id' => (string) $submitter->id, 'sequence' => 1, 'role' => '']])
            ->call('submitRevision')
            ->assertHasNoErrors();

        // Named as a reviewer of their own submission, and still refused —
        // the grant is what decides, not being on the list.
        Livewire::actingAs($submitter)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->set('responseCodeId', (string) $this->code(ResponseCode::APPROVED)->id)
            ->call('recordResponse')
            ->assertForbidden();

        $this->assertSame(Approval::IN_REVIEW, $approval->fresh()->status);
    }

    public function test_responding_does_not_carry_the_right_to_edit(): void
    {
        $reviewer = $this->member($this->project, ['project.view', 'approvals.view', 'approvals.respond']);
        $approval = $this->approval();

        Livewire::actingAs($reviewer)
            ->test(ApprovalForm::class, ['approval' => $approval])
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | The guest case
    |---------------------------------------------------------------------------
    */

    /**
     * The projetista reviews from the same screens as everybody else, and sees
     * no money on them.
     */
    public function test_a_guest_reviewer_can_respond_but_sees_no_money(): void
    {
        $template = PermissionTemplate::where('key', 'projetista-project')->firstOrFail();

        $projetista = $this->member(
            $this->project,
            $template->abilityRows->pluck('ability')->all(),
            isGuest: true,
        );

        $approval = $this->approval();
        $approval->submit([['user_id' => $projetista->id]], $this->admin);

        Livewire::actingAs($projetista)
            ->test(ApprovalShow::class, ['approval' => $approval->fresh()])
            ->assertOk()
            ->set('responseCodeId', (string) $this->code(ResponseCode::APPROVED)->id)
            ->set('responseComments', 'Conforme.')
            ->call('recordResponse')
            ->assertHasNoErrors();

        $this->assertSame(Approval::APPROVED, $approval->fresh()->status);
        $this->assertFalse(app(PermissionResolver::class)->canSeeMoney($projetista, $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue
    |---------------------------------------------------------------------------
    */

    public function test_the_area_is_declared_and_swept(): void
    {
        $area = AbilityCatalog::areas()['approvals'];

        $this->assertSame('collaboration', $area['module']);
        $this->assertTrue($area['swept'], 'approvals is enforced now; the flag should say so.');
        $this->assertTrue($area['money'], 'an approval hangs off a budget line');
        $this->assertTrue(AbilityCatalog::isSensitive('approvals.distribute'));
    }

    public function test_an_undeclared_approval_ability_is_refused(): void
    {
        $this->assertFalse(app(PermissionResolver::class)->allows($this->admin, 'approvals.invented'));
    }
}
