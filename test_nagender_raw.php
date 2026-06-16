<?php
use App\Models\User;
use App\Models\AttendanceLog;
use Carbon\Carbon;

$user = User::where('first_name', 'like', '%Nagender%')->orWhere('last_name', 'like', '%Nagender%')->first();

$logs = AttendanceLog::where('user_id', $user->id)
    ->whereBetween('timestamp', ['2026-06-01 00:00:00', '2026-06-05 23:59:59'])
    ->get();

echo "Raw Attendance Logs for Nagender June 1-5:\n";
foreach ($logs as $l) {
    echo "ID: {$l->id}, Time: {$l->timestamp}, Type: {$l->type->name}, Source: {$l->source->name}\n";
}
if ($logs->isEmpty()) echo "No raw logs found.\n";
