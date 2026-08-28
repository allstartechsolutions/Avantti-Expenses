<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Assignment\DefaultAssignmentsPanel;
use App\Livewire\Project\ProjectRequisitions;
use App\Mail\RequisitionSubmittedMail;
use App\Models\Client;
use App\Models\DefaultAssignment;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\Project;
use App\Models\PurchaseRequisition;
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
 * Submitting a requisition tells somebody.
 *
 * The gap this closes: the site raised a requisition, pressed Submit, and
 * nobody was told — the manager found out by opening the screen and noticing a
 * count. Approving already mailed the buyer; asking for the approval mailed
 * nobody.
 *
 * The rule that governs the whole thing: **the named approver is who gets
 * told, not who may approve.** `requisitions.approve` remains the only thing
 * that grants approval, which is why naming nobody still reaches everybody who
 * holds it rather than reaching no one.
 */
class RequisitionSubmittedMailTest extends TestCase
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

        $this->admin = $this->user('admin', ['name' => 'The Administrator']);
        $this->project = $this->makeProject('Submitting');
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
                ['company_name' => 'Submit Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-sub@example.test',
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
            'email' => str($name)->slug().'-sub@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function memberOf(Project|JobSite $scope, array $abilities, string $name = 'Member'): User
    {
        $user = $this->user('employee', [
            'name' => $name,
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'status' => MembershipStatus::ACTIVE,
        ])->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    protected function approver(Project|JobSite $scope, string $name = 'The Approver'): User
    {
        return $this->memberOf($scope, [
            'requisitions.view', 'requisitions.approve',
        ], $name);
    }

    protected function raiser(string $name = 'The Raiser'): User
    {
        return $this->memberOf($this->project, [
            // `edit` so the same person can pull their own submission back,
            // which is what returnToDraft asks for.
            'requisitions.view', 'requisitions.create', 'requisitions.edit', 'requisitions.submit',
        ], $name);
    }

    protected function makeRequisition(array $attributes = []): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::createWithNumber(array_merge([
            'project_id' => $this->project->id,
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

    /*
    |---------------------------------------------------------------------------
    | The named approver
    |---------------------------------------------------------------------------
    */

    public function test_the_named_approver_is_the_one_told(): void
    {
        $named = $this->approver($this->project, 'Named Approver');
        $alsoAllowed = $this->approver($this->project, 'Other Approver');
        $raiser = $this->raiser();

        DefaultAssignment::set(
            DefaultAssignment::REQUISITION_APPROVER, 'project', $this->project->id, $named->id, $this->admin->id
        );

        $requisition = $this->makeRequisition(['created_by' => $raiser->id]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($named->email));
        Mail::assertNotSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($alsoAllowed->email));
    }

    public function test_naming_nobody_reaches_everybody_who_may_approve(): void
    {
        $first = $this->approver($this->project, 'First Approver');
        $second = $this->approver($this->project, 'Second Approver');
        $bystander = $this->memberOf($this->project, ['requisitions.view'], 'Bystander');
        $raiser = $this->raiser();

        $requisition = $this->makeRequisition(['created_by' => $raiser->id]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($first->email));
        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($second->email));
        Mail::assertNotSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($bystander->email));
    }

    public function test_naming_somebody_grants_them_nothing(): void
    {
        // Named as the approver for this project, but holding no such grant.
        $named = $this->memberOf($this->project, ['requisitions.view'], 'Named But Powerless');

        DefaultAssignment::set(
            DefaultAssignment::REQUISITION_APPROVER, 'project', $this->project->id, $named->id, $this->admin->id
        );

        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->assertFalse(
            app(PermissionResolver::class)->allows($named, 'requisitions.approve', $requisition),
            'The default names who is asked, never who may decide.'
        );

        Livewire::actingAs($named)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('approveRequisition', $requisition->id)
            ->assertForbidden();
    }

    public function test_a_named_approver_who_lost_the_grant_falls_back_to_everybody(): void
    {
        $named = $this->approver($this->project, 'Named Approver');
        $other = $this->approver($this->project, 'Other Approver');
        $raiser = $this->raiser();

        DefaultAssignment::set(
            DefaultAssignment::REQUISITION_APPROVER, 'project', $this->project->id, $named->id, $this->admin->id
        );

        // The grant is taken away after they were named.
        Membership::where('user_id', $named->id)->first()->syncAbilities(['project.view', 'requisitions.view']);
        app(PermissionResolver::class)->flush();

        $requisition = $this->makeRequisition(['created_by' => $raiser->id]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($other->email));
        Mail::assertNotSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($named->email));
    }

    public function test_the_default_walks_job_site_then_project(): void
    {
        $projectApprover = $this->approver($this->project, 'Project Approver');
        $siteApprover = $this->approver($this->site, 'Site Approver');
        $raiser = $this->raiser();

        DefaultAssignment::set(
            DefaultAssignment::REQUISITION_APPROVER, 'project', $this->project->id, $projectApprover->id, $this->admin->id
        );
        DefaultAssignment::set(
            DefaultAssignment::REQUISITION_APPROVER, 'job_site', $this->site->id, $siteApprover->id, $this->admin->id
        );

        $requisition = $this->makeRequisition(['job_site_id' => $this->site->id, 'created_by' => $raiser->id]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($siteApprover->email));
        Mail::assertNotSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($projectApprover->email));
    }

    /*
    |---------------------------------------------------------------------------
    | When it fires, and when it does not
    |---------------------------------------------------------------------------
    */

    public function test_saving_a_draft_tells_nobody(): void
    {
        $this->approver($this->project);
        $raiser = $this->raiser();

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('req_title', 'Steel')
            ->set('req_type', 'material')
            ->set('req_priority', 'normal')
            ->set('itemRows', [[
                'id' => null, 'catalog_item_id' => null, 'item_name' => 'Rebar',
                'item_type' => 'custom', 'description' => '', 'quantity' => 5, 'unit' => 'ton',
            ]])
            ->call('saveRequisition', 'draft')
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_save_and_submit_tells_the_approver(): void
    {
        $approver = $this->approver($this->project);
        $raiser = $this->raiser();

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openAddModal')
            ->set('req_title', 'Steel')
            ->set('req_type', 'material')
            ->set('req_priority', 'normal')
            ->set('itemRows', [[
                'id' => null, 'catalog_item_id' => null, 'item_name' => 'Rebar',
                'item_type' => 'custom', 'description' => '', 'quantity' => 5, 'unit' => 'ton',
            ]])
            ->call('saveRequisition', 'pending')
            ->assertHasNoErrors();

        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($approver->email));
    }

    public function test_the_person_submitting_is_never_told_about_their_own_action(): void
    {
        // Somebody who may both raise and approve submits their own.
        $both = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.create', 'requisitions.submit', 'requisitions.approve',
        ], 'Both Hats');

        $requisition = $this->makeRequisition(['created_by' => $both->id]);

        Livewire::actingAs($both)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertNothingSent();
    }

    public function test_somebody_who_could_not_approve_it_anyway_is_not_asked(): void
    {
        // N2: the reviewer must not be the requester. An approver who is named
        // as the requester cannot act without `approve_own`, so mailing them
        // is a dead letter.
        $approver = $this->approver($this->project, 'Named As Requester');
        $other = $this->approver($this->project, 'Free To Act');
        $raiser = $this->raiser();

        $requisition = $this->makeRequisition([
            'created_by' => $raiser->id,
            'requested_by' => $approver->id,
        ]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($other->email));
        Mail::assertNotSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($approver->email));
    }

    public function test_the_self_approval_grant_puts_them_back_on_the_list(): void
    {
        $approver = $this->memberOf($this->project, [
            'requisitions.view', 'requisitions.approve', 'requisitions.approve_own',
        ], 'May Approve Own');
        $raiser = $this->raiser();

        $requisition = $this->makeRequisition([
            'created_by' => $raiser->id,
            'requested_by' => $approver->id,
        ]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, fn ($mail) => $mail->hasTo($approver->email));
    }

    public function test_it_is_sent_once_per_submission_and_survives_a_double_call(): void
    {
        $approver = $this->approver($this->project);
        $raiser = $this->raiser();

        $requisition = $this->makeRequisition(['created_by' => $raiser->id, 'status' => 'pending']);
        $requisition->recordStatusChange($raiser, 'draft', 'pending');

        $notifier = app(\App\Services\ProcurementNotifier::class);
        $notifier->requisitionSubmitted($requisition, $raiser);
        $notifier->requisitionSubmitted($requisition->fresh(), $raiser);

        Mail::assertSent(RequisitionSubmittedMail::class, 1);
        $this->assertSame(1, NotificationLogEntry::where('type', NotificationLogEntry::REQUISITION_SUBMITTED)->count());
    }

    public function test_pulling_it_back_and_sending_it_again_asks_again(): void
    {
        $approver = $this->approver($this->project);
        $raiser = $this->raiser();

        $requisition = $this->makeRequisition(['created_by' => $raiser->id]);

        $page = Livewire::actingAs($raiser)->test(ProjectRequisitions::class, ['project' => $this->project]);

        $page->call('submitForApproval', $requisition->id);
        Mail::assertSent(RequisitionSubmittedMail::class, 1);

        $page->call('returnToDraft', $requisition->id);
        $page->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, 2);
    }

    public function test_the_install_can_switch_it_off_and_a_person_can_opt_out(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_SUBMITTED,
            'is_enabled' => false,
        ]);

        $this->approver($this->project);
        $raiser = $this->raiser();
        $requisition = $this->makeRequisition(['created_by' => $raiser->id]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertNothingSent();

        // ...and the requisition still went to pending; only the mail stopped.
        $this->assertSame('pending', $requisition->fresh()->status);
    }

    public function test_a_person_can_opt_out_of_being_asked(): void
    {
        $approver = $this->approver($this->project);
        $approver->update(['notification_preferences' => [NotificationSetting::REQUISITION_SUBMITTED => false]]);

        $raiser = $this->raiser();
        $requisition = $this->makeRequisition(['created_by' => $raiser->id]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertNothingSent();
    }

    public function test_the_mail_says_what_is_being_asked_for_and_links_to_it(): void
    {
        $approver = $this->approver($this->project);
        $raiser = $this->raiser('Site Foreman');

        $requisition = $this->makeRequisition([
            'created_by' => $raiser->id,
            'title' => 'Rebar for the slab',
            'priority' => 'urgent',
            'needed_by' => now()->addWeek(),
        ]);

        Livewire::actingAs($raiser)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('submitForApproval', $requisition->id);

        Mail::assertSent(RequisitionSubmittedMail::class, function ($mail) use ($requisition) {
            $rendered = $mail->render();

            $this->assertStringContainsString(
                __('Approve :number — :title', [
                    'number' => $requisition->requisition_number,
                    'title' => 'Rebar for the slab',
                ]),
                $mail->envelope()->subject,
            );

            $this->assertStringContainsString('Site Foreman', $rendered);
            $this->assertStringContainsString(__('Urgent'), $rendered);
            $this->assertStringContainsString('Cement', $rendered);
            $this->assertStringContainsString(
                route('projects.requisitions', $this->project->id).'?requisition='.$requisition->id,
                $rendered,
            );

            return true;
        });
    }

    /*
    |---------------------------------------------------------------------------
    | Setting it, on the same panel as the buyer
    |---------------------------------------------------------------------------
    */

    public function test_the_approver_default_is_set_from_the_same_panel(): void
    {
        $approver = $this->approver($this->project, 'Named Approver');

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->assertOk()
            ->assertSee(__('Who approves it'))
            ->assertSee(__('Who quotes it'))
            ->set('choices.'.DefaultAssignment::REQUISITION_APPROVER, (string) $approver->id)
            ->call('save', DefaultAssignment::REQUISITION_APPROVER)
            ->assertHasNoErrors();

        $this->assertSame(
            $approver->id,
            DefaultAssignment::resolve(DefaultAssignment::REQUISITION_APPROVER, null, $this->project)?->id
        );
    }

    public function test_each_role_offers_only_people_who_hold_its_own_ability(): void
    {
        $approver = $this->approver($this->project, 'Only Approves');
        $buyer = $this->memberOf($this->project, [
            'requisitions.view', 'quotations.view', 'quotations.create',
        ], 'Only Buys');

        $panel = Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->instance();

        $approverIds = $panel->candidatesFor(DefaultAssignment::REQUISITION_APPROVER)->pluck('id')->all();
        $buyerIds = $panel->candidatesFor(DefaultAssignment::QUOTATION_BUYER)->pluck('id')->all();

        $this->assertContains($approver->id, $approverIds);
        $this->assertNotContains($buyer->id, $approverIds);

        $this->assertContains($buyer->id, $buyerIds);
        $this->assertNotContains($approver->id, $buyerIds);
    }

    public function test_an_id_that_cannot_approve_here_is_refused(): void
    {
        $buyer = $this->memberOf($this->project, [
            'requisitions.view', 'quotations.view', 'quotations.create',
        ], 'Only Buys');

        Livewire::actingAs($this->admin)
            ->test(DefaultAssignmentsPanel::class, [
                'contextType' => 'project',
                'contextId' => $this->project->id,
            ])
            ->set('choices.'.DefaultAssignment::REQUISITION_APPROVER, (string) $buyer->id)
            ->call('save', DefaultAssignment::REQUISITION_APPROVER)
            ->assertHasErrors('choices.'.DefaultAssignment::REQUISITION_APPROVER);

        $this->assertNull(DefaultAssignment::resolve(DefaultAssignment::REQUISITION_APPROVER, null, $this->project));
    }

    public function test_the_settings_screen_lists_the_new_trigger(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->assertOk()
            ->assertSee(NotificationSetting::label(NotificationSetting::REQUISITION_SUBMITTED));
    }
}
