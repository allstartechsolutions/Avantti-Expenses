<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Mail\QuotationDueSoonMail;
use App\Mail\RequisitionStalledMail;
use App\Models\Client;
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
use Tests\TestCase;

/**
 * Phase 5 of docs/procurement-assignment-plan.md: the three scheduled
 * reminders.
 *
 * "Stall, approaching and past due all fire once per window and survive a
 * double run." Surviving a double run is the clause that matters most — a
 * cron that fires twice, or a server whose clock slips, must not mail anybody
 * twice, and that is what the notification log is for.
 */
class ProcurementRemindersTest extends TestCase
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
        $this->project = $this->makeProject('Reminders');
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
                ['company_name' => 'Reminder Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-rem@example.test',
            'status' => ProjectStatus::CREATED,
            'created_by' => $this->admin->id,
        ]);
    }

    protected function worker(string $name = 'The Buyer'): User
    {
        $user = $this->user('employee', [
            'name' => $name,
            'access_scope' => AccessScope::ASSIGNED,
        ]);

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => Project::class,
            'scopeable_id' => $this->project->id,
            'status' => MembershipStatus::ACTIVE,
        ]);
        $membership->syncAbilities([
            'project.view', 'requisitions.view', 'quotations.view', 'quotations.create', 'quotations.edit',
        ]);

        app(PermissionResolver::class)->flush();

        return $user;
    }

    /** An approved requisition, handed over $daysAgo days ago, with no round. */
    protected function stalledRequisition(User $buyer, int $daysAgo, array $attributes = []): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::createWithNumber(array_merge([
            'project_id' => $this->project->id,
            'type' => 'material',
            'title' => 'Cement and rebar',
            'priority' => 'normal',
            'status' => 'approved',
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now()->subDays($daysAgo),
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now()->subDays($daysAgo),
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

    protected function makeQuotation(array $attributes = []): Quotation
    {
        return Quotation::createWithNumber(array_merge([
            'project_id' => $this->project->id,
            'type' => 'material',
            'title' => 'Cement round',
            'status' => 'sent',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /*
    |---------------------------------------------------------------------------
    | The stall nudge
    |---------------------------------------------------------------------------
    */

    public function test_nothing_is_sent_before_the_configured_number_of_days(): void
    {
        $buyer = $this->worker();
        $this->stalledRequisition($buyer, 6);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_buyer_is_nudged_once_the_days_have_passed(): void
    {
        $buyer = $this->worker();
        $requisition = $this->stalledRequisition($buyer, 7);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionStalledMail::class, function ($mail) use ($buyer, $requisition) {
            return $mail->hasTo($buyer->email)
                && $mail->requisition->is($requisition)
                && $mail->daysWaiting === 7;
        });
    }

    public function test_the_approver_is_copied(): void
    {
        $buyer = $this->worker();
        $this->stalledRequisition($buyer, 7);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionStalledMail::class, fn ($mail) => $mail->hasCc($this->admin->email));
    }

    public function test_the_approver_is_not_copied_when_they_are_the_buyer(): void
    {
        // Whoever approved it is also the person quoting it: one copy, not two.
        $requisition = $this->stalledRequisition($this->admin, 7);
        $requisition->update(['reviewed_by' => $this->admin->id]);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionStalledMail::class, fn ($mail) => $mail->hasCc($this->admin->email) === false);
    }

    public function test_a_double_run_on_the_same_day_mails_nobody_twice(): void
    {
        $buyer = $this->worker();
        $this->stalledRequisition($buyer, 7);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();
        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionStalledMail::class, 1);
        $this->assertSame(1, NotificationLogEntry::where('type', NotificationLogEntry::REQUISITION_STALLED)->count());
    }

    public function test_it_repeats_once_the_next_window_comes_round(): void
    {
        $buyer = $this->worker();
        $requisition = $this->stalledRequisition($buyer, 7);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();
        Mail::assertSent(RequisitionStalledMail::class, 1);

        // Still nothing on days 8–13: the same window.
        $requisition->forceFill(['assigned_at' => now()->subDays(10)])->save();
        $this->artisan('procurement:notify-stalled')->assertSuccessful();
        Mail::assertSent(RequisitionStalledMail::class, 1);

        // Day 14 is the second window.
        $requisition->forceFill(['assigned_at' => now()->subDays(14)])->save();
        $this->artisan('procurement:notify-stalled')->assertSuccessful();
        Mail::assertSent(RequisitionStalledMail::class, 2);
    }

    public function test_it_stops_shouting_after_the_cap(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_STALLED,
            'is_enabled' => true,
            'options' => ['days' => 7, 'max_reminders' => 2],
        ]);

        $buyer = $this->worker();
        $requisition = $this->stalledRequisition($buyer, 7);

        foreach ([7, 14, 21, 28] as $days) {
            $requisition->forceFill(['assigned_at' => now()->subDays($days)])->save();
            $this->artisan('procurement:notify-stalled')->assertSuccessful();
        }

        Mail::assertSent(RequisitionStalledMail::class, 2);
    }

    public function test_the_number_of_days_is_configurable(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_STALLED,
            'is_enabled' => true,
            'options' => ['days' => 3],
        ]);

        $buyer = $this->worker();
        $this->stalledRequisition($buyer, 4);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionStalledMail::class);
    }

    public function test_a_requisition_that_already_has_a_round_is_left_alone(): void
    {
        $buyer = $this->worker();
        $requisition = $this->stalledRequisition($buyer, 20);

        $this->makeQuotation(['purchase_requisition_id' => $requisition->id, 'status' => 'sent']);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_cancelled_round_does_not_count_as_being_quoted(): void
    {
        $buyer = $this->worker();
        $requisition = $this->stalledRequisition($buyer, 7);

        $this->makeQuotation(['purchase_requisition_id' => $requisition->id, 'status' => 'cancelled']);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionStalledMail::class);
    }

    public function test_an_unassigned_requisition_is_not_stalled_it_is_unassigned(): void
    {
        PurchaseRequisition::createWithNumber([
            'project_id' => $this->project->id,
            'type' => 'material',
            'title' => 'Nobody has this',
            'priority' => 'normal',
            'status' => 'approved',
            'reviewed_by' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_install_can_switch_the_stall_nudge_off(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_STALLED,
            'is_enabled' => false,
        ]);

        $buyer = $this->worker();
        $this->stalledRequisition($buyer, 30);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_person_can_opt_out_of_the_stall_nudge(): void
    {
        $buyer = $this->worker();
        $buyer->update(['notification_preferences' => [NotificationSetting::REQUISITION_STALLED => false]]);

        $this->stalledRequisition($buyer, 7);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertNothingSent();
    }

    /*
    |---------------------------------------------------------------------------
    | Responses due, and past due
    |---------------------------------------------------------------------------
    */

    public function test_the_working_party_is_warned_before_the_response_date(): void
    {
        $owner = $this->worker('The Owner');
        $helper = $this->worker('The Helper');

        $quotation = $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->addDays(2),
        ]);
        $quotation->assignees()->attach($helper->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertSent(QuotationDueSoonMail::class, fn ($mail) => $mail->hasTo($owner->email) && ! $mail->overdue);
        Mail::assertSent(QuotationDueSoonMail::class, fn ($mail) => $mail->hasTo($helper->email) && ! $mail->overdue);
        Mail::assertSent(QuotationDueSoonMail::class, 2);

        $this->assertNotNull($quotation->fresh()->due_notified_at);
    }

    public function test_a_round_beyond_the_lead_time_is_left_alone(): void
    {
        $owner = $this->worker('The Owner');

        $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->addDays(10),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_lead_time_is_configurable(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::QUOTATION_DUE_SOON,
            'is_enabled' => true,
            'options' => ['lead_days' => 14],
        ]);

        $owner = $this->worker('The Owner');

        $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->addDays(10),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertSent(QuotationDueSoonMail::class);
    }

    public function test_a_past_due_round_is_chased_as_overdue(): void
    {
        $owner = $this->worker('The Owner');

        $quotation = $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->subDays(2),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertSent(QuotationDueSoonMail::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->overdue);

        $this->assertNotNull($quotation->fresh()->overdue_notified_at);
    }

    public function test_a_double_run_does_not_warn_twice(): void
    {
        $owner = $this->worker('The Owner');

        $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->addDay(),
        ]);
        $this->makeQuotation([
            'title' => 'Late round',
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->subDay(),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();
        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertSent(QuotationDueSoonMail::class, 2);
    }

    public function test_pushing_the_deadline_re_arms_the_warning(): void
    {
        $owner = $this->worker('The Owner');

        $quotation = $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->addDay(),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();
        Mail::assertSent(QuotationDueSoonMail::class, 1);

        // The deadline moves out, which re-arms the stamp...
        $quotation->update(['responses_due_at' => now()->addDays(30)]);
        $this->assertNull($quotation->fresh()->due_notified_at);

        // ...and once a NEW date comes back inside the lead time, it warns
        // again. A deadline pushed out and pulled back to the very same date
        // does not: they have already been told about that date.
        $quotation->update(['responses_due_at' => now()->addDays(2)]);
        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertSent(QuotationDueSoonMail::class, 2);
    }

    public function test_a_deadline_pushed_out_and_pulled_back_to_the_same_date_does_not_warn_twice(): void
    {
        $owner = $this->worker('The Owner');

        $date = now()->addDay();

        $quotation = $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => $date,
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        $quotation->update(['responses_due_at' => now()->addDays(30)]);
        $quotation->update(['responses_due_at' => $date]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertSent(QuotationDueSoonMail::class, 1);
    }

    public function test_a_draft_round_has_nobody_to_chase(): void
    {
        $owner = $this->worker('The Owner');

        $this->makeQuotation([
            'status' => 'draft',
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->subDays(3),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_an_awarded_round_is_no_longer_chased(): void
    {
        $owner = $this->worker('The Owner');

        $this->makeQuotation([
            'status' => 'awarded',
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->subDays(3),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_round_with_no_owner_is_stamped_rather_than_retried_forever(): void
    {
        $quotation = $this->makeQuotation(['responses_due_at' => now()->subDay()]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNotNull(
            $quotation->fresh()->overdue_notified_at,
            'A round nobody can be mailed about must not retry every morning forever.'
        );
    }

    public function test_the_install_can_switch_each_warning_off_separately(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::QUOTATION_DUE_SOON,
            'is_enabled' => false,
        ]);

        $owner = $this->worker('The Owner');

        $soon = $this->makeQuotation([
            'title' => 'Due soon',
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->addDay(),
        ]);
        $late = $this->makeQuotation([
            'title' => 'Late',
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->subDay(),
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        // The past-due one still goes; the approaching one does not.
        Mail::assertSent(QuotationDueSoonMail::class, 1);
        Mail::assertSent(QuotationDueSoonMail::class, fn ($mail) => $mail->overdue);

        $this->assertNull($soon->fresh()->due_notified_at);
        $this->assertNotNull($late->fresh()->overdue_notified_at);
    }

    /*
    |---------------------------------------------------------------------------
    | What the reminders say
    |---------------------------------------------------------------------------
    */

    public function test_the_stall_nudge_says_how_long_and_links_to_the_round(): void
    {
        $buyer = $this->worker();
        $requisition = $this->stalledRequisition($buyer, 9);

        $this->artisan('procurement:notify-stalled')->assertSuccessful();

        Mail::assertSent(RequisitionStalledMail::class, function ($mail) use ($requisition) {
            $rendered = $mail->render();

            $this->assertStringContainsString($requisition->requisition_number, $rendered);
            $this->assertStringContainsString(
                route('projects.quotations', $this->project->id).'?requisition='.$requisition->id,
                $rendered,
            );

            return true;
        });
    }

    public function test_the_due_warning_names_the_vendors_still_to_price(): void
    {
        $owner = $this->worker('The Owner');
        $vendor = new \App\Models\Vendor;
        $vendor->forceFill([
            'name' => 'Ferro e Aço Ltda',
            'is_supplier' => true,
            'created_by' => $this->admin->id,
        ])->save();

        $quotation = $this->makeQuotation([
            'assigned_to' => $owner->id,
            'assigned_at' => now(),
            'responses_due_at' => now()->addDay(),
        ]);

        \App\Models\QuotationVendor::create([
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            'status' => 'invited',
            'created_by' => $this->admin->id,
        ]);

        $this->artisan('procurement:notify-due')->assertSuccessful();

        Mail::assertSent(QuotationDueSoonMail::class, function ($mail) {
            $this->assertStringContainsString('Ferro e Aço Ltda', $mail->render());

            return true;
        });
    }
}
