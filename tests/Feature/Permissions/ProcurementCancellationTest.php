<?php

namespace Tests\Feature\Permissions;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Enums\ProjectStatus;
use App\Livewire\Project\ProjectQuotations;
use App\Livewire\Project\ProjectRequisitions;
use App\Mail\QuotationCancelledMail;
use App\Mail\RequisitionCancelledMail;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cancelling tells the people whose work stops.
 *
 * The gap: pulling a requisition or a round changed a status on a screen
 * nobody was looking at. The buyer went on collecting prices for something
 * nobody wanted, and the person who asked for it never found out it was off.
 *
 * The round's version matters most for a reason outside the system: the
 * vendors were invited by e-mail and nothing will tell *them* it is cancelled,
 * so the person who invited them has to — and the mail says who they were.
 */
class ProcurementCancellationTest extends TestCase
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

        $this->admin = $this->user('admin', ['name' => 'The Manager']);
        $this->project = $this->makeProject('Cancellations');
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
                ['company_name' => 'Cancel Client'],
                ['contact_name' => 'C', 'email' => 'c@example.test', 'created_by' => $this->admin->id],
            )->id,
            'contact_person' => 'C',
            'email' => str($name)->slug().'-cxl@example.test',
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

    protected function buyer(string $name = 'The Buyer'): User
    {
        return $this->memberOf([
            'requisitions.view', 'quotations.view', 'quotations.create', 'quotations.edit',
        ], $name);
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
    | Cancelling a requisition
    |---------------------------------------------------------------------------
    */

    public function test_the_buyer_and_the_requester_are_both_told(): void
    {
        $buyer = $this->buyer();
        $raiser = $this->memberOf(['requisitions.view'], 'The Foreman');

        $requisition = $this->makeRequisition([
            'created_by' => $raiser->id,
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('cancelRequisition', $requisition->id);

        $this->assertSame('cancelled', $requisition->fresh()->status);

        Mail::assertSent(RequisitionCancelledMail::class, fn ($mail) => $mail->hasTo($buyer->email));
        Mail::assertSent(RequisitionCancelledMail::class, fn ($mail) => $mail->hasTo($raiser->email));
        Mail::assertSent(RequisitionCancelledMail::class, 2);
    }

    public function test_the_buyer_is_told_to_stop_getting_prices(): void
    {
        $buyer = $this->buyer();

        $requisition = $this->makeRequisition([
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('cancelRequisition', $requisition->id);

        Mail::assertSent(RequisitionCancelledMail::class, function ($mail) use ($buyer) {
            if (! $mail->hasTo($buyer->email)) {
                return false;
            }

            // The buyer's copy says something different from the requester's:
            // one has work to stop, the other has a decision to make.
            $this->assertStringContainsString(
                __('If you have already asked vendors for prices, let them know it is off.'),
                $mail->render(),
            );

            return true;
        });
    }

    public function test_nobody_is_told_about_their_own_cancellation(): void
    {
        // The buyer cancels the requisition they were quoting.
        $buyer = $this->memberOf([
            'requisitions.view', 'requisitions.edit', 'requisitions.approve',
            'quotations.view', 'quotations.create',
        ], 'Buyer Who Cancels');

        $requisition = $this->makeRequisition([
            'created_by' => $buyer->id,
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($buyer)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('cancelRequisition', $requisition->id);

        $this->assertSame('cancelled', $requisition->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_an_unassigned_requisition_only_tells_the_requester(): void
    {
        $raiser = $this->memberOf(['requisitions.view'], 'The Foreman');

        $requisition = $this->makeRequisition(['created_by' => $raiser->id]);

        Livewire::actingAs($this->admin)
            ->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('cancelRequisition', $requisition->id);

        Mail::assertSent(RequisitionCancelledMail::class, 1);
        Mail::assertSent(RequisitionCancelledMail::class, fn ($mail) => $mail->hasTo($raiser->email));
    }

    public function test_the_cancellation_is_logged_and_not_repeated(): void
    {
        $buyer = $this->buyer();
        $requisition = $this->makeRequisition([
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
            'status' => 'cancelled',
        ]);

        $notifier = app(\App\Services\ProcurementNotifier::class);
        $notifier->requisitionCancelled($requisition, $this->admin);
        $notifier->requisitionCancelled($requisition->fresh(), $this->admin);

        Mail::assertSent(RequisitionCancelledMail::class, 1);
        $this->assertSame(1, NotificationLogEntry::where('type', NotificationLogEntry::REQUISITION_CANCELLED)->count());
    }

    public function test_a_requisition_that_is_not_cancelled_tells_nobody(): void
    {
        $buyer = $this->buyer();
        $requisition = $this->makeRequisition([
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);

        app(\App\Services\ProcurementNotifier::class)->requisitionCancelled($requisition, $this->admin);

        Mail::assertNothingSent();
    }

    /*
    |---------------------------------------------------------------------------
    | Cancelling a round
    |---------------------------------------------------------------------------
    */

    public function test_the_owner_and_collaborators_are_told(): void
    {
        $owner = $this->buyer('Round Owner');
        $helper = $this->buyer('Round Helper');

        $quotation = $this->makeQuotation(['assigned_to' => $owner->id, 'assigned_at' => now()]);
        $quotation->assignees()->attach($helper->id, ['assigned_by' => $this->admin->id, 'assigned_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('cancelQuotation', $quotation->id);

        $this->assertSame('cancelled', $quotation->fresh()->status);

        Mail::assertSent(QuotationCancelledMail::class, fn ($mail) => $mail->hasTo($owner->email));
        Mail::assertSent(QuotationCancelledMail::class, fn ($mail) => $mail->hasTo($helper->email));
        Mail::assertSent(QuotationCancelledMail::class, 2);
    }

    public function test_the_mail_names_the_vendors_nobody_will_tell(): void
    {
        $owner = $this->buyer('Round Owner');
        $quotation = $this->makeQuotation(['assigned_to' => $owner->id, 'assigned_at' => now()]);

        $vendor = new \App\Models\Vendor;
        $vendor->forceFill([
            'name' => 'Ferro e Aço Ltda',
            'is_supplier' => true,
            'created_by' => $this->admin->id,
        ])->save();

        \App\Models\QuotationVendor::create([
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            'status' => 'invited',
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('cancelQuotation', $quotation->id);

        Mail::assertSent(QuotationCancelledMail::class, function ($mail) {
            $rendered = $mail->render();

            // The system will not tell the vendors anything, so the mail says
            // who they were and that it is now a phone call.
            $this->assertStringContainsString('Ferro e Aço Ltda', $rendered);
            $this->assertStringContainsString(
                __('Nothing has been sent to them about this. Let them know the round is off.'),
                $rendered,
            );

            return true;
        });
    }

    public function test_cancelling_your_own_round_tells_you_nothing(): void
    {
        $owner = $this->buyer('Round Owner');
        $quotation = $this->makeQuotation(['assigned_to' => $owner->id, 'assigned_at' => now()]);

        Livewire::actingAs($owner)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('cancelQuotation', $quotation->id);

        $this->assertSame('cancelled', $quotation->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_a_round_nobody_owns_tells_nobody(): void
    {
        $quotation = $this->makeQuotation();

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('cancelQuotation', $quotation->id);

        $this->assertSame('cancelled', $quotation->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_the_round_cancellation_is_logged(): void
    {
        $owner = $this->buyer('Round Owner');
        $quotation = $this->makeQuotation(['assigned_to' => $owner->id, 'assigned_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('cancelQuotation', $quotation->id);

        $entry = NotificationLogEntry::where('type', NotificationLogEntry::QUOTATION_CANCELLED)->first();

        $this->assertNotNull($entry);
        $this->assertSame(Quotation::class, $entry->notifiable_type);
        $this->assertSame($quotation->id, $entry->notifiable_id);
        $this->assertTrue($entry->wasSent());
    }

    /*
    |---------------------------------------------------------------------------
    | The switches
    |---------------------------------------------------------------------------
    */

    public function test_each_cancellation_notice_can_be_switched_off(): void
    {
        NotificationSetting::create([
            'key' => NotificationSetting::REQUISITION_CANCELLED,
            'is_enabled' => false,
        ]);

        $buyer = $this->buyer();
        $requisition = $this->makeRequisition([
            'assigned_buyer_id' => $buyer->id,
            'assigned_at' => now(),
        ]);
        $quotation = $this->makeQuotation(['assigned_to' => $buyer->id, 'assigned_at' => now()]);

        $page = Livewire::actingAs($this->admin);

        $page->test(ProjectRequisitions::class, ['project' => $this->project])
            ->call('cancelRequisition', $requisition->id);
        $page->test(ProjectQuotations::class, ['project' => $this->project])
            ->call('cancelQuotation', $quotation->id);

        // The requisition one is off; the round one is untouched.
        Mail::assertNotSent(RequisitionCancelledMail::class);
        Mail::assertSent(QuotationCancelledMail::class);

        // And both records were still cancelled.
        $this->assertSame('cancelled', $requisition->fresh()->status);
        $this->assertSame('cancelled', $quotation->fresh()->status);
    }

    public function test_the_settings_screen_lists_both(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\SystemSettings\NotificationSettings::class)
            ->assertOk()
            ->assertSee(NotificationSetting::label(NotificationSetting::REQUISITION_CANCELLED))
            ->assertSee(NotificationSetting::label(NotificationSetting::QUOTATION_CANCELLED));
    }
}
