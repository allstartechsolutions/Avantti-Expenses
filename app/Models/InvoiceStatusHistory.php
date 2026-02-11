<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceStatusHistory extends Model
{
    protected $fillable = [
        'invoice_id',
        'old_status',
        'new_status',
        'changed_by',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    public function getChangeDescription(): string
    {
        $oldLabel = $this->old_status ? $this->getStatusLabel($this->old_status) : 'Created';
        $newLabel = $this->getStatusLabel($this->new_status);

        if (!$this->old_status) {
            return "Created as {$newLabel}";
        }

        return "{$oldLabel} → {$newLabel}";
    }

    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'sent' => 'Sent',
            'pending' => 'Pending',
            'partial' => 'Partial',
            'paid' => 'Paid',
            default => ucfirst($status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->new_status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'pending' => 'yellow',
            'partial' => 'orange',
            'paid' => 'green',
            default => 'gray',
        };
    }
}
