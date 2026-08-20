<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who was invited and who turned up. External people — clients, engineers,
 * vendors — have no user_id: they are a name, a company and an e-mail that
 * receives the published minute.
 */
class MeetingAttendee extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'name',
        'company',
        'email',
        'role',
        'attendance',
        'notes',
        'notified_at',
    ];

    protected $attributes = [
        'role' => 'participant',
        'attendance' => 'present',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExternal(): bool
    {
        return $this->user_id === null;
    }

    public function displayName(): string
    {
        return $this->user?->name ?? (string) $this->name;
    }

    public function displayEmail(): ?string
    {
        return $this->user?->email ?? $this->email;
    }

    public function getRoleLabel(): string
    {
        return match ($this->role) {
            'chair' => __('Chair'),
            'secretary' => __('Secretary'),
            'participant' => __('Participant'),
            default => ucfirst($this->role),
        };
    }

    public function getAttendanceLabel(): string
    {
        return match ($this->attendance) {
            'present' => __('Present'),
            'absent' => __('Absent'),
            'excused' => __('Excused'),
            default => ucfirst($this->attendance),
        };
    }

    public function getAttendanceColor(): string
    {
        return match ($this->attendance) {
            'present' => 'green',
            'absent' => 'red',
            'excused' => 'yellow',
            default => 'gray',
        };
    }
}
