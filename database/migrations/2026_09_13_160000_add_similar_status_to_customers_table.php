<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the old "none" bucket in two: a Setu customer that's only
 * FUZZY-close (no exact normalized-name match) is now its own "similar"
 * status, distinct from genuinely "none" (nothing close at all). Laravel's
 * schema builder has no portable "modify an enum's value list" operation, so
 * this goes straight to SQL -- MySQL only, matching every other enum column
 * in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customers MODIFY setu_match_status ENUM('none', 'similar', 'suggested', 'verified') NOT NULL DEFAULT 'none'");
    }

    public function down(): void
    {
        // Collapse 'similar' back into 'none' before narrowing the enum, or
        // the ALTER would silently truncate those rows' value.
        DB::statement("UPDATE customers SET setu_match_status = 'none' WHERE setu_match_status = 'similar'");
        DB::statement("ALTER TABLE customers MODIFY setu_match_status ENUM('none', 'suggested', 'verified') NOT NULL DEFAULT 'none'");
    }
};
