<?php
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

$user = User::where('first_name', 'like', '%dinesh%')->where('last_name', 'like', '%maurya%')->first();
$attendances = Attendance::where('user_id', $user->id)->orderBy('check_in_time', 'desc')->take(7)->get();
echo "Attendance records for Dinesh Maurya (Live Server):\n";
foreach ($attendances as $a) {
    echo "Date: " . ($a->check_in_time ? $a->check_in_time->toDateString() : 'N/A') . " | In: " . $a->check_in_time->toTimeString() . " | Out: " . $a->check_out_time->toTimeString() . " | Status: " . $a->status . "\n";
}
