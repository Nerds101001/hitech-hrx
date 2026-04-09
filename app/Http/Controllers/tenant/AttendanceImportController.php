<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\User;

class AttendanceImportController extends Controller
{
    public function showBiometricImport()
    {
        return view('tenant.attendance.import_biometric', [
            'pageConfigs' => ['contentLayout' => 'wide']
        ]);
    }

    public function previewBiometricImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $path = $request->file('file')->getRealPath();
        $data = Excel::toArray(new \stdClass(), $request->file('file'))[0];
        
        $previewData = [];
        $currentBiometricId = null;

        // Detection Logic
        $isReportFormat = false;
        foreach (array_slice($data, 0, 15) as $row) {
            if (isset($row[0]) && str_contains($row[0], 'Code & Name')) {
                $isReportFormat = true;
                break;
            }
        }

        if ($isReportFormat) {
            // Smart Parser for DLF Manesar style reports
            foreach ($data as $row) {
                $firstCol = $row[0] ?? '';
                // Extract Biometric ID from header row
                if (str_contains($firstCol, 'Code & Name')) {
                    if (preg_match('/\d+/', $firstCol, $matches)) {
                        $currentBiometricId = $matches[0];
                    }
                    continue;
                }

                // Match attendance row (01/03/2026)
                $dateStr = $row[1] ?? '';
                if ($currentBiometricId && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateStr)) {
                    $in = $this->excelTimeToHi($row[4] ?? null);
                    $out = $this->excelTimeToHi($row[7] ?? null);
                    
                    if ($in || $out) {
                        // Priority 1: Match by biometric_id
                        $user = User::where('biometric_id', $currentBiometricId)->first();
                        
                        // Priority 2: Fallback to code
                        if (!$user) {
                            $user = User::where('code', $currentBiometricId)->first();
                        }

                        $previewData[] = [
                            'biometric_id' => $currentBiometricId,
                            'employee_name' => $user ? $user->getFullName() : '<span class="text-danger">Not Found</span>',
                            'user_id' => $user ? $user->id : null,
                            'date' => $dateStr,
                            'check_in' => $in ?? '--',
                            'check_out' => $out ?? '--',
                            'status' => $user ? 'Ready' : 'Error'
                        ];
                    }
                }
            }
        } else {
            // Standard Raw Parser
            $header = array_shift($data);
            foreach ($data as $row) {
                if (empty($row[0])) continue;
                $biometricId = $row[0];
                
                // Priority 1: Match by biometric_id
                $user = User::where('biometric_id', $biometricId)->first();
                
                // Priority 2: Fallback to code
                if (!$user) {
                    $user = User::where('code', $biometricId)->first();
                }
                
                $previewData[] = [
                    'biometric_id' => $biometricId,
                    'employee_name' => $user ? $user->getFullName() : '<span class="text-danger">Not Found</span>',
                    'user_id' => $user ? $user->id : null,
                    'date' => $row[1] ?? 'N/A',
                    'check_in' => $this->excelTimeToHi($row[2] ?? null) ?? ($row[2] ?? '--'),
                    'check_out' => $this->excelTimeToHi($row[3] ?? null) ?? ($row[3] ?? '--'),
                    'status' => $user ? 'Ready' : 'Error'
                ];
            }
        }

        if (request()->ajax()) {
            return response()->json([
                'html' => view('tenant.attendance.preview_table', ['previewData' => $previewData])->render(),
                'records' => $previewData
            ]);
        }

        return view('tenant.attendance.import_biometric_preview', [
            'previewData' => $previewData
        ]);
    }

    private function excelTimeToHi($val) {
        if (empty($val)) return null;
        if (is_numeric($val)) {
            $seconds = round($val * 86400);
            return gmdate("H:i", $seconds);
        }
        // Handle raw string like "09:00"
        if (preg_match('/^\d{1,2}:\d{2}/', $val)) {
            return Carbon::parse($val)->format('H:i');
        }
        return null;
    }

    public function storeBiometricImport(Request $request)
    {
        $records = json_decode($request->input('records'), true);
        $count = 0;

        foreach ($records as $record) {
            if (empty($record['user_id'])) continue;

            // Robust Date Parsing for Indian Standard (d/m/Y) or others
            $dateStr = $record['date'];
            try {
                if (str_contains($dateStr, '/')) {
                    $date = Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
                } else {
                    $date = Carbon::parse($dateStr)->format('Y-m-d');
                }
            } catch (\Exception $e) {
                try {
                    $date = Carbon::parse($dateStr)->format('Y-m-d');
                } catch (\Exception $e2) {
                    continue; // Skip invalid date
                }
            }

            $checkIn = !empty($record['check_in']) && $record['check_in'] !== '--' ? Carbon::parse($date . ' ' . $record['check_in']) : null;
            $checkOut = !empty($record['check_out']) && $record['check_out'] !== '--' ? Carbon::parse($date . ' ' . $record['check_out']) : null;

            $user = \App\Models\User::find($record['user_id']);
            $attendanceService = new \App\Services\Api\Attendance\AttendanceService();

            // Centralized calculation logic (replaces 60+ individual lines)
            $calc = $attendanceService->calculateDayStatus($user, $date, $checkIn, $checkOut);

            // Correct Update/Create Logic: Match by User and Date, not exact timestamp
            $attendance = \App\Models\Attendance::where('user_id', $record['user_id'])
                ->whereDate('check_in_time', $date)
                ->first();

            if ($attendance) {
                $attendance->update([
                    'check_in_time' => $checkIn ?: $attendance->check_in_time,
                    'check_out_time' => $checkOut ?: $attendance->check_out_time,
                    'status' => $calc['status'],
                    'tenant_id' => auth()->user()->tenant_id,
                    'is_policy_late' => $calc['is_policy_late'] ?? false,
                ]);
            } else {
                $attendance = \App\Models\Attendance::create([
                    'user_id' => $record['user_id'],
                    'check_in_time' => $checkIn ?: \Carbon\Carbon::parse($date)->startOfDay(),
                    'check_out_time' => $checkOut,
                    'status' => $calc['status'],
                    'tenant_id' => auth()->user()->tenant_id,
                    'is_policy_late' => $calc['is_policy_late'] ?? false,
                ]);
            }

            if ($checkIn) {
                \App\Models\AttendanceLog::updateOrCreate(
                    ['attendance_id' => $attendance->id, 'type' => 'check_in'],
                    ['created_at' => $checkIn, 'tenant_id' => auth()->user()->tenant_id]
                );
            }

            if ($checkOut) {
                \App\Models\AttendanceLog::updateOrCreate(
                    ['attendance_id' => $attendance->id, 'type' => 'check_out'],
                    ['created_at' => $checkOut, 'tenant_id' => auth()->user()->tenant_id]
                );
            }
            $count++;
        }

        return redirect()->route('attendance.index')->with('success', "$count Attendance records synced successfully.");
    }

    public function downloadSample()
    {
        $filename = "attendance_import_sample.csv";
        $header = ['BiometricID_or_EmpCode', 'Date', 'CheckIn', 'CheckOut'];
        $rows = [
            ['6303', '01/03/2026', '09:00', '18:00'],
            ['EMP-001', '01/03/2026', '09:15', '18:45'],
            ['8195', '01/03/2026', '08:50', '17:30'],
        ];

        return response()->streamDownload(function () use ($header, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $header);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
