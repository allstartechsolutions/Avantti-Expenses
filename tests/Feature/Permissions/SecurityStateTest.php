<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
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

/**
 * What is actually enforced today — the inventory, as assertions.
 *
 * The permission module is deployed one module at a time, so at any moment
 * some of it is live and some of it is still on the legacy bridge. This file
 * says which is which, so nobody has to guess, and so the answer cannot drift
 * without somebody editing it on purpose.
 *
 * **Every module pass moves cases from the second group to the first.**
 */
class SecurityStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $employee;

    protected User $confined;

    protected Project $project;

    protected JobSite $jobSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->employee = User::factory()->create(['role_id' => Role::where('name', 'employee')->value('id')]);
        $this->confined = User::factory()->create([
            'role_id' => Role::where('name', 'employee')->value('id'),
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $client = Client::create([
            'company_name' => 'Security Client', 'contact_name' => 'C',
            'email' => 'c@example.test', 'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Security Project', 'client_id' => $client->id,
            'contact_person' => 'C', 'email' => 'p@example.test', 'created_by' => $this->admin->id,
        ]);

        $this->jobSite = JobSite::create([
            'project_id' => $this->project->id, 'job_site_name' => 'Site',
            'contact_person' => 'C', 'email' => 's@example.test', 'created_by' => $this->admin->id,
        ]);
    }

    /*
    |---------------------------------------------------------------------------
    | Enforced today
    |---------------------------------------------------------------------------
    */

    public function test_the_permission_screens_themselves_are_enforced(): void
    {
        foreach ([
            route('access.index'),
            route('projects.team', $this->project),
            route('jobsites.team', $this->jobSite),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->employee)->get($url)->assertForbidden();
            $this->actingAs($this->confined)->get($url)->assertForbidden();
        }
    }

    public function test_what_was_admin_only_before_is_still_admin_only(): void
    {
        foreach ([
            route('users.index'),
            route('cost-codes.templates.index'),
            route('reports.sales-tax'),
            route('system-settings.index'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->employee)->get($url)->assertForbidden();
        }
    }

    public function test_a_switched_off_module_still_beats_everybody(): void
    {
        \App\Models\ModuleAccess::create([
            'module_key' => 'estimates', 'module_name' => 'Estimates',
            'is_enabled' => false, 'is_core' => false, 'created_by' => $this->admin->id,
        ]);
        \App\Models\ModuleAccess::clearCache('estimates');

        $this->actingAs($this->admin)->get(route('estimates.index'))->assertForbidden();
    }

    public function test_a_membership_cannot_be_given_an_ability_the_catalogue_does_not_declare(): void
    {
        $membership = Membership::create([
            'user_id' => $this->confined->id,
            'scopeable_type' => JobSite::class,
            'scopeable_id' => $this->jobSite->id,
        ]);

        // Whatever reaches syncAbilities, the resolver only ever answers for
        // abilities that exist; and the screens filter on the way in.
        $membership->syncAbilities(['expenses.view', 'made.up']);

        $this->assertFalse(app(PermissionResolver::class)->allows($this->confined, 'made.up', $this->jobSite));
    }

    /*
    |---------------------------------------------------------------------------
    | NOT enforced yet — every one of these is a module pass waiting to happen
    |---------------------------------------------------------------------------
    */

    public function test_the_converted_areas_are_the_ones_recorded_here(): void
    {
        // M1 converted the permission module's own screens. Everything else is
        // still on the legacy bridge; each pass moves one line.
        $swept = array_values(array_diff(array_keys(AbilityCatalog::areas()), AbilityCatalog::unsweptAreas()));

        sort($swept);

        $this->assertSame(
            [
                'access', 'budget', 'change-orders', 'company', 'contracts', 'cost-codes',
                'documents', 'expenses', 'income', 'payments', 'project', 'projects',
                'purchase-orders', 'quotations', 'requisitions', 'settings', 'team', 'users',
            ],
            $swept,
            'An area is marked swept. Move its cases in this file from "not enforced" to "enforced".',
        );
    }

    public function test_the_company_record_can_no_longer_be_edited_by_just_anybody(): void
    {
        // Until M3 every signed-in person could open *and save* the company
        // record. The default still lets an employee see and edit it — that is
        // what the application did — but it is now a grant that can be taken
        // away, which is the whole difference.
        $this->actingAs($this->employee)->get(route('company.info'))->assertOk();

        $role = $this->employee->role;
        $role->syncAbilities(array_values(array_filter(
            $role->abilities(),
            fn ($ability) => ! str_starts_with($ability, 'company.'),
        )));
        app(PermissionResolver::class)->flush();

        $this->actingAs($this->employee->fresh())->get(route('company.info'))->assertForbidden();
    }

    public function test_the_users_screen_is_now_enforced_by_abilities(): void
    {
        // Converted in M1: it used to be the `admin` middleware, and the
        // answer is deliberately identical — an administrator holds the
        // abilities and nobody else does until somebody grants them.
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
        $this->actingAs($this->employee)->get(route('users.index'))->assertForbidden();

        // …but now it can be granted, which the middleware could never do.
        $this->employee->role->syncAbilities(
            array_merge($this->employee->role->abilities(), ['users.view'])
        );
        app(PermissionResolver::class)->flush();

        $this->actingAs($this->employee->fresh())->get(route('users.index'))->assertOk();
        $this->actingAs($this->employee->fresh())->get(route('users.create'))->assertForbidden();
    }

    public function test_a_confined_person_can_no_longer_open_a_project_they_are_not_on(): void
    {
        // N4, closed by M2 for the shell: which projects somebody can open.
        foreach ([
            route('projects.overview', $this->project),
            route('projects.expenses', $this->project),
            route('projects.budget', $this->project),
            route('jobsites.overview', $this->jobSite),
        ] as $url) {
            // Company-wide: still everything, exactly as before.
            $this->actingAs($this->employee)->get($url)->assertOk();

            // Confined and on nothing: the door is shut, tab by tab.
            $this->actingAs($this->confined)->get($url)->assertForbidden();
        }
    }

    public function test_what_is_inside_a_project_is_open_only_where_the_module_has_not_had_its_pass(): void
    {
        // M2 decides which projects somebody may open, not what they may do
        // inside one. A member with a narrow membership still sees every tab
        // of a module that has not had its pass.
        $membership = Membership::create([
            'user_id' => $this->confined->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
        ]);
        $membership->syncAbilities(['project.view']);   // nothing else at all

        app(PermissionResolver::class)->flush();

        $this->actingAs($this->confined)->get(route('projects.overview', $this->project))->assertOk();

        // Expenses (M4) and Budget (M6) are swept: the membership says nothing
        // about either, so no.
        $this->actingAs($this->confined)->get(route('projects.expenses', $this->project))->assertForbidden();
        $this->actingAs($this->confined)->get(route('projects.budget', $this->project))->assertForbidden();

        // Change orders no longer either — M10 swept them.
        $this->actingAs($this->confined)->get(route('projects.change-orders', $this->project))->assertForbidden();

        // Contracts no longer either — M11 swept them.
        $this->actingAs($this->confined)->get(route('projects.contracts', $this->project))->assertForbidden();

        // Daily reports are still open, and should be refused once M14 lands.
        $this->actingAs($this->confined)->get(route('projects.daily-reports', $this->project))->assertOk();
    }

    public function test_the_money_screens_m11_closed_are_now_grants_rather_than_open_doors(): void
    {
        // All three were reachable by anybody signed in — what E1 recorded and
        // what M11 was held back for. The seeded roles still reach them, which
        // is the pass rule: reproduce today's answer, but make it a grant.
        foreach (['payments.index', 'contract-payments.index', 'payment-batches.index'] as $name) {
            $this->actingAs($this->employee)->get(route($name))->assertOk();
        }

        // The difference is that it can now be taken away.
        $role = Role::create(['name' => 'no-payments-'.uniqid()]);
        $role->syncAbilities(['projects.view', 'project.view']);
        $blind = User::factory()->create(['role_id' => $role->id]);

        foreach (['payments.index', 'contract-payments.index', 'payment-batches.index'] as $name) {
            $this->actingAs($blind)->get(route($name))->assertForbidden();
        }

        // …and the three grants are separable: the batches need their own.
        $viewer = Role::create(['name' => 'payments-viewer-'.uniqid()]);
        $viewer->syncAbilities(['projects.view', 'project.view', 'payments.view']);
        $reader = User::factory()->create(['role_id' => $viewer->id]);

        $this->actingAs($reader)->get(route('payments.index'))->assertOk();
        $this->actingAs($reader)->get(route('payment-batches.index'))->assertForbidden();
    }

    public function test_the_unguarded_money_screens_are_still_unguarded(): void
    {
        // M15 (estimates and invoices) — the last two.
        foreach ([
            route('estimates.index'),
            route('invoices.index'),
        ] as $url) {
            $this->actingAs($this->employee)->get($url)->assertOk();
        }
    }

    public function test_a_membership_now_decides_the_swept_modules_and_not_the_rest(): void
    {
        $template = PermissionTemplate::where('key', 'site-supervisor')->first();

        $membership = Membership::create([
            'user_id' => $this->confined->id,
            'scopeable_type' => JobSite::class,
            'scopeable_id' => $this->jobSite->id,
            'permission_template_id' => $template->id,
            'can_see_money' => false,
        ]);
        $membership->syncAbilities($template->abilities());

        $resolver = app(PermissionResolver::class);

        // Expenses is swept, so the template's grants are live…
        $this->assertTrue($resolver->allows($this->confined, 'expenses.create', $this->jobSite));
        $this->assertFalse($resolver->allows($this->confined, 'expenses.delete', $this->jobSite));

        $this->actingAs($this->confined)->get(route('jobsites.expenses', $this->jobSite))->assertOk();

        // …and an unswept area still denies a confined person outright,
        // whatever the template says. Daily reports close in M14.
        $this->assertTrue(in_array('daily-reports.view', $template->abilities(), true));
        $this->assertFalse($resolver->allows($this->confined, 'daily-reports.view', $this->jobSite));
    }

    public function test_documents_and_reports_are_still_reachable_by_id(): void
    {
        // N5: the PDF and file controllers are behind `auth` and nothing else.
        $pdfRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getName(), 'pdf'))
            ->reject(fn ($route) => in_array('admin', $route->gatherMiddleware(), true))
            ->reject(fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'ability')));

        $this->assertGreaterThan(
            0,
            $pdfRoutes->count(),
            'Every PDF route is now guarded — N5 is closed, so this test should be turned around.',
        );
    }

    public function test_the_team_tab_says_plainly_that_it_does_not_restrict_anybody_yet(): void
    {
        // A screen that lets somebody configure access which is not switched on
        // has to say so — otherwise it promises something the code does not do.
        $this->actingAs($this->admin)
            ->get(route('projects.team', $this->project))
            ->assertOk()
            ->assertSee(__('This team list does not restrict anybody yet.'));

        $this->actingAs($this->admin)
            ->get(route('jobsites.team', $this->jobSite))
            ->assertOk()
            ->assertSee(__('This team list does not restrict anybody yet.'));
    }
}
