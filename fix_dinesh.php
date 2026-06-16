<?php
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

$user = User::where('first_name', 'like', '%dinesh%')->where('last_name', 'like', '%maurya%')->first();
if (!$user) {
    echo "Dinesh Maurya not found.\n";
    exit;
}

$attendances = Attendance::where('user_id', $user->id)->get();
$fixedCount = 0;

foreach ($attendances as $a) {
    if ($a->check_in_time && $a->check_out_time) {
        if ($a->check_in_time->gt($a->check_out_time)) {
            // Swap times
            $temp = $a->check_in_time;
            $a->check_in_time = $a->check_out_time;
            $a->check_out_time = $temp;
            
            // Recalculate status using Service
            $service = new \App\Services\Api\Attendance\AttendanceService();
            $calc = $service->calculateDayStatus($user, $a->check_in_time->toDateString(), $a->check_in_time, $a->check_out_time);
            $a->status = $calc['status'];
            $a->is_policy_late = $calc['is_policy_late'] ?? false;
            
            $a->save();
            $fixedCount++;
            echo "Fixed Date: " . $a->check_in_time->toDateString() . " In: " . $a->check_in_time->toTimeString() . " Out: " . $a->check_out_time->toTimeString() . "\n";
        }
    }
}
echo "Total records fixed: $fixedCount\n";
