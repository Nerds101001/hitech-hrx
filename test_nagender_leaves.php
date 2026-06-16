<?php
use App\Models\User;
use App\Models\LeaveRequest;

$user = User::where('first_name', 'like', '%Nagender%')->orWhere('last_name', 'like', '%Nagender%')->first();

$leaves = LeaveRequest::where('user_id', $user->id)->get();
echo "Leave Requests for Nagender:\n";
foreach ($leaves as $l) {
    echo "ID: {$l->id}, From: {$l->from_date}, To: {$l->to_date}, Status: {$l->status->name}\n";
}
