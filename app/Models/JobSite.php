<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
use App\Enums\JobSiteStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobSite extends Model
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
