<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estimate extends Model
{
    protected $fillable = [
        'client_id',
        'project_id',
        'job_site_id',
        'estimate_number',
        'estimate_date',
        'terms',
        'due_date',
        'status',
        'message_title',
        'message_body',
        'discount_type',
        'discount_value',
        'discount_amount',
        'subtotal',
        'tax_total',
        'total_amount',
        'notes',
        'converted_to_invoice_id',
        'sent_at',
        'accepted_at',
        'declined_at',
        'created_by',
    ];

    protected $casts = [
        'estimate_date' => 'date',
        'due_date' => 'date',
        'discount_value' => 'decimal:2',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Money accessors (cents <-> dollars)

    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function taxTotal(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function discountAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    // Relationships

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emailsSent(): HasMany
    {
        return $this->hasMany(EstimateEmail::class)->orderByDesc('sent_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EstimateStatusHistory::class)->orderByDesc('created_at');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_to_invoice_id');
    }

    // Status tracking

    public function recordStatusChange(\App\Models\User $user, ?string $oldStatus, string $newStatus): void
    {
        $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user->id,
        ]);
    }

    // Status helpers

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft() || $this->isSent();
    }

    public function canBeSent(): bool
    {
        return $this->isDraft() && $this->items()->count() > 0;
    }

    // Auto-number generation

    public static function generateEstimateNumber(): string
    {
        $last = static::max('estimate_number');

        if (!$last) {
            return 'EST-0001';
        }

        $number = (int) str_replace('EST-', '', $last);

        return 'EST-' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    // Due date calculation

    public static function calculateDueDate(string $estimateDate, string $terms): string
    {
        $days = match ($terms) {
            'due_upon_receipt' => 0,
            'net_15' => 15,
            'net_30' => 30,
            'net_60' => 60,
            'net_90' => 90,
            default => 30,
        };

        return \Carbon\Carbon::parse($estimateDate)->addDays($days)->toDateString();
    }

    // Status display helpers

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'sent' => 'bg-blue-100 text-blue-800',
            'accepted' => 'bg-green-100 text-green-800',
            'declined' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => __('Draft'),
            'sent' => __('Sent'),
            'accepted' => __('Accepted'),
            'declined' => __('Declined'),
            default => ucfirst($this->status),
        };
    }

    public function getTermsLabelAttribute(): string
    {
        return match ($this->terms) {
            'due_upon_receipt' => 'Due Upon Receipt',
            'net_15' => 'Net 15',
            'net_30' => 'Net 30',
            'net_60' => 'Net 60',
            'net_90' => 'Net 90',
            default => ucfirst($this->terms),
        };
    }
}
