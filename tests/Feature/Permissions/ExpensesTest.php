<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\Expense\ExpenseEdit;
use App\Livewire\JobSite\JobSiteShow;
use App\Livewire\Project\ProjectExpenses;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M4 — Expenses.
 *
 * The first pass that changes what somebody sees *inside* a project, the first
 * with `pay` and `edit_paid`, and the first with money masking.
 *
 * The five personas of every pass: admin · manager · employee · a member
 * confined to one project or job site · a guest. The question each time is
 * whether any of them reaches something they were not given — by URL, by list,
 * by a `wire:click` on a button that was not rendered, or by a receipt link.
 */
class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected JobSite $siblingSite;

    protected Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');

        $this->project = $this->makeProject('Ours');
        $this->otherProject = $this->makeProject('Theirs');

        $this->site = $this->makeSite($this->project, 'Site A');
        $this->siblingSite = $this->makeSite($this->project, 'Site B');
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

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Expense Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'status' => ProjectStatus::CREATED,
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
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeExpense(array $attributes = []): Expense
    {
        return Expense::create(array_merge([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'item_name' => 'Cement',
            'quantity' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'expense_date' => now()->toDateString(),
            'status' => 'unpaid',
            'total_installments' => 1,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /** A member confined to one scope, holding exactly these abilities. */
    protected function memberOf(Project|JobSite $scope, array $abilities, bool $money = true): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

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
    | The screens answer as they did for the company-wide roles
    |---------------------------------------------------------------------------
    */

    public function test_the_expense_screens_answer_as_they_did_for_every_company_wide_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.expenses', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.expenses', $this->site))->assertOk();
            $this->actingAs($user)->get(route('expenses.project.create', $this->project))->assertOk();
            $this->actingAs($user)->get(route('expenses.jobsite.create', $this->site))->assertOk();
        }
    }

    public function test_seeing_expenses_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.expenses', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.expenses', $this->site))->assertForbidden();
    }

    public function test_the_expenses_tab_disappears_for_somebody_without_the_ability(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);
        $reader = $this->roleWith(['project.view', 'projects.view', 'expenses.view']);

        $this->actingAs($blind)->get(route('projects.overview', $this->project))
            ->assertOk()
            ->assertDontSee(route('projects.expenses', $this->project));

        $this->actingAs($reader)->get(route('projects.overview', $this->project))
            ->assertOk()
            ->assertSee(route('projects.expenses', $this->project));
    }

    /*
    |---------------------------------------------------------------------------
    | A confined member — the persona this pass exists for
    |---------------------------------------------------------------------------
    */

    public function test_a_job_site_member_reaches_their_site_and_not_the_project_screen(): void
    {
        $member = $this->memberOf($this->site, ['expenses.view']);

        $this->actingAs($member)->get(route('jobsites.expenses', $this->site))->assertOk();

        // A job-site membership does not cascade upwards.
        $this->actingAs($member)->get(route('projects.expenses', $this->project))->assertForbidden();
        $this->actingAs($member)->get(route('jobsites.expenses', $this->siblingSite))->assertForbidden();
    }

    public function test_a_membership_without_the_expense_grant_is_refused_the_tab(): void
    {
        $member = $this->memberOf($this->site, []);   // project.view and nothing else

        $this->actingAs($member)->get(route('jobsites.expenses', $this->site))->assertForbidden();
    }

    public function test_a_guest_holds_no_expenses_whatever_their_membership_says(): void
    {
        $template = PermissionTemplate::where('key', 'client-project')->first();

        $guest = $this->user('employee', ['is_guest' => true, 'access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $guest->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
            'permission_template_id' => $template->id,
            'can_see_money' => false,
        ]);
        $membership->syncAbilities($template->abilities());

        app(PermissionResolver::class)->flush();

        $this->actingAs($guest)->get(route('projects.expenses', $this->project))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Create — the button and the action behind it
    |---------------------------------------------------------------------------
    */

    public function test_the_add_expense_button_is_not_shown_to_somebody_who_may_not_add_one(): void
    {
        $reader = $this->roleWith(['project.view', 'projects.view', 'expenses.view']);

        $this->actingAs($reader)->get(route('projects.expenses', $this->project))
            ->assertOk()
            ->assertDontSee(route('expenses.project.create', $this->project));

        $this->actingAs($reader)->get(route('jobsites.expenses', $this->site))
            ->assertOk()
            ->assertDontSee(route('expenses.jobsite.create', $this->site));
    }

    public function test_the_create_screen_and_its_save_are_both_refused_without_the_grant(): void
    {
        $reader = $this->roleWith(['project.view', 'projects.view', 'expenses.view']);

        $this->actingAs($reader)->get(route('expenses.project.create', $this->project))->assertForbidden();

        Livewire::actingAs($reader)
            ->test(ExpenseCreate::class, ['project' => $this->project])
            ->assertForbidden();
    }

    public function test_the_job_site_modal_refuses_to_open_or_save_without_create(): void
    {
        $reader = $this->memberOf($this->site, ['expenses.view']);

        Livewire::actingAs($reader)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('openExpenseCreateModal')
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('saveExpense')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Where the expense lands — the Location picker
    |---------------------------------------------------------------------------
    */

    public function test_a_site_member_cannot_file_an_expense_against_a_sibling_site(): void
    {
        $member = $this->memberOf($this->site, ['expenses.view', 'expenses.create']);

        Livewire::actingAs($member)
            ->test(ExpenseCreate::class, ['jobSite' => $this->site])
            ->set('expense_job_site_id', $this->siblingSite->id)
            ->set('expense_date', now()->toDateString())
            ->set('items', [[
                'item_name' => 'Cement', 'quantity' => 1, 'unit_price' => 10,
                'total_amount' => 10, 'budget_item_id' => null, 'catalog_item_id' => null,
                'item_type' => null, 'unit' => null, 'description' => '',
            ]])
            ->call('save')
            ->assertForbidden();
    }

    public function test_the_location_picker_only_offers_sites_the_person_may_write_to(): void
    {
        $member = $this->memberOf($this->project, ['expenses.view', 'expenses.create']);

        // A project membership cascades to both sites.
        Livewire::actingAs($member)
            ->test(ExpenseCreate::class, ['project' => $this->project])
            ->assertViewHas('jobSites', fn ($sites) => $sites->count() === 2);

        // A site membership offers only that site.
        $siteMember = $this->memberOf($this->site, ['expenses.view', 'expenses.create']);

        Livewire::actingAs($siteMember)
            ->test(ExpenseCreate::class, ['jobSite' => $this->site])
            ->assertViewHas('jobSites', fn ($sites) => $sites->pluck('id')->all() === [$this->site->id]);
    }

    public function test_an_expense_cannot_be_pointed_at_another_projects_job_site(): void
    {
        $foreignSite = $this->makeSite($this->otherProject, 'Foreign');

        Livewire::actingAs($this->admin)
            ->test(ExpenseCreate::class, ['project' => $this->project])
            ->set('expense_job_site_id', $foreignSite->id)
            ->set('expense_date', now()->toDateString())
            ->set('items', [[
                'item_name' => 'Cement', 'quantity' => 1, 'unit_price' => 10,
                'total_amount' => 10, 'budget_item_id' => null, 'catalog_item_id' => null,
                'item_type' => null, 'unit' => null, 'description' => '',
            ]])
            ->call('save')
            ->assertHasErrors('expense_job_site_id');
    }

    /*
    |---------------------------------------------------------------------------
    | Edit, pay, edit_paid, delete — each its own grant
    |---------------------------------------------------------------------------
    */

    public function test_editing_needs_its_own_grant_and_the_link_is_hidden_without_it(): void
    {
        $expense = $this->makeExpense();
        $reader = $this->memberOf($this->site, ['expenses.view']);

        $this->actingAs($reader)->get(route('jobsites.expenses', $this->site))
            ->assertOk()
            ->assertDontSee(route('expenses.edit', $expense->id));

        $this->actingAs($reader)->get(route('expenses.edit', $expense->id))->assertForbidden();
    }

    public function test_marking_paid_needs_the_pay_grant_on_both_screens(): void
    {
        $expense = $this->makeExpense();
        $editor = $this->memberOf($this->site, ['expenses.view', 'expenses.edit']);   // no pay

        Livewire::actingAs($editor)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('startMarkPaid', 'expense', $expense->id)
            ->assertForbidden();

        Livewire::actingAs($editor)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->set('markPaidType', 'expense')
            ->set('markPaidId', $expense->id)
            ->set('markPaidDate', now()->toDateString())
            ->call('confirmMarkPaid')
            ->assertForbidden();

        $this->assertSame('unpaid', $expense->fresh()->status);
    }

    public function test_the_pay_grant_lets_a_member_settle_an_expense(): void
    {
        $expense = $this->makeExpense();
        $payer = $this->memberOf($this->site, ['expenses.view', 'expenses.pay']);

        Livewire::actingAs($payer)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->set('markPaidType', 'expense')
            ->set('markPaidId', $expense->id)
            ->set('markPaidDate', now()->toDateString())
            ->call('confirmMarkPaid')
            ->assertOk();

        $this->assertSame('paid', $expense->fresh()->status);
    }

    public function test_correcting_a_settled_expense_needs_edit_paid(): void
    {
        $expense = $this->makeExpense(['status' => 'paid', 'paid_date' => now()->toDateString()]);

        $editor = $this->memberOf($this->site, ['expenses.view', 'expenses.edit']);
        $auditor = $this->memberOf($this->site, ['expenses.view', 'expenses.edit', 'expenses.edit_paid']);

        $this->actingAs($editor)->get(route('expenses.edit', $expense->id))->assertForbidden();
        $this->actingAs($auditor)->get(route('expenses.edit', $expense->id))->assertOk();
    }

    public function test_reverting_a_paid_expense_needs_edit_paid_and_is_hidden_without_it(): void
    {
        $expense = $this->makeExpense(['status' => 'paid', 'paid_date' => now()->toDateString()]);
        $payer = $this->memberOf($this->site, ['expenses.view', 'expenses.pay']);

        $this->actingAs($payer)->get(route('jobsites.expenses', $this->site))
            ->assertOk()
            ->assertDontSee(__('Revert to Unpaid'));

        Livewire::actingAs($payer)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('unmarkExpensePaid', $expense->id)
            ->assertForbidden();

        $this->assertSame('paid', $expense->fresh()->status);
    }

    public function test_reverting_a_paid_installment_needs_edit_paid(): void
    {
        $expense = $this->makeExpense(['total_installments' => 2, 'payment_frequency' => 'monthly']);

        $payment = ExpensePayment::create([
            'expense_id' => $expense->id,
            'payment_number' => 1,
            'amount' => 50,
            'due_date' => now()->toDateString(),
            'status' => 'paid',
            'paid_date' => now()->toDateString(),
        ]);

        $payer = $this->memberOf($this->site, ['expenses.view', 'expenses.pay']);

        Livewire::actingAs($payer)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('unmarkPaymentPaid', $payment->id)
            ->assertForbidden();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_deleting_needs_the_delete_grant_and_the_button_is_hidden_without_it(): void
    {
        $expense = $this->makeExpense();
        $editor = $this->memberOf($this->site, ['expenses.view', 'expenses.edit', 'expenses.pay']);

        $this->actingAs($editor)->get(route('jobsites.expenses', $this->site))
            ->assertOk()
            ->assertDontSee('deleteExpense('.$expense->id.')');

        Livewire::actingAs($editor)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('deleteExpense', $expense->id)
            ->assertForbidden();

        $this->assertNotNull($expense->fresh());
    }

    public function test_the_legacy_project_screen_renders_and_obeys_the_same_grants(): void
    {
        // Nothing links to projects/{project}/show any more, but the route is
        // live and carries a second full copy of the expense CRUD. It opens on
        // the overview and reaches its expenses tab only through setActiveTab.
        $expense = $this->makeExpense(['job_site_id' => null]);

        $this->actingAs($this->admin)->get(route('projects.show', $this->project))->assertOk();

        // The admin's expenses tab renders, with its controls.
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Project\ProjectShow::class, ['project' => $this->project])
            ->call('setActiveTab', 'expenses')
            ->assertOk()
            ->assertSee('deleteExpense('.$expense->id.')', false);

        // A reader gets the tab without the controls…
        $reader = $this->roleWith(['project.view', 'projects.view', 'expenses.view']);

        Livewire::actingAs($reader)
            ->test(\App\Livewire\Project\ProjectShow::class, ['project' => $this->project])
            ->call('setActiveTab', 'expenses')
            ->assertOk()
            ->assertDontSee('deleteExpense('.$expense->id.')', false)
            ->assertDontSee(route('expenses.project.create', $this->project));

        // …and somebody without expenses.view cannot switch to it at all.
        $blind = $this->roleWith(['project.view', 'projects.view']);

        Livewire::actingAs($blind)
            ->test(\App\Livewire\Project\ProjectShow::class, ['project' => $this->project])
            ->call('setActiveTab', 'expenses')
            ->assertForbidden();

        Livewire::actingAs($blind)
            ->test(\App\Livewire\Project\ProjectShow::class, ['project' => $this->project])
            ->call('deleteExpense', $expense->id)
            ->assertForbidden();

        $this->assertNotNull($expense->fresh());
    }

    public function test_an_administrator_still_holds_every_expense_action(): void
    {
        $expense = $this->makeExpense();

        Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('deleteExpense', $expense->id)
            ->assertOk();

        $this->assertNull($expense->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | Reaching another project's records by id
    |---------------------------------------------------------------------------
    */

    public function test_an_expense_of_another_project_cannot_be_reached_through_this_screen(): void
    {
        $foreign = $this->makeExpense(['project_id' => $this->otherProject->id, 'job_site_id' => null]);

        foreach (['deleteExpense', 'openExpenseViewModal'] as $action) {
            try {
                Livewire::actingAs($this->admin)
                    ->test(ProjectExpenses::class, ['project' => $this->project])
                    ->call($action, $foreign->id);

                $this->fail("{$action} reached an expense of another project.");
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                // Exactly right: the lookup is narrowed to this project, so the
                // id of somebody else's expense simply does not exist here.
            }
        }

        $this->assertNotNull($foreign->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | The tab guard — switching tab is not a fresh request
    |---------------------------------------------------------------------------
    */

    public function test_the_expense_tab_cannot_be_reached_by_switching_to_it(): void
    {
        $member = $this->memberOf($this->site, []);   // project.view only

        Livewire::actingAs($member)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('setActiveTab', 'expenses')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Receipts
    |---------------------------------------------------------------------------
    */

    public function test_a_receipt_is_only_served_to_somebody_who_may_see_its_expense(): void
    {
        Storage::fake('local');
        Storage::put('expenses/receipt.pdf', 'x');

        $this->makeExpense(['receipt_path' => 'expenses/receipt.pdf']);

        $reader = $this->memberOf($this->site, ['expenses.view']);
        $outsider = $this->memberOf($this->siblingSite, ['expenses.view']);

        $this->actingAs($reader)->get(route('files.show', ['path' => 'expenses/receipt.pdf']))->assertOk();
        $this->actingAs($outsider)->get(route('files.show', ['path' => 'expenses/receipt.pdf']))->assertForbidden();
    }

    public function test_a_receipt_no_expense_claims_is_not_served_at_all(): void
    {
        Storage::fake('local');
        Storage::put('expenses/orphan.pdf', 'x');

        $this->actingAs($this->admin)
            ->get(route('files.show', ['path' => 'expenses/orphan.pdf']))
            ->assertNotFound();
    }

    /*
    |---------------------------------------------------------------------------
    | Money — roll-ups are hidden, records are not
    |---------------------------------------------------------------------------
    */

    public function test_hiding_money_hides_the_totals_and_leaves_the_expense_amounts(): void
    {
        $this->makeExpense(['total_amount' => 1234.56]);

        $withMoney = $this->memberOf($this->site, ['expenses.view'], money: true);
        $withoutMoney = $this->memberOf($this->site, ['expenses.view'], money: false);

        $total = \Number::currency(1234.56, config('app.currency'), config('app.locale'));

        // Both see the expense's own amount — it is the record, not a roll-up.
        $this->actingAs($withMoney)->get(route('jobsites.expenses', $this->site))
            ->assertOk()
            ->assertSee($total);

        $this->actingAs($withoutMoney)->get(route('jobsites.expenses', $this->site))
            ->assertOk()
            ->assertSee($total)
            ->assertSee(__('Totals are hidden for your access on this project.'));
    }

    public function test_an_administrator_always_sees_the_totals(): void
    {
        $this->makeExpense();

        $this->actingAs($this->admin)->get(route('jobsites.expenses', $this->site))
            ->assertOk()
            ->assertDontSee(__('Totals are hidden for your access on this project.'));
    }

    /*
    |---------------------------------------------------------------------------
    | What the templates hand out
    |---------------------------------------------------------------------------
    */

    public function test_the_seeded_templates_grant_the_expected_expense_actions(): void
    {
        $expected = [
            'project-manager' => ['expenses.view', 'expenses.create', 'expenses.edit', 'expenses.pay'],
            'procurement' => [],
            'accounting' => ['expenses.view', 'expenses.pay'],
            'client-project' => [],
            'site-supervisor' => ['expenses.view', 'expenses.create', 'expenses.edit'],
            'site-team' => ['expenses.view', 'expenses.create'],
        ];

        foreach ($expected as $key => $abilities) {
            $template = PermissionTemplate::where('key', $key)->firstOrFail();

            $held = array_values(array_filter(
                $template->abilities(),
                fn ($a) => str_starts_with($a, 'expenses.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong expense actions.");
        }
    }

    public function test_no_template_hands_out_delete_or_edit_paid(): void
    {
        foreach (PermissionTemplate::all() as $template) {
            $this->assertNotContains('expenses.delete', $template->abilities(), $template->key);
            $this->assertNotContains('expenses.edit_paid', $template->abilities(), $template->key);
        }
    }
}
