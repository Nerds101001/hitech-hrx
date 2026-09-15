<?php

namespace App\Http\Controllers\tenant;

use App\Exports\AttendanceLogsExport;
use App\Exports\AttendanceRegistryExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Kept separate from AttendanceController on purpose: that file is huge and
 * obfuscated on prod, so a new controller here is a plain new upload with no
 * drift/patch risk, instead of a surgical edit to an existing one.
 */
class AttendanceExportController extends Controller
{
    // Same manager/self scoping as AttendanceController::getScopedUserIds().
    private function getScopedUserIds($user)
    {
        if ($user->hasRole(['admin', 'hr', 'accounts'])) {
            return null;
        }
        if ($user->hasRole('manager')) {
            $ids = User::where('reporting_to_id', $user->id)->notTerminated()->pluck('id')->toArray();
            $ids[] = $user->id;
            return $ids;
        }
        return [$user->id];
    }

    // Mirrors indexAjax's own filters exactly (Real-Time Logs tab), minus
    // pagination/search-highlighting — see AttendanceController::indexAjax().
    public function logs(Request $request)
    {
        $currentUser = auth()->user();
        $scopedIds = $this->getScopedUserIds($currentUser);

        $query = Attendance::query()->with(['user.department', 'shift']);
        if ($scopedIds) {
            $query->whereIn('user_id', $scopedIds);
        }
        if ($request->filled('userId')) {
            $query->where('user_id', $request->input('userId'));
        }
        if ($request->filled('shiftId')) {
            $query->where('shift_id', $request->input('shiftId'));
        }
        if ($request->filled('siteId')) {
            $siteId = $request->input('siteId');
            $query->whereHas('user', fn ($q) => $q->where('site_id', $siteId));
        }
        if ($request->filled('departmentId')) {
            $departmentId = $request->input('departmentId');
            $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
        }
        if ($request->filled('startDate') && $request->filled('endDate')) {
            $start = Carbon::parse($request->input('startDate'))->startOfDay();
            $end = Carbon::parse($request->input('endDate'))->endOfDay();
            $query->whereBetween('check_in_time', [$start, $end]);
        } elseif ($request->filled('date')) {
            try {
                $query->whereDate('check_in_time', Carbon::parse($request->input('date'))->toDateString());
            } catch (\Exception $e) {
                $query->whereDate('check_in_time', Carbon::today());
            }
        } else {
            $query->whereDate('check_in_time', Carbon::today());
        }
        if ($request->filled('searchTerm')) {
            $term = $request->input('searchTerm');
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%");
            });
        }
        $query->orderBy('check_in_time', 'desc');

        return Excel::download(new AttendanceLogsExport($query), 'attendance-logs-' . now()->format('Y-m-d_His') . '.xlsx');
    }

    // Reuses AttendanceController::registryAjax() itself for the Monthly
    // Registry data — that method's day-by-day status logic is the single
    // source of truth already used on screen, so the export can't drift from
    // what the user is actually looking at.
    public function registry(Request $request)
    {
        $controller = new AttendanceController();
        $response = $controller->registryAjax($request);
        $payload = json_decode($response->getContent(), true);

        $rows = $payload['data'] ?? [];
        $daysInMonth = $payload['daysInMonth'] ?? 31;

        // The legend chips and sort control are client-side only (they filter
        // the already-fetched registry payload in the browser) — replicate
        // both here so "export carries current filters" also covers them.
        $legendStatus = $request->input('legendStatus');
        if ($legendStatus) {
            $rows = array_values(array_filter($rows, function ($row) use ($daysInMonth, $legendStatus) {
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $day = $row['day_' . $d] ?? null;
                    if (!$day) continue;
                    if ($legendStatus === 'Short Leave') {
                        if (!empty($day['is_short_leave'])) return true;
                        continue;
                    }
                    $status = $day['status'] ?? null;
                    if (!$status) continue;
                    if ($legendStatus === 'Present' && in_array($status, ['Present', 'Present*'], true)) return true;
                    if ($legendStatus === 'SP' && in_array($status, ['SP', 'HP'], true)) return true;
                    if ($status === $legendStatus) return true;
                }
                return false;
            }));
        }

        $sort = $request->input('sort', 'name_asc');
        usort($rows, function ($a, $b) use ($sort) {
            $num = fn ($v) => (float) ($v ?? 0);
            $name = fn ($r) => strtolower($r['employee'] ?? '');
            switch ($sort) {
                case 'name_desc':     return strcmp($name($b), $name($a));
                case 'code_asc':      return strnatcasecmp($a['code'] ?? '', $b['code'] ?? '');
                case 'absents_desc':  return $num($b['absents']) <=> $num($a['absents']);
                case 'presents_desc': return $num($b['presents']) <=> $num($a['presents']);
                case 'lates_desc':    return $num($b['lates']) <=> $num($a['lates']);
                case 'leaves_desc':   return $num($b['leaves']) <=> $num($a['leaves']);
                default:              return strcmp($name($a), $name($b));
            }
        });

        $month = (int) ($payload['month'] ?? now()->month);
        $year = (int) ($payload['year'] ?? now()->year);

        return Excel::download(
            new AttendanceRegistryExport($rows, $daysInMonth, $month, $year),
            'attendance-registry-' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }
}
