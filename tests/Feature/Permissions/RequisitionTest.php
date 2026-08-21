<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\JobSite\JobSiteRequisitions;
use App\Livewire\Project\ProjectRequisitions;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\PurchaseRequisition;
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
 * M7 — Requisitions.
 *
 * Seven grants, and the two notations this pass exists to settle:
 *
 *   N1  a submitted requisition is locked, and *Duplicate* is the honest way
 *       to raise a near-identical ask without touching a signed document.
 *   N2  the reviewer must not be the requester — lifted by a grant, not by a
 *       hard-coded exception.
 *
 * Deliberately NOT here: approval limits. A requisition asks for things, not a
 * sum; its items carry a quantity and never a price. Limits start at M8.
 */
class RequisitionTest extends TestCase
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
                ['company_name' => 'Req Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-req@example.test',
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
            'email' => str($name)->slug().'-req@example.test',
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

    protected function memberOf(Project|JobSite $scope, array $abilities): User
    {
        $user = $this->user('employee', ['access_scope' => AccessScope::ASSIGNED]);

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

    /*
    |---------------------------------------------------------------------------
    | The screens answer as they did
    |---------------------------------------------------------------------------
    */

    public function test_the_requisition_screens_answer_as_they_did_for_every_company_wide_role(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('projects.requisitions', $this->project))->assertOk();
            $this->actingAs($user)->get(route('jobsites.requisitions', $this->site))->assertOk();
        }
    }

    public function test_seeing_requisitions_is_a_grant_that_can_be_taken_away(): void
    {
        $blind = $this->roleWith(['project.view', 'projects.view']);

        $this->actingAs($blind)->get(route('projects.requisitions', $this->project))->assertForbidden();
        $this->actingAs($blind)->get(route('jobsites.requisitions', $this->site))->assertForbidden();
    }

    public function test_a_job_site_member_is_held_to_their_own_site(): void
    {
        $member = $this->memberOf($this->site, ['requisitions.view']);

        $this->actingAs($member)->get(route('jobsites.requisitions', $this->site))->assertOk();
        $this->actingAs($member)->get(route('projects.requisitions', $this->project))->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | Each grant on its own
    |---------------------------------------------------------------------------
    */

    public function test_creating_needs_its_own_grant_and_the_button_is_hidden_without_it(): void
    {
        $reader = $this->memberOf($this->project, ['requisitions.view']);

        $this->actingAs($reader)->get(route('projects.requisitions', $this->project))
            ->assertOk()
            ->assertDontSee('openAddModal');

        Livewire::actingAs($reader)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->assertForbidden();
    }

    public function test_submitting_is_a_separate_grant_from_creating(): void
    {
        $requisition = $this->makeRequisition();

        // Create but not submit: the draft saves, the submission is refused.
        $drafter = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.edit',
        ]);

        Livewire::actingAs($drafter)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id)
            ->assertForbidden();

        $this->assertSame('draft', $requisition->fresh()->status);

        // And "Save and submit" is refused too, even though "Save as draft" is not.
        Livewire::actingAs($drafter)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('req_title', 'Sand')
            ->set('itemRows', [[
                'id' => null, 'catalog_item_id' => null, 'item_name' => 'Sand',
                'item_type' => 'custom', 'description' => '', 'quantity' => 5, 'unit' => 'm3',
            ]])
            ->call('saveRequisition', 'pending')
            ->assertForbidden();
    }

    public function test_approving_and_rejecting_need_the_review_grant(): void
    {
        $requisition = $this->makeRequisition(['status' => 'pending']);

        $raiser = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.submit',
        ]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('approveRequisition', $requisition->id)
            ->assertForbidden();

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('rejectRequisition', $requisition->id)
            ->assertForbidden();

        $this->assertSame('pending', $requisition->fresh()->status);
    }

    public function test_deleting_needs_its_own_grant(): void
    {
        $requisition = $this->makeRequisition();

        $editor = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.edit',
        ]);

        Livewire::actingAs($editor)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('deleteRequisition', $requisition->id)
            ->assertForbidden();

        $this->assertNotNull($requisition->fresh());
    }

    /*
    |---------------------------------------------------------------------------
    | N1 — a submitted requisition is locked
    |---------------------------------------------------------------------------
    */

    public function test_a_submitted_requisition_can_no_longer_be_edited(): void
    {
        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->assertFalse($requisition->canBeEdited());

        // Not even by an administrator: the lock is about the document, not
        // about the person.
        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openEditModal', $requisition->id)
            ->assertForbidden();
    }

    public function test_the_raiser_can_pull_their_own_submission_back_to_draft(): void
    {
        $raiser = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.edit', 'requisitions.submit',
        ]);

        $requisition = $this->makeRequisition(['status' => 'pending', 'created_by' => $raiser->id]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('returnToDraft', $requisition->id)
            ->assertOk();

        $this->assertSame('draft', $requisition->fresh()->status);

        // …and the withdrawal is on the record.
        $this->assertSame(
            ['pending', 'draft'],
            [$requisition->statusHistories()->latest('id')->first()->old_status,
             $requisition->statusHistories()->latest('id')->first()->new_status],
        );

        // Now it is editable again.
        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openEditModal', $requisition->id)
            ->assertOk();
    }

    public function test_somebody_elses_submission_cannot_be_pulled_back_without_the_review_grant(): void
    {
        $requisition = $this->makeRequisition(['status' => 'pending', 'created_by' => $this->admin->id]);

        $other = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.edit', 'requisitions.submit',
        ]);

        Livewire::actingAs($other)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('returnToDraft', $requisition->id)
            ->assertForbidden();

        $this->assertSame('pending', $requisition->fresh()->status);

        // A reviewer may, to ask for more detail instead of rejecting outright.
        $reviewer = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.edit', 'requisitions.approve',
        ]);

        Livewire::actingAs($reviewer)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('returnToDraft', $requisition->id)
            ->assertOk();

        $this->assertSame('draft', $requisition->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | N1 — duplicate into a new draft
    |---------------------------------------------------------------------------
    */

    public function test_duplicating_copies_an_approved_requisition_into_a_fresh_draft(): void
    {
        $original = $this->makeRequisition([
            'status' => 'approved',
            'job_site_id' => $this->site->id,
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
            'review_notes' => 'Approved by the office',
            'needed_by' => now()->addWeek()->toDateString(),
        ]);

        $employee = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.duplicate',
        ]);

        Livewire::actingAs($employee)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('duplicateRequisition', $original->id)
            ->assertOk();

        $copy = PurchaseRequisition::where('id', '!=', $original->id)->latest('id')->firstOrFail();

        // Copied.
        $this->assertSame($original->title, $copy->title);
        $this->assertSame($original->type, $copy->type);
        $this->assertSame($original->job_site_id, $copy->job_site_id);
        $this->assertSame($original->items->count(), $copy->items->count());
        $this->assertSame('Cement', $copy->items->first()->item_name);

        // Not copied: the approval, and somebody else's deadline.
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->reviewed_by);
        $this->assertNull($copy->review_notes);
        $this->assertNull($copy->needed_by);

        // Owned by whoever duplicated it, with a number of its own.
        $this->assertSame($employee->id, $copy->created_by);
        $this->assertSame($employee->id, $copy->requested_by);
        $this->assertNotSame($original->requisition_number, $copy->requisition_number);

        // …and the original is untouched.
        $this->assertSame('approved', $original->fresh()->status);
    }

    public function test_duplicating_needs_its_own_grant(): void
    {
        $original = $this->makeRequisition(['status' => 'approved']);

        $employee = $this->memberOf($this->project, ['requisitions.view', 'requisitions.create']);

        Livewire::actingAs($employee)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('duplicateRequisition', $original->id)
            ->assertForbidden();

        $this->assertSame(1, PurchaseRequisition::count());
    }

    /*
    |---------------------------------------------------------------------------
    | N2 — the reviewer must not be the requester
    |---------------------------------------------------------------------------
    */

    public function test_a_reviewer_cannot_approve_a_requisition_they_raised(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.submit', 'requisitions.approve',
        ]);

        $ownRequisition = $this->makeRequisition(['status' => 'pending', 'created_by' => $reviewer->id]);

        Livewire::actingAs($reviewer)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('approveRequisition', $ownRequisition->id)
            ->assertForbidden();

        $this->assertSame('pending', $ownRequisition->fresh()->status);
    }

    public function test_being_named_as_the_requester_counts_as_raising_it(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.approve',
        ]);

        // Keyed in by somebody else, but asked for on this person's behalf.
        $requisition = $this->makeRequisition([
            'status' => 'pending',
            'created_by' => $this->admin->id,
            'requested_by' => $reviewer->id,
        ]);

        Livewire::actingAs($reviewer)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('approveRequisition', $requisition->id)
            ->assertForbidden();
    }

    public function test_self_approval_is_lifted_by_a_grant_rather_than_a_hard_coded_exception(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.submit',
            'requisitions.approve', 'requisitions.approve_own',
        ]);

        $ownRequisition = $this->makeRequisition(['status' => 'pending', 'created_by' => $reviewer->id]);

        Livewire::actingAs($reviewer)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('approveRequisition', $ownRequisition->id)
            ->assertOk();

        $this->assertSame('approved', $ownRequisition->fresh()->status);
    }

    public function test_rejecting_your_own_requisition_is_not_blocked(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.approve',
        ]);

        $ownRequisition = $this->makeRequisition(['status' => 'pending', 'created_by' => $reviewer->id]);

        Livewire::actingAs($reviewer)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->set('reviewNotes', 'Changed my mind')
            ->call('rejectRequisition', $ownRequisition->id)
            ->assertOk();

        $this->assertSame('rejected', $ownRequisition->fresh()->status);
    }

    public function test_the_screen_says_why_it_will_not_let_you_approve_your_own(): void
    {
        $reviewer = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.approve',
        ]);

        $this->makeRequisition(['status' => 'pending', 'created_by' => $reviewer->id]);

        $this->actingAs($reviewer)->get(route('projects.requisitions', $this->project))
            ->assertOk();

        // The refusal is worded on the detail view rather than being a silent
        // missing button.
        $this->assertStringContainsString(
            'You raised this requisition, so somebody else has to approve it.',
            file_get_contents(resource_path('views/livewire/requisition/partials/view-modal.blade.php')),
        );
    }

    public function test_no_seeded_role_or_template_may_approve_its_own(): void
    {
        foreach (['manager', 'employee'] as $name) {
            $role = Role::where('name', $name)->firstOrFail();

            $this->assertNotContains(
                'requisitions.approve_own',
                $role->abilityRows()->pluck('ability')->all(),
                "The {$name} role was seeded with requisitions.approve_own.",
            );
        }

        foreach (PermissionTemplate::all() as $template) {
            $this->assertNotContains('requisitions.approve_own', $template->abilities(), $template->key);
        }
    }

    /*
    |---------------------------------------------------------------------------
    | Attachments, and the catalogue's own claims
    |---------------------------------------------------------------------------
    */

    public function test_a_requisition_attachment_is_only_served_to_somebody_who_may_see_it(): void
    {
        Storage::fake('local');
        Storage::put('requisitions/spec.pdf', 'x');

        $requisition = $this->makeRequisition(['job_site_id' => $this->site->id]);
        $requisition->attachments()->create([
            'file_path' => 'requisitions/spec.pdf',
            'original_name' => 'spec.pdf',
            'uploaded_by' => $this->admin->id,
        ]);

        $reader = $this->memberOf($this->site, ['requisitions.view']);
        $outsider = $this->memberOf($this->makeSite($this->project, 'Site B'), ['requisitions.view']);

        $this->actingAs($reader)->get(route('files.show', ['path' => 'requisitions/spec.pdf']))->assertOk();
        $this->actingAs($outsider)->get(route('files.show', ['path' => 'requisitions/spec.pdf']))->assertForbidden();
    }

    public function test_a_requisition_carries_no_money_so_approval_is_not_value_limited(): void
    {
        // Recorded because the catalogue used to claim otherwise. Items have a
        // quantity and a unit and never a price; the round is where money
        // arrives. Limits start at M8.
        $this->assertFalse(\App\Services\AbilityCatalog::area('requisitions')['money']);
        $this->assertFalse(\App\Services\AbilityCatalog::isLimited('requisitions.approve'));

        $requisition = $this->makeRequisition();

        $this->assertFalse(
            in_array('price', array_keys($requisition->items->first()->getAttributes()), true),
        );
    }
}
