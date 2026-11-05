<?php

namespace Tests\Feature\Contract;

use App\Enums\ProjectStatus;
use App\Livewire\PaymentBatch\PaymentBatchEdit;
use App\Livewire\PaymentBatch\PaymentBatchShow;
use App\Models\Client;
use App\Models\Contract;
use App\Models\PaymentBatch;
use App\Models\PaymentBatchItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\SubcontractorEmployee;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PaymentBatchLayout;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The batch screen, client requests 2, 3, 6, 7 and 8 (Nov 2026): a total and
 * an approve button for what the filters select, reject all pending, a filter
 * by employee, pending lines first and rejected last, and one employee's
 * contracts one after another.
 */
class PaymentBatchSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected PaymentBatch $batch;

    protected Vendor $benitez;

    protected Vendor $wolf;

    protected SubcontractorEmployee $jhony;

    protected SubcontractorEmployee $ariel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);

        $this->project = Project::create([
            'project_name' => 'Creekside Run',
            'client_id' => Client::create(['company_name' => 'Lennar', 'contact_name' => 'C', 'email' => 'l@example.test', 'created_by' => $this->admin->id])->id,
            'contact_person' => 'C',
            'email' => 'creek@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);

        $this->batch = PaymentBatch::create(['name' => 'Alexandre - Darlan', 'status' => 'draft', 'payment_date' => '2026-10-09', 'created_by' => $this->admin->id]);

        $this->benitez = $this->sub('Benitez Carpenter LLC');
        $this->wolf = $this->sub('Wolf Construction');
        $this->jhony = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Jhony']);
        $this->ariel = SubcontractorEmployee::create(['subcontractor_id' => $this->benitez->id, 'name' => 'Ariel']);
    }

    private function sub(string $name): Vendor
    {
        $vendor = new Vendor;
        $vendor->forceFill(['name' => $name, 'is_subcontractor' => true, 'is_active' => true, 'created_by' => $this->admin->id])->save();

        return $vendor;
    }

    private function line(Vendor $sub, ?SubcontractorEmployee $employee, string $number, float $amount, string $status = 'pending'): PaymentBatchItem
    {
        $contract = Contract::create([
            'project_id' => $this->project->id,
            'subcontractor_id' => $sub->id,
            'subcontractor_employee_id' => $employee?->id,
            'contract_number' => $number,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'amount' => 1000,
            'created_by' => $this->admin->id,
        ]);

        return PaymentBatchItem::create([
            'payment_batch_id' => $this->batch->id,
            'contract_id' => $contract->id,
            'amount' => $amount,
            'payment_method' => 'check',
            'status' => $status,
        ]);
    }

    private function seedLines(): void
    {
        $this->line($this->wolf, null, 'CTR-W1', 300, 'rejected');
        $this->line($this->benitez, $this->jhony, 'CTR-J1', 450);
        $this->line($this->wolf, null, 'CTR-W2', 200);
        $this->line($this->benitez, $this->ariel, 'CTR-A1', 100);
        $this->line($this->benitez, $this->jhony, 'CTR-J2', 450);
    }

    public function test_pending_come_first_rejected_last_and_one_employee_stays_together(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            // Benitez (Ariel, then Jhony's two), Wolf, then the rejected line.
            ->assertSeeInOrder(['CTR-A1', 'CTR-J1', 'CTR-J2', 'CTR-W2', 'CTR-W1'])
            // A column sort applies inside each band; rejected stays last.
            ->call('sort', 'contract')
            ->call('sort', 'contract')
            ->assertSeeInOrder(['CTR-W2', 'CTR-J2', 'CTR-J1', 'CTR-A1', 'CTR-W1']);
    }

    public function test_the_employee_filter_and_the_employee_grouping(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->assertViewHas('contracts', fn ($c) => $c->total() === 5)
            ->set('groupBy', 'employee')
            ->assertSeeInOrder(['Ariel', 'CTR-A1', 'Jhony', 'CTR-J1', 'CTR-J2', __('No employee')])
            ->set('subcontractorFilter', (string) $this->wolf->id)
            ->assertSet('employeeFilter', '')
            ->set('subcontractorFilter', (string) $this->benitez->id)
            ->assertViewHas('contracts', fn ($c) => $c->total() >= 3)
            ->set('employeeFilter', (string) $this->jhony->id)
            ->assertSet('selectionSummary', fn ($s) => $s['count'] === 2 && $s['amount'] === 900.0 && $s['partial']);

        $this->assertArrayHasKey('employee', PaymentBatchLayout::groupOptions());
    }

    public function test_approving_the_selection_pays_only_what_the_filters_select(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->assertSet('selectionSummary', fn ($s) => ! $s['partial'])
            ->assertDontSee(__('Approve this selection'))
            ->set('subcontractorFilter', (string) $this->benitez->id)
            ->assertSee(__('Approve this selection'))
            ->assertSee('$1,000.00')
            ->call('approveSelection')
            ->assertHasNoErrors();

        $statuses = $this->batch->items()->with('contract')->get()->mapWithKeys(fn ($i) => [$i->contract->contract_number => $i->status]);
        $this->assertSame(
            ['CTR-W1' => 'rejected', 'CTR-J1' => 'approved', 'CTR-W2' => 'pending', 'CTR-A1' => 'approved', 'CTR-J2' => 'approved'],
            $statuses->all(),
        );
        $this->assertSame('partially_approved', $this->batch->fresh()->status);
    }

    public function test_reject_all_pending_and_reject_the_selection(): void
    {
        $this->seedLines();

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->set('subcontractorFilter', (string) $this->wolf->id)
            ->call('rejectSelection');

        $this->assertSame(3, $this->batch->items()->where('status', 'pending')->count());

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('rejectAllPending')
            ->assertSee(trans_choice(':count item rejected.|:count items rejected.', 3, ['count' => 3]));

        $this->assertSame(0, $this->batch->items()->where('status', 'pending')->count());
        $this->assertSame(5, $this->batch->items()->where('status', 'rejected')->count());
    }

    /** The batch view, PDF and CSV show each contract's change orders, as the edit screen does. */
    public function test_the_view_pdf_and_csv_show_the_change_orders(): void
    {
        $line = $this->line($this->benitez, $this->jhony, 'CTR-1204', 450);
        $line->contract->changeOrders()->create([
            'title' => 'BC — dumpster Lot 46',
            'date' => '2026-10-20',
            'amount' => -250,
            'description' => 'Debris left on site',
            'created_by' => $this->admin->id,
        ]);
        $this->line($this->wolf, null, 'CTR-W9', 300);

        $page = Livewire::actingAs($this->admin)
            ->test(PaymentBatchShow::class, ['paymentBatch' => $this->batch])
            ->assertSee(__('Change Orders'))
            ->assertSee('-$250.00')
            ->assertDontSee('BC — dumpster Lot 46')
            ->call('toggleChangeOrders', $line->contract_id)
            ->assertSee('BC — dumpster Lot 46')
            ->assertSee('Debris left on site')
            ->call('toggleAllChangeOrders')
            ->assertSet('expandedContracts', [])
            ->call('toggleAllChangeOrders')
            ->assertSet('expandedContracts', [$line->contract_id]);

        $csv = $page->call('exportCsv')->effects['download']['content'] ?? null;
        $csv = $csv ? base64_decode($csv) : '';
        $this->assertStringContainsString(__('Change order details'), $csv);
        $this->assertStringContainsString('2026-10-20 BC — dumpster Lot 46: -250.00', $csv);

        $data = [];
        View::composer('pdf.payment-batch', function ($view) use (&$data) {
            $data = $view->getData();
        });
        $this->actingAs($this->admin)->get(route('payment-batches.pdf.view', $this->batch))->assertOk();
        $item = collect($data['groups'])->flatMap(fn ($g) => $g['rows'])->firstWhere('contract_id', $line->contract_id);
        $this->assertSame(['BC — dumpster Lot 46'], $item->contract->changeOrders->pluck('title')->all());
    }

    public function test_a_contract_outside_the_batch_cannot_be_expanded(): void
    {
        $this->seedLines();
        $outside = Contract::create([
            'project_id' => $this->project->id, 'subcontractor_id' => $this->wolf->id, 'contract_number' => 'CTR-OUT',
            'status' => 'active', 'start_date' => now()->toDateString(), 'amount' => 100, 'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(PaymentBatchShow::class, ['paymentBatch' => $this->batch])
            ->call('toggleChangeOrders', $outside->id)
            ->assertNotFound();
    }

    public function test_bulk_actions_keep_their_grants(): void
    {
        $this->seedLines();

        $role = Role::create(['name' => 'batch-builder']);
        $role->syncAbilities(['payments.view', 'payments.batch']);
        $builder = User::factory()->create(['role_id' => $role->id]);

        Livewire::actingAs($builder)
            ->test(PaymentBatchEdit::class, ['paymentBatch' => $this->batch])
            ->call('approveSelection')
            ->assertForbidden();

        $this->assertSame(0, $this->batch->items()->where('status', 'approved')->count());
    }
}
