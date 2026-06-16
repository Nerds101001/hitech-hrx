<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

use App\Models\User;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use Carbon\Carbon;

\ = User::where('first_name', 'like', '%Nagender%')->orWhere('last_name', 'like', '%Nagender%')->first();

if (!\) {
    echo "User Nagender not found.\n";
    exit;
}

echo "User found: " . \->first_name . " " . \->last_name . " (ID: " . \->id . ")\n";
echo "Shift ID: " . \->shift_id . "\n";
echo "Leave Policy Profile ID: " . \->leave_policy_profile_id . "\n\n";

for (\ = 1; \ <= 5; \++) {
    \ = Carbon::create(2026, 6, \);
    \ = \->toDateString();
    echo "Date: " . \ . " (" . \->format('l') . ")\n";
    
    // Check Working Day
    \ = \App\Services\LeavePolicyService::isWorkingDay(\, \);
    echo "Is Working Day: " . (\ ? "Yes" : "No") . "\n";
    
    // Check Attendance
    \ = Attendance::where('user_id', \->id)->whereDate('check_in_time', \)->first();
    if (\) {
        echo "Attendance: ID " . \->id . ", Status: " . \->status . ", In: " . \->check_in_time . ", Out: " . \->check_out_time . "\n";
    } else {
        echo "Attendance: No record found.\n";
    }
    
    // Check Leave
    \ = LeaveRequest::where('user_id', \->id)
        ->where('from_date', '<=', \)
        ->where('to_date', '>=', \)
        ->where('status', \App\Enums\LeaveRequestStatus::APPROVED)
        ->first();
    if (\) {
        echo "Leave: Approved leave found.\n";
    } else {
        echo "Leave: No approved leave found.\n";
    }
    
    // Check Holiday
    \ = \App\Models\Holiday::where('date', \)->first();
    if (\) {
        echo "Holiday: " . \->name . "\n";
    } else {
        echo "Holiday: No\n";
    }
    
    echo "--------------------------\n";
}
