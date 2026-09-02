<?php

namespace App\Models;

use App\Contracts\PermissionScope;
use App\Models\Concerns\HasFormattedPhone;
use App\Enums\JobSiteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class JobSite extends Model implements PermissionScope
{
    use HasFormattedPhone, HasFactory;

    protected $fillable = [
        'project_id',
        'job_site_name',
        'street',
        'address_2',
        'city',
        'state',
        'postal_code',
        'neighborhood',
        'country',
        'latitude',
        'longitude',
        'contact_person',
        'phone',
        'email',
        'job_amount',
        'status',
        'created_by',
        'supervisor_id',
    ];

    protected $casts = [
        'status' => JobSiteStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get/Set job_amount as dollars (stored as cents)
     */
    protected function jobAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the project that owns this job site
     */
    /**
     * Only the job sites this person may see: the ones they are on, and every
     * site of a project they are on — a project membership cascades down.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || ! $user->isActive()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || ! $user->isConfined()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query
                ->whereIn('id', Membership::query()
                    ->select('scopeable_id')
                    ->where('user_id', $user->id)
                    ->active()
                    ->where('scopeable_type', static::class))
                ->orWhereIn('project_id', Membership::query()
                    ->select('scopeable_id')
                    ->where('user_id', $user->id)
                    ->active()
                    ->where('scopeable_type', Project::class));
        });
    }

    /** A job site sits inside its project, whose members reach it too. */
    public function parentScope(): ?Model
    {
        return $this->relationLoaded('project') ? $this->project : Project::find($this->project_id);
    }

    public function scopeLevel(): string
    {
        return 'job_site';
    }

    public function scopeLabel(): string
    {
        return (string) $this->job_site_name;
    }

    /**
     * The people added to this job site specifically. A membership here
     * overrides the parent project's for this site — specific beats general.
     */
    public function memberships(): MorphMany
    {
        return $this->morphMany(Membership::class, 'scopeable');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created this job site
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the supervisor assigned to this job site
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Get the supervisor change history for this job site
     */
    public function supervisorHistories(): HasMany
    {
        return $this->hasMany(JobSiteSupervisorHistory::class)->orderByDesc('created_at');
    }

    /**
     * Record a supervisor change in the history
     */
    public function recordSupervisorChange(User $changedBy, ?int $oldSupervisorId, ?int $newSupervisorId, ?string $note = null): void
    {
        $this->supervisorHistories()->create([
            'old_supervisor_id' => $oldSupervisorId,
            'new_supervisor_id' => $newSupervisorId,
            'changed_by' => $changedBy->id,
            'note' => $note,
        ]);
    }

    /**
     * Get the change orders for this job site
     */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    /**
     * Base contract value, before change orders. Dollars.
     *
     * Named to match `Project::getContractValue()`: every screen that reports
     * on a location asks the same three questions of it, and four of them were
     * spelling the sum out by hand before this.
     */
    public function getContractValue(): float
    {
        return (float) $this->job_amount;
    }

    /**
     * Current contract value: the job amount plus the change orders the client
     * has agreed to. Signed.
     *
     * **Only an approved change order counts** — see
     * `Project::getAdjustedContractValue()` for why.
     */
    public function getAdjustedContractValue(): float
    {
        return round($this->getContractValue() + $this->getApprovedChangeOrdersTotal(), 2);
    }

    /**
     * The change orders that revise the contract value, in dollars. Signed.
     */
    public function getApprovedChangeOrdersTotal(): float
    {
        if ($this->relationLoaded('changeOrders')) {
            return round($this->changeOrders
                ->where('status', ChangeOrder::STATUS_APPROVED)
                ->sum(fn (ChangeOrder $co) => (int) $co->getRawOriginal('amount')) / 100, 2);
        }

        return round($this->changeOrders()->approved()->sum('amount') / 100, 2);
    }

    /**
     * Raised but not yet decided (draft or pending), in dollars. Signed.
     * Reported beside the contract value, never inside it.
     */
    public function getPendingChangeOrdersTotal(): float
    {
        if ($this->relationLoaded('changeOrders')) {
            return round($this->changeOrders
                ->whereIn('status', [ChangeOrder::STATUS_DRAFT, ChangeOrder::STATUS_PENDING])
                ->sum(fn (ChangeOrder $co) => (int) $co->getRawOriginal('amount')) / 100, 2);
        }

        return round($this->changeOrders()->pendingDecision()->sum('amount') / 100, 2);
    }

    /**
     * Get the expenses for this job site
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the income for this job site
     */
    public function income(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * Get the daily reports for this job site
     */
    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    /**
     * Get the budget for this job site (if exists).
     */
    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    /**
     * Get the contracts for this job site
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Repository documents filed under this job site
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Tasks raised for this job site
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Repository folders belonging to this job site
     */
    public function documentFolders(): HasMany
    {
        return $this->hasMany(DocumentFolder::class);
    }

    /**
     * Get the full address as a formatted string
     */
    public function getFullAddressAttribute(): string
    {
        if ($this->country === 'BR') {
            $addressParts = array_filter([
                $this->street,
                $this->address_2,
                $this->neighborhood,
                $this->city,
                $this->state,
                $this->postal_code,
            ]);
        } else {
            $addressParts = array_filter([
                $this->street,
                $this->address_2,
                $this->city,
                $this->state,
                $this->postal_code,
            ]);
        }

        return implode(', ', $addressParts);
    }
}
