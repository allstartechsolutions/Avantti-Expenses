<?php

namespace Tests\Feature\BackCharge;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractPayments;
use App\Livewire\Contract\ContractShow;
use App\Models\BackCharge;
use App\Models\BackChargeDeduction;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
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
 * Back charges, stage 4 (docs/back-charges.md): deducted from a payment
 * recorded on Contract Payments or on the contract page.
 */
class BackChargeContractPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Vendor $benitez;

    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'K. Hovnanian', 'contact_name' => 'C', 'email' => 'k@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C', 'email' => 'kent@example.test', 'status' => ProjectStatus::CREATED, 'created_by' => $this->admin->id,
        ]);

        $this->benitez = new Vendor;
        $this->benitez->forceFill(['name' => 'Benitez Carpentry', 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        $this->contract = Contract::create([
            'project_id' => $project->id, 'subcontractor_id' => $this->benitez->id, 'contract_number' => 'CTR-1311',
            'status' => 'active', 'start_date' => now()->toDateString(), 'amount' => 10000, 'created_by' => $this->admin->id,
        ]);
    }

    protected function backCharge(float $amount): BackCharge
    {
        return BackCharge::create([
            'number' => BackCharge::generateNumber(), 'subcontractor_id' => $this->benitez->id, 'amount' => $amount,
            'date' => now()->toDateString(), 'reason' => 'Damaged drywall', 'created_by' => $this->admin->id,
        ]);
    }

    public function test_contract_payments_pay_1000_deduct_300(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;

        Livewire::actingAs($this->admin)->test(ContractPayments::class)
            ->assertSee($bc->number)
            ->set("payAmounts.$id", '1000')
            ->set("payDeductions.$id.{$bc->id}", '300')
            ->call('processPayments')
            ->assertHasNoErrors()
            ->assertSet('payDeductions', [])
            ->assertSee(__('−:amount back charges', ['amount' => '$300.00']));

        $payment = ContractPayment::sole();
        $this->assertSame([1000.0, 700.0], [$payment->amount, $payment->getNetAmount()]);
        $this->assertSame(9700.0, $this->contract->fresh()->getNetAmount());
        $this->assertSame(200.0, $bc->fresh()->getRemaining());
    }

    public function test_contract_payments_refuses_too_much_and_pays_nothing(): void
    {
        $bc = $this->backCharge(200);
        $id = $this->contract->id;

        Livewire::actingAs($this->admin)->test(ContractPayments::class)
            ->set("payAmounts.$id", '1000')
            ->set("payDeductions.$id.{$bc->id}", '250')
            ->call('processPayments')
            ->assertHasErrors("payDeductions.$id.{$bc->id}");

        $this->assertSame(0, ContractPayment::count(), 'rolled back with the deduction');

        Livewire::actingAs($this->admin)->test(ContractPayments::class)
            ->set("payDeductions.$id.{$bc->id}", '100')
            ->call('processPayments')
            ->assertHasErrors("payDeductions.$id");
        $this->assertSame(0, BackChargeDeduction::count());
    }

    public function test_without_the_grant_contract_payments_pays_without_deducting(): void
    {
        $bc = $this->backCharge(500);
        $role = Role::create(['name' => 'payer']);
        $role->syncAbilities(['payments.view', 'payments.pay']);
        $payer = User::factory()->create(['role_id' => $role->id]);
        $id = $this->contract->id;

        Livewire::actingAs($payer)->test(ContractPayments::class)
            ->assertDontSee($bc->number)
            ->set("payAmounts.$id", '1000')
            ->set("payDeductions.$id.{$bc->id}", '300')
            ->call('processPayments')
            ->assertHasNoErrors();

        $this->assertSame(1, ContractPayment::count());
        $this->assertSame(0, BackChargeDeduction::count(), 'what the browser sent is ignored');
    }

    public function test_the_contract_page_records_a_payment_with_a_deduction(): void
    {
        $bc = $this->backCharge(500);

        Livewire::actingAs($this->admin)->test(ContractShow::class, ['contract' => $this->contract])
            ->call('openPaymentModal')
            ->assertSee($bc->number)
            ->set('paymentAmount', '1000')
            ->set("paymentDeductions.{$bc->id}", '600')
            ->call('recordPayment')
            ->assertHasErrors("paymentDeductions.{$bc->id}")
            ->set("paymentDeductions.{$bc->id}", '500')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $payment = ContractPayment::sole();
        $this->assertSame(500.0, $payment->getNetAmount());
        $this->assertTrue($bc->fresh()->isFullyDeducted());
        $this->assertSame(9500.0, $this->contract->fresh()->getNetAmount());
        $this->assertSame(9000.0, $this->contract->fresh()->getBalanceDue());
    }
}
