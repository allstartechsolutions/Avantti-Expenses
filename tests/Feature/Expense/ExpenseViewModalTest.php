<?php

namespace Tests\Feature\Expense;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\JobSite\JobSiteShow;
use App\Livewire\Project\ProjectShow;
use App\Models\Client;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The expense view modal on the project and job site screens.
 *
 * Production threw "Call to a member function ability() on null" from the
 * history partial (Sentry AVANTTI-CONSTRUCTION-6): closing the modal cleared
 * the expense id but left the modal in view mode, and the modal body renders
 * on every request whether it is open or not. The same shape appears when the
 * expense is deleted while somebody still has it open.
 */
class ExpenseViewModalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Modal Client',
            'contact_name' => 'C',
            'email' => 'modal-client@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'project-modal@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->site = JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => 'Torre A',
            'contact_person' => 'C',
            'email' => 'site-modal@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeExpense(): Expense
    {
        return Expense::create([
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
        ]);
    }

    public function test_closing_the_view_modal_on_the_job_site_screen_renders_again(): void
    {
        $expense = $this->makeExpense();

        Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('openExpenseViewModal', $expense->id)
            ->assertSet('showExpenseModal', true)
            ->assertSee(__('Expense Details'))
            ->call('closeExpenseModal')
            ->assertOk()
            ->assertSet('viewingExpenseId', null)
            ->assertSet('showExpenseModal', false);
    }

    /**
     * The job site screen ran a copy of the single-item modal and never read
     * `expense_items`, so an expense filed on the full create screen opened
     * with no items at all. It is the shared modal now, the project's own.
     */
    public function test_the_job_site_view_modal_lists_every_line_item(): void
    {
        $expense = Expense::create([
            'project_id' => $this->project->id,
            'job_site_id' => $this->site->id,
            'total_amount' => 350,
            'expense_date' => now()->toDateString(),
            'status' => 'unpaid',
            'total_installments' => 1,
            'created_by' => $this->admin->id,
        ]);
        $expense->items()->create(['item_name' => 'Rebar 10mm', 'quantity' => 10, 'unit_price' => 20, 'total_amount' => 200]);
        $expense->items()->create(['item_name' => 'Sand bags', 'quantity' => 5, 'unit_price' => 30, 'total_amount' => 150]);

        Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('setActiveTab', 'expenses')
            ->assertSee(trans_choice(':count item|:count items', 2, ['count' => 2]))
            ->call('openExpenseViewModal', $expense->id)
            ->assertCount('expenseItems', 2)
            ->assertSee('Rebar 10mm')
            ->assertSee('Sand bags');
    }

    /** An expense from before line items keeps its one item on the header, and still shows it. */
    public function test_a_single_item_expense_from_before_line_items_still_shows_its_item(): void
    {
        $expense = $this->makeExpense();

        Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('openExpenseViewModal', $expense->id)
            ->assertCount('expenseItems', 1)
            ->assertSet('expenseItems.0.item_name', 'Cement');
    }

    public function test_closing_the_view_modal_on_the_project_screen_renders_again(): void
    {
        $expense = $this->makeExpense();

        Livewire::actingAs($this->admin)
            ->test(ProjectShow::class, ['project' => $this->project])
            ->call('openExpenseViewModal', $expense->id)
            ->assertSet('expenseModalMode', 'view')
            ->call('closeExpenseModal')
            ->assertOk()
            ->assertSet('editingExpense', null)
            ->assertSet('expenseModalMode', 'create');
    }

    public function test_an_expense_deleted_under_an_open_view_modal_does_not_break_the_screen(): void
    {
        $expense = $this->makeExpense();

        $component = Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('openExpenseViewModal', $expense->id)
            ->assertSet('showExpenseModal', true);

        // Somebody else removes it while this modal is still open.
        $expense->delete();

        $component->call('$refresh')->assertOk();
    }

    /** The open expense is chosen by the server only; the browser cannot point the modal at another. */
    public function test_the_open_expense_cannot_be_set_from_the_browser(): void
    {
        $expense = $this->makeExpense();

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->set('viewingExpenseId', $expense->id);
    }

    /** Paying the last installment from the open modal shows the expense as paid without reopening it. */
    public function test_a_payment_action_refreshes_the_open_modal(): void
    {
        $expense = $this->makeExpense();
        $expense->update(['total_installments' => 2, 'payment_frequency' => 'monthly', 'payment_due_date' => now()->toDateString()]);
        $expense->generatePaymentSchedule();
        [$first, $last] = $expense->payments()->orderBy('payment_number')->get()->all();
        $first->markAsPaid(null, now());

        $component = Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $this->site])
            ->call('openExpenseViewModal', $expense->id)
            ->assertSet('expense_status', 'partial');

        $component->call('startMarkPaid', 'payment', $last->id)
            ->call('confirmMarkPaid')
            ->assertSet('expense_status', 'paid')
            ->assertSet('showExpenseModal', true);
    }
}
