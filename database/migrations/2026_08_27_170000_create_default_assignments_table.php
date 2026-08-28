<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who gets a piece of work by default, at each level of the tree.
     *
     * One general table rather than a column per level per module: the buyer
     * who runs a cotação is the first `role_key`, and RFI ball-in-court,
     * approval reviewers and task owners are queued behind it. A new module
     * adds a constant, not a migration — the same reasoning
     * `notification_settings` was built on.
     *
     * Not a morph. The `global` tier is the install itself and has no row to
     * point at, so `context_id` carries no foreign key and means different
     * tables under different `context_type` values.
     *
     * `context_id` is **0 for global**, not null. The plan said nullable, but
     * MySQL treats NULLs as distinct inside a unique index, so a nullable
     * column would let two `global` rows exist for one `role_key` — an
     * install-wide default that resolves to whichever row came back first.
     * A sentinel 0 makes `default_assignment_unique` actually enforce
     * one default per role per context, which is the whole point of the index.
     *
     * Resolution walks job site → project → global and is in
     * App\Models\DefaultAssignment::resolve().
     */
    public function up(): void
    {
        Schema::create('default_assignments', function (Blueprint $table) {
            $table->id();

            $table->enum('context_type', ['global', 'project', 'job_site']);
            $table->unsignedBigInteger('context_id')->default(0);

            // A constant on the model, never a free string: 'quotation_buyer'
            // to start with.
            $table->string('role_key', 40);

            // nullOnDelete on both, and for the same reason: deleting a person
            // must not delete the rule that names them. The row falls back to
            // "nobody set here", which the resolver treats as "look one level
            // up" — never as a silent hole.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['context_type', 'context_id', 'role_key'], 'default_assignment_unique');
            $table->index('role_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_assignments');
    }
};
