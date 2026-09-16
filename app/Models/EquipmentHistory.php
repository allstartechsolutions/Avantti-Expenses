<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The audit trail of a piece of equipment, the shape of ExpenseChangeHistory. */
class EquipmentHistory extends Model
{
    protected $fillable = ['equipment_id', 'subject_type', 'subject_id', 'action', 'changed_by', 'changes'];

    protected $casts = [
        'changes' => 'array',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'created' => __('Equipment history: created'),
            'edited' => __('Equipment history: edited'),
            'status_changed' => __('Equipment history: status changed'),
            'assigned' => __('Equipment history: assigned'),
            'unassigned' => __('Equipment history: unassigned'),
            'reading_logged' => __('Equipment history: reading logged'),
            'maintenance_scheduled' => __('Equipment history: maintenance scheduled'),
            'maintenance_started' => __('Equipment history: maintenance started'),
            'maintenance_completed' => __('Equipment history: maintenance completed'),
            'maintenance_cancelled' => __('Equipment history: maintenance cancelled'),
            'plan_added' => __('Equipment history: plan added'),
            'plan_changed' => __('Equipment history: plan changed'),
            'finding_recorded' => __('Equipment history: finding recorded'),
            'finding_resolved' => __('Equipment history: finding resolved'),
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            'created', 'maintenance_completed', 'finding_resolved' => 'green',
            'maintenance_scheduled', 'plan_added', 'reading_logged' => 'blue',
            'maintenance_started', 'assigned', 'unassigned', 'status_changed' => 'yellow',
            'maintenance_cancelled', 'finding_recorded' => 'red',
            default => 'gray',
        };
    }
}
