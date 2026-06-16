<?php
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

$user = User::where('first_name', 'like', '%nagender%')->first();
if (!$user) { echo "User not found.\n"; exit; }

$attendances = Attendance::where('user_id', $user->id)->whereBetween('check_in_time', ['2026-06-01', '2026-06-06'])->orderBy('check_in_time')->get();
echo "Nagender Attendances (June 1-5):\n";
foreach ($attendances as $a) {
    $in = $a->check_in_time ? $a->check_in_time->format('Y-m-d H:i:s') : 'N/A';
    $out = $a->check_out_time ? $a->check_out_time->format('Y-m-d H:i:s') : 'N/A';
    echo "Date: $in | Out: $out | Status: {$a->status} | Shift: {$a->shift_id}\n";
}
