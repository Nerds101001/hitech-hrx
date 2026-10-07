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
        /* A size cap matters here beyond tidiness: the spreadsheet reader below
           expands the file in memory, and PhpSpreadsheet has open advisories for
           memory exhaustion via crafted XLS/Gnumeric input. `mimes` only checks
           the extension, so the cap is the real guard until the library is
           patched. 10 MB matches PipelineImportController. */
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
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

        // Preload users into memory (terminated staff excluded — they must
        // not receive imported attendance)
        $biometricUsers = \App\Models\User::notTerminated()->whereNotNull('biometric_id')->get();
        $usersByBiometric = $biometricUsers->keyBy('biometric_id');
        $usersByCode = \App\Models\User::notTerminated()->whereNotNull('code')->get()->keyBy('code');
        // Machine exports zero-pad the code (e.g. "0170"), but some users'
        // biometric_id is stored without the padding ("170") — an exact
        // string match then silently drops every one of their real punches
        // into "no matching employee". Match on the un-padded form too.
        $usersByBiometricNormalized = $biometricUsers->keyBy(fn($u) => ltrim($u->biometric_id, '0') ?: '0');
        $matchBiometric = function ($code) use ($usersByBiometric, $usersByBiometricNormalized) {
            return $usersByBiometric->get($code) ?? $usersByBiometricNormalized->get(ltrim($code, '0') ?: '0');
        };

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
                        $user = $matchBiometric($currentBiometricId) ?? $usersByCode->get($currentBiometricId);

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
                
                $user = $matchBiometric($biometricId) ?? $usersByCode->get($biometricId);
                
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

        $cacheKey = 'biometric_import_' . auth()->id();
        \Illuminate\Support\Facades\Cache::put($cacheKey, $previewData, now()->addHours(1));

        if (request()->ajax()) {
            return response()->json([
                'html' => view('tenant.attendance.preview_table', ['previewData' => $previewData])->render(),
                'records' => []
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
        // Large imports on shared hosting were being cut short by execution
        // limits and, more often, a transient DB disconnect mid-loop which
        // (with no per-row guard) aborted every remaining row silently.
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $records = \Illuminate\Support\Facades\Cache::get('biometric_import_' . auth()->id());
        if (!$records) {
            return redirect()->back()->with('error', 'Import session expired or invalid. Please upload the file again.');
        }
        \Illuminate\Support\Facades\Cache::forget('biometric_import_' . auth()->id());

        // Preload User Data
        $userIds = collect($records)->pluck('user_id')->filter()->unique();
        $usersById = \App\Models\User::with(['department', 'designation', 'shift'])->whereIn('id', $userIds)->get()->keyBy('id');
        // role='ccare' only — a 'newbiz' mapping doesn't mean CCare marks this
        // person's attendance manually, same distinction CcAttendanceController
        // makes everywhere (see its own role='ccare' filters).
        $salesUserIds = \App\Models\CcSalespersonMap::where('role', 'ccare')->whereIn('sales_user_id', $userIds)->pluck('sales_user_id')->toArray();

        // Parse dates safely for preloading attendances
        $dates = collect($records)->pluck('date')->map(function($dateStr) {
            try {
                if (str_contains($dateStr, '/')) {
                    return Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
                }
                return Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        })->filter()->unique()->toArray();

        $existingAttendancesGroup = empty($userIds) || empty($dates) ? collect() : \App\Models\Attendance::whereIn('user_id', $userIds)
            ->where(function($q) use ($dates) {
                foreach($dates as $d) {
                    $q->orWhereDate('check_in_time', $d)
                      ->orWhere(function($q2) use ($d) {
                          $q2->whereNull('check_in_time')->whereDate('created_at', $d);
                      });
                }
            })->get()->groupBy(function($att) {
                return $att->user_id . '_' . ($att->check_in_time ? $att->check_in_time->format('Y-m-d') : $att->created_at->format('Y-m-d'));
            });

        $attendanceService = new \App\Services\Api\Attendance\AttendanceService();

        $created = 0; $updated = 0; $skippedSales = 0; $skippedLeave = 0; $notFound = 0;
        $failed = [];

        foreach ($records as $record) {
            // Each row is isolated: a bad row or a transient DB drop must not
            // abort the rest of the import. Transient DB errors are retried
            // once after reconnecting.
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $result = $this->syncOneBiometric($record, $usersById, $salesUserIds, $existingAttendancesGroup, $attendanceService);
                    switch ($result) {
                        case 'created': $created++; break;
                        case 'updated': $updated++; break;
                        case 'skipped_sales': $skippedSales++; break;
                        case 'skipped_leave': $skippedLeave++; break;
                        case 'not_found': $notFound++; break;
                    }
                    break; // success, stop retrying
                } catch (\Illuminate\Database\QueryException $e) {
                    $transient = in_array((string)$e->getCode(), ['2002', '2006', 'HY000', '1205', '1213', '40001'], true);
                    if ($attempt === 1 && $transient) {
                        try { \Illuminate\Support\Facades\DB::reconnect(); } catch (\Throwable $ignore) {}
                        usleep(250000); // 0.25s backoff, then retry this row
                        continue;
                    }
                    $failed[] = ($record['biometric_id'] ?? '?') . ' / ' . ($record['date'] ?? '?') . ' — ' . substr($e->getMessage(), 0, 100);
                    break;
                } catch (\Throwable $e) {
                    $failed[] = ($record['biometric_id'] ?? '?') . ' / ' . ($record['date'] ?? '?') . ' — ' . substr($e->getMessage(), 0, 100);
                    break;
                }
            }
        }

        $synced = $created + $updated;
        $msg = "Import finished: {$synced} records synced ({$created} new, {$updated} updated).";
        if ($skippedSales) $msg .= " Skipped {$skippedSales} sales (marked by CCare).";
        if ($skippedLeave) $msg .= " Skipped {$skippedLeave} on approved leave.";
        if ($notFound) $msg .= " {$notFound} rows had no matching employee.";

        if (!empty($failed)) {
            \Illuminate\Support\Facades\Log::warning('Biometric Import failures (' . count($failed) . '): ' . implode(' | ', array_slice($failed, 0, 50)));
            $msg .= " " . count($failed) . " rows FAILED and were NOT imported — re-upload the file to retry only those.";
            return redirect()->route('attendance.index')
                ->with('warning', $msg)
                ->with('import_failures', array_slice($failed, 0, 100));
        }

        return redirect()->route('attendance.index')->with('success', $msg);
    }

    /**
     * Sync a single biometric preview record. Returns a result code:
     * created | updated | skipped_sales | skipped_leave | not_found.
     * Throws on DB errors so the caller can retry/report per-row.
     */
    private function syncOneBiometric(array $record, $usersById, array $salesUserIds, $existingAttendancesGroup, $attendanceService): string
    {
        if (empty($record['user_id'])) return 'not_found';

        // Robust Date Parsing for Indian Standard (d/m/Y) or others
        $dateStr = $record['date'];
        try {
            $date = str_contains($dateStr, '/')
                ? Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d')
                : Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            $date = Carbon::parse($dateStr)->format('Y-m-d'); // final attempt (throws -> caught by caller)
        }

        $checkIn = !empty($record['check_in']) && $record['check_in'] !== '--' ? Carbon::parse($date . ' ' . $record['check_in']) : null;
        $checkOut = !empty($record['check_out']) && $record['check_out'] !== '--' ? Carbon::parse($date . ' ' . $record['check_out']) : null;

        $user = $usersById->get($record['user_id']);
        if (!$user) return 'not_found';

        // Reports sometimes list a day's punches evening-first: store them in order.
        [$checkIn, $checkOut] = \App\Services\AttendanceDayStatus::orderPunches($checkIn, $checkOut, $user->shift);

        // 1. Skip only someone actually CCare-managed (cc_salesperson_map,
        // role='ccare') — being in a department/designation whose NAME
        // happens to contain "Sales" is not the same thing, and wrongly
        // skipped real biometric punches for people never in that map at
        // all (e.g. Piyush Sardana, Tanisha Walia — both "Sales Department"
        // by name, neither CCare-managed).
        if (in_array($user->id, $salesUserIds)) return 'skipped_sales';

        $tenantId = auth()->user()->tenant_id;

        $attendanceGroup = $existingAttendancesGroup->get($user->id . '_' . $date);
        $attendance = $attendanceGroup ? $attendanceGroup->first() : null;

        // 2. Do not override approved leaves; only attach punch times to them
        $leaveStatuses = ['leave', 'paid_leave', 'unpaid_leave', 'on_leave', 'half-day'];
        if ($attendance && in_array(strtolower(str_replace('_', '-', $attendance->status)), $leaveStatuses)) {
            if ($checkIn || $checkOut) {
                $attendance->update([
                    'check_in_time' => $checkIn ?: $attendance->check_in_time,
                    'check_out_time' => $checkOut ?: $attendance->check_out_time,
                ]);
                if ($checkIn) {
                    \App\Models\AttendanceLog::updateOrCreate(
                        ['attendance_id' => $attendance->id, 'type' => 'check_in'],
                        ['created_at' => $checkIn, 'tenant_id' => $tenantId]
                    );
                }
                if ($checkOut) {
                    \App\Models\AttendanceLog::updateOrCreate(
                        ['attendance_id' => $attendance->id, 'type' => 'check_out'],
                        ['created_at' => $checkOut, 'tenant_id' => $tenantId]
                    );
                }
                return 'updated';
            }
            return 'skipped_leave';
        }

        $calc = $attendanceService->calculateDayStatus($user, $date, $checkIn, $checkOut);
        $safeCheckIn = $checkIn ?: Carbon::parse($date)->startOfDay();

        if ($attendance) {
            $attendance->update([
                'check_in_time' => $safeCheckIn,
                'check_out_time' => $checkOut ?: $attendance->check_out_time,
                'status' => $calc['status'],
                'tenant_id' => $tenantId,
                'is_policy_late' => $calc['is_policy_late'] ?? false,
                'leave_request_id' => $calc['leave_request_id'] ?? null,
            ]);
            $wasCreated = false;
        } else {
            $attendance = \App\Models\Attendance::create([
                'user_id' => $record['user_id'],
                'check_in_time' => $safeCheckIn,
                'check_out_time' => $checkOut,
                'status' => $calc['status'],
                'tenant_id' => $tenantId,
                'is_policy_late' => $calc['is_policy_late'] ?? false,
                'leave_request_id' => $calc['leave_request_id'] ?? null,
                'created_at' => Carbon::parse($date)->startOfDay()
            ]);
            // Track in the in-memory group so a duplicate row in the same file
            // updates instead of creating a second record.
            $existingAttendancesGroup->put($user->id . '_' . $date, collect([$attendance]));
            $wasCreated = true;
        }

        if ($checkIn) {
            \App\Models\AttendanceLog::updateOrCreate(
                ['attendance_id' => $attendance->id, 'type' => 'check_in'],
                ['created_at' => $checkIn, 'tenant_id' => $tenantId]
            );
        }
        if ($checkOut) {
            \App\Models\AttendanceLog::updateOrCreate(
                ['attendance_id' => $attendance->id, 'type' => 'check_out'],
                ['created_at' => $checkOut, 'tenant_id' => $tenantId]
            );
        }

        return $wasCreated ? 'created' : 'updated';
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
