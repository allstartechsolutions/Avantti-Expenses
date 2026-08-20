<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\ModuleAccess;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\Navigation;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu each role sees, pinned.
 *
 * These lists were taken from the hand-written sidebar before it was replaced,
 * so they are the proof that generating the menu changed nothing — with the one
 * deliberate exception recorded below: the settings gear, which used to be
 * rendered for everybody and answered 403 for anybody who clicked it.
 *
 * Like LegacyBehaviourTest, this is the regression net for the module passes:
 * a pass that changes who sees a menu entry changes a line here, deliberately.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /** The sidebar, group by group, as each role sees it. */
    protected const SIDEBAR = [
        'admin' => [
            'Dashboard',
            // Roles & Access joined the Company group in E4.
            'Company: Company Info, Users, Roles & Access',
            'Projects: All Projects, Subcontractors, Clients, Cost Codes, Payments, Contract Payments, Payment Batches',
            'Catalog: All Items, Categories, Suppliers',
            'Estimates',
            'Invoices',
            'Meetings: Minutes, My Tasks, Meeting Series',
            'Reports: Sales Tax Report, Accounts Payable, Company Financials, Expense Report, Payment Schedule, Payment Details',
            'Documentation',
        ],
        'manager' => [
            'Dashboard',
            'Company: Company Info',
            'Projects: All Projects, Subcontractors, Clients, Payments, Contract Payments, Payment Batches',
            'Catalog: All Items, Categories, Suppliers',
            'Estimates',
            'Invoices',
            'Meetings: Minutes, My Tasks, Meeting Series',
            'Documentation',
        ],
        'employee' => [
            'Dashboard',
            'Company: Company Info',
            'Projects: All Projects, Subcontractors, Clients, Payments, Contract Payments, Payment Batches',
            'Catalog: All Items, Categories, Suppliers',
            'Estimates',
            'Invoices',
            'Meetings: Minutes, My Tasks',
            'Documentation',
        ],
    ];

    /** The top bar. The gear is the one thing E3 deliberately took away. */
    protected const HEADER = [
        'admin' => ['Settings'],
        'manager' => [],
        'employee' => [],
    ];

    protected Navigation $nav;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->nav = app(Navigation::class);
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

    /** @return array<int, string> */
    protected function describe(array $sidebar): array
    {
        return array_map(
            fn ($entry) => $entry['type'] === 'group'
                ? $entry['name'].': '.implode(', ', array_column($entry['items'], 'name'))
                : $entry['name'],
            $sidebar,
        );
    }

    public function test_each_role_sees_the_menu_it_saw_before(): void
    {
        foreach (self::SIDEBAR as $role => $expected) {
            $this->assertSame(
                $expected,
                $this->describe($this->nav->sidebar($this->user($role))),
                "The sidebar changed for {$role}. If that is deliberate, update the table here.",
            );
        }
    }

    public function test_the_settings_gear_is_only_offered_to_people_who_can_open_it(): void
    {
        foreach (self::HEADER as $role => $expected) {
            $this->assertSame(
                $expected,
                array_column($this->nav->header($this->user($role)), 'name'),
                "The top bar changed for {$role}.",
            );
        }
    }

    public function test_a_switched_off_module_takes_its_entries_with_it(): void
    {
        $admin = $this->user('admin');

        ModuleAccess::create([
            'module_key' => 'estimates',
            'module_name' => 'Estimates',
            'is_enabled' => false,
            'is_core' => false,
            'created_by' => $admin->id,
        ]);
        ModuleAccess::clearCache('estimates');
        app(PermissionResolver::class)->flush();

        $this->assertNotContains('Estimates', $this->describe($this->nav->sidebar($admin)));
    }

    public function test_a_group_with_nothing_left_in_it_is_not_rendered(): void
    {
        // The employee holds no reports ability, so the Reports group is gone
        // rather than being shown as a heading that opens onto nothing.
        $menu = $this->describe($this->nav->sidebar($this->user('employee')));

        foreach ($menu as $line) {
            $this->assertStringNotContainsString('Reports:', $line);
            $this->assertNotSame('Reports', $line);
        }
    }

    public function test_a_guest_sees_no_menu_at_all(): void
    {
        $guest = $this->user('employee', ['is_guest' => true]);

        // Every area is still on the legacy bridge, which denies confined
        // users outright — so a guest gets nothing until the passes are done.
        $this->assertSame([], $this->nav->sidebar($guest));
        $this->assertSame([], $this->nav->header($guest));
    }

    public function test_nobody_signed_in_sees_nothing(): void
    {
        $this->assertSame([], $this->nav->sidebar(null));
    }

    /*
    |---------------------------------------------------------------------------
    | The project and job-site tabs
    |---------------------------------------------------------------------------
    */

    public function test_the_project_and_job_site_tabs_are_unchanged_for_staff(): void
    {
        [$project, $jobSite] = $this->makeProjectAndSite();
        $employee = $this->user('employee');

        $this->assertSame(
            ['overview', 'jobsites', 'expenses', 'income', 'requisitions', 'quotations',
                'purchase-orders', 'change-orders', 'contracts', 'documents', 'tasks',
                'daily-reports', 'budget', 'report'],
            array_column($this->nav->projectTabs($employee, $project), 'key'),
        );

        // The job-site bar has its own order, and no Job Sites tab.
        $this->assertSame(
            ['overview', 'expenses', 'income', 'change-orders', 'contracts', 'requisitions',
                'quotations', 'purchase-orders', 'documents', 'tasks', 'daily-reports',
                'budget', 'report'],
            array_column($this->nav->jobSiteTabs($employee, $jobSite), 'key'),
        );
    }

    public function test_the_team_tab_appears_only_for_people_who_hold_it(): void
    {
        [$project, $jobSite] = $this->makeProjectAndSite();

        // New in M1b. team.* is admin-only until somebody grants it, so staff
        // do not see the tab and the bars above are unchanged for them.
        $this->assertContains('team', array_column($this->nav->projectTabs($this->user('admin'), $project), 'key'));
        $this->assertContains('team', array_column($this->nav->jobSiteTabs($this->user('admin'), $jobSite), 'key'));

        $this->assertNotContains('team', array_column($this->nav->projectTabs($this->user('manager'), $project), 'key'));
        $this->assertNotContains('team', array_column($this->nav->jobSiteTabs($this->user('employee'), $jobSite), 'key'));
    }

    public function test_a_switched_off_module_takes_its_tabs_with_it(): void
    {
        [$project] = $this->makeProjectAndSite();
        $admin = $this->user('admin');

        ModuleAccess::create([
            'module_key' => 'quotations',
            'module_name' => 'Quotations',
            'is_enabled' => false,
            'is_core' => false,
            'created_by' => $admin->id,
        ]);
        ModuleAccess::clearCache('quotations');
        app(PermissionResolver::class)->flush();

        $keys = array_column($this->nav->projectTabs($admin, $project), 'key');

        $this->assertNotContains('requisitions', $keys);
        $this->assertNotContains('quotations', $keys);
        $this->assertContains('expenses', $keys);
    }

    public function test_a_site_supervisor_sees_only_the_tabs_they_hold(): void
    {
        [$project, $jobSite] = $this->makeProjectAndSite();

        // What M4, M7, M12, M13 and M14 will look like once those areas are
        // swept: the supervisor's own membership decides their job-site tabs.
        foreach (['expenses', 'requisitions', 'daily-reports', 'documents', 'tasks', 'budget', 'income'] as $area) {
            config()->set("permissions.areas.{$area}.swept", true);
        }
        AbilityCatalog::flush();

        $supervisor = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);
        $template = PermissionTemplate::where('key', 'site-supervisor')->first();

        $membership = Membership::create([
            'user_id' => $supervisor->id,
            'scopeable_type' => JobSite::class,
            'scopeable_id' => $jobSite->id,
            'permission_template_id' => $template->id,
            'can_see_money' => $template->can_see_money,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities($template->abilities());
        app(PermissionResolver::class)->flush();

        $keys = array_column($this->nav->jobSiteTabs($supervisor, $jobSite), 'key');

        $this->assertContains('expenses', $keys);
        $this->assertContains('daily-reports', $keys);
        $this->assertContains('documents', $keys);
        $this->assertNotContains('budget', $keys);
        $this->assertNotContains('income', $keys);
    }

    public function test_the_project_and_job_site_pages_still_render_their_tab_bars(): void
    {
        [$project, $jobSite] = $this->makeProjectAndSite();
        $admin = $this->user('admin');

        $projectPage = $this->actingAs($admin)->get(route('projects.overview', $project));
        $projectPage->assertOk();
        $projectPage->assertSee(route('projects.expenses', $project));
        $projectPage->assertSee(route('projects.budget', $project));

        $sitePage = $this->actingAs($admin)->get(route('jobsites.overview', $jobSite));
        $sitePage->assertOk();
        $sitePage->assertSee(route('jobsites.expenses', $jobSite));
        $sitePage->assertSee(route('jobsites.daily-reports', $jobSite));
    }

    /** @return array{0: Project, 1: JobSite} */
    protected function makeProjectAndSite(): array
    {
        $owner = $this->user('admin');

        $client = Client::create([
            'company_name' => 'Nav Client',
            'contact_name' => 'Contact',
            'email' => 'client@example.test',
            'created_by' => $owner->id,
        ]);

        $project = Project::create([
            'project_name' => 'Nav Project',
            'client_id' => $client->id,
            'contact_person' => 'Contact',
            'email' => 'project@example.test',
            'created_by' => $owner->id,
        ]);

        $jobSite = JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => 'Nav Site',
            'contact_person' => 'Contact',
            'email' => 'site@example.test',
            'created_by' => $owner->id,
        ]);

        return [$project, $jobSite];
    }
}
