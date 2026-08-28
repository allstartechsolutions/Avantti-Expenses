<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\JobSite\JobSiteQuotations;
use App\Livewire\Project\ProjectQuotations;
use App\Mail\QuotationAssignedMail;
use App\Models\Client;
use App\Models\DefaultAssignment;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 of docs/procurement-assignment-plan.md: who works the round.
 *
 * "Ownership carries onto the round and survives reassignment." The three
 * things that has to mean:
 *
 *  - a round raised from a requisition inherits that requisition's buyer,
 *    including when the person raising it may not assign — it is not their
 *    decision to make;
 *  - one owner, plus collaborators, with the owner never duplicated in the
 *    pivot;
 *  - and a pivot row grants nothing at all.
 */
class QuotationAssignmentTest extends TestCase
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

        Mail::fake();

        $this->admin = $this->user('admin', ['name' => 'The Manager']);
        $this->project = $this->makeProject('Rounds');
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
                ['company_name' => 'Round Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-round@example.test',
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
            'email' => str($name)->slug().'-round@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeQuotation(array $attributes = []): Quotation
    {
        $quotation = Quotation::createWithNumber(array_merge([
            'project_id' => $this->project->id,
            'type' => 'material',
            'title' => 'Cement round',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ], $attributes));

        $quotation->items()->create([
            'item_name' => 'Cement',
            'item_type' => 'custom',
            'quantity' => 20,
            'unit' => 'bag',
            'sort_order' => 0,
        ]);

        return $quotation;
    }

    protected function makeRequisition(array $attributes = []): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::createWithNumber(array_merge([
            'project_id' => $this->project->id,
            'type' => 'material',
            'title' => 'Cement and rebar',
            'priority' => 'normal',
            'status' => 'approved',
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

    /** Somebody who may work a round here, and so may be given one. */
    protected function worker(Project|JobSite $scope, string $name = 'Worker'): User
    {
        return $this->memberOf($scope, [
            'requisitions.view', 'quotations.view', 'quotations.create', 'quotations.edit',
        ], $name);
    }

    /*
    |---------------------------------------------------------------------------
    | Ownership carries onto the round
    |---------------------------------------------------------------------------
    */

    public function test_a_round_raised_from_a_requisition_inherits_its_buyer(): void
    {
        $buyer = $this->worker($this->project, 'The Buyer');
        $requisition = $this->makeRequisition([
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAddFromRequisition', $requisition->id)
            ->assertSet('quo_assigned_to', (string) $buyer->id);
    }

    public function test_the_inheritance_happens_even_for_somebody_who_may_not_assign(): void
    {
        $buyer = $this->worker($this->project, 'The Buyer');

        // May raise a round, may not decide who works it.
        $raiser = $this->memberOf($this->project, [
            'requisitions.view', 'quotations.view', 'quotations.create',
        ], 'The Raiser');

        $requisition = $this->makeRequisition([
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($raiser)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAddFromRequisition', $requisition->id)
            ->set('quo_title', 'From the requisition')
            ->set('itemRows', [[
                'id' => null, 'requisition_item_id' => null, 'catalog_item_id' => null,
                'item_name' => 'Cement', 'item_type' => 'custom', 'description' => '',
                'quantity' => 10, 'unit' => 'bag',
            ]])
            ->call('saveQuotation')
            ->assertHasNoErrors();

        $quotation = Quotation::where('title', 'From the requisition')->firstOrFail();

        $this->assertSame(
            $buyer->id,
            $quotation->assigned_to,
            'Carrying the hand-off forward is not a decision the raiser is making.'
        );
        $this->assertNotNull($quotation->assigned_at);
    }

    public function test_a_standalone_round_falls_to_the_default_then_to_whoever_raises_it(): void
    {
        $defaultBuyer = $this->worker($this->project, 'Default Buyer');

        DefaultAssignment::set(
            DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, $defaultBuyer->id, $this->admin->id
        );

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAddModal')
            ->assertSet('quo_assigned_to', (string) $defaultBuyer->id);

        // With no default at all, it falls to the person keying it in.
        DefaultAssignment::set(
            DefaultAssignment::QUOTATION_BUYER, 'project', $this->project->id, null, $this->admin->id
        );

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openAddModal')
            ->assertSet('quo_assigned_to', (string) $this->admin->id);
    }

    /*
    |---------------------------------------------------------------------------
    | Assigning is its own grant
    |---------------------------------------------------------------------------
    */

    public function test_reassigning_a_round_needs_the_assign_grant(): void
    {
        $worker = $this->worker($this->project);
        $bystander = $this->memberOf($this->project, ['quotations.view'], 'Bystander');

        $quotation = $this->makeQuotation();

        Livewire::actingAs($bystander)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('roundOwnerId', (string) $worker->id)
            ->call('assignRound', $quotation->id)
            ->assertForbidden();

        $this->assertNull($quotation->fresh()->assigned_to);
    }

    public function test_adding_and_removing_collaborators_needs_the_assign_grant(): void
    {
        $worker = $this->worker($this->project);
        $bystander = $this->memberOf($this->project, ['quotations.view'], 'Bystander');

        $quotation = $this->makeQuotation();

        Livewire::actingAs($bystander)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('roundCollaboratorId', (string) $worker->id)
            ->call('addCollaborator', $quotation->id)
            ->assertForbidden();

        Livewire::actingAs($bystander)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('removeCollaborator', $quotation->id, $worker->id)
            ->assertForbidden();

        $this->assertSame(0, $quotation->assignees()->count());
    }

    public function test_the_grant_is_checked_against_the_round_not_the_page(): void
    {
        $siteB = $this->makeSite($this->project, 'Site B');
        $worker = $this->worker($this->project);

        $assigner = $this->memberOf($this->project, ['quotations.view'], 'Site B Assigner');

        $onSiteB = Membership::create([
            'user_id' => $assigner->id,
            'scopeable_type' => JobSite::class,
            'scopeable_id' => $siteB->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $onSiteB->syncAbilities(['project.view', 'quotations.view', 'quotations.assign']);

        app(PermissionResolver::class)->flush();

        $roundOnSiteA = $this->makeQuotation(['job_site_id' => $this->site->id]);

        Livewire::actingAs($assigner)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('roundOwnerId', (string) $worker->id)
            ->call('assignRound', $roundOnSiteA->id)
            ->assertForbidden();

        $this->assertNull($roundOnSiteA->fresh()->assigned_to);
    }

    /*
    |---------------------------------------------------------------------------
    | One owner, plus collaborators
    |---------------------------------------------------------------------------
    */

    public function test_a_round_can_be_reassigned_and_the_history_says_so(): void
    {
        $first = $this->worker($this->project, 'First Owner');
        $second = $this->worker($this->project, 'Second Owner');

        $quotation = $this->makeQuotation(['assigned_to' => $first->id, 'assigned_at' => now()->subDay()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->set('roundOwnerId', (string) $second->id)
            ->call('assignRound', $quotation->id)
            ->assertHasNoErrors();

        $quotation->refresh();

        $this->assertSame($second->id, $quotation->assigned_to);

        $history = $quotation->statusHistories()->latest('id')->first();
        $this->assertStringContainsString('First Owner', $history->reason);
        $this->assertStringContainsString('Second Owner', $history->reason);
        $this->assertSame($history->old_status, $history->new_status);
    }

    public function test_the_owner_is_never_duplicated_in_the_pivot(): void
    {
        $helper = $this->worker($this->project, 'The Helper');
        $quotation = $this->makeQuotation(['assigned_to' => $this->admin->id, 'assigned_at' => now()]);

        $page = Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id);

        $page->set('roundCollaboratorId', (string) $helper->id)
            ->call('addCollaborator', $quotation->id)
            ->assertHasNoErrors();

        $this->assertSame(1, $quotation->fresh()->assignees()->count());

        // Promote the collaborator to owner: they must not be both.
        $page->set('roundOwnerId', (string) $helper->id)
            ->call('assignRound', $quotation->id)
            ->assertHasNoErrors();

        $quotation->refresh();

        $this->assertSame($helper->id, $quotation->assigned_to);
        $this->assertSame(0, $quotation->assignees()->count(), 'The owner is an implicit collaborator, never both.');
        $this->assertSame(1, $quotation->workers()->count());
    }

    public function test_the_owner_cannot_be_added_as_their_own_collaborator(): void
    {
        $owner = $this->worker($this->project, 'The Owner');
        $quotation = $this->makeQuotation(['assigned_to' => $owner->id, 'assigned_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->set('roundCollaboratorId', (string) $owner->id)
            ->call('addCollaborator', $quotation->id)
            ->assertHasErrors('roundCollaboratorId');

        $this->assertSame(0, $quotation->fresh()->assignees()->count());
    }

    public function test_a_collaborator_can_be_taken_off_again(): void
    {
        $helper = $this->worker($this->project, 'The Helper');
        $quotation = $this->makeQuotation(['assigned_to' => $this->admin->id, 'assigned_at' => now()]);

        $page = Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id);

        $page->set('roundCollaboratorId', (string) $helper->id)->call('addCollaborator', $quotation->id);
        $page->call('removeCollaborator', $quotation->id, $helper->id)->assertHasNoErrors();

        $this->assertSame(0, $quotation->fresh()->assignees()->count());
        $this->assertStringContainsString(
            'The Helper',
            $quotation->statusHistories()->latest('id')->first()->reason
        );
    }

    public function test_a_closed_round_cannot_be_reassigned(): void
    {
        $worker = $this->worker($this->project);
        $quotation = $this->makeQuotation(['status' => 'cancelled']);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('roundOwnerId', (string) $worker->id)
            ->call('assignRound', $quotation->id)
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------------
    | A pivot row grants nothing
    |---------------------------------------------------------------------------
    */

    public function test_being_added_to_a_round_grants_nothing(): void
    {
        // Can see rounds here, cannot price them.
        $reader = $this->memberOf($this->project, ['quotations.view'], 'The Reader');
        $quotation = $this->makeQuotation(['assigned_to' => $this->admin->id, 'assigned_at' => now()]);

        $quotation->assignees()->attach($reader->id, [
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        app(PermissionResolver::class)->flush();

        $resolver = app(PermissionResolver::class);

        $this->assertTrue($resolver->allows($reader, 'quotations.view', $quotation));
        $this->assertFalse(
            $resolver->allows($reader, 'quotations.edit', $quotation),
            'Being on a round is a work list, not a permission.'
        );
        $this->assertFalse($resolver->allows($reader, 'quotations.assign', $quotation));
    }

    public function test_an_id_the_picker_never_offered_is_refused(): void
    {
        $stranger = $this->memberOf($this->project, ['quotations.view'], 'Stranger');
        $quotation = $this->makeQuotation();

        $page = Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id);

        $page->set('roundOwnerId', (string) $stranger->id)
            ->call('assignRound', $quotation->id)
            ->assertHasErrors('roundOwnerId');

        $page->set('roundCollaboratorId', (string) $stranger->id)
            ->call('addCollaborator', $quotation->id)
            ->assertHasErrors('roundCollaboratorId');

        $quotation->refresh();
        $this->assertNull($quotation->assigned_to);
        $this->assertSame(0, $quotation->assignees()->count());
    }

    /*
    |---------------------------------------------------------------------------
    | The mail
    |---------------------------------------------------------------------------
    */

    public function test_the_new_owner_is_mailed_but_not_the_person_who_did_it(): void
    {
        $owner = $this->worker($this->project, 'New Owner');
        $quotation = $this->makeQuotation();

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->set('roundOwnerId', (string) $owner->id)
            ->call('assignRound', $quotation->id)
            ->assertHasNoErrors();

        Mail::assertSent(QuotationAssignedMail::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->owns);
        Mail::assertSent(QuotationAssignedMail::class, 1);
    }

    public function test_taking_a_round_off_somebody_mails_nobody(): void
    {
        $owner = $this->worker($this->project, 'Former Owner');
        $quotation = $this->makeQuotation(['assigned_to' => $owner->id, 'assigned_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->set('roundOwnerId', '')
            ->call('assignRound', $quotation->id)
            ->assertHasNoErrors();

        Mail::assertNothingSent();
        $this->assertNull($quotation->fresh()->assigned_to);
    }

    public function test_a_collaborator_is_mailed_as_help_not_as_owner(): void
    {
        $helper = $this->worker($this->project, 'The Helper');
        $quotation = $this->makeQuotation(['assigned_to' => $this->admin->id, 'assigned_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->set('roundCollaboratorId', (string) $helper->id)
            ->call('addCollaborator', $quotation->id)
            ->assertHasNoErrors();

        Mail::assertSent(QuotationAssignedMail::class, function ($mail) use ($helper) {
            return $mail->hasTo($helper->email) && $mail->owns === false;
        });
    }

    public function test_removing_a_collaborator_mails_nobody(): void
    {
        $helper = $this->worker($this->project, 'The Helper');
        $quotation = $this->makeQuotation(['assigned_to' => $this->admin->id, 'assigned_at' => now()]);

        $quotation->assignees()->attach($helper->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->call('removeCollaborator', $quotation->id, $helper->id)
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_the_send_is_logged_against_the_round(): void
    {
        $owner = $this->worker($this->project, 'New Owner');
        $quotation = $this->makeQuotation();

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->set('roundOwnerId', (string) $owner->id)
            ->call('assignRound', $quotation->id);

        $entry = NotificationLogEntry::query()
            ->where('type', NotificationLogEntry::QUOTATION_ASSIGNED)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(Quotation::class, $entry->notifiable_type);
        $this->assertSame($quotation->id, $entry->notifiable_id);
        $this->assertTrue($entry->wasSent());
    }

    public function test_the_install_can_switch_the_round_mail_off(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::QUOTATION_ASSIGNED,
            'is_enabled' => false,
        ]);

        $owner = $this->worker($this->project, 'New Owner');
        $quotation = $this->makeQuotation();

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->set('roundOwnerId', (string) $owner->id)
            ->call('assignRound', $quotation->id);

        Mail::assertNothingSent();
        $this->assertSame($owner->id, $quotation->fresh()->assigned_to, 'The hand-off still happened.');
    }

    /*
    |---------------------------------------------------------------------------
    | Moving the deadline re-arms the warnings
    |---------------------------------------------------------------------------
    */

    public function test_pushing_the_response_date_re_arms_both_warnings(): void
    {
        $quotation = $this->makeQuotation([
            'status' => 'sent',
            'responses_due_at' => now()->addDays(2),
            'due_notified_at' => now()->subDay(),
            'overdue_notified_at' => now()->subHour(),
        ]);

        $quotation->update(['responses_due_at' => now()->addDays(10)]);

        $quotation->refresh();

        $this->assertNull($quotation->due_notified_at, 'A stamp that survived would disarm the reminder for good.');
        $this->assertNull($quotation->overdue_notified_at);
    }

    public function test_an_unrelated_edit_leaves_the_warning_stamps_alone(): void
    {
        $stamp = now()->subDay();

        $quotation = $this->makeQuotation([
            'status' => 'sent',
            'responses_due_at' => now()->addDays(2),
            'due_notified_at' => $stamp,
        ]);

        $quotation->update(['title' => 'Renamed']);

        $this->assertNotNull($quotation->fresh()->due_notified_at);
    }

    /*
    |---------------------------------------------------------------------------
    | The list
    |---------------------------------------------------------------------------
    */

    public function test_assigned_to_me_covers_both_owning_and_collaborating(): void
    {
        $me = $this->worker($this->project, 'Me');
        $other = $this->worker($this->project, 'Other');

        $mine = $this->makeQuotation(['title' => 'Owned by me', 'assigned_to' => $me->id, 'assigned_at' => now()]);
        $helping = $this->makeQuotation(['title' => 'Helping on', 'assigned_to' => $other->id, 'assigned_at' => now()]);
        $this->makeQuotation(['title' => 'Nothing to do with me', 'assigned_to' => $other->id, 'assigned_at' => now()]);

        $helping->assignees()->attach($me->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);

        Livewire::actingAs($me)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('assignmentFilter', 'mine')
            ->assertSee('Owned by me')
            ->assertSee('Helping on')
            ->assertDontSee('Nothing to do with me');
    }

    public function test_the_unassigned_bucket_is_visible(): void
    {
        $worker = $this->worker($this->project);

        $this->makeQuotation(['title' => 'Taken round', 'assigned_to' => $worker->id, 'assigned_at' => now()]);
        $this->makeQuotation(['title' => 'Orphan round']);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('assignmentFilter', 'unassigned')
            ->assertSee('Orphan round')
            ->assertDontSee('Taken round');
    }

    public function test_the_filter_never_widens_what_somebody_can_see(): void
    {
        $elsewhere = $this->makeProject('Not Ours');
        $me = $this->worker($this->project, 'Me');

        Quotation::createWithNumber([
            'project_id' => $elsewhere->id,
            'type' => 'material',
            'title' => 'Elsewhere round',
            'status' => 'draft',
            'assigned_to' => $me->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $this->makeQuotation(['title' => 'Here round', 'assigned_to' => $me->id, 'assigned_at' => now()]);

        Livewire::actingAs($me)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->set('assignmentFilter', 'mine')
            ->assertSee('Here round')
            ->assertDontSee('Elsewhere round');
    }

    public function test_the_job_site_page_carries_the_same_filter(): void
    {
        $me = $this->worker($this->site, 'Site Worker');

        $this->makeQuotation([
            'title' => 'Site round',
            'job_site_id' => $this->site->id,
            'assigned_to' => $me->id,
            'assigned_at' => now(),
        ]);
        $this->makeQuotation(['title' => 'Site other', 'job_site_id' => $this->site->id]);

        Livewire::actingAs($me)
            ->test(JobSiteQuotations::class, ['jobSite' => $this->site])
            ->set('assignmentFilter', 'mine')
            ->assertSee('Site round')
            ->assertDontSee('Site other');
    }

    public function test_both_full_pages_render_the_column_and_the_filter(): void
    {
        $worker = $this->worker($this->project, 'Column Worker');

        $this->makeQuotation(['title' => 'Project round', 'assigned_to' => $worker->id, 'assigned_at' => now()]);
        $this->makeQuotation(['title' => 'Site round', 'job_site_id' => $this->site->id]);

        $this->actingAs($this->admin)
            ->get(route('projects.quotations', $this->project))
            ->assertOk()
            ->assertSee(__('Worked By'))
            ->assertSee(__('Assigned to me'))
            ->assertSee('Column Worker');

        $this->actingAs($this->admin)
            ->get(route('jobsites.quotations', $this->site))
            ->assertOk()
            ->assertSee(__('Worked By'))
            ->assertSee(__('Unassigned'));
    }

    public function test_the_detail_view_shows_the_working_party(): void
    {
        $owner = $this->worker($this->project, 'Round Owner');
        $helper = $this->worker($this->project, 'Round Helper');

        $quotation = $this->makeQuotation(['assigned_to' => $owner->id, 'assigned_at' => now()]);
        $quotation->assignees()->attach($helper->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('openViewModal', $quotation->id)
            ->assertSee(__('Who works it'))
            ->assertSee('Round Owner')
            ->assertSee('Round Helper');
    }
}
