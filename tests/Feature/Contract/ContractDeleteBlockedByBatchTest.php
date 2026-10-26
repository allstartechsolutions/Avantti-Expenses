<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractShow;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A contract with a payment recorded cannot be deleted — money blocks the
 * delete, it never cascades. A contract paid through a payment batch cannot
 * be deleted either — the batch row
 * is a financial record and its foreign key does not cascade. Deleting one
 * used to crash the page (Sentry AVANTTI-CONSTRUCTION-7); now it is refused
 * with a message. Once its payments are deleted the batch rows record
 * nothing paid, and the contract deletes with them, whatever its status. Approving a whole batch loads its contracts in one query
 * (AVANTTI-CONSTRUCTION-8).
 */
class ContractDeleteBlockedByBatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Vendor $vendor;

    protected PaymentBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->project = Project::create([
            'project_name' => 'Obra Central',
            'client_id' => Client::create(['company_name' => 'Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'p@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->vendor = new Vendor;
        $this->vendor->forceFill(['name' => 'Wolf Framing', 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        $this->batch = PaymentBatch::create([
            'name' => 'Friday run',
            'status' => 'draft',
            'payment_date' => '2026-10-16',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function contract(string $number): Contract
    {
        return Contract::create([
            'project_id' => $this->project->id,
            'subcontractor_id' => $this->vendor->id,
            'contract_number' => $number,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 10000,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function addToBatch(Contract $contract, float $amount): PaymentBatchItem
    {
        return PaymentBatchItem::create([
            'payment_batch_id' => $this->batch->id,
            'contract_id' => $contract->id,
            'amount' => $amount,
            'payment_method' => 'check',
            'status' => 'pending',
        ]);
    }

    public function test_a_contract_paid_through_a_batch_is_refused_not_crashed(): void
    {
        $contract = $this->contract('CTR-0001');
        $this->addToBatch($contract, 500);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('approveAll');
        $this->assertSame(1, $contract->payments()->count());

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertDontSee(__('Delete Contract'))
            ->assertSee(trans_choice('This contract has :count payment recorded and cannot be deleted. Delete the payment first, or cancel the contract instead.|This contract has :count payments recorded and cannot be deleted. Delete the payments first, or cancel the contract instead.', 1, ['count' => 1]))
            ->call('delete')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
    }

    public function test_a_paid_contract_whose_batch_payments_were_deleted_deletes_with_its_batch_rows(): void
    {
        $contract = $this->contract('CTR-0003');
        $item = $this->addToBatch($contract, 10000);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('approveAll');
        $this->assertSame('paid', $contract->fresh()->status);

        $component = Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract->fresh()])
            ->call('deletePayment', $contract->payments()->value('id'));

        $component
            ->assertSee(__('Delete Contract'))
            ->call('delete')
            ->assertRedirect(route('projects.contracts', $this->project->id));

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
        $this->assertDatabaseMissing('payment_batch_items', ['id' => $item->id]);
        $this->assertDatabaseHas('payment_batches', ['id' => $this->batch->id]);
    }

    public function test_a_contract_left_paid_with_no_payments_still_deletes(): void
    {
        // A status the payments no longer support — set by hand, or left from
        // before the auto-status — does not block the delete either.
        $contract = $this->contract('CTR-0004');
        $contract->update(['status' => 'partially_paid']);
        $item = $this->addToBatch($contract, 500);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract->fresh()])
            ->call('delete')
            ->assertRedirect(route('projects.contracts', $this->project->id));

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
        $this->assertDatabaseMissing('payment_batch_items', ['id' => $item->id]);
    }

    public function test_a_directly_recorded_payment_blocks_the_delete_until_it_is_deleted(): void
    {
        $contract = $this->contract('CTR-0005');
        foreach (['2026-10-01', '2026-10-08'] as $date) {
            ContractPayment::create([
                'contract_id' => $contract->id,
                'amount' => 1000,
                'payment_date' => $date,
                'payment_method' => 'pix',
                'created_by' => $this->admin->id,
            ]);
        }

        $message = trans_choice('This contract has :count payment recorded and cannot be deleted. Delete the payment first, or cancel the contract instead.|This contract has :count payments recorded and cannot be deleted. Delete the payments first, or cancel the contract instead.', 2, ['count' => 2]);

        $component = Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertDontSee(__('Delete Contract'))
            ->assertSee($message)
            ->call('delete')
            ->assertNoRedirect();

        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
        $this->assertSame(2, ContractPayment::where('contract_id', $contract->id)->count());

        foreach ($contract->payments()->pluck('id') as $paymentId) {
            $component->call('deletePayment', $paymentId);
        }

        $component
            ->assertSee(__('Delete Contract'))
            ->call('delete')
            ->assertRedirect(route('projects.contracts', $this->project->id));

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }

    public function test_the_delete_itself_rechecks_for_a_payment_recorded_after_the_screen_checked(): void
    {
        // The window a concurrent payment would land in: the screen's check
        // has passed, the row delete has not run. The delete refuses on its
        // own rather than cascading the payment away.
        $contract = $this->contract('CTR-0006');
        $item = $this->addToBatch($contract, 500);
        ContractPayment::create([
            'contract_id' => $contract->id,
            'amount' => 500,
            'payment_date' => '2026-10-20',
            'payment_method' => 'pix',
            'created_by' => $this->admin->id,
        ]);

        $this->assertFalse($contract->deleteWithBatchItems());

        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
        $this->assertDatabaseHas('payment_batch_items', ['id' => $item->id]);
        $this->assertSame(1, ContractPayment::where('contract_id', $contract->id)->count());
    }

    public function test_taking_the_last_pending_line_out_with_its_contract_completes_the_batch(): void
    {
        $paid = $this->contract('CTR-0007');
        $approved = $this->addToBatch($paid, 500);
        $pending = $this->addToBatch($this->contract('CTR-0008'), 500);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('approveItem', $approved->id);
        $this->assertSame('partially_approved', $this->batch->fresh()->status);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $pending->contract])
            ->call('delete')
            ->assertRedirect(route('projects.contracts', $this->project->id));

        $this->assertDatabaseMissing('payment_batch_items', ['id' => $pending->id]);
        $batch = $this->batch->fresh();
        $this->assertSame('approved', $batch->status);
        // The deleter approved no payment: no approver, the last line's time.
        $this->assertNull($batch->approved_by);
        $this->assertEquals($approved->fresh()->approved_at, $batch->approved_at);
    }

    public function test_an_open_batch_editor_saves_after_one_of_its_contracts_was_deleted(): void
    {
        $gone = $this->contract('CTR-0013');
        $this->addToBatch($gone, 500);
        $kept = $this->contract('CTR-0014');
        $this->addToBatch($kept, 700);

        $editor = Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch]);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $gone])
            ->call('delete');

        $editor->set("payAmounts.{$kept->id}", 800)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('contracts', ['id' => $gone->id]);
        $this->assertSame(1, $this->batch->items()->count());
        $this->assertSame(800.0, (float) $this->batch->items()->first()->amount);
    }

    public function test_taking_the_last_approved_line_out_returns_the_batch_to_draft(): void
    {
        $contract = $this->contract('CTR-0009');
        $approved = $this->addToBatch($contract, 500);
        $pending = $this->addToBatch($this->contract('CTR-0010'), 500);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('approveItem', $approved->id);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract->fresh()])
            ->call('deletePayment', $contract->payments()->value('id'))
            ->call('delete')
            ->assertRedirect(route('projects.contracts', $this->project->id));

        $this->assertDatabaseHas('payment_batch_items', ['id' => $pending->id, 'status' => 'pending']);
        $this->assertSame('draft', $this->batch->fresh()->status);
    }

    public function test_an_approved_batch_keeps_its_approver_when_a_rejected_line_leaves(): void
    {
        $approved = $this->addToBatch($this->contract('CTR-0011'), 500);
        $rejected = $this->addToBatch($this->contract('CTR-0012'), 500);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('rejectItem', $rejected->id)
            ->call('approveItem', $approved->id);

        $batch = $this->batch->fresh();
        $this->assertSame('approved', $batch->status);
        $batch->forceFill(['approved_at' => '2026-10-16 09:00:00'])->save();

        $other = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        Livewire::actingAs($other)
            ->test(ContractShow::class, ['contract' => $rejected->contract])
            ->call('delete')
            ->assertRedirect(route('projects.contracts', $this->project->id));

        $batch->refresh();
        $this->assertSame('approved', $batch->status);
        $this->assertSame($this->admin->id, $batch->approved_by);
        $this->assertSame('2026-10-16 09:00:00', $batch->approved_at->format('Y-m-d H:i:s'));
    }

    public function test_a_contract_outside_any_batch_still_deletes(): void
    {
        $contract = $this->contract('CTR-0002');

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertSee(__('Delete Contract'))
            ->call('delete')
            ->assertRedirect(route('projects.contracts', $this->project->id));

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }

    public function test_approving_a_batch_loads_its_contracts_in_one_query(): void
    {
        foreach (['CTR-0010', 'CTR-0011', 'CTR-0012'] as $number) {
            $this->addToBatch($this->contract($number), 100);
        }

        $component = Livewire::actingAs($this->admin)->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch]);

        DB::enableQueryLog();
        $component->call('approveAll');
        // The N+1 Sentry saw: the balance query run once per row, by id, limit 1.
        $perRowLookups = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'total_paid_cents') && str_contains($q['query'], 'limit 1'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(0, $perRowLookups);
        $this->assertSame(3, ContractPayment::count());
        $this->assertSame(0, $this->batch->items()->where('status', 'pending')->count());
    }

    public function test_every_pending_item_is_listed_even_when_the_filters_would_hide_it(): void
    {
        $paid = $this->contract('CTR-0020');
        $paid->update(['status' => 'paid']);
        $this->addToBatch($paid, 100);

        $draft = $this->contract('CTR-0021');
        $draft->update(['status' => 'draft']);
        $this->addToBatch($draft, 100);

        $this->addToBatch($this->contract('CTR-0022'), 100);
        $this->contract('CTR-0023');

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->set('subcontractorFilter', '999999')
            ->assertSee(['CTR-0020', 'CTR-0021', 'CTR-0022'])
            ->assertDontSee('CTR-0023');
    }
}
