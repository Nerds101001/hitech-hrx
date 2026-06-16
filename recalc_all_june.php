<?php
use App\Models\Attendance;
use App\Models\User;
use App\Services\Api\Attendance\AttendanceService;

$service = new AttendanceService();

// Get all attendance records from June 1st onwards
$attendances = Attendance::where('check_in_time', '>=', '2026-06-01 00:00:00')->get();

$fixedCount = 0;
foreach ($attendances as $a) {
    if ($a->check_in_time && $a->check_out_time) {
        $user = User::find($a->user_id);
        if ($user) {
            $calc = $service->calculateDayStatus($user, $a->check_in_time->toDateString(), $a->check_in_time, $a->check_out_time);
            
            // Only save if the status or is_policy_late changed
            if ($a->status !== $calc['status'] || $a->is_policy_late !== ($calc['is_policy_late'] ?? false)) {
                $a->status = $calc['status'];
                $a->is_policy_late = $calc['is_policy_late'] ?? false;
                $a->save();
                $fixedCount++;
            }
        }
    }
}

echo "Successfully recalculated attendance for June. Total records updated to new rules: " . $fixedCount . "\n";
