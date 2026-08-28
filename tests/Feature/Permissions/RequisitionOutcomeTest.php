<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectRequisitions;
use App\Mail\RequisitionAwaitingMail;
use App\Mail\RequisitionDecidedMail;
use App\Models\Client;
use App\Models\DefaultAssignment;
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
 * The two remaining silences in the approval half of the chain.
 *
 *  1. **The answer never came back.** Approved or rejected, whoever asked
 *     found out by going and looking — and the rejection reason, a required
 *     field, reached nobody at all.
 *  2. **Nothing chased a decision.** A submitted requisition could sit
 *     `pending` for ever; the stall reminder only started counting after
 *     approval, so the period when the site is actually blocked was the one
 *     period nothing watched.
 */
class RequisitionOutcomeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        Mail::fake();

        $this->admin = $this->user('admin', ['name' => 'The Approver']);
        $this->project = $this->makeProject('Outcomes');
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
                ['company_name' => 'Outcome Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-out@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function memberOf(array $abilities, string $name = 'Member'): User
    {
        $user = $this->user('employee', [
            'name' => $name,
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
        ])->syncAbilities(array_merge(['project.view'], $abilities));

        app(PermissionResolver::class)->flush();

        return $user;
    }

    protected function raiser(string $name = 'The Foreman'): User
    {
        return $this->memberOf([
            'requisitions.view', 'requisitions.create', 'requisitions.edit', 'requisitions.submit',
        ], $name);
    }

    protected function approver(string $name = 'The Manager'): User
    {
        return $this->memberOf(['requisitions.view', 'requisitions.approve'], $name);
    }

    protected function buyer(string $name = 'The Buyer'): User
    {
        return $this->memberOf([
            'requisitions.view', 'quotations.view', 'quotations.create',
        ], $name);
    }

    /** A submitted requisition, with its history row so the wait can be timed. */
    protected function pendingRequisition(User $raiser, int $submittedDaysAgo = 0, array $attributes = []): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::createWithNumber(array_merge([
            'project_id' => $this->project->id,
            'type' => 'material',
            'title' => 'Cement and rebar',
            'priority' => 'normal',
            'status' => 'pending',
            'created_by' => $raiser->id,
        ], $attributes));

        $requisition->items()->create([
            'item_name' => 'Cement',
            'item_type' => 'custom',
            'quantity' => 20,
            'unit' => 'bag',
            'sort_order' => 0,
        ]);

        // `created_at` is not fillable on the history model, so it has to be
        // forced — passing it to create() is silently ignored and every row
        // ends up stamped "now".
        $requisition->statusHistories()->create([
            'old_status' => 'draft',
            'new_status' => 'pending',
            'changed_by' => $raiser->id,
        ])->forceFill([
            'created_at' => now()->subDays($submittedDaysAgo),
            'updated_at' => now()->subDays($submittedDaysAgo),
        ])->save();

        return $requisition;
    }

    /*
    |---------------------------------------------------------------------------
    | 1. The answer comes back
    |---------------------------------------------------------------------------
    */

    public function test_the_requester_is_told_it_was_approved(): void
    {
        $raiser = $this->raiser();
        $requisition = $this->pendingRequisition($raiser);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', '')
            ->call('approveRequisition', $requisition->id)
            ->assertHasNoErrors();

        Mail::assertSent(RequisitionDecidedMail::class, function ($mail) use ($raiser) {
            return $mail->hasTo($raiser->email) && $mail->approved();
        });
    }

    public function test_the_requester_is_told_it_was_rejected_and_why(): void
    {
        $raiser = $this->raiser();
        $requisition = $this->pendingRequisition($raiser);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewNotes', 'The budget for this trade is already committed.')
            ->call('rejectRequisition', $requisition->id)
            ->assertHasNoErrors();

        Mail::assertSent(RequisitionDecidedMail::class, function ($mail) use ($raiser) {
            $this->assertTrue($mail->hasTo($raiser->email));
            $this->assertFalse($mail->approved());

            // The reason is a required field; this is the whole point of it.
            $this->assertStringContainsString(
                'The budget for this trade is already committed.',
                $mail->render(),
            );

            return true;
        });
    }

    public function test_the_named_requester_hears_rather_than_whoever_keyed_it_in(): void
    {
        // Office staff raise it on behalf of somebody on site: the person it
        // is *for* is the one waiting on the answer.
        $clerk = $this->raiser('The Clerk');
        $foreman = $this->memberOf(['requisitions.view'], 'The Foreman');

        $requisition = $this->pendingRequisition($clerk, attributes: ['requested_by' => $foreman->id]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->call('approveRequisition', $requisition->id);

        Mail::assertSent(RequisitionDecidedMail::class, fn ($mail) => $mail->hasTo($foreman->email));
        Mail::assertNotSent(RequisitionDecidedMail::class, fn ($mail) => $mail->hasTo($clerk->email));
    }

    public function test_deciding_on_your_own_requisition_tells_you_nothing(): void
    {
        $both = $this->memberOf([
            'requisitions.view', 'requisitions.create', 'requisitions.submit',
            'requisitions.approve', 'requisitions.approve_own',
        ], 'Both Hats');

        $requisition = $this->pendingRequisition($both);

        Livewire::actingAs($both)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->call('approveRequisition', $requisition->id)
            ->assertHasNoErrors();

        Mail::assertNotSent(RequisitionDecidedMail::class);
    }

    public function test_the_approval_mail_names_the_buyer_it_was_handed_to(): void
    {
        $raiser = $this->raiser();
        $buyer = $this->buyer('Maria Silva');
        $requisition = $this->pendingRequisition($raiser);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->set('reviewAssignedBuyerId', (string) $buyer->id)
            ->call('approveRequisition', $requisition->id)
            ->assertHasNoErrors();

        Mail::assertSent(RequisitionDecidedMail::class, function ($mail) use ($raiser) {
            $this->assertTrue($mail->hasTo($raiser->email));
            $this->assertStringContainsString('Maria Silva', $mail->render());

            return true;
        });
    }

    public function test_the_outcome_is_logged_and_not_repeated(): void
    {
        $raiser = $this->raiser();
        $requisition = $this->pendingRequisition($raiser);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->call('approveRequisition', $requisition->id);

        app(\App\Services\ProcurementNotifier::class)
            ->requisitionDecided($requisition->fresh(), $this->admin);

        Mail::assertSent(RequisitionDecidedMail::class, 1);
        $this->assertSame(1, NotificationLogEntry::where('type', NotificationLogEntry::REQUISITION_DECIDED)->count());
    }

    public function test_a_still_pending_requisition_has_no_outcome_to_report(): void
    {
        $raiser = $this->raiser();
        $requisition = $this->pendingRequisition($raiser);

        app(\App\Services\ProcurementNotifier::class)->requisitionDecided($requisition, $this->admin);

        Mail::assertNotSent(RequisitionDecidedMail::class);
    }

    public function test_the_install_can_switch_the_outcome_notice_off(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_DECIDED,
            'is_enabled' => false,
        ]);

        $raiser = $this->raiser();
        $requisition = $this->pendingRequisition($raiser);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('openViewModal', $requisition->id)
            ->call('approveRequisition', $requisition->id);

        Mail::assertNotSent(RequisitionDecidedMail::class);
        $this->assertSame('approved', $requisition->fresh()->status);
    }

    /*
    |---------------------------------------------------------------------------
    | 2. A decision that never comes is chased
    |---------------------------------------------------------------------------
    */

    public function test_nothing_is_chased_before_the_configured_days(): void
    {
        $this->approver();
        $this->pendingRequisition($this->raiser(), submittedDaysAgo: 2);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNotSent(RequisitionAwaitingMail::class);
    }

    public function test_the_approver_is_chased_once_the_days_have_passed(): void
    {
        $approver = $this->approver();
        $requisition = $this->pendingRequisition($this->raiser(), submittedDaysAgo: 3);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionAwaitingMail::class, function ($mail) use ($approver, $requisition) {
            return $mail->hasTo($approver->email)
                && $mail->requisition->is($requisition)
                && $mail->daysWaiting === 3;
        });
    }

    public function test_the_chase_goes_to_the_named_approver_when_there_is_one(): void
    {
        $named = $this->approver('Named Approver');
        $other = $this->approver('Other Approver');

        DefaultAssignment::set(
            DefaultAssignment::REQUISITION_APPROVER, 'project', $this->project->id, $named->id, $this->admin->id
        );

        $this->pendingRequisition($this->raiser(), submittedDaysAgo: 3);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionAwaitingMail::class, fn ($mail) => $mail->hasTo($named->email));
        Mail::assertNotSent(RequisitionAwaitingMail::class, fn ($mail) => $mail->hasTo($other->email));
    }

    public function test_a_double_run_chases_nobody_twice(): void
    {
        $this->approver();
        $this->pendingRequisition($this->raiser(), submittedDaysAgo: 3);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();
        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionAwaitingMail::class, 1);
    }

    public function test_it_chases_again_in_the_next_window_and_then_stops(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_AWAITING,
            'is_enabled' => true,
            'options' => ['days' => 3, 'max_reminders' => 2],
        ]);

        $this->approver();
        $raiser = $this->raiser();
        $requisition = $this->pendingRequisition($raiser, submittedDaysAgo: 3);

        $history = $requisition->statusHistories()->first();

        foreach ([3, 6, 9, 12] as $days) {
            $history->forceFill(['created_at' => now()->subDays($days)])->save();
            $this->artisan('procurement:notify-stalled')->assertSuccessful();
        }

        Mail::assertSent(RequisitionAwaitingMail::class, 2);
    }

    public function test_a_decided_requisition_is_no_longer_chased(): void
    {
        $this->approver();
        $requisition = $this->pendingRequisition($this->raiser(), submittedDaysAgo: 30);

        $requisition->update(['status' => 'approved']);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNotSent(RequisitionAwaitingMail::class);
    }

    public function test_resubmitting_restarts_the_clock(): void
    {
        $this->approver();
        $raiser = $this->raiser();
        $requisition = $this->pendingRequisition($raiser, submittedDaysAgo: 30);

        // Pulled back and sent again today: it has been waiting a day, not a
        // month, so nothing is chased yet.
        $requisition->statusHistories()->create([
            'old_status' => 'draft',
            'new_status' => 'pending',
            'changed_by' => $raiser->id,
        ]);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNotSent(RequisitionAwaitingMail::class);
    }

    public function test_the_chase_skips_somebody_who_could_not_act_on_it(): void
    {
        // N2 again: an approver named as the requester cannot decide without
        // `approve_own`, so chasing them is a dead letter.
        $blocked = $this->approver('Named As Requester');
        $free = $this->approver('Free To Act');

        $this->pendingRequisition($this->raiser(), submittedDaysAgo: 3, attributes: [
            'requested_by' => $blocked->id,
        ]);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionAwaitingMail::class, fn ($mail) => $mail->hasTo($free->email));
        Mail::assertNotSent(RequisitionAwaitingMail::class, fn ($mail) => $mail->hasTo($blocked->email));
    }

    public function test_the_install_can_switch_the_chase_off(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_AWAITING,
            'is_enabled' => false,
        ]);

        $this->approver();
        $this->pendingRequisition($this->raiser(), submittedDaysAgo: 30);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNotSent(RequisitionAwaitingMail::class);
    }

    public function test_the_command_reports_both_kinds_of_stall(): void
    {
        $this->approver();
        $this->pendingRequisition($this->raiser(), submittedDaysAgo: 5);

        $this->artisan('procurement:notify-stalled')
            ->expectsOutputToContain('waiting on a decision')
            ->assertSuccessful();
    }

    /*
    |---------------------------------------------------------------------------
    | The settings
    |---------------------------------------------------------------------------
    */

    public function test_the_chase_numbers_can_be_changed_and_are_read_back(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->set('awaitingDays', 2)
            ->set('awaitingMaxReminders', 6)
            ->call('saveProcurementOptions')
            ->assertHasNoErrors();

        $this->assertSame(2, NotificationSetting::awaitingDays());
        $this->assertSame(6, NotificationSetting::awaitingMaxReminders());

        // The quoting stall keeps its own numbers.
        $this->assertSame(NotificationSetting::DEFAULT_STALL_DAYS, NotificationSetting::stallDays());
    }

    public function test_the_settings_screen_lists_both_new_triggers(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->assertOk()
            ->assertSee(NotificationSetting::label(NotificationSetting::REQUISITION_AWAITING))
            ->assertSee(NotificationSetting::label(NotificationSetting::REQUISITION_DECIDED))
            ->assertSee(__('Days before chasing a decision'));
    }
}
