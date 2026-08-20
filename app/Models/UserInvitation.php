<?php

namespace App\Models;

use App\Enums\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation for somebody with no login yet — staff or an outside guest.
 *
 * Only the hash of the token is stored, so a copy of the table cannot be turned
 * back into a working link. The plain token exists once, in the e-mail.
 */
class UserInvitation extends Model
{
    protected $fillable = [
        'email',
        'name',
        'role_id',
        'access_scope',
        'is_guest',
        'payload',
        'token_hash',
        'expires_at',
        'invited_by',
        'last_sent_at',
        'send_count',
        'accepted_at',
        'accepted_user_id',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'payload' => 'array',
        'access_scope' => AccessScope::class,
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', static::hashToken($token))->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && ! $this->isExpired();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
