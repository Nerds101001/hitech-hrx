<?php
$service = app(\App\Services\Api\Attendance\AttendanceService::class);
$attendances = \App\Models\Attendance::with('user')
    ->whereNotNull('check_in_time')
    ->whereNotNull('check_out_time')
    ->get();
$count = 0;
foreach ($attendances as $a) {
    if(!$a->user) continue;
    $calc = $service->calculateDayStatus($a->user, $a->check_in_time, $a->check_in_time, $a->check_out_time);
    if ($a->status !== $calc['status']) {
        $a->status = $calc['status'];
        $a->save();
        $count++;
    }
}
echo 'Fixed ' . $count . ' records.';
