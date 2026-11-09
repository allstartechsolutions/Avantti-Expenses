<?php

namespace Tests\Feature\BackCharge;

use App\Enums\ProjectStatus;
use App\Models\BackCharge;
use App\Models\BackChargeDeduction;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountsPayableService;
use App\Services\BackChargeDeductions;
use App\Services\PaymentDetailReportService;
use App\Services\ProjectFinancials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Back charges, stage 1 (docs/back-charges.md): the remaining amount, the
 * deduction rules, and what a deduction does to its contract — a payment of
 * $1,000 with $300 deducted is a $700 check, a contract $300 cheaper and a
 * balance $1,000 lower.
 */
class BackChargeMoneyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Vendor $benitez;

    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'K. Hovnanian', 'contact_name' => 'C', 'email' => 'k@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->benitez = $this->subcontractor('Benitez Carpentry');
        $this->contract = $this->contract($this->benitez);
    }

    protected function subcontractor(string $name): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => $name, 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        return $vendor;
    }

    protected function contract(Vendor $vendor, array $attributes = []): Contract
    {
        return Contract::create(array_merge([
            'project_id' => $this->project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => Contract::generateContractNumber(),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 10000,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    protected function backCharge(float $amount, array $attributes = []): BackCharge
    {
        return BackCharge::create(array_merge([
            'number' => BackCharge::generateNumber(),
            'subcontractor_id' => $this->benitez->id,
            'amount' => $amount,
            'date' => now()->toDateString(),
            'reason' => 'Damaged drywall',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    protected function pay(Contract $contract, float $amount): ContractPayment
    {
        return ContractPayment::create([
            'contract_id' => $contract->id,
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'check',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function batchLine(Contract $contract, float $amount): PaymentBatchItem
    {
        $batch = PaymentBatch::create(['name' => 'Week 45', 'status' => 'draft', 'payment_date' => now()->toDateString(), 'created_by' => $this->admin->id]);

        return PaymentBatchItem::create(['payment_batch_id' => $batch->id, 'contract_id' => $contract->id, 'amount' => $amount, 'status' => 'pending']);
    }

    public function test_numbers_count_numerically(): void
    {
        $this->assertSame('BC-0001', $this->backCharge(10)->number);
        BackCharge::query()->update(['number' => 'BC-9999']);
        $this->assertSame('BC-10000', BackCharge::generateNumber());
    }

    public function test_paying_1000_with_300_deducted(): void
    {
        $bc = $this->backCharge(500);
        $payment = $this->pay($this->contract, 1000);

        app(BackChargeDeductions::class)->deductFromPayment($payment, [$bc->id => 300]);

        $this->assertSame(700.0, $payment->fresh()->getNetAmount(), 'the check');
        $this->assertSame(200.0, $bc->fresh()->getRemaining());
        $this->assertFalse($bc->fresh()->isFullyDeducted());

        $contract = $this->contract->fresh();
        $this->assertSame(10000.0, $contract->getAdjustedAmount(), 'gross, for installments and retention');
        $this->assertSame(300.0, $contract->getBackChargesDeducted());
        $this->assertSame(9700.0, $contract->getNetAmount());
        $this->assertSame(700.0, $contract->getCashPaid());
        $this->assertSame(9000.0, $contract->getBalanceDue(), 'down by the whole $1,000');

        $listed = Contract::withBackChargesDeducted()->find($contract->id);
        $this->assertSame(9700.0, $listed->getNetAmount(), 'the query sum agrees with the model');
    }

    public function test_the_rest_comes_off_a_later_payment_on_another_contract(): void
    {
        $bc = $this->backCharge(1000);
        $other = $this->contract($this->benitez);

        app(BackChargeDeductions::class)->deductFromPayment($this->pay($this->contract, 2500), [$bc->id => 600]);
        app(BackChargeDeductions::class)->deductFromPayment($this->pay($other, 800), [$bc->id => 400]);

        $this->assertTrue($bc->fresh()->isFullyDeducted());
        $this->assertSame(0, BackCharge::open()->count());
        $this->assertSame(400.0, $other->fresh()->getBackChargesDeducted());
        $this->assertSame(400.0, $other->fresh()->getCashPaid());
    }

    public function test_never_more_than_remaining_nor_more_than_the_payment(): void
    {
        $bc = $this->backCharge(300);
        $deduct = app(BackChargeDeductions::class);

        try {
            $deduct->deductFromPayment($this->pay($this->contract, 1000), [$bc->id => 301]);
            $this->fail('More than remaining was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey("deductions.{$bc->id}", $e->errors());
        }

        $big = $this->backCharge(5000);
        try {
            $deduct->deductFromPayment($this->pay($this->contract, 1000), [$big->id => 1000.01]);
            $this->fail('A negative check was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('deductions', $e->errors());
        }

        $this->assertSame(0, BackChargeDeduction::count());
    }

    public function test_only_the_company_and_the_employee_named_on_the_contract(): void
    {
        $maria = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Maria Benitez']);
        $jose = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Jose Ruiz']);
        $company = $this->backCharge(100);
        $mine = $this->backCharge(100, ['subcontractor_employee_id' => $maria->id]);
        $his = $this->backCharge(100, ['subcontractor_employee_id' => $jose->id]);
        $foreign = $this->backCharge(100, ['subcontractor_id' => $this->subcontractor('Wolf Construction')->id]);

        $mariasContract = $this->contract($this->benitez, ['subcontractor_employee_id' => $maria->id]);

        $this->assertEqualsCanonicalizing([$company->id, $mine->id], BackCharge::deductibleFrom($mariasContract)->pluck('id')->all());
        $this->assertSame([$company->id], BackCharge::deductibleFrom($this->contract)->pluck('id')->all());

        foreach ([$his, $foreign] as $bc) {
            try {
                app(BackChargeDeductions::class)->deductFromPayment($this->pay($mariasContract, 500), [$bc->id => 50]);
                $this->fail("{$bc->number} was deducted from a contract it does not belong to.");
            } catch (ValidationException) {
            }
        }
    }

    public function test_a_batch_line_reserves_then_takes_or_releases(): void
    {
        $bc = $this->backCharge(500);
        $service = app(BackChargeDeductions::class);

        $line = $this->batchLine($this->contract, 1000);
        $service->syncForBatchItem($line, [$bc->id => 300]);

        $this->assertSame(200.0, $bc->fresh()->getRemaining(), 'reserved while pending');
        $this->assertSame(0.0, $this->contract->fresh()->getBackChargesDeducted(), 'not taken yet');
        $this->assertSame(700.0, $line->fresh()->getNetAmount());

        // Editing the line replaces what it deducts.
        $service->syncForBatchItem($line, [$bc->id => 450]);
        $this->assertSame(50.0, $bc->fresh()->getRemaining());
        $service->syncForBatchItem($line, [$bc->id => '']);
        $this->assertSame(500.0, $bc->fresh()->getRemaining());

        // Approved: its payment takes it.
        $service->syncForBatchItem($line, [$bc->id => 300]);
        $payment = $this->pay($this->contract, 1000);
        $service->takeForApprovedItem($line, $payment);
        $this->assertSame(300.0, $this->contract->fresh()->getBackChargesDeducted());

        // Rejected: what a second line reserved goes back.
        $second = $this->batchLine($this->contract, 1000);
        $service->syncForBatchItem($second, [$bc->id => 200]);
        $this->assertTrue($bc->fresh()->isFullyDeducted());
        $service->releaseForBatchItem($second);
        $this->assertSame(200.0, $bc->fresh()->getRemaining());
    }

    public function test_deleting_the_payment_reopens_the_back_charge(): void
    {
        $bc = $this->backCharge(300);
        $payment = $this->pay($this->contract, 1000);
        app(BackChargeDeductions::class)->deductFromPayment($payment, [$bc->id => 300]);

        $payment->delete();

        $this->assertSame(300.0, $bc->fresh()->getRemaining());
        $this->assertSame(0.0, $this->contract->fresh()->getBackChargesDeducted());
    }

    /** Every figure that shows a contract's cost or the cash paid reads them net. */
    public function test_the_reports_read_the_net_contract_and_the_cash(): void
    {
        $bc = $this->backCharge(500);
        $payment = $this->pay($this->contract, 1000);
        app(BackChargeDeductions::class)->deductFromPayment($payment, [$bc->id => 300]);

        $figures = ProjectFinancials::forProject($this->project)['totals'];
        $this->assertSame(9700.0, $figures['contracts'], 'the job site cost');
        $this->assertSame(700.0, $figures['paid']);
        $this->assertSame(9000.0, $figures['unpaid']);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $row = (new PaymentDetailReportService($from, $to))->rows()->firstWhere('type', 'contract');
        $this->assertSame(700.0, $row['amount'], 'Payment Detail shows the check');
        $this->assertSame([['number' => $bc->number, 'reason' => 'Damaged drywall', 'amount' => 300.0]], $row['back_charges']);

        $payable = new AccountsPayableService($from, $to, '', 'all');
        $summary = $payable->subcontractorSummary()->sole();
        $this->assertSame([9700.0, 700.0, 9000.0], [$summary['contract_value'], $summary['paid'], $summary['outstanding']]);
        $this->assertSame(700.0, $payable->kpis()['paid_subcontractors'], 'Accounts Payable cash out');
    }
}
