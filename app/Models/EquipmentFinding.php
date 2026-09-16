<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something encountered during a maintenance — a worn belt, a cracked hose — open until resolved. */
class EquipmentFinding extends Model
{
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'equipment_id', 'equipment_maintenance_id', 'description', 'severity', 'status', 'photo_path',
        'resolved_in_maintenance_id', 'resolved_at', 'resolved_by', 'resolution_notes', 'reported_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(EquipmentMaintenance::class, 'equipment_maintenance_id');
    }

    public function resolvedIn(): BelongsTo
    {
        return $this->belongsTo(EquipmentMaintenance::class, 'resolved_in_maintenance_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Feminine in pt_BR (a ocorrência), hence their own keys. */
    public static function severityLabel(?string $severity): string
    {
        return match ($severity) {
            'low' => __('Finding severity: low'),
            'medium' => __('Finding severity: medium'),
            'high' => __('Finding severity: high'),
            'critical' => __('Finding severity: critical'),
            default => ucfirst((string) $severity),
        };
    }

    public function getSeverityLabel(): string
    {
        return static::severityLabel($this->severity);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'open' => __('Finding status: open'),
            'resolved' => __('Finding status: resolved'),
            default => ucfirst((string) $status),
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public function severityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'high' => 'amber',
            'medium' => 'blue',
            default => 'gray',
        };
    }
}
