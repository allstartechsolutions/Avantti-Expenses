<?php

namespace Tests\Feature\Project;

use App\Enums\JobSiteStatus;
use App\Enums\ProjectAmountSource;
use App\Enums\ProjectStatus;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\JobSite\JobSiteOverview;
use App\Livewire\Project\ProjectIndex;
use App\Livewire\Project\ProjectOverview;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ProjectFinancials;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client requests 18 and 19 (Nov 2026): the job site list shows total costs
 * (contracts + expenses) and the balance; the job site overview shows the
 * totals and the profit or loss, summed over its lots — the same sum each
 * lot's own overview makes.
 */
class ProjectFinancialsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $lot12;

    protected JobSite $lot14;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::IN_PROGRESS,
            'amount_source' => ProjectAmountSource::FROM_JOBSITES,
            'created_by' => $this->admin->id,
        ]);

        $this->lot12 = $this->lot('Lot 12', 100000);
        $this->lot14 = $this->lot('Lot 14', 80000);

        $sub = new Vendor;
        $sub->forceFill(['name' => 'Wolf', 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        $this->contract($sub, $this->lot12, 40000, 'active');
        $this->contract($sub, $this->lot14, 30000, 'active');
        $this->contract($sub, null, 5000, 'active');
        $this->contract($sub, $this->lot14, 99999, 'draft'); // not committed: never counted

        $this->expense($this->lot12, 2500);
        $this->expense($this->lot14, 1500);
        $this->expense(null, 1000);
    }

    private function lot(string $name, float $amount): JobSite
    {
        return JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'@example.test',
            'job_amount' => $amount,
            'status' => JobSiteStatus::IN_PROGRESS,
            'created_by' => $this->admin->id,
        ]);
    }

    private function contract(Vendor $sub, ?JobSite $lot, float $amount, string $status): void
    {
        Contract::create([
            'project_id' => $this->project->id,
            'job_site_id' => $lot?->id,
            'subcontractor_id' => $sub->id,
            'contract_number' => 'CTR-'.str()->random(5),
            'status' => $status,
            'start_date' => now()->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
    }

    private function expense(?JobSite $lot, float $amount): void
    {
        Expense::create([
            'project_id' => $this->project->id,
            'job_site_id' => $lot?->id,
            'item_name' => 'Item',
            'expense_date' => now()->toDateString(),
            'total_amount' => $amount,
            'status' => 'paid',
            'total_installments' => 1,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_totals_and_the_lot_rows_add_up(): void
    {
        $fin = ProjectFinancials::forProject($this->project);

        $this->assertSame(180000.0, $fin['totals']['contract_value']);
        $this->assertSame(5000.0, $fin['totals']['expenses']);
        $this->assertSame(75000.0, $fin['totals']['contracts']);
        $this->assertSame(80000.0, $fin['totals']['costs']);
        $this->assertSame(100000.0, $fin['totals']['balance']);

        $this->assertSame(['Lot 12', 'Lot 14', null], $fin['lots']->pluck('name')->all());
        $this->assertSame([57500.0, 48500.0, -6000.0], $fin['lots']->pluck('balance')->all());
        $this->assertSame($fin['totals']['balance'], round($fin['lots']->sum('balance'), 2));
    }

    public function test_each_lot_row_matches_that_lots_own_overview(): void
    {
        $row = ProjectFinancials::forProject($this->project)['lots']->firstWhere('name', 'Lot 12');

        $lot = Livewire::actingAs($this->admin)->test(JobSiteOverview::class, ['jobSite' => $this->lot12]);

        $profit = $lot->viewData('contractValue') - $lot->viewData('totalExpensesAmount') - $lot->viewData('totalContractsAdjusted');
        $this->assertEquals($row['balance'], round($profit, 2));
    }

    public function test_the_list_shows_total_costs_and_the_balance(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectIndex::class)
            ->assertSee(__('Total Costs'))
            ->assertSee('$80,000.00')
            ->assertSee('$100,000.00')
            ->assertViewHas('financials', fn ($f) => $f[$this->project->id]['costs'] === 80000.0);
    }

    /** Request 16: receivables are lots with an open contract, their value less their contracts. */
    public function test_receivables_are_open_lots_value_less_contracts(): void
    {
        $open = ProjectFinancials::openLots();

        // Both lots have an open contract; the job-site-level contract and the draft are left out.
        $this->assertSame(2, $open['lots']);
        $this->assertSame(180000.0, $open['value']);
        $this->assertSame(70000.0, $open['contracts']);
        $this->assertSame(110000.0, $open['receivable']);
        $this->assertSame(70000.0, $open['to_pay']);

        // Lot 14's only committed contract is paid in full: the lot is no longer open.
        $paid = Contract::where('job_site_id', $this->lot14->id)->where('status', 'active')->first();
        $paid->payments()->create(['amount' => 30000, 'payment_date' => now()->toDateString(), 'payment_method' => 'check', 'created_by' => $this->admin->id]);
        $paid->update(['status' => 'paid']);

        $open = ProjectFinancials::openLots();
        $this->assertSame(1, $open['lots']);
        $this->assertSame(60000.0, $open['receivable']);
        // Narrowed to the projects a confined reader may see: none of these.
        $this->assertSame(0, ProjectFinancials::openLots([999])['lots']);
    }

    public function test_the_dashboard_card_needs_the_contracts_grant(): void
    {
        $base = ['dashboard.view', 'dashboard.overview', 'projects.view', 'project.view', 'invoices.view'];

        $with = Role::create(['name' => 'with-contracts']);
        $with->syncAbilities([...$base, 'contracts.view']);
        $page = Livewire::actingAs(User::factory()->create(['role_id' => $with->id]))
            ->test(DashboardIndex::class)
            ->assertSee(__('Still to pay'))
            ->assertSee(__('Invoiced, due this month'));
        $this->assertSame(110000.0, $page->instance()->kpis['open_lots']['receivable']);

        // An admin sees the amounts themselves.
        Livewire::actingAs($this->admin)->test(DashboardIndex::class)->assertSee('$110,000.00');

        $without = Role::create(['name' => 'without-contracts']);
        $without->syncAbilities($base);
        $page = Livewire::actingAs(User::factory()->create(['role_id' => $without->id]))
            ->test(DashboardIndex::class)
            ->assertDontSee(__('Still to pay'))
            ->assertSee(__('Invoiced, due this month'));
        $this->assertNull($page->instance()->kpis['open_lots']);
    }

    public function test_the_overview_shows_the_cards_and_the_lot_table(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProjectOverview::class, ['project' => $this->project])
            ->assertSee(__('Profit or loss by lot'))
            ->assertSeeInOrder(['Lot 12', '$57,500.00', 'Lot 14', '$48,500.00', __('Project (General)'), '-$6,000.00'])
            ->assertSee('$100,000.00');
    }
}
