<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractPayments;
use App\Livewire\PaymentBatch\PaymentBatchCreate;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractChangeOrder;
use App\Models\PaymentBatch;
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
 * The batch screen shows what /contract-payments shows: the change orders
 * (back charges included) in their own column with the list behind a
 * toggle, and the subcontractor's contact under the company name. The
 * subcontractor dropdowns on both screens offer active subcontractors only.
 */
class PaymentBatchChangeOrdersAndContactTest extends TestCase
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
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'K. Hovnanian', 'contact_name' => 'C', 'email' => 'k@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSubcontractor(string $name, bool $active = true): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => $name,
            'is_subcontractor' => true,
            'is_active' => $active,
            'created_by' => $this->admin->id,
        ])->save();

        return $vendor;
    }

    protected function makeContract(Vendor $vendor, array $attributes = []): Contract
    {
        return Contract::create(array_merge([
            'project_id' => $this->project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CTR-'.str()->random(4),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 10900,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    protected function makeBatch(?int $subcontractorId = null): PaymentBatch
    {
        return PaymentBatch::create([
            'name' => 'Delaware area',
            'status' => 'draft',
            'payment_date' => now()->toDateString(),
            'subcontractor_id' => $subcontractorId,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_the_batch_shows_the_back_charge_in_its_own_column_and_lists_it_on_demand(): void
    {
        $wolf = $this->makeSubcontractor('Wolf Construction Services LLC');
        $contract = $this->makeContract($wolf, ['contract_number' => 'CTR-0330']);
        ContractChangeOrder::create([
            'contract_id' => $contract->id,
            'title' => 'Back charge: cleanup',
            'date' => now()->toDateString(),
            'amount' => -100,
            'created_by' => $this->admin->id,
        ]);

        $screen = Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->makeBatch()])
            ->assertSee('CTR-0330')
            ->assertSee('$10,900.00')
            ->assertSee('-$100.00')
            ->assertSee('$10,800.00')
            ->assertDontSee('Back charge: cleanup');

        $screen->call('toggleChangeOrders', $contract->id)
            ->assertSee('Back charge: cleanup');

        $screen->call('toggleChangeOrders', $contract->id)
            ->assertDontSee('Back charge: cleanup');
    }

    public function test_the_batch_shows_the_subcontractors_contact_under_the_company(): void
    {
        $wolf = $this->makeSubcontractor('Wolf Construction Services LLC');
        $vinicius = SubcontractorEmployee::create(['subcontractor_id' => $wolf->id, 'name' => 'Vinicius Contact', 'title' => 'Framer']);
        $this->makeContract($wolf, ['subcontractor_employee_id' => $vinicius->id]);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->makeBatch()])
            ->assertSee('Vinicius Contact');
    }

    public function test_the_subcontractor_dropdowns_offer_active_subcontractors_and_keep_the_one_already_picked(): void
    {
        $active = $this->makeSubcontractor('Still Working LLC');
        $inactive = $this->makeSubcontractor('Gone Away LLC', active: false);
        $this->makeContract($active);
        $this->makeContract($inactive);

        $names = fn ($screen) => $screen->instance()->subcontractors->pluck('name')->all();

        $payments = Livewire::actingAs($this->admin)->test(ContractPayments::class);
        $this->assertSame(['Still Working LLC'], $names($payments));
        $payments->assertSee('Gone Away LLC'); // its contract is still listed and payable
        $payments->set('subcontractorFilter', (string) $inactive->id);
        $this->assertSame(['Gone Away LLC', 'Still Working LLC'], $names($payments));

        $this->assertSame(['Still Working LLC'], $names(Livewire::actingAs($this->admin)->test(PaymentBatchCreate::class)));

        $this->assertSame(['Still Working LLC'], $names(Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->makeBatch()])));
        $this->assertSame(['Gone Away LLC', 'Still Working LLC'], $names(Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->makeBatch($inactive->id)])), 'A batch made for a subcontractor since switched off keeps showing it.');
    }
}
