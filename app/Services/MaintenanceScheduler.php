<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentFinding;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentMaintenancePlan;
use App\Models\EquipmentMeterReading;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The rules of equipment maintenance, in one place (docs/equipment-module.md):
 *
 *   - A plan is "every N days and/or every N km / hours". Exactly one open
 *     maintenance row exists per active plan — the next occurrence — and it
 *     is generated from the point the last one was COMPLETED, not the point
 *     it was planned for: a service done late does not make the next one
 *     early.
 *   - A maintenance is due when today reaches its date OR the meter reaches
 *     its reading, whichever comes first. Due-ness is computed
 *     (EquipmentMaintenance::isDue()), never stored.
 *   - A meter reading never goes backwards. Back-dating is allowed between
 *     its neighbours; the newest reading is denormalised onto the equipment.
 *   - Findings are recorded on a maintenance that has started or finished,
 *     stay open until a later maintenance (or a note) resolves them, and
 *     never block completion — they are the reason for the next corrective.
 */
class MaintenanceScheduler
{
    /*
    |---------------------------------------------------------------------------
    | Readings
    |---------------------------------------------------------------------------
    */

    /**
     * Log a reading. Returns the reading and the open maintenances it has
     * just made due by meter, so the screen can say so.
     *
     * @return array{reading: EquipmentMeterReading, nowDue: Collection<int, EquipmentMaintenance>}
     *
     * @throws ValidationException when the reading would go backwards
     */
    public function logReading(Equipment $equipment, float $reading, string $readAt, ?string $notes = null, string $source = 'manual', ?EquipmentMaintenance $maintenance = null): array
    {
        if (! $equipment->hasMeter()) {
            throw ValidationException::withMessages(['reading' => __('This equipment has no meter.')]);
        }

        $this->assertMonotonic($equipment, $reading, $readAt);

        $dueBefore = $this->openDueByMeter($equipment)->pluck('id');

        $row = DB::transaction(function () use ($equipment, $reading, $readAt, $notes, $source, $maintenance) {
            $row = $equipment->readings()->create([
                'reading' => $reading,
                'read_at' => $readAt,
                'source' => $source,
                'equipment_maintenance_id' => $maintenance?->id,
                'notes' => $notes,
                'recorded_by' => Auth::id(),
            ]);

            // The newest reading by date is the equipment's current one.
            if ($equipment->current_meter_at === null || $equipment->current_meter_at->lte(Carbon::parse($readAt))) {
                $equipment->update(['current_meter' => $reading, 'current_meter_at' => $readAt]);
            }

            $equipment->recordHistory('reading_logged', [
                'reading' => $equipment->formatMeter($reading),
                'date' => $readAt,
            ], $row);

            return $row;
        });

        $equipment->refresh();

        $nowDue = $this->openDueByMeter($equipment)->reject(fn ($m) => $dueBefore->contains($m->id))->values();

        return ['reading' => $row, 'nowDue' => $nowDue];
    }

    /** The one rule on readings: never lower than the one before, never higher than the one after. */
    protected function assertMonotonic(Equipment $equipment, float $reading, string $readAt): void
    {
        $date = Carbon::parse($readAt)->toDateString();

        $before = $equipment->readings()->getQuery()
            ->whereDate('read_at', '<=', $date)
            ->reorder()->orderByDesc('read_at')->orderByDesc('id')
            ->first();

        if ($before && $reading < (float) $before->reading) {
            throw ValidationException::withMessages(['reading' => __('A reading cannot be lower than the last one: :reading on :date.', [
                'reading' => $equipment->formatMeter((float) $before->reading),
                'date' => $before->read_at->appDate(),
            ])]);
        }

        $after = $equipment->readings()->getQuery()
            ->whereDate('read_at', '>', $date)
            ->reorder()->orderBy('read_at')->orderBy('id')
            ->first();

        if ($after && $reading > (float) $after->reading) {
            throw ValidationException::withMessages(['reading' => __('A reading on that date cannot be higher than the one that followed it: :reading on :date.', [
                'reading' => $equipment->formatMeter((float) $after->reading),
                'date' => $after->read_at->appDate(),
            ])]);
        }
    }

    protected function openDueByMeter(Equipment $equipment): Collection
    {
        return $equipment->maintenances()->open()->whereNotNull('due_meter')->get()
            ->each(fn ($m) => $m->setRelation('equipment', $equipment))
            ->filter(fn (EquipmentMaintenance $m) => $m->isDueByMeter())
            ->values();
    }

    /*
    |---------------------------------------------------------------------------
    | Plans
    |---------------------------------------------------------------------------
    */

    public function addPlan(Equipment $equipment, array $data): EquipmentMaintenancePlan
    {
        return DB::transaction(function () use ($equipment, $data) {
            $plan = $equipment->plans()->create($data + ['is_active' => true, 'created_by' => Auth::id()]);

            $equipment->recordHistory('plan_added', ['plan' => $plan->title], $plan);

            $this->generateNext($plan, now(), $equipment->current_meter !== null ? (float) $equipment->current_meter : null);

            return $plan;
        });
    }

    /** Changing the interval re-plans the open occurrence from the last completion (or from now). */
    public function updatePlan(EquipmentMaintenancePlan $plan, array $data): void
    {
        DB::transaction(function () use ($plan, $data) {
            $plan->update($data);
            $plan->equipment->recordHistory('plan_changed', ['plan' => $plan->title], $plan);

            if ($plan->is_active) {
                $this->replan($plan);
            }
        });
    }

    public function deactivatePlan(EquipmentMaintenancePlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan->update(['is_active' => false]);

            $open = $plan->openOccurrence()->first();

            if ($open && $open->status === 'scheduled') {
                $open->update(['status' => 'cancelled', 'cancel_reason' => __('Plan deactivated')]);
            }

            $plan->equipment->recordHistory('plan_changed', ['plan' => $plan->title, 'active' => false], $plan);
        });
    }

    public function reactivatePlan(EquipmentMaintenancePlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan->update(['is_active' => true]);
            $plan->equipment->recordHistory('plan_changed', ['plan' => $plan->title, 'active' => true], $plan);
            $this->replan($plan);
        });
    }

    /**
     * Re-aim the open, not-yet-started occurrence at what the plan now says,
     * from the last completion point. The row is kept — expenses, readings
     * and history point at its id — and its reminder stamps are reset only
     * when its due point actually moved.
     */
    protected function replan(EquipmentMaintenancePlan $plan): void
    {
        $open = $plan->openOccurrence()->first();

        if ($open && $open->status === 'in_progress') {
            return; // work under way is not re-planned under somebody's feet
        }

        $equipment = $plan->equipment;
        $fromDate = Carbon::parse($plan->last_completed_date ?? now())->startOfDay();
        $fromMeter = $plan->last_completed_meter !== null
            ? (float) $plan->last_completed_meter
            : ($equipment->current_meter !== null ? (float) $equipment->current_meter : null);

        if (! $open) {
            $this->generateNext($plan, $fromDate, $fromMeter);

            return;
        }

        $scheduledDate = $plan->interval_days ? $fromDate->copy()->addDays($plan->interval_days)->toDateString() : null;
        $dueMeter = ($plan->interval_meter && $equipment->hasMeter() && $fromMeter !== null) ? round($fromMeter + $plan->interval_meter, 1) : null;

        $moved = ($open->scheduled_date?->toDateString() !== $scheduledDate) || ((float) $open->due_meter !== (float) $dueMeter && ! ($open->due_meter === null && $dueMeter === null));

        $open->update([
            'title' => $plan->title,
            'maintenance_type' => $plan->maintenance_type,
            'scheduled_date' => $scheduledDate,
            'due_meter' => $dueMeter,
        ] + ($moved ? ['notified_30_at' => null, 'notified_7_at' => null, 'notified_due_at' => null, 'notified_overdue_at' => null] : []));
    }

    /** The one open occurrence of a plan, from a date and a meter reading. */
    public function generateNext(EquipmentMaintenancePlan $plan, Carbon|string $fromDate, ?float $fromMeter): ?EquipmentMaintenance
    {
        if (! $plan->is_active) {
            return null;
        }

        if ($plan->openOccurrence()->exists()) {
            return $plan->openOccurrence()->first();
        }

        $equipment = $plan->equipment;
        $from = Carbon::parse($fromDate)->startOfDay();

        $scheduledDate = $plan->interval_days ? $from->copy()->addDays($plan->interval_days)->toDateString() : null;
        $dueMeter = ($plan->interval_meter && $equipment->hasMeter() && $fromMeter !== null)
            ? round($fromMeter + $plan->interval_meter, 1)
            : null;

        if ($scheduledDate === null && $dueMeter === null) {
            return null; // a meter-only plan on equipment with no reading yet: nothing to aim at
        }

        $maintenance = $equipment->maintenances()->create([
            'plan_id' => $plan->id,
            'maintenance_type' => $plan->maintenance_type,
            'title' => $plan->title,
            'status' => 'scheduled',
            'scheduled_date' => $scheduledDate,
            'due_meter' => $dueMeter,
            'created_by' => Auth::id(),
        ]);

        $equipment->recordHistory('maintenance_scheduled', ['title' => $maintenance->title, 'due' => $maintenance->dueLabel()], $maintenance);

        return $maintenance;
    }

    /*
    |---------------------------------------------------------------------------
    | Maintenances
    |---------------------------------------------------------------------------
    */

    /** A one-off service, planned by hand. */
    public function schedule(Equipment $equipment, array $data): EquipmentMaintenance
    {
        return DB::transaction(function () use ($equipment, $data) {
            $maintenance = $equipment->maintenances()->create($data + ['status' => 'scheduled', 'created_by' => Auth::id()]);

            $equipment->recordHistory('maintenance_scheduled', ['title' => $maintenance->title, 'due' => $maintenance->dueLabel()], $maintenance);

            return $maintenance;
        });
    }

    public function start(EquipmentMaintenance $maintenance): void
    {
        if ($maintenance->status !== 'scheduled') {
            throw ValidationException::withMessages(['maintenance' => __('Only a scheduled maintenance can be started.')]);
        }

        DB::transaction(function () use ($maintenance) {
            $maintenance->update(['status' => 'in_progress', 'started_at' => now()]);

            $equipment = $maintenance->equipment;

            if ($equipment->status === 'active') {
                $equipment->update(['status' => 'in_maintenance']);
            }

            $equipment->recordHistory('maintenance_started', ['title' => $maintenance->title], $maintenance);
        });
    }

    /**
     * Finish a maintenance: the date, the meter (logged as a reading), who did
     * it, the findings it resolved. A plan then gets its next occurrence from
     * this completion point.
     *
     * @param  array<int, int>  $resolvedFindingIds
     */
    public function complete(EquipmentMaintenance $maintenance, array $data, array $resolvedFindingIds = []): void
    {
        if (! $maintenance->isOpen()) {
            throw ValidationException::withMessages(['maintenance' => __('This maintenance is already closed.')]);
        }

        $equipment = $maintenance->equipment;

        DB::transaction(function () use ($maintenance, $equipment, $data, $resolvedFindingIds) {
            $meter = $data['meter_at_completion'] ?? null;

            if ($equipment->hasMeter() && $meter !== null && $meter !== '') {
                $this->logReading($equipment, (float) $meter, $data['completed_date'], __('At :title', ['title' => $maintenance->title]), 'maintenance', $maintenance);
            } else {
                $meter = null;
            }

            $maintenance->update([
                'status' => 'completed',
                'completed_date' => $data['completed_date'],
                'meter_at_completion' => $meter,
                'performed_by' => $data['performed_by'] ?? null,
                'supplier_id' => ($data['performed_by'] ?? null) === 'vendor' ? ($data['supplier_id'] ?? null) : null,
                'performed_by_user_id' => ($data['performed_by'] ?? null) === 'internal' ? ($data['performed_by_user_id'] ?? null) : null,
                'completion_notes' => $data['completion_notes'] ?? null,
                'completed_by' => Auth::id(),
            ]);

            // Findings ticked as fixed here — only this equipment's, only open ones.
            if ($resolvedFindingIds !== []) {
                $equipment->findings()->open()->whereIn('id', $resolvedFindingIds)->get()
                    ->each(fn (EquipmentFinding $f) => $this->resolveFinding($f, null, $maintenance));
            }

            if ($maintenance->plan_id && $maintenance->plan) {
                $plan = $maintenance->plan;
                $plan->update([
                    'last_completed_date' => $data['completed_date'],
                    'last_completed_meter' => $meter,
                ]);

                $this->generateNext($plan, $data['completed_date'], $meter !== null ? (float) $meter : ($equipment->fresh()->current_meter !== null ? (float) $equipment->fresh()->current_meter : null));
            }

            // Back in service once nothing else is under way.
            $equipment->refresh();
            if ($equipment->status === 'in_maintenance' && ! $equipment->maintenances()->where('status', 'in_progress')->exists()) {
                $equipment->update(['status' => 'active']);
            }

            $equipment->recordHistory('maintenance_completed', ['title' => $maintenance->title, 'date' => $data['completed_date']], $maintenance);
        });
    }

    /** A plan's occurrence cancelled is a skip: the next one is generated from the point this one was due. */
    public function cancel(EquipmentMaintenance $maintenance, ?string $reason = null): void
    {
        if (! $maintenance->isOpen()) {
            throw ValidationException::withMessages(['maintenance' => __('This maintenance is already closed.')]);
        }

        DB::transaction(function () use ($maintenance, $reason) {
            $maintenance->update(['status' => 'cancelled', 'cancel_reason' => $reason]);

            $equipment = $maintenance->equipment;

            if ($equipment->status === 'in_maintenance' && ! $equipment->maintenances()->where('status', 'in_progress')->exists()) {
                $equipment->update(['status' => 'active']);
            }

            $equipment->recordHistory('maintenance_cancelled', ['title' => $maintenance->title, 'reason' => $reason], $maintenance);

            if ($maintenance->plan_id && $maintenance->plan?->is_active) {
                $this->generateNext(
                    $maintenance->plan,
                    $maintenance->scheduled_date ?? now(),
                    $maintenance->due_meter !== null ? (float) $maintenance->due_meter : ($equipment->current_meter !== null ? (float) $equipment->current_meter : null),
                );
            }
        });
    }

    /*
    |---------------------------------------------------------------------------
    | Findings
    |---------------------------------------------------------------------------
    */

    public function recordFinding(EquipmentMaintenance $maintenance, string $description, string $severity, ?string $photoPath = null): EquipmentFinding
    {
        if ($maintenance->status === 'scheduled' || $maintenance->status === 'cancelled') {
            throw ValidationException::withMessages(['finding' => __('A finding is recorded on a maintenance that has started or been completed.')]);
        }

        return DB::transaction(function () use ($maintenance, $description, $severity, $photoPath) {
            $finding = $maintenance->findings()->create([
                'equipment_id' => $maintenance->equipment_id,
                'description' => $description,
                'severity' => $severity,
                'status' => 'open',
                'photo_path' => $photoPath,
                'reported_by' => Auth::id(),
            ]);

            $maintenance->equipment->recordHistory('finding_recorded', ['severity' => EquipmentFinding::severityLabel($severity), 'finding' => str($description)->limit(80)], $finding);

            return $finding;
        });
    }

    public function resolveFinding(EquipmentFinding $finding, ?string $notes = null, ?EquipmentMaintenance $in = null): void
    {
        if (! $finding->isOpen()) {
            return;
        }

        DB::transaction(function () use ($finding, $notes, $in) {
            $finding->update([
                'status' => 'resolved',
                'resolved_in_maintenance_id' => $in?->id,
                'resolved_at' => now(),
                'resolved_by' => Auth::id(),
                'resolution_notes' => $notes,
            ]);

            $finding->equipment->recordHistory('finding_resolved', ['finding' => str($finding->description)->limit(80)], $finding);
        });
    }
}
