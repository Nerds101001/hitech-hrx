<?php
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

$user = User::where('first_name', 'like', '%Nagender%')->orWhere('last_name', 'like', '%Nagender%')->first();

if (!$user) {
    echo "User Nagender not found.\n";
    exit;
}

echo "User found: " . $user->first_name . " " . $user->last_name . " (ID: " . $user->id . ")\n";

for ($i = 1; $i <= 5; $i++) {
    $date = Carbon::create(2026, 6, $i);
    $dateStr = $date->toDateString();
    
    // Check Attendance
    $attendance = Attendance::where('user_id', $user->id)->whereDate('check_in_time', $dateStr)->first();
    if ($attendance) {
        echo "Attendance for {$dateStr}: ID " . $attendance->id . ", Status: " . $attendance->status . ", In: " . $attendance->check_in_time . ", Out: " . $attendance->check_out_time . "\n";
    } else {
        echo "Attendance for {$dateStr}: No record found.\n";
    }
}
