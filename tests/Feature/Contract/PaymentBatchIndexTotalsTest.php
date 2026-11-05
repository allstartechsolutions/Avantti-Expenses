<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\PaymentBatch\PaymentBatchIndex;
use App\Models\Client;
use App\Models\Contract;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
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
 * Client requests 1 and 9 (Nov 2026): the batch list filters by project
 * manager and payment date, and totals every batch the filters keep by PM
 * (split by payment date) and by date — the client's own example being
 * PM Alexandre = Oct 8 $100,012.50 + Oct 9 $110,349.98 = $210,362.48.
 */
class PaymentBatchIndexTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $alexandre;

    protected User $rildo;

    protected Project $project;

    protected Vendor $sub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $role = Role::where('name', 'admin')->value('id');
        $this->admin = User::factory()->create(['role_id' => $role]);
        $this->alexandre = User::factory()->create(['role_id' => $role, 'name' => 'Alexandre']);
        $this->rildo = User::factory()->create(['role_id' => $role, 'name' => 'Rildo']);

        $this->project = Project::create([
            'project_name' => 'Kent Island',
            'client_id' => Client::create(['company_name' => 'Client', 'contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'kent@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->sub = new Vendor;
        $this->sub->forceFill(['name' => 'Wolf Framing', 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        // The client's example, split over several batches per day.
        $this->batch('Alexandre - DLW', $this->alexandre, '2026-10-08', [100012.50]);
        $this->batch('Alexandre - Dhovane', $this->alexandre, '2026-10-09', [23750.00]);
        $this->batch('Alexandre - Darlan', $this->alexandre, '2026-10-09', [48650.00, 37949.98]);
        $this->batch('Rildo - Abel', $this->rildo, '2026-10-09', [3125.00]);
        $this->batch('Loose run', null, '2026-10-23', [1000.00]);
    }

    private function batch(string $name, ?User $pm, string $date, array $amounts): PaymentBatch
    {
        $batch = PaymentBatch::create([
            'name' => $name,
            'status' => 'draft',
            'payment_date' => $date,
            'project_manager_id' => $pm?->id,
            'created_by' => $this->admin->id,
        ]);

        foreach ($amounts as $amount) {
            PaymentBatchItem::create([
                'payment_batch_id' => $batch->id,
                'contract_id' => Contract::create([
                    'project_id' => $this->project->id,
                    'subcontractor_id' => $this->sub->id,
                    'contract_number' => 'CTR-'.str()->random(6),
                    'status' => 'active',
                    'start_date' => now()->toDateString(),
                    'amount' => 500000,
                    'created_by' => $this->admin->id,
                ])->id,
                'amount' => $amount,
                'payment_method' => 'check',
                'status' => 'pending',
            ]);
        }

        return $batch;
    }

    public function test_the_totals_split_each_pm_by_payment_date(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PaymentBatchIndex::class)
            ->assertViewHas('totals', function (array $t) {
                $alexandre = $t['byPm']->firstWhere('name', 'Alexandre');

                return $t['count'] === 5
                    && $t['total'] === 214487.48
                    && $alexandre['total'] === 210362.48
                    && $alexandre['dates']->pluck('total')->all() === [100012.50, 110349.98]
                    && $t['byPm']->pluck('name')->all() === ['Alexandre', 'Rildo', null]
                    && $t['byDate']->pluck('total')->all() === [100012.50, 113474.98, 1000.00];
            })
            ->assertSee('$210,362.48')
            ->assertSee(__('No project manager'));
    }

    public function test_the_pm_and_date_filters_narrow_the_list_and_the_totals(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PaymentBatchIndex::class)
            ->set('pmFilter', (string) $this->alexandre->id)
            ->assertViewHas('batches', fn ($b) => $b->total() === 3)
            ->assertViewHas('totals', fn ($t) => $t['total'] === 210362.48)
            ->set('fromDate', '2026-10-09')
            ->set('toDate', '2026-10-09')
            ->assertViewHas('totals', fn ($t) => $t['total'] === 110349.98 && $t['count'] === 2)
            ->assertDontSee('Alexandre - DLW')
            ->call('clearFilters')
            ->assertViewHas('totals', fn ($t) => $t['count'] === 5);
    }

    public function test_batches_without_a_pm_can_be_picked_out(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PaymentBatchIndex::class)
            ->set('pmFilter', 'none')
            ->assertViewHas('totals', fn ($t) => $t['count'] === 1 && $t['total'] === 1000.0)
            ->assertSee('Loose run');
    }
}
