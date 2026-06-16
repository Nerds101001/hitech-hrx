<?php
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceLog;

$user = User::where('first_name', 'like', '%dinesh%')->where('last_name', 'like', '%maurya%')->first();
if (!$user) {
    echo "Dinesh Maurya not found.\n";
    exit;
}

echo "User: " . $user->getFullName() . " (ID: " . $user->id . ")\n";

$attendances = Attendance::where('user_id', $user->id)->orderBy('check_in_time', 'desc')->take(5)->get();

foreach ($attendances as $a) {
    echo "Attendance ID: {$a->id}\n";
    echo "  Date: " . ($a->check_in_time ? $a->check_in_time->toDateString() : 'N/A') . "\n";
    echo "  In: {$a->check_in_time}\n";
    echo "  Out: {$a->check_out_time}\n";
    
    $logs = AttendanceLog::where('attendance_id', $a->id)->orderBy('created_at', 'asc')->get();
    foreach ($logs as $l) {
        echo "    Log ID: {$l->id}, Type: {$l->type->name}, Time: {$l->created_at}\n";
    }
    echo "--------------------------\n";
}
