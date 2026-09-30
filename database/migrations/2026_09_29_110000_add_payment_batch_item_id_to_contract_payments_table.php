<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payment approved from a payment batch now remembers the batch line it
     * came from, so the contract page can show the batch and the batch's own
     * notes beside the payment. Until now the two were not linked at all.
     *
     * Existing payments are linked where the match is certain: an approved
     * batch line for the same contract, the same amount and the batch's payment
     * date, with exactly one unlinked payment answering to it. Anything
     * ambiguous is left unlinked rather than guessed.
     */
    public function up(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->foreignId('payment_batch_item_id')->nullable()->after('contract_measurement_id')
                ->constrained('payment_batch_items')->nullOnDelete();
        });

        DB::table('payment_batch_items')
            ->join('payment_batches', 'payment_batches.id', '=', 'payment_batch_items.payment_batch_id')
            ->where('payment_batch_items.status', 'approved')
            ->whereNotNull('payment_batch_items.amount')
            ->orderBy('payment_batch_items.id')
            ->select('payment_batch_items.id', 'payment_batch_items.contract_id', 'payment_batch_items.amount', 'payment_batches.payment_date')
            ->chunk(500, function ($items) {
                foreach ($items as $item) {
                    $matches = DB::table('contract_payments')
                        ->whereNull('payment_batch_item_id')
                        ->where('contract_id', $item->contract_id)
                        ->where('amount', $item->amount)
                        ->whereDate('payment_date', $item->payment_date)
                        ->limit(2)
                        ->pluck('id');

                    if ($matches->count() === 1) {
                        DB::table('contract_payments')->where('id', $matches->first())
                            ->update(['payment_batch_item_id' => $item->id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_batch_item_id');
        });
    }
};
