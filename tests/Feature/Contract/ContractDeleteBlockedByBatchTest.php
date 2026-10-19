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
 * A contract that sits in a payment batch cannot be deleted — the batch row
 * is a financial record and its foreign key does not cascade. Deleting one
 * used to crash the page (Sentry AVANTTI-CONSTRUCTION-7); now it is refused
 * with a message. Approving a whole batch loads its contracts in one query
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

    protected function addToBatch(Contract $contract, float $amount): void
    {
        PaymentBatchItem::create([
            'payment_batch_id' => $this->batch->id,
            'contract_id' => $contract->id,
            'amount' => $amount,
            'payment_method' => 'check',
            'status' => 'pending',
        ]);
    }

    public function test_a_contract_in_a_payment_batch_is_refused_not_crashed(): void
    {
        $contract = $this->contract('CTR-0001');
        $this->addToBatch($contract, 500);

        Livewire::actingAs($this->admin)
            ->test(ContractShow::class, ['contract' => $contract])
            ->assertDontSee(__('Delete Contract'))
            ->assertSee(__('This contract is in a payment batch and cannot be deleted. Remove it from the batch first, or cancel the contract instead.'))
            ->call('delete')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
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
}
