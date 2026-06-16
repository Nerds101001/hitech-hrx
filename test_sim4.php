<?php
use App\Models\User;
use Carbon\Carbon;
use App\Services\Api\Attendance\AttendanceService;

$user = User::whereNotNull('shift_id')->first();
if (!$user) { echo "No user with shift found.\n"; exit; }
$service = new AttendanceService();

$dateStr = '2026-06-11';
$checkIn = Carbon::parse('2026-06-11 11:30:00');
$checkOut = Carbon::parse('2026-06-11 20:30:00');

$calc3 = $service->calculateDayStatus($user, $dateStr, $checkIn, $checkOut);
echo "User: " . $user->first_name . " Shift ID: " . $user->shift_id . "\n";
echo "Status: " . $calc3['status'] . "\n";
