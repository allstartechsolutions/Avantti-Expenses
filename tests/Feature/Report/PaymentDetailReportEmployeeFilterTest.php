<?php

namespace Tests\Feature\Report;

use App\Enums\ProjectStatus;
use App\Livewire\Report\PaymentDetailReport;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorEmployee;
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
 * Client request 20 (Nov 2026): the Payment Details report narrows to one
 * subcontractor's employee — the person named on the contract — as well as
 * to the subcontractor.
 */
class PaymentDetailReportEmployeeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Vendor $sub;

    protected Vendor $otherSub;

    protected SubcontractorEmployee $edwin;

    protected SubcontractorEmployee $marcos;

    protected SubcontractorEmployee $otherSubWorker;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->actingAs($this->admin);

        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->sub = $this->makeSub('Framing Pros');
        $this->otherSub = $this->makeSub('Roof Masters');

        $this->edwin = SubcontractorEmployee::create(['subcontractor_id' => $this->sub->id, 'name' => 'Edwin']);
        $this->marcos = SubcontractorEmployee::create(['subcontractor_id' => $this->sub->id, 'name' => 'Marcos']);
        $this->otherSubWorker = SubcontractorEmployee::create(['subcontractor_id' => $this->otherSub->id, 'name' => 'Jorge']);

        $this->makeContract($this->sub, $this->edwin, 1000);
        $this->makeContract($this->sub, $this->marcos, 2000);
        $this->makeContract($this->otherSub, $this->otherSubWorker, 4000);

        Expense::create([
            'project_id' => $this->project->id,
            'item_name' => 'Lumber',
            'expense_date' => now()->toDateString(),
            'total_amount' => 500,
            'status' => 'unpaid',
            'total_installments' => 1,
            'payment_due_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->admin->id,
        ]);
    }

    private function makeSub(string $name): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => $name, 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();

        return $vendor;
    }

    private function makeContract(Vendor $sub, SubcontractorEmployee $employee, float $amount): Contract
    {
        return Contract::create([
            'project_id' => $this->project->id,
            'subcontractor_id' => $sub->id,
            'subcontractor_employee_id' => $employee->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
    }

    private function service(string $subcontractor = '', string $employee = ''): PaymentDetailReportService
    {
        return new PaymentDetailReportService(
            now()->subMonth()->toDateString(),
            now()->addMonths(2)->toDateString(),
            '', '', '', $subcontractor, '', [], 'all', '', $employee,
        );
    }

    public function test_the_employee_filter_keeps_only_that_employees_contracts(): void
    {
        $rows = $this->service(employee: (string) $this->edwin->id)->rows();

        $this->assertCount(1, $rows);
        $this->assertSame('Edwin', $rows->first()['employee']);
        $this->assertSame(1000.0, (float) $rows->sum('amount'));

        // Without it: three contract balances and the expense.
        $this->assertSame(7500.0, (float) $this->service()->rows()->sum('amount'));
    }

    public function test_the_list_narrows_to_the_subcontractor_and_a_stale_pick_is_dropped(): void
    {
        Livewire::test(PaymentDetailReport::class)
            ->assertViewHas('employees', fn ($e) => $e->pluck('name')->all() === ['Edwin', 'Jorge', 'Marcos'])
            ->set('employeeFilter', (string) $this->otherSubWorker->id)
            ->assertSee(__('Employee').': Jorge')
            ->set('subcontractorFilter', (string) $this->sub->id)
            ->assertSet('employeeFilter', '')
            ->assertViewHas('employees', fn ($e) => $e->pluck('name')->all() === ['Edwin', 'Marcos']);
    }

    public function test_the_pdf_honours_the_employee_filter_and_names_the_employee(): void
    {
        // The PDF is a binary stream, so read what the template was handed.
        $data = [];
        View::composer('pdf.payment-detail-report', function ($view) use (&$data) {
            $data = $view->getData();
        });

        $this->get(route('reports.payment-details.pdf.view', [
            'fromDate' => now()->subMonth()->toDateString(),
            'toDate' => now()->addMonths(2)->toDateString(),
            'employeeFilter' => $this->marcos->id,
        ]))->assertOk();

        $this->assertSame('Marcos', $data['employee']?->name);
        $this->assertEquals(2000.0, $data['kpis']['total']);
        $this->assertSame(['Marcos'], $data['rows']->pluck('employee')->all());
    }
}
