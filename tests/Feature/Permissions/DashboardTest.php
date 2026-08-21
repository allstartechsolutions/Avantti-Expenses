<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Dashboard\DashboardIndex;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M18 — the dashboard and the header search. The last module pass.
 *
 * The dashboard is unlike every other screen in this module: there is nothing
 * on it that belongs to it. Every card and every panel is a summary of another
 * module, which means guarding the page is not the interesting question —
 * guarding what is drawn on it is. Three rules, and this file is the three:
 *
 *   1. `dashboard.view` opens the page and everybody has it (a login lands
 *      here). `dashboard.overview` is what fills it, and reproduces exactly the
 *      `$role === 'admin'` the view used to carry.
 *   2. Holding the overview is not holding its contents. Each block asks for
 *      the ability of the module it summarises.
 *   3. The figures are narrowed to the projects the reader may see — a total
 *      across projects somebody cannot open is a leak by aggregate.
 *
 * It also closes **N9**: the header search would otherwise be the easiest way
 * in the application to enumerate the projects somebody was never meant to see.
 */
class DashboardTest extends TestCase
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

        $this->admin = $this->user('admin');

        $this->project = $this->makeProject('Ours');
        $this->otherProject = $this->makeProject('Theirs');
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        app(PermissionResolver::class)->flush();

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Dashboard Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'status' => ProjectStatus::IN_PROGRESS,
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

    protected function makeExpense(Project $project, float $amount): Expense
    {
        return Expense::create([
            'project_id' => $project->id,
            'item_name' => 'Cement',
            'quantity' => 1,
            'unit_price' => $amount,
            'total_amount' => $amount,
            'expense_date' => now()->toDateString(),
            'payment_due_date' => now()->toDateString(),
            'status' => 'unpaid',
            'total_installments' => 1,
            'created_by' => $this->admin->id,
        ]);
    }

    /** A member confined to one project, holding exactly these abilities. */
    protected function memberOf(Project|JobSite $scope, array $abilities, bool $money = true, array $roleAbilities = []): User
    {
        $user = $roleAbilities === []
            ? $this->user('employee', ['access_scope' => AccessScope::ASSIGNED])
            : tap($this->roleWith($roleAbilities), fn ($u) => $u->forceFill(['access_scope' => AccessScope::ASSIGNED])->save());

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
            'can_see_money' => $money,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | Reproduced, then revocable
    |---------------------------------------------------------------------------
    */

    public function test_the_dashboard_answers_as_it_did_for_every_role(): void
    {
        // Today: the admin gets the overview and everybody else gets a panel.
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cash to Pay');

        foreach (['manager', 'employee'] as $role) {
            $this->actingAs($this->user($role))->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('Cash to Pay')
                ->assertSee('Welcome');
        }
    }

    public function test_the_overview_is_now_a_grant_rather_than_a_role_name(): void
    {
        // The same screen the admin sees, for somebody who is not an admin.
        $analyst = $this->roleWith([
            'dashboard.view', 'dashboard.overview', 'expenses.view', 'finance.view_amounts',
        ]);

        $this->actingAs($analyst)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cash to Pay');
    }

    public function test_opening_the_dashboard_at_all_can_be_taken_away(): void
    {
        $locked = $this->roleWith(['projects.view']);

        $this->actingAs($locked)->get(route('dashboard'))->assertForbidden();
    }

    public function test_somebody_who_does_not_belong_here_is_sent_where_they_do(): void
    {
        // The login lands everybody on this route, so a person without the
        // dashboard must not meet a 403 on every sign-in. A guest is the case
        // that matters: the resolver refuses them every company-wide ability
        // by design, so `dashboard.view` is not something they can be given.
        $guest = $this->user('employee', [
            'is_guest' => true,
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $membership = Membership::create([
            'user_id' => $guest->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(['project.view']);

        app(PermissionResolver::class)->flush();

        $this->actingAs($guest)->get(route('dashboard'))
            ->assertRedirect(route('projects.overview', $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | Every block obeys the module it summarises
    |---------------------------------------------------------------------------
    */

    public function test_the_overview_is_not_permission_to_see_everything_on_it(): void
    {
        $expensesOnly = $this->roleWith([
            'dashboard.view', 'dashboard.overview', 'expenses.view', 'finance.view_amounts',
        ]);

        $this->actingAs($expensesOnly)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cash to Pay')          // expenses.view
            ->assertDontSee('Receivables')      // invoices.view
            ->assertDontSee('Open Estimates')   // estimates.view
            ->assertDontSee('Active Projects'); // projects.view
    }

    public function test_each_block_arrives_with_its_own_grant(): void
    {
        $abilities = ['dashboard.view', 'dashboard.overview', 'finance.view_amounts'];

        foreach ([
            'expenses.view' => 'Cash to Pay',
            'invoices.view' => 'Receivables',
            'estimates.view' => 'Open Estimates',
            'projects.view' => 'Active Projects',
        ] as $ability => $heading) {
            $user = $this->roleWith(array_merge($abilities, [$ability]));

            $this->actingAs($user)->get(route('dashboard'))
                ->assertOk()
                ->assertSee($heading, false);
        }
    }

    public function test_the_purchase_order_card_stands_in_when_there_are_no_estimates(): void
    {
        // The layout substitution the view has always made, now driven by the
        // ability instead of the module switch.
        $buyer = $this->roleWith([
            'dashboard.view', 'dashboard.overview', 'purchase-orders.view', 'finance.view_amounts',
        ]);

        $this->actingAs($buyer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Open Purchase Orders')
            ->assertDontSee('Open Estimates');
    }

    public function test_the_overview_with_no_modules_behind_it_says_so(): void
    {
        // Not a blank page: this is somebody whose access needs adjusting and
        // the screen has to be the thing that tells them.
        $empty = $this->roleWith(['dashboard.view', 'dashboard.overview']);

        $this->actingAs($empty)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nothing to summarise yet')
            ->assertDontSee('Cash to Pay');
    }

    /*
    |---------------------------------------------------------------------------
    | The figures are narrowed to what the reader may see
    |---------------------------------------------------------------------------
    */

    public function test_a_total_never_counts_a_project_the_reader_cannot_open(): void
    {
        $this->makeExpense($this->project, 100);
        $this->makeExpense($this->otherProject, 900);

        $member = $this->memberOf($this->project, ['expenses.view', 'projects.view']);

        // Their own project's spend, and not a cent of the other one's.
        $kpis = Livewire::actingAs($member)->test(DashboardIndex::class)->instance()->kpis;

        $this->assertSame(100.0, round($kpis['cash_to_pay'], 2));
        $this->assertSame(1, $kpis['active_projects']);
    }

    public function test_a_company_wide_reader_still_sees_the_whole_company(): void
    {
        $this->makeExpense($this->project, 100);
        $this->makeExpense($this->otherProject, 900);

        $kpis = Livewire::actingAs($this->admin)->test(DashboardIndex::class)->instance()->kpis;

        $this->assertSame(1000.0, round($kpis['cash_to_pay'], 2));
        $this->assertSame(2, $kpis['active_projects']);
    }

    public function test_a_block_that_is_off_is_never_even_queried(): void
    {
        $this->makeExpense($this->project, 100);

        $noExpenses = $this->roleWith([
            'dashboard.view', 'dashboard.overview', 'projects.view', 'finance.view_amounts',
        ]);

        $component = Livewire::actingAs($noExpenses)->test(DashboardIndex::class)->instance();

        $this->assertSame(0, $component->kpis['cash_to_pay']);
        $this->assertTrue($component->overduePayments->isEmpty());
    }

    /*
    |---------------------------------------------------------------------------
    | Money
    |---------------------------------------------------------------------------
    */

    public function test_hiding_money_masks_the_figures_and_removes_the_chart(): void
    {
        $this->makeExpense($this->project, 4321);

        // Every figure on this screen is a roll-up over the company, which is
        // precisely what can_see_money hides (M4).
        // The overview itself is a company-wide grant, so it comes from the
        // role; what hides the money is `can_see_money` on the membership.
        $blind = $this->memberOf(
            $this->project,
            ['expenses.view', 'projects.view'],
            money: false,
            roleAbilities: ['dashboard.view', 'dashboard.overview', 'finance.view_amounts'],
        );

        $this->actingAs($blind)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cash to Pay')
            ->assertDontSee('4,321')
            ->assertDontSee('Cash Out (Last 6 Months)');
    }

    public function test_the_same_member_with_money_allowed_sees_every_figure(): void
    {
        $this->makeExpense($this->project, 4321);

        $seeing = $this->memberOf(
            $this->project,
            ['expenses.view', 'projects.view'],
            money: true,
            roleAbilities: ['dashboard.view', 'dashboard.overview', 'finance.view_amounts'],
        );

        $this->actingAs($seeing)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('4,321');
    }

    public function test_somebody_who_may_see_money_still_sees_it(): void
    {
        $this->makeExpense($this->project, 4321);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('4,321');
    }

    /*
    |---------------------------------------------------------------------------
    | The welcome panel
    |---------------------------------------------------------------------------
    */

    public function test_the_welcome_panel_offers_only_screens_the_person_may_open(): void
    {
        $employee = $this->user('employee');

        // It is built from the sidebar, so it cannot offer what the sidebar
        // would not: an employee has no Roles & Access entry.
        $shortcuts = Livewire::actingAs($employee)->test(DashboardIndex::class)->instance()->shortcuts;
        $keys = array_column($shortcuts, 'key');

        $this->assertNotContains('access', $keys);
        $this->assertNotContains('dashboard', $keys);
        $this->assertNotEmpty($keys);
    }

    public function test_the_welcome_panel_is_honest_when_there_is_nothing_to_offer(): void
    {
        $bare = $this->roleWith(['dashboard.view']);

        $this->actingAs($bare)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ask an administrator to give you access');
    }

    /*
    |---------------------------------------------------------------------------
    | N9 — the header search
    |---------------------------------------------------------------------------
    */

    public function test_the_search_finds_only_projects_the_person_may_see(): void
    {
        $member = $this->memberOf($this->project, ['projects.view']);

        Livewire::actingAs($member)
            ->test(HeaderSearch::class)
            ->set('search', 'r')          // one character: too short to search on
            ->assertSet('search', 'r')
            ->set('search', 'rs')
            ->assertSee('Ours')
            ->assertDontSee('Theirs');
    }

    public function test_the_search_finds_only_job_sites_the_person_may_see(): void
    {
        $mine = $this->makeSite($this->project, 'Depot Alpha');
        $this->makeSite($this->otherProject, 'Depot Beta');

        $member = $this->memberOf($mine, ['projects.view']);

        Livewire::actingAs($member)
            ->test(HeaderSearch::class)
            ->set('search', 'Depot')
            ->assertSee('Depot Alpha')
            ->assertDontSee('Depot Beta');
    }

    public function test_the_search_touches_nothing_under_two_characters(): void
    {
        $component = Livewire::actingAs($this->admin)->test(HeaderSearch::class)->set('search', 'O');

        $this->assertTrue($component->instance()->projects->isEmpty());
        $this->assertFalse($component->instance()->isSearching);
    }

    /*
    |---------------------------------------------------------------------------
    | The catalogue
    |---------------------------------------------------------------------------
    */

    public function test_the_dashboard_was_the_last_module_to_be_swept(): void
    {
        $this->assertTrue(AbilityCatalog::isSwept('dashboard.view'));

        // It was the last module pass; F2 then swept the documentation library
        // and deleted the bridge, so nothing is unswept any more.
        $this->assertSame([], array_values(AbilityCatalog::unsweptAreas()));
    }

    public function test_the_overview_is_held_back_from_both_seeded_roles(): void
    {
        foreach (['manager', 'employee'] as $name) {
            $this->assertNotContains(
                'dashboard.overview',
                Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all(),
                $name,
            );

            // …but opening the page is not, or a login would land on a 403.
            $this->assertContains(
                'dashboard.view',
                Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all(),
                $name,
            );
        }
    }
}
