<?php

namespace App\Services;

use App\Models\BackCharge;
use App\Models\BackChargeDeduction;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentBatchItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

/**
 * The one place a back charge is deducted (docs/back-charges.md). The batch
 * screen and both contract-payment screens call it; every rule lives here:
 *
 *  - only the subcontractor's own back charges, and only the employee's when
 *    the contract names that employee;
 *  - never more than a back charge has remaining;
 *  - never more, in all, than the payment (the check cannot go below zero);
 *  - each back charge row is locked while its remaining is read, so two
 *    people paying at once cannot take the same dollars.
 *
 * Callers run it inside their own save transaction.
 */
class BackChargeDeductions
{
    /**
     * Replace what a batch line deducts. Called on every save of the line
     * while it is pending; an emptied amount removes that deduction.
     *
     * @param  array<int|string, mixed>  $amounts  back_charge_id => amount
     */
    public function syncForBatchItem(PaymentBatchItem $item, array $amounts, string $errorKey = 'deductions'): void
    {
        $item->loadMissing('contract');

        DB::transaction(function () use ($item, $amounts, $errorKey) {
            // Only a pending line may change what it deducts. An approved one
            // has a payment, and its deductions are part of that check — a
            // screen left open since must never rewrite them.
            $locked = PaymentBatchItem::whereKey($item->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending' || $locked->backChargeDeductions()->whereNotNull('contract_payment_id')->exists()) {
                throw ValidationException::withMessages([$errorKey => __('This line was approved or rejected meanwhile. Reload the batch.')]);
            }

            $locked->backChargeDeductions()->whereNull('contract_payment_id')->delete();

            $this->create($item->contract, $amounts, (float) $locked->amount, $errorKey, ['payment_batch_item_id' => $item->id]);
        });
    }

    /**
     * Deduct from a payment recorded directly on the contract.
     *
     * @param  array<int|string, mixed>  $amounts  back_charge_id => amount
     */
    public function deductFromPayment(ContractPayment $payment, array $amounts, string $errorKey = 'deductions'): void
    {
        $payment->loadMissing('contract');

        DB::transaction(function () use ($payment, $amounts, $errorKey) {
            $this->create($payment->contract, $amounts, (float) $payment->amount, $errorKey, ['contract_payment_id' => $payment->id]);
        });
    }

    /**
     * Why a pending line's reserved deductions can no longer be taken, or
     * null: the contract may have been given another subcontractor or
     * employee since the line was saved, and one payee's debt never lowers
     * another's check.
     */
    public function payeeError(PaymentBatchItem $item, Contract $contract): ?string
    {
        $ids = $item->backChargeDeductions()->whereNull('contract_payment_id')->pluck('back_charge_id')->unique();

        if ($ids->isEmpty()) {
            return null;
        }

        $fitting = BackCharge::deductibleFrom($contract)->whereKey($ids)->count();

        return $fitting === $ids->count() ? null : __(':number: a back charge on this line is not owed by the contract\'s current subcontractor or employee. Open the batch and clear it.', [
            'number' => $contract->contract_number,
        ]);
    }

    /** A batch line was approved and paid: its deductions now lower the contract. */
    public function takeForApprovedItem(PaymentBatchItem $item, ContractPayment $payment): void
    {
        $item->loadMissing('contract');

        if ($error = $this->payeeError($item, $item->contract)) {
            throw ValidationException::withMessages(['deductions' => $error]);
        }

        $item->backChargeDeductions()->whereNull('contract_payment_id')->update(['contract_payment_id' => $payment->id]);
    }

    /** A batch line was rejected: what it reserved goes back to the back charges. */
    public function releaseForBatchItem(PaymentBatchItem $item): void
    {
        $item->backChargeDeductions()->whereNull('contract_payment_id')->delete();
    }

    /**
     * Validate and write. Throws a ValidationException naming the offending
     * back charge, so the screen can show it beside the right box.
     *
     * @param  array<int|string, mixed>  $amounts
     * @param  array<string, int>  $link
     */
    protected function create(Contract $contract, array $amounts, float $paymentAmount, string $errorKey, array $link): void
    {
        $wanted = [];

        foreach ($amounts as $id => $amount) {
            $cents = is_numeric($amount) ? (int) round((float) $amount * 100) : 0;

            if ($cents < 0) {
                throw ValidationException::withMessages(["{$errorKey}.{$id}" => __('A deduction cannot be negative.')]);
            }

            if ($cents > 0) {
                $wanted[(int) $id] = $cents;
            }
        }

        if ($wanted === []) {
            return;
        }

        if (array_sum($wanted) > (int) round($paymentAmount * 100)) {
            throw ValidationException::withMessages([$errorKey => __('The back charges deducted cannot be more than the payment.')]);
        }

        $charges = BackCharge::query()
            ->deductibleFrom($contract)
            ->whereKey(array_keys($wanted))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($wanted as $id => $cents) {
            $charge = $charges->get($id);

            if (! $charge) {
                throw ValidationException::withMessages(["{$errorKey}.{$id}" => __('This back charge cannot be deducted from this contract.')]);
            }

            // A current, locking read: under REPEATABLE READ a plain sum would
            // see the snapshot taken before this lock was granted, and miss a
            // deduction another payment committed while we waited.
            $deductedNow = (int) BackChargeDeduction::where('back_charge_id', $id)->lockForUpdate()->sum('amount');
            $remainingNow = max(0, (int) $charge->getRawOriginal('amount') - $deductedNow);

            if ($cents > $remainingNow) {
                throw ValidationException::withMessages(["{$errorKey}.{$id}" => __(':number has only :amount left to deduct.', [
                    'number' => $charge->number,
                    'amount' => Number::currency($remainingNow / 100, config('app.currency'), config('app.locale')),
                ])]);
            }

            BackChargeDeduction::create($link + [
                'back_charge_id' => $id,
                'contract_id' => $contract->id,
                'amount' => $cents / 100,
                'created_by' => Auth::id(),
            ]);
        }
    }
}
