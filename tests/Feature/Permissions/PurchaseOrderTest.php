<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\PurchaseOrder\PurchaseOrderShow;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * M9 — Purchase Orders.
 *
 * Six grants, and the pass builds the one that had nothing behind it.
 *
 * `approve` and `receive` are deliberately not the same authority: on a real
 * site the office approves the spend and the storeman signs for the delivery.
 * Approving creates the expense, so it obeys the ceiling; receiving commits
 * nothing, so it does not.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
        $this->site = $this->makeSite($this->project, 'Site A');
    }

    /*
    |---------------------------------------------------------------------------
    | Fixtures
    |---------------------------------------------------------------------------
    */

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', $role)->value('id'),
        ], $attributes));
    }

    protected function roleWith(array $abilities): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(['role_id' => $role->id]);
    }

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'PO Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-po@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeSite(Project $project, string $name): JobSite
    {
        return JobSite::create([
            'project_id' => $project->id,
            'job_site_name' => $name,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-po@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    /** @param  array<int, array{name: string, qty: float, price: float}>  $lines */
    protected function makeOrder(array $attributes = [], ?array $lines = null): PurchaseOrder
    {
        $lines ??= [
            ['name' => 'Cement', 'qty' => 100, 'price' => 30],
            ['name' => 'Rebar', 'qty' => 40, 'price' => 25],
        ];

        $total = array_sum(array_map(fn ($l) => $l['qty'] * $l['price'], $lines));

        $order = PurchaseOrder::create(array_merge([
            'project_id' => $this->project->id,
            'po_number' => 'PO-'.str()->random(6),
            'po_date' => now()->toDateString(),
            'status' => 'draft',
            'total_amount' => $total,
            'total_installments' => 1,
            'created_by' => $this->admin->id,
        ], $attributes));

        foreach ($lines as $i => $line) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'item_name' => $line['name'],
                'item_type' => 'custom',
                'quantity' => $line['qty'],
                'unit' => 'un',
                'unit_price' => $line['price'],
                'total_amount' => $line['qty'] * $line['price'],
                'sort_order' => $i,
            ]);
        }

        return $order->fresh('items');
    }

    protected function memberOf(Project|JobSite $scope, array $abilities, ?int $limitCents = null): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
            'approval_limit' => $limitCents,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /*
    |---------------------------------------------------------------------------
    | The screens answer as they did
    |---------------------------------------------------------------------------
    */

    public function test_the_purchase_order_screens_answer_as_they_did_for_every_role(): void
    {
        $order = $this->makeOrder();

        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.purchase-orders', $this->project))->assertOk();
            $this->actingAs($user)->get(route('purchase-orders.show', $order))->assertOk();
            $this->actingAs($user)->get(route('purchase-orders.project.create', $this->project))->assertOk();
        }
    }

    public function test_seeing_purchase_orders_is_a_grant_that_can_be_taken_away(): void
    {
        $order = $this->makeOrder();
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.purchase-orders', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('purchase-orders.show', $order))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.purchase-orders', $this->site))->assertForbidden();
    }

    public function test_creating_and_editing_are_separate_grants(): void
    {
        $order = $this->makeOrder();
        $reader = $this->memberOf($this->project, ['purchase-orders.view']);

        $this->actingAs($reader)->get(route('purchase-orders.project.create', $this->project))->assertForbidden();
        $this->actingAs($reader)->get(route('purchase-orders.edit', $order))->assertForbidden();

        Livewire::actingAs($reader)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('submitForApproval')
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Approving — the money, and the ceiling
    |---------------------------------------------------------------------------
    */

    public function test_approving_needs_its_own_grant(): void
    {
        $order = $this->makeOrder(['status' => 'pending']);

        $editor = $this->memberOf($this->project, ['purchase-orders.view', 'purchase-orders.edit']);

        Livewire::actingAs($editor)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('approve')
            ->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_order_above_the_ceiling_cannot_be_approved(): void
    {
        // 100 × 30 + 40 × 25 = R$ 4.000
        $order = $this->makeOrder(['status' => 'pending']);

        $buyer = $this->memberOf(
            $this->project,
            ['purchase-orders.view', 'purchase-orders.approve'],
            limitCents: 100000,       // R$ 1.000
        );

        Livewire::actingAs($buyer)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('approve')
            ->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_order_within_the_ceiling_can_be_approved(): void
    {
        $order = $this->makeOrder(['status' => 'pending']);

        $buyer = $this->memberOf(
            $this->project,
            ['purchase-orders.view', 'purchase-orders.approve'],
            limitCents: 1000000,      // R$ 10.000
        );

        Livewire::actingAs($buyer)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('approve')
            ->assertOk();

        $this->assertSame('approved', $order->fresh()->status);
    }

    public function test_rejecting_needs_the_review_grant_but_not_the_ceiling(): void
    {
        $order = $this->makeOrder(['status' => 'pending']);

        $editor = $this->memberOf($this->project, ['purchase-orders.view', 'purchase-orders.edit']);

        Livewire::actingAs($editor)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('reject')
            ->assertForbidden();

        // Turning an order down commits nothing, so a tiny ceiling is no bar.
        $reviewer = $this->memberOf(
            $this->project,
            ['purchase-orders.view', 'purchase-orders.approve'],
            limitCents: 1,
        );

        Livewire::actingAs($reviewer)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->set('rejectReason', 'Wrong supplier')
            ->call('reject')
            ->assertOk();

        $this->assertSame('rejected', $order->fresh()->status);
    }

    public function test_cancelling_an_approved_order_needs_the_review_grant(): void
    {
        $order = $this->makeOrder(['status' => 'approved']);

        $editor = $this->memberOf($this->project, ['purchase-orders.view', 'purchase-orders.edit']);

        Livewire::actingAs($editor)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('cancel')
            ->assertForbidden();

        $this->assertSame('approved', $order->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | Receiving — built by this pass
    |---------------------------------------------------------------------------
    */

    public function test_receiving_is_held_apart_from_approving(): void
    {
        $order = $this->makeOrder(['status' => 'approved']);

        // Somebody who may approve every penny still cannot sign for a delivery.
        $approver = $this->memberOf($this->project, [
            'purchase-orders.view', 'purchase-orders.approve',
        ]);

        Livewire::actingAs($approver)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('openReceiptModal')
            ->assertForbidden();

        // …and the storeman, who may sign for anything, cannot approve.
        $storeman = $this->memberOf($this->project, [
            'purchase-orders.view', 'purchase-orders.receive',
        ]);

        $pending = $this->makeOrder(['status' => 'pending']);

        Livewire::actingAs($storeman)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $pending])
            ->call('approve')
            ->assertForbidden();
    }

    public function test_a_full_delivery_marks_the_order_received(): void
    {
        $order = $this->makeOrder(['status' => 'approved']);
        $storeman = $this->memberOf($this->project, [
            'purchase-orders.view', 'purchase-orders.receive',
        ]);

        $this->assertSame('awaiting', $order->receiptStatus());

        Livewire::actingAs($storeman)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('openReceiptModal')
            ->set('receiptNote', 'All checked against the note')
            ->call('recordReceipt')
            ->assertOk()
            ->assertHasNoErrors();

        $order = $order->fresh('items');

        $this->assertTrue($order->isFullyReceived());
        $this->assertSame('received', $order->receiptStatus());
        $this->assertFalse($order->canBeReceived());

        // …and the delivery is on the record with who and when.
        $receipt = $order->receipts()->with('lines')->first();

        $this->assertSame($storeman->id, $receipt->received_by);
        $this->assertSame('All checked against the note', $receipt->note);
        $this->assertCount(2, $receipt->lines);
    }

    public function test_a_part_delivery_leaves_the_rest_outstanding(): void
    {
        $order = $this->makeOrder(['status' => 'approved']);
        $storeman = $this->memberOf($this->project, [
            'purchase-orders.view', 'purchase-orders.receive',
        ]);

        $cement = $order->items->firstWhere('item_name', 'Cement');   // 100 ordered
        $rebar = $order->items->firstWhere('item_name', 'Rebar');     // 40 ordered

        Livewire::actingAs($storeman)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('openReceiptModal')
            ->set('receiptQuantities', [$cement->id => 100, $rebar->id => 25])
            ->set('receiptNote', 'Rest due Friday')
            ->call('recordReceipt')
            ->assertOk();

        $order = $order->fresh('items');

        $this->assertSame('partial', $order->receiptStatus());
        $this->assertTrue($order->canBeReceived());
        $this->assertTrue($order->items->firstWhere('item_name', 'Cement')->isFullyReceived());
        $this->assertEqualsWithDelta(
            15.0,
            $order->items->firstWhere('item_name', 'Rebar')->outstandingQuantity(),
            0.01,
        );

        // The second delivery closes it.
        Livewire::actingAs($storeman)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('openReceiptModal')
            ->call('recordReceipt')
            ->assertOk();

        $order = $order->fresh('items');

        $this->assertSame('received', $order->receiptStatus());
        $this->assertCount(2, $order->receipts()->get());
    }

    public function test_more_cannot_be_booked_in_than_was_ordered(): void
    {
        $order = $this->makeOrder(['status' => 'approved'], [
            ['name' => 'Sand', 'qty' => 10, 'price' => 100],
        ]);

        $storeman = $this->memberOf($this->project, [
            'purchase-orders.view', 'purchase-orders.receive',
        ]);

        $sand = $order->items->first();

        Livewire::actingAs($storeman)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('openReceiptModal')
            ->set('receiptQuantities', [$sand->id => 999])
            ->call('recordReceipt')
            ->assertOk();

        // Booked in at the ordered quantity, not the claimed one: an
        // over-delivery is a conversation with the supplier.
        $this->assertEqualsWithDelta(
            10.0,
            (float) $order->fresh('items')->items->first()->received_quantity,
            0.01,
        );
    }

    public function test_an_empty_delivery_is_refused_with_a_reason(): void
    {
        $order = $this->makeOrder(['status' => 'approved']);
        $storeman = $this->memberOf($this->project, [
            'purchase-orders.view', 'purchase-orders.receive',
        ]);

        Livewire::actingAs($storeman)
            ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
            ->call('openReceiptModal')
            ->set('receiptQuantities', [])
            ->call('recordReceipt')
            ->assertHasErrors('receiptQuantities');

        $this->assertSame('awaiting', $order->fresh('items')->receiptStatus());
    }

    public function test_only_an_approved_order_can_take_delivery(): void
    {
        $storeman = $this->memberOf($this->project, [
            'purchase-orders.view', 'purchase-orders.receive',
        ]);

        foreach (['draft', 'pending', 'rejected', 'cancelled'] as $status) {
            $order = $this->makeOrder(['status' => $status]);

            $this->assertNull($order->receiptStatus(), $status);

            Livewire::actingAs($storeman)
                ->test(PurchaseOrderShow::class, ['purchaseOrder' => $order])
                ->call('openReceiptModal')
                ->assertForbidden();
        }
    }

    public function test_the_delivery_columns_only_appear_once_the_order_is_approved(): void
    {
        $draft = $this->makeOrder();
        $approved = $this->makeOrder(['status' => 'approved']);

        $this->actingAs($this->admin)->get(route('purchase-orders.show', $draft))
            ->assertOk()
            ->assertDontSee(__('Outstanding'));

        $this->actingAs($this->admin)->get(route('purchase-orders.show', $approved))
            ->assertOk()
            ->assertSee(__('Outstanding'))
            ->assertSee(__('Awaiting delivery'));
    }

    /*
    |---------------------------------------------------------------------------
    | Files, and what the seeds hand out
    |---------------------------------------------------------------------------
    */

    public function test_an_order_document_is_only_served_to_somebody_who_may_see_it(): void
    {
        Storage::fake('local');
        Storage::put('purchase-orders/order.pdf', 'x');

        $this->makeOrder([
            'job_site_id' => $this->site->id,
            'receipt_path' => 'purchase-orders/order.pdf',
        ]);

        $reader = $this->memberOf($this->site, ['purchase-orders.view']);
        $outsider = $this->memberOf($this->makeSite($this->project, 'Site B'), ['purchase-orders.view']);

        $this->actingAs($reader)
            ->get(route('files.show', ['path' => 'purchase-orders/order.pdf']))->assertOk();
        $this->actingAs($outsider)
            ->get(route('files.show', ['path' => 'purchase-orders/order.pdf']))->assertForbidden();
    }

    public function test_the_seeded_templates_grant_the_expected_purchase_order_actions(): void
    {
        $expected = [
            'project-manager' => [
                'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.edit',
                'purchase-orders.approve', 'purchase-orders.receive',
            ],
            'procurement' => [
                'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.edit',
                'purchase-orders.receive',
            ],
            'accounting' => ['purchase-orders.view'],
        ];

        foreach ($expected as $key => $abilities) {
            $held = array_values(array_filter(
                PermissionTemplate::where('key', $key)->firstOrFail()->abilities(),
                fn ($a) => str_starts_with($a, 'purchase-orders.'),
            ));

            sort($held);
            sort($abilities);

            $this->assertSame($abilities, $held, "Template {$key} grants the wrong purchase order actions.");
        }
    }

    public function test_approving_is_declared_limited_and_receiving_is_not(): void
    {
        $catalog = \App\Services\AbilityCatalog::class;

        $this->assertTrue($catalog::isLimited('purchase-orders.approve'));

        // Receiving commits nothing, so a ceiling would mean nothing.
        $this->assertFalse($catalog::isLimited('purchase-orders.receive'));
    }
}
