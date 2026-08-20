<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which task e-mails this install sends.
     *
     * One row per trigger rather than a column each, so adding a trigger later
     * is a row and not a migration. `options` carries what a trigger needs —
     * the weekly digest keeps its day and hour there.
     */
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->json('options')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The four the owner asked for, on by default.
        $now = now();

        DB::table('notification_settings')->insert([
            ['key' => 'task_created', 'is_enabled' => true, 'options' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'task_closed', 'is_enabled' => true, 'options' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'task_overdue', 'is_enabled' => true, 'options' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'task_weekly_digest', 'is_enabled' => true, 'options' => json_encode(['day' => 1, 'hour' => 7]), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
