<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bundle of approvals submitted together.
     *
     * The US submittal package: everything for one part of the work goes to
     * the architect at once, and comes back at once. Rare in BR practice,
     * which submits item by item, so an approval's `package_id` is nullable
     * and most installs will never fill it.
     *
     * Created before `approvals`, because that table's foreign key points here.
     */
    public function up(): void
    {
        Schema::create('approval_packages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('number', 60);
            $table->string('title');
            $table->string('status', 20)->default('open');

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_packages');
    }
};
