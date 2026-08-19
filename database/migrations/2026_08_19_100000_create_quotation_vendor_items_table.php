<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one vendor priced one line at. Keyed in by procurement from the
     * proposal the vendor e-mailed back — there is no vendor portal.
     *
     * A vendor may not supply a line at all, or may offer a substitute brand
     * or spec; both are recorded rather than left as an empty price, because
     * the comparison map has to say why a column is blank.
     */
    public function up(): void
    {
        Schema::create('quotation_vendor_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_vendor_id')->constrained('quotation_vendors')->cascadeOnDelete();
            $table->foreignId('quotation_item_id')->constrained('quotation_items')->cascadeOnDelete();

            // Money in cents, like everywhere else in the app
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            $table->boolean('is_unavailable')->default(false);
            $table->string('offered_brand')->nullable();
            $table->text('offered_spec')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['quotation_vendor_id', 'quotation_item_id'], 'quotation_vendor_item_unique');
            $table->index('quotation_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_vendor_items');
    }
};
