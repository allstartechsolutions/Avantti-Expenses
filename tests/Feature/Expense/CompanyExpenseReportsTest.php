<?php

namespace Tests\Feature\Expense;

use App\Enums\ProjectStatus;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Payment\PaymentDashboard;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountsPayableService;
use App\Services\CompanyFinancialService;
use App\Services\ExpenseReportService;
use App\Services\PaymentDetailReportService;
use App\Services\PaymentScheduleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Company (general) expenses on the money reports — docs/company-expenses.md.
 *
 * One rule everywhere: a project, job-site or client filter excludes the
 * company's rows by construction; unfiltered, they appear only for a reader
 * who holds `company-expenses.view`; "Company (general)" on the Project
 * dropdown narrows a report to them alone. A company row prints
 * *Company (general)* where a project goes and its category where a job
 * site goes.
 */
class CompanyExpenseReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Client $client;

    protected Project $project;

    protected ExpenseCategory $rent;

    protected Expense $rentExpense;

    protected Expense $cement;

    protected string $from;

    protected string $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->client = Client::create(['company_name' => 'Report Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id]);
        $this->project = Project::create([
            'project_name' => 'Ours',
            'client_id' => $this->client->id,
            'contact_person' => 'C',
            'email' => 'ours-rep@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->rent = ExpenseCategory::where('key', 'overhead.rent')->firstOrFail();

        $this->rentExpense = Expense::create([
            'project_id' => null,
            'expense_category_id' => $this->rent->id,
            'expense_date' => now()->toDateString(),
            'total_amount' => 1200,
            'status' => 'unpaid',
            'total_installments' => 1,
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $this->cement = Expense::create([
            'project_id' => $this->project->id,
            'item_name' => 'Cement',
            'expense_date' => now()->toDateString(),
            'total_amount' => 100,
            'status' => 'unpaid',
            'total_installments' => 1,
            'payment_due_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $this->from = now()->subMonth()->toDateString();
        $this->to = now()->addMonths(2)->toDateString();
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function expenseReport(string $project = '', string $client = ''): ExpenseReportService
    {
        return new ExpenseReportService($this->from, $this->to, $project, '', '', '', $client);
    }

    /*
    |---------------------------------------------------------------------------
    | The services
    |---------------------------------------------------------------------------
    */

    public function test_the_expense_report_buckets_the_company_only_for_a_reader_with_the_grant(): void
    {
        // Without the grant, the company's rent is simply not there.
        $blind = $this->expenseReport();
        $this->assertEquals(100, $blind->kpis()['total']);
        $this->assertCount(1, $blind->byProject());

        // With it, one more bucket, nested by category rather than by site.
        $report = $this->expenseReport()->includeCompany();
        $this->assertEquals(1300, $report->kpis()['total']);

        $bucket = $report->byProject()->firstWhere('project_id', null);
        $this->assertNotNull($bucket, 'The company bucket is missing.');
        $this->assertSame(__('Company (general)'), $bucket['project']);
        $this->assertEquals(1200, $bucket['total']);
        $this->assertSame($this->rent->getDisplayLabel(), $bucket['jobsites'][0]['job_site']);
        $this->assertNull($bucket['jobsites'][0]['job_site_id']);

        $detail = $report->detail()->firstWhere('project_id', null);
        $this->assertSame(__('Company (general)'), $detail['project']);
        $this->assertSame($this->rent->getDisplayLabel(), $detail['job_site']);
        $this->assertSame($this->rent->getDisplayLabel(), $detail['item'], 'A company row with no item name reads as its category.');
    }

    public function test_a_project_or_client_filter_excludes_the_company_and_the_dropdown_entry_narrows_to_it(): void
    {
        $this->assertEquals(100, $this->expenseReport((string) $this->project->id)->includeCompany()->kpis()['total']);
        $this->assertEquals(100, $this->expenseReport('', (string) $this->client->id)->includeCompany()->kpis()['total']);

        $company = $this->expenseReport('company')->includeCompany();
        $this->assertEquals(1200, $company->kpis()['total']);
        $this->assertCount(1, $company->byProject());
        $this->assertFalse($company->includesContracts(), '"Company (general)" has no contracts to fold in.');

        // The dropdown entry without the grant shows nothing at all.
        $this->assertEquals(0, $this->expenseReport('company')->kpis()['total']);
    }

    public function test_the_company_financials_print_the_category_where_a_job_site_goes(): void
    {
        $blind = CompanyFinancialService::forFilters()->between($this->from, $this->to)->items();
        $this->assertNull($blind->firstWhere('project', __('Company (general)')));

        $items = CompanyFinancialService::forFilters()->between($this->from, $this->to)->includeCompany()->items();
        $row = $items->firstWhere('project', __('Company (general)'));
        $this->assertNotNull($row);
        $this->assertSame($this->rent->getDisplayLabel(), $row['job_site']);
        $this->assertSame($this->rent->getDisplayLabel(), $row['description']);
        $this->assertEquals(1200, $row['amount']);

        $only = CompanyFinancialService::forFilters()->between($this->from, $this->to)->companyScopeFrom('company')->includeCompany()->items();
        $this->assertCount(1, $only);
        $this->assertSame(__('Company (general)'), $only->first()['project']);

        $this->assertEquals(100, CompanyFinancialService::forFilters(null, $this->project->id)->between($this->from, $this->to)->includeCompany()->items()->sum('amount'));
    }

    public function test_the_payment_schedule_the_payment_details_and_the_accounts_payable_follow_the_same_rule(): void
    {
        $this->assertEquals(100, PaymentScheduleService::forSystem()->between($this->from, $this->to)->expenseSchedule()['open']);
        $this->assertEquals(1300, PaymentScheduleService::forSystem()->between($this->from, $this->to)->includeCompany()->expenseSchedule()['open']);
        $this->assertEquals(1200, PaymentScheduleService::forSystem()->between($this->from, $this->to)->companyScopeFrom('company')->includeCompany()->expenseSchedule()['open']);
        $this->assertEquals(100, PaymentScheduleService::forSystem(null, $this->project->id)->between($this->from, $this->to)->includeCompany()->expenseSchedule()['open']);

        $details = fn (string $project = '') => new PaymentDetailReportService($this->from, $this->to, $project);
        $this->assertNull($details()->rows()->firstWhere('project', __('Company (general)')));
        $row = $details()->includeCompany()->rows()->firstWhere('project', __('Company (general)'));
        $this->assertNotNull($row);
        $this->assertSame($this->rent->getDisplayLabel(), $row['job_site']);
        $this->assertEquals(1200, $row['amount']);
        $this->assertSame([__('Company (general)')], $details('company')->includeCompany()->rows()->pluck('project')->unique()->values()->all());
        $this->assertEquals([], $details('company')->rows()->all());

        $payable = fn (string $project = '') => new AccountsPayableService($this->from, $this->to, $project, 'unpaid');
        $this->assertNull($payable()->rows()->firstWhere('project', __('Company (general)')));
        $row = $payable()->includeCompany()->rows()->firstWhere('project', __('Company (general)'));
        $this->assertNotNull($row);
        $this->assertSame($this->rent->getDisplayLabel(), $row['job_site']);
        $this->assertSame([__('Company (general)')], $payable('company')->includeCompany()->rows()->pluck('project')->unique()->values()->all());
        $this->assertEquals(0, $payable('company')->includeCompany()->outstandingContracts()->count());
    }

    /*
    |---------------------------------------------------------------------------
    | The screens
    |---------------------------------------------------------------------------
    */

    public function test_the_payment_dashboard_shows_the_companys_rows_by_grant_and_offers_the_dropdown_entry(): void
    {
        $bookkeeper = $this->roleWith(['payments.view', 'company-expenses.view']);
        $clerk = $this->roleWith(['payments.view']);

        $with = Livewire::actingAs($bookkeeper)->test(PaymentDashboard::class)->set('viewMode', 'all');
        $with->assertSee(__('Company (general)'))->assertSee($this->rent->getDisplayLabel());
        $this->assertEquals(1300, $with->instance()->summary['pending']);

        $with->set('projectFilter', 'company');
        $this->assertEquals(1200, $with->instance()->summary['pending']);
        $with->assertDontSee('Cement');

        $without = Livewire::actingAs($clerk)->test(PaymentDashboard::class)->set('viewMode', 'all');
        $without->assertDontSee(__('Company (general)'));
        $this->assertEquals(100, $without->instance()->summary['pending']);

        // Typing the entry into the address bar does not get around the grant.
        $without->set('projectFilter', 'company');
        $this->assertEquals(0, $without->instance()->summary['pending']);
    }

    public function test_the_dashboard_counts_the_companys_cash_only_for_a_reader_with_the_grant(): void
    {
        $base = ['dashboard.view', 'dashboard.overview', 'projects.view', 'project.view', 'expenses.view'];

        $without = Livewire::actingAs($this->roleWith($base))->test(DashboardIndex::class);
        $this->assertEquals(100, $without->instance()->kpis['cash_to_pay']);

        $with = Livewire::actingAs($this->roleWith([...$base, 'company-expenses.view']))->test(DashboardIndex::class);
        $this->assertEquals(1300, $with->instance()->kpis['cash_to_pay']);
    }

    public function test_the_expense_report_screen_and_its_pdf_follow_the_readers_grant(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.expenses', ['fromDate' => $this->from, 'toDate' => $this->to]))
            ->assertOk()
            ->assertSee('value="company"', false)
            ->assertSee(__('Company (general)'))
            ->assertSee($this->rent->getDisplayLabel());

        $reader = $this->roleWith(['projects.view', 'project.view', 'reports.expenses']);
        $this->actingAs($reader)
            ->get(route('reports.expenses', ['fromDate' => $this->from, 'toDate' => $this->to]))
            ->assertOk()
            ->assertDontSee('value="company"', false)
            ->assertDontSee(__('Company (general)'));

        $this->actingAs($reader)
            ->get(route('reports.expenses.pdf.view', ['fromDate' => $this->from, 'toDate' => $this->to, 'projectFilter' => 'company']))
            ->assertOk();
    }
}
