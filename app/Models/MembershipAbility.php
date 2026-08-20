<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ability a person holds on one project or job site.
 */
class MembershipAbility extends Model
{
    protected $fillable = [
        'membership_id',
        'ability',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
