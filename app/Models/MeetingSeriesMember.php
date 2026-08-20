<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody a series normally invites. Copied onto every new meeting as its
 * attendance list, then corrected on the day.
 *
 * user_id is null for external participants — a client, an engineer, a vendor
 * with no login — who can be standing members too.
 */
class MeetingSeriesMember extends Model
{
    protected $fillable = [
        'meeting_series_id',
        'user_id',
        'name',
        'company',
        'email',
        'role',
    ];

    protected $attributes = [
        'role' => 'participant',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(MeetingSeries::class, 'meeting_series_id');
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
}
