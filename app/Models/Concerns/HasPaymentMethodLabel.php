<?php

namespace App\Models\Concerns;

/**
 * Translates a stored payment_method / payment_frequency value into a label.
 *
 * The columns store English enum values ('bank_transfer', 'biweekly'), which
 * several screens used to print with ucfirst(str_replace('_', ' ', ...)) — so a
 * Brazilian user read "Bank transfer". The keys below are the Title Case ones
 * already carried in lang/pt_BR.json, so nothing new had to be invented.
 *
 * Shared by Expense, ExpensePayment and PurchaseOrder because all three store
 * the same enum. See docs/pt-br-translation-audit.md.
 */
trait HasPaymentMethodLabel
{
    public static function paymentMethodLabel(?string $method): ?string
    {
        if ($method === null || $method === '') {
            return null;
        }

        return match ($method) {
            'cash' => __('Cash'),
            'check' => __('Check'),
            'credit_card' => __('Credit Card'),
            'debit_card' => __('Debit Card'),
            'bank_transfer' => __('Bank Transfer'),
            'pix' => __('PIX'),
            'other' => __('Other'),
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public static function paymentFrequencyLabel(?string $frequency): ?string
    {
        if ($frequency === null || $frequency === '') {
            return null;
        }

        return match ($frequency) {
            'weekly' => __('Weekly'),
            'biweekly' => __('Biweekly'),
            'monthly' => __('Monthly'),
            default => ucfirst($frequency),
        };
    }

    /** The label for this record's own payment_method. */
    public function getPaymentMethodLabel(): ?string
    {
        return static::paymentMethodLabel($this->payment_method);
    }
}
