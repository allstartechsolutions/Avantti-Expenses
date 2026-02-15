<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Contract extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'subcontractor_id',
        'contract_number',
        'status',
        'start_date',
        'end_date',
        'amount',
        'notes',
        'contract_file_path',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public static function generateContractNumber(): string
    {
        $last = static::max('contract_number');

        if (!$last) {
            return 'CTR-0001';
        }

        $number = (int) str_replace('CTR-', '', $last);

        return 'CTR-' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    public function recordStatusChange($user, ?string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user->id,
            'reason' => $reason,
        ]);
    }

    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ContractStatusHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractPayment::class)->orderByDesc('payment_date');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(ContractPayment::class)->latestOfMany('payment_date');
    }

    public function getAmountPaid(): float
    {
        return round($this->payments()->sum('amount') / 100, 2);
    }

    public function getBalanceDue(): float
    {
        return round($this->amount - $this->getAmountPaid(), 2);
    }

    public function updateStatusFromPayments(): void
    {
        if (!in_array($this->status, ['completed', 'partially_paid', 'paid'])) {
            return;
        }

        $amountPaid = $this->getAmountPaid();
        $contractAmount = $this->amount;

        if ($amountPaid >= $contractAmount) {
            $newStatus = 'paid';
        } elseif ($amountPaid > 0) {
            $newStatus = 'partially_paid';
        } else {
            $newStatus = 'completed';
        }

        if ($newStatus !== $this->status) {
            $oldStatus = $this->status;
            $this->update(['status' => $newStatus]);
            $this->recordStatusChange(
                Auth::user(),
                $oldStatus,
                $newStatus,
                'Auto-updated from payment activity'
            );
        }
    }
}
