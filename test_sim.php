<?php
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Services\Api\Attendance\AttendanceService;

$user = User::where('first_name', 'like', '%dinesh%')->first();
$service = new AttendanceService();

// Simulate a missed check-in: $checkIn is null, $checkOut is at 18:00
$calc = $service->calculateDayStatus($user, '2026-06-09', null, Carbon::parse('2026-06-09 18:00:00'));
echo "Simulate Late Punch Auto-Detect (CheckIn=null, CheckOut=18:00):\n";
echo "Status: " . $calc['status'] . "\n";

// Simulate normal Full Day: 09:00 to 18:00
$calc2 = $service->calculateDayStatus($user, '2026-06-10', Carbon::parse('2026-06-10 09:00:00'), Carbon::parse('2026-06-10 18:00:00'));
echo "\nSimulate Normal Full Day (In=09:00, Out=18:00):\n";
echo "Status: " . $calc2['status'] . "\n";

// Simulate 11:00 AM rule: 11:30 to 20:30 (9 hours total)
$calc3 = $service->calculateDayStatus($user, '2026-06-11', Carbon::parse('2026-06-11 11:30:00'), Carbon::parse('2026-06-11 20:30:00'));
echo "\nSimulate 11:00 AM Penalty (In=11:30, Out=20:30):\n";
echo "Status: " . $calc3['status'] . "\n";
