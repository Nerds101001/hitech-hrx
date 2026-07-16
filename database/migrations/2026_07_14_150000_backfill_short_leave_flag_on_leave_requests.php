<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Older short-leave requests were saved without the is_short_leave flag
     * (it was only set by the auto-approval flow, and the tenant scope bug
     * could hide the leave type at application time). Backfill the flag from
     * the leave type so history/attendance pages classify them correctly.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE leave_requests lr
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            SET lr.is_short_leave = 1
            WHERE lt.is_short_leave = 1
              AND (lr.is_short_leave = 0 OR lr.is_short_leave IS NULL)
        ");
    }

    public function down(): void
    {
        // Data backfill; not reversible.
    }
};
