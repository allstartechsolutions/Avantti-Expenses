<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractChangeOrders;
use App\Livewire\Contract\ContractEdit;
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
 * A contract's status follows its money, and every change to that money is
 * recorded. A paid contract whose price was then raised kept saying "Paid"
 * (29 Sep 2026): the edit screen never re-derived the status, and nothing
 * recorded who changed the price or from what.
 */
class ContractChangeTrackingTest extends TestCase
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
            'client_id' => Client::create(['company_name' => 'Tracking Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'ours-track@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeContract(float $amount, string $status = 'active'): Contract
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => 'Sub '.str()->random(5), 'is_subcontractor' => true, 'created_by' => $this->admin->id])->save();

        $contract = Contract::create([
            'project_id' => $this->project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => $status,
            'start_date' => now()->toDateString(),
            'amount' => $amount,
            'created_by' => $this->admin->id,
        ]);
        $contract->recordStatusChange($this->admin, null, $status);

        return $contract;
    }

    protected function pay(Contract $contract, float $amount): void
    {
        $contract->payments()->create([
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'created_by' => $this->admin->id,
        ]);
        $contract->refresh()->updateStatusFromPayments();
    }

    public function test_raising_the_price_of_a_paid_contract_makes_it_partially_paid_and_records_the_change(): void
    {
        $contract = $this->makeContract(1000);
        $this->pay($contract, 1000);
        $this->assertSame('paid', $contract->fresh()->status);

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->set('amount', '1500')
            ->call('save')
            ->assertHasNoErrors();

        $contract->refresh();
        $this->assertSame('partially_paid', $contract->status);

        $entry = $contract->changeHistories()->first();
        $this->assertSame('edited', $entry->action);
        $this->assertSame($this->admin->id, $entry->changed_by);
        $this->assertEquals(['old' => 1000, 'new' => 1500], $entry->changes['amount']);
        $this->assertSame(['old' => 'paid', 'new' => 'partially_paid'], $entry->changes['status']);

        $status = $contract->statusHistories()->latest('id')->first();
        $this->assertSame('paid', $status->old_status);
        $this->assertSame('partially_paid', $status->new_status);
        $this->assertSame($this->admin->id, $status->changed_by);
    }

    public function test_lowering_the_price_to_what_was_paid_makes_it_paid(): void
    {
        $contract = $this->makeContract(1000);
        $this->pay($contract, 600);
        $this->assertSame('partially_paid', $contract->fresh()->status);

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->set('amount', '600')
            ->call('save');

        $this->assertSame('paid', $contract->fresh()->status);
    }

    public function test_an_edit_that_leaves_the_amount_alone_keeps_a_status_set_by_hand(): void
    {
        // Marked paid by hand, settled outside the system: no payment recorded.
        $contract = $this->makeContract(1000, 'paid');

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->set('notes', 'Signed copy received')
            ->call('save');

        $contract->refresh();
        $this->assertSame('paid', $contract->status);
        $this->assertSame(['old' => null, 'new' => 'Signed copy received'], $contract->changeHistories()->first()->changes['notes']);
        $this->assertArrayNotHasKey('amount', $contract->changeHistories()->first()->changes);
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $contract = $this->makeContract(1000);

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->call('save');

        $this->assertSame(0, $contract->changeHistories()->count());
    }

    public function test_the_edit_screen_warns_before_a_price_change_moves_the_status(): void
    {
        $contract = $this->makeContract(1000);
        $this->pay($contract, 1000);

        $component = Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->set('amount', '1500');

        $impact = $component->instance()->amountImpact;
        $this->assertSame('partially_paid', $impact['status']);
        $this->assertTrue($impact['status_changes']);
        $this->assertEquals(500, $impact['balance']);

        $component->assertSee(__('Saving will change the status from :old to :new. The change is recorded in the contract history.', [
            'old' => __('Paid'),
            'new' => __('Partially Paid'),
        ]));
    }

    public function test_a_change_order_on_a_paid_contract_makes_it_partially_paid_and_is_recorded(): void
    {
        $contract = $this->makeContract(1000);
        $this->pay($contract, 1000);

        Livewire::actingAs($this->admin)
            ->test(ContractChangeOrders::class, ['contract' => $contract])
            ->call('openCreateModal')
            ->set('title', 'Extra wall')
            ->set('amount', '250')
            ->call('save')
            ->assertHasNoErrors();

        $contract->refresh();
        $this->assertSame('partially_paid', $contract->status);

        $entry = $contract->changeHistories()->first();
        $this->assertSame('change_order_added', $entry->action);
        $this->assertSame('Extra wall', $entry->changes['title']);
        $this->assertEquals(250, $entry->changes['amount']);
        $this->assertSame(['old' => 'paid', 'new' => 'partially_paid'], $entry->changes['status']);
    }

    public function test_editing_and_deleting_a_change_order_is_recorded_and_restores_the_status(): void
    {
        $contract = $this->makeContract(1000);
        $this->pay($contract, 1000);

        $changeOrder = ContractChangeOrder::create([
            'contract_id' => $contract->id,
            'title' => 'Extra',
            'date' => now()->toDateString(),
            'amount' => 200,
            'created_by' => $this->admin->id,
        ]);
        $contract->refresh()->updateStatusFromPayments();
        $this->assertSame('partially_paid', $contract->fresh()->status);

        $component = Livewire::actingAs($this->admin)
            ->test(ContractChangeOrders::class, ['contract' => $contract->fresh()])
            ->call('openEditModal', $changeOrder->id)
            ->set('amount', '300')
            ->call('save');

        $updated = $contract->changeHistories()->first();
        $this->assertSame('change_order_updated', $updated->action);
        $this->assertSame('Extra', $updated->changes['title']);
        $this->assertEquals(['old' => 200, 'new' => 300], $updated->changes['amount']);

        $component->call('delete', $changeOrder->id);

        $contract->refresh();
        $this->assertSame('paid', $contract->status, 'Without the change order, the payment settles it again.');

        $deleted = $contract->changeHistories()->first();
        $this->assertSame('change_order_deleted', $deleted->action);
        $this->assertSame($changeOrder->id, $deleted->contract_change_order_id);
        $this->assertEquals(300, $deleted->changes['amount']);
        $this->assertSame(['old' => 'partially_paid', 'new' => 'paid'], $deleted->changes['status']);
    }

    public function test_a_change_order_on_an_unpaid_contract_leaves_its_work_status_alone(): void
    {
        // It used to turn an active contract "Completed" when nothing was paid.
        $active = $this->makeContract(1000);
        $draft = $this->makeContract(1000, 'draft');

        foreach ([$active, $draft] as $contract) {
            Livewire::actingAs($this->admin)
                ->test(ContractChangeOrders::class, ['contract' => $contract])
                ->call('openCreateModal')
                ->set('title', 'Extra')
                ->set('amount', '100')
                ->call('save');
        }

        $this->assertSame('active', $active->fresh()->status);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_deleting_the_only_payment_returns_to_the_work_status_and_is_recorded(): void
    {
        $contract = $this->makeContract(1000);
        $contract->update(['status' => 'completed']);
        $contract->recordStatusChange($this->admin, 'active', 'completed');
        $this->pay($contract, 400);
        $this->assertSame('partially_paid', $contract->fresh()->status);

        $payment = $contract->payments()->first();

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->call('deletePayment', $payment->id);

        $contract->refresh();
        $this->assertSame('completed', $contract->status);

        $entry = $contract->changeHistories()->first();
        $this->assertSame('payment_deleted', $entry->action);
        $this->assertEquals(400, $entry->changes['amount']);
        $this->assertSame(['old' => 'partially_paid', 'new' => 'completed'], $entry->changes['status']);
    }

    public function test_the_contract_page_shows_the_change_history(): void
    {
        $contract = $this->makeContract(1000);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertSee(__('No changes since the contract was created.'));

        Livewire::actingAs($this->admin)
            ->test(ContractEdit::class, ['contract' => $contract])
            ->set('amount', '1250')
            ->call('save');

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract->fresh()])
            ->assertSee(__('Contract Edited'))
            ->assertSee($this->admin->name)
            ->assertDontSee(__('No changes since the contract was created.'));
    }

    public function test_the_reconcile_command_lists_by_default_and_fixes_only_contracts_with_payments(): void
    {
        $stale = $this->makeContract(1000);
        $this->pay($stale, 1000);
        // The state the bug left behind: price raised straight in the table.
        Contract::whereKey($stale->id)->update(['amount' => 150000]);

        $manual = $this->makeContract(1000, 'paid');

        $this->artisan('contracts:reconcile-status')->assertSuccessful();
        $this->assertSame('paid', $stale->fresh()->status, 'Listing changes nothing.');

        $this->artisan('contracts:reconcile-status', ['--fix' => true])->assertSuccessful();

        $this->assertSame('partially_paid', $stale->fresh()->status);
        $this->assertSame('status_reconciled', $stale->changeHistories()->first()->action);
        $this->assertNull($stale->statusHistories()->latest('id')->first()->changed_by);
        $this->assertSame('paid', $manual->fresh()->status, 'No payment recorded: left for a person to decide.');
    }
}
