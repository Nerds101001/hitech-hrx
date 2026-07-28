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

        // Ensure only users mapped as CCARE (or Admin/HR/cc_attendance.manage) can access
        $isCCare = $user->department && stripos($user->department->name, 'Customer Care') !== false;
        if (!$isCCare && !$this->isAttendanceAdmin($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized access.');
        }

        // Get the date (default to today)
        $date = $request->input('date', Carbon::today()->format('Y-m-d'));

        if ($this->isAttendanceAdmin($user)) {
            $mappings = \App\Models\CcSalespersonMap::where('role', 'ccare')->with(['salesUser' => function ($q) {
                // Terminated staff must not appear on the attendance register.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminated()
                  ->with('designation');
            }, 'ccUser'])->get();
            $salespeople = $mappings->pluck('salesUser')->filter()->map(function($salesUser) use ($mappings) {
                $map = $mappings->firstWhere('sales_user_id', $salesUser->id);
                $salesUser->mapped_cc_name = $map && $map->ccUser ? $map->ccUser->name : 'N/A';
                return $salesUser;
            })->unique('id')->values();
        } else {
            // Fetch salespeople tagged to this CCARE user
            $salespeople = $user->mappedSalespersons()->where('role', 'ccare')->with(['salesUser' => function ($q) {
                // Terminated staff must not appear on the attendance register.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminated()
                  ->with('designation');
            }])->get()->pluck('salesUser')->filter()->unique('id')->values();
        }

        // Fetch their attendance for the selected date
        $attendances = Attendance::whereIn('user_id', $salespeople->pluck('id'))
            ->whereDate('check_in_time', $date)
            ->get()
            ->keyBy('user_id');

        // ── Summary over the WHOLE register, before any filter is applied, so
        //    the cards keep telling the truth while you narrow the list down.
        $shortFor = function ($att) {
            if (!$att) return null;
            $s = strtolower(str_replace('_', '-', (string) $att->status));
            if ($s === 'absent') return 'A';
            if (str_contains($s, 'half')) return 'HD';
            if (str_contains($s, 'home') || str_contains($s, 'wfh')) return 'WFH';
            if (str_contains($s, 'sunday')) return 'PS';
            if (in_array($s, ['leave', 'paid-leave', 'unpaid-leave', 'on-leave'], true)) return 'L';
            return 'P';
        };
        $summary = ['total' => $salespeople->count(), 'marked' => 0, 'pending' => 0,
                    'P' => 0, 'A' => 0, 'HD' => 0, 'WFH' => 0, 'PS' => 0, 'L' => 0];
        foreach ($salespeople as $p) {
            $code = $shortFor($attendances->get($p->id));
            if ($code === null) { $summary['pending']++; continue; }
            $summary['marked']++;
            $summary[$code]++;
        }

        // ── Register filters ──
        $fSearch = trim((string) $request->input('q', ''));
        $fCc     = trim((string) $request->input('cc', ''));
        $fDesig  = trim((string) $request->input('desig', ''));
        $fStatus = trim((string) $request->input('status', ''));
        $sort    = (string) $request->input('sort', 'name');
        $dir     = strtolower((string) $request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Option lists come from the register itself, so every choice returns rows.
        $ccOptions    = $salespeople->pluck('mapped_cc_name')->filter()->unique()->sort()->values();
        $desigOptions = $salespeople->map(fn($p) => optional($p->designation)->name)->filter()->unique()->sort()->values();

        $filtered = $salespeople->filter(function ($p) use ($fSearch, $fCc, $fDesig, $fStatus, $attendances, $shortFor) {
            if ($fSearch !== '') {
                $hay = strtolower(trim($p->getFullName() . ' ' . $p->code));
                if (!str_contains($hay, strtolower($fSearch))) return false;
            }
            if ($fCc !== '' && ($p->mapped_cc_name ?? '') !== $fCc) return false;
            if ($fDesig !== '' && optional($p->designation)->name !== $fDesig) return false;
            if ($fStatus !== '') {
                $code = $shortFor($attendances->get($p->id));
                if ($fStatus === 'pending' && $code !== null) return false;
                if ($fStatus === 'marked' && $code === null) return false;
                if (!in_array($fStatus, ['pending', 'marked'], true) && $code !== $fStatus) return false;
            }
            return true;
        })->values();

        $filtered = $filtered->sortBy(function ($p) use ($sort, $attendances, $shortFor) {
            return match ($sort) {
                'code'   => (string) $p->code,
                'desig'  => (string) optional($p->designation)->name,
                'cc'     => (string) ($p->mapped_cc_name ?? ''),
                'status' => (string) ($shortFor($attendances->get($p->id)) ?? 'ZZ'), // unmarked last
                default  => strtolower($p->getFullName()),
            };
        }, SORT_NATURAL | SORT_FLAG_CASE, $dir === 'desc')->values();

        $salespeople = $filtered;

        // Lock state for the banner: date D closes at D+2, 12:00 PM (Admin/HR exempt).
        $isLocked = $this->isLockedFor($date, $user);
        $lockDeadline = Carbon::parse($date)->addDays(2)->setTime(12, 0);
        // Earliest date a non-admin may load (July back-fill window is open).
        $minDate = min(self::OPEN_FROM, Carbon::today()->subDays(2)->toDateString());
        $openWindow = [self::OPEN_FROM, self::OPEN_TO];

        $canSeeReport = $this->canSeeReport($user);

        return view('tenant.cc_attendance.index', compact('salespeople', 'date', 'attendances', 'user',
            'isLocked', 'lockDeadline', 'minDate', 'openWindow', 'canSeeReport',
            'summary', 'ccOptions', 'desigOptions', 'fSearch', 'fCc', 'fDesig', 'fStatus', 'sort', 'dir'));
    }

    /**
     * Filling window: date D stays open until D+2 at 12:00 PM (app time / IST).
     * Admin/HR are never locked.
     */
    /** Approved-leave style statuses are managed by the leave module, not here. */
    private function isLeaveStatus(?string $status): bool
    {
        if (!$status) return false;
        $s = strtolower(str_replace('_', '-', $status));
        return in_array($s, ['leave', 'paid-leave', 'unpaid-leave', 'on-leave']);
    }

    /**
     * PHASE 1 (temporary): the whole of July 2026 is open for back-dated filling
     * so the teams can complete the month. Remove this window in the next phase
     * to enforce the D+2 12:00 PM deadline everywhere.
     */
    private const OPEN_FROM = '2026-07-01';
    private const OPEN_TO   = '2026-07-31';

    /**
     * True for the classic admin/hr role, OR a user directly granted the
     * cc_attendance.manage permission — the latter unlocks the full register,
     * report, and lock-bypass without promoting the user to admin/hr.
     */
    private function isAttendanceAdmin($user): bool
    {
        return $user->hasRole(['admin', 'hr']) || $user->can('cc_attendance.manage');
    }

    /**
     * The salesperson user_ids this user may write attendance for: the whole
     * CCare register for an attendance admin, else only their own mapped
     * salespeople. Used to stop store()/storeMonthly() from writing
     * attendance for anyone outside the caller's authorized set.
     */
    private function allowedSalespersonIds($user): array
    {
        if ($this->isAttendanceAdmin($user)) {
            return CcSalespersonMap::where('role', 'ccare')
                ->pluck('sales_user_id')->unique()->values()->all();
        }
        return $user->mappedSalespersons()->where('role', 'ccare')
            ->pluck('sales_user_id')->unique()->values()->all();
    }

    private function isLockedFor(string $date, $user): bool
    {
        if ($this->isAttendanceAdmin($user)) {
            return false;
        }
        if ($date >= self::OPEN_FROM && $date <= self::OPEN_TO) {
            return false; // July back-fill window
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
        $isTop = $this->isAttendanceAdmin($user);

        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->input('to', Carbon::today()->toDateString());

        $q = Attendance::with(['user.designation', 'createdBy', 'updatedBy'])
            ->whereDate('check_in_time', '>=', $from)
            ->whereDate('check_in_time', '<=', $to);

        if ($isTop) {
            $spPool = CcSalespersonMap::where('role', 'ccare')->pluck('sales_user_id')->unique()->values();
            $q->whereIn('user_id', $spPool);
        } else {
            // A CCare agent sees ONLY what they submitted themselves — never
            // another agent's submissions, and never the whole register.
            $q->where(function ($w) use ($user) {
                $w->where('created_by_id', $user->id)->orWhere('updated_by_id', $user->id);
            });
            $spPool = CcSalespersonMap::where('cc_user_id', $user->id)->where('role', 'ccare')
                ->pluck('sales_user_id')->unique()->values();
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

    /** Admin/HR see every submission; a CCare agent sees only their own. */
    /**
     * Who may open the Submission Report.
     *
     * Public so the Blade tab strip can ask the SAME question the report()
     * method asks. The tabs used to hardcode hasRole(['admin','hr','manager']),
     * which ignored cc_attendance.manage — so anyone delegated that permission
     * could open /cc-attendance/report by URL but never saw a link to it.
     */
    public function canSeeReport($user): bool
    {
        return $this->isAttendanceAdmin($user)
            || ($user->department && stripos($user->department->name, 'Customer Care') !== false);
    }

    public function report(Request $request)
    {
        $user = Auth::user();
        if (!$this->canSeeReport($user)) {
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
        if (!$this->canSeeReport($user)) {
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
            'attendance.*' => 'nullable|in:Present,Absent,Work from home,Half day,Sunday working,__clear',
            'wfh_proof' => 'nullable|array',
            'wfh_proof.*' => 'nullable|image|max:5120',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string'
        ]);

        // Only allow writes for salespeople actually on this user's register —
        // an attendance admin gets the whole CCare set, everyone else only
        // their own mapped salespeople. Anything else in the posted payload
        // is silently dropped rather than trusted.
        $allowedIds = $this->allowedSalespersonIds(Auth::user());
        $request->merge(['attendance' => array_intersect_key(
            (array) $request->attendance, array_flip($allowedIds)
        )]);

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

        // Reporting/remarks is MANDATORY for every salesperson being marked — a
        // note must accompany each mark. Unmarked people are simply skipped, so a
        // partial submission (even a SINGLE person) is fine as long as that
        // person's note is filled. WFH also needs the approval snapshot.
        $missingNotes = [];
        $missingProof = [];
        foreach ($request->attendance as $userId => $status) {
            if (empty($status) || $status === '__clear') continue;
            if (trim((string) ($request->notes[$userId] ?? '')) === '') {
                $missingNotes[] = $userId;
            }
            // Work from home has to be backed by the approval snapshot, unless
            // the row already carries one from an earlier submission.
            if ($status === 'Work from home'
                && !$request->hasFile("wfh_proof.$userId")
                && !$this->existingProof($userId, $date)) {
                $missingProof[] = $userId;
            }
        }
        if ($missingNotes) {
            $names = \App\Models\User::whereIn('id', $missingNotes)->get()
                ->map(fn($u) => $u->name)->implode(', ');
            return redirect()->back()->withInput()
                ->with('error', "Reporting/remarks is mandatory for every marked salesperson — nothing was saved. Missing for: {$names}");
        }
        if ($missingProof) {
            $names = \App\Models\User::whereIn('id', $missingProof)->get()
                ->map(fn($u) => $u->name)->implode(', ');
            return redirect()->back()->withInput()
                ->with('error', "Work From Home needs the approval snapshot — nothing was saved. Missing for: {$names}");
        }

        $tenantId = Auth::user()->tenant_id;
        $authId = Auth::id();

        $userIds = array_keys(array_filter($request->attendance));
        $existingAttendances = empty($userIds) ? collect() : Attendance::whereIn('user_id', $userIds)
            ->whereDate('check_in_time', $date)
            ->get()
            ->keyBy('user_id');

        $cleared = 0;
        foreach ($request->attendance as $userId => $status) {
            if (empty($status)) continue;

            $attendance = $existingAttendances->get($userId);

            // Previous submissions can be corrected while the date is open (the
            // window check above already blocked locked dates; Admin/HR always
            // pass). Approved leave is owned by the leave module.
            if ($attendance && $this->isLeaveStatus($attendance->status)) {
                continue;
            }

            // "Clear" removes a wrongly-marked day so it can be marked again,
            // or left blank. Soft delete keeps the record for the audit trail.
            if ($status === '__clear') {
                if ($attendance) {
                    $attendance->forceFill(['updated_by_id' => $authId])->save();
                    $attendance->delete();
                    $cleared++;
                }
                continue;
            }

            $dbStatus = $status;
            $note = $request->notes[$userId] ?? null;

            $compOffReason = $this->processCompOffLogic($userId, $tenantId, $dbStatus);
            [$dbStatus, $checkIn, $checkOut, $ccReason] = $this->alignWithDecisionMatrix($dbStatus, $date);
            $adminReason = trim(($compOffReason ? $compOffReason . ' | ' : '') . $ccReason) ?: null;

            $proofPath = $request->hasFile("wfh_proof.$userId")
                ? $request->file("wfh_proof.$userId")->store('wfh_approvals', 'public')
                : null;

            if ($attendance) {
                $updateData = [
                    'status' => $dbStatus,
                    'notes' => $note,
                    'admin_reason' => $adminReason,
                    'updated_by_id' => $authId
                ];
                if ($proofPath) {
                    $updateData['attachment'] = $proofPath;
                }
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
                    'attachment' => $proofPath,
                    'created_by_id' => $authId
                ]);
            }
        }

        $msg = 'Attendance saved for the salespeople you marked.'
             . ($cleared ? " {$cleared} entry(ies) cleared." : '');
        return redirect()->route('cc-attendance.index', ['date' => $date])->with('success', $msg);
    }

    /** Is a WFH approval snapshot already on file for this person/date? */
    private function existingProof($userId, string $date): bool
    {
        return Attendance::where('user_id', $userId)
            ->whereDate('check_in_time', $date)
            ->whereNotNull('attachment')->exists();
    }

    /**
     * Display the Monthly Matrix Attendance screen for CCARE
     */
    public function monthly(Request $request)
    {
        $user = Auth::user();

        // Ensure only users mapped as CCARE (or Admin/HR/cc_attendance.manage) can access
        $isCCare = $user->department && stripos($user->department->name, 'Customer Care') !== false;
        if (!$isCCare && !$this->isAttendanceAdmin($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Unauthorized access.');
        }

        $month = $request->input('month', Carbon::today()->month);
        $year = $request->input('year', Carbon::today()->year);
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;

        if ($this->isAttendanceAdmin($user)) {
            $mappings = \App\Models\CcSalespersonMap::where('role', 'ccare')->with(['salesUser' => function ($q) {
                // Terminated staff must not appear on the attendance register.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminated()
                  ->with('designation');
            }, 'ccUser'])->get();
            $salespeople = $mappings->pluck('salesUser')->filter()->map(function($salesUser) use ($mappings) {
                $map = $mappings->firstWhere('sales_user_id', $salesUser->id);
                $salesUser->mapped_cc_name = $map && $map->ccUser ? $map->ccUser->name : 'N/A';
                return $salesUser;
            })->unique('id')->values();
        } else {
            $salespeople = $user->mappedSalespersons()->where('role', 'ccare')->with(['salesUser' => function ($q) {
                // Terminated staff must not appear on the attendance register.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminated()
                  ->with('designation');
            }])->get()->pluck('salesUser')->filter()->unique('id')->values();
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

        $canSeeReport = $this->canSeeReport($user);

        return view('tenant.cc_attendance.monthly', compact('salespeople', 'month', 'year', 'daysInMonth', 'startOfMonth', 'attendances', 'user', 'canSeeReport'));
    }

    /**
     * Store/Update manual attendance by CCARE from Monthly View
     */
    public function storeMonthly(Request $request)
    {
        $request->validate([
            'attendance' => 'required|array',
        ]);

        // Same register-scoping guard as store(): drop any user_id outside
        // this caller's authorized set before touching anything.
        $allowedIds = $this->allowedSalespersonIds(Auth::user());
        $request->merge(['attendance' => array_intersect_key(
            (array) $request->attendance, array_flip($allowedIds)
        )]);

        $tenantId = Auth::user()->tenant_id;
        $authId = Auth::id();
        $maxDate = Carbon::today()->format('Y-m-d');

        // Reporting/remarks is MANDATORY for every cell being marked.
        $missing = [];
        foreach ($request->attendance as $userId => $dates) {
            foreach ($dates as $dateStr => $status) {
                if (empty($status)) continue;
                if (trim((string) ($request->notes[$userId][$dateStr] ?? '')) === '') {
                    $missing[] = [$userId, $dateStr];
                }
            }
        }
        if ($missing) {
            $sample = collect($missing)->take(3)->map(function ($m) {
                $n = optional(\App\Models\User::find($m[0]))->name ?? ('#' . $m[0]);
                return "$n (" . $m[1] . ")";
            })->implode(', ');
            $more = count($missing) > 3 ? ' and ' . (count($missing) - 3) . ' more' : '';
            return redirect()->back()->with('error',
                "Reporting/remarks is mandatory — nothing was saved. Use the 'Add Reporting Note' link on each marked cell. Missing: {$sample}{$more}.");
        }

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

                // Corrections allowed while the date is open; approved leave is
                // owned by the leave module and never overwritten here.
                if ($existing && $this->isLeaveStatus($existing->status)) {
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
