<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets models with non-integer primary keys be audited.
 *
 * `audits.auditable_id` was BIGINT UNSIGNED, so a model keyed by a UUID could
 * not be logged at all -- MySQL rejected the insert with
 *
 *   1366 Incorrect integer value: '<uuid>' for column 'auditable_id'
 *
 * and because the audit write happens inside the model's own save, the record
 * itself failed to save too. LibraryFile (HasUuids) hit exactly this. The
 * requirement is that everything is stored, so the column widens to hold both
 * shapes rather than the one model being left out.
 *
 * VARCHAR(64) covers a UUID (36), a ULID (26) and any integer id. Verified by
 * EXPLAIN that the (auditable_type, auditable_id, created_at) index is still
 * used when the id is compared as an integer: auditable_type leads the index
 * and anchors the lookup, so the usual "string column vs numeric literal
 * defeats the index" trap does not apply to how this table is actually queried.
 *
 * audits_archive gets the same treatment -- rows are copied between the two
 * verbatim, so a type mismatch would break archiving the moment a UUID-keyed
 * record aged out.
 */
return new class extends Migration
{
    private const TABLES = ['audits', 'audits_archive'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `auditable_id` VARCHAR(64) NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Anything non-numeric cannot survive the narrowing -- it would
            // silently become 0 and point every one of those audit rows at the
            // same imaginary record. Drop them instead, which is honest about
            // what a rollback costs.
            DB::table($table)->whereRaw("`auditable_id` NOT REGEXP '^[0-9]+$'")->delete();

            DB::statement("ALTER TABLE `{$table}` MODIFY `auditable_id` BIGINT UNSIGNED NOT NULL");
        }
    }
};
