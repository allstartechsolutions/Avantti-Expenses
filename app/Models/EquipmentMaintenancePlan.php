<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A recurring service — every N days and/or every N km or hours, whichever
 * comes first. Exactly one open maintenance row per active plan is the next
 * occurrence; App\Services\MaintenanceScheduler generates it.
 */
class EquipmentMaintenancePlan extends Model
{
    public const TYPES = ['preventive', 'inspection'];

    protected $fillable = [
        'equipment_id', 'title', 'maintenance_type', 'interval_days', 'interval_meter', 'meter_lead',
        'is_active', 'last_completed_date', 'last_completed_meter', 'notes', 'created_by',
    ];

    protected $casts = [
        'interval_days' => 'integer',
        'interval_meter' => 'integer',
        'meter_lead' => 'integer',
        'is_active' => 'boolean',
        'last_completed_date' => 'date',
        'last_completed_meter' => 'decimal:1',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class, 'plan_id');
    }

    /** The next occurrence — the one open row. */
    public function openOccurrence(): HasOne
    {
        return $this->hasOne(EquipmentMaintenance::class, 'plan_id')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The "due soon" margin on the meter: what was set, else a tenth of the interval. */
    public function effectiveMeterLead(): ?int
    {
        if (! $this->interval_meter) {
            return null;
        }

        return $this->meter_lead ?? (int) round($this->interval_meter / 10);
    }

    /**
     * "Every 180 days", "Every 10,000 km", or both. Takes the equipment when
     * the caller already holds it, so a list of plans is not one query per
     * row for the meter unit.
     */
    public function intervalLabel(?Equipment $equipment = null): string
    {
        $equipment ??= $this->equipment;

        $parts = [];

        if ($this->interval_days) {
            $parts[] = trans_choice('every :count day|every :count days', $this->interval_days, ['count' => $this->interval_days]);
        }

        if ($this->interval_meter) {
            $parts[] = __('every :count :unit', [
                'count' => number_format($this->interval_meter),
                'unit' => $equipment?->meterUnit() ?? '',
            ]);
        }

        return $parts === [] ? __('No interval') : ucfirst(implode(' '.__('or').' ', $parts));
    }
}
