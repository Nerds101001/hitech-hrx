<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CcSalespersonMap;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CcAttendanceController extends Controller
{
    /**
     * Display the Salesperson Attendance screen for CCARE
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Ensure only users mapped as CCARE (or Admin/HR) can access
        $isCCare = $user->department && stripos($user->department->name, 'Customer Care') !== false;
        if (!$isCCare && !$user->hasRole(['admin', 'hr'])) {
            return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized access.');
        }

        // Get the date (default to today)
        $date = $request->input('date', Carbon::today()->format('Y-m-d'));
        
        if ($user->hasRole(['admin', 'hr'])) {
            $mappings = \App\Models\CcSalespersonMap::with(['salesUser' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->with('designation');
            }, 'ccUser'])->get();
            $salespeople = $mappings->pluck('salesUser')->filter()->map(function($salesUser) use ($mappings) {
                $map = $mappings->firstWhere('sales_user_id', $salesUser->id);
                $salesUser->mapped_cc_name = $map && $map->ccUser ? $map->ccUser->name : 'N/A';
                return $salesUser;
            })->unique('id');
        } else {
            // Fetch salespeople tagged to this CCARE user
            $salespeople = $user->mappedSalespersons()->with(['salesUser' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->with('designation');
            }])->get()->pluck('salesUser')->filter();
        }

        // Fetch their attendance for the selected date
        $attendances = Attendance::whereIn('user_id', $salespeople->pluck('id'))
            ->whereDate('check_in_time', $date)
            ->get()
            ->keyBy('user_id');

        return view('tenant.cc_attendance.index', compact('salespeople', 'date', 'attendances', 'user'));
    }

    /**
     * Store/Update manual attendance by CCARE
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*' => 'in:Present,Absent,Work from home,Half day,Sunday working',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string'
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');
        
        // 3-day restriction
        $minDate = Carbon::today()->subDays(3)->format('Y-m-d');
        $maxDate = Carbon::today()->format('Y-m-d');
        if ($date < $minDate || $date > $maxDate) {
            return redirect()->back()->with('error', 'Attendance can only be marked for the past 3 days.');
        }

        $tenantId = Auth::user()->tenant_id;
        $authId = Auth::id();

        $userIds = array_keys(array_filter($request->attendance));
        $existingAttendances = empty($userIds) ? collect() : Attendance::whereIn('user_id', $userIds)
            ->whereDate('check_in_time', $date)
            ->get()
            ->keyBy('user_id');

        foreach ($request->attendance as $userId => $status) {
            if (empty($status)) continue;

            $attendance = $existingAttendances->get($userId);

            // If attendance is already marked and has a status, do not allow update
            if ($attendance && $attendance->status) {
                continue;
            }

            $dbStatus = $status;
            $note = $request->notes[$userId] ?? null;

            $compOffReason = $this->processCompOffLogic($userId, $tenantId, $dbStatus);
            [$dbStatus, $checkIn, $checkOut, $ccReason] = $this->alignWithDecisionMatrix($dbStatus, $date);
            $adminReason = trim(($compOffReason ? $compOffReason . ' | ' : '') . $ccReason) ?: null;

            if ($attendance) {
                $updateData = [
                    'status' => $dbStatus,
                    'notes' => $note,
                    'admin_reason' => $adminReason,
                    'updated_by_id' => $authId
                ];
                // Keep real biometric punches if the row already has them
                $hasRealPunch = $attendance->check_in_time && $attendance->check_in_time->format('H:i') !== '00:00';
                if (!$hasRealPunch) {
                    $updateData['check_in_time'] = $checkIn;
                    $updateData['check_out_time'] = $checkOut;
                }
                $attendance->update($updateData);
            } else {
                Attendance::create([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'check_in_time' => $checkIn,
                    'check_out_time' => $checkOut,
                    'status' => $dbStatus,
                    'notes' => $note,
                    'admin_reason' => $adminReason,
                    'created_by_id' => $authId
                ]);
            }
        }

        return redirect()->route('cc-attendance.index', ['date' => $date])
            ->with('success', 'Attendance marked successfully for the selected salespeople.');
    }

    /**
     * Display the Monthly Matrix Attendance screen for CCARE
     */
    public function monthly(Request $request)
    {
        $user = Auth::user();
        
        // Ensure only users mapped as CCARE (or Admin/HR) can access
        $isCCare = $user->department && stripos($user->department->name, 'Customer Care') !== false;
        if (!$isCCare && !$user->hasRole(['admin', 'hr'])) {
            return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized access.');
        }

        $month = $request->input('month', Carbon::today()->month);
        $year = $request->input('year', Carbon::today()->year);
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        
        if ($user->hasRole(['admin', 'hr'])) {
            $mappings = \App\Models\CcSalespersonMap::with(['salesUser' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->with('designation');
            }, 'ccUser'])->get();
            $salespeople = $mappings->pluck('salesUser')->filter()->map(function($salesUser) use ($mappings) {
                $map = $mappings->firstWhere('sales_user_id', $salesUser->id);
                $salesUser->mapped_cc_name = $map && $map->ccUser ? $map->ccUser->name : 'N/A';
                return $salesUser;
            })->unique('id');
        } else {
            $salespeople = $user->mappedSalespersons()->with(['salesUser' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->with('designation');
            }])->get()->pluck('salesUser')->filter();
        }

        // Fetch their attendance for the month
        $attendancesDb = Attendance::whereIn('user_id', $salespeople->pluck('id'))
            ->whereBetween('check_in_time', [$startOfMonth, $endOfMonth])
            ->get();

        // Organize attendances: $attendances[$userId][$dateStr] = $attendance
        $attendances = [];
        foreach ($attendancesDb as $att) {
            $d = $att->check_in_time->format('Y-m-d');
            $attendances[$att->user_id][$d] = $att;
        }

        return view('tenant.cc_attendance.monthly', compact('salespeople', 'month', 'year', 'daysInMonth', 'startOfMonth', 'attendances', 'user'));
    }

    /**
     * Store/Update manual attendance by CCARE from Monthly View
     */
    public function storeMonthly(Request $request)
    {
        $request->validate([
            'attendance' => 'required|array',
        ]);

        $tenantId = Auth::user()->tenant_id;
        $authId = Auth::id();
        $minDate = Carbon::today()->subDays(3)->format('Y-m-d');
        $maxDate = Carbon::today()->format('Y-m-d');
        
        $savedCount = 0;

        foreach ($request->attendance as $userId => $dates) {
            foreach ($dates as $dateStr => $status) {
                if (empty($status)) continue;
                
                // 3-day window validation
                if ($dateStr < $minDate || $dateStr > $maxDate) continue;
                
                // Allowed statuses
                if (!in_array($status, ['Present', 'Absent', 'Half day', 'Work from home', 'Sunday working'])) continue;

                $existing = Attendance::where('user_id', $userId)
                    ->whereDate('check_in_time', $dateStr)
                    ->first();

                // Prevent override if status is not empty
                if ($existing && $existing->status) {
                    continue;
                }

                $dbStatus = $status;
                $note = $request->notes[$userId][$dateStr] ?? null;

                $compOffReason = $this->processCompOffLogic($userId, $tenantId, $dbStatus);
                [$dbStatus, $checkIn, $checkOut, $ccReason] = $this->alignWithDecisionMatrix($dbStatus, $dateStr);
                $adminReason = trim(($compOffReason ? $compOffReason . ' | ' : '') . $ccReason) ?: null;

                if ($existing) {
                    $updateData = [
                        'status' => $dbStatus,
                        'notes' => $note,
                        'admin_reason' => $adminReason,
                        'updated_by_id' => $authId
                    ];
                    // Keep real biometric punches if the row already has them
                    $hasRealPunch = $existing->check_in_time && $existing->check_in_time->format('H:i') !== '00:00';
                    if (!$hasRealPunch) {
                        $updateData['check_in_time'] = $checkIn;
                        $updateData['check_out_time'] = $checkOut;
                    }
                    $existing->update($updateData);
                } else {
                    Attendance::create([
                        'user_id' => $userId,
                        'tenant_id' => $tenantId,
                        'check_in_time' => $checkIn,
                        'check_out_time' => $checkOut,
                        'status' => $dbStatus,
                        'notes' => $note,
                        'admin_reason' => $adminReason,
                        'created_by_id' => $authId
                    ]);
                }
                $savedCount++;
            }
        }

        return redirect()->back()
            ->with('success', "Attendance marked successfully. $savedCount records processed.");
    }

    /**
     * Process Compensatory Off logic for Sunday working (credit) and Absent (debit).
     */
    private function processCompOffLogic($userId, $tenantId, &$dbStatus)
    {
        $compOffType = \App\Models\LeaveType::where('code', 'ALP')
            ->orWhere('name', 'like', '%All Purpose%')
            ->orWhere('code', 'PL')
            ->first();

        if (!$compOffType) return null;

        if ($dbStatus === 'Sunday working') {
            // Credit is granted by the Attendance saved-hook (Sunday/Holiday
            // Working Credit) once the record is stored with >= 4h punches,
            // exactly like biometric users. No manual credit here or it
            // would be applied twice.
            return null;
        }

        if ($dbStatus === 'Absent') {
            // Debit if balance available
            $balance = \App\Models\LeaveBalance::where('user_id', $userId)
                ->where('leave_type_id', $compOffType->id)
                ->first();

            if ($balance && ($balance->balance - $balance->used) >= 1) {
                \App\Models\LeaveBalance::$auditReason = "Auto-adjusted against Earned Leave (Sunday)";
                $balance->increment('used', 1);

                $dbStatus = 'paid-leave';
                return 'Auto-adjusted against Earned Leave (Sunday Working)';
            }
        }

        return null;
    }

    /**
     * Normalize a CCare-marked status to the Decision Matrix vocabulary and
     * synthesize punch times so the matrix thresholds (7:45h full / 4h half)
     * classify the day exactly as marked. Every manual mark carries an admin
     * reason so the matrix honors it instead of recomputing from punches.
     *
     * @return array [status, checkIn, checkOut, adminReason]
     */
    private function alignWithDecisionMatrix(string $status, string $date): array
    {
        $reason = 'Marked by CCare';

        switch ($status) {
            case 'Present':
                return ['present', Carbon::parse($date . ' 09:30:00'), Carbon::parse($date . ' 18:30:00'), $reason];
            case 'Sunday working':
                // Stored as present with >= 4h punches on the Sunday/holiday:
                // the matrix derives SP/HP from the date, and the Attendance
                // saved-hook grants the working credit
                return ['present', Carbon::parse($date . ' 09:30:00'), Carbon::parse($date . ' 18:30:00'), $reason];
            case 'Half day':
                // No admin reason on purpose: the matrix then applies the
                // half-day leave check itself - if a paid half-day leave is
                // approved for the other half it upgrades to HD-PL (counts
                // full present), otherwise it stays a plain Half Day
                return ['half-day', Carbon::parse($date . ' 09:30:00'), Carbon::parse($date . ' 14:00:00'), ''];
            case 'Work from home':
                return ['work_from_home', Carbon::parse($date . ' 09:30:00'), Carbon::parse($date . ' 18:30:00'), $reason];
            case 'paid-leave': // Absent converted by comp-off adjustment above
                return ['paid_leave', Carbon::parse($date)->startOfDay(), null, $reason];
            case 'Absent':
            default:
                return ['absent', Carbon::parse($date)->startOfDay(), null, $reason];
        }
    }
}
