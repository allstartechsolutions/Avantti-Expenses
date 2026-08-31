<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\JobSiteStatus;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectRequisitions;
use App\Mail\RequisitionAssignedMail;
use App\Models\Client;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Services\ProcurementNotifier;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3 of docs/procurement-assignment-plan.md: the hand-off e-mail.
 *
 * "The buyer is mailed on approval-with-assignee, once, logged, and never on
 * a draft." Each clause of that is a test here, plus the two rules the whole
 * notification design rests on: the install can switch it off, and a person
 * can opt out of it.
 */
class RequisitionAssignedMailTest extends TestCase
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

        $this->admin = $this->user('admin', ['name' => 'The Approver']);
        $this->project = $this->makeProject('Mailing');
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
                ['company_name' => 'Mail Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-mail@example.test',
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
            'email' => str($name)->slug().'-mail@example.test',
            'status' => JobSiteStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
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

    protected function buyer(Project|JobSite $scope, string $name = 'The Buyer'): User
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
        $membership->syncAbilities([
            'project.view', 'requisitions.view', 'quotations.view', 'quotations.create',
        ]);

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /** Approve a requisition through the screen, handing it to $buyer. */
    protected function approveHandingTo(PurchaseRequisition $requisition, User $buyer, ?User $actor = null): void
    {
        Livewire::actingAs($actor ?? $this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('approveRequisition', $requisition->id)
            ->assertHasNoErrors();
    }

    /*
    |---------------------------------------------------------------------------
    | It is sent, once, and logged
    |---------------------------------------------------------------------------
    */

    public function test_the_buyer_is_mailed_when_an_approved_requisition_is_handed_over(): void
    {
        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->approveHandingTo($requisition, $buyer);

        Mail::assertSent(RequisitionAssignedMail::class, fn ($mail) => $mail->hasTo($buyer->email));
    }

    public function test_the_send_is_logged_before_it_leaves(): void
    {
        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->approveHandingTo($requisition, $buyer);

        $entry = NotificationLogEntry::query()
            ->where('user_id', $buyer->id)
            ->where('type', NotificationLogEntry::REQUISITION_ASSIGNED)
            ->first();

        $this->assertNotNull($entry, '"Why did I not get an e-mail?" has to be a query, not a guess.');
        $this->assertSame(PurchaseRequisition::class, $entry->notifiable_type);
        $this->assertSame($requisition->id, $entry->notifiable_id);
        $this->assertSame($buyer->email, $entry->email);
        $this->assertTrue($entry->wasSent());
        $this->assertNull($entry->error);
    }

    public function test_the_same_hand_off_does_not_mail_twice(): void
    {
        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition([
            'status' => 'approved',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        $notifier = app(ProcurementNotifier::class);

        $notifier->requisitionAssigned($requisition, $this->admin);
        $notifier->requisitionAssigned($requisition->fresh(), $this->admin);

        Mail::assertSent(RequisitionAssignedMail::class, 1);

        $this->assertSame(1, NotificationLogEntry::query()
            ->where('type', NotificationLogEntry::REQUISITION_ASSIGNED)
            ->count());
    }

    public function test_handing_it_back_to_the_same_person_later_does_mail_again(): void
    {
        $buyer = $this->buyer($this->project);
        $other = $this->buyer($this->project, 'Other Buyer');
        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->approveHandingTo($requisition, $buyer);

        $page = Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id);

        // Away, and back again a moment later.
        $this->travel(1)->minutes();
        $page->set('reviewAssignedBuyerId', (string) $other->id)->call('assignBuyer', $requisition->id);

        $this->travel(1)->minutes();
        $page->set('reviewAssignedBuyerId', (string) $buyer->id)->call('assignBuyer', $requisition->id);

        // Twice for the first buyer — it is a fresh instruction, not a repeat.
        Mail::assertSent(
            RequisitionAssignedMail::class,
            fn ($mail) => $mail->hasTo($buyer->email)
        );

        $this->assertSame(2, NotificationLogEntry::query()
            ->where('user_id', $buyer->id)
            ->where('type', NotificationLogEntry::REQUISITION_ASSIGNED)
            ->count());
    }

    /*
    |---------------------------------------------------------------------------
    | And not sent, when it should not be
    |---------------------------------------------------------------------------
    */

    public function test_a_draft_suggestion_mails_nobody(): void
    {
        $buyer = $this->buyer($this->project);

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

        Mail::assertNothingSent();
    }

    public function test_a_pending_requisition_mails_nobody_even_if_it_names_a_buyer(): void
    {
        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition([
            'status' => 'pending',
            'assigned_buyer_id' => $buyer->id,
        ]);

        app(ProcurementNotifier::class)->requisitionAssigned($requisition, $this->admin);

        Mail::assertNothingSent();
    }

    public function test_nobody_is_told_about_their_own_action(): void
    {
        // The approver hands it to themselves — legal, and silent.
        $requisition = $this->makeRequisition([
            'status' => 'approved',
            'assigned_buyer_id' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        app(ProcurementNotifier::class)->requisitionAssigned($requisition, $this->admin);

        Mail::assertNothingSent();
    }

    public function test_taking_a_requisition_back_off_somebody_mails_nobody(): void
    {
        $buyer = $this->buyer($this->project);
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

        Mail::assertNothingSent();
    }

    public function test_approving_with_nobody_selected_mails_nobody(): void
    {
        $requisition = $this->makeRequisition(['status' => 'pending']);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', '')
            ->call('approveRequisition', $requisition->id)
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }

    /*
    |---------------------------------------------------------------------------
    | The two switches
    |---------------------------------------------------------------------------
    */

    public function test_the_install_can_switch_it_off_for_everybody(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_ASSIGNED,
            'is_enabled' => false,
        ]);

        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->approveHandingTo($requisition, $buyer);

        Mail::assertNothingSent();
        $this->assertSame(0, NotificationLogEntry::query()->count());

        // The hand-off itself still happened; only the mail was suppressed.
        $this->assertSame($buyer->id, $requisition->fresh()->assigned_buyer_id);
    }

    public function test_a_person_can_opt_out_of_it(): void
    {
        $buyer = $this->buyer($this->project);
        $buyer->update([
            'notification_preferences' => [NotificationSetting::REQUISITION_ASSIGNED => false],
        ]);

        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->approveHandingTo($requisition, $buyer);

        Mail::assertNothingSent();
        $this->assertSame($buyer->id, $requisition->fresh()->assigned_buyer_id);
    }

    public function test_an_unknown_key_sends_by_default(): void
    {
        // No row for the trigger at all — a trigger added in code should send
        // until somebody deliberately switches it off.
        $this->assertSame(0, NotificationSetting::query()
            ->where('key', NotificationSetting::REQUISITION_ASSIGNED)
            ->count());

        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition(['status' => 'pending']);

        $this->approveHandingTo($requisition, $buyer);

        Mail::assertSent(RequisitionAssignedMail::class);
    }

    /*
    |---------------------------------------------------------------------------
    | What the mail says
    |---------------------------------------------------------------------------
    */

    public function test_the_subject_is_an_instruction_and_the_link_goes_to_the_round(): void
    {
        $buyer = $this->buyer($this->site, 'Site Buyer');
        $requisition = $this->makeRequisition([
            'status' => 'approved',
            'job_site_id' => $this->site->id,
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
            'title' => 'Rebar for the slab',
        ]);

        app(ProcurementNotifier::class)->requisitionAssigned($requisition, $this->admin);

        Mail::assertSent(RequisitionAssignedMail::class, function ($mail) use ($requisition) {
            $rendered = $mail->render();

            $this->assertStringContainsString(
                __('Quote :number — :title', [
                    'number' => $requisition->requisition_number,
                    'title' => 'Rebar for the slab',
                ]),
                $mail->envelope()->subject,
            );

            // Straight to raising the round on the job site it belongs to,
            // not to the generic list.
            $this->assertStringContainsString(
                route('jobsites.quotations', $this->site->id).'?requisition='.$requisition->id,
                $rendered,
            );

            $this->assertStringContainsString('Site Buyer', $rendered);
            $this->assertStringContainsString('The Approver', $rendered);

            return true;
        });
    }

    public function test_the_mail_carries_the_facts_a_buyer_needs(): void
    {
        $buyer = $this->buyer($this->project);
        $requisition = $this->makeRequisition([
            'status' => 'approved',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
            'priority' => 'urgent',
            'needed_by' => now()->addWeek(),
        ]);

        app(ProcurementNotifier::class)->requisitionAssigned($requisition, $this->admin);

        Mail::assertSent(RequisitionAssignedMail::class, function ($mail) use ($requisition) {
            $rendered = $mail->render();

            $this->assertStringContainsString($requisition->requisition_number, $rendered);
            $this->assertStringContainsString(__('Urgent'), $rendered);
            $this->assertStringContainsString(
                $requisition->needed_by->appDate(),
                $rendered,
            );
            // The one item on the fixture, with its quantity.
            $this->assertStringContainsString('Cement', $rendered);

            return true;
        });
    }

    /*
    |---------------------------------------------------------------------------
    | The settings screens
    |---------------------------------------------------------------------------
    */

    public function test_the_install_settings_screen_lists_the_purchasing_triggers(): void
    {
        // The page opens on Tax Rates, so the panel is reached by its tab.
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\SettingsIndex::class)
            ->call('switchTab', 'notifications')
            ->assertSet('activeTab', 'notifications');

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->assertOk()
            ->assertSee(__('Purchasing E-mails'))
            ->assertSee(NotificationSetting::label(NotificationSetting::REQUISITION_ASSIGNED))
            ->assertSee(__('Days before the first nudge'));
    }

    public function test_the_reminder_numbers_can_be_changed_and_are_read_back(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->set('stallDays', 10)
            ->set('stallMaxReminders', 2)
            ->set('dueLeadDays', 5)
            ->call('saveProcurementOptions')
            ->assertHasNoErrors();

        $this->assertSame(10, NotificationSetting::stallDays());
        $this->assertSame(2, NotificationSetting::stallMaxReminders());
        $this->assertSame(5, NotificationSetting::dueLeadDays());
    }

    public function test_the_reminder_numbers_are_bounded(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->set('stallDays', 0)
            ->call('saveProcurementOptions')
            ->assertHasErrors('stallDays');
    }

    public function test_saving_personal_preferences_does_not_wipe_the_purchasing_ones(): void
    {
        $person = $this->user('employee');
        $person->update([
            'notification_preferences' => [NotificationSetting::REQUISITION_ASSIGNED => false],
        ]);

        Livewire::actingAs($person)
            ->test('settings.notifications')
            ->call('save');

        $this->assertFalse(
            $person->fresh()->wantsNotification(NotificationSetting::REQUISITION_ASSIGNED),
            'Saving the page must not silently reset a group it forgot to list.'
        );
    }
}
