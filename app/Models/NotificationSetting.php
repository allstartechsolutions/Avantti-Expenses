<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Which task e-mails this install sends, and when.
 *
 * Read on nearly every task action, so the answers are cached for a few
 * minutes the way ModuleAccess does it. Saving clears the cache.
 */
class NotificationSetting extends Model
{
    public const TASK_CREATED = 'task_created';
    public const TASK_CLOSED = 'task_closed';
    public const TASK_OVERDUE = 'task_overdue';
    public const TASK_WEEKLY_DIGEST = 'task_weekly_digest';

    /** Every trigger, in the order the settings screen lists them. */
    public const KEYS = [self::TASK_CREATED, self::TASK_CLOSED, self::TASK_OVERDUE, self::TASK_WEEKLY_DIGEST];

    private const CACHE_KEY = 'notification_settings';

    protected $fillable = ['key', 'is_enabled', 'options', 'updated_by'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'options' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return \Illuminate\Support\Collection<string, self> */
    public static function all($columns = ['*'])
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => static::query()->get()->keyBy('key'));
    }

    /**
     * Is this install sending this one?
     *
     * An unknown key is treated as on: a trigger added in code should send
     * until somebody deliberately switches it off.
     */
    public static function enabled(string $key): bool
    {
        return (bool) (static::all()->get($key)?->is_enabled ?? true);
    }

    /** @return array<string, mixed> */
    public static function optionsFor(string $key): array
    {
        return (array) (static::all()->get($key)?->options ?? []);
    }

    /** Which weekday the digest goes out on: 1 = Monday. */
    public static function digestDay(): int
    {
        return (int) (static::optionsFor(self::TASK_WEEKLY_DIGEST)['day'] ?? 1);
    }

    public static function digestHour(): int
    {
        return (int) (static::optionsFor(self::TASK_WEEKLY_DIGEST)['hour'] ?? 7);
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::TASK_CREATED => __('Task created or assigned to you'),
            self::TASK_CLOSED => __('Task closed'),
            self::TASK_OVERDUE => __('Task went past due'),
            self::TASK_WEEKLY_DIGEST => __('Weekly open-tasks digest'),
            default => $key,
        };
    }

    public static function description(string $key): string
    {
        return match ($key) {
            self::TASK_CREATED => __('Goes to the owner and everyone assigned, never to the person who did it.'),
            self::TASK_CLOSED => __('Goes to the owner, the assignees, whoever raised it, and the chair of the meeting it came from.'),
            self::TASK_OVERDUE => __('Sent once, the morning after the due date passes — not every day.'),
            self::TASK_WEEKLY_DIGEST => __('One e-mail per person listing everything of theirs still open.'),
            default => '',
        };
    }
}
