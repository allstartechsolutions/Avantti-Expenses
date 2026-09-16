<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One odometer or hour-meter reading. Never lower than the one before it on the same day or earlier. */
class EquipmentMeterReading extends Model
{
    protected $fillable = ['equipment_id', 'reading', 'read_at', 'source', 'equipment_maintenance_id', 'notes', 'recorded_by'];

    protected $casts = [
        'reading' => 'decimal:1',
        'read_at' => 'date',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(EquipmentMaintenance::class, 'equipment_maintenance_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            'manual' => __('Reading source: manual'),
            'maintenance' => __('Reading source: maintenance'),
            default => ucfirst((string) $source),
        };
    }

    public function getSourceLabel(): string
    {
        return static::sourceLabel($this->source);
    }
}
