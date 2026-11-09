<?php

namespace Tests\Feature\BackCharge;

use App\Enums\ProjectStatus;
use App\Livewire\BackCharge\BackChargeIndex;
use App\Livewire\Contract\ContractPayments;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Models\BackCharge;
use App\Models\BackChargeDeduction;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BackChargeDeductions;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Codex review of the back-charge module, 9 Nov 2026: every finding pinned.
 */
class BackChargeReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Vendor $benitez;

    protected Project $project;

    protected Contract $contract;

    protected PaymentBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'K. Hovnanian', 'contact_name' => 'C', 'email' => 'k@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C', 'email' => 'kent@example.test', 'status' => ProjectStatus::CREATED, 'created_by' => $this->admin->id,
        ]);
        $this->benitez = $this->vendor('Benitez Carpentry');
        $this->contract = $this->contract($this->benitez, 'CTR-1204');
        $this->batch = PaymentBatch::create(['name' => 'Week 45', 'status' => 'draft', 'payment_date' => now()->toDateString(), 'created_by' => $this->admin->id]);
    }

    protected function vendor(string $name): Vendor
    {
        $v = new Vendor;
        $v->forceFill(['name' => $name, 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        return $v;
    }

    protected function contract(Vendor $vendor, string $number): Contract
    {
        return Contract::create([
            'project_id' => $this->project->id, 'subcontractor_id' => $vendor->id, 'contract_number' => $number,
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

    /** P1: a stale screen saved after the line was approved leaves the payment and its check alone. */
    public function test_a_stale_save_never_rewrites_an_approved_line(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;

        $stale = Livewire::actingAs($this->admin)->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft');

        // Somebody else approves it.
        Livewire::actingAs($this->admin)->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('approveItem', PaymentBatchItem::sole()->id);

        $stale->set("payDeductions.$id.{$bc->id}", '')->call('saveDraft');

        $line = PaymentBatchItem::sole();
        $this->assertSame('approved', $line->status, 'not turned back to pending');
        $this->assertSame(700.0, ContractPayment::sole()->getNetAmount(), 'the check is unchanged');
        $this->assertNotNull(BackChargeDeduction::sole()->contract_payment_id);

        $this->expectException(ValidationException::class);
        app(BackChargeDeductions::class)->syncForBatchItem($line, []);
    }

    /** P2: the contract changed hands after the line was saved — its debt is not taken from the new payee. */
    public function test_approval_refuses_a_back_charge_the_new_payee_does_not_owe(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;

        Livewire::actingAs($this->admin)->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft');

        $this->contract->update(['subcontractor_id' => $this->vendor('Wolf Construction')->id]);

        Livewire::actingAs($this->admin)->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('approveItem', PaymentBatchItem::sole()->id)
            ->call('approveAll');

        $this->assertSame(0, ContractPayment::count());
        $this->assertSame('pending', PaymentBatchItem::sole()->status);
    }

    /** P1: the remaining amount is read under the lock, from what is committed now. */
    public function test_the_remaining_is_read_fresh_inside_the_lock(): void
    {
        $bc = $this->backCharge(300);
        $bc->getRemainingCents(); // a stale read on this instance
        BackChargeDeduction::create(['back_charge_id' => $bc->id, 'contract_id' => $this->contract->id, 'amount' => 300]);

        $payment = ContractPayment::create(['contract_id' => $this->contract->id, 'amount' => 1000, 'payment_date' => now()->toDateString(), 'payment_method' => 'check', 'created_by' => $this->admin->id]);

        $this->expectException(ValidationException::class);
        app(BackChargeDeductions::class)->deductFromPayment($payment, [$bc->id => 1]);
    }

    /** P2: the company-wide total in the subtitle follows the money grant like the cards. */
    public function test_the_index_subtitle_total_is_a_rollup(): void
    {
        $this->backCharge(1234.56);
        $role = Role::create(['name' => 'clerk']);
        $role->syncAbilities(['back-charges.view']);
        $clerk = User::factory()->create(['role_id' => $role->id]);

        $resolver = $this->mock(PermissionResolver::class, function ($mock) {
            $mock->shouldReceive('canSeeMoney')->andReturn(false);
            $mock->shouldReceive('allows')->andReturn(true);
            $mock->shouldIgnoreMissing(true);
        });

        Livewire::actingAs($clerk)->test(BackChargeIndex::class)
            ->assertDontSee(__('Of :total charged in all', ['total' => '$1,234.56']));
    }

    /** P2: a back charge nothing was deducted from can be waived: lowered to zero. */
    public function test_an_untouched_back_charge_can_be_waived(): void
    {
        $bc = $this->backCharge(500);

        Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->call('openBackChargeEdit', $bc->id)
            ->set('bc_amount', '0')
            ->set('bc_description', 'Waived after the walk-through.')
            ->call('saveBackCharge')
            ->assertHasNoErrors();

        $this->assertTrue($bc->fresh()->isWaived());
        $this->assertSame(__('Waived'), $bc->fresh()->getStatusLabel());
        $this->assertSame(0, BackCharge::open()->count());

        Livewire::actingAs($this->admin)->test(BackChargeIndex::class)
            ->call('openBackChargeCreate')
            ->set('bc_subcontractor_id', (string) $this->benitez->id)
            ->set('bc_amount', '0')->set('bc_reason', 'x')
            ->call('saveBackCharge')
            ->assertHasErrors('bc_amount');
    }

    /** P2: Paid sorts by the cash it shows. */
    public function test_paid_sorts_by_the_cash_shown(): void
    {
        $bc = $this->backCharge(900);
        $other = $this->contract($this->benitez, 'CTR-0002');
        $big = ContractPayment::create(['contract_id' => $this->contract->id, 'amount' => 1000, 'payment_date' => now()->toDateString(), 'payment_method' => 'check', 'created_by' => $this->admin->id]);
        app(BackChargeDeductions::class)->deductFromPayment($big, [$bc->id => 900]); // $100 in cash
        ContractPayment::create(['contract_id' => $other->id, 'amount' => 500, 'payment_date' => now()->toDateString(), 'payment_method' => 'check', 'created_by' => $this->admin->id]);

        $order = Livewire::actingAs($this->admin)->test(ContractPayments::class)
            ->call('sort', 'paid')
            ->instance()->contracts()->pluck('contract_number')->all();

        // Money sorts largest first: $500 cash, then $100 (gross it would be $1,000 first).
        $this->assertSame(['CTR-0002', 'CTR-1204'], $order);
    }
}
