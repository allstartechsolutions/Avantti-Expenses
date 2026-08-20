<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccessScope;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use HasFormattedPhone;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role_id',
        'notification_preferences',
        'status',
        'access_scope',
        'is_guest',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_preferences' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'access_scope' => AccessScope::class,
            'is_guest' => 'boolean',
        ];
    }

    /**
     * Get the user's role
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Whether the user holds the admin role.
     * Exposed as $user->is_admin (used across the UI and admin guards).
     */
    protected function isAdmin(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role?->name === 'admin',
        );
    }

    /**
     * Whether the user holds the manager role.
     * Exposed as $user->is_manager.
     */
    protected function isManager(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->role?->name === 'manager',
        );
    }

    /**
     * Who may approve or reject a purchase requisition: admins and managers.
     */
    public function canReviewRequisitions(): bool
    {
        return $this->is_admin || $this->is_manager;
    }

    /**
     * Who may add, rename, move, tag or share a repository document, and who
     * may create folders: admins and managers.
     */
    public function canManageDocuments(): bool
    {
        return $this->is_admin || $this->is_manager;
    }

    /**
     * Who may delete a repository document or folder, restore one, or purge
     * the trash: admins only.
     */
    public function canDeleteDocuments(): bool
    {
        return $this->is_admin;
    }

    /**
     * Documents flagged internal are hidden from ordinary employees.
     */
    public function canSeeInternalDocuments(): bool
    {
        return $this->is_admin || $this->is_manager;
    }

    /**
     * Every project and job site this user has been added to, with their
     * ability list for it.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** Only the memberships that actually grant something today. */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()->active();
    }

    /**
     * How much of the system this person can reach, resolved.
     *
     * A guest is always confined. Otherwise their own column wins if it is
     * set, and null — the normal case — means "whatever the role says". That
     * is what makes "this role only sees the projects it is added to" a single
     * setting rather than a chore repeated for every employee.
     */
    public function effectiveAccessScope(): AccessScope
    {
        if ($this->is_guest) {
            return AccessScope::ASSIGNED;
        }

        return $this->access_scope
            ?? $this->role?->access_scope
            ?? AccessScope::COMPANY;
    }

    /** True when this person's scope comes from their role rather than themselves. */
    public function followsRoleScope(): bool
    {
        return ! $this->is_guest && $this->access_scope === null;
    }

    /**
     * Whether this user is confined to what they have been assigned.
     */
    public function isConfined(): bool
    {
        return $this->effectiveAccessScope() === AccessScope::ASSIGNED;
    }

    /** The opposite of isConfined(): sees every project, as everybody does today. */
    public function isCompanyWide(): bool
    {
        return ! $this->isConfined();
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    /**
     * Has this person switched off one of the task e-mails?
     *
     * Null preferences mean "send me what everyone gets" — nobody needs a row
     * written to receive the ordinary mail.
     */
    public function wantsNotification(string $key): bool
    {
        return (bool) (($this->notification_preferences[$key] ?? true));
    }

    /**
     * Tasks this user owns — the only person who may declare them ready
     */
    public function ownedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'owner_id');
    }

    /**
     * Tasks this user works on without owning them
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
