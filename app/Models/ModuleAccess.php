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

    /**
     * Answers already given during this request, keyed by module.
     *
     * The store behind `Cache` is the database, so every `remember()` is a
     * round trip of its own — and this is asked once per permission decision.
     * One meeting screen asked it 54 times for 9 modules. The shared cache
     * still carries the answer between requests; this only stops the same
     * request asking twice.
     *
     * Static, so it must be emptied when the application is built rather than
     * left to the end of the process — `AppServiceProvider::register()` does
     * it. One process serves one request, but it serves *every* test, and a
     * test that switches a module off would otherwise be believed by the tests
     * that follow it.
     */
    protected static array $enabled = [];

    /** Start of an application: nothing has been asked yet. */
    public static function flushEnabled(): void
    {
        static::$enabled = [];
    }

    /**
     * A row that moves takes its own answer down with it.
     *
     * `clearCache()` is still the call the settings screen makes, but hanging
     * this off the model as well means no future call site can forget: a
     * module switched off is switched off from the very next question, in this
     * request and every other.
     */
    protected static function booted(): void
    {
        $forget = fn (self $module) => static::clearCache($module->module_key);

        static::saved($forget);
        static::deleted($forget);
    }

    public static function isEnabled(string $moduleKey): bool
    {
        return static::$enabled[$moduleKey] ??= Cache::remember("module_access.{$moduleKey}", 300, function () use ($moduleKey) {
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
        // Both, and in this order: a module switched off re-renders the screen
        // inside the same request, and the memo would otherwise keep saying
        // the module is still on until the next click.
        unset(static::$enabled[$moduleKey]);

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
