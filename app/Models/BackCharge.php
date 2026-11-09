<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Money a subcontractor company — or one of its employees — owes, taken out
 * of what they are paid, all at once or in parts (docs/back-charges.md).
 *
 * Remaining = amount − every deduction. A deduction on a pending batch line
 * already counts, so two batches cannot take the same dollars; a rejected
 * line's deductions are deleted, which releases them.
 */
class BackCharge extends Model
{
    protected $fillable = [
        'number',
        'subcontractor_id',
        'subcontractor_employee_id',
        'project_id',
        'job_site_id',
        'amount',
        'date',
        'reason',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public static function generateNumber(): string
    {
        // Numeric max, not string max: 'BC-9999' > 'BC-10000' lexically.
        $number = (int) static::query()
            ->selectRaw('MAX(CAST(SUBSTRING(number, 4) AS UNSIGNED)) AS max_number')
            ->value('max_number');

        return 'BC-'.str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function subcontractorEmployee(): BelongsTo
    {
        return $this->belongsTo(SubcontractorEmployee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(BackChargeDeduction::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A company record (permission levels: global). The origin lot is a
     * label, so without this the resolver would walk to it and answer for
     * that lot's members instead of the role.
     */
    public function permissionScope(): null
    {
        return null;
    }

    /** Number, reason, description, the company or the employee. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.trim($term).'%';

        return $query->where(fn (Builder $q) => $q->where('number', 'like', $like)
            ->orWhere('reason', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhereHas('subcontractor', fn (Builder $s) => $s->where('name', 'like', $like))
            ->orWhereHas('subcontractorEmployee', fn (Builder $e) => $e->where('name', 'like', $like)));
    }

    /** Nothing deducted yet: it may go from the list. */
    public function canBeDeleted(): bool
    {
        return $this->getDeductedCents() === 0;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Adds deducted_cents (every deduction, pending batch lines included) and
     * reserved_cents (the part only set aside on lines not approved yet).
     */
    public function scopeWithDeducted(Builder $query): Builder
    {
        return $query->withSum('deductions as deducted_cents', 'amount')
            ->withSum(['deductions as reserved_cents' => fn ($q) => $q->whereNull('contract_payment_id')], 'amount');
    }

    /** What is only set aside on batch lines that are not approved yet, in cents. */
    public function getReservedCents(): int
    {
        if (array_key_exists('reserved_cents', $this->attributes)) {
            return (int) $this->attributes['reserved_cents'];
        }

        if ($this->relationLoaded('deductions')) {
            return (int) $this->deductions->whereNull('contract_payment_id')->sum(fn ($d) => $d->getRawOriginal('amount'));
        }

        return (int) $this->deductions()->whereNull('contract_payment_id')->sum('amount');
    }

    /** Nothing left to deduct, but part of it waits on a batch line that is not approved yet. */
    public function isAwaitingApproval(): bool
    {
        return $this->isFullyDeducted() && $this->getReservedCents() > 0;
    }

    /** Back charges with something left to deduct. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereRaw(
            'back_charges.amount > (select coalesce(sum(d.amount), 0) from back_charge_deductions d where d.back_charge_id = back_charges.id)'
        );
    }

    /**
     * The back charges a payment to this contract may take from: the
     * subcontractor company's own, plus those against the employee the
     * contract names. Another employee's are never offered.
     */
    public function scopeDeductibleFrom(Builder $query, Contract $contract): Builder
    {
        return $query->where('subcontractor_id', $contract->subcontractor_id)
            ->where(fn (Builder $q) => $q->whereNull('subcontractor_employee_id')
                ->when($contract->subcontractor_employee_id, fn (Builder $q) => $q->orWhere('subcontractor_employee_id', $contract->subcontractor_employee_id)));
    }

    public function getDeductedCents(): int
    {
        if (array_key_exists('deducted_cents', $this->attributes)) {
            return (int) $this->attributes['deducted_cents'];
        }

        if ($this->relationLoaded('deductions')) {
            return (int) $this->deductions->sum(fn ($d) => $d->getRawOriginal('amount'));
        }

        return (int) $this->deductions()->sum('amount');
    }

    public function getDeducted(): float
    {
        return round($this->getDeductedCents() / 100, 2);
    }

    public function getRemainingCents(): int
    {
        return max(0, (int) $this->getRawOriginal('amount') - $this->getDeductedCents());
    }

    public function getRemaining(): float
    {
        return round($this->getRemainingCents() / 100, 2);
    }

    public function isFullyDeducted(): bool
    {
        return $this->getRemainingCents() === 0;
    }

    /** Lowered to nothing before anything was deducted: declined for good. */
    public function isWaived(): bool
    {
        return (int) $this->getRawOriginal('amount') === 0 && $this->getDeductedCents() === 0;
    }

    public function getStatusLabel(): string
    {
        return match (true) {
            $this->isWaived() => __('Waived'),
            $this->isAwaitingApproval() => __('Pending approval'),
            $this->isFullyDeducted() => __('Fully deducted'),
            default => __('Open'),
        };
    }

    public function getStatusColor(): string
    {
        return $this->isFullyDeducted() && ! $this->isAwaitingApproval() ? 'green' : 'amber';
    }

    /** Company name, or "Employee — Company" when it is against an employee. */
    public function getOwedByLabel(): string
    {
        $company = $this->subcontractor?->company_name ?? '—';

        return $this->subcontractorEmployee
            ? __(':employee — :company', ['employee' => $this->subcontractorEmployee->name, 'company' => $company])
            : $company;
    }
}
