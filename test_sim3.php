<?php
use App\Models\User;
use Carbon\Carbon;
use App\Services\Api\Attendance\AttendanceService;

$user = User::where('first_name', 'like', '%dinesh%')->first();
$service = new AttendanceService();

$dateStr = '2026-06-11';
$checkIn = Carbon::parse('2026-06-11 11:30:00');
$checkOut = Carbon::parse('2026-06-11 20:30:00');

$isWorkingDay = \App\Services\LeavePolicyService::isWorkingDay($user, Carbon::parse($dateStr));
echo "Is Working Day? " . ($isWorkingDay ? 'Yes' : 'No') . "\n";
echo "Shift ID: " . $user->shift_id . "\n";

$calc3 = $service->calculateDayStatus($user, $dateStr, $checkIn, $checkOut);
echo "Status: " . $calc3['status'] . "\n";
