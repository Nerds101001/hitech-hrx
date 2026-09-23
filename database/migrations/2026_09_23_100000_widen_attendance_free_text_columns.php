<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * attendances.notes was varchar(255) while the form behind it invites a
 * description of the whole working day. On 2026-09-18 a regularisation was
 * attempted fourteen times and failed every time with
 *
 *   SQLSTATE[22001]: Data too long for column 'notes' at row 1
 *
 * because MySQL rejects rather than truncates in strict mode. Nothing was
 * saved, and the employee got a 500 with no indication of the real cause.
 * The longest note that did fit was 244 characters -- the ceiling was being
 * grazed constantly, so this was a matter of time.
 *
 * admin_reason on the same table is already TEXT, which is what the other
 * free-text columns should have been from the start. late_reason and
 * early_checkout_reason are the same kind of field typed by the same people
 * through the same forms, so they are widened together rather than waiting
 * for each to produce its own incident.
 *
 * varchar(255) -> TEXT is an in-place change for InnoDB and the table is
 * small (~16k rows); no data can be lost widening a column.
 */
return new class extends Migration
{
    /** Columns that hold something a human typed, and their old width. */
    private const COLUMNS = [
        'notes',
        'late_reason',
        'early_checkout_reason',
    ];

    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->text($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Truncate anything that would not survive the narrowing, otherwise
        // the rollback itself fails with the very error this migration fixes.
        foreach (self::COLUMNS as $column) {
            \Illuminate\Support\Facades\DB::table('attendances')
                ->whereRaw("CHAR_LENGTH({$column}) > 255")
                ->update([$column => \Illuminate\Support\Facades\DB::raw("LEFT({$column}, 255)")]);
        }

        Schema::table('attendances', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column, 255)->nullable()->change();
            }
        });
    }
};
