<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'status',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
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

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_manager_id');
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
