<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Short leaves are meant to be auto-approved (one per month). Older
     * requests were saved as pending before that rule existed, so approve
     * the first pending short leave of each user-month, leaving any further
     * ones in the same month pending for manual review.
     */
    public function up(): void
    {
        $pending = DB::table('leave_requests as lr')
            ->join('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
            ->where('lt.is_short_leave', 1)
            ->where('lr.status', 'pending')
            ->orderBy('lr.user_id')
            ->orderBy('lr.from_date')
            ->orderBy('lr.id')
            ->select('lr.id', 'lr.user_id', 'lr.from_date')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        // user|month combinations that already have an approved short leave
        $taken = DB::table('leave_requests as lr')
            ->join('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
            ->where('lt.is_short_leave', 1)
            ->where('lr.status', 'approved')
            ->selectRaw("CONCAT(lr.user_id, '|', DATE_FORMAT(lr.from_date, '%Y-%m')) as k")
            ->pluck('k')
            ->flip()
            ->toArray();

        foreach ($pending as $req) {
            $key = $req->user_id . '|' . substr($req->from_date, 0, 7);
            if (isset($taken[$key])) {
                continue;
            }

            DB::table('leave_requests')->where('id', $req->id)->update([
                'status'         => 'approved',
                'is_short_leave' => 1,
                'approved_at'    => now(),
                'approval_notes' => 'System auto-approved first monthly short leave (backfill)',
                'updated_at'     => now(),
            ]);
            $taken[$key] = true;
        }
    }

    public function down(): void
    {
        // Data backfill; not reversible.
    }
};
