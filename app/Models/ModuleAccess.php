<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ModuleAccess extends Model
{
    protected $table = 'module_access';

    protected $fillable = [
        'module_key',
        'module_name',
        'description',
        'is_enabled',
        'is_core',
        'created_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_core' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ModuleAccessHistory::class);
    }

    public static function isEnabled(string $moduleKey): bool
    {
        return Cache::remember("module_access.{$moduleKey}", 300, function () use ($moduleKey) {
            $module = static::where('module_key', $moduleKey)->first();

            if (!$module) {
                return true;
            }

            if ($module->is_core) {
                return true;
            }

            return $module->is_enabled;
        });
    }

    public static function clearCache(string $moduleKey): void
    {
        Cache::forget("module_access.{$moduleKey}");
    }

    public static function logHistory(?int $moduleAccessId, string $action, ?string $field = null, ?string $oldValue = null, ?string $newValue = null): void
    {
        ModuleAccessHistory::create([
            'module_access_id' => $moduleAccessId,
            'action' => $action,
            'field_changed' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => Auth::id(),
        ]);
    }
}
