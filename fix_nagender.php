<?php
use App\Models\User;
use App\Models\Attendance;
use App\Services\Api\Attendance\AttendanceService;

$user = User::where('first_name', 'like', '%nagender%')->first();
$attendances = Attendance::where('user_id', $user->id)->whereBetween('check_in_time', ['2026-06-01', '2026-06-06'])->get();
$service = new AttendanceService();

foreach ($attendances as $a) {
    if ($a->check_in_time && $a->check_out_time) {
        $calc = $service->calculateDayStatus($user, $a->check_in_time->toDateString(), $a->check_in_time, $a->check_out_time);
        $a->status = $calc['status'];
        $a->is_policy_late = $calc['is_policy_late'] ?? false;
        $a->save();
        echo "Updated Date: " . $a->check_in_time->toDateString() . " Status: " . $a->status . "\n";
    }
}
echo "Done recalculating for Nagender.\n";
