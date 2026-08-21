<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\ModuleAccess;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionResolverTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionResolver $resolver;

    protected Project $project;

    protected JobSite $jobSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->resolver = app(PermissionResolver::class);

        $owner = $this->user('admin');
        $client = Client::create([
            'company_name' => 'Resolver Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $owner->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Resolver Project',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $owner->id,
        ]);

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Site One',
            'contact_person' => 'Contact',
            'email' => 'site@example.test',
            'created_by' => $owner->id,
        ]);
    }

    protected function tearDown(): void
    {
        AbilityCatalog::flush();

        parent::tearDown();
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    /** Mark an area as having had its permission pass, for this test only. */
    protected function sweep(string ...$areas): void
    {
        foreach ($areas as $area) {
            config()->set("permissions.areas.{$area}.swept", true);
        }

        AbilityCatalog::flush();
        $this->resolver->flush();
    }

    protected function member(User $user, Project|JobSite $scope, array $abilities, array $attributes = []): Membership
    {
        $membership = Membership::create(array_merge([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
        ], $attributes));

        $membership->syncAbilities($abilities);
        $this->resolver->flush();

        return $membership;
    }

    /*
    |---------------------------------------------------------------------------
    | The legacy bridge
    |---------------------------------------------------------------------------
    */

    public function test_an_unswept_area_answers_from_the_role_for_company_wide_users(): void
    {
        $employee = $this->user('employee');

        // daily-reports is unswept, and the employee role holds
        // daily-reports.create today. (expenses was the example until M4 swept
        // it, income until M5, requisitions until M7, quotations until M8,
        // documents until M12.)
        $this->assertFalse(AbilityCatalog::isSwept('daily-reports.create'));
        $this->assertTrue($this->resolver->allows($employee, 'daily-reports.create', $this->project));
    }

    public function test_an_unswept_area_denies_a_confined_user_outright(): void
    {
        $employee = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $this->member($employee, $this->project, ['daily-reports.view', 'daily-reports.create']);

        // Confined, and the area has not had its pass: denied even though the
        // membership grants it. This is what stops a half-converted module
        // leaking to somebody who is supposed to be confined.
        $this->assertFalse($this->resolver->allows($employee, 'daily-reports.create', $this->project));
    }

    public function test_a_guest_is_denied_by_an_unswept_area_whatever_the_column_says(): void
    {
        $guest = $this->user('employee', ['is_guest' => true]);

        $this->assertTrue($guest->isConfined());
        $this->assertFalse($this->resolver->allows($guest, 'documents.view', $this->project));
    }

    public function test_sweeping_an_area_brings_the_membership_to_life(): void
    {
        $employee = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $this->member($employee, $this->project, ['expenses.view', 'expenses.create']);

        $this->sweep('expenses');

        $this->assertTrue($this->resolver->allows($employee, 'expenses.create', $this->project));
        $this->assertFalse($this->resolver->allows($employee, 'expenses.edit', $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | Scope resolution
    |---------------------------------------------------------------------------
    */

    public function test_a_project_membership_cascades_to_its_job_sites(): void
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $this->member($user, $this->project, ['daily-reports.view', 'daily-reports.create']);

        $this->sweep('daily-reports');

        $this->assertTrue($this->resolver->allows($user, 'daily-reports.create', $this->jobSite));
    }

    public function test_a_job_site_membership_overrides_the_project_one_for_that_site(): void
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $this->member($user, $this->project, ['expenses.view', 'expenses.create', 'expenses.edit']);
        $this->member($user, $this->jobSite, ['expenses.view']);

        $this->sweep('expenses');

        // On the site, the site's narrower list wins — specific beats general.
        $this->assertTrue($this->resolver->allows($user, 'expenses.view', $this->jobSite));
        $this->assertFalse($this->resolver->allows($user, 'expenses.create', $this->jobSite));

        // On the project itself, the project membership still applies.
        $this->assertTrue($this->resolver->allows($user, 'expenses.create', $this->project));
    }

    public function test_a_confined_user_reaches_nothing_on_a_project_they_are_not_on(): void
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $other = Project::create([
            'project_name' => 'Someone Elses',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $user->id,
        ]);

        $this->member($user, $this->project, ['expenses.view']);
        $this->sweep('expenses');

        $this->assertTrue($this->resolver->allows($user, 'expenses.view', $this->project));
        $this->assertFalse($this->resolver->allows($user, 'expenses.view', $other));

        // Asked with no record in hand — "may they do this anywhere?", which
        // is what a menu entry or an index has to know — the answer is yes,
        // because one of their memberships says so. It is the *scoped*
        // question above that confines them.
        $this->assertTrue($this->resolver->allows($user, 'expenses.view'));
        $this->assertFalse($this->resolver->allows($user, 'expenses.delete'));
    }

    public function test_a_membership_replaces_the_role_on_the_project_it_covers(): void
    {
        $employee = $this->user('employee');   // company-wide
        $this->sweep('requisitions', 'expenses');

        // The role gives expenses everywhere and no approvals anywhere.
        $this->assertTrue($this->resolver->allows($employee, 'expenses.create', $this->project));
        $this->assertFalse($this->resolver->allows($employee, 'requisitions.approve', $this->project));

        // On this one project they are something else entirely.
        $this->member($employee, $this->project, ['requisitions.approve']);

        $this->assertTrue($this->resolver->allows($employee, 'requisitions.approve', $this->project));

        // …and the membership REPLACES the role here rather than adding to it:
        // being given a job on a project means being that on that project.
        $this->assertFalse($this->resolver->allows($employee, 'expenses.create', $this->project));

        // Everywhere else the role still applies, untouched.
        $other = Project::create([
            'project_name' => 'Untouched',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'untouched@example.test',
            'created_by' => $employee->id,
        ]);

        $this->assertTrue($this->resolver->allows($employee, 'expenses.create', $other));
        $this->assertFalse($this->resolver->allows($employee, 'requisitions.approve', $other));
    }

    public function test_confinement_does_not_touch_the_company_wide_screens(): void
    {
        // The complaint that produced this rule: choosing "only the projects
        // they are added to" emptied the whole left menu, including things
        // that have no project at all.
        $confined = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $this->assertTrue($this->resolver->allows($confined, 'dashboard.view'));
        $this->assertTrue($this->resolver->allows($confined, 'catalog.view'));
        $this->assertTrue($this->resolver->allows($confined, 'documentation.view'));

        // What they lose is projects they are not on.
        $this->assertFalse($this->resolver->allows($confined, 'expenses.view', $this->project));
    }

    public function test_a_guest_never_holds_a_company_wide_ability(): void
    {
        // Invitations give a guest no role at all; this is the second lock.
        $guest = $this->user('employee', ['is_guest' => true]);

        $this->assertFalse($this->resolver->allows($guest, 'dashboard.view'));
        $this->assertFalse($this->resolver->allows($guest, 'catalog.view'));
        $this->assertFalse($this->resolver->allows($guest, 'projects.view'));
    }

    public function test_a_suspended_membership_grants_nothing(): void
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $membership = $this->member($user, $this->project, ['expenses.view']);
        $this->sweep('expenses');

        $this->assertTrue($this->resolver->allows($user, 'expenses.view', $this->project));

        $membership->update(['status' => MembershipStatus::SUSPENDED]);
        $this->resolver->flush();

        $this->assertFalse($this->resolver->allows($user, 'expenses.view', $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | The checks that come before everything else
    |---------------------------------------------------------------------------
    */

    public function test_an_administrator_is_allowed_everything_that_is_switched_on(): void
    {
        $admin = $this->user('admin');

        $this->assertTrue($this->resolver->allows($admin, 'expenses.edit_paid', $this->project));
        $this->assertTrue($this->resolver->allows($admin, 'access.manage'));
        $this->assertTrue($this->resolver->canSeeMoney($admin, $this->project));
        $this->assertNull($this->resolver->approvalLimit($admin, $this->project));
    }

    public function test_a_switched_off_module_beats_every_permission_including_an_admins(): void
    {
        $admin = $this->user('admin');
        $this->assertTrue($this->resolver->allows($admin, 'estimates.view'));

        ModuleAccess::create([
            'module_key' => 'estimates',
            'module_name' => 'Estimates',
            'is_enabled' => false,
            'is_core' => false,
            'created_by' => $admin->id,
        ]);
        ModuleAccess::clearCache('estimates');
        $this->resolver->flush();

        $this->assertFalse($this->resolver->allows($admin, 'estimates.view'));
    }

    public function test_an_inactive_user_holds_nothing(): void
    {
        $admin = $this->user('admin', ['status' => UserStatus::INACTIVE]);

        $this->assertFalse($this->resolver->allows($admin, 'expenses.view', $this->project));
        $this->assertFalse($this->resolver->canSeeMoney($admin, $this->project));
    }

    public function test_nobody_is_allowed_an_ability_that_does_not_exist(): void
    {
        $this->assertFalse($this->resolver->allows($this->user('employee'), 'nonsense.action'));
        $this->assertFalse($this->resolver->allows(null, 'expenses.view'));
    }

    /*
    |---------------------------------------------------------------------------
    | Money and approval limits
    |---------------------------------------------------------------------------
    */

    public function test_money_visibility_follows_the_membership_where_there_is_one(): void
    {
        $employee = $this->user('employee');   // company-wide, role sees money

        $this->assertTrue($this->resolver->canSeeMoney($employee, $this->project));

        $this->member($employee, $this->jobSite, ['expenses.view'], ['can_see_money' => false]);

        // The membership is the one deliberate subtraction in the model.
        $this->assertFalse($this->resolver->canSeeMoney($employee, $this->jobSite));
        $this->assertTrue($this->resolver->canSeeMoney($employee, $this->project));
    }

    public function test_a_confined_user_without_a_membership_sees_no_money(): void
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $this->assertFalse($this->resolver->canSeeMoney($user));
        $this->assertFalse($this->resolver->canSeeMoney($user, $this->project));
    }

    public function test_the_approval_limit_caps_a_limited_action(): void
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $this->member($user, $this->project, ['requisitions.approve'], ['approval_limit' => 50_000_00]);

        $this->sweep('requisitions');

        $this->assertTrue($this->resolver->allows($user, 'requisitions.approve', $this->project));
        $this->assertSame(50_000_00, $this->resolver->approvalLimit($user, $this->project));
        $this->assertTrue($this->resolver->withinApprovalLimit($user, 49_999_00, $this->project));
        $this->assertTrue($this->resolver->withinApprovalLimit($user, 50_000_00, $this->project));
        $this->assertFalse($this->resolver->withinApprovalLimit($user, 50_000_01, $this->project));

        // No ceiling set means no ceiling.
        $other = $this->user('manager');
        $this->assertTrue($this->resolver->withinApprovalLimit($other, 999_999_00, $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | The Gate says the same thing
    |---------------------------------------------------------------------------
    */

    public function test_the_gate_routes_catalogue_abilities_through_the_resolver(): void
    {
        $employee = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $this->member($employee, $this->project, ['expenses.view']);
        $this->sweep('expenses');

        $this->assertTrue($employee->can('expenses.view', $this->project));
        $this->assertFalse($employee->can('expenses.delete', $this->project));

        $admin = $this->user('admin');
        $this->assertTrue($admin->can('expenses.delete', $this->project));
    }

    public function test_the_template_seeds_behave_as_the_templates_describe_them(): void
    {
        $this->sweep('expenses', 'budget', 'daily-reports', 'requisitions');

        $supervisor = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $template = PermissionTemplate::where('key', 'site-supervisor')->first();

        $this->member($supervisor, $this->jobSite, $template->abilities(), [
            'permission_template_id' => $template->id,
            'can_see_money' => $template->can_see_money,
        ]);

        // Files the daily report, keys in expenses, raises requisitions…
        $this->assertTrue($this->resolver->allows($supervisor, 'daily-reports.create', $this->jobSite));
        $this->assertTrue($this->resolver->allows($supervisor, 'expenses.create', $this->jobSite));
        $this->assertTrue($this->resolver->allows($supervisor, 'requisitions.submit', $this->jobSite));

        // …but does not approve them, does not open the budget, and sees no money.
        $this->assertFalse($this->resolver->allows($supervisor, 'requisitions.approve', $this->jobSite));
        $this->assertFalse($this->resolver->allows($supervisor, 'budget.view', $this->jobSite));
        $this->assertFalse($this->resolver->canSeeMoney($supervisor, $this->jobSite));
    }
}
