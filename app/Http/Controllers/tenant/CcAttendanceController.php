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

        // Lock state for the banner: date D closes at D+2, 12:00 PM (Admin/HR exempt).
        $isLocked = $this->isLockedFor($date, $user);
        $lockDeadline = Carbon::parse($date)->addDays(2)->setTime(12, 0);

        return view('tenant.cc_attendance.index', compact('salespeople', 'date', 'attendances', 'user', 'isLocked', 'lockDeadline'));
    }

    /**
     * Filling window: date D stays open until D+2 at 12:00 PM (app time / IST).
     * Admin/HR are never locked.
     */
    private function isLockedFor(string $date, $user): bool
    {
        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            return false;
        }
        return now()->greaterThan(Carbon::parse($date)->addDays(2)->setTime(12, 0));
    }

    /**
     * Submission report for Admin/HR/Manager: who marked what, for whom, when
     * (exact time), with the reporting remarks — filterable by salesperson,
     * marker, status and date range (weekly/monthly).
     */
    /** Shared query for the report page and its Excel export. Returns [$q, $spPool]. */
    private function reportQuery(Request $request, $user): array
    {
        $isTop = $user->hasRole(['admin', 'Admin', 'hr', 'HR']);

        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->input('to', Carbon::today()->toDateString());

        $q = Attendance::with(['user.designation', 'createdBy', 'updatedBy'])
            ->whereDate('check_in_time', '>=', $from)
            ->whereDate('check_in_time', '<=', $to);

        if ($isTop) {
            $spPool = CcSalespersonMap::pluck('sales_user_id')->unique()->values();
            $q->whereIn('user_id', $spPool);
        } else {
            // Manager: their team's rows — as the salesperson OR as the marker.
            $teamIds = \App\Models\User::where('reporting_to_id', $user->id)->pluck('id')->push($user->id);
            $q->where(function ($w) use ($teamIds) {
                $w->whereIn('user_id', $teamIds)
                  ->orWhereIn('created_by_id', $teamIds)
                  ->orWhereIn('updated_by_id', $teamIds);
            });
            $spPool = $teamIds;
        }

        if ($request->filled('sp'))     $q->where('user_id', $request->sp);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('cc')) {
            $q->where(function ($w) use ($request) {
                $w->where('created_by_id', $request->cc)->orWhere('updated_by_id', $request->cc);
            });
        }

        return [$q, $spPool, $from, $to];
    }

    public function report(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager'])) {
            abort(403);
        }

        [$q, $spPool, $from, $to] = $this->reportQuery($request, $user);

        $summary = (clone $q)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $rows = $q->orderByDesc('check_in_time')->paginate(100)->withQueryString();

        $salesList = \App\Models\User::whereIn('id', $spPool)->orderBy('first_name')->get();
        $markerIds = Attendance::whereIn('user_id', $spPool)->whereNotNull('created_by_id')
            ->distinct()->pluck('created_by_id')
            ->merge(Attendance::whereIn('user_id', $spPool)->whereNotNull('updated_by_id')->distinct()->pluck('updated_by_id'))
            ->unique()->values();
        $markers = \App\Models\User::whereIn('id', $markerIds)->orderBy('first_name')->get();

        return view('tenant.cc_attendance.report', compact('rows', 'summary', 'salesList', 'markers', 'from', 'to'));
    }

    /**
     * Excel export of the submission report — same filters as the page, so
     * salesperson + month range = that person's monthly attendance report
     * (salesperson, status, remarks, filled by, exact submission time).
     */
    public function exportReport(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager'])) {
            abort(403);
        }

        [$q, $spPool, $from, $to] = $this->reportQuery($request, $user);
        $rows = $q->orderBy('check_in_time')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Report');

        $spName = $request->filled('sp') ? trim(optional(\App\Models\User::find($request->sp))->name ?? '') : 'All Salespersons';
        $sheet->setCellValue('A1', "Salesperson Attendance Report — {$spName} ({$from} to {$to})");
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $headers = ['Date', 'Day', 'Salesperson', 'Emp Code', 'Status', 'Reporting / Remarks', 'Filled By', 'Submitted At', 'Last Updated (by)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 3, $h);
        }
        $sheet->getStyle('A3:I3')->getFont()->setBold(true);
        $sheet->getStyle('A3:I3')->getFill()->setFillType('solid')->getStartColor()->setRGB('0D7377');
        $sheet->getStyle('A3:I3')->getFont()->getColor()->setRGB('FFFFFF');

        $r = 4;
        foreach ($rows as $row) {
            $wasUpdated = $row->updated_at && $row->created_at && !$row->updated_at->equalTo($row->created_at);
            $sheet->setCellValueByColumnAndRow(1, $r, optional($row->check_in_time)->format('d-m-Y'));
            $sheet->setCellValueByColumnAndRow(2, $r, optional($row->check_in_time)->format('D'));
            $sheet->setCellValueByColumnAndRow(3, $r, $row->user->name ?? ('#' . $row->user_id));
            $sheet->setCellValueByColumnAndRow(4, $r, $row->user->code ?? '');
            $sheet->setCellValueByColumnAndRow(5, $r, ucwords(str_replace('_', ' ', $row->status ?? '')));
            $sheet->setCellValueByColumnAndRow(6, $r, $row->notes ?? '');
            $sheet->setCellValueByColumnAndRow(7, $r, $row->createdBy->name ?? ($row->updatedBy->name ?? 'System/Biometric'));
            $sheet->setCellValueByColumnAndRow(8, $r, optional($row->created_at)->format('d-m-Y h:i:s A'));
            $sheet->setCellValueByColumnAndRow(9, $r, $wasUpdated
                ? $row->updated_at->format('d-m-Y h:i:s A') . ($row->updatedBy ? ' (' . $row->updatedBy->name . ')' : '')
                : '');
            $r++;
        }
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $file = 'salesperson_attendance_' . str_replace(' ', '_', strtolower($spName)) . "_{$from}_to_{$to}.xlsx";
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
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

        // Filling window: date D stays open until D+2 at 12:00 PM (IST). After
        // that it is locked for CCare — only Admin/HR can still mark it.
        $maxDate = Carbon::today()->format('Y-m-d');
        if ($date > $maxDate) {
            return redirect()->back()->with('error', 'Attendance cannot be marked for a future date.');
        }
        if ($this->isLockedFor($date, Auth::user())) {
            $deadline = Carbon::parse($date)->addDays(2)->setTime(12, 0)->format('d M Y, h:i A');
            return redirect()->back()->with('error', "Attendance for {$date} is locked (deadline was {$deadline}). Please contact Admin/HR.");
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
        $maxDate = Carbon::today()->format('Y-m-d');

        $savedCount = 0;

        foreach ($request->attendance as $userId => $dates) {
            foreach ($dates as $dateStr => $status) {
                if (empty($status)) continue;

                // Window: open until D+2 12:00 PM; Admin/HR always open. No future dates.
                if ($dateStr > $maxDate) continue;
                if ($this->isLockedFor($dateStr, Auth::user())) continue;
                
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
