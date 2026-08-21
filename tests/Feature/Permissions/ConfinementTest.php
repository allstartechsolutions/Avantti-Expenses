<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Livewire\Shared\HeaderSearch;
use App\Models\Client;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AbilityCatalog;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F1 — confinement, proved.
 *
 * The plan's acceptance criterion for this phase is one sentence: **an Assigned
 * user cannot reach another project's data by any URL, list, search, report or
 * PDF.** This file is that sentence, as assertions.
 *
 * The important case is deliberately the harshest one available. `Sam` is
 * confined and is a member of one project, so every refusal below is a refusal
 * of somebody the application otherwise trusts — not of a stranger. And their
 * role is `manager`, which holds nearly every ability there is: if confinement
 * only worked for people who were short of grants anyway, it would not be
 * confinement.
 *
 * The URL sweep enumerates the router rather than a hand-written list, so a
 * route added later has to pass it too or this test fails. That is on purpose:
 * a list somebody has to remember to update is a list that goes stale.
 */
class ConfinementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    /** Confined, a member of `ours`, and a manager everywhere else. */
    protected User $sam;

    protected Project $ours;

    protected Project $theirs;

    protected JobSite $ourSite;

    protected JobSite $theirSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->ours = $this->makeProject('Ours');
        $this->theirs = $this->makeProject('Theirs');

        $this->ourSite = $this->makeSite($this->ours, 'Our Depot');
        $this->theirSite = $this->makeSite($this->theirs, 'Their Depot');

        $this->sam = $this->user('manager', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $this->sam->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->ours->id,
            'status' => MembershipStatus::ACTIVE,
            'can_see_money' => true,
        ]);

        // Everything the project level can hold, so nothing below is refused
        // for want of an ability rather than for want of the project.
        $membership->syncAbilities($this->everyProjectAbility());

        app(PermissionResolver::class)->flush();
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    /** @return array<int, string> */
    protected function everyProjectAbility(): array
    {
        $abilities = [];

        foreach (AbilityCatalog::areasForLevel('project') as $area) {
            foreach ($area['actions'] as $action) {
                $abilities[] = $action['ability'];
            }
        }

        return AbilityCatalog::filter($abilities, 'project');
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Confinement Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSite(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Every GET route whose only required parameter is a project or a job site.
     *
     * @return array<string, string>  route name => parameter name
     */
    protected function scopedRoutes(): array
    {
        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }

            $parameters = $route->parameterNames();

            if (count($parameters) !== 1) {
                continue;   // needs a record too; covered by the module's own test
            }

            if (in_array($parameters[0], ['project', 'jobSite'], true)) {
                $found[$name] = $parameters[0];
            }
        }

        ksort($found);

        return $found;
    }

    /*
    |---------------------------------------------------------------------------
    | By URL
    |---------------------------------------------------------------------------
    */

    public function test_the_sweep_covers_the_screens_it_claims_to(): void
    {
        // A guard on the sweep itself: if the router stopped answering, every
        // case below would pass by testing nothing.
        $routes = $this->scopedRoutes();

        $this->assertGreaterThan(30, count($routes));

        foreach (['projects.overview', 'projects.expenses', 'projects.report', 'jobsites.overview'] as $name) {
            $this->assertArrayHasKey($name, $routes, $name);
        }
    }

    public function test_no_project_screen_admits_somebody_who_is_not_on_the_project(): void
    {
        $refused = [];

        foreach ($this->scopedRoutes() as $name => $parameter) {
            $scope = $parameter === 'project' ? $this->theirs : $this->theirSite;

            $status = $this->actingAs($this->sam)->get(route($name, $scope))->getStatusCode();

            if ($status !== 403) {
                $refused[$name] = $status;
            }
        }

        $this->assertSame([], $refused, 'These screens let a confined non-member in.');
    }

    public function test_the_same_person_is_admitted_to_their_own_project(): void
    {
        // The other half of the claim: confinement narrows, it does not break.
        $admitted = [];

        foreach ($this->scopedRoutes() as $name => $parameter) {
            $scope = $parameter === 'project' ? $this->ours : $this->ourSite;

            $status = $this->actingAs($this->sam)->get(route($name, $scope))->getStatusCode();

            if ($status >= 400) {
                $admitted[$name] = $status;
            }
        }

        $this->assertSame([], $admitted, 'These screens refused a member of the project.');
    }

    /*
    |---------------------------------------------------------------------------
    | By list
    |---------------------------------------------------------------------------
    */

    public function test_the_project_and_job_site_lists_show_only_theirs(): void
    {
        $this->actingAs($this->sam)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Ours')
            ->assertDontSee('Theirs');

        $this->actingAs($this->sam)->get(route('projects.jobsites', $this->ours))
            ->assertOk()
            ->assertSee('Our Depot')
            ->assertDontSee('Their Depot');
    }

    public function test_a_cross_project_list_shows_only_their_rows(): void
    {
        // Tasks are the one screen that gathers records from every project at
        // once, so it is where an unfiltered list would show up first.
        \App\Models\Task::create([
            'title' => 'Pour the slab', 'project_id' => $this->ours->id,
            'owner_id' => $this->sam->id, 'number' => 1, 'status' => 'open',
        ]);
        \App\Models\Task::create([
            'title' => 'Secret task', 'project_id' => $this->theirs->id,
            'owner_id' => $this->admin->id, 'number' => 2, 'status' => 'open',
        ]);

        $this->actingAs($this->sam)->get(route('tasks.mine'))
            ->assertOk()
            ->assertDontSee('Secret task');
    }

    /*
    |---------------------------------------------------------------------------
    | By search
    |---------------------------------------------------------------------------
    */

    public function test_the_search_cannot_be_used_to_enumerate_the_rest(): void
    {
        Livewire::actingAs($this->sam)
            ->test(HeaderSearch::class)
            ->set('search', 'Depot')
            ->assertSee('Our Depot')
            ->assertDontSee('Their Depot');

        Livewire::actingAs($this->sam)
            ->test(HeaderSearch::class)
            ->set('search', 'rs')
            ->assertSee('Ours')
            ->assertDontSee('Theirs');
    }

    /*
    |---------------------------------------------------------------------------
    | By report and by PDF
    |---------------------------------------------------------------------------
    */

    public function test_the_financial_reports_and_their_pdfs_stop_at_the_boundary(): void
    {
        foreach ([
            ['projects.report', $this->ours, $this->theirs],
            ['projects.report.pdf.view', $this->ours, $this->theirs],
            ['projects.report.pdf.download', $this->ours, $this->theirs],
            ['jobsites.report', $this->ourSite, $this->theirSite],
            ['jobsites.report.pdf.view', $this->ourSite, $this->theirSite],
            ['jobsites.report.pdf.download', $this->ourSite, $this->theirSite],
        ] as [$name, $mine, $theirs]) {
            $this->actingAs($this->sam)->get(route($name, $mine))->assertSuccessful();
            $this->actingAs($this->sam)->get(route($name, $theirs))->assertForbidden();
        }
    }

    public function test_the_company_reports_are_not_on_their_menu_at_all(): void
    {
        // A confined person holds no company-wide report grant — the six are
        // administrator-only by seed — so the question does not arise. Pinned
        // here because P35 records what happens if one is ever granted.
        foreach ([
            'reports.company-financial', 'reports.sales-tax', 'reports.expenses',
            'reports.payment-schedule', 'reports.accounts-payable', 'reports.payment-details',
        ] as $name) {
            if (! Route::has($name)) {
                continue;
            }

            $this->actingAs($this->sam)->get(route($name))->assertForbidden();
        }
    }

    /*
    |---------------------------------------------------------------------------
    | By record id
    |---------------------------------------------------------------------------
    */

    public function test_a_record_on_another_project_is_not_reachable_by_its_id(): void
    {
        $theirs = Expense::create([
            'project_id' => $this->theirs->id,
            'item_name' => 'Their cement',
            'quantity' => 1, 'unit_price' => 100, 'total_amount' => 100,
            'expense_date' => now()->toDateString(),
            'status' => 'unpaid', 'total_installments' => 1,
            'created_by' => $this->admin->id,
        ]);

        $this->assertFalse(app(PermissionResolver::class)->allows($this->sam, 'expenses.view', $theirs));
        $this->assertFalse(app(PermissionResolver::class)->allows($this->sam, 'expenses.edit', $theirs));
    }

    /*
    |---------------------------------------------------------------------------
    | And the company-wide half is untouched
    |---------------------------------------------------------------------------
    */

    public function test_confinement_does_not_empty_their_menu_of_things_that_have_no_project(): void
    {
        // Confinement is about WHICH PROJECTS somebody can reach. Taking the
        // company-wide screens away with it would be a different setting
        // wearing the same name — see PermissionResolver, step 4.
        foreach (['clients.index', 'suppliers.index', 'catalog-items.index'] as $name) {
            if (! Route::has($name)) {
                continue;
            }

            $this->actingAs($this->sam)->get(route($name))->assertOk();
        }
    }

    public function test_a_second_membership_widens_them_by_exactly_one_project(): void
    {
        $this->actingAs($this->sam)->get(route('projects.expenses', $this->theirs))->assertForbidden();

        $membership = Membership::create([
            'user_id' => $this->sam->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->theirs->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(['project.view', 'expenses.view']);

        app(PermissionResolver::class)->flush();

        $this->actingAs($this->sam)->get(route('projects.expenses', $this->theirs))->assertOk();

        // …and only by what that membership says. It carries no budget.
        $this->actingAs($this->sam)->get(route('projects.budget', $this->theirs))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | The two screens F1 owes
    |---------------------------------------------------------------------------
    */

    public function test_the_inspector_says_what_they_can_do_and_why(): void
    {
        $this->sam->syncAbilityOverrides(['cost-codes.edit' => true]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\User\UserAccess::class, ['user' => $this->sam->fresh()])
            ->set('tab', 'effective')
            ->assertSee('Only the ones they are added to')   // their scope, and where it came from
            ->assertSee('Set on this person')
            ->assertSee('Ours')                              // the project they are on
            ->assertSee('Always allowed — set here')         // the exception, named as one
            ->assertSee('From their role');
    }

    public function test_the_inspector_says_when_somebody_is_on_nothing(): void
    {
        $stranded = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\User\UserAccess::class, ['user' => $stranded])
            ->set('tab', 'effective')
            ->assertSee('They are on nothing, so they see no project at all.');
    }

    public function test_who_can_approve_what_gathers_the_ceiling_from_everywhere(): void
    {
        // Their role approves nothing above R$ 10.000, they are trusted with
        // R$ 50.000 personally, and R$ 90.000 on the one project they are on.
        \App\Models\Role::where('name', 'manager')->update(['approval_limit' => 1_000_000]);
        $this->sam->update(['approval_limit' => 5_000_000]);
        Membership::where('user_id', $this->sam->id)->update(['approval_limit' => 9_000_000]);

        app(PermissionResolver::class)->flush();

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Access\ApprovalAuthority::class)
            ->set('search', $this->sam->name)
            ->assertSee($this->sam->name)
            ->assertSee('Set on this person')
            ->assertSee('Ours');
    }

    public function test_who_can_approve_what_is_the_resolver_and_not_a_copy_of_it(): void
    {
        // Take the approval off this one person and the report has to agree on
        // the next render, because it asks rather than remembers. Asserted on
        // the rows rather than the markup: the search box echoes the term, so
        // their name is on the page either way.
        $buyer = $this->user('manager');

        $component = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Access\ApprovalAuthority::class)
            ->set('search', $buyer->name);

        $this->assertNotEmpty($component->instance()->people);

        $buyer->syncAbilityOverrides(array_fill_keys(
            array_column($component->instance()->actions, 'ability'),
            false,
        ));

        app(PermissionResolver::class)->flush();

        $after = Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Access\ApprovalAuthority::class)
            ->set('search', $buyer->name);

        $this->assertSame([], $after->instance()->people);
    }

    public function test_a_confined_persons_authority_comes_from_their_projects(): void
    {
        // Worth pinning, because it is the one thing about this report that
        // surprises: taking `quotations.award` off a confined person
        // company-wide changes nothing, since their membership is what grants
        // it. Specific beats general — the rule the whole engine runs on — so
        // the place to take it away is the project's Team tab.
        $this->sam->syncAbilityOverrides(['quotations.award' => false]);

        app(PermissionResolver::class)->flush();

        $this->assertTrue(app(PermissionResolver::class)->allows($this->sam, 'quotations.award', $this->ours));

        $membership = Membership::where('user_id', $this->sam->id)->firstOrFail();
        $membership->syncAbilities(array_values(array_diff(
            $membership->abilities(),
            ['quotations.award'],
        )));

        app(PermissionResolver::class)->flush();

        $this->assertFalse(app(PermissionResolver::class)->allows($this->sam, 'quotations.award', $this->ours));
    }

    public function test_the_payment_dashboard_now_obeys_the_ceiling(): void
    {
        // P19's own case, and the last of the four. Before F1 this screen was
        // the one way round a ceiling that bound everywhere else.
        $this->sam->update(['approval_limit' => 10_000]);   // R$ 100,00

        app(PermissionResolver::class)->flush();

        $expense = Expense::create([
            'project_id' => $this->ours->id,
            'item_name' => 'Steel',
            'quantity' => 1, 'unit_price' => 5000, 'total_amount' => 5000,
            'expense_date' => now()->toDateString(),
            'status' => 'unpaid', 'total_installments' => 1,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->sam)
            ->test(\App\Livewire\Payment\PaymentDashboard::class)
            ->call('openPayModal', $expense->id, 'expense')
            ->assertForbidden();

        // Small enough, and the same person goes through.
        $small = Expense::create([
            'project_id' => $this->ours->id,
            'item_name' => 'Nails',
            'quantity' => 1, 'unit_price' => 50, 'total_amount' => 50,
            'expense_date' => now()->toDateString(),
            'status' => 'unpaid', 'total_installments' => 1,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->sam)
            ->test(\App\Livewire\Payment\PaymentDashboard::class)
            ->call('openPayModal', $small->id, 'expense')
            ->assertOk();
    }

    public function test_the_approval_report_is_held_to_the_permission_module(): void
    {
        Livewire::actingAs($this->user('manager'))
            ->test(\App\Livewire\Access\ApprovalAuthority::class)
            ->assertForbidden();
    }

    public function test_a_suspended_membership_closes_the_door_again(): void
    {
        $this->actingAs($this->sam)->get(route('projects.overview', $this->ours))->assertOk();

        // Taking somebody off a project has to take the project with them, on
        // the next click and not on the next login.
        Membership::where('user_id', $this->sam->id)
            ->update(['status' => MembershipStatus::SUSPENDED, 'revoked_at' => now()]);

        app(PermissionResolver::class)->flush();

        $this->actingAs($this->sam)->get(route('projects.overview', $this->ours))->assertForbidden();
        $this->actingAs($this->sam)->get(route('projects.index'))->assertOk()->assertDontSee('Ours');
    }
}
