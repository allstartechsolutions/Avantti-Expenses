<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'project_name',
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
        'initial_amount',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get/Set initial_amount as dollars (stored as cents)
     */
    protected function initialAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the client that owns this project
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created this project
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the job sites for this project
     */
    public function jobSites(): HasMany
    {
        return $this->hasMany(JobSite::class);
    }

    /**
     * Get all expenses for this project (including job site expenses)
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get only project-level expenses (not tied to a job site)
     */
    public function projectLevelExpenses(): HasMany
    {
        return $this->hasMany(Expense::class)->whereNull('job_site_id');
    }

    /**
     * Get all change orders for this project (including job site change orders)
     */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    /**
     * Get only project-level change orders (not tied to a job site)
     */
    public function projectLevelChangeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class)->whereNull('job_site_id');
    }

    /**
     * Get all daily reports for this project (including job site daily reports)
     */
    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    /**
     * Get only project-level daily reports (not tied to a job site)
     */
    public function projectLevelDailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class)->whereNull('job_site_id');
    }

    /**
     * Get the project-level budget (if exists).
     */
    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class)->whereNull('job_site_id');
    }

    /**
     * Get all budgets for this project (including job site budgets).
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
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
