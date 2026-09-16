<?php

namespace Tests\Feature\Equipment;

use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use App\Services\MaintenanceScheduler;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The maintenance rules (docs/equipment-module.md): due by date OR by meter,
 * whichever first; the next occurrence generated from the completion point;
 * readings that never go backwards; findings resolved in a later service.
 */
class MaintenanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected MaintenanceScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(PermissionSeeder::class)->run();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
        $this->actingAs($this->admin);

        $this->scheduler = app(MaintenanceScheduler::class);

        Carbon::setTestNow('2026-09-16');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function truck(?float $meter = 10000): Equipment
    {
        $equipment = Equipment::create([
            'name' => 'Truck', 'equipment_type' => 'vehicle', 'ownership' => 'owned', 'meter_type' => 'km', 'status' => 'active', 'created_by' => $this->admin->id,
        ]);

        if ($meter !== null) {
            $this->scheduler->logReading($equipment, $meter, '2026-09-01');
        }

        return $equipment->fresh();
    }

    public function test_a_plan_schedules_its_first_service_by_date_and_by_meter(): void
    {
        $truck = $this->truck();

        $plan = $this->scheduler->addPlan($truck, ['title' => 'Oil change', 'maintenance_type' => 'preventive', 'interval_days' => 180, 'interval_meter' => 10000]);
        $next = $plan->openOccurrence()->first();

        $this->assertNotNull($next);
        $this->assertSame('2027-03-15', $next->scheduled_date->toDateString(), 'Today plus 180 days.');
        $this->assertEquals(20000, $next->due_meter, 'The current reading plus the interval.');
        $this->assertSame('scheduled', $next->urgency());
        $this->assertSame(1, $truck->maintenances()->open()->count(), 'Exactly one open occurrence per plan.');
    }

    public function test_due_is_whichever_comes_first(): void
    {
        $truck = $this->truck();
        $plan = $this->scheduler->addPlan($truck, ['title' => 'Oil change', 'maintenance_type' => 'preventive', 'interval_days' => 180, 'interval_meter' => 10000]);
        $next = $plan->openOccurrence()->first()->setRelation('equipment', $truck);

        // The meter gets there long before the date.
        $result = $this->scheduler->logReading($truck, 20000, '2026-10-01');
        $next->setRelation('equipment', $truck->fresh());

        $this->assertTrue($next->isDueByMeter());
        $this->assertTrue($next->isDue());
        $this->assertSame('due', $next->urgency());
        $this->assertSame([$next->id], $result['nowDue']->pluck('id')->all(), 'Logging the reading says which maintenance it made due.');

        // A date-only plan is due on its date and overdue the day after.
        $dated = $this->scheduler->addPlan($truck, ['title' => 'Inspection', 'maintenance_type' => 'inspection', 'interval_days' => 30]);
        $occurrence = $dated->openOccurrence()->first();
        $this->assertFalse($occurrence->isDue(Carbon::parse('2026-10-15')));
        $this->assertTrue($occurrence->isDue(Carbon::parse('2026-10-16')));
        $this->assertSame('due', $occurrence->urgency(Carbon::parse('2026-10-16')));
        $this->assertSame('overdue', $occurrence->urgency(Carbon::parse('2026-10-17')));
        $this->assertSame('due_soon', $occurrence->urgency(Carbon::parse('2026-09-20')));
    }

    public function test_completing_logs_the_meter_and_schedules_the_next_from_the_completion_point(): void
    {
        $truck = $this->truck();
        $plan = $this->scheduler->addPlan($truck, ['title' => 'Oil change', 'maintenance_type' => 'preventive', 'interval_days' => 180, 'interval_meter' => 10000]);
        $first = $plan->openOccurrence()->first();

        $this->scheduler->start($first);
        $this->assertSame('in_maintenance', $truck->fresh()->status);

        // Done late, and at a higher reading than planned.
        Carbon::setTestNow('2027-04-01');
        $this->scheduler->complete($first, [
            'completed_date' => '2027-04-01',
            'meter_at_completion' => 21500,
            'performed_by' => 'internal',
            'performed_by_user_id' => $this->admin->id,
            'completion_notes' => 'Synthetic 5W-30',
        ]);

        $first->refresh();
        $truck->refresh();
        $this->assertSame('completed', $first->status);
        $this->assertEquals(21500, $first->meter_at_completion);
        $this->assertEquals(21500, $truck->current_meter, 'The completion reading is the current reading.');
        $this->assertSame('maintenance', $truck->readings()->first()->source);
        $this->assertSame('active', $truck->status, 'Back in service.');

        $next = $plan->fresh()->openOccurrence()->first();
        $this->assertNotNull($next, 'A plan always has its next occurrence.');
        $this->assertSame('2027-09-28', $next->scheduled_date->toDateString(), 'From the completion date, not the planned one.');
        $this->assertEquals(31500, $next->due_meter, 'From the completion reading.');
        $this->assertSame(1, $truck->maintenances()->open()->count());
    }

    public function test_cancelling_a_plans_occurrence_skips_to_the_next_and_a_one_off_just_closes(): void
    {
        $truck = $this->truck();
        $plan = $this->scheduler->addPlan($truck, ['title' => 'Inspection', 'maintenance_type' => 'inspection', 'interval_days' => 30]);
        $first = $plan->openOccurrence()->first();

        $this->scheduler->cancel($first, 'Off site');
        $this->assertSame('cancelled', $first->fresh()->status);
        $next = $plan->fresh()->openOccurrence()->first();
        $this->assertSame('2026-11-15', $next->scheduled_date->toDateString(), 'From the cancelled due date.');

        $oneOff = $this->scheduler->schedule($truck, ['title' => 'Fix mirror', 'maintenance_type' => 'corrective', 'scheduled_date' => '2026-09-20']);
        $this->scheduler->cancel($oneOff, 'Not needed');
        $this->assertSame('cancelled', $oneOff->fresh()->status);
        $this->assertSame(1, $truck->maintenances()->open()->count(), 'Only the plan\'s next occurrence remains open.');

        // Deactivating a plan cancels its pending service; reactivating re-plans it.
        $this->scheduler->deactivatePlan($plan->fresh());
        $this->assertSame(0, $truck->maintenances()->open()->count());
        $this->scheduler->reactivatePlan($plan->fresh());
        $this->assertSame(1, $truck->maintenances()->open()->count());
    }

    public function test_a_reading_never_goes_backwards_but_may_be_back_dated_between_its_neighbours(): void
    {
        $truck = $this->truck(10000); // 10000 on 2026-09-01

        try {
            $this->scheduler->logReading($truck, 9500, '2026-09-10');
            $this->fail('A lower reading was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('10,000.0 km', $e->errors()['reading'][0]);
        }

        $this->scheduler->logReading($truck, 12000, '2026-09-15');
        $this->scheduler->logReading($truck, 11000, '2026-09-10'); // between 10000 and 12000: fine
        $this->assertEquals(12000, $truck->fresh()->current_meter, 'The newest by date stays current.');

        try {
            $this->scheduler->logReading($truck, 13000, '2026-09-12'); // higher than the 12000 that followed
            $this->fail('A reading higher than the one after it was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('12,000.0 km', $e->errors()['reading'][0]);
        }

        // Equipment with no meter refuses readings outright.
        $tool = Equipment::create(['name' => 'Drill', 'equipment_type' => 'tool', 'ownership' => 'owned', 'meter_type' => 'none', 'status' => 'active', 'created_by' => $this->admin->id]);
        $this->expectException(ValidationException::class);
        $this->scheduler->logReading($tool, 1, '2026-09-10');
    }

    public function test_findings_are_recorded_on_started_work_and_resolved_in_a_later_maintenance(): void
    {
        $truck = $this->truck();
        $service = $this->scheduler->schedule($truck, ['title' => 'Service', 'maintenance_type' => 'preventive', 'scheduled_date' => '2026-09-16']);

        try {
            $this->scheduler->recordFinding($service, 'Cracked hose', 'high');
            $this->fail('A finding on a maintenance that has not started was accepted.');
        } catch (ValidationException) {
        }

        $this->scheduler->start($service);
        $finding = $this->scheduler->recordFinding($service, 'Cracked hose', 'high');
        $this->assertTrue($finding->isOpen());

        // Completing without ticking it leaves it open; it never blocks completion.
        $this->scheduler->complete($service, ['completed_date' => '2026-09-16', 'meter_at_completion' => 10050, 'performed_by' => 'internal']);
        $this->assertTrue($finding->fresh()->isOpen());
        $this->assertSame('completed', $service->fresh()->status);

        // A later repair resolves it.
        $repair = $this->scheduler->schedule($truck, ['title' => 'Replace hose', 'maintenance_type' => 'corrective', 'scheduled_date' => '2026-09-20']);
        $this->scheduler->start($repair);
        $this->scheduler->complete($repair, ['completed_date' => '2026-09-20', 'meter_at_completion' => 10100, 'performed_by' => 'vendor', 'supplier_id' => null], [$finding->id]);

        $finding->refresh();
        $this->assertFalse($finding->isOpen());
        $this->assertSame($repair->id, $finding->resolved_in_maintenance_id);
        $this->assertSame($this->admin->id, $finding->resolved_by);
        $this->assertContains('finding_resolved', $truck->histories()->pluck('action')->all());
    }

    public function test_editing_a_plan_re_aims_its_open_occurrence_without_replacing_it(): void
    {
        $truck = $this->truck();
        $plan = $this->scheduler->addPlan($truck, ['title' => 'Oil change', 'maintenance_type' => 'preventive', 'interval_days' => 180, 'interval_meter' => 10000]);
        $open = $plan->openOccurrence()->first();
        $open->update(['notified_30_at' => now()]);

        // A note only: nothing moves, the stamp stays.
        $this->scheduler->updatePlan($plan->fresh(), ['notes' => 'Use synthetic']);
        $same = $plan->fresh()->openOccurrence()->first();
        $this->assertSame($open->id, $same->id, 'The occurrence keeps its id — expenses and readings point at it.');
        $this->assertNotNull($same->notified_30_at);

        // The interval changes: same row, new due point, stamps reset.
        $this->scheduler->updatePlan($plan->fresh(), ['interval_days' => 90]);
        $moved = $plan->fresh()->openOccurrence()->first();
        $this->assertSame($open->id, $moved->id);
        $this->assertSame('2026-12-15', $moved->scheduled_date->toDateString());
        $this->assertNull($moved->notified_30_at);
        $this->assertSame(1, $truck->maintenances()->count(), 'Still one row.');
    }

    public function test_a_meter_only_plan_waits_for_a_reading(): void
    {
        $truck = $this->truck(null);

        $plan = $this->scheduler->addPlan($truck, ['title' => 'Tyres', 'maintenance_type' => 'preventive', 'interval_meter' => 40000]);
        $this->assertNull($plan->openOccurrence()->first(), 'Nothing to aim at without a reading.');

        $this->scheduler->logReading($truck, 5000, '2026-09-16');
        $this->scheduler->reactivatePlan($plan->fresh());
        $this->assertEquals(45000, $plan->fresh()->openOccurrence()->first()->due_meter);
    }
}
