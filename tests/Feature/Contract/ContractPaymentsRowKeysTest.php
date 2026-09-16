<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\Contract\ContractPayments;
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
 * The contract-payments table is a list of rows that each carry their own
 * `wire:model` inputs (pay today, method, notes). Without a `wire:key` on
 * every row, Livewire's morph reuses row elements *positionally* whenever the
 * list shifts — a change-orders sub-row opening, a filter changing, a paid
 * contract dropping out — and an input built for contract A is retargeted at
 * contract B while its original binding survives. From then on, typing in
 * that field wrote the amount to both contracts: the "value duplicated into
 * other rows" bug. Keyed rows move with their contract instead.
 */
class ContractPaymentsRowKeysTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);

        $client = Client::create([
            'company_name' => 'Contract Client',
            'contact_name' => 'C',
            'email' => 'c@example.test',
            'created_by' => $this->admin->id,
        ]);

        $this->project = Project::create([
            'project_name' => 'Ours',
            'client_id' => $client->id,
            'contact_person' => 'C',
            'email' => 'ours-ct@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeContract(): Contract
    {
        $vendor = new Vendor;
        $vendor->forceFill([
            'name' => 'Sub '.str()->random(5),
            'is_subcontractor' => true,
            'created_by' => $this->admin->id,
        ])->save();

        return Contract::create([
            'project_id' => $this->project->id,
            'subcontractor_id' => $vendor->id,
            'contract_number' => 'CT-'.str()->random(5),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 50000,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_every_payment_row_is_keyed_by_its_contract(): void
    {
        $first = $this->makeContract();
        $second = $this->makeContract();

        $changeOrder = ContractChangeOrder::create([
            'contract_id' => $first->id,
            'title' => 'Extra work',
            'date' => now()->toDateString(),
            'amount' => 1000,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->call('toggleChangeOrders', $first->id)
            ->assertSeeHtml('wire:key="contract-'.$first->id.'"')
            ->assertSeeHtml('wire:key="contract-'.$second->id.'"')
            ->assertSeeHtml('wire:key="contract-'.$first->id.'-change-orders"')
            ->assertSeeHtml('wire:key="change-order-'.$changeOrder->id.'"')
            ->assertSeeHtml('wire:model.blur="payAmounts.'.$first->id.'"')
            ->assertSeeHtml('wire:model.blur="payAmounts.'.$second->id.'"');
    }

    public function test_an_amount_entered_on_one_row_stays_on_that_row(): void
    {
        $first = $this->makeContract();
        $second = $this->makeContract();

        Livewire::actingAs($this->admin)
            ->test(ContractPayments::class)
            ->set('payAmounts.'.$first->id, '150.00')
            ->call('toggleChangeOrders', $first->id)
            ->assertSet('payAmounts.'.$first->id, '150.00')
            ->assertSet('payAmounts.'.$second->id, null);
    }
}
