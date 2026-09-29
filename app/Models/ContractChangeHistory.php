<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a contract's terms: an edit, a change order added, altered
 * or removed, or a payment taken back out.
 *
 * `changes` maps a field to ['old' => …, 'new' => …] for an edit, or holds
 * the plain values of the record added or removed. Values are stored raw
 * (Y-m-d dates, decimal amounts, names rather than ids) and formatted when
 * shown, so a locale switch never leaves an old entry in the wrong format.
 */
class ContractChangeHistory extends Model
{
    public const MONEY_FIELDS = ['amount', 'adjusted_amount', 'amount_paid', 'balance_due'];

    public const DATE_FIELDS = ['start_date', 'end_date', 'date', 'payment_date'];

    protected $fillable = [
        'contract_id',
        'contract_change_order_id',
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

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'edited' => __('Contract Edited'),
            'change_order_added' => __('Change Order Added'),
            'change_order_updated' => __('Change Order Updated'),
            'change_order_deleted' => __('Change Order Deleted'),
            'payment_deleted' => __('Payment Deleted'),
            'status_reconciled' => __('Status Corrected'),
            default => ucfirst(str_replace('_', ' ', (string) $this->action)),
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            'edited' => 'blue',
            'change_order_added' => 'green',
            'change_order_updated' => 'amber',
            'change_order_deleted', 'payment_deleted' => 'red',
            'status_reconciled' => 'amber',
            default => 'gray',
        };
    }

    /** The name a person reads for a stored field key. */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'subcontractor' => __('Subcontractor'),
            'subcontractor_employee' => __('Contact'),
            'job_site' => __('Job Site'),
            'start_date' => __('Start Date'),
            'end_date' => __('End Date'),
            'amount' => __('Amount'),
            'retention_percent' => __('Retention'),
            'notes' => __('Notes'),
            'contract_file' => __('Contract File'),
            'allocations' => __('Cost Code Allocations'),
            'status' => __('Status'),
            'title' => __('Title'),
            'date' => __('Date'),
            'cost_code' => __('Cost Code'),
            'description' => __('Description'),
            'file' => __('File'),
            'adjusted_amount' => __('Adjusted Amount'),
            'amount_paid' => __('Amount Paid'),
            'balance_due' => __('Balance Due'),
            'payment_date' => __('Payment Date'),
            'reference' => __('Reference'),
            'payment_method' => __('Payment Method'),
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    public static function isMoneyField(string $field): bool
    {
        return in_array($field, self::MONEY_FIELDS, true);
    }

    /**
     * A stored value as a person reads it. Money is left to the view
     * (<x-ui.money>); everything else is resolved here.
     */
    public static function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return \Illuminate\Support\Carbon::parse($value)->appDate();
        }

        return match ($field) {
            'status' => Contract::statusLabel($value),
            'retention_percent' => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%',
            'contract_file', 'file' => match ($value) {
                'added' => __('Added'),
                'replaced' => __('Replaced'),
                'removed' => __('Removed'),
                default => (string) $value,
            },
            'allocations' => __('Changed'),
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }
}
