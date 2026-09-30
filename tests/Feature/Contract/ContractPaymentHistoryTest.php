<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractShow;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Livewire\Report\PaymentDetailReport;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PaymentDetailReportService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Everything written on a payment is readable where the payment is listed:
 * the contract's payment history and the payment detail report (screen and
 * CSV). A payment approved from a batch also carries the batch's own notes,
 * which were not linked to the payment at all before 29 Sep 2026.
 */
class ContractPaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $project = Project::create([
            'project_name' => 'Ours',
            'client_id' => Client::create(['company_name' => 'History Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'ours-history@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'Sub History', 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();

        $this->contract = Contract::create([
            'project_id' => $project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CT-HIST',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 10000,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function payWithNotes(): ContractPayment
    {
        return $this->contract->payments()->create([
            'amount' => 1500,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'check',
            'reference_number' => 'CHK-4471',
            'phase' => 'Foundation',
            'notes' => "Paid after inspection.\nSecond line of the note.",
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_contract_page_shows_every_field_of_a_payment(): void
    {
        $this->payWithNotes();

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $this->contract])
            ->assertSee('CHK-4471')
            ->assertSee('Foundation')
            ->assertSee('Paid after inspection.')
            ->assertSee('Second line of the note.')
            ->assertSee(__('Recorded by :name on :date', [
                'name' => $this->admin->name,
                'date' => ContractPayment::first()->created_at->appDateTime(),
            ]));
    }

    public function test_a_batch_payment_is_linked_and_shows_the_batch_and_its_notes(): void
    {
        $batch = PaymentBatch::create([
            'name' => 'Friday run',
            'status' => 'draft',
            'payment_date' => now()->toDateString(),
            'notes' => 'Approved by the owner on the call.',
            'created_by' => $this->admin->id,
        ]);
        $item = PaymentBatchItem::create([
            'payment_batch_id' => $batch->id,
            'contract_id' => $this->contract->id,
            'amount' => 800,
            'payment_method' => 'bank_transfer',
            'phase' => 'Framing',
            'notes' => 'Line note for this contract.',
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $batch])
            ->call('approveItem', $item->id);

        $payment = $this->contract->payments()->first();
        $this->assertNotNull($payment, 'The batch line was paid.');
        $this->assertSame($item->id, $payment->payment_batch_item_id);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $this->contract])
            ->assertSee('Friday run')
            ->assertSee('Framing')
            ->assertSee('Line note for this contract.')
            ->assertSee('Approved by the owner on the call.');

        $row = (new PaymentDetailReportService(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), '', '', '', '', '', [], 'all'))
            ->rows()
            ->firstWhere('type', 'contract');
        $this->assertSame('Approved by the owner on the call.', $row['batch_notes']);
    }

    public function test_the_payment_detail_report_shows_reference_phase_and_notes(): void
    {
        $this->payWithNotes();

        $row = (new PaymentDetailReportService(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), '', '', '', '', '', [], 'all'))
            ->rows()
            ->firstWhere('type', 'contract');

        $this->assertSame('CHK-4471', $row['reference']);
        $this->assertSame('Foundation', $row['phase']);
        $this->assertStringContainsString('Paid after inspection.', $row['notes']);

        $component = Livewire::actingAs($this->admin)
            ->test(PaymentDetailReport::class)
            ->set('fromDate', now()->startOfMonth()->toDateString())
            ->set('toDate', now()->endOfMonth()->toDateString())
            ->assertSee('CHK-4471')
            ->assertSee('Paid after inspection.');

        ob_start();
        $component->instance()->exportCsv()->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString(__('Notes'), $csv);
        $this->assertStringContainsString('CHK-4471', $csv);
        $this->assertStringContainsString('Foundation', $csv);
        $this->assertStringContainsString('Second line of the note.', $csv);
    }
}
