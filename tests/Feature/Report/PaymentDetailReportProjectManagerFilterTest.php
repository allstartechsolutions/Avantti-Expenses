<?php

namespace Tests\Feature\Report;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Report\PaymentDetailReport;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PaymentDetailReportService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Project Manager filter on the Payment Details report: one manager,
 * every project they run and every job site under those projects, across
 * expenses and contracts alike.
 */
class PaymentDetailReportProjectManagerFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $ana;

    protected User $bruno;

    protected Project $anaProject;

    protected Project $brunoProject;

    protected JobSite $anaSite;

    protected JobSite $brunoSite;

    protected string $from;

    protected string $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $adminRole = Role::where('name', 'admin')->value('id');
        $this->admin = User::factory()->create(['role_id' => $adminRole]);
        $this->ana = User::factory()->create(['role_id' => $adminRole, 'name' => 'Ana']);
        $this->bruno = User::factory()->create(['role_id' => $adminRole, 'name' => 'Bruno']);

        $client = Client::create(['company_name' => 'PM Client', 'contact_name' => 'C', 'email' => 'pm@example.test', 'created_by' => $this->admin->id]);

        $this->anaProject = $this->makeProject('Ana Tower', $client, $this->ana);
        $this->brunoProject = $this->makeProject('Bruno Plaza', $client, $this->bruno);

        $this->anaSite = $this->makeSite($this->anaProject, 'Ana Site');
        $this->brunoSite = $this->makeSite($this->brunoProject, 'Bruno Site');

        // A project-level expense and a job-site expense for each manager,
        // a company expense with no project, and one contract each.
        $this->makeExpense($this->anaProject, null, 100);
        $this->makeExpense($this->anaProject, $this->anaSite, 200);
        $this->makeExpense($this->brunoProject, null, 1000);
        $this->makeExpense($this->brunoProject, $this->brunoSite, 2000);
        $this->makeExpense(null, null, 5000);
        $this->makeContract($this->anaProject, 300);
        $this->makeContract($this->brunoProject, 3000);

        $this->from = now()->subMonth()->toDateString();
        $this->to = now()->addMonths(2)->toDateString();
    }

    protected function makeProject(string $name, Client $client, User $manager): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => $client->id,
            'project_manager_id' => $manager->id,
            'contact_person' => 'C',
            'email' => str()->slug($name).'@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSite(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'contact_person' => 'Contact',
            'email' => str()->slug($name).'@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeExpense(?Project $project, ?JobSite $site, float $amount): Expense
    {
        return Expense::create([
            'project_id' => $project?->id,
            'job_site_id' => $site?->id,
            'expense_category_id' => $project ? null : ExpenseCategory::where('key', 'overhead.rent')->value('id'),
            'item_name' => 'Item '.$amount,
            'expense_date' => now()->toDateString(),
            'total_amount' => $amount,
            'status' => 'unpaid',
            'total_installments' => 1,
            'payment_due_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeContract(Project $project, float $amount): Contract
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'Sub '.str()->random(5), 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();

        return Contract::create([
            'project_id' => $project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function service(string $manager = ''): PaymentDetailReportService
    {
        return new PaymentDetailReportService($this->from, $this->to, '', '', '', '', '', [], 'all', $manager)
            ->includeCompany(true);
    }

    public function test_the_manager_filter_keeps_every_project_and_job_site_of_that_manager_across_expenses_and_contracts(): void
    {
        $this->assertEquals(11600.0, $this->service()->kpis()['total'], 'Unfiltered, every row is counted');

        $ana = $this->service((string) $this->ana->id);
        $this->assertEquals(600.0, $ana->kpis()['total']);
        $this->assertEqualsCanonicalizing([100.0, 200.0, 300.0], $ana->rows()->pluck('amount')->all());
        $this->assertEquals(['Ana Tower'], $ana->rows()->pluck('project')->unique()->values()->all());
        $this->assertContains('Ana Site', $ana->rows()->pluck('job_site')->all(), 'The job-site row under the manager\'s project is kept');

        $bruno = $this->service((string) $this->bruno->id);
        $this->assertEquals(6000.0, $bruno->kpis()['total']);
        $this->assertNotContains(5000.0, $bruno->rows()->pluck('amount')->all(), 'A company expense belongs to no manager');
    }

    public function test_the_manager_filter_stacks_with_the_job_site_filter(): void
    {
        $rows = (new PaymentDetailReportService($this->from, $this->to, '', (string) $this->anaSite->id, '', '', '', [], 'all', (string) $this->ana->id))->rows();

        $this->assertEquals([200.0], $rows->pluck('amount')->all());

        // A job site under another manager's project yields nothing, not a leak.
        $crossed = (new PaymentDetailReportService($this->from, $this->to, '', (string) $this->brunoSite->id, '', '', '', [], 'all', (string) $this->ana->id))->rows();

        $this->assertCount(0, $crossed);
    }

    public function test_the_screen_narrows_the_project_and_job_site_lists_and_drops_a_selection_outside_them(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(PaymentDetailReport::class)
            ->set('fromDate', $this->from)
            ->set('toDate', $this->to);

        $this->assertEqualsCanonicalizing(['Ana', 'Bruno'], $component->instance()->projectManagers->pluck('name')->all());
        $this->assertCount(2, $component->instance()->projects);

        $component
            ->set('projectFilter', (string) $this->brunoProject->id)
            ->set('jobSiteFilter', (string) $this->brunoSite->id)
            ->set('projectManagerFilter', (string) $this->ana->id)
            ->assertSet('projectFilter', '')
            ->assertSet('jobSiteFilter', '');

        $this->assertEquals(['Ana Tower'], $component->instance()->projects->pluck('project_name')->all());
        $this->assertEquals(['Ana Site'], $component->instance()->jobSites->pluck('job_site_name')->all());

        $component->assertViewHas('kpis', fn (array $k) => $k['total'] === 600.0);

        // A selection that is still one of the manager's projects survives.
        $component
            ->set('projectManagerFilter', '')
            ->set('projectFilter', (string) $this->anaProject->id)
            ->set('projectManagerFilter', (string) $this->ana->id)
            ->assertSet('projectFilter', (string) $this->anaProject->id);
    }

    public function test_the_pdf_applies_the_filter_and_names_the_manager(): void
    {
        // The PDF is a binary stream, so read what the template was handed.
        $data = [];
        View::composer('pdf.payment-detail-report', function ($view) use (&$data) {
            $data = $view->getData();
        });

        $this->actingAs($this->admin)
            ->get(route('reports.payment-details.pdf.view', [
                'fromDate' => $this->from,
                'toDate' => $this->to,
                'projectManagerFilter' => $this->ana->id,
                'view' => 'project',
            ]))
            ->assertOk();

        $this->assertSame('Ana', $data['projectManager']?->name);
        $this->assertEquals(600.0, $data['kpis']['total']);
        $this->assertSame(['Ana Tower'], $data['byProject']->pluck('project')->all());
    }
}
