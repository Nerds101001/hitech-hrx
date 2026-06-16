<?php
use App\Models\User;
use App\Models\Attendance;
$user = User::where('first_name', 'like', '%Nagender%')->orWhere('last_name', 'like', '%Nagender%')->first();
$attendances = Attendance::where('user_id', $user->id)->whereBetween('check_in_time', ['2026-06-01', '2026-06-05 23:59:59'])->get();
foreach ($attendances as $a) {
    echo "Date: " . $a->check_in_time->toDateString() . " Status: " . $a->status . " AdminReason: " . ($a->admin_reason ?: 'None') . "\n";
}
