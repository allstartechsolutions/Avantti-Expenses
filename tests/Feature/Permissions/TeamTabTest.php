<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Livewire\JobSite\JobSiteTeam;
use App\Livewire\Project\ProjectTeam;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamTabTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected JobSite $jobSite;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $client = Client::create([
            'company_name' => 'Team Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Team Project',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'North Site',
            'contact_person' => 'Contact',
            'email' => 'site@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | Reaching the tab
    |---------------------------------------------------------------------------
    */

    public function test_the_tab_is_guarded_by_the_ability_on_that_record(): void
    {
        $this->actingAs($this->admin)->get(route('projects.team', $this->project))->assertOk();
        $this->actingAs($this->admin)->get(route('jobsites.team', $this->jobSite))->assertOk();

        // team.* is admin-only until somebody grants it: it is new, so it has
        // no counterpart in the old rules.
        $this->actingAs($this->user('manager'))->get(route('projects.team', $this->project))->assertForbidden();
        $this->actingAs($this->user('employee'))->get(route('jobsites.team', $this->jobSite))->assertForbidden();
    }

    public function test_the_empty_state_explains_what_a_member_is(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.team', $this->project))
            ->assertOk()
            ->assertSee(__('Nobody has been added to :name yet.', ['name' => $this->project->project_name]));
    }

    /*
    |---------------------------------------------------------------------------
    | Adding somebody
    |---------------------------------------------------------------------------
    */

    public function test_a_person_can_be_added_from_a_template(): void
    {
        $engineer = $this->user('employee');
        $template = PermissionTemplate::where('key', 'procurement')->first();

        Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('addMember')
            ->set('userId', (string) $engineer->id)
            ->set('templateId', $template->id)
            ->set('title', 'Engenheiro residente')
            ->call('saveMember')
            ->assertHasNoErrors();

        $membership = Membership::where('user_id', $engineer->id)->first();

        $this->assertNotNull($membership);
        $this->assertSame(Project::class, $membership->scopeable_type);
        $this->assertSame($this->project->id, $membership->scopeable_id);
        $this->assertSame(MembershipStatus::ACTIVE, $membership->status);
        $this->assertSame('Engenheiro residente', $membership->title);
        $this->assertSame($this->admin->id, $membership->invited_by);
        $this->assertEqualsCanonicalizing($template->abilities(), $membership->abilities());
        $this->assertSame('Procurement', $membership->accessLabel());

        $audit = PermissionAudit::where('subject_type', 'membership')->latest('id')->first();
        $this->assertSame($engineer->id, $audit->subject_user_id);
        $this->assertSame(Project::class, $audit->scopeable_type);
    }

    public function test_a_template_can_be_adjusted_before_saving(): void
    {
        $engineer = $this->user('employee');
        $template = PermissionTemplate::where('key', 'site-supervisor')->first();

        Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->call('addMember')
            ->set('userId', (string) $engineer->id)
            ->set('templateId', $template->id)
            ->set('granted.budget.view', true)
            ->set('canSeeMoney', true)
            ->call('saveMember')
            ->assertHasNoErrors();

        $membership = Membership::where('user_id', $engineer->id)->first();

        $this->assertContains('budget.view', $membership->abilities());
        $this->assertTrue($membership->can_see_money);
        $this->assertSame(__('Custom (based on :template)', ['template' => 'Site Supervisor']), $membership->accessLabel());
    }

    public function test_only_abilities_valid_at_that_level_are_saved(): void
    {
        $engineer = $this->user('employee');

        Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->call('addMember')
            ->set('userId', (string) $engineer->id)
            ->set('granted.expenses.view', true)
            // Company-wide areas cannot be held on a job site, however the
            // browser asks.
            ->set('granted.users.view', true)
            ->set('granted.estimates.view', true)
            ->set('granted.nonsense.action', true)
            ->call('saveMember');

        $membership = Membership::where('user_id', $engineer->id)->first();

        $this->assertSame(['expenses.view'], $membership->abilities());
    }

    public function test_adding_the_same_person_twice_updates_rather_than_duplicates(): void
    {
        $engineer = $this->user('employee');

        foreach (['expenses.view', 'documents.view'] as $ability) {
            Livewire::actingAs($this->admin)
                ->test(ProjectTeam::class, ['project' => $this->project])
                ->call('addMember')
                ->set('userId', (string) $engineer->id)
                ->set('granted.'.$ability, true)
                ->call('saveMember');
        }

        $this->assertSame(1, Membership::where('user_id', $engineer->id)->count());
    }

    public function test_the_project_editor_offers_the_projects_own_modules_and_nothing_else(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('addMember');

        $sections = $component->get('matrix');
        $tabbed = collect($sections)->firstWhere('key', 'tabs');

        // The rows are the project's tabs, in the order the tab bar uses them.
        $this->assertSame(
            ['project', 'expenses', 'income', 'requisitions', 'quotations', 'purchase-orders',
                'change-orders', 'contracts', 'documents', 'tasks', 'daily-reports', 'budget',
                'project-report', 'team'],
            array_column($tabbed['areas'], 'key'),
        );

        // Nothing company-wide is on offer: a project has no Users screen, no
        // Estimates, no Settings, and giving somebody those here would be a lie.
        $everything = collect($sections)->flatMap(fn ($section) => array_column($section['areas'], 'key'))->all();

        foreach (['users', 'access', 'settings', 'estimates', 'invoices', 'clients', 'vendors',
            'catalog', 'cost-codes', 'payments', 'reports', 'dashboard', 'company', 'projects',
            'documentation'] as $companyWide) {
            $this->assertNotContains($companyWide, $everything, "'{$companyWide}' must not be offered on a project.");
        }
    }

    public function test_the_job_site_editor_uses_the_job_sites_own_tab_order(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->call('addMember');

        $tabbed = collect($component->get('matrix'))->firstWhere('key', 'tabs');

        // The two bars are ordered differently today, and each editor follows
        // its own: Change Orders and Contracts come before Requisitions here.
        $this->assertSame(
            ['project', 'expenses', 'income', 'change-orders', 'contracts', 'requisitions',
                'quotations', 'purchase-orders', 'documents', 'tasks', 'daily-reports',
                'budget', 'project-report', 'team'],
            array_column($tabbed['areas'], 'key'),
        );

        // A job site has no Job Sites tab of its own.
        $this->assertNotContains('jobsites', array_column($tabbed['areas'], 'key'));
    }

    public function test_anything_scoped_but_not_a_tab_is_kept_apart_and_explained(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('addMember');

        $related = collect($component->get('matrix'))->firstWhere('key', 'related');

        // Minutes are scoped to a project but are not one of its tabs, so they
        // sit in their own section rather than unexplained among the tabs.
        $this->assertNotNull($related);
        $this->assertContains('meetings', array_column($related['areas'], 'key'));
    }

    public function test_the_editor_does_not_borrow_the_company_wide_wording(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('addMember');

        $hints = collect($component->get('matrix'))->pluck('hint')->implode(' ');

        $this->assertStringContainsString(__('One row per tab of this project.'), $hints);
        // The role editor's line — telling somebody standing on a Team tab to
        // go to the Team tab — must not appear here.
        $this->assertStringNotContainsString('apply on every project', $hints);
    }

    /*
    |---------------------------------------------------------------------------
    | What the membership then does
    |---------------------------------------------------------------------------
    */

    public function test_the_access_given_is_the_access_the_resolver_uses(): void
    {
        $supervisor = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $template = PermissionTemplate::where('key', 'site-supervisor')->first();

        Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->call('addMember')
            ->set('userId', (string) $supervisor->id)
            ->set('templateId', $template->id)
            ->call('saveMember');

        // Sweep the areas this template touches, as their passes will.
        foreach (['expenses', 'daily-reports', 'budget'] as $area) {
            config()->set("permissions.areas.{$area}.swept", true);
        }
        AbilityCatalog::flush();
        app(PermissionResolver::class)->flush();

        $resolver = app(PermissionResolver::class);

        $this->assertTrue($resolver->allows($supervisor, 'expenses.create', $this->jobSite));
        $this->assertTrue($resolver->allows($supervisor, 'daily-reports.create', $this->jobSite));
        $this->assertFalse($resolver->allows($supervisor, 'budget.view', $this->jobSite));
        $this->assertFalse($resolver->canSeeMoney($supervisor, $this->jobSite));

        // …and nothing on the project itself.
        $this->assertFalse($resolver->allows($supervisor, 'expenses.create', $this->project));

        AbilityCatalog::flush();
    }

    public function test_suspending_and_restoring_a_member(): void
    {
        $engineer = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $engineer->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(['expenses.view']);

        config()->set('permissions.areas.expenses.swept', true);
        AbilityCatalog::flush();

        $component = Livewire::actingAs($this->admin)->test(ProjectTeam::class, ['project' => $this->project]);

        $component->call('suspendMember', $membership->id);
        $this->assertSame(MembershipStatus::SUSPENDED, $membership->fresh()->status);
        $this->assertFalse(app(PermissionResolver::class)->allows($engineer, 'expenses.view', $this->project));

        $component->call('suspendMember', $membership->id);
        $this->assertSame(MembershipStatus::ACTIVE, $membership->fresh()->status);
        app(PermissionResolver::class)->flush();
        $this->assertTrue(app(PermissionResolver::class)->allows($engineer, 'expenses.view', $this->project));

        AbilityCatalog::flush();
    }

    public function test_removing_a_member_keeps_the_audit_trail(): void
    {
        $engineer = $this->user('employee');

        $membership = Membership::create([
            'user_id' => $engineer->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
        ]);
        $membership->syncAbilities(['expenses.view']);

        Livewire::actingAs($this->admin)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('removeMember', $membership->id);

        $this->assertNull(Membership::find($membership->id));

        $audit = PermissionAudit::where('action', 'removed')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($engineer->id, $audit->subject_user_id);
        $this->assertContains('expenses.view', $audit->before['abilities']);
    }

    /*
    |---------------------------------------------------------------------------
    | The cascade, on screen
    |---------------------------------------------------------------------------
    */

    public function test_the_job_site_tab_shows_who_reaches_it_through_the_project(): void
    {
        $engineer = $this->user('employee');

        $membership = Membership::create([
            'user_id' => $engineer->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
        ]);
        $membership->syncAbilities(['expenses.view', 'documents.view']);

        Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->assertSee(__('From the project'))
            ->assertSee($engineer->name);
    }

    public function test_giving_a_site_its_own_access_starts_from_what_is_inherited(): void
    {
        $engineer = $this->user('employee');

        $projectMembership = Membership::create([
            'user_id' => $engineer->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'can_see_money' => false,
        ]);
        $projectMembership->syncAbilities(['expenses.view', 'expenses.create', 'documents.view']);

        Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->call('overrideInherited', $projectMembership->id)
            ->assertSet('userId', (string) $engineer->id)
            ->assertSet('canSeeMoney', false)
            ->assertSet('granted.expenses.create', true)
            // narrowed for this site
            ->set('granted.expenses.create', false)
            ->call('saveMember');

        $siteMembership = Membership::where('user_id', $engineer->id)
            ->where('scopeable_type', JobSite::class)
            ->first();

        $this->assertNotNull($siteMembership);
        $this->assertEqualsCanonicalizing(['expenses.view', 'documents.view'], $siteMembership->abilities());

        // Once a site membership exists, the person drops off the inherited list.
        Livewire::actingAs($this->admin)
            ->test(JobSiteTeam::class, ['jobSite' => $this->jobSite])
            ->assertDontSee(__('From the project'));
    }

    /*
    |---------------------------------------------------------------------------
    | Guards
    |---------------------------------------------------------------------------
    */

    public function test_one_team_tab_cannot_reach_another_projects_membership(): void
    {
        $other = Project::create([
            'project_name' => 'Someone Elses',
            'client_id' => $this->project->client_id,
            'contact_person' => 'Contact',
            'email' => 'other@example.test',
            'created_by' => $this->admin->id,
        ]);

        $membership = Membership::create([
            'user_id' => $this->user('employee')->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $other->id,
        ]);

        // findMembership() scopes every lookup to this tab's own record, so
        // the id simply does not exist here.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            Livewire::actingAs($this->admin)
                ->test(ProjectTeam::class, ['project' => $this->project])
                ->call('removeMember', $membership->id);
        } finally {
            $this->assertNotNull(Membership::find($membership->id));
        }
    }

    public function test_somebody_who_may_only_view_the_team_cannot_change_it(): void
    {
        // A membership that grants team.view but not team.manage, once the
        // area is swept.
        config()->set('permissions.areas.team.swept', true);
        AbilityCatalog::flush();

        $viewer = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $viewer->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
        ]);
        $membership->syncAbilities(['team.view']);

        app(PermissionResolver::class)->flush();

        $this->actingAs($viewer)->get(route('projects.team', $this->project))->assertOk();

        Livewire::actingAs($viewer)
            ->test(ProjectTeam::class, ['project' => $this->project])
            ->call('addMember')
            ->assertForbidden();

        AbilityCatalog::flush();
    }
}
