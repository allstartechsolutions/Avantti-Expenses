<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected $fillable = [
        'client_id',
        'project_id',
        'job_site_id',
        'estimate_id',
        'invoice_number',
        'invoice_date',
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
        'payment_token',
        'sent_at',
        'paid_at',
        'created_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->payment_token)) {
                $invoice->payment_token = Str::uuid()->toString();
            }
        });
    }

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'discount_value' => 'decimal:2',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
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

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emailsSent(): HasMany
    {
        return $this->hasMany(InvoiceEmail::class)->orderByDesc('sent_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InvoiceStatusHistory::class)->orderByDesc('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('payment_number');
    }

    // Status tracking

    public function recordStatusChange(?User $user, ?string $oldStatus, string $newStatus): void
    {
        $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user?->id,
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

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isPastDue(): bool
    {
        return in_array($this->status, ['pending', 'partial']) && $this->due_date->isPast();
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

    public static function generateInvoiceNumber(): string
    {
        $last = static::max('invoice_number');

        if (!$last) {
            return 'INV-0001';
        }

        $number = (int) str_replace('INV-', '', $last);

        return 'INV-' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    // Due date calculation

    public static function calculateDueDate(string $invoiceDate, string $terms): string
    {
        $days = match ($terms) {
            'due_upon_receipt' => 0,
            'net_15' => 15,
            'net_30' => 30,
            'net_60' => 60,
            'net_90' => 90,
            default => 30,
        };

        return \Carbon\Carbon::parse($invoiceDate)->addDays($days)->toDateString();
    }

    // Payment helpers

    public function getAmountPaid(): float
    {
        // Count completed and partially refunded payments, net of any amount refunded.
        $query = $this->payments()->whereIn('status', ['completed', 'partially_refunded']);

        $gross = (int) (clone $query)->sum('amount');
        $refunded = (int) (clone $query)->sum('refund_amount');

        return round(($gross - $refunded) / 100, 2);
    }

    public function getBalanceDue(): float
    {
        return round($this->total_amount - $this->getAmountPaid(), 2);
    }

    public function getPaymentProgress(): int
    {
        if ($this->total_amount == 0) {
            return 0;
        }

        return (int) min(100, round(($this->getAmountPaid() / $this->total_amount) * 100));
    }

    public function updateStatusFromPayments(): void
    {
        $balanceDue = $this->getBalanceDue();
        $amountPaid = $this->getAmountPaid();
        $oldStatus = $this->status;

        if ($balanceDue <= 0 && $amountPaid > 0) {
            $this->update([
                'status' => 'paid',
                'paid_at' => $this->paid_at ?? now(),
            ]);
        } elseif ($amountPaid > 0 && $balanceDue > 0) {
            $this->update([
                'status' => 'partial',
                'paid_at' => null,
            ]);
        } else {
            // No completed payments — revert to pending if was partial/paid
            if (in_array($oldStatus, ['partial', 'paid'])) {
                $this->update([
                    'status' => 'pending',
                    'paid_at' => null,
                ]);
            }
        }

        // Record status change if it changed
        if ($this->status !== $oldStatus) {
            $this->recordStatusChange(auth()->user(), $oldStatus, $this->status);
        }
    }

    public function getPaymentUrl(): string
    {
        return route('invoice.pay', $this->payment_token);
    }

    // Status display helpers

    public function getStatusColorAttribute(): string
    {
        if ($this->isPastDue()) {
            return 'bg-red-100 text-red-800';
        }

        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'sent' => 'bg-blue-100 text-blue-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'partial' => 'bg-orange-100 text-orange-800',
            'paid' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->isPastDue()) {
            return 'Past Due';
        }

        return match ($this->status) {
            'draft' => 'Draft',
            'sent' => 'Sent',
            'pending' => 'Pending',
            'partial' => 'Partial',
            'paid' => 'Paid',
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
