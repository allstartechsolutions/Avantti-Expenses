<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Income extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'income_date',
        'due_date',
        'title',
        'description',
        'amount',
        'status',
        'created_by',
    ];

    protected $casts = [
        'income_date' => 'date',
        'due_date' => 'date',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($income) {
            $income->attachments->each->delete();
        });

        // The distribution can never describe more money than exists, and it
        // only makes sense on project-level income. Both rules live here so
        // no call site can route around them.
        static::updating(function (Income $income) {
            if (! $income->isDirty('amount') && ! $income->isDirty('job_site_id')) {
                return;
            }

            $distributed = (int) $income->distributions()->sum('amount');

            if ($distributed <= 0) {
                return;
            }

            if ($income->isDirty('job_site_id') && $income->getAttributes()['job_site_id'] !== null) {
                throw new \DomainException(__('This income is distributed across locations. Remove the distribution before assigning it to a single location.'));
            }

            if ((int) $income->getAttributes()['amount'] < $distributed) {
                throw new \DomainException(__('The amount cannot be lower than the :total already distributed across locations. Adjust the distribution first.', [
                    'total' => \Illuminate\Support\Number::currency($distributed / 100, config('app.currency'), config('app.locale')),
                ]));
            }
        });
    }

    /**
     * Get/Set amount as dollars (stored as cents)
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    // =========================================================================
    // STATUS — received money vs money still expected
    // =========================================================================

    public function isReceived(): bool
    {
        return $this->status !== 'expected';
    }

    public function isExpected(): bool
    {
        return $this->status === 'expected';
    }

    /**
     * Overdue is derived from the due date, never stored — the same rule
     * the payables side uses.
     */
    public function isOverdue(): bool
    {
        return $this->isExpected() && $this->due_date !== null && $this->due_date->lt(today());
    }

    /** The date this money counts on: when it arrived, or when it is due. */
    public function effectiveDate(): ?\Carbon\CarbonInterface
    {
        return $this->isExpected() ? ($this->due_date ?? $this->income_date) : $this->income_date;
    }

    public function getStatusLabel(): string
    {
        return match (true) {
            $this->isOverdue() => __('Overdue'),
            $this->isExpected() => __('Expected'),
            default => __('Received'),
        };
    }

    public function getStatusColor(): string
    {
        return match (true) {
            $this->isOverdue() => 'red',
            $this->isExpected() => 'amber',
            default => 'green',
        };
    }

    /**
     * Booking expected money as received: the receipt date becomes the
     * income date, which is what every report reads.
     */
    public function markReceived(?string $receivedOn = null): void
    {
        // The due date is kept: it records what was expected and when, and
        // effectiveDate() ignores it once the money is received. Clearing
        // it would destroy information on a single click, with no undo.
        $this->update([
            'status' => 'received',
            'income_date' => $receivedOn ?: today()->format('Y-m-d'),
        ]);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the job site this income belongs to (nullable for project-level income)
     */
    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The job-site shares of this income. Only project-level income has them.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(IncomeDistribution::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isProjectLevel(): bool
    {
        return $this->job_site_id === null;
    }

    // =========================================================================
    // DISTRIBUTION — project-level money shared across the project's job sites
    // =========================================================================

    /** How much of this income has been assigned to job sites. */
    public function distributedTotal(): float
    {
        $cents = $this->relationLoaded('distributions')
            ? $this->distributions->sum(fn ($d) => round($d->amount * 100))
            : $this->distributions()->sum('amount');

        return round($cents / 100, 2);
    }

    /** What is still project-level: the part no job site has claimed. */
    public function undistributedAmount(): float
    {
        return round($this->amount - $this->distributedTotal(), 2);
    }

    public function isDistributed(): bool
    {
        return $this->distributedTotal() > 0;
    }

    /**
     * Replace the whole distribution in one transaction.
     *
     * @param  array<int, float>  $shares  job_site_id => amount, zero/blank drops the row
     */
    public function syncDistributions(array $shares): void
    {
        $shares = collect($shares)
            ->map(fn ($amount) => round((float) $amount, 2))
            ->filter(fn ($amount) => $amount > 0);

        // Clearing is always allowed — it is how an income stops being split.
        // Only handing money to a job site needs the income to be
        // project-level, so the check sits after the empty case.
        if ($shares->isNotEmpty() && ! $this->isProjectLevel()) {
            throw new \DomainException(__('Only income received at the project level can be distributed.'));
        }

        $validJobSiteIds = $this->project->jobSites()->pluck('id');

        if ($shares->keys()->diff($validJobSiteIds)->isNotEmpty()) {
            throw new \DomainException(__('The selected location is invalid.'));
        }

        if (round($shares->sum(), 2) > $this->amount) {
            throw new \DomainException(__('The distribution cannot exceed the income amount.'));
        }

        DB::transaction(function () use ($shares) {
            $this->distributions()->whereNotIn('job_site_id', $shares->keys()->all() ?: [0])->delete();

            foreach ($shares as $jobSiteId => $amount) {
                $this->distributions()->updateOrCreate(
                    ['job_site_id' => $jobSiteId],
                    ['amount' => $amount],
                );
            }
        });

        $this->unsetRelation('distributions');
    }
}
