<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ability granted to a company-wide role.
 */
class RoleAbility extends Model
{
    protected $fillable = [
        'role_id',
        'ability',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
