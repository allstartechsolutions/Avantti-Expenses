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
     * Every module's answer, loaded once per request and kept for it.
     *
     * The store behind `Cache` is the database, so each cache read is a round
     * trip of its own. This used to be one cache entry per module, asked once
     * per permission decision: the sidebar alone put nine `cache` selects on
     * every page, and Sentry flagged it as an N+1 on the job-site contracts
     * screen (AVANTTI-CONSTRUCTION-3). Now the whole table is one entry —
     * `module_key => enabled` — read once and memoised here.
     *
     * Static, so it must be emptied when the application is built rather than
     * left to the end of the process — `AppServiceProvider::register()` does
     * it. One process serves one request, but it serves *every* test, and a
     * test that switches a module off would otherwise be believed by the tests
     * that follow it.
     *
     * @var array<string, bool>|null
     */
    protected static ?array $enabled = null;

    protected const CACHE_KEY = 'module_access.enabled';

    /** Start of an application: nothing has been asked yet. */
    public static function flushEnabled(): void
    {
        static::$enabled = null;
    }

    /**
     * A row that moves takes the whole answer down with it.
     *
     * `clearCache()` is still the call the settings screen makes, but hanging
     * this off the model as well means no future call site can forget: a
     * module switched off is switched off from the very next question, in this
     * request and every other.
     */
    protected static function booted(): void
    {
        $forget = fn () => static::clearCache();

        static::saved($forget);
        static::deleted($forget);
    }

    public static function isEnabled(string $moduleKey): bool
    {
        static::$enabled ??= Cache::remember(self::CACHE_KEY, 300, fn () => static::query()
            ->get(['module_key', 'is_enabled', 'is_core'])
            ->mapWithKeys(fn (self $module) => [
                $module->module_key => $module->is_core || $module->is_enabled,
            ])
            ->all());

        // A module with no row has never been switched off.
        return static::$enabled[$moduleKey] ?? true;
    }

    /**
     * The map is one entry for every module, so any change empties all of it.
     * The key is accepted for the callers that still pass one.
     */
    public static function clearCache(?string $moduleKey = null): void
    {
        // Both, and in this order: a module switched off re-renders the screen
        // inside the same request, and the memo would otherwise keep saying
        // the module is still on until the next click.
        static::$enabled = null;

        Cache::forget(self::CACHE_KEY);
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
