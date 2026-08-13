<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copies all suppliers and subcontractors into the unified `vendors`
     * table and repoints every foreign key to the new vendor ids.
     *
     * The legacy `suppliers` and `subcontractors` tables are intentionally
     * left in place, untouched, as a safety net. They are no longer read or
     * written by the application and can be dropped in a future release once
     * the migration is verified in production.
     *
     * Foreign keys remapped:
     *   - expenses.supplier_id, catalog_items.supplier_id, purchase_orders.supplier_id
     *   - contracts.subcontractor_id, payment_batches.subcontractor_id
     *   - subcontractor_documents.subcontractor_id, subcontractor_employees.subcontractor_id
     */
    private const SUPPLIER_FKS = ['expenses', 'catalog_items', 'purchase_orders'];

    private const SUBCONTRACTOR_FKS = ['contracts', 'payment_batches', 'subcontractor_documents', 'subcontractor_employees'];

    public function up(): void
    {
        // Step 1: drop the FK constraints pointing at the legacy tables so the
        // id values can be rewritten. (DDL — runs outside the transaction.)
        foreach (self::SUPPLIER_FKS as $table) {
            $this->dropForeignIfExists($table, 'supplier_id');
        }
        foreach (self::SUBCONTRACTOR_FKS as $table) {
            $this->dropForeignIfExists($table, 'subcontractor_id');
        }

        // Step 2: copy rows and remap ids atomically. Guarded so a re-run
        // after a mid-step failure never remaps twice.
        $alreadyMigrated = DB::table('vendors')
            ->whereNotNull('legacy_supplier_id')
            ->orWhereNotNull('legacy_subcontractor_id')
            ->exists();

        if (! $alreadyMigrated) {
            DB::transaction(function () {
                DB::statement('
                    INSERT INTO vendors (name, is_supplier, phone, email, description,
                        street, address_2, neighborhood, city, state, postal_code, country,
                        created_by, created_at, updated_at, legacy_supplier_id)
                    SELECT name, 1, phone, email, description,
                        street, address_2, neighborhood, city, state, postal_code, country,
                        created_by, created_at, updated_at, id
                    FROM suppliers
                ');

                DB::statement('
                    INSERT INTO vendors (name, is_subcontractor, website, contact_name, contact_email, title, phone,
                        street, address_2, neighborhood, city, state, postal_code, country, latitude, longitude,
                        created_by, created_at, updated_at, legacy_subcontractor_id)
                    SELECT company_name, 1, website, contact_name, contact_email, title, phone,
                        street, address_2, neighborhood, city, state, postal_code, country, latitude, longitude,
                        created_by, created_at, updated_at, id
                    FROM subcontractors
                ');

                foreach (self::SUPPLIER_FKS as $table) {
                    DB::statement("
                        UPDATE {$table} t
                        JOIN vendors v ON v.legacy_supplier_id = t.supplier_id
                        SET t.supplier_id = v.id
                        WHERE t.supplier_id IS NOT NULL
                    ");
                }

                foreach (self::SUBCONTRACTOR_FKS as $table) {
                    DB::statement("
                        UPDATE {$table} t
                        JOIN vendors v ON v.legacy_subcontractor_id = t.subcontractor_id
                        SET t.subcontractor_id = v.id
                        WHERE t.subcontractor_id IS NOT NULL
                    ");
                }
            });
        }

        // Step 3: point the FK constraints at vendors, mirroring the original
        // delete behavior of each column.
        foreach (self::SUPPLIER_FKS as $table) {
            $this->addForeignUnlessExists($table, 'supplier_id', 'vendors', 'SET NULL');
        }
        foreach (['contracts', 'payment_batches'] as $table) {
            $this->addForeignUnlessExists($table, 'subcontractor_id', 'vendors', 'SET NULL');
        }
        foreach (['subcontractor_documents', 'subcontractor_employees'] as $table) {
            $this->addForeignUnlessExists($table, 'subcontractor_id', 'vendors', 'CASCADE');
        }
    }

    /**
     * DESTRUCTIVE rollback — refuses to run once real usage exists.
     *
     * Rolling back deletes every row in `vendors` and re-points FKs at the
     * legacy tables, which are a frozen snapshot from migration day. Vendors
     * created after the migration would be destroyed outright, and any edits
     * or merges made since would be lost. To protect production data, down()
     * throws when post-migration vendors exist; recovering past that point is
     * a manual operation using the untouched legacy tables.
     */
    public function down(): void
    {
        $postMigration = DB::table('vendors')
            ->whereNull('legacy_supplier_id')
            ->whereNull('legacy_subcontractor_id')
            ->count();

        if ($postMigration > 0) {
            throw new RuntimeException(
                "Refusing to roll back: {$postMigration} vendor(s) were created after the migration and would be permanently destroyed. " .
                'Roll back manually if this is truly intended (the legacy suppliers/subcontractors tables are untouched).'
            );
        }

        foreach (self::SUPPLIER_FKS as $table) {
            $this->dropForeignIfExists($table, 'supplier_id');
        }
        foreach (self::SUBCONTRACTOR_FKS as $table) {
            $this->dropForeignIfExists($table, 'subcontractor_id');
        }

        DB::transaction(function () {
            foreach (self::SUPPLIER_FKS as $table) {
                DB::statement("
                    UPDATE {$table} t
                    LEFT JOIN vendors v ON v.id = t.supplier_id
                    SET t.supplier_id = v.legacy_supplier_id
                    WHERE t.supplier_id IS NOT NULL
                ");
            }

            foreach (['contracts', 'payment_batches'] as $table) {
                DB::statement("
                    UPDATE {$table} t
                    LEFT JOIN vendors v ON v.id = t.subcontractor_id
                    SET t.subcontractor_id = v.legacy_subcontractor_id
                    WHERE t.subcontractor_id IS NOT NULL
                ");
            }

            foreach (['subcontractor_documents', 'subcontractor_employees'] as $table) {
                DB::statement("
                    DELETE t FROM {$table} t
                    LEFT JOIN vendors v ON v.id = t.subcontractor_id
                    WHERE v.legacy_subcontractor_id IS NULL
                ");
                DB::statement("
                    UPDATE {$table} t
                    JOIN vendors v ON v.id = t.subcontractor_id
                    SET t.subcontractor_id = v.legacy_subcontractor_id
                ");
            }

            DB::table('vendors')->delete();
        });

        foreach (self::SUPPLIER_FKS as $table) {
            $this->addForeignUnlessExists($table, 'supplier_id', 'suppliers', 'SET NULL');
        }
        foreach (['contracts', 'payment_batches'] as $table) {
            $this->addForeignUnlessExists($table, 'subcontractor_id', 'subcontractors', 'SET NULL');
        }
        foreach (['subcontractor_documents', 'subcontractor_employees'] as $table) {
            $this->addForeignUnlessExists($table, 'subcontractor_id', 'subcontractors', 'CASCADE');
        }
    }

    /**
     * Drops the FK constraint on $table.$column if one exists, regardless of
     * its generated name — keeps up()/down() safely re-runnable.
     */
    private function dropForeignIfExists(string $table, string $column): void
    {
        $constraint = DB::selectOne('
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$table, $column]);

        if ($constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->name}`");
        }
    }

    /**
     * Adds a FK constraint on $table.$column referencing $referenced.id
     * unless the column already has one — keeps up()/down() safely
     * re-runnable after a partial failure.
     */
    private function addForeignUnlessExists(string $table, string $column, string $referenced, string $onDelete): void
    {
        $existing = DB::selectOne('
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$table, $column]);

        if (! $existing) {
            $name = "{$table}_{$column}_foreign";
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$referenced}` (`id`) ON DELETE {$onDelete}");
        }
    }
};
