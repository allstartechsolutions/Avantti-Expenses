<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentBatch extends Model
{
    protected $fillable = [
        'name',
        'status',
        'payment_date',
        'notes',
        'client_id',
        'project_id',
        'subcontractor_id',
        'project_manager_id',
        'contract_status_filter',
        'show_zero_balance',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'show_zero_balance' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PaymentBatchItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'partially_approved']);
    }

    public function getTotalAmount(): float
    {
        return round($this->items()->sum('amount') / 100, 2);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'partially_approved' => 'Partially Approved',
            'approved' => 'Approved',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'partially_approved' => 'amber',
            'approved' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
