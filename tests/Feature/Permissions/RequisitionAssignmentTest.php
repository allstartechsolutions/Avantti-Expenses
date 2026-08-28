<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\JobSite\JobSiteRequisitions;
use App\Livewire\Project\ProjectRequisitions;
use App\Models\Client;
use App\Models\DefaultAssignment;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2 of docs/procurement-assignment-plan.md: who quotes it.
 *
 * The hole this closes is between a requisition being approved and a round
 * being raised, where nobody owned the work. What has to hold:
 *
 *  - assigning is its own grant, not implied by `requisitions.approve`;
 *  - the approve dialog is the intended moment, and it stamps `assigned_at`;
 *  - the raise form carries a *suggestion*, which is not a hand-off and is
 *    not stamped;
 *  - reassignment is visible afterwards, in the history;
 *  - and an id the picker never offered is refused by the endpoint.
 */
class RequisitionAssignmentTest extends TestCase
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
        $this->project = $this->makeProject('Assignment');
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

    protected function makeProject(string $name): Project
    {
        return Project::create([
            'project_name' => $name,
            'client_id' => Client::firstOrCreate(
                ['company_name' => 'Assign Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-assign@example.test',
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
            'email' => str($name)->slug().'-assign@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeRequisition(array $attributes = []): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::createWithNumber(array_merge([
            'project_id' => $this->project->id,
            'job_site_id' => null,
            'type' => 'material',
            'title' => 'Cement and rebar',
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ], $attributes));

        $requisition->items()->create([
            'item_name' => 'Cement',
            'item_type' => 'custom',
            'quantity' => 20,
            'unit' => 'bag',
            'sort_order' => 0,
        ]);

        return $requisition;
    }

    protected function memberOf(Project|JobSite $scope, array $abilities, string $name = 'Member'): User
    {
        $user = $this->user('employee', [
            'name' => $name,
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /** Somebody who may raise a round here, and so may be handed one. */
    protected function buyer(Project|JobSite $scope, string $name = 'Buyer'): User
    {
        return $this->memberOf($scope, ['requisitions.view', 'quotations.view', 'quotations.create'], $name);
    }

    /*
    |---------------------------------------------------------------------------
    | Assigning is its own grant
    |---------------------------------------------------------------------------
    */

    public function test_assigning_is_not_implied_by_approving(): void
    {
        $buyer = $this->buyer($this->project);

        // May approve, may not assign.
        $approver = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.approve',
        ], 'Approver');

        $requisition = $this->makeRequisition(['status' => 'pending']);

        Livewire::actingAs($approver)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('approveRequisition', $requisition->id);

        $requisition->refresh();

        $this->assertSame('approved', $requisition->status, 'They may still approve.');
        $this->assertNull(
            $requisition->assigned_buyer_id,
            'Without the assign grant the select is ignored, not honoured.'
        );
    }

    public function test_reassigning_needs_the_assign_grant(): void
    {
        $buyer = $this->buyer($this->project);
        $bystander = $this->memberOf($this->project, ['requisitions.view'], 'Bystander');

        $requisition = $this->makeRequisition(['status' => 'approved']);

        Livewire::actingAs($bystander)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('assignBuyer', $requisition->id)
            ->assertForbidden();

        $this->assertNull($requisition->fresh()->assigned_buyer_id);
    }

    /**
     * The grant is asked about the requisition, not about the page.
     *
     * This person can open the project list — a project membership gives them
     * that — and holds `requisitions.assign` on Site B only. A job-site
     * membership overrides its project membership for that site, so on a
     * Site A requisition they hold nothing but `view`, and the endpoint has to
     * say so even though the page rendered.
     */
    public function test_the_grant_is_checked_against_the_requisition_not_the_page(): void
    {
        $siteB = $this->makeSite($this->project, 'Site B');
        $buyer = $this->buyer($this->project);

        $siteAssigner = $this->memberOf($this->project, ['requisitions.view'], 'Site B Assigner');

        $onSiteB = Membership::create([
            'user_id' => $siteAssigner->id,
            'scopeable_type' => JobSite::class,
            'scopeable_id' => $siteB->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $onSiteB->syncAbilities(['project.view', 'requisitions.view', 'requisitions.assign']);

        app(PermissionResolver::class)->flush();

        $onSiteA = $this->makeRequisition(['status' => 'approved', 'job_site_id' => $this->site->id]);

        Livewire::actingAs($siteAssigner)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('assignBuyer', $onSiteA->id)
            ->assertForbidden();

        $this->assertNull($onSiteA->fresh()->assigned_buyer_id);

        // And on the site they DO hold it, the same call goes through.
        $onSiteBRequisition = $this->makeRequisition(['status' => 'approved', 'job_site_id' => $siteB->id]);

        Livewire::actingAs($siteAssigner)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('assignBuyer', $onSiteBRequisition->id)
            ->assertHasNoErrors();

        $this->assertSame($buyer->id, $onSiteBRequisition->fresh()->assigned_buyer_id);
    }

    /*
    |---------------------------------------------------------------------------
    | Approving is the intended moment
    |---------------------------------------------------------------------------
    */

    public function test_approving_hands_the_requisition_over_and_stamps_when(): void
    {
        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('approveRequisition', $requisition->id)
            ->assertHasNoErrors();

        $requisition->refresh();

        $this->assertSame('approved', $requisition->status);
        $this->assertSame($buyer->id, $requisition->assigned_buyer_id);
        $this->assertNotNull($requisition->assigned_at, 'The hand-off is stamped; phase 5 counts days from it.');
        $this->assertTrue($requisition->isAwaitingItsRound());
    }

    public function test_the_approve_dialog_opens_with_the_resolved_default_already_in_it(): void
    {
        $buyer = $this->buyer($this->project, 'Default Buyer');

        DefaultAssignment::set(
            DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $buyer->id, $this->admin->id
        );

        $requisition = $this->makeRequisition(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->assertSet('reviewAssignedBuyerId', (string) $buyer->id);
    }

    public function test_what_the_requester_suggested_beats_the_default_in_the_dialog(): void
    {
        $suggested = $this->buyer($this->project, 'Suggested');
        $default = $this->buyer($this->project, 'Default');

        DefaultAssignment::set(
            DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $default->id, $this->admin->id
        );

        $requisition = $this->makeRequisition([
            'status' => 'pending',
            'assigned_buyer_id' => $suggested->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->assertSet('reviewAssignedBuyerId', (string) $suggested->id);
    }

    public function test_approving_with_nobody_selected_leaves_it_in_the_unassigned_bucket(): void
    {
        $requisition = $this->makeRequisition(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', '')
            ->call('approveRequisition', $requisition->id)
            ->assertHasNoErrors();

        $requisition->refresh();

        $this->assertSame('approved', $requisition->status);
        $this->assertNull($requisition->assigned_buyer_id, 'Unassigned stays a legal, visible state.');
        $this->assertNull($requisition->assigned_at);
    }

    /*
    |---------------------------------------------------------------------------
    | The raise form carries a suggestion, not a hand-off
    |---------------------------------------------------------------------------
    */

    public function test_the_form_records_a_suggestion_without_stamping_a_hand_off(): void
    {
        $buyer = $this->buyer($this->project, 'Suggested');

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('req_title', 'Steel')
            ->set('req_type', 'material')
            ->set('req_priority', 'normal')
            ->set('req_assigned_buyer_id', (string) $buyer->id)
            ->set('itemRows', [[
                'id' => null, 'catalog_item_id' => null, 'item_name' => 'Rebar',
                'item_type' => 'custom', 'description' => '', 'quantity' => 5, 'unit' => 'ton',
            ]])
            ->call('saveRequisition', 'draft')
            ->assertHasNoErrors();

        $requisition = PurchaseRequisition::where('title', 'Steel')->firstOrFail();

        $this->assertSame($buyer->id, $requisition->assigned_buyer_id);
        $this->assertNull($requisition->assigned_at, 'A draft suggestion is not a hand-off.');
    }

    public function test_somebody_without_the_assign_grant_cannot_set_a_buyer_from_the_form(): void
    {
        $buyer = $this->buyer($this->project);

        $raiser = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create',
        ], 'Raiser');

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('req_title', 'Sand')
            ->set('req_type', 'material')
            ->set('req_priority', 'normal')
            ->set('req_assigned_buyer_id', (string) $buyer->id)
            ->set('itemRows', [[
                'id' => null, 'catalog_item_id' => null, 'item_name' => 'Sand',
                'item_type' => 'custom', 'description' => '', 'quantity' => 3, 'unit' => 'm3',
            ]])
            ->call('saveRequisition', 'draft')
            ->assertHasNoErrors();

        $this->assertNull(
            PurchaseRequisition::where('title', 'Sand')->firstOrFail()->assigned_buyer_id,
            'The field must not be a way around the grant.'
        );
    }

    public function test_the_add_form_opens_with_the_resolved_default_suggested(): void
    {
        $buyer = $this->buyer($this->project, 'Default Buyer');

        DefaultAssignment::set(
            DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $buyer->id, $this->admin->id
        );

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->assertSet('req_assigned_buyer_id', (string) $buyer->id);
    }

    /*
    |---------------------------------------------------------------------------
    | Reassignment is visible afterwards
    |---------------------------------------------------------------------------
    */

    public function test_reassigning_writes_both_names_into_the_history(): void
    {
        $first = $this->buyer($this->project, 'First Buyer');
        $second = $this->buyer($this->project, 'Second Buyer');

        $requisition = $this->makeRequisition([
            'status' => 'approved',
            'assigned_buyer_id' => $first->id,
            'assigned_at' => now()->subDays(3),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', (string) $second->id)
            ->call('assignBuyer', $requisition->id)
            ->assertHasNoErrors();

        $requisition->refresh();

        $this->assertSame($second->id, $requisition->assigned_buyer_id);

        $history = $requisition->statusHistories()->latest('id')->first();

        $this->assertSame($this->admin->id, $history->changed_by);
        $this->assertStringContainsString('First Buyer', $history->reason);
        $this->assertStringContainsString('Second Buyer', $history->reason);
        $this->assertSame(
            $history->old_status,
            $history->new_status,
            'Equal statuses are what mark the row as an assignment rather than a status move.'
        );
    }

    public function test_taking_a_requisition_back_off_somebody_is_recorded_too(): void
    {
        $buyer = $this->buyer($this->project, 'Former Buyer');

        $requisition = $this->makeRequisition([
            'status' => 'approved',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now()->subDay(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', '')
            ->call('assignBuyer', $requisition->id)
            ->assertHasNoErrors();

        $requisition->refresh();

        $this->assertNull($requisition->assigned_buyer_id);
        $this->assertNull($requisition->assigned_at);
        $this->assertStringContainsString('Former Buyer', $requisition->statusHistories()->latest('id')->first()->reason);
    }

    public function test_a_closed_requisition_cannot_be_reassigned(): void
    {
        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition(['status' => 'cancelled']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('assignBuyer', $requisition->id)
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Never trust an id from the browser
    |---------------------------------------------------------------------------
    */

    public function test_an_id_the_picker_never_offered_is_refused(): void
    {
        // On the project, but with no quotations.create.
        $stranger = $this->memberOf($this->project, ['requisitions.view'], 'Stranger');
        $requisition = $this->makeRequisition(['status' => 'approved']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', (string) $stranger->id)
            ->call('assignBuyer', $requisition->id)
            ->assertHasErrors('reviewAssignedBuyerId');

        $this->assertNull($requisition->fresh()->assigned_buyer_id);
    }

    public function test_approving_refuses_an_id_the_picker_never_offered(): void
    {
        $stranger = $this->memberOf($this->project, ['requisitions.view'], 'Stranger');
        $requisition = $this->makeRequisition(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', (string) $stranger->id)
            ->call('approveRequisition', $requisition->id)
            ->assertHasErrors('reviewAssignedBuyerId');

        $this->assertSame('pending', $requisition->fresh()->status, 'The approval does not go through either.');
    }

    /*
    |---------------------------------------------------------------------------
    | The list: column, filter, and no leak across projects
    |---------------------------------------------------------------------------
    */

    public function test_assigned_to_me_lists_only_this_persons_own(): void
    {
        $mine = $this->buyer($this->project, 'Mine');
        $theirs = $this->buyer($this->project, 'Theirs');

        $a = $this->makeRequisition(['status' => 'approved', 'title' => 'Mine one', 'assigned_buyer_id' => $mine->id]);
        $b = $this->makeRequisition(['status' => 'approved', 'title' => 'Theirs one', 'assigned_buyer_id' => $theirs->id]);
        $c = $this->makeRequisition(['status' => 'approved', 'title' => 'Nobody one']);

        Livewire::actingAs($mine)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('assignmentFilter', 'mine')
            ->assertSee('Mine one')
            ->assertDontSee('Theirs one')
            ->assertDontSee('Nobody one');
    }

    public function test_the_unassigned_bucket_is_visible(): void
    {
        $buyer = $this->buyer($this->project, 'Somebody');

        $this->makeRequisition(['status' => 'approved', 'title' => 'Taken one', 'assigned_buyer_id' => $buyer->id]);
        $this->makeRequisition(['status' => 'approved', 'title' => 'Orphan one']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('assignmentFilter', 'unassigned')
            ->assertSee('Orphan one')
            ->assertDontSee('Taken one');
    }

    public function test_the_filter_never_widens_what_somebody_can_see(): void
    {
        $otherProject = $this->makeProject('Not Ours');
        $buyer = $this->buyer($this->project, 'Buyer');

        PurchaseRequisition::createWithNumber([
            'project_id' => $otherProject->id,
            'type' => 'material',
            'title' => 'Elsewhere one',
            'priority' => 'normal',
            'status' => 'approved',
            'assigned_buyer_id' => $buyer->id,
            'created_by' => $this->admin->id,
        ]);

        $this->makeRequisition(['status' => 'approved', 'title' => 'Here one', 'assigned_buyer_id' => $buyer->id]);

        Livewire::actingAs($buyer)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('assignmentFilter', 'mine')
            ->assertSee('Here one')
            ->assertDontSee('Elsewhere one');
    }

    public function test_the_job_site_page_carries_the_same_filter(): void
    {
        $buyer = $this->buyer($this->site, 'Site Buyer');

        $this->makeRequisition([
            'status' => 'approved',
            'title' => 'Site one',
            'job_site_id' => $this->site->id,
            'assigned_buyer_id' => $buyer->id,
        ]);
        $this->makeRequisition([
            'status' => 'approved',
            'title' => 'Site other',
            'job_site_id' => $this->site->id,
        ]);

        Livewire::actingAs($buyer)
            ->test(JobSiteRequisitions::class, ['jobSite' => $this->site])
            ->set('assignmentFilter', 'mine')
            ->assertSee('Site one')
            ->assertDontSee('Site other');
    }

    /*
    |---------------------------------------------------------------------------
    | The picker
    |---------------------------------------------------------------------------
    */

    public function test_both_full_pages_render_the_column_and_the_filter(): void
    {
        $buyer = $this->buyer($this->project, 'Column Buyer');

        $this->makeRequisition([
            'status' => 'approved',
            'title' => 'On the project',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);
        $this->makeRequisition([
            'status' => 'approved',
            'title' => 'On the site',
            'job_site_id' => $this->site->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('projects.requisitions', $this->project))
            ->assertOk()
            ->assertSee(__('Quoted By'))
            ->assertSee(__('Assigned to me'))
            ->assertSee('Column Buyer');

        $this->actingAs($this->admin)
            ->get(route('jobsites.requisitions', $this->site))
            ->assertOk()
            ->assertSee(__('Quoted By'))
            ->assertSee(__('Assigned to me'))
            // The site's requisition is approved and nobody has it.
            ->assertSee(__('Unassigned'));
    }

    public function test_the_detail_view_shows_the_hand_off_and_its_history(): void
    {
        $first = $this->buyer($this->project, 'First Buyer');
        $second = $this->buyer($this->project, 'Second Buyer');

        $requisition = $this->makeRequisition([
            'status' => 'approved',
            'assigned_buyer_id' => $first->id,
            'assigned_at' => now()->subDays(2),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->assertSee(__('Quoted By'))
            ->assertSee('First Buyer')
            ->set('reviewAssignedBuyerId', (string) $second->id)
            ->call('assignBuyer', $requisition->id)
            ->assertSee(__('Assignment changed'))
            ->assertSee('Second Buyer');
    }

    public function test_the_picker_offers_only_people_who_can_raise_a_round(): void
    {
        $buyer = $this->buyer($this->project, 'Can Buy');
        $other = $this->memberOf($this->project, ['requisitions.view'], 'Cannot Buy');

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project]);

        $ids = $component->instance()->eligibleBuyers()->pluck('id')->all();

        $this->assertContains($buyer->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_a_job_site_requisition_offers_that_sites_own_people(): void
    {
        $siteBuyer = $this->buyer($this->site, 'Site Only Buyer');
        $requisition = $this->makeRequisition(['status' => 'approved', 'job_site_id' => $this->site->id]);

        $component = Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project]);

        $ids = $component->instance()->eligibleBuyers($requisition)->pluck('id')->all();

        $this->assertContains(
            $siteBuyer->id,
            $ids,
            'Opened from the project list, a job-site requisition still offers that site’s people.'
        );
    }
}
