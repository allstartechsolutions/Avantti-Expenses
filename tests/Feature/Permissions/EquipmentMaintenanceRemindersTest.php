<?php

namespace Tests\Feature\Permissions;

use App\Livewire\SystemSettings\NotificationSettings;
use App\Mail\EquipmentMaintenanceDueMail;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\EquipmentMaintenanceNotifier;
use App\Services\MaintenanceScheduler;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Equipment maintenance reminders — the vendor-document pattern applied to
 * maintenance (docs/equipment-module.md): 30 and 7 days before, the day it
 * is due by date or by meter, the day after; once per stage; one mail per
 * person per morning; recipients chosen or the fallback.
 */
class EquipmentMaintenanceRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $mechanic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        Mail::fake();
        Carbon::setTestNow('2026-09-16');

        $this->admin = $this->user('admin', ['name' => 'The Admin']);
        $this->mechanic = $this->roleWith(['equipment.view', 'equipment.maintain'], ['name' => 'The Mechanic']);
        $this->chooseRecipients([$this->mechanic->id]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role_id' => Role::where('name', $role)->value('id')], $attributes));
    }

    protected function roleWith(array $abilities, array $attributes = []): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid()]);
        $role->syncAbilities($abilities);

        return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
    }

    protected function chooseRecipients(array $ids): void
    {
        NotificationSetting::updateOrCreate(
            ['key' => NotificationSetting::EQUIPMENT_MAINTENANCE_DUE],
            ['is_enabled' => true, 'options' => ['recipients' => $ids]],
        );
    }

    protected function truck(string $name = 'Truck'): Equipment
    {
        return Equipment::create(['name' => $name, 'equipment_type' => 'vehicle', 'ownership' => 'owned', 'meter_type' => 'km', 'status' => 'active', 'created_by' => $this->admin->id]);
    }

    protected function maintenance(Equipment $equipment, ?int $daysFromToday, ?float $dueMeter = null): EquipmentMaintenance
    {
        return $equipment->maintenances()->create([
            'title' => 'Service '.str()->random(3),
            'maintenance_type' => 'preventive',
            'status' => 'scheduled',
            'scheduled_date' => $daysFromToday === null ? null : now()->addDays($daysFromToday)->toDateString(),
            'due_meter' => $dueMeter,
        ]);
    }

    protected function remind(): array
    {
        return app(EquipmentMaintenanceNotifier::class)->sendDueReminders();
    }

    /*
    |---------------------------------------------------------------------------
    | Stages
    |---------------------------------------------------------------------------
    */

    public function test_each_stage_fires_once_and_a_double_run_mails_nobody_twice(): void
    {
        $truck = $this->truck();
        $in30 = $this->maintenance($truck, 30);
        $in7 = $this->maintenance($truck, 7);
        $today = $this->maintenance($truck, 0);
        $yesterday = $this->maintenance($truck, -1);
        $far = $this->maintenance($truck, 60);

        $result = $this->remind();

        $this->assertSame(4, $result['maintenances']);
        $this->assertSame(1, $result['sent']);
        Mail::assertSent(EquipmentMaintenanceDueMail::class, 1);
        Mail::assertSent(EquipmentMaintenanceDueMail::class, function (EquipmentMaintenanceDueMail $mail) use ($in30, $in7, $today, $yesterday) {
            return $mail->recipient->is($this->mechanic)
                && $mail->upcoming->pluck('maintenance.id')->sort()->values()->all() == collect([$in30->id, $in7->id])->sort()->values()->all()
                && $mail->due->pluck('id')->all() == [$today->id]
                && $mail->overdue->pluck('id')->all() == [$yesterday->id];
        });

        $this->assertNotNull($in30->fresh()->notified_30_at);
        $this->assertNull($in30->fresh()->notified_7_at);
        $this->assertNotNull($in7->fresh()->notified_7_at);
        $this->assertNotNull($in7->fresh()->notified_30_at, 'Inside the 7-day window is inside the 30-day one too; both stamped.');
        $this->assertNotNull($today->fresh()->notified_due_at);
        $this->assertNotNull($yesterday->fresh()->notified_overdue_at);
        $this->assertNull($far->fresh()->notified_30_at);

        // The same morning again: nothing new to say, nothing sent.
        $again = $this->remind();
        $this->assertSame(0, $again['sent']);
        Mail::assertSent(EquipmentMaintenanceDueMail::class, 1);

        // A week later: the 7-day one is due today, today's is now overdue,
        // the 30-day one is 23 days out and still inside its told window,
        // yesterday's was already told. One mail, two rows.
        Carbon::setTestNow('2026-09-23');
        $later = $this->remind();
        $this->assertSame(1, $later['sent']);
        $this->assertNotNull($in7->fresh()->notified_due_at);
        $this->assertNull($in30->fresh()->notified_7_at, '23 days out is not the 7-day stage.');
        $this->assertNotNull($today->fresh()->notified_overdue_at);
        Mail::assertSent(EquipmentMaintenanceDueMail::class, function (EquipmentMaintenanceDueMail $mail) use ($in7, $today, $yesterday) {
            return $mail->upcoming->isEmpty()
                && $mail->due->pluck('id')->all() == [$in7->id]
                && $mail->overdue->pluck('id')->all() == [$today->id]
                && ! $mail->overdue->pluck('id')->contains($yesterday->id);
        });
    }

    public function test_a_meter_only_maintenance_reaches_its_stages_through_readings(): void
    {
        $truck = $this->truck();
        app(MaintenanceScheduler::class)->logReading($truck, 10000, '2026-09-01');
        $byMeter = $this->maintenance($truck, null, 12000);

        $this->assertSame(0, $this->remind()['maintenances'], 'Far from the reading: nothing to say.');

        // Within a tenth of the due reading: the "7-day" warning stands in.
        Carbon::setTestNow('2026-09-17');
        app(MaintenanceScheduler::class)->logReading($truck, 11000, '2026-09-10');
        $result = $this->remind();
        $this->assertSame(1, $result['maintenances']);
        $this->assertNotNull($byMeter->fresh()->notified_7_at);
        $this->assertNull($byMeter->fresh()->notified_due_at);

        // The reading reaches it the next day: due, once.
        Carbon::setTestNow('2026-09-18');
        app(MaintenanceScheduler::class)->logReading($truck, 12000, '2026-09-15');
        $this->remind();
        $this->assertNotNull($byMeter->fresh()->notified_due_at);
        $this->assertNull($byMeter->fresh()->notified_overdue_at, 'A meter reached has no overdue stage.');
        Mail::assertSent(EquipmentMaintenanceDueMail::class, 2);
    }

    public function test_a_maintenance_with_both_a_date_and_a_meter_keeps_its_seven_day_warning(): void
    {
        $truck = $this->truck();
        app(MaintenanceScheduler::class)->logReading($truck, 10000, '2026-09-01');
        $both = $this->maintenance($truck, 20, 20000);   // 20 days out, 10,000 km to go

        $this->remind();
        $both->refresh();
        $this->assertNotNull($both->notified_30_at);
        $this->assertNull($both->notified_7_at, 'Twenty days out with the meter far away is not the 7-day stage.');

        Carbon::setTestNow('2026-09-29');   // 7 days out
        $this->remind();
        $this->assertNotNull($both->fresh()->notified_7_at);
    }

    public function test_a_second_run_the_same_day_does_not_stamp_what_it_could_not_send(): void
    {
        $truck = $this->truck();
        $this->maintenance($truck, 7);
        $this->assertSame(1, $this->remind()['sent']);

        // Created after the morning digest went out.
        $later = $this->maintenance($truck, 5);
        $again = $this->remind();
        $this->assertSame(0, $again['sent']);
        $this->assertNull($later->fresh()->notified_7_at, 'Nobody was told, so tomorrow must.');

        Carbon::setTestNow('2026-09-17');
        $this->assertSame(1, $this->remind()['sent']);
        $this->assertNotNull($later->fresh()->notified_7_at);
    }

    public function test_completing_cancelling_or_retiring_ends_the_sequence(): void
    {
        $truck = $this->truck();
        $done = $this->maintenance($truck, 7);
        $dropped = $this->maintenance($truck, 7);
        $sold = $this->truck('Sold truck');
        $onSold = $this->maintenance($sold, 7);

        app(MaintenanceScheduler::class)->start($done);
        app(MaintenanceScheduler::class)->complete($done, ['completed_date' => '2026-09-16', 'meter_at_completion' => null, 'performed_by' => 'internal']);
        app(MaintenanceScheduler::class)->cancel($dropped, 'No longer needed');
        $sold->update(['status' => 'sold']);

        $this->assertSame(0, $this->remind()['maintenances']);
        $this->assertNull($onSold->fresh()->notified_7_at);
    }

    /*
    |---------------------------------------------------------------------------
    | Recipients and switches
    |---------------------------------------------------------------------------
    */

    public function test_the_fallback_is_everyone_who_may_maintain_and_chosen_recipients_replace_it(): void
    {
        $this->chooseRecipients([]);
        $viewer = $this->roleWith(['equipment.view'], ['name' => 'Viewer']);

        $names = app(EquipmentMaintenanceNotifier::class)->recipients()->pluck('name');
        $this->assertContains('The Mechanic', $names);
        $this->assertContains('The Admin', $names);
        $this->assertNotContains('Viewer', $names);

        $this->chooseRecipients([$viewer->id]);
        $this->assertSame(['Viewer'], app(EquipmentMaintenanceNotifier::class)->recipients()->pluck('name')->all());
    }

    public function test_the_company_switch_the_personal_opt_out_and_the_command(): void
    {
        $truck = $this->truck();
        $this->maintenance($truck, 7);

        NotificationSetting::updateOrCreate(['key' => NotificationSetting::EQUIPMENT_MAINTENANCE_DUE], ['is_enabled' => false]);
        $this->assertSame(0, $this->remind()['sent']);
        Mail::assertNothingSent();

        NotificationSetting::updateOrCreate(['key' => NotificationSetting::EQUIPMENT_MAINTENANCE_DUE], ['is_enabled' => true]);
        $this->mechanic->update(['notification_preferences' => [NotificationSetting::EQUIPMENT_MAINTENANCE_DUE => false]]);
        $this->assertSame(0, $this->remind()['sent']);
        $this->assertNotNull($truck->maintenances()->first()->fresh()->notified_7_at, 'A stage nobody wants is still stamped so it does not retry forever.');

        $this->mechanic->update(['notification_preferences' => null]);
        $this->maintenance($truck, 0);
        Artisan::call('equipment:notify-maintenance-due');
        $this->assertStringContainsString('1 e-mail(s) sent', Artisan::output());
        $this->assertSame(1, NotificationLogEntry::where('type', NotificationLogEntry::EQUIPMENT_MAINTENANCE_DUE)->whereNotNull('sent_at')->count());
    }

    public function test_choosing_recipients_needs_settings_edit_and_the_pages_show_the_key(): void
    {
        $reader = $this->roleWith(['settings.view']);
        Livewire::actingAs($reader)->test(NotificationSettings::class)
            ->assertSee(NotificationSetting::label(NotificationSetting::EQUIPMENT_MAINTENANCE_DUE))
            ->set('equipmentRecipients', [$this->mechanic->id])
            ->call('saveEquipmentRecipients')
            ->assertForbidden();

        $editor = $this->roleWith(['settings.view', 'settings.edit']);
        Livewire::actingAs($editor)->test(NotificationSettings::class)
            ->set('equipmentRecipients', [$this->admin->id])
            ->call('saveEquipmentRecipients')
            ->assertHasNoErrors();
        $this->assertSame([$this->admin->id], NotificationSetting::equipmentMaintenanceRecipientIds());

        // The personal page carries the key: the Volt screen merges EQUIPMENT_KEYS
        // into what it saves, so the group cannot be silently reset.
        $this->assertStringContainsString('NotificationSetting::EQUIPMENT_KEYS', file_get_contents(resource_path('views/livewire/settings/notifications.blade.php')));
    }

    public function test_the_mail_renders_with_every_section(): void
    {
        $truck = $this->truck('Kenworth T880');
        $upcoming = collect([['maintenance' => $this->maintenance($truck, 7), 'stage' => 7]]);
        $due = collect([$this->maintenance($truck, 0)]);
        $overdue = collect([$this->maintenance($truck, -3)]);

        $html = (new EquipmentMaintenanceDueMail($this->mechanic, $upcoming, $due, $overdue))->render();

        $this->assertStringContainsString('Kenworth T880', $html);
        $this->assertStringContainsString(__('Due today'), $html);
        $this->assertStringContainsString(trans_choice('Overdue by :count day|Overdue by :count days', 3, ['count' => 3]), $html);
        $this->assertStringContainsString(trans_choice('Due in :count day|Due in :count days', 7, ['count' => 7]), $html);
        $this->assertStringContainsString(route('equipment.maintenance.index', ['view' => 'overdue']), $html);
    }
}
