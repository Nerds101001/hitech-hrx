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

            // Check if attendance already exists for this user and date
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('created_at', $date)
                ->first();

            if (!$attendance) {
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'check_in_time' => $checkIn,
                    'check_out_time' => $checkOut,
                    'status' => $status,
                    'shift_id' => $user->shift_id,
                    'tenant_id' => $user->tenant_id,
                    'created_at' => $date . ' 00:00:00', // Set the correct date
                ]);
            } else {
                $attendance->update([
                    'check_in_time' => $checkIn,
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
