<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two signals that decide which budget lines need an approval.
     *
     * **Why the flag is on three tables and not one.** A project's applied cost
     * code IS its `BudgetItem` — copied out of a `CostCodeTemplate` with its own
     * `code` and `name`, and carrying **no `cost_code_id`** back to the library
     * it came from (docs/rfi-aprovacoes-discovery.md item 4). So a flag living
     * only on `cost_codes` could never be read from a budget line. It is copied
     * forward when a template is applied, exactly as `code` and `name` already
     * are. `catalog_items` carries its own because an item is flagged once and
     * then earns its keep on every project that buys it.
     *
     * **Why a threshold alone would be the wrong filter.** The highest-value
     * lines in a BR orçamento are concreto, aço and alvenaria — commodities with
     * an NBR, approved by certificate if at all. What needs a review cycle is
     * spec-sensitive: porcelanatos, louças e metais, esquadrias, vidros,
     * elevadores, climatização, impermeabilização. The threshold is a crude
     * first pass; the flag is what makes seeding accurate, and it compounds as
     * a company marks up its catalogue.
     *
     * Existing rows get `false`, so nothing changes for anybody until somebody
     * marks something up.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Null means no value pre-filter at all, which is the sane default:
            // guessing a threshold for somebody is worse than asking.
            $table->unsignedBigInteger('approval_seed_threshold')->nullable()->after('initial_amount');
        });

        foreach (['cost_codes', 'budget_items', 'catalog_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('requires_approval')->default(false);
                $table->string('default_approval_type', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('approval_seed_threshold');
        });

        foreach (['cost_codes', 'budget_items', 'catalog_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['requires_approval', 'default_approval_type']);
            });
        }
    }
};
