<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One company-wide ability this person differs from their role on.
 *
 * `granted = true` means always allowed and `granted = false` means never
 * allowed, whatever the role says. **No row at all is the normal case** and
 * means "follow the role" — so an empty table is an install behaving exactly
 * as it did before per-person access existed.
 *
 * Project and job-site permissions are not held here: those are memberships,
 * which already model "this person, this scope, these abilities".
 */
class UserAbility extends Model
{
    protected $fillable = [
        'user_id',
        'ability',
        'granted',
    ];

    protected $casts = [
        'granted' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
