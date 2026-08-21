<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectChangeOrders;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderItem;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
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
 * M10 — Change Orders.
 *
 * Approving a change order is what moves the cost budget, and until this pass
 * anyone who could reach the screen could approve, reject or return one. The
 * four questions in docs/permissions-notes.md §4b are answered by holding four
 * things apart:
 *
 *   approve       deciding on a pending change — obeys the ceiling
 *   approve_own   approving one you raised, as M7 and M8 did for their own
 *   unapprove     pulling an APPROVED change back out of a live budget
 *   delete        and an approved change cannot be deleted at all
 */
class ChangeOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected JobSite $site;

    protected BudgetItem $costCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = $this->user('admin');
        $this->project = $this->makeProject('Ours');
        $this->site = $this->makeSite($this->project, 'Site A');

        $budget = Budget::create([
            'project_id' => $this->project->id,
            'name' => 'Project budget',
            'created_by' => $this->admin->id,
        ]);

        $this->costCode = BudgetItem::create([
            'budget_id' => $budget->id,
            'code' => '01-100',
            'name' => 'Site prep',
            'budgeted_amount' => 100000,
            'sort_order' => 1,
        ]);
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
                ['company_name' => 'CO Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-co@example.test',
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
            'email' => str($name)->slug().'-co@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeChangeOrder(array $attributes = [], float $costImpact = 4000.0): ChangeOrder
    {
        $changeOrder = ChangeOrder::create(array_merge([
            'project_id' => $this->project->id,
            'co_number' => 'CO-'.str()->random(5),
            'title' => 'Extra floor',
            'requested_date' => now()->toDateString(),
            'status' => ChangeOrder::STATUS_PENDING,
            'amount' => $costImpact * 1.2,
            'created_by' => $this->admin->id,
        ], $attributes));

        ChangeOrderItem::create([
            'change_order_id' => $changeOrder->id,
            'budget_item_id' => $this->costCode->id,
            'description' => 'Extra concrete',
            'amount' => $costImpact,
            'sort_order' => 0,
        ]);

        return $changeOrder->fresh('items');
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
    | The screen answers as it did
    |---------------------------------------------------------------------------
    */

    public function test_the_change_order_screens_answer_as_they_did_for_every_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.change-orders', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.change-orders', $this->site))->assertOk();
        }
    }

    public function test_seeing_change_orders_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.change-orders', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.change-orders', $this->site))->assertForbidden();
    }

    public function test_raising_and_editing_are_separate_grants(): void
    {
        $changeOrder = $this->makeChangeOrder();
        $reader = $this->memberOf($this->project, ['change-orders.view']);

        Livewire::actingAs($reader)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('openChangeOrderCreateModal')
            ->assertForbidden();

        Livewire::actingAs($reader)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('openChangeOrderEditModal', $changeOrder->id)
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | §4b question 1 — who may approve, and up to how much
    |---------------------------------------------------------------------------
    */

    public function test_approving_needs_its_own_grant(): void
    {
        $changeOrder = $this->makeChangeOrder();

        $raiser = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.create', 'change-orders.edit',
        ]);

        Livewire::actingAs($raiser)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('approveChangeOrder', $changeOrder->id)
            ->assertForbidden();

        $this->assertFalse($changeOrder->fresh()->isApproved());
    }

    public function test_an_employee_can_no_longer_approve_a_change_order(): void
    {
        // The tightening this pass makes deliberately: today anybody who can
        // reach the screen can approve, which is what moves the cost budget.
        $employee = Role::where('name', 'employee')->firstOrFail();
        $manager = Role::where('name', 'manager')->firstOrFail();

        $this->assertNotContains('change-orders.approve', $employee->abilityRows()->pluck('ability')->all());
        $this->assertContains('change-orders.approve', $manager->abilityRows()->pluck('ability')->all());
    }

    public function test_a_change_order_above_the_ceiling_cannot_be_approved(): void
    {
        $changeOrder = $this->makeChangeOrder(costImpact: 4000.0);

        $reviewer = $this->memberOf(
            $this->project,
            ['change-orders.view', 'change-orders.approve'],
            limitCents: 100000,       // R$ 1.000
        );

        Livewire::actingAs($reviewer)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('approveChangeOrder', $changeOrder->id)
            ->assertForbidden();

        $this->assertFalse($changeOrder->fresh()->isApproved());
    }

    public function test_a_change_order_within_the_ceiling_is_approved(): void
    {
        $changeOrder = $this->makeChangeOrder(costImpact: 4000.0);

        $reviewer = $this->memberOf(
            $this->project,
            ['change-orders.view', 'change-orders.approve'],
            limitCents: 1000000,      // R$ 10.000
        );

        Livewire::actingAs($reviewer)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('approveChangeOrder', $changeOrder->id)
            ->assertOk();

        $this->assertTrue($changeOrder->fresh()->isApproved());
    }

    public function test_a_deductive_change_order_is_measured_by_its_magnitude(): void
    {
        // Taking money OUT of a budget is not an act a spending ceiling should
        // refuse, but nor should a large negative slip through unchecked.
        $changeOrder = $this->makeChangeOrder(costImpact: -4000.0);

        $this->assertSame(400000, $changeOrder->costImpactInCents());

        $reviewer = $this->memberOf(
            $this->project,
            ['change-orders.view', 'change-orders.approve'],
            limitCents: 100000,
        );

        Livewire::actingAs($reviewer)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('approveChangeOrder', $changeOrder->id)
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | §4b question 2 — self-approval, the same answer as M7 and M8
    |---------------------------------------------------------------------------
    */

    public function test_the_person_who_raised_it_cannot_approve_it(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.create', 'change-orders.approve',
        ]);

        $own = $this->makeChangeOrder(['created_by' => $reviewer->id]);

        Livewire::actingAs($reviewer)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('approveChangeOrder', $own->id)
            ->assertForbidden();

        $this->assertFalse($own->fresh()->isApproved());
    }

    public function test_self_approval_is_lifted_by_a_grant(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.create',
            'change-orders.approve', 'change-orders.approve_own',
        ]);

        $own = $this->makeChangeOrder(['created_by' => $reviewer->id]);

        Livewire::actingAs($reviewer)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('approveChangeOrder', $own->id)
            ->assertOk();

        $this->assertTrue($own->fresh()->isApproved());
    }

    public function test_turning_down_your_own_pending_change_order_is_not_blocked(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.create', 'change-orders.approve',
        ]);

        $own = $this->makeChangeOrder(['created_by' => $reviewer->id]);

        Livewire::actingAs($reviewer)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('rejectChangeOrder', $own->id)
            ->assertOk();

        $this->assertTrue($own->fresh()->isRejected());
    }

    /*
    |---------------------------------------------------------------------------
    | §4b question 3 — undoing an approval is narrower than making one
    |---------------------------------------------------------------------------
    */

    public function test_pulling_an_approved_change_back_out_needs_the_unapprove_grant(): void
    {
        $approved = $this->makeChangeOrder(['status' => ChangeOrder::STATUS_APPROVED]);

        // Somebody who may approve anything still cannot undo one.
        $reviewer = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.approve',
        ]);

        foreach (['rejectChangeOrder', 'returnChangeOrderToPending'] as $action) {
            Livewire::actingAs($reviewer)
                ->test(ProjectChangeOrders::class, ['project' => $this->project])
                ->call($action, $approved->id)
                ->assertForbidden();
        }

        $this->assertTrue($approved->fresh()->isApproved());
    }

    public function test_the_unapprove_grant_undoes_it(): void
    {
        $approved = $this->makeChangeOrder(['status' => ChangeOrder::STATUS_APPROVED]);

        $auditor = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.unapprove',
        ]);

        Livewire::actingAs($auditor)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('returnChangeOrderToPending', $approved->id)
            ->assertOk();

        $this->assertTrue($approved->fresh()->isPending());
    }

    public function test_turning_down_something_still_pending_is_an_ordinary_review_decision(): void
    {
        $pending = $this->makeChangeOrder();

        // Its lines are not in the budget yet, so nothing is pulled out and
        // `unapprove` is not needed.
        $reviewer = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.approve',
        ]);

        Livewire::actingAs($reviewer)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('rejectChangeOrder', $pending->id)
            ->assertOk();

        $this->assertTrue($pending->fresh()->isRejected());
    }

    /*
    |---------------------------------------------------------------------------
    | §4b question 4 — deleting an approved change order
    |---------------------------------------------------------------------------
    */

    public function test_an_approved_change_order_cannot_be_deleted_by_anybody(): void
    {
        $approved = $this->makeChangeOrder(['status' => ChangeOrder::STATUS_APPROVED]);

        // Not even an administrator: the rule is about the record, like a
        // locked budget in M6. Deleting it would take its cost lines out of
        // every budget they revised with no trace of the revision.
        Livewire::actingAs($this->admin)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('deleteChangeOrder', $approved->id)
            ->assertForbidden();

        $this->assertNotNull($approved->fresh());

        // Undo the approval and it can go.
        $approved->returnToPending();

        Livewire::actingAs($this->admin)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('deleteChangeOrder', $approved->id)
            ->assertOk();

        $this->assertNull($approved->fresh());
    }

    public function test_deleting_needs_its_own_grant(): void
    {
        $changeOrder = $this->makeChangeOrder();

        $editor = $this->memberOf($this->project, [
            'change-orders.view', 'change-orders.create', 'change-orders.edit',
        ]);

        Livewire::actingAs($editor)
            ->test(ProjectChangeOrders::class, ['project' => $this->project])
            ->call('deleteChangeOrder', $changeOrder->id)
            ->assertForbidden();

        $this->assertNotNull($changeOrder->fresh());
    }

    public function test_the_delete_button_is_not_offered_on_an_approved_change_order(): void
    {
        $this->makeChangeOrder(['status' => ChangeOrder::STATUS_APPROVED, 'title' => 'Locked in']);

        $this->actingAs($this->admin)->get(route('projects.change-orders', $this->project))
            ->assertOk()
            ->assertSee('Locked in')
            ->assertDontSee('deleteChangeOrder(');
    }

    /*
    |---------------------------------------------------------------------------
    | Scoping, files and the seeds
    |---------------------------------------------------------------------------
    */

    public function test_a_change_order_of_another_project_cannot_be_reached(): void
    {
        $other = $this->makeProject('Elsewhere');

        $foreign = ChangeOrder::create([
            'project_id' => $other->id,
            'co_number' => 'CO-FOREIGN',
            'title' => 'Not yours',
            'requested_date' => now()->toDateString(),
            'status' => ChangeOrder::STATUS_PENDING,
            'amount' => 100,
            'created_by' => $this->admin->id,
        ]);

        foreach (['approveChangeOrder', 'deleteChangeOrder', 'openChangeOrderViewModal'] as $action) {
            try {
                Livewire::actingAs($this->admin)
                    ->test(ProjectChangeOrders::class, ['project' => $this->project])
                    ->call($action, $foreign->id);

                $this->fail("{$action} reached a change order of another project.");
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                // Right answer.
            }
        }

        $this->assertNotNull($foreign->fresh());
    }

    public function test_a_change_order_file_is_only_served_to_somebody_who_may_see_it(): void
    {
        Storage::fake('local');
        Storage::put('change_orders/signed.pdf', 'x');

        $this->makeChangeOrder([
            'job_site_id' => $this->site->id,
            'file_path' => 'change_orders/signed.pdf',
        ]);

        $reader = $this->memberOf($this->site, ['change-orders.view']);
        $outsider = $this->memberOf($this->makeSite($this->project, 'Site B'), ['change-orders.view']);

        $this->actingAs($reader)
            ->get(route('files.show', ['path' => 'change_orders/signed.pdf']))->assertOk();
        $this->actingAs($outsider)
            ->get(route('files.show', ['path' => 'change_orders/signed.pdf']))->assertForbidden();
    }

    public function test_no_seeded_role_or_template_may_approve_its_own_or_undo_an_approval(): void
    {
        foreach (['manager', 'employee'] as $name) {
            $held = Role::where('name', $name)->firstOrFail()->abilityRows()->pluck('ability')->all();

            $this->assertNotContains('change-orders.approve_own', $held, $name);
            $this->assertNotContains('change-orders.unapprove', $held, $name);
        }

        foreach (PermissionTemplate::all() as $template) {
            $this->assertNotContains('change-orders.approve_own', $template->abilities(), $template->key);
            $this->assertNotContains('change-orders.unapprove', $template->abilities(), $template->key);
        }
    }

    public function test_approving_is_declared_limited_and_the_rest_are_not(): void
    {
        $catalog = \App\Services\AbilityCatalog::class;

        $this->assertTrue($catalog::isLimited('change-orders.approve'));
        $this->assertFalse($catalog::isLimited('change-orders.unapprove'));
        $this->assertFalse($catalog::isLimited('change-orders.create'));
    }
}
