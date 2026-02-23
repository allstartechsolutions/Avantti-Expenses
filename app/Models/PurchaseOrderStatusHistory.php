<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderStatusHistory extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'old_status',
        'new_status',
        'changed_by',
        'reason',
        'revision_data',
    ];

    protected $casts = [
        'revision_data' => 'array',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    /**
     * Get a human-readable description of the status change.
     */
    public function getChangeDescription(): string
    {
        $oldLabel = $this->old_status ? $this->getStatusLabel($this->old_status) : __('Created');
        $newLabel = $this->getStatusLabel($this->new_status);

        if (!$this->old_status) {
            return __('Created as :status', ['status' => $newLabel]);
        }

        return "{$oldLabel} → {$newLabel}";
    }

    /**
     * Get status label.
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => __('Draft'),
            'pending' => __('Pending Approval'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($status),
        };
    }

    /**
     * Get color for the new status.
     */
    public function getStatusColor(): string
    {
        return match ($this->new_status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
