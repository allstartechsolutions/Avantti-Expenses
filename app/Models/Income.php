<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
}
