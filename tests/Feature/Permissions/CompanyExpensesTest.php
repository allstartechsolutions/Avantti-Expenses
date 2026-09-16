<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\CompanyExpense\CompanyExpenseCreate;
use App\Livewire\CompanyExpense\CompanyExpenseIndex;
use App\Livewire\Expense\ExpenseEdit;
use App\Models\Budget;
use App\Livewire\Shared\Attachments;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Navigation;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * Company (general) expenses — docs/company-expenses.md.
 *
 * A row in `expenses` with no project. Its own area, `company-expenses`,
 * because `expenses.*` is project-scoped and a row with no project asked
 * about it would be answered by any membership the person holds anywhere.
 * The four questions of every pass: reproduced, revocable, scoped, separate —
 * with *scoped* meaning, here, that a site member holding `expenses.view`
 * sees none of the company's rent.
 */
class CompanyExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected ExpenseCategory $rent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
        $this->site = $this->makeSite($this->project, 'Site A');
        $this->rent = ExpenseCategory::where('key', 'overhead.rent')->firstOrFail();
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
                ['company_name' => 'Company Expense Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-ce@example.test',
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
            'email' => str($name)->slug().'-ce@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeCompanyExpense(array $attributes = []): Expense
    {
        return Expense::create(array_merge([
            'project_id' => null,
            'expense_category_id' => $this->rent->id,
            'expense_date' => now()->toDateString(),
            'total_amount' => 1200,
            'status' => 'unpaid',
            'total_installments' => 1,
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'notes' => 'Office rent',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    protected function makeProjectExpense(array $attributes = []): Expense
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

    /** Somebody confined to one job site, holding these abilities there and nothing else. */
    protected function siteMember(array $abilities): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => JobSite::class,
            'scopeable_id' => $this->site->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    protected function menuFor(User $user): array
    {
        $entries = [];

        foreach (app(Navigation::class)->sidebar($user) as $entry) {
            foreach ($entry['items'] ?? [] as $item) {
                $entries[] = $item['key'];
            }
        }

        return $entries;
    }

    /*
    |---------------------------------------------------------------------------
    | Reproduced, then revocable
    |---------------------------------------------------------------------------
    */

    public function test_managers_and_admins_reach_the_screen_and_employees_do_not(): void
    {
        $this->makeCompanyExpense();

        foreach (['admin', 'manager'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get(route('company-expenses.index'))->assertOk()->assertSee('Office rent');
            $this->assertContains('company-expenses', $this->menuFor($user));
        }

        $employee = $this->user('employee');
        $this->actingAs($employee)->get(route('company-expenses.index'))->assertForbidden();
        $this->assertNotContains('company-expenses', $this->menuFor($employee));
    }

    public function test_the_screen_is_a_grant_that_can_be_taken_away(): void
    {
        $this->actingAs($this->roleWith(['projects.view']))->get(route('company-expenses.index'))->assertForbidden();
        $this->actingAs($this->roleWith(['company-expenses.view']))->get(route('company-expenses.index'))->assertOk();
    }

    /*
    |---------------------------------------------------------------------------
    | Scoped: the company's rent is not a site member's business
    |---------------------------------------------------------------------------
    */

    public function test_a_site_member_with_the_expense_grant_sees_none_of_the_companys_rows(): void
    {
        Storage::fake('local');

        $theirs = $this->makeProjectExpense();
        $rent = $this->makeCompanyExpense(['receipt_path' => 'expenses/rent.pdf']);
        Storage::disk('local')->put('expenses/rent.pdf', 'pdf');

        $member = $this->siteMember(['expenses.view', 'expenses.pay']);
        $resolver = app(PermissionResolver::class);

        // The screen and its menu entry.
        $this->actingAs($member)->get(route('company-expenses.index'))->assertForbidden();
        $this->assertNotContains('company-expenses', $this->menuFor($member));

        // The list filter: their site's row, never the company's.
        $visible = Expense::visibleTo($member)->pluck('id')->all();
        $this->assertContains($theirs->id, $visible);
        $this->assertNotContains($rent->id, $visible);

        // The guards, asked the way every screen asks them. This is the leak
        // a shared area would have had: `expenses.view` on a row with no
        // project is answered by heldOnAnyScope(), and the member holds it.
        $this->assertTrue($resolver->allows($member, 'expenses.view', $theirs));
        $this->assertFalse($resolver->allows($member, $rent->ability('view'), $rent));
        $this->assertFalse($resolver->allows($member, 'company-expenses.view'));
        $this->assertFalse($resolver->allows($member, $rent->ability('pay'), $rent));

        // The receipt and the attachments go the same way.
        $this->actingAs($member)->get(route('files.show', ['path' => 'expenses/rent.pdf']))->assertForbidden();
        Livewire::actingAs($member)->test(Attachments::class, ['modelType' => 'expense', 'modelId' => $rent->id])->assertForbidden();

        // And the administrator still sees everything.
        $this->assertEqualsCanonicalizing([$theirs->id, $rent->id], Expense::visibleTo($this->admin)->pluck('id')->all());
        $this->actingAs($this->admin)->get(route('files.show', ['path' => 'expenses/rent.pdf']))->assertOk();
    }

    public function test_a_company_wide_reader_without_the_grant_sees_project_rows_only(): void
    {
        $theirs = $this->makeProjectExpense();
        $rent = $this->makeCompanyExpense();

        $reader = $this->roleWith(['projects.view', 'project.view', 'expenses.view']);
        $this->assertSame([$theirs->id], Expense::visibleTo($reader)->pluck('id')->all());

        $bookkeeper = $this->roleWith(['projects.view', 'project.view', 'expenses.view', 'company-expenses.view']);
        $this->assertEqualsCanonicalizing([$theirs->id, $rent->id], Expense::visibleTo($bookkeeper)->pluck('id')->all());

        // A confined member who was handed the company grant sees the rent
        // beside their own site's rows and nothing of other projects.
        $other = $this->makeProjectExpense(['project_id' => $this->makeProject('Theirs')->id, 'job_site_id' => null]);
        $member = $this->siteMember(['expenses.view']);
        $member->abilityOverrides()->create(['ability' => 'company-expenses.view', 'granted' => true]);
        app(PermissionResolver::class)->flush();

        $visible = Expense::visibleTo($member)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$theirs->id, $rent->id], $visible);
        $this->assertNotContains($other->id, $visible);
    }

    /*
    |---------------------------------------------------------------------------
    | Separate: each action is its own grant, and the two areas do not overlap
    |---------------------------------------------------------------------------
    */

    public function test_the_two_areas_do_not_stand_in_for_each_other(): void
    {
        $this->actingAs($this->roleWith(['projects.view', 'project.view', 'expenses.view']))
            ->get(route('company-expenses.index'))
            ->assertForbidden();

        $this->actingAs($this->roleWith(['projects.view', 'project.view', 'company-expenses.view']))
            ->get(route('projects.expenses', $this->project))
            ->assertForbidden();
    }

    public function test_marking_paid_reverting_due_dates_and_deleting_are_each_their_own_grant(): void
    {
        $rent = $this->makeCompanyExpense();
        $installments = $this->makeCompanyExpense(['total_installments' => 3, 'payment_frequency' => 'monthly']);
        $installments->generatePaymentSchedule();
        $installment = $installments->payments()->orderBy('payment_number')->first();

        $viewer = $this->roleWith(['company-expenses.view']);
        $payer = $this->roleWith(['company-expenses.view', 'company-expenses.pay']);
        $editor = $this->roleWith(['company-expenses.view', 'company-expenses.edit']);
        $reverter = $this->roleWith(['company-expenses.view', 'company-expenses.edit_paid']);
        $deleter = $this->roleWith(['company-expenses.view', 'company-expenses.delete']);

        $test = fn (User $user) => Livewire::actingAs($user)->test(CompanyExpenseIndex::class);

        // View only: the buttons are not there, and the calls are refused.
        $test($viewer)->assertDontSeeHtml('startMarkPaid(')->assertDontSeeHtml('deleteExpense(');
        $test($viewer)->call('startMarkPaid', 'expense', $rent->id)->assertForbidden();
        $test($viewer)->call('startMarkPaid', 'payment', $installment->id)->assertForbidden();
        $test($viewer)->call('startEditDueDate', $installment->id)->assertForbidden();
        $test($viewer)->call('deleteExpense', $rent->id)->assertForbidden();
        $this->assertSame('unpaid', $rent->fresh()->status);

        // Pay.
        $test($payer)->call('startMarkPaid', 'expense', $rent->id)
            ->set('markPaidDate', now()->toDateString())
            ->call('confirmMarkPaid')
            ->assertHasNoErrors();
        $this->assertSame('paid', $rent->fresh()->status);

        $test($payer)->call('startMarkPaid', 'payment', $installment->id)
            ->set('markPaidDate', now()->toDateString())
            ->call('confirmMarkPaid')
            ->assertHasNoErrors();
        $this->assertSame('paid', $installment->fresh()->status);

        // Paying does not let somebody revert; reverting is its own grant.
        $test($payer)->call('unmarkExpensePaid', $rent->id)->assertForbidden();
        $test($payer)->call('unmarkPaymentPaid', $installment->id)->assertForbidden();
        $this->assertSame('paid', $rent->fresh()->status);

        $test($reverter)->call('unmarkExpensePaid', $rent->id)->assertHasNoErrors();
        $this->assertSame('unpaid', $rent->fresh()->status);
        $test($reverter)->call('unmarkPaymentPaid', $installment->id)->assertHasNoErrors();
        $this->assertSame('pending', $installment->fresh()->status);

        // Due date needs edit, and edit alone cannot pay.
        $test($editor)->call('startMarkPaid', 'expense', $rent->id)->assertForbidden();
        $test($editor)->call('startEditDueDate', $installment->id)
            ->set('editDueDate', now()->addMonth()->toDateString())
            ->call('confirmEditDueDate')
            ->assertHasNoErrors();
        $this->assertSame(now()->addMonth()->toDateString(), $installment->fresh()->due_date->toDateString());

        // Delete.
        $test($payer)->call('deleteExpense', $rent->id)->assertForbidden();
        $this->assertNotNull($rent->fresh());
        $test($deleter)->call('deleteExpense', $rent->id)->assertHasNoErrors();
        $this->assertNull($rent->fresh());
    }

    public function test_a_project_expense_id_posted_to_the_company_screen_is_not_found(): void
    {
        $theirs = $this->makeProjectExpense();

        foreach (['openExpenseViewModal', 'deleteExpense'] as $method) {
            try {
                Livewire::actingAs($this->admin)->test(CompanyExpenseIndex::class)->call($method, $theirs->id);
                $this->fail("{$method}() accepted a project expense on the company screen.");
            } catch (ModelNotFoundException) {
                // The id is a 404 here, whatever grant the caller holds.
            }
        }

        $this->assertNotNull($theirs->fresh());
    }

    public function test_the_view_modal_shows_the_category_and_the_audit_facts(): void
    {
        $rent = $this->makeCompanyExpense();

        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseIndex::class)
            ->call('openExpenseViewModal', $rent->id)
            ->assertSet('showExpenseModal', true)
            ->assertSee($this->rent->account_code)
            ->assertSee(__($this->rent->name))
            ->assertSee(__('Created by'))
            ->assertSee($this->admin->name)
            ->assertDontSee(__('Location'));
    }

    /*
    |---------------------------------------------------------------------------
    | Create and edit
    |---------------------------------------------------------------------------
    */

    protected function lineItem(string $name, float $amount): array
    {
        return [
            'budget_item_id' => null,
            'cost_code' => null,
            'catalog_item_id' => null,
            'item_name' => $name,
            'item_type' => 'custom',
            'description' => '',
            'quantity' => 1,
            'unit' => '',
            'unit_price' => $amount,
            'total_amount' => $amount,
        ];
    }

    public function test_filing_a_company_expense_needs_the_create_grant_and_makes_no_budget(): void
    {
        $viewer = $this->roleWith(['company-expenses.view']);
        $this->actingAs($viewer)->get(route('company-expenses.create'))->assertForbidden();
        Livewire::actingAs($viewer)->test(CompanyExpenseCreate::class)->assertForbidden();

        $filer = $this->roleWith(['company-expenses.view', 'company-expenses.create']);
        $this->actingAs($filer)->get(route('company-expenses.create'))
            ->assertOk()
            ->assertSee('wire:model="expense_category_id"', false)
            ->assertDontSee('wire:model.live="expense_job_site_id"', false);

        $budgetsBefore = Budget::count();

        Livewire::actingAs($filer)
            ->test(CompanyExpenseCreate::class)
            ->set('expense_date', now()->toDateString())
            ->set('expense_category_id', $this->rent->id)
            ->set('expense_notes', 'September rent')
            // Through the item dialog, as a person would; the total follows.
            ->call('openAddItemModal')
            ->set('item_name', 'Office rent')
            ->set('item_quantity', 1)
            ->set('item_unit_price', 2500)
            ->call('saveItem')
            ->set('expense_status', 'unpaid')
            ->set('expense_payment_due_date', now()->addDays(5)->toDateString())
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('company-expenses.index'));

        $expense = Expense::where('notes', 'September rent')->sole();
        $this->assertNull($expense->project_id);
        $this->assertNull($expense->job_site_id);
        $this->assertSame($this->rent->id, $expense->expense_category_id);
        $this->assertSame($filer->id, $expense->created_by);
        $this->assertSame(1, $expense->items()->count());
        $this->assertNull($expense->items()->first()->budget_item_id, 'A company line carries no cost code.');
        $this->assertSame($budgetsBefore, Budget::count(), 'Filing a company expense creates no budget.');
        $this->assertEquals(2500, $expense->total_amount);

        // A job site cannot be smuggled in, and a category is required.
        Livewire::actingAs($filer)
            ->test(CompanyExpenseCreate::class)
            ->set('expense_date', now()->toDateString())
            ->set('expense_category_id', '')
            ->set('expense_job_site_id', $this->site->id)
            ->set('items', [$this->lineItem('Smuggled', 10)])
            ->call('save')
            ->assertHasErrors(['expense_category_id', 'expense_job_site_id']);

        // A retired category is refused for a new expense.
        $retired = ExpenseCategory::create(['name' => 'Old', 'account_code' => '7999', 'is_active' => false, 'sort_order' => 1]);
        Livewire::actingAs($filer)
            ->test(CompanyExpenseCreate::class)
            ->set('expense_date', now()->toDateString())
            ->set('expense_category_id', $retired->id)
            ->set('items', [$this->lineItem('Old thing', 10)])
            ->call('save')
            ->assertHasErrors(['expense_category_id']);
    }

    public function test_editing_a_company_expense_needs_the_company_grant_and_not_the_project_one(): void
    {
        $rent = $this->makeCompanyExpense();
        $rent->items()->create($this->lineItem('Office rent', 1200) + ['sort_order' => 0]);

        // The project grant is the wrong key.
        $projectEditor = $this->roleWith(['projects.view', 'project.view', 'expenses.view', 'expenses.edit']);
        $this->actingAs($projectEditor)->get(route('expenses.edit', $rent))->assertForbidden();

        $viewer = $this->roleWith(['company-expenses.view']);
        $this->actingAs($viewer)->get(route('expenses.edit', $rent))->assertForbidden();

        $editor = $this->roleWith(['company-expenses.view', 'company-expenses.edit']);
        $this->actingAs($editor)->get(route('expenses.edit', $rent))
            ->assertOk()
            ->assertSee(__('Company (general)'))
            ->assertSee('wire:model="expense_category_id"', false)
            ->assertDontSee('wire:model.live="expense_job_site_id"', false);

        $utilities = ExpenseCategory::where('key', 'overhead.utilities')->firstOrFail();

        Livewire::actingAs($editor)
            ->test(ExpenseEdit::class, ['expense' => $rent])
            ->assertSet('expense_category_id', $this->rent->id)
            ->set('expense_category_id', $utilities->id)
            ->set('expense_notes', 'Recoded')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('company-expenses.index'));

        $rent->refresh();
        $this->assertSame($utilities->id, $rent->expense_category_id);
        $this->assertNull($rent->project_id);
        $this->assertNull($rent->items()->first()->budget_item_id);

        $history = $rent->changeHistories()->latest()->first();
        $this->assertSame('edited', $history->action);
        $this->assertArrayHasKey('expense_category_id', $history->changes);
        $this->assertSame(__('Category'), Expense::fieldLabel('expense_category_id'));

        // Settled money is its own grant, on this area too.
        $rent->markAsPaid(null, now());
        $this->actingAs($editor)->get(route('expenses.edit', $rent))->assertForbidden();
        $this->actingAs($this->roleWith(['company-expenses.view', 'company-expenses.edit', 'company-expenses.edit_paid']))
            ->get(route('expenses.edit', $rent))
            ->assertOk();
    }

    /*
    |---------------------------------------------------------------------------
    | The one rule
    |---------------------------------------------------------------------------
    */

    public function test_a_company_row_carries_a_category_and_no_job_site_and_a_project_row_no_category(): void
    {
        try {
            $this->makeCompanyExpense(['job_site_id' => $this->site->id]);
            $this->fail('A company expense on a job site was accepted.');
        } catch (LogicException) {
        }

        try {
            $this->makeCompanyExpense(['expense_category_id' => null]);
            $this->fail('A company expense without a category was accepted.');
        } catch (LogicException) {
        }

        $this->assertSame(0, Expense::company()->count());

        $project = $this->makeProjectExpense(['expense_category_id' => $this->rent->id]);
        $this->assertNull($project->fresh()->expense_category_id, 'A project row never keeps a category.');
    }

    public function test_hiding_money_hides_the_totals_and_leaves_the_row_amounts(): void
    {
        $this->makeCompanyExpense();

        $blind = $this->roleWith(['company-expenses.view']);
        $this->assertFalse(app(PermissionResolver::class)->canSeeMoney($blind, null));

        Livewire::actingAs($blind)
            ->test(CompanyExpenseIndex::class)
            ->assertSee(__('Hidden'))
            ->assertSee('1,200');

        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseIndex::class)
            ->assertDontSee(__('Hidden'));
    }
}
