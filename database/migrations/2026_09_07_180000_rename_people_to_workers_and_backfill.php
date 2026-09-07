<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "People" becomes "Workers", and a worker now exists for every employee
     * row rather than only for the ones linked across companies — the
     * Workers screen lists everybody and every worker has a contracts page.
     *
     * Three things move: the table and the pointer column are renamed, every
     * employee row without a worker gets one (the backfill), and the two
     * stored abilities are rewritten on every role and override so nobody
     * loses access. `seeded_areas` is rewritten too, or the seeder would
     * offer the "new" area a second time.
     */
    private const ABILITIES = ['people.view' => 'workers.view', 'people.link' => 'workers.link'];

    public function up(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        Schema::rename('people', 'workers');

        Schema::table('subcontractor_employees', function (Blueprint $table) use ($sqlite) {
            // SQLite rewrites the reference when the table is renamed and
            // cannot drop a constraint on its own; the other drivers need
            // the constraint taken off before the column can move.
            if (! $sqlite) {
                $table->dropForeign(['person_id']);
            }

            $table->renameColumn('person_id', 'worker_id');
        });

        if (! $sqlite) {
            Schema::table('subcontractor_employees', function (Blueprint $table) {
                $table->foreign('worker_id')->references('id')->on('workers')->nullOnDelete();
            });
        }

        // Every employee row is a worker now.
        DB::table('subcontractor_employees')
            ->whereNull('worker_id')
            ->orderBy('id')
            ->select(['id', 'name', 'created_at'])
            ->each(function ($row) {
                $workerId = DB::table('workers')->insertGetId([
                    'name' => $row->name,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                DB::table('subcontractor_employees')->where('id', $row->id)->update(['worker_id' => $workerId]);
            });

        foreach (['role_abilities', 'user_abilities'] as $table) {
            if (Schema::hasTable($table)) {
                foreach (self::ABILITIES as $from => $to) {
                    DB::table($table)->where('ability', $from)->update(['ability' => $to]);
                }
            }
        }

        $this->renameSeededArea('people', 'workers');
    }

    public function down(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        $this->renameSeededArea('workers', 'people');

        foreach (['role_abilities', 'user_abilities'] as $table) {
            if (Schema::hasTable($table)) {
                foreach (self::ABILITIES as $from => $to) {
                    DB::table($table)->where('ability', $to)->update(['ability' => $from]);
                }
            }
        }

        Schema::table('subcontractor_employees', function (Blueprint $table) use ($sqlite) {
            if (! $sqlite) {
                $table->dropForeign(['worker_id']);
            }

            $table->renameColumn('worker_id', 'person_id');
        });

        Schema::rename('workers', 'people');

        if (! $sqlite) {
            Schema::table('subcontractor_employees', function (Blueprint $table) {
                $table->foreign('person_id')->references('id')->on('people')->nullOnDelete();
            });
        }
    }

    private function renameSeededArea(string $from, string $to): void
    {
        if (! Schema::hasColumn('roles', 'seeded_areas')) {
            return;
        }

        foreach (DB::table('roles')->whereNotNull('seeded_areas')->get(['id', 'seeded_areas']) as $role) {
            $areas = json_decode($role->seeded_areas, true) ?: [];

            if (! in_array($from, $areas, true)) {
                continue;
            }

            $areas = array_values(array_unique(array_map(fn ($a) => $a === $from ? $to : $a, $areas)));

            DB::table('roles')->where('id', $role->id)->update(['seeded_areas' => json_encode($areas)]);
        }
    }
};
