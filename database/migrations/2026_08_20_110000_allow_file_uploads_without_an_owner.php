<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A file does not always belong to one record.
     *
     * Documentation images belong to the library itself: the guide that shows
     * them is a markdown file shipped with the code, not a row. Their identity
     * is their object key, so the owner columns become optional rather than
     * being pointed at something invented.
     */
    public function up(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->string('attachable_type')->nullable()->change();
            $table->unsignedBigInteger('attachable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('file_uploads', function (Blueprint $table) {
            $table->string('attachable_type')->nullable(false)->change();
            $table->unsignedBigInteger('attachable_id')->nullable(false)->change();
        });
    }
};
