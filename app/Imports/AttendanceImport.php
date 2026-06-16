<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;

class AttendanceImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $user = User::where('code', $row['employee_code'])->first();
            
            if (!$user) {
                Log::warning("Attendance Import: User not found with code " . $row['employee_code']);
                continue;
            }

            $date = Carbon::parse($row['date'])->format('Y-m-d');
            $checkIn = $row['check_in'] ? Carbon::parse($date . ' ' . $row['check_in']) : null;
            $checkOut = $row['check_out'] ? Carbon::parse($date . ' ' . $row['check_out']) : null;
            $status = $row['status'] ?? 'Present';

            // ----------------------------------------------------
            // 1. Skip Salesperson (they are marked manually by CCARE)
            // ----------------------------------------------------
            $isSales = false;
            if ($user->department && stripos($user->department->name, 'Sales') !== false) {
                $isSales = true;
            }
            if ($user->designation && stripos($user->designation->name, 'Sales') !== false) {
                $isSales = true;
            }
            if (\App\Models\CcSalespersonMap::where('sales_user_id', $user->id)->exists()) {
                $isSales = true;
            }

            if ($isSales) {
                Log::info("Attendance Import: Skipped Salesperson " . $user->code);
                continue;
            }

            // ----------------------------------------------------
            // 2. Check if attendance already exists for this user and date
            // ----------------------------------------------------
            $attendance = Attendance::where('user_id', $user->id)
                ->where(function ($query) use ($date) {
                    $query->whereDate('check_in_time', $date)
                          ->orWhere(function ($q2) use ($date) {
                              $q2->whereNull('check_in_time')
                                 ->whereDate('created_at', $date);
                          });
                })->first();

            // ----------------------------------------------------
            // 3. Prevent overriding approved leaves
            // ----------------------------------------------------
            $leaveStatuses = ['leave', 'paid_leave', 'unpaid_leave', 'on_leave', 'half-day'];
            if ($attendance && in_array(strtolower(str_replace('_', '-', $attendance->status)), $leaveStatuses)) {
                Log::info("Attendance Import: Skipped {$user->code} for $date because of existing leave: {$attendance->status}");
                continue;
            }

            // Ensure check_in_time is never null to prevent future duplicate bugs
            $safeCheckIn = $checkIn ?: Carbon::parse($date)->startOfDay();

            if (!$attendance) {
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'check_in_time' => $safeCheckIn,
                    'check_out_time' => $checkOut,
                    'status' => $status,
                    'shift_id' => $user->shift_id,
                    'tenant_id' => $user->tenant_id,
                    'created_at' => Carbon::parse($date)->startOfDay() // Force created_at to the attendance date for legacy support
                ]);
            } else {
                $attendance->update([
                    'check_in_time' => $safeCheckIn,
                    'check_out_time' => $checkOut,
                    'status' => $status,
                ]);
            }

            // Create Logs if they don't exist
            if ($checkIn) {
                AttendanceLog::updateOrCreate(
                    [
                        'attendance_id' => $attendance->id,
                        'type' => 'check_in',
                    ],
                    [
                        'tenant_id' => $user->tenant_id,
                        'created_at' => $checkIn,
                    ]
                );
            }

            if ($checkOut) {
                AttendanceLog::updateOrCreate(
                    [
                        'attendance_id' => $attendance->id,
                        'type' => 'check_out',
                    ],
                    [
                        'tenant_id' => $user->tenant_id,
                        'created_at' => $checkOut,
                    ]
                );
            }
        }
    }
}
