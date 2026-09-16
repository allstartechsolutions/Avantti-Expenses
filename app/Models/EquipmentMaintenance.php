<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One service on a piece of equipment: scheduled by date and/or meter, then
 * started and completed. Due when today reaches the date OR the meter
 * reaches the reading — whichever comes first. Its cost is the sum of the
 * expenses tagged to it (the Costs phase), never a stored figure.
 */
class EquipmentMaintenance extends Model
{
    public const TYPES = ['preventive', 'corrective', 'inspection'];

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    public const DUE_SOON_DAYS = 30;

    protected $fillable = [
        'equipment_id', 'plan_id', 'maintenance_type', 'title', 'description', 'status',
        'scheduled_date', 'due_meter', 'started_at', 'completed_date', 'meter_at_completion',
        'performed_by', 'supplier_id', 'performed_by_user_id', 'completion_notes', 'cancel_reason',
        'notified_30_at', 'notified_7_at', 'notified_due_at', 'notified_overdue_at',
        'created_by', 'completed_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'due_meter' => 'decimal:1',
        'started_at' => 'datetime',
        'completed_date' => 'date',
        'meter_at_completion' => 'decimal:1',
        'notified_30_at' => 'datetime',
        'notified_7_at' => 'datetime',
        'notified_due_at' => 'datetime',
        'notified_overdue_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EquipmentMaintenancePlan::class, 'plan_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'supplier_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** Findings recorded during this maintenance. */
    public function findings(): HasMany
    {
        return $this->hasMany(EquipmentFinding::class)->latest();
    }

    /** Findings from earlier maintenances that this one resolved. */
    public function resolvedFindings(): HasMany
    {
        return $this->hasMany(EquipmentFinding::class, 'resolved_in_maintenance_id');
    }

    /*
    |---------------------------------------------------------------------------
    | Labels — feminine in pt_BR (a manutenção), hence their own keys
    |---------------------------------------------------------------------------
    */

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'preventive' => __('Maintenance type: preventive'),
            'corrective' => __('Maintenance type: corrective'),
            'inspection' => __('Maintenance type: inspection'),
            default => ucfirst((string) $type),
        };
    }

    public function getTypeLabel(): string
    {
        return static::typeLabel($this->maintenance_type);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'scheduled' => __('Maintenance status: scheduled'),
            'in_progress' => __('Maintenance status: in progress'),
            'completed' => __('Maintenance status: completed'),
            'cancelled' => __('Maintenance status: cancelled'),
            default => ucfirst((string) $status),
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public static function performerLabel(?string $performer): string
    {
        return match ($performer) {
            'internal' => __('Performed by: our own team'),
            'vendor' => __('Performed by: a vendor'),
            default => '—',
        };
    }

    /*
    |---------------------------------------------------------------------------
    | Due-ness — computed, never stored
    |---------------------------------------------------------------------------
    */

    public function isOpen(): bool
    {
        return in_array($this->status, ['scheduled', 'in_progress'], true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'in_progress']);
    }

    /** Due by date: today is on or past the scheduled date. */
    public function isDueByDate(?Carbon $today = null): bool
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return $this->scheduled_date !== null && $this->scheduled_date->lte($today);
    }

    /** Due by meter: the equipment's reading has reached the due reading. */
    public function isDueByMeter(): bool
    {
        $current = $this->equipment?->current_meter;

        return $this->due_meter !== null && $current !== null && (float) $current >= (float) $this->due_meter;
    }

    /** Due when either the date or the meter says so — whichever came first. */
    public function isDue(?Carbon $today = null): bool
    {
        return $this->isOpen() && ($this->isDueByDate($today) || $this->isDueByMeter());
    }

    /** Past its date (the meter has no "past": reached is reached). */
    public function isOverdue(?Carbon $today = null): bool
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return $this->isOpen() && $this->scheduled_date !== null && $this->scheduled_date->lt($today);
    }

    /** Days until the scheduled date; negative when past; null without a date. */
    public function daysUntilDue(?Carbon $today = null): ?int
    {
        if ($this->scheduled_date === null) {
            return null;
        }

        $today = ($today ?? now())->copy()->startOfDay();

        return (int) $today->diffInDays($this->scheduled_date, false);
    }

    /** Meter units still to run before it is due; negative when reached; null without a due reading. */
    public function meterRemaining(): ?float
    {
        $current = $this->equipment?->current_meter;

        if ($this->due_meter === null || $current === null) {
            return null;
        }

        return round((float) $this->due_meter - (float) $current, 1);
    }

    /** Within 30 days, or within the plan's meter lead. */
    public function isDueSoon(?Carbon $today = null): bool
    {
        if (! $this->isOpen() || $this->isDue($today)) {
            return false;
        }

        $days = $this->daysUntilDue($today);

        if ($days !== null && $days <= self::DUE_SOON_DAYS) {
            return true;
        }

        $remaining = $this->meterRemaining();
        $lead = $this->plan?->effectiveMeterLead() ?? ($this->due_meter ? (int) round((float) $this->due_meter / 10) : null);

        return $remaining !== null && $lead !== null && $remaining <= $lead;
    }

    /** scheduled | in_progress | due | due_soon | overdue — the word the badges use. */
    public function urgency(?Carbon $today = null): string
    {
        if (! $this->isOpen()) {
            return $this->status;
        }

        if ($this->status === 'in_progress') {
            return 'in_progress';
        }

        if ($this->isOverdue($today)) {
            return 'overdue';
        }

        if ($this->isDue($today)) {
            return 'due';
        }

        return $this->isDueSoon($today) ? 'due_soon' : 'scheduled';
    }

    public static function urgencyLabel(string $urgency): string
    {
        return match ($urgency) {
            'overdue' => __('Maintenance urgency: overdue'),
            'due' => __('Maintenance urgency: due'),
            'due_soon' => __('Maintenance urgency: due soon'),
            'in_progress' => __('Maintenance status: in progress'),
            'scheduled' => __('Maintenance status: scheduled'),
            default => static::statusLabel($urgency),
        };
    }

    /** "31 ago 2026", "10,000 km", or both joined by "or". */
    public function dueLabel(): string
    {
        $parts = [];

        if ($this->scheduled_date) {
            $parts[] = $this->scheduled_date->appDate();
        }

        if ($this->due_meter !== null && $this->equipment) {
            $parts[] = $this->equipment->formatMeter((float) $this->due_meter);
        }

        return $parts === [] ? __('No due date') : implode(' '.__('or').' ', $parts);
    }

    /** Expenses tagged to this maintenance. */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'equipment_maintenance_id');
    }

    /** The cost of this maintenance: the sum of the expenses tagged to it, never a stored figure. */
    public function costInCents(?User $user = null): int
    {
        return (int) $this->expenses()
            ->where('status', '!=', 'cancelled')
            ->visibleTo($user ?? auth()->user())
            ->sum('total_amount');
    }
}
