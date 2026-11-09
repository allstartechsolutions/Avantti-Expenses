<?php

namespace Tests\Feature\BackCharge;

use App\Enums\ProjectStatus;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Livewire\PaymentBatch\PaymentBatchShow;
use App\Models\BackCharge;
use App\Models\BackChargeDeduction;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Back charges, stage 3 (docs/back-charges.md): deducted in a payment batch.
 * Set aside while the line is pending, taken when it is approved, given back
 * when it is rejected or the batch is cancelled.
 */
class BackChargeBatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Vendor $benitez;

    protected Contract $contract;

    protected PaymentBatch $batch;

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
            'project_id' => $project->id, 'subcontractor_id' => $this->benitez->id, 'contract_number' => 'CTR-1204',
            'status' => 'active', 'start_date' => now()->toDateString(), 'amount' => 10000, 'created_by' => $this->admin->id,
        ]);

        $this->batch = PaymentBatch::create(['name' => 'Week 45', 'status' => 'draft', 'payment_date' => now()->toDateString(), 'created_by' => $this->admin->id]);
    }

    protected function backCharge(float $amount, string $reason = 'Damaged drywall'): BackCharge
    {
        return BackCharge::create([
            'number' => BackCharge::generateNumber(), 'subcontractor_id' => $this->benitez->id, 'amount' => $amount,
            'date' => now()->toDateString(), 'reason' => $reason, 'created_by' => $this->admin->id,
        ]);
    }

    protected function screen(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->admin)->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch]);
    }

    protected function line(): PaymentBatchItem
    {
        return PaymentBatchItem::where('payment_batch_id', $this->batch->id)->sole();
    }

    public function test_pay_1000_deduct_300_and_approve(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;

        $screen = $this->screen()
            ->assertSee($bc->number)->assertSee(__('Deduct now'))
            ->set("payAmounts.$id", '1000')
            ->set("payDeductions.$id.{$bc->id}", '300')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertSame(200.0, $bc->fresh()->getRemaining(), 'set aside while pending');
        $this->assertSame(0.0, $this->contract->fresh()->getBackChargesDeducted());
        $this->assertSame(700.0, $this->line()->getNetAmount());

        // Reloading the screen keeps what was typed.
        $this->screen()->assertSet("payDeductions.$id.{$bc->id}", '300.00');

        $screen->call('approveItem', $this->line()->id)->assertHasNoErrors();

        $payment = ContractPayment::sole();
        $this->assertSame(1000.0, $payment->amount);
        $this->assertSame(700.0, $payment->getNetAmount(), 'the check');
        $contract = $this->contract->fresh();
        $this->assertSame(9700.0, $contract->getNetAmount());
        $this->assertSame(700.0, $contract->getCashPaid());
        $this->assertSame(9000.0, $contract->getBalanceDue());
        $this->assertSame($payment->id, BackChargeDeduction::sole()->contract_payment_id);
    }

    public function test_the_view_the_csv_and_the_pdf_show_the_deduction(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;
        $this->screen()->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft')
            ->call('approveItem', $this->line()->id);

        Livewire::actingAs($this->admin)->test(PaymentBatchShow::class, ['paymentBatch' => $this->batch->fresh()])
            ->assertSee($bc->number)
            ->assertSee(__('Check: :amount', ['amount' => '$700.00']))
            ->assertViewHas('items', fn ($items) => $items->first()->backChargeDeductions->count() === 1)
            ->call('exportCsv')
            ->assertFileDownloaded();

        $this->actingAs($this->admin)->get(route('payment-batches.pdf.view', $this->batch))->assertOk();
    }

    public function test_never_more_than_remaining_nor_than_the_line(): void
    {
        $bc = $this->backCharge(300);
        $id = $this->contract->id;

        $this->screen()
            ->set("payAmounts.$id", '1000')
            ->set("payDeductions.$id.{$bc->id}", '350')
            ->call('saveDraft')
            ->assertHasErrors("payDeductions.$id.{$bc->id}");

        $big = $this->backCharge(5000);
        $this->screen()
            ->set("payAmounts.$id", '100')
            ->set("payDeductions.$id.{$big->id}", '150')
            ->call('saveDraft')
            ->assertHasErrors("payDeductions.$id");

        $this->assertSame(0, BackChargeDeduction::count());
        $this->assertSame(0, PaymentBatchItem::count(), 'nothing is half-saved');
    }

    public function test_a_second_batch_cannot_take_what_the_first_set_aside(): void
    {
        $bc = $this->backCharge(300);
        $id = $this->contract->id;
        $this->screen()->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft');

        $other = PaymentBatch::create(['name' => 'Week 46', 'status' => 'draft', 'payment_date' => now()->toDateString(), 'created_by' => $this->admin->id]);
        Livewire::actingAs($this->admin)->test(PaymentBatchEdit::class, ['paymentBatch' => $other])
            ->assertDontSee($bc->number)
            ->set("payAmounts.$id", '500')
            ->set("payDeductions.$id.{$bc->id}", '100')
            ->call('saveDraft')
            ->assertHasErrors("payDeductions.$id.{$bc->id}");
    }

    public function test_rejecting_or_cancelling_gives_it_back(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;

        $this->screen()->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft')
            ->call('rejectItem', $this->line()->id);
        $this->assertSame(500.0, $bc->fresh()->getRemaining());

        $this->line()->delete();
        $this->screen()->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft')
            ->call('rejectAllPending');
        $this->assertSame(500.0, $bc->fresh()->getRemaining());

        $this->line()->delete();
        $this->screen()->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft')
            ->call('cancelBatch');
        $this->assertSame(500.0, $bc->fresh()->getRemaining());
        $this->assertSame(0, BackChargeDeduction::count());
    }

    public function test_clearing_the_line_gives_it_back(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;

        $screen = $this->screen()->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft');
        $screen->set("payDeductions.$id.{$bc->id}", '')->call('saveDraft');
        $this->assertSame(500.0, $bc->fresh()->getRemaining(), 'emptying the box');

        $screen->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft');
        $screen->set("payAmounts.$id", '')->set("payDeductions.$id.{$bc->id}", '')->call('saveDraft');
        $this->assertSame(0, PaymentBatchItem::count());
        $this->assertSame(500.0, $bc->fresh()->getRemaining(), 'removing the line');
    }

    public function test_without_the_grant_the_line_is_saved_around_its_deductions(): void
    {
        $bc = $this->backCharge(500);
        $id = $this->contract->id;
        $this->screen()->set("payAmounts.$id", '1000')->set("payDeductions.$id.{$bc->id}", '300')->call('saveDraft');

        $role = Role::create(['name' => 'batcher']);
        $role->syncAbilities(['payments.batch', 'payments.view']);
        $batcher = User::factory()->create(['role_id' => $role->id]);

        $screen = $this->screen($batcher)
            ->assertDontSee(__('Deduct now'))
            ->assertSee($bc->number) // what the line deducts is still shown
            ->set("payDeductions.$id.{$bc->id}", '0')
            ->set("payNotes.$id", 'Week 45')
            ->call('saveDraft')
            ->assertHasNoErrors();
        $this->assertSame(300.0, $this->line()->getBackChargesDeducted(), 'kept, whatever the browser sent');

        $screen->set("payAmounts.$id", '200')->call('saveDraft')->assertHasErrors("payAmounts.$id");
        $this->assertSame(1000.0, $this->line()->fresh()->amount);
    }

    public function test_the_employee_s_back_charge_only_on_the_employee_s_contract(): void
    {
        $maria = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Maria Benitez']);
        $bc = BackCharge::create([
            'number' => BackCharge::generateNumber(), 'subcontractor_id' => $this->benitez->id, 'subcontractor_employee_id' => $maria->id,
            'amount' => 200, 'date' => now()->toDateString(), 'reason' => 'Lost tools', 'created_by' => $this->admin->id,
        ]);

        $this->screen()->assertDontSee($bc->number);

        $this->contract->update(['subcontractor_employee_id' => $maria->id]);
        $this->screen()->assertSee($bc->number);
    }
}
