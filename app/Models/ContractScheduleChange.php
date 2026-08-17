<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractScheduleChange extends Model
{
    protected $fillable = [
        'contract_id',
        'contract_schedule_item_id',
        'item_description',
        'action',
        'changes',
        'changed_by',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(ContractScheduleItem::class, 'contract_schedule_item_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'created' => __('Installment Created'),
            'updated' => __('Installment Updated'),
            'deleted' => __('Installment Deleted'),
            'released' => __('Installment Released'),
            'release_reverted' => __('Approval Reverted'),
            default => ucfirst($this->action),
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            'created' => 'green',
            'updated' => 'blue',
            'deleted' => 'red',
            'released' => 'amber',
            'release_reverted' => 'orange',
            default => 'gray',
        };
    }
}
