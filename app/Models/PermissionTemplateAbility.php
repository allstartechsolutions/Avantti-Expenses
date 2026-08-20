<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ability handed out by a permission template.
 */
class PermissionTemplateAbility extends Model
{
    protected $fillable = [
        'permission_template_id',
        'ability',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PermissionTemplate::class, 'permission_template_id');
    }
}
