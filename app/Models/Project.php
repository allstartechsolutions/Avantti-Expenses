<?php

namespace App\Models;

use App\Models\Concerns\HasFormattedPhone;
use App\Enums\ProjectAmountSource;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFormattedPhone, HasFactory;

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
        'amount_source',
        'description',
        'status',
        'created_by',
        'project_manager_id',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'amount_source' => ProjectAmountSource::class,
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
     * Base contract value (before change orders). Resolves manual vs
     * from-jobsites mode. Returns dollars.
     */
    public function getContractValue(): float
    {
        if ($this->amount_source === ProjectAmountSource::FROM_JOBSITES) {
            return round($this->jobSites()->sum('job_amount') / 100, 2);
        }

        return (float) $this->initial_amount;
    }

    /**
     * Current contract value including all change orders (both project-level
     * and jobsite-level, signed). Returns dollars.
     */
    public function getAdjustedContractValue(): float
    {
        $changeOrdersTotal = round($this->changeOrders()->sum('amount') / 100, 2);

        return round($this->getContractValue() + $changeOrdersTotal, 2);
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
     * Get the project manager
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
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
     * Get all income for this project (including job site income)
     */
    public function income(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * Get only project-level income (not tied to a job site)
     */
    public function projectLevelIncome(): HasMany
    {
        return $this->hasMany(Income::class)->whereNull('job_site_id');
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
     * Get all contracts for this project (including job site contracts)
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get only project-level contracts (not tied to a job site)
     */
    public function projectLevelContracts(): HasMany
    {
        return $this->hasMany(Contract::class)->whereNull('job_site_id');
    }

    /**
     * Every document in the repository for this project, job site ones included
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Only project-level documents (not filed under a job site)
     */
    public function projectLevelDocuments(): HasMany
    {
        return $this->hasMany(Document::class)->whereNull('job_site_id');
    }

    /**
     * Every repository folder for this project, job site ones included
     */
    public function documentFolders(): HasMany
    {
        return $this->hasMany(DocumentFolder::class);
    }

    /**
     * Only project-level folders (not tied to a job site)
     */
    public function projectLevelDocumentFolders(): HasMany
    {
        return $this->hasMany(DocumentFolder::class)->whereNull('job_site_id');
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
