<?php

namespace App\Services;

use App\Mail\EquipmentMaintenanceDueMail;
use App\Models\EquipmentMaintenance;
use App\Models\NotificationLogEntry;
use App\Models\NotificationSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Warning people that a piece of equipment is due for service.
 *
 * Four fixed stages on every open, not-yet-started maintenance: 30 and 7
 * days before its date, the day it becomes due — by date, or by the meter
 * reaching the due reading — and the day after the date passes. Each is
 * stamped on the maintenance once it has gone out, so a stage never
 * repeats; completing or cancelling the maintenance ends its sequence, and
 * retired or sold equipment takes no part.
 *
 * A meter-only maintenance reaches the 7-day stage through its plan's
 * "due soon" margin and the due stage the day a reading reaches the due
 * reading; it has no overdue stage, since a meter reached is reached.
 *
 * One e-mail per recipient per morning, grouped by equipment — the same
 * three checks as every notifier: the install has the trigger on, the person
 * has not opted out, and today's mail has not already gone to them.
 *
 * Who receives it is a setting: the people picked on the Notification
 * Settings screen, or, when nobody is picked, everyone who may maintain
 * equipment.
 */
class EquipmentMaintenanceNotifier
{
    /** Days before the scheduled date → the column that records the stage went out. */
    public const STAGES = [
        30 => 'notified_30_at',
        7 => 'notified_7_at',
    ];

    public const DUE_STAGE = 'notified_due_at';

    public const OVERDUE_STAGE = 'notified_overdue_at';

    /** Deliveries that raised an exception this run — as opposed to people who simply did not want the mail. */
    protected int $failures = 0;

    /** Recipients who had already received today's digest — a second run the same day, which must not stamp. */
    protected int $deferred = 0;

    public function __construct(protected BuyerDirectory $directory) {}

    /**
     * @return array{maintenances:int, recipients:int, sent:int}
     */
    public function sendDueReminders(): array
    {
        $result = ['maintenances' => 0, 'recipients' => 0, 'sent' => 0];
        $this->failures = 0;
        $this->deferred = 0;

        if (! NotificationSetting::enabled(NotificationSetting::EQUIPMENT_MAINTENANCE_DUE)) {
            return $result;
        }

        $today = Carbon::today();

        // Every scheduled maintenance on equipment in service with any stage
        // still unstamped. Which stages it is inside of is decided in PHP —
        // the meter side cannot be asked of the database — and "on or
        // before", never "exactly", so a missed morning is caught the next.
        $candidates = EquipmentMaintenance::query()
            ->where('status', 'scheduled')
            ->whereHas('equipment', fn ($q) => $q->inService())
            ->where(function ($q) {
                foreach (array_merge(array_values(self::STAGES), [self::DUE_STAGE, self::OVERDUE_STAGE]) as $column) {
                    $q->orWhereNull($column);
                }
            })
            ->with(['equipment.project:id,project_name', 'equipment.jobSite:id,job_site_name', 'plan'])
            ->orderBy('scheduled_date')
            ->get();

        $upcoming = collect();
        $due = collect();
        $overdue = collect();
        $stampsToWrite = [];   // maintenance id => [column, ...]

        foreach ($candidates as $maintenance) {
            $stages = $this->stagesReached($maintenance, $today);

            if ($stages === []) {
                continue;
            }

            $stampsToWrite[$maintenance->id] = array_values(array_map(
                fn ($stage) => is_int($stage) ? self::STAGES[$stage] : $stage,
                $stages,
            ));

            // Listed once, under the most urgent stage it reached.
            if (in_array(self::OVERDUE_STAGE, $stages, true)) {
                $overdue->push($maintenance);
            } elseif (in_array(self::DUE_STAGE, $stages, true)) {
                $due->push($maintenance);
            } else {
                $upcoming->push(['maintenance' => $maintenance, 'stage' => min(array_filter($stages, 'is_int'))]);
            }
        }

        if ($upcoming->isEmpty() && $due->isEmpty() && $overdue->isEmpty()) {
            return $result;
        }

        $result['maintenances'] = $upcoming->count() + $due->count() + $overdue->count();

        $recipients = $this->recipients();
        $result['recipients'] = $recipients->count();

        foreach ($recipients as $user) {
            if ($this->send($user, new EquipmentMaintenanceDueMail($user, $upcoming, $due, $overdue), 'digest:'.$today->toDateString())) {
                $result['sent']++;
            }
        }

        // Stamped when the stage went out, and also when there was nobody to
        // send it to; not stamped when every delivery failed (an SMTP outage —
        // tomorrow's run tries again) nor when everybody had today's digest
        // already (a second run the same day — tomorrow's run says it).
        $nothingReachedAnybody = $result['sent'] === 0 && ($this->failures > 0 || $this->deferred > 0);

        if (! $nothingReachedAnybody) {
            $now = now();

            foreach ($stampsToWrite as $id => $columns) {
                EquipmentMaintenance::whereKey($id)->update(array_fill_keys($columns, $now));
            }
        }

        return $result;
    }

    /**
     * Every stage this maintenance is inside of today and has not been told
     * about: the day thresholds as integers, the due and overdue stages as
     * their column names.
     *
     * @return array<int, int|string>
     */
    public function stagesReached(EquipmentMaintenance $maintenance, Carbon $today): array
    {
        $stages = [];
        $days = $maintenance->daysUntilDue($today);
        $byMeter = $maintenance->isDueByMeter();

        // Overdue: the date has passed. (A meter reached has no "past".)
        if ($days !== null && $days < 0 && $maintenance->{self::OVERDUE_STAGE} === null) {
            $stages[] = self::OVERDUE_STAGE;
        }

        // Due: today is the date, or the meter has reached the due reading.
        if ((($days !== null && $days <= 0) || $byMeter) && $maintenance->{self::DUE_STAGE} === null) {
            $stages[] = self::DUE_STAGE;
        }

        // Ahead of it: the day thresholds, and for the meter, the plan's margin
        // stands in for the 7-day warning.
        foreach (self::STAGES as $threshold => $column) {
            if ($maintenance->{$column} !== null) {
                continue;
            }

            $withinDays = $days !== null && $days <= $threshold;

            // The meter side stands in for the 7-day warning on its own terms:
            // within the plan's margin (a tenth of the due reading without one).
            $remaining = $maintenance->meterRemaining();
            $lead = $maintenance->plan?->effectiveMeterLead() ?? ($maintenance->due_meter ? (int) round((float) $maintenance->due_meter / 10) : null);
            $withinMeter = $threshold === 7 && $remaining !== null && $lead !== null && $remaining <= $lead;

            if ($withinDays || $withinMeter) {
                $stages[] = $threshold;
            }
        }

        return $stages;
    }

    /**
     * Who is told: the people picked in System Settings, or everyone who may
     * maintain equipment when nobody is picked.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        $ids = NotificationSetting::equipmentMaintenanceRecipientIds();

        if ($ids !== []) {
            return $this->directory->activeStaff()
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->get();
        }

        return $this->directory->holdersOf('equipment.maintain', null);
    }

    /** One mail to one person, if they should get it and have not already had today's. */
    protected function send(User $user, EquipmentMaintenanceDueMail $mail, string $window): bool
    {
        $key = NotificationSetting::EQUIPMENT_MAINTENANCE_DUE;

        if (! $user->email || ! $user->isActive() || ! $user->wantsNotification($key)) {
            return false;
        }

        $already = NotificationLogEntry::query()
            ->where('user_id', $user->id)
            ->where('type', $key)
            ->whereJsonContains('meta->window', $window)
            ->whereNotNull('sent_at')
            ->exists();

        if ($already) {
            $this->deferred++;

            return false;
        }

        $record = NotificationLogEntry::create([
            'user_id' => $user->id,
            'type' => $key,
            'email' => $user->email,
            'meta' => ['window' => $window],
        ]);

        try {
            Mail::to($user->email)->send($mail);

            $record->update(['sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            $this->failures++;

            $record->update(['error' => substr($e->getMessage(), 0, 500)]);

            Log::warning('Equipment maintenance reminder could not be sent', [
                'user' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
