<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractPayments;
use App\Livewire\Contract\ContractShow;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractChangeOrder;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The balance due on a contract is the ADJUSTED amount — the original plus
 * or minus its change orders — less what was paid. The contract payments
 * batch screen checked a payment against the original amount alone, so a
 * contract raised at zero and then valued by change orders could never be
 * paid: "exceeds balance due (0.00)". Its summary cards had the same blind
 * spot. The single contract screen already used the adjusted figure.
 */
class ContractPaymentBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->project = Project::create([
            'project_name' => 'Ours',
            'client_id' => Client::create(['company_name' => 'Balance Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'ours-bal@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeContract(float $amount): Contract
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'Sub '.str()->random(5), 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();

        return Contract::create([
            'project_id' => $this->project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function changeOrder(Contract $contract, float $amount): ContractChangeOrder
    {
        return ContractChangeOrder::create([
            'contract_id' => $contract->id,
            'title' => 'Extra',
            'date' => now()->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_a_contract_valued_only_by_change_orders_can_be_paid_from_the_batch_screen(): void
    {
        $contract = $this->makeContract(0);
        $this->changeOrder($contract, 5000);

        $this->assertEquals(5000, $contract->getBalanceDue());

        // Up to the adjusted balance goes through.
        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->set('payAmounts.'.$contract->id, '5000')
            ->set('payMethods.'.$contract->id, 'bank_transfer')
            ->call('processPayments');

        $this->assertSame(1, $contract->payments()->count());
        $this->assertEquals(5000, $contract->fresh()->getAmountPaid());
        $this->assertSame('paid', $contract->fresh()->status);
    }

    public function test_the_batch_screen_refuses_more_than_the_adjusted_balance_and_says_the_real_figure(): void
    {
        $contract = $this->makeContract(1000);
        $this->changeOrder($contract, -400);   // adjusted 600

        $component = Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->set('payAmounts.'.$contract->id, '601')
            ->call('processPayments');

        $this->assertSame(0, $contract->payments()->count());
        $component->assertSee(__('Payment for :number exceeds the balance due of :balance.', [
            'number' => $contract->contract_number,
            'balance' => \Illuminate\Support\Number::currency(600, config('app.currency'), config('app.locale')),
        ]));

        $component->set('payAmounts.'.$contract->id, '600')->call('processPayments');
        $this->assertEquals(600, $contract->fresh()->getAmountPaid());
        $this->assertSame('paid', $contract->fresh()->status);
    }

    public function test_each_row_names_the_employee_assigned_to_the_contract(): void
    {
        $contract = $this->makeContract(1000);
        $employee = \App\Models\SubcontractorEmployee::create([
            'subcontractor_id' => $contract->subcontractor_id,
            'name' => 'Maria Souza',
            'title' => 'Foreman',
        ]);
        $contract->update(['subcontractor_employee_id' => $employee->id]);

        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->assertSee($contract->subcontractor->company_name)
            ->assertSee('Maria Souza');
    }

    public function test_the_table_sorts_by_lot_contract_amount_paid_and_balance(): void
    {
        $site = fn (string $name) => \App\Models\JobSite::create([
            'project_id' => $this->project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str()->random(6).'@example.test',
            'status' => \App\Enums\JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $small = $this->makeContract(1000);
        $small->update(['contract_number' => 'CTR-0010', 'job_site_id' => $site('Lot 2')->id]);
        $large = $this->makeContract(5000);
        $large->update(['contract_number' => 'CTR-0009', 'job_site_id' => $site('Lot 10')->id]);
        $general = $this->makeContract(3000);
        $general->update(['contract_number' => 'CTR-0011']);

        // Paid: small 900 (balance 100), large 1000 (balance 4000), general 0 (balance 3000).
        foreach ([[$small, 900], [$large, 1000]] as [$contract, $amount]) {
            $contract->payments()->create(['amount' => $amount, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash', 'created_by' => $this->admin->id]);
        }

        $component = Livewire::actingAs($this->admin)->test(ContractPayments::class);
        $order = fn () => $component->instance()->contracts()->pluck('id')->all();

        $component->call('sort', 'job_site');
        $this->assertSame([$small->id, $large->id, $general->id], $order(), 'Natural order, project level last.');
        $component->call('sort', 'job_site');
        $this->assertSame([$large->id, $small->id, $general->id], $order(), 'Project level stays last descending.');

        $component->call('sort', 'contract');
        $this->assertSame([$large->id, $small->id, $general->id], $order());

        $component->call('sort', 'amount');
        $this->assertSame([$large->id, $general->id, $small->id], $order(), 'Money starts largest first.');

        $component->call('sort', 'paid');
        $this->assertSame([$large->id, $small->id, $general->id], $order());

        $component->call('sort', 'balance');
        $this->assertSame([$large->id, $general->id, $small->id], $order());
        $component->call('sort', 'balance');
        $this->assertSame([$small->id, $general->id, $large->id], $order());

        $component->call('sort', 'project_id; drop table contracts');
        $this->assertSame('balance', $component->get('sortField'), 'Unknown columns are ignored.');
    }

    public function test_the_summary_cards_count_the_adjusted_value_and_balance(): void
    {
        $zero = $this->makeContract(0);
        $this->changeOrder($zero, 5000);

        $reduced = $this->makeContract(1000);
        $this->changeOrder($reduced, -400);
        $reduced->payments()->create([
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'created_by' => $this->admin->id,
        ]);

        $summary = Livewire::actingAs($this->admin)->test(ContractPayments::class)->instance()->summary();

        $this->assertEquals(5600, $summary['total_value'], 'Original plus change orders.');
        $this->assertEquals(5500, $summary['pending_balance'], 'Adjusted less paid.');
    }

    public function test_the_single_contract_screen_agrees(): void
    {
        $contract = $this->makeContract(0);
        $this->changeOrder($contract, 5000);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('openPaymentModal')
            ->set('paymentAmount', 5000)
            ->set('paymentDate', now()->toDateString())
            ->set('paymentMethod', 'bank_transfer')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertEquals(5000, $contract->fresh()->getAmountPaid());
    }
}
