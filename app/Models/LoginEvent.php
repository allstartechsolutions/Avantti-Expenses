<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in, failed attempt, logout, lockout or two-factor step
 * (docs/login-activity.md). An audit record: written by RecordLoginActivity,
 * never edited, never deleted by hand — only pruned after the retention window.
 */
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    public const LOGIN = 'login';

    public const FAILED = 'failed';

    public const LOGOUT = 'logout';

    public const LOCKOUT = 'lockout';

    public const TWO_FACTOR_CHALLENGED = 'two_factor_challenged';

    public const TWO_FACTOR_PASSED = 'two_factor_passed';

    public const TWO_FACTOR_FAILED = 'two_factor_failed';

    /** An administrator ended this person's session (actor_id says who). */
    public const SESSION_ENDED = 'session_ended';

    public const EVENTS = [
        self::LOGIN, self::FAILED, self::LOGOUT, self::LOCKOUT,
        self::TWO_FACTOR_CHALLENGED, self::TWO_FACTOR_PASSED, self::TWO_FACTOR_FAILED, self::SESSION_ENDED,
    ];

    /** Events that are something going wrong, for filters and colours. */
    public const PROBLEMS = [self::FAILED, self::LOCKOUT, self::TWO_FACTOR_FAILED];

    /** How long events are kept. */
    public const RETENTION_DAYS = 90;

    protected $fillable = [
        'user_id', 'actor_id', 'email', 'event', 'ip_address', 'user_agent', 'browser', 'platform', 'device',
        'fingerprint', 'via_remember', 'is_new_device', 'created_at',
    ];

    protected $casts = [
        'via_remember' => 'boolean',
        'is_new_device' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The administrator who ended the session, on a SESSION_ENDED event. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeProblems(Builder $query): Builder
    {
        return $query->whereIn('event', self::PROBLEMS);
    }

    public static function eventLabel(?string $event): string
    {
        return match ($event) {
            self::LOGIN => __('Signed in'),
            self::FAILED => __('Failed sign-in'),
            self::LOGOUT => __('Signed out'),
            self::LOCKOUT => __('Locked out'),
            self::TWO_FACTOR_CHALLENGED => __('Two-factor code asked'),
            self::TWO_FACTOR_PASSED => __('Two-factor passed'),
            self::TWO_FACTOR_FAILED => __('Two-factor failed'),
            self::SESSION_ENDED => __('Signed out by an administrator'),
            default => (string) $event,
        };
    }

    public function getEventLabel(): string
    {
        return self::eventLabel($this->event);
    }

    /** green / red / amber / slate, for the chip. */
    public function getEventColor(): string
    {
        return match ($this->event) {
            self::LOGIN, self::TWO_FACTOR_PASSED => 'green',
            self::FAILED, self::LOCKOUT, self::TWO_FACTOR_FAILED => 'red',
            self::TWO_FACTOR_CHALLENGED => 'amber',
            default => 'slate',
        };
    }

    /** "Chrome on Windows", or the raw agent's start when it could not be read. */
    public function getDeviceLabel(): string
    {
        if ($this->browser || $this->platform) {
            return __(':browser on :platform', [
                'browser' => $this->browser ?: __('Unknown browser'),
                'platform' => $this->platform ?: __('unknown system'),
            ]);
        }

        return $this->user_agent ? str($this->user_agent)->limit(60)->toString() : __('Unknown device');
    }
}
