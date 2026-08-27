<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every reply to an SI, kept as its own record.
     *
     * An SI is answered by whoever can answer it, and on a real job that is
     * often more than one person — the projetista replies, the structural
     * engineer qualifies it, and somebody then has to say which of the two the
     * work is built to. A single `answer` column could not express that: a
     * second reply overwrote the first, and the only trace of it was a JSON
     * blob inside an activity row.
     *
     * `rfis.valid_reply_id` is the answer that counts. One pointer rather than
     * an `is_valid` flag on each row, because two rows both claiming to be the
     * valid one is a state that should not be representable.
     *
     * `rfis.answer` / `answered_by_id` / `answered_at` stay as a mirror of the
     * valid reply. They are what the PDF prints, what search matches and what
     * a change order is argued from, and keeping them saves rewriting all of
     * that to chase a relation.
     */
    public function up(): void
    {
        Schema::create('rfi_replies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rfi_id')->constrained()->cascadeOnDelete();
            $table->text('body');

            $table->foreignId('replied_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at');

            // A reply can be corrected. Who and when is kept beside it, so a
            // reader can see the words were changed after they were given.
            $table->foreignId('edited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();

            $table->timestamps();

            $table->index(['rfi_id', 'replied_at']);
        });

        Schema::table('rfis', function (Blueprint $table) {
            $table->foreignId('valid_reply_id')->nullable()->after('answer')
                ->constrained('rfi_replies')->nullOnDelete();
        });

        // Every answer already on record becomes its first reply, and the
        // valid one — otherwise the screens would show an answer with no reply
        // behind it.
        foreach (DB::table('rfis')->whereNotNull('answer')->get() as $rfi) {
            $replyId = DB::table('rfi_replies')->insertGetId([
                'rfi_id' => $rfi->id,
                'body' => $rfi->answer,
                'replied_by_id' => $rfi->answered_by_id,
                'replied_at' => $rfi->answered_at ?? $rfi->updated_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('rfis')->where('id', $rfi->id)->update(['valid_reply_id' => $replyId]);
        }
    }

    public function down(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valid_reply_id');
        });

        Schema::dropIfExists('rfi_replies');
    }
};
