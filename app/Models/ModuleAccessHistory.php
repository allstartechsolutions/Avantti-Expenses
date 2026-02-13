<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleAccessHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'module_access_id',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'changed_by',
    ];

    public function moduleAccess(): BelongsTo
    {
        return $this->belongsTo(ModuleAccess::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
