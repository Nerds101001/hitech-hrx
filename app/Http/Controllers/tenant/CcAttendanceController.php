<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CcSalespersonMap;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            $mappings = \App\Models\CcSalespersonMap::where('role', 'ccare')->with(['salesUser' => function ($q) use ($date) {
                // Terminated staff drop off the register once $date is past
                // their last working day; attendance from their tenure stays visible.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminatedAsOf($date)
                  ->with('designation');
            }, 'ccUser'])->get();
            $salespeople = $mappings->pluck('salesUser')->filter()->map(function($salesUser) use ($mappings) {
                $map = $mappings->firstWhere('sales_user_id', $salesUser->id);
                $salesUser->mapped_cc_name = $map && $map->ccUser ? $map->ccUser->name : 'N/A';
                return $salesUser;
            })->unique('id')->values();
        } else {
            // Fetch salespeople tagged to this CCARE user
            $salespeople = $user->mappedSalespersons()->where('role', 'ccare')->with(['salesUser' => function ($q) use ($date) {
                // Terminated staff drop off the register once $date is past
                // their last working day; attendance from their tenure stays visible.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminatedAsOf($date)
                  ->with('designation');
            }])->get()->pluck('salesUser')->filter()->unique('id')->values();
        }

        // Fetch their attendance for the selected date
        $attendances = Attendance::whereIn('user_id', $salespeople->pluck('id'))
            ->whereDate('check_in_time', $date)
            ->get()
            ->keyBy('user_id');

        // Leave applied for or approved on this date, shown on each row. A
        // part-day leave (short leave, half day, WFH) leaves the rest of the
        // day for CCare to confirm; a full-day leave keeps the row read-only.
        $dayLeaves = $this->leaveClaimsByUser($salespeople->pluck('id')->map(fn ($i) => (int) $i)->all(), $date);

        // ── Summary over the WHOLE register, before any filter is applied, so
        //    the cards keep telling the truth while you narrow the list down.
        // 'Present', 'Sunday working' and 'Holiday working' all save as the
        // identical status='present' + 09:30-18:30 punches (alignWithDecisionMatrix) —
        // the actual comp-off-earning distinction is date-derived, not stored, so
        // PS/HP must be derived here from the date the same way, not from $att->status.
        $shortFor = function ($att, $person) use ($dayLeaves) {
            if (!$att) return null;
            // The row the leave module writes for a part-day leave (00:00, no
            // punches) is not a mark: nobody has said whether the rest of the
            // day was worked. It waits for CCare like an empty row.
            if ($this->isUnconfirmedLeaveRow($att, $dayLeaves->get($person->id))) return null;
            $s = strtolower(str_replace('_', '-', (string) $att->status));
            if ($s === 'absent') return 'A';
            if (str_contains($s, 'half')) return 'HD';
            if (str_contains($s, 'home') || str_contains($s, 'wfh')) return 'WFH';
            if (in_array($s, ['leave', 'paid-leave', 'unpaid-leave', 'on-leave'], true)) return 'L';
            if ($s === 'present' && $att->check_in_time) {
                $workMins = \App\Services\AttendanceDayStatus::workedMinutes($att->check_in_time, $att->check_out_time);   // unsigned: punches can be stored reversed
                if ($workMins >= \App\Services\AttendanceDayStatus::OFFDAY_CREDIT_MINUTES) {
                    if ($att->check_in_time->isSunday()) return 'PS';
                    if (\App\Models\Holiday::appliesTo($person, $att->check_in_time)) return 'HP';
                }
            }
            return 'P';
        };
        $summary = ['total' => $salespeople->count(), 'marked' => 0, 'pending' => 0,
                    'P' => 0, 'A' => 0, 'HD' => 0, 'WFH' => 0, 'PS' => 0, 'HP' => 0, 'L' => 0];
        foreach ($salespeople as $p) {
            $code = $shortFor($attendances->get($p->id), $p);
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
                $code = $shortFor($attendances->get($p->id), $p);
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
                'status' => (string) ($shortFor($attendances->get($p->id), $p) ?? 'ZZ'), // unmarked last
                default  => strtolower($p->getFullName()),
            };
        }, SORT_NATURAL | SORT_FLAG_CASE, $dir === 'desc')->values();

        $salespeople = $filtered;

        // Lock state for the banner: date D closes at 12:00 PM on the 2nd working day after it (Admin/HR exempt).
        $isLocked = $this->isLockedFor($date, $user);
        $lockDeadline = $this->lockDeadline($date, $user);
        // Earliest date a non-admin may load (July back-fill window is open).
        $minDate = min(self::OPEN_FROM, Carbon::today()->subDays(2)->toDateString());
        $openWindow = [self::OPEN_FROM, self::OPEN_TO];

        $canSeeReport = $this->canSeeReport($user);

        return view('tenant.cc_attendance.index', compact('salespeople', 'date', 'attendances', 'dayLeaves', 'user',
            'isLocked', 'lockDeadline', 'minDate', 'openWindow', 'canSeeReport',
            'summary', 'ccOptions', 'desigOptions', 'fSearch', 'fCc', 'fDesig', 'fStatus', 'sort', 'dir'));
    }

    /**
     * Filling window: date D stays open until 12:00 PM (app time / IST) on the
     * 2nd working day after it - see lockDeadline(). Admin/HR are never locked.
     */
    /** Approved-leave style statuses are managed by the leave module, not here. */
    private function isLeaveStatus(?string $status): bool
    {
        if (!$status) return false;
        $s = strtolower(str_replace('_', '-', $status));
        return in_array($s, ['leave', 'paid-leave', 'unpaid-leave', 'on-leave']);
    }

    /**
     * Salespeople with a leave request covering this date, as [user_id => status].
     *
     * The existing guard only looked at the attendance row, so it caught a day
     * the leave module had already written to and nothing else. A leave that
     * was merely APPLIED FOR, or approved before its attendance row existed,
     * left the day open for CCare to mark -- and someone marked absent that
     * way keeps the absence even after the leave is approved, because an
     * admin-marked row carries clock times and used to survive approval.
     *
     * Pending counts as well as approved: the answer to "was this person at
     * work" is not CCare's to give while the leave module is still deciding.
     */
    private function leaveClaimsByUser(array $userIds, string $date)
    {
        if ($userIds === []) {
            return collect();
        }

        return \App\Models\LeaveRequest::with('leaveType')
            ->whereIn('user_id', $userIds)
            ->whereIn('status', ['pending', 'approved'])
            ->whereNull('deleted_at')
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->get()
            ->keyBy('user_id');
    }

    /** 'pending' or 'approved'. */
    public static function leaveState($leave): string
    {
        return (string) ($leave->status->value ?? $leave->status);
    }

    /**
     * What part of the day a leave takes: 'short', 'half', 'wfh' or 'full'.
     * Only 'full' takes the whole day away from CCare -- for the others the
     * salesperson is meant to work the rest of the day (or work from home),
     * and whether they did is exactly what CCare reports on.
     */
    public static function leaveKind($leave): string
    {
        if (!$leave) return 'full';
        $type = $leave->leaveType;
        if (strtoupper((string) ($type->code ?? '')) === 'WFH') return 'wfh';
        if ($leave->is_short_leave || ($type->is_short_leave ?? false)) return 'short';
        if ($leave->is_half_day || ($type->is_half_day ?? false)) {
            // First half and second half both taken (as two requests): the
            // whole day is off, nothing left for CCare to mark as worked.
            if ($leave instanceof \App\Models\LeaveRequest
                && $leave->otherHalf([\App\Enums\LeaveRequestStatus::PENDING, \App\Enums\LeaveRequestStatus::APPROVED])) {
                return 'full';
            }
            return 'half';
        }
        return 'full';
    }

    /** One line for the register: "Short Leave 10:00 AM - 12:00 PM (2h) - approved". */
    public static function leaveLabel($leave): string
    {
        $kind = self::leaveKind($leave);
        $bits = [$leave->leaveType->name ?? 'Leave'];
        if ($kind === 'short' && $leave->start_time && $leave->end_time) {
            $bits[] = Carbon::parse($leave->start_time)->format('h:i A') . ' - ' . Carbon::parse($leave->end_time)->format('h:i A');
        }
        if ($kind === 'short' && $leave->duration_hours) {
            $bits[] = '(' . rtrim(rtrim(number_format((float) $leave->duration_hours, 2), '0'), '.') . 'h)';
        }
        if (in_array($kind, ['half', 'wfh'], true) && $leave->is_half_day) {
            $bits[] = $leave->half_day_session === 'second_half' ? '(2nd half)' : '(1st half)';
        }
        return implode(' ', $bits) . ' - ' . (self::leaveState($leave) === 'pending' ? 'awaiting approval' : 'approved');
    }

    /**
     * The placeholder row the leave module writes for a part-day leave --
     * 00:00 check-in, no check-out, never touched by CCare -- is not a mark.
     */
    public function isUnconfirmedLeaveRow($att, $leave): bool
    {
        if (!$att || !$att->leave_request_id || $att->check_out_time) return false;
        if (!$att->check_in_time || $att->check_in_time->format('H:i') !== '00:00') return false;
        if ($att->updated_by_id) return false;
        $leave = $leave && $leave->id === $att->leave_request_id ? $leave : $att->leaveRequest;
        return $leave && self::leaveKind($leave) !== 'full';
    }

    /**
     * CCare's mark on a part-day-leave day, turned into what is stored.
     *
     * Returns [status, checkIn, checkOut, adminReason] like
     * alignWithDecisionMatrix(), or null to fall back to it. The admin
     * reason is left empty on purpose so the day-status matrix does the
     * sum itself with the leave in view: a short leave plus 6h+ worked is
     * 'short-leave-present', a half-day leave plus the other half worked is
     * 'half-day-paid', an approved WFH is 'work_from_home'.
     */
    private function markAroundLeave(string $status, $leave, string $date): ?array
    {
        $kind = self::leaveKind($leave);
        $day = fn (string $t) => Carbon::parse($date . ' ' . $t);

        if ($kind === 'short' && $status === 'Present') {
            // The part of the 09:30-18:30 day the short leave does not cover.
            [$in, $out] = [$day('09:30:00'), $day('18:30:00')];
            if ($leave->start_time && $leave->end_time) {
                $ls = $day(Carbon::parse($leave->start_time)->format('H:i:s'));
                $le = $day(Carbon::parse($leave->end_time)->format('H:i:s'));
                if ($ls->lessThanOrEqualTo($day('11:00:00'))) {
                    $in = $le->max($in);          // came in after the leave
                } else {
                    $out = $ls->min($out);        // left when the leave began
                }
            }
            // Reporting "present" means the rest of the day was worked: never
            // record less than six hours for it, whatever the leave times say.
            if ($out->lessThanOrEqualTo($in) || $in->diffInMinutes($out) < 360) {
                [$in, $out] = [$day('09:30:00'), $day('15:30:00')];
            }
            return ['present', $in, $out, ''];
        }

        // The register's own hint text ("HD = half day, A = absent") already
        // promised these two for a short leave, but only 'Present' was ever
        // implemented -- the other two fell through to the generic path below,
        // which stamps an admin_reason and makes AttendanceDayStatus::resolve()
        // trust a bare 'absent' instead of correctly classifying the day (e.g.
        // unpaid_leave/half-day) from the still-attached short leave. No admin
        // reason here on purpose, same as 'Present' above: the matrix derives
        // the right code itself from workMins + the leave link.
        if ($kind === 'short' && $status === 'Half day') {
            // Some, but under 6h, of the day's remainder was worked (4h-5h50m
            // lands AttendanceDayStatus in its own 'half-day' bucket).
            return ['half-day', $day('09:30:00'), $day('14:00:00'), ''];
        }
        if ($kind === 'short' && $status === 'Absent') {
            // None of the remainder was worked -- no real punches, matching
            // how an ordinary Absent day is stored.
            return ['absent', $day('00:00:00'), null, ''];
        }

        if ($kind === 'half' && in_array($status, ['Present', 'Half day'], true)) {
            // Worked the half the leave does not cover.
            return $leave->half_day_session === 'second_half'
                ? ['half-day', $day('09:30:00'), $day('14:00:00'), '']
                : ['half-day', $day('14:00:00'), $day('18:30:00'), ''];
        }
        if ($kind === 'half' && $status === 'Absent') {
            // Same hint-text promise as short leave's 'A' -- see above. Left
            // with no admin reason so the matrix classifies the unworked half
            // against the leave's own paid/unpaid state (unworkedHalfCode()),
            // instead of a bare 'absent' that drops that distinction.
            return ['absent', $day('00:00:00'), null, ''];
        }

        if ($kind === 'wfh' && in_array($status, ['Present', 'Work from home'], true)) {
            return ['work_from_home', $day('09:30:00'), $day('18:30:00'), ''];
        }
        // wfh + Absent deliberately falls through to the generic path below,
        // NOT handled here: AttendanceDayStatus::resolve() has no punch-based
        // way to verify remote work (WFH never required a punch), so its own
        // "no admin reason" branch trusts any WFH leave as worked unconditionally.
        // The generic path's admin_reason override -- trusting CCare's raw
        // 'Absent' as-is -- is what correctly overrides that assumption here.

        return null;
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
            return false; // legacy July 2026 back-fill window
        }
        // Admin-opened override windows (Attendance > Unlock Windows).
        foreach ($this->activeUnlocks() as $w) {
            if ($w->covers($date, $user->id ?? null)) {
                return false;
            }
        }
        return now()->greaterThan($this->lockDeadline($date, $user));
    }

    /**
     * When date D stops being fillable: 12:00 PM on the 2nd WORKING day after
     * it, counting the working days of the person filling it in - Sundays,
     * holidays that apply to them and their off-Saturdays don't count.
     *
     * It was D+2 calendar days, so a weekend or a holiday used up the window:
     * Friday's sheet locked on Sunday and Saturday's at Monday noon, before the
     * team was back. Now Saturday's stays open until Tuesday noon, and a day
     * before a holiday gets the holiday added on.
     */
    public function lockDeadline(string $date, $user): Carbon
    {
        $day = Carbon::parse($date)->startOfDay();
        $counted = 0;
        for ($i = 0; $i < 31 && $counted < 2; $i++) {
            $day->addDay();
            if ($this->isWorkingDay($day, $user)) {
                $counted++;
            }
        }

        return $day->copy()->setTime(12, 0);
    }

    /** Per-request memo: the monthly grid asks about the same few days for every row. */
    private array $workingDays = [];

    private function isWorkingDay(Carbon $day, $user): bool
    {
        $key = ($user->id ?? 0) . '|' . $day->toDateString();

        return $this->workingDays[$key] ??= (function () use ($day, $user) {
            if ($day->isSunday()) {
                return false;
            }
            if ($user && \App\Models\Holiday::appliesTo($user, $day->toDateString())) {
                return false;
            }
            if ($user && $day->isSaturday() && \App\Services\LeavePolicyService::saturdayOffState($user, $day) === true) {
                return false;
            }
            return true;
        })();
    }

    /**
     * Active unlock windows, fetched once per request. isLockedFor() runs once
     * per row on the monthly grid, so without this the page would issue a query
     * per cell.
     */
    private ?\Illuminate\Support\Collection $unlockCache = null;

    private function activeUnlocks(): \Illuminate\Support\Collection
    {
        if ($this->unlockCache === null) {
            // No date filter here: from_date/to_date describe the ATTENDANCE
            // dates a window covers, not when it activates, so a window opened
            // ahead of time must still be found. covers() does the date test.
            $this->unlockCache = \App\Models\CcAttendanceUnlock::where('is_active', true)
                ->orderByDesc('id')
                ->get();
        }
        return $this->unlockCache;
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

        $q = Attendance::with(['user.designation', 'createdBy', 'updatedBy', 'leaveRequest.leaveType'])
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

        // Only what someone else reported for the salesperson. A row the
        // salesperson created for themselves (their own short leave, their
        // own app entry) is not a CCare submission, unless someone else has
        // since marked it. Biometric/system rows (no creator) stay.
        $q->where(function ($w) {
            $w->whereNull('created_by_id')
              ->orWhereColumn('created_by_id', '!=', 'user_id')
              ->orWhere(fn ($x) => $x->whereNotNull('updated_by_id')->whereColumn('updated_by_id', '!=', 'user_id'));
        });

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
        // Salespeople marking their own day are not "markers" for this report.
        $markerIds = Attendance::whereIn('user_id', $spPool)->whereNotNull('created_by_id')
            ->whereColumn('created_by_id', '!=', 'user_id')
            ->distinct()->pluck('created_by_id')
            ->merge(Attendance::whereIn('user_id', $spPool)->whereNotNull('updated_by_id')
                ->whereColumn('updated_by_id', '!=', 'user_id')->distinct()->pluck('updated_by_id'))
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
            'attendance.*' => 'nullable|in:Present,Absent,Work from home,Half day,Sunday working,Holiday working,__clear',
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

        // Filling window: date D stays open until 12:00 PM (IST) on the 2nd working
        // day after it. After that it is locked for CCare — only Admin/HR can still mark it.
        $maxDate = Carbon::today()->format('Y-m-d');
        if ($date > $maxDate) {
            return redirect()->back()->with('error', 'Attendance cannot be marked for a future date.');
        }
        if ($this->isLockedFor($date, Auth::user())) {
            $deadline = $this->lockDeadline($date, Auth::user())->format('d M Y, h:i A');
            return redirect()->back()->with('error', "Attendance for {$date} is locked (deadline was {$deadline}). Please contact Admin/HR.");
        }

        // Reporting/remarks is MANDATORY for every salesperson being marked — a
        // note must accompany each mark. Unmarked people are simply skipped, so a
        // partial submission (even a SINGLE person) is fine as long as that
        // person's note is filled. WFH also needs the approval snapshot.
        $missingNotes = [];
        $missingProof = [];
        // Who has a leave request over this date, applied or approved.
        $leaveClaims = $this->leaveClaimsByUser(
            array_map('intval', array_keys($request->attendance)),
            $date
        );
        foreach ($request->attendance as $userId => $status) {
            if (empty($status) || $status === '__clear') continue;
            if (trim((string) ($request->notes[$userId] ?? '')) === '') {
                $missingNotes[] = $userId;
            }
            // Work from home has to be backed by the approval snapshot, unless
            // the row already carries one from an earlier submission.
            // An approved WFH leave is the approval -- no snapshot needed.
            $wfhLeave = $leaveClaims->get((int) $userId);
            $wfhApproved = $wfhLeave && self::leaveKind($wfhLeave) === 'wfh' && self::leaveState($wfhLeave) === 'approved';
            if ($status === 'Work from home'
                && !$wfhApproved
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
        $usersById = empty($userIds) ? collect() : \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');

        // Counted so the message can tell the truth. It used to say "Attendance
        // saved" unconditionally — including when every row was skipped and
        // nothing at all was written.
        $cleared = 0; $saved = 0; $onLeave = 0; $onPending = 0; $invalidForLeave = 0; $invalidForHoliday = 0;

        foreach ($request->attendance as $userId => $status) {
            if (empty($status)) continue;

            $attendance = $existingAttendances->get($userId);
            $claim = $leaveClaims->get((int) $userId);
            $partDay = $claim && self::leaveKind($claim) !== 'full';

            // "Sunday working"/"Holiday working" don't mean anything on a day
            // that already has a leave claim covering part of it -- the register's
            // own hint text for a part-day leave only ever offers P/HD/A (or
            // WFH), never these two. Reject rather than let them fall through
            // to the generic path and silently detach the day from its leave.
            if ($partDay && in_array($status, ['Sunday working', 'Holiday working'], true)) {
                $invalidForLeave++;
                continue;
            }

            // On a full holiday, the only thing worth recording is whether the
            // person actually worked it - "Holiday working" - or nothing at all
            // (the day already reads Holiday with nothing marked). Present/Absent/
            // Half day/WFH/Sunday working make no sense on a day nobody was
            // expected to work; the sheet's own select no longer offers them for
            // a holiday cell, but reject here too in case of a stale/crafted
            // submission (owner's call, 2026-10-24).
            $markingUser = $usersById->get((int) $userId);
            $isHolidayDay = $markingUser
                && \App\Services\AttendanceDayStatus::holidayForUserOnDateSingle($markingUser, $date)?->isFullDay();
            if ($isHolidayDay && $status !== 'Holiday working' && $status !== '__clear') {
                $invalidForHoliday++;
                continue;
            }

            // Previous submissions can be corrected while the date is open (the
            // window check above already blocked locked dates; Admin/HR always
            // pass). Approved leave is owned by the leave module -- except a
            // part-day leave, where CCare reports the rest of the day.
            if ($attendance && $this->isLeaveStatus($attendance->status) && !$partDay) {
                $onLeave++;
                continue;
            }

            // A full-day leave takes the day from the moment it is applied for,
            // not from the moment it is approved. Clearing stays allowed so a
            // wrongly-marked day can still be removed.
            if ($claim && !$partDay && $status !== '__clear') {
                self::leaveState($claim) === 'pending' ? $onPending++ : $onLeave++;
                continue;
            }

            // The leave module's own placeholder row for a part-day leave is not
            // CCare's to delete -- clearing it would drop the leave link.
            if ($status === '__clear' && $partDay && $this->isUnconfirmedLeaveRow($attendance, $claim)) {
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

            $around = $partDay ? $this->markAroundLeave($dbStatus, $claim, $date) : null;
            if ($around) {
                [$dbStatus, $checkIn, $checkOut, $adminReason] = $around;
                $adminReason = $adminReason ?: null;
            } else {
                $compOffReason = $this->processCompOffLogic($userId, $tenantId, $dbStatus);
                [$dbStatus, $checkIn, $checkOut, $ccReason] = $this->alignWithDecisionMatrix($dbStatus, $date);
                $adminReason = trim(($compOffReason ? $compOffReason . ' | ' : '') . $ccReason) ?: null;
            }
            // Keep the day tied to its approved leave, so the matrix and the
            // report see "short leave + worked" rather than a bare mark.
            $leaveLink = ($partDay && self::leaveState($claim) === 'approved') ? $claim->id : null;

            // Private disk. These used to land on the public disk and were served
            // at /storage/wfh_approvals/<name> with no authentication at all —
            // a manager's approval screenshot readable by anyone with the URL.
            // Served through proof() below instead. Files uploaded before this
            // change still read from the public disk so old links keep working.
            $proofPath = $request->hasFile("wfh_proof.$userId")
                ? $request->file("wfh_proof.$userId")->store('wfh_approvals', 'local')
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
                if ($leaveLink) {
                    $updateData['leave_request_id'] = $leaveLink;
                }
                // Keep real biometric punches if the row already has them
                $hasRealPunch = $attendance->check_in_time && $attendance->check_in_time->format('H:i') !== '00:00';
                if (!$hasRealPunch) {
                    $updateData['check_in_time'] = $checkIn;
                    $updateData['check_out_time'] = $checkOut;
                }
                $attendance->update($updateData);
                $saved++;
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
                    'leave_request_id' => $leaveLink,
                    'created_by_id' => $authId
                ]);
                $saved++;
            }
        }

        // Nothing written is a FAILURE, not a success — reporting it as saved is
        // what let a lost submission look like a completed one.
        if ($saved === 0 && $cleared === 0) {
            if ($onLeave && $onPending) {
                $why = "those salespeople have leave for {$date} — {$onLeave} approved, {$onPending} awaiting approval. The Leave module owns those days.";
            } elseif ($onLeave) {
                $why = "those salespeople are on approved leave for {$date} — leave is managed by the Leave module.";
            } elseif ($onPending) {
                $why = "those salespeople have applied for leave on {$date} and it is still awaiting approval — their attendance cannot be marked until the leave is decided.";
            } elseif ($invalidForLeave) {
                $why = "\"Sunday working\"/\"Holiday working\" can't be marked on a day that already has a leave claim — pick P / A / HD / WFH instead for those.";
            } elseif ($invalidForHoliday) {
                $why = "that date is a holiday — only \"Holiday working\" can be marked on it, or leave it blank.";
            } else {
                $why = 'no attendance status was ticked. Choose P / A / HD / WFH / PS / HP for at least one salesperson.';
            }
            return redirect()->back()->withInput()
                ->with('error', "Nothing was saved — {$why}");
        }

        $parts = [];
        if ($saved)     { $parts[] = $saved . ' ' . str('salesperson')->plural($saved) . ' marked'; }
        if ($cleared)   { $parts[] = $cleared . ' ' . str('entry')->plural($cleared) . ' cleared'; }
        if ($onLeave)   { $parts[] = $onLeave . ' skipped (on approved leave)'; }
        if ($invalidForLeave) { $parts[] = $invalidForLeave . ' skipped ("Sunday working"/"Holiday working" is not valid on a leave day)'; }
        if ($invalidForHoliday) { $parts[] = $invalidForHoliday . ' skipped (only "Holiday working" is valid on a holiday)'; }
        if ($onPending) { $parts[] = $onPending . ' skipped (leave applied, awaiting approval)'; }
        $msg = 'Attendance saved for ' . Carbon::parse($date)->format('d M Y') . ' — ' . implode(', ', $parts) . '.';

        return redirect()->route('cc-attendance.index', ['date' => $date])->with('success', $msg);
    }

    /**
     * Serve a WFH approval snapshot, to the people entitled to see it.
     *
     * Allowed: whoever may mark this salesperson's attendance (their CCare
     * owner, or an attendance admin) and the salesperson themselves. Everyone
     * else gets a 403 — these are screenshots of a private conversation with
     * someone's reporting manager.
     */
    public function proof($id)
    {
        $user = Auth::user();
        $row  = Attendance::withTrashed()->findOrFail($id);

        $allowed = $row->user_id === $user->id
            || in_array($row->user_id, $this->allowedSalespersonIds($user));

        if (!$allowed) {
            abort(403, 'You are not allowed to view this approval snapshot.');
        }
        if (!$row->attachment) {
            abort(404);
        }

        // 'local' first for anything uploaded since the fix, 'public' for the
        // snapshots that predate it.
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($row->attachment)) {
                return Storage::disk($disk)->response(
                    $row->attachment,
                    basename($row->attachment),
                    ['Cache-Control' => 'private, no-store']
                );
            }
        }

        abort(404);
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
            $mappings = \App\Models\CcSalespersonMap::where('role', 'ccare')->with(['salesUser' => function ($q) use ($startOfMonth) {
                // Terminated staff drop off the register once this month is past
                // their last working day; attendance from their tenure stays visible.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminatedAsOf($startOfMonth)
                  ->with('designation');
            }, 'ccUser'])->get();
            $salespeople = $mappings->pluck('salesUser')->filter()->map(function($salesUser) use ($mappings) {
                $map = $mappings->firstWhere('sales_user_id', $salesUser->id);
                $salesUser->mapped_cc_name = $map && $map->ccUser ? $map->ccUser->name : 'N/A';
                return $salesUser;
            })->unique('id')->values();
        } else {
            $salespeople = $user->mappedSalespersons()->where('role', 'ccare')->with(['salesUser' => function ($q) use ($startOfMonth) {
                // Terminated staff drop off the register once this month is past
                // their last working day; attendance from their tenure stays visible.
                $q->select('id', 'first_name', 'last_name', 'code', 'status', 'designation_id', 'department_id', 'profile_picture')
                  ->notTerminatedAsOf($startOfMonth)
                  ->with('designation');
            }])->get()->pluck('salesUser')->filter()->unique('id')->values();
        }

        // Fetch their attendance for the month
        $attendancesDb = Attendance::whereIn('user_id', $salespeople->pluck('id'))
            ->whereBetween('check_in_time', [$startOfMonth, $endOfMonth])
            ->get();

        // Leave applied for or approved in the month, per person per day, so
        // each cell can show it: $monthLeaves[$userId][$dateStr] = LeaveRequest.
        $monthLeaves = [];
        $leavesDb = \App\Models\LeaveRequest::with('leaveType')
            ->whereIn('user_id', $salespeople->pluck('id'))
            ->whereIn('status', ['pending', 'approved'])
            ->whereNull('deleted_at')
            ->whereDate('from_date', '<=', $endOfMonth->toDateString())
            ->whereDate('to_date', '>=', $startOfMonth->toDateString())
            ->get();
        foreach ($leavesDb as $lv) {
            $d = $lv->from_date->copy()->max($startOfMonth);
            $last = $lv->to_date->copy()->min($endOfMonth);
            for (; $d->lte($last); $d->addDay()) {
                $monthLeaves[$lv->user_id][$d->format('Y-m-d')] = $lv;
            }
        }

        // Organize attendances: $attendances[$userId][$dateStr] = $attendance.
        // The leave module's placeholder row for a part-day leave is left out:
        // it is not a mark, so the cell stays open for CCare, as on the daily page.
        $attendances = [];
        foreach ($attendancesDb as $att) {
            $d = $att->check_in_time->format('Y-m-d');
            if ($this->isUnconfirmedLeaveRow($att, $monthLeaves[$att->user_id][$d] ?? null)) {
                continue;
            }
            $attendances[$att->user_id][$d] = $att;
        }

        $canSeeReport = $this->canSeeReport($user);

        // Days that can still be marked on this sheet: up to today and not past
        // their working-day deadline (lockDeadline). It was a fixed 3 calendar
        // days, so a Sunday or holiday hid days that were still open. CCare also
        // gets any unlock window; Admin/HR keep the deadline window here and use
        // the daily page for older dates.
        $openDates = [];
        for ($d = $startOfMonth->copy(); $d->lte($endOfMonth) && $d->lte(Carbon::today()); $d->addDay()) {
            $ds = $d->toDateString();
            $open = $this->isAttendanceAdmin($user)
                ? !now()->greaterThan($this->lockDeadline($ds, $user))
                : !$this->isLockedFor($ds, $user);
            if ($open) {
                $openDates[$ds] = true;
            }
        }

        return view('tenant.cc_attendance.monthly', compact('salespeople', 'month', 'year', 'daysInMonth', 'startOfMonth', 'attendances', 'monthLeaves', 'user', 'canSeeReport', 'openDates'));
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
        $skippedForLeave = 0;

        // Every date being submitted, so leave claims are fetched once rather
        // than per cell -- a month grid for a whole team is a lot of cells.
        $allDates = collect($request->attendance)->flatMap(fn ($d) => array_keys($d))->unique()->values();
        $claimsByDate = [];
        foreach ($allDates as $d) {
            $claimsByDate[$d] = $this->leaveClaimsByUser(
                array_map('intval', array_keys($request->attendance)),
                $d
            );
        }

        foreach ($request->attendance as $userId => $dates) {
            foreach ($dates as $dateStr => $status) {
                if (empty($status)) continue;

                // Window: open until D+2 12:00 PM; Admin/HR always open. No future dates.
                if ($dateStr > $maxDate) continue;
                if ($this->isLockedFor($dateStr, Auth::user())) continue;

                // Allowed statuses
                if (!in_array($status, ['Present', 'Absent', 'Half day', 'Work from home', 'Sunday working', 'Holiday working'])) continue;

                // A full-day leave, applied for or approved, takes the day out
                // of CCare's hands, same rule as the daily register. A part-day
                // leave (short, half day, WFH) leaves the rest for CCare.
                $claim = isset($claimsByDate[$dateStr]) ? $claimsByDate[$dateStr]->get((int) $userId) : null;
                $partDay = $claim && self::leaveKind($claim) !== 'full';
                if ($claim && !$partDay) {
                    $skippedForLeave++;
                    continue;
                }
                // Same guard as the daily register's store(): these two don't
                // mean anything on a day that already has a leave claim.
                if ($partDay && in_array($status, ['Sunday working', 'Holiday working'], true)) {
                    $skippedForLeave++;
                    continue;
                }

                $existing = Attendance::where('user_id', $userId)
                    ->whereDate('check_in_time', $dateStr)
                    ->first();

                // Corrections allowed while the date is open; approved leave is
                // owned by the leave module and never overwritten here.
                if ($existing && $this->isLeaveStatus($existing->status) && !$partDay) {
                    $skippedForLeave++;
                    continue;
                }

                $dbStatus = $status;
                $note = $request->notes[$userId][$dateStr] ?? null;

                $around = $partDay ? $this->markAroundLeave($dbStatus, $claim, $dateStr) : null;
                if ($around) {
                    [$dbStatus, $checkIn, $checkOut, $adminReason] = $around;
                    $adminReason = $adminReason ?: null;
                } else {
                    $compOffReason = $this->processCompOffLogic($userId, $tenantId, $dbStatus);
                    [$dbStatus, $checkIn, $checkOut, $ccReason] = $this->alignWithDecisionMatrix($dbStatus, $dateStr);
                    $adminReason = trim(($compOffReason ? $compOffReason . ' | ' : '') . $ccReason) ?: null;
                }
                $leaveLink = ($partDay && self::leaveState($claim) === 'approved') ? $claim->id : null;

                if ($existing) {
                    $updateData = [
                        'status' => $dbStatus,
                        'notes' => $note,
                        'admin_reason' => $adminReason,
                        'updated_by_id' => $authId
                    ];
                    if ($leaveLink) {
                        $updateData['leave_request_id'] = $leaveLink;
                    }
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
                        'leave_request_id' => $leaveLink,
                        'created_by_id' => $authId
                    ]);
                }
                $savedCount++;
            }
        }

        // Say what was skipped. A silent skip is how somebody concludes the
        // save worked and only finds out at payroll that it did not.
        $msg = "Attendance marked successfully. $savedCount records processed.";
        if ($skippedForLeave) {
            $msg .= " {$skippedForLeave} skipped — those days have leave applied or approved, which the Leave module owns.";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Process Compensatory Off logic for Sunday/Holiday working (credit) and Absent (debit).
     */
    private function processCompOffLogic($userId, $tenantId, &$dbStatus)
    {
        $compOffType = \App\Models\LeaveType::where('code', 'ALP')->first();

        if (!$compOffType) return null;

        if ($dbStatus === 'Sunday working' || $dbStatus === 'Holiday working') {
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
            case 'Holiday working':
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

    // ─── Unlock windows (Admin/HR) ────────────────────────────────────────

    /** Manage screen: existing windows + the pool of users a window can target. */
    public function unlocks(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->isAttendanceAdmin($user), 403);

        $windows = \App\Models\CcAttendanceUnlock::with('creator')->orderByDesc('id')->get();

        // Anyone who fills the register: people mapped to salespeople, plus
        // whoever has already been granted the manage permission.
        $markerIds = \App\Models\CcSalespersonMap::query()->distinct()->pluck('cc_user_id');
        $people = \App\Models\User::where(fn ($q) => $q
                ->whereIn('id', $markerIds)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'hr', 'manager'])))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'code']);

        // A window can reference a user who has since left the picker's pool
        // (role changed, unmapped) - fetch those separately so the "N selected"
        // popup can still show a real name instead of just an id.
        $allSelectedIds = $windows->where('scope', 'selected')
            ->flatMap(fn ($w) => $w->user_ids ?? [])->unique()->values();
        $missingIds = $allSelectedIds->diff($people->pluck('id'));
        $extraPeople = $missingIds->isEmpty()
            ? collect()
            : \App\Models\User::withTrashed()->whereIn('id', $missingIds)->get(['id', 'first_name', 'last_name', 'code']);

        $peopleById = $people->concat($extraPeople)->keyBy('id');

        return view('tenant.cc_attendance.unlocks', compact('windows', 'people', 'peopleById', 'user'));
    }

    /** Open a window. Capped at CcAttendanceUnlock::MAX_DAYS. */
    public function storeUnlock(Request $request)
    {
        abort_unless($this->isAttendanceAdmin(Auth::user()), 403);

        $data = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date'   => ['required', 'date', 'after_or_equal:from_date'],
            'scope'     => ['required', 'in:all,selected'],
            'user_ids'  => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'reason'    => ['nullable', 'string', 'max:255'],
        ]);

        $days = Carbon::parse($data['from_date'])->diffInDays(Carbon::parse($data['to_date'])) + 1;
        if ($days > \App\Models\CcAttendanceUnlock::MAX_DAYS) {
            return back()->with('error', "A window may cover at most "
                . \App\Models\CcAttendanceUnlock::MAX_DAYS . " days — you asked for {$days}.");
        }
        if ($data['scope'] === 'selected' && empty($data['user_ids'])) {
            return back()->with('error', 'Pick at least one person, or choose "Everyone".');
        }

        \App\Models\CcAttendanceUnlock::create([
            'from_date' => $data['from_date'],
            'to_date'   => $data['to_date'],
            'scope'     => $data['scope'],
            'user_ids'  => $data['scope'] === 'selected' ? array_values(array_map('intval', $data['user_ids'])) : null,
            'is_active' => true,
            'reason'    => $data['reason'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', "Attendance unlocked for {$days} day(s).");
    }

    /** Enable / disable without losing the record. */
    public function toggleUnlock($id)
    {
        abort_unless($this->isAttendanceAdmin(Auth::user()), 403);
        $w = \App\Models\CcAttendanceUnlock::findOrFail($id);
        $w->is_active = !$w->is_active;
        $w->save();

        return back()->with('success', 'Window ' . ($w->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroyUnlock($id)
    {
        abort_unless($this->isAttendanceAdmin(Auth::user()), 403);
        \App\Models\CcAttendanceUnlock::findOrFail($id)->delete();

        return back()->with('success', 'Window removed.');
    }
}
