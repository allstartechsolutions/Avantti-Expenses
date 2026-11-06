<?php

namespace Tests\Feature\Expense;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\CompanyExpense\CompanyExpenseCreate;
use App\Livewire\CompanyExpense\CompanyExpenseIndex;
use App\Livewire\Expense\ExpenseCreate;
use App\Livewire\Expense\ExpenseEdit;
use App\Livewire\JobSite\JobSiteShow;
use App\Livewire\Project\ProjectExpenses;
use App\Livewire\Project\ProjectShow;
use App\Livewire\Report\AccountsPayableReport;
use App\Livewire\Report\ExpenseReport;
use App\Livewire\Report\PaymentDetailReport;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The check number on a company expense (client request, Nov 2026).
 *
 * Only a company expense paid by check keeps one: another method clears it,
 * and a job-site expense never stores one whatever the browser sends.
 */
class CompanyExpenseCheckNumberTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ExpenseCategory $rent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $this->rent = ExpenseCategory::where('key', 'overhead.rent')->firstOrFail();
    }

    private function fileCompanyExpense(string $method, string $checkNumber): Expense
    {
        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseCreate::class)
            ->set('expense_date', now()->toDateString())
            ->set('expense_category_id', $this->rent->id)
            ->call('openAddItemModal')
            ->set('item_name', 'Office rent')
            ->set('item_quantity', 1)
            ->set('item_unit_price', 2500)
            ->call('saveItem')
            ->set('expense_payment_method', $method)
            ->set('expense_check_number', $checkNumber)
            ->set('expense_status', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        return Expense::latest('id')->firstOrFail();
    }

    public function test_the_field_appears_only_when_the_method_is_check(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseCreate::class)
            ->set('expense_payment_method', 'credit_card')
            ->assertDontSee('wire:model="expense_check_number"', false)
            ->set('expense_payment_method', 'check')
            ->assertSee('wire:model="expense_check_number"', false);
    }

    public function test_a_check_number_is_stored_shown_and_searchable(): void
    {
        $expense = $this->fileCompanyExpense('check', '  29852 ');

        $this->assertSame('29852', $expense->check_number);

        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseIndex::class)
            ->assertSee(__('Check no. :number', ['number' => '29852']))
            ->set('search', '29852')
            ->assertViewHas('expenses', fn ($rows) => $rows->pluck('id')->all() === [$expense->id])
            ->call('openExpenseViewModal', $expense->id)
            ->assertSet('expense_check_number', '29852');
    }

    /** Item 13: the note is on the list, so nobody opens each expense to read it. */
    public function test_the_list_shows_each_expense_note(): void
    {
        $expense = $this->fileCompanyExpense('check', '29852');
        $expense->update(['notes' => 'Forklift for Kent Island — Edwin']);

        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseIndex::class)
            ->assertSee(__('Notes'))
            ->assertSee('Forklift for Kent Island — Edwin');
    }

    /** Item 14: a row with files says so, and opens the expense at them. */
    public function test_a_row_with_files_opens_the_expense_at_them(): void
    {
        $withFiles = $this->fileCompanyExpense('check', '29852');
        $withFiles->attachments()->create([
            'file_path' => 'attachments/forklift-invoice.pdf',
            'original_name' => 'forklift-invoice.pdf',
            'uploaded_by' => $this->admin->id,
        ]);
        $bare = $this->fileCompanyExpense('check', '29853');

        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseIndex::class)
            ->assertSee("openExpenseFiles({$withFiles->id})", false)
            ->assertDontSee("openExpenseFiles({$bare->id})", false)
            ->call('openExpenseFiles', $withFiles->id)
            ->assertSet('viewingExpenseId', $withFiles->id)
            ->assertDispatched('expense-show-files')
            ->assertSee('expense-attachments-'.$withFiles->id, false);
    }

    /** Item 15: the machine an expense was for shows on the list and on its details. */
    public function test_the_machine_and_its_maintenance_show_on_the_list_and_the_details(): void
    {
        $equipment = Equipment::create([
            'name' => 'Bobcat S650',
            'asset_tag' => 'EQ-0042',
            'equipment_type' => 'vehicle',
            'make' => 'Bobcat',
            'model' => 'S650',
            'year' => 2021,
            'ownership' => 'owned',
            'meter_type' => 'km',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $maintenance = $equipment->maintenances()->create([
            'title' => 'Hydraulic hose replacement',
            'maintenance_type' => 'corrective',
            'status' => 'completed',
            'completed_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $expense = $this->fileCompanyExpense('check', '29852');
        $expense->update(['equipment_id' => $equipment->id, 'equipment_maintenance_id' => $maintenance->id]);

        Livewire::actingAs($this->admin)
            ->test(CompanyExpenseIndex::class)
            ->assertSee('Bobcat S650 (EQ-0042)')
            ->set('search', 'EQ-0042')
            ->assertViewHas('expenses', fn ($rows) => $rows->pluck('id')->all() === [$expense->id])
            ->call('openExpenseViewModal', $expense->id)
            ->assertSee(route('equipment.show', $equipment))
            ->assertSee('Bobcat S650 2021')
            ->assertSee('Hydraulic hose replacement');
    }

    /** Item 25: what was paid by check, card or autopay over the dates, with a CSV. */
    public function test_the_list_reports_by_payment_method(): void
    {
        $check = $this->fileCompanyExpense('check', '29852');
        $card = $this->fileCompanyExpense('credit_card', '');
        $auto = $this->fileCompanyExpense('credit_card', '');
        $auto->update(['is_auto_payment' => true]);

        $page = Livewire::actingAs($this->admin)
            ->test(CompanyExpenseIndex::class)
            ->assertViewHas('methodSummary', fn ($rows) => collect($rows)->pluck('count', 'key')->sortKeys()->all() === ['autopay' => 1, 'check' => 1, 'credit_card' => 1]
                && (float) collect($rows)->sum('total') === 7500.0)
            ->assertSee(__('By payment method'))
            ->set('methodFilter', 'autopay')
            ->assertViewHas('expenses', fn ($rows) => $rows->pluck('id')->all() === [$auto->id])
            ->set('methodFilter', 'credit_card')
            ->assertViewHas('expenses', fn ($rows) => $rows->pluck('id')->all() === [$card->id])
            ->set('methodFilter', 'check');

        $csv = $page->call('exportCsv');
        $csv->assertFileDownloaded('company-expenses-'.now()->format('Y-m-d').'.csv');
        $this->assertSame([$check->id], Livewire::actingAs($this->admin)->test(CompanyExpenseIndex::class)->set('methodFilter', 'check')->viewData('expenses')->pluck('id')->all());
    }

    public function test_exporting_needs_the_export_grant(): void
    {
        $role = Role::create(['name' => 'bookkeeper']);
        $role->syncAbilities(['company-expenses.view']);

        Livewire::actingAs(User::factory()->create(['role_id' => $role->id]))
            ->test(CompanyExpenseIndex::class)
            ->assertDontSee(__('Export CSV'))
            ->call('exportCsv')
            ->assertForbidden();
    }

    public function test_another_method_stores_no_check_number(): void
    {
        $expense = $this->fileCompanyExpense('credit_card', '29852');

        $this->assertNull($expense->check_number);
    }

    public function test_changing_the_method_away_from_check_clears_it_and_the_history_names_it(): void
    {
        $expense = $this->fileCompanyExpense('check', '29852');

        Livewire::actingAs($this->admin)
            ->test(ExpenseEdit::class, ['expense' => $expense])
            ->assertSet('expense_check_number', '29852')
            ->set('expense_payment_method', 'credit_card')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($expense->fresh()->check_number);
        $this->assertSame(__('Check number'), Expense::fieldLabel('check_number'));
    }

    /** 6 Nov 2026: the client wants the check number on job site and lot expenses too. */
    private function projectWithLot(): array
    {
        $client = Client::create(['company_name' => 'Check Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id]);
        $project = Project::create([
            'project_name' => 'Obra', 'client_id' => $client->id, 'contact_person' => 'C',
            'email' => 'p@example.test', 'status' => ProjectStatus::CREATED, 'created_by' => $this->admin->id,
        ]);
        $lot = JobSite::create([
            'project_id' => $project->id, 'job_site_name' => 'Lot 46', 'contact_person' => 'C',
            'email' => 'lot@example.test', 'status' => JobSiteStatus::CREATED, 'created_by' => $this->admin->id,
        ]);

        return [$project, $lot];
    }

    public function test_a_lot_expense_paid_by_check_keeps_its_number(): void
    {
        [$project, $lot] = $this->projectWithLot();

        Livewire::actingAs($this->admin)
            ->test(ExpenseCreate::class, ['jobSite' => $lot])
            ->set('expense_date', now()->toDateString())
            ->call('openAddItemModal')
            ->set('item_name', 'Cement')
            ->set('item_quantity', 1)
            ->set('item_unit_price', 100)
            ->call('saveItem')
            ->set('expense_payment_method', 'check')
            ->assertSee('wire:model="expense_check_number"', false)
            ->set('expense_check_number', ' 30117 ')
            ->set('expense_status', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        $expense = Expense::where('job_site_id', $lot->id)->sole();
        $this->assertSame('30117', $expense->check_number);

        // The project and the lot lists show it, and their searches find it.
        Livewire::actingAs($this->admin)
            ->test(ProjectExpenses::class, ['project' => $project])
            ->assertSee(__('Check no. :number', ['number' => '30117']))
            ->set('expenseSearch', '30117')
            ->assertSee(__('Check no. :number', ['number' => '30117']))
            ->set('expenseSearch', 'no-such-check')
            ->assertDontSee(__('Check no. :number', ['number' => '30117']));

        Livewire::actingAs($this->admin)
            ->test(JobSiteShow::class, ['jobSite' => $lot])
            ->call('setActiveTab', 'expenses')
            ->assertSee(__('Check no. :number', ['number' => '30117']))
            ->set('expenseSearch', '30117')
            ->assertSee(__('Check no. :number', ['number' => '30117']))
            ->set('expenseSearch', 'no-such-check')
            ->assertDontSee(__('Check no. :number', ['number' => '30117']));
    }

    public function test_the_project_page_modal_keeps_and_clears_the_check_number(): void
    {
        [$project] = $this->projectWithLot();

        $page = Livewire::actingAs($this->admin)
            ->test(ProjectShow::class, ['project' => $project])
            ->call('openExpenseCreateModal')
            ->set('expense_date', now()->toDateString())
            ->set('expenseItems.0.item_name', 'Lumber')
            ->set('expenseItems.0.quantity', 1)
            ->set('expenseItems.0.unit_price', 250)
            ->set('expense_payment_method', 'check')
            ->assertSee('wire:model="expense_check_number"', false)
            ->set('expense_check_number', '30118')
            ->call('saveExpense')
            ->assertHasNoErrors();

        $expense = Expense::where('project_id', $project->id)->sole();
        $this->assertSame('30118', $expense->check_number);

        $page->call('openExpenseEditModal', $expense->id)
            ->assertSet('expense_check_number', '30118')
            ->set('expense_payment_method', 'credit_card')
            ->call('saveExpense')
            ->assertHasNoErrors();

        $this->assertNull($expense->fresh()->check_number);
    }

    /** 6 Nov 2026: the reports show the check number too — on screen, in the CSV and in the PDF. */
    public function test_the_reports_show_the_check_number(): void
    {
        $label = __('Check no. :number', ['number' => '29852']);

        // Paid: Payment Details and the Expense Report.
        $this->fileCompanyExpense('check', '29852');

        $details = Livewire::actingAs($this->admin)->test(PaymentDetailReport::class)->assertSee($label);
        $csv = base64_decode($details->call('exportCsv')->effects['download']['content'] ?? '');
        $this->assertStringContainsString(__('Check number'), $csv);
        $this->assertStringContainsString('29852', $csv);

        $report = Livewire::actingAs($this->admin)->test(ExpenseReport::class)->set('view', 'detail')->assertSee($label);
        $csv = base64_decode($report->call('exportCsv')->effects['download']['content'] ?? '');
        $this->assertStringContainsString('29852', $csv);

        $pdf = [];
        View::composer('pdf.expense-report', function ($view) use (&$pdf) {
            $pdf = $view->getData();
        });
        $this->actingAs($this->admin)->get(route('reports.expenses.pdf.view', ['view' => 'detail']))->assertOk();
        $this->assertContains('29852', collect($pdf['detail'] ?? [])->pluck('check_number')->all());

        // A check written but not cleared yet: Accounts Payable.
        $unpaid = $this->fileCompanyExpense('check', '29853');
        $unpaid->update(['status' => 'unpaid', 'payment_due_date' => now()->addDays(3)->toDateString(), 'paid_date' => null]);

        Livewire::actingAs($this->admin)
            ->test(AccountsPayableReport::class)
            ->assertSee(__('Check no. :number', ['number' => '29853']));
    }
}
