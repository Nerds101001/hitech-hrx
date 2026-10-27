<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeePromotion;
use App\Models\EmployeeTransfer;
use App\Models\EmployeeWarning;
use App\Models\EmployeeResignation;
use App\Models\EmployeeTermination;
use App\Models\EmployeeComplaint;
use App\Models\EmployeeTrip;
use App\Models\Announcement;
use App\Models\User;
use App\Models\Designation;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EmployeeLifecycleController extends Controller
{
    /**
     * Display employee lifecycle dashboard
     */
    public function index(): View
    {
        $user = Auth::user();
        $isManager = $user->hasRole('manager') && !$user->hasRole(['admin', 'hr']) && !$user->can('lifecycle.manage');
        $managedTeamIds = [];
        if ($isManager) {
            $managedTeamIds = Team::where('team_head_id', $user->id)->pluck('id')->toArray();
        }

        // Get statistics
        $statsQuery = function($model) use ($isManager, $managedTeamIds) {
            $q = $model::query();
            if ($isManager) {
                $q->whereHas('user', function($qu) use ($managedTeamIds) {
                    $qu->whereIn('team_id', $managedTeamIds);
                });
            }
            return $q;
        };

        $stats = [
            'pending_promotions' => $statsQuery(EmployeePromotion::class)->pending()->count(),
            'pending_transfers' => $statsQuery(EmployeeTransfer::class)->pending()->count(),
            'active_warnings' => $statsQuery(EmployeeWarning::class)->active()->count(),
            'pending_resignations' => $statsQuery(EmployeeResignation::class)->pending()->count(),
            'open_complaints' => $statsQuery(EmployeeComplaint::class)->open()->count(),
            'pending_trips' => $statsQuery(EmployeeTrip::class)->pending()->count(),
            'active_announcements' => Announcement::current()->count(),
        ];

        // Get recent activities
        $recentPromotions = $statsQuery(EmployeePromotion::class)->latest()->limit(5)->get();
        $recentTransfers = $statsQuery(EmployeeTransfer::class)->latest()->limit(5)->get();
        $recentWarnings = $statsQuery(EmployeeWarning::class)->latest()->limit(5)->get();

        // Real 7-month lifecycle trend. The chart previously rendered four
        // hardcoded demo series (the ApexCharts docs sample data) — and never
        // even reached the browser, because it was pushed to a @stack the
        // layout did not render.
        $trendStart = now()->copy()->startOfMonth()->subMonths(6);
        $monthLabels = [];
        for ($i = 0; $i < 7; $i++) {
            $monthLabels[$trendStart->copy()->addMonths($i)->format('Y-m')] =
                $trendStart->copy()->addMonths($i)->format('M Y');
        }

        $seriesFor = function ($model) use ($statsQuery, $trendStart, $monthLabels) {
            $rows = $statsQuery($model)
                ->where('created_at', '>=', $trendStart)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
                ->groupBy('ym')
                ->pluck('total', 'ym');
            return collect(array_keys($monthLabels))
                ->map(fn($ym) => (int) ($rows[$ym] ?? 0))->all();
        };

        $lifecycleChart = [
            'labels' => array_values($monthLabels),
            'series' => [
                ['name' => 'Promotions', 'data' => $seriesFor(EmployeePromotion::class)],
                ['name' => 'Transfers',  'data' => $seriesFor(EmployeeTransfer::class)],
                ['name' => 'Warnings',   'data' => $seriesFor(EmployeeWarning::class)],
                ['name' => 'Complaints', 'data' => $seriesFor(EmployeeComplaint::class)],
            ],
        ];

        // Everything the tabbed page needs in one request. The lifecycle used
        // to be eight separate sidebar entries and eight page loads; it is now
        // one page with tabs, so each dataset is loaded here.
        $tabPromotions   = $statsQuery(EmployeePromotion::class)->with('user')->latest()->limit(100)->get();
        $tabTransfers    = $statsQuery(EmployeeTransfer::class)->with('user')->latest()->limit(100)->get();
        $tabWarnings     = $statsQuery(EmployeeWarning::class)->with('user')->latest()->limit(100)->get();
        $tabComplaints   = $statsQuery(EmployeeComplaint::class)->with('user')->latest()->limit(100)->get();
        $tabTrips        = $statsQuery(EmployeeTrip::class)->with('user')->latest()->limit(100)->get();

        // Exits come from the users table as well as the dedicated tables —
        // see exitRows() for why.
        $tabResignations = $this->exitRows(true)->getCollection();
        $tabTerminations = $this->exitRows(false)->getCollection();

        $employees = User::notTerminated()
            ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'code', 'designation_id', 'department_id']);

        $departments  = \App\Models\Department::withTrashed()->orderBy('name')->get(['id', 'name']);
        $designations = \App\Models\Designation::orderBy('name')->get(['id', 'name']);

        return view('tenant.employee-lifecycle.index', compact(
            'stats',
            'recentPromotions',
            'recentTransfers',
            'recentWarnings',
            'lifecycleChart',
            'tabPromotions',
            'tabTransfers',
            'tabWarnings',
            'tabComplaints',
            'tabTrips',
            'tabResignations',
            'tabTerminations',
            'employees',
            'departments',
            'designations'
        ));
    }

    /**
     * Display promotions page
     */
    public function promotions(): View
    {
        $user = Auth::user();
        $isManager = $user->hasRole('manager') && !$user->hasRole(['admin', 'hr']) && !$user->can('lifecycle.manage');
        
        $query = EmployeePromotion::with(['user', 'previousDesignation', 'newDesignation', 'approvedBy']);
        
        if ($isManager) {
            $managedTeamIds = Team::where('team_head_id', $user->id)->pluck('id')->toArray();
            $query->whereHas('user', function($q) use ($managedTeamIds) {
                $q->whereIn('team_id', $managedTeamIds);
            });
        }

        $promotions = $query->latest()->paginate(15);

        return view('tenant.employee-lifecycle.promotions', compact('promotions'));
    }

    /**
     * Store new promotion
     */
    public function storePromotion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'new_designation_id' => 'required|exists:designations,id',
            'promotion_type' => 'required|in:merit,seniority,performance',
            'promotion_date' => 'required|date',
            'salary_increase' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $validated['previous_designation_id'] = $user->designation_id;

        EmployeePromotion::create($validated);

        return redirect()->back()->with('success', 'Promotion request submitted successfully.');
    }

    /**
     * Approve promotion
     */
    public function approvePromotion(EmployeePromotion $promotion): RedirectResponse
    {
        $promotion->update([
            'status' => 'approved',
            'approved_by_id' => Auth::id(),
        ]);

        // Update user's designation
        if ($promotion->newDesignation) {
            $promotion->user->update(['designation_id' => $promotion->new_designation_id]);
        }

        return redirect()->back()->with('success', 'Promotion approved successfully.');
    }

    /**
     * Display transfers page
     */
    public function transfers(): View
    {
        $transfers = EmployeeTransfer::with(['user', 'fromDepartment', 'toDepartment', 'fromTeam', 'toTeam', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.transfers', compact('transfers'));
    }

    /**
     * Store new transfer
     */
    public function storeTransfer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'to_department_id' => 'required|exists:departments,id',
            'to_team_id' => 'nullable|exists:teams,id',
            'transfer_date' => 'required|date',
            'effective_date' => 'required|date|after_or_equal:transfer_date',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $validated['from_department_id'] = $user->department_id;
        $validated['from_team_id'] = $user->team_id;

        EmployeeTransfer::create($validated);

        return redirect()->back()->with('success', 'Transfer request submitted successfully.');
    }

    /**
     * Approve transfer
     */
    public function approveTransfer(EmployeeTransfer $transfer): RedirectResponse
    {
        $transfer->update([
            'status' => 'approved',
            'approved_by_id' => Auth::id(),
        ]);

        // Update user's department and team
        $transfer->user->update([
            'department_id' => $transfer->to_department_id,
            'team_id' => $transfer->to_team_id,
        ]);

        return redirect()->back()->with('success', 'Transfer approved successfully.');
    }

    /**
     * Display warnings page
     */
    public function warnings(): View
    {
        $warnings = EmployeeWarning::with(['user', 'givenBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.warnings', compact('warnings'));
    }

    /**
     * Store new warning
     */
    public function storeWarning(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'warning_type' => 'required|in:verbal,written,performance,attendance',
            'severity' => 'required|in:low,medium,high,critical',
            'warning_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'action_taken' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $validated['given_by_id'] = Auth::id();

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . '_' . \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $validated['attachment_path'] = $file->storeAs('employee-warnings', $filename, 'r2');
        }
        unset($validated['attachment']);

        $warning = EmployeeWarning::create($validated);

        \App\Services\HrNotificationService::notifyLifecycleNotice($warning, 'warning');

        return redirect()->back()->with('success', 'Warning recorded successfully.');
    }

    /**
     * Display resignations page
     */
    /**
     * Exit types recorded on the users table that count as a resignation
     * rather than a termination.
     */
    private const RESIGNATION_TYPES = ['resignation', 'resignation_without_notice_period'];

    /**
     * Builds a uniform row set from BOTH exit paths.
     *
     * Terminations and resignations are actually performed by
     * EmployeeController's exit action, which writes status / exit_date /
     * last_working_day / termination_type / exit_reason straight onto the user
     * and never creates an employee_terminations or employee_resignations row.
     * Reading only those tables showed an empty page while 24 real exits sat in
     * `users`, so both sources are merged here and shaped alike for the view.
     */
    private function exitRows(bool $wantResignations)
    {
        $types = self::RESIGNATION_TYPES;

        $users = User::whereNotNull('termination_type')
            ->when($wantResignations,
                fn ($q) => $q->whereIn('termination_type', $types),
                fn ($q) => $q->whereNotIn('termination_type', $types))
            ->with('department')
            ->get()
            ->map(function ($u) {
                $type = $u->termination_type instanceof \App\Enums\TerminationType
                    ? $u->termination_type->value
                    : (string) $u->termination_type;

                $asDate = fn ($v) => $v ? \Carbon\Carbon::parse($v) : null;
                $exit   = $asDate($u->exit_date);
                $lwd    = $asDate($u->last_working_day);

                return (object) [
                    'id'                => null,   // no dedicated row to act on
                    'user'              => $u,
                    'notice_date'       => $exit,
                    'resignation_date'  => $exit,
                    'relieving_date'    => $lwd,
                    'termination_date'  => $lwd ?? $exit,
                    'effective_date'    => $lwd ?? $exit,
                    'reason'            => $u->exit_reason,
                    'reason_type'       => $type,
                    'termination_type'  => $type,
                    'type'              => $type,   // the terminations view uses ->type
                    'status'            => 'completed',
                    'is_rehireable'     => (bool) $u->is_eligible_for_rehire,
                    'approvedBy'        => null,
                    'processedBy'       => null,
                    'from_user_record'  => true,
                ];
            });

        $model = $wantResignations ? EmployeeResignation::class : EmployeeTermination::class;
        $rows  = $model::with(['user', 'approvedBy'])->latest()->get()
            ->map(function ($r) {
                $r->from_user_record = false;
                return $r;
            });

        $merged = $rows->concat($users)
            ->sortByDesc(fn ($r) => $r->effective_date ?? $r->resignation_date ?? $r->termination_date)
            ->values();

        // Views call ->links(), so hand back a real paginator over the merge
        $page    = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $perPage = 15;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $merged->forPage($page, $perPage)->values(),
            $merged->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );
    }

    public function resignations(): View
    {
        $resignations = $this->exitRows(true);

        return view('tenant.employee-lifecycle.resignations', compact('resignations'));
    }

    /**
     * Store new resignation
     */
    public function storeResignation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'resignation_date' => 'required|date',
            'last_working_day' => 'required|date|after:resignation_date',
            'reason_type' => 'required|in:voluntary,involuntary,retirement',
            'reason' => 'nullable|string|max:1000',
            'exit_interview_notes' => 'nullable|string|max:1000',
            'is_rehireable' => 'boolean',
        ]);

        // Clearance is still outstanding at this point, so the record starts
        // as pending and only becomes approved once every NOC is in.
        $validated['status'] = 'pending';
        $resignation = EmployeeResignation::create($validated);

        // Raise the IT / HR / Finance / Accounts NOCs and alert everyone.
        $leaver = \App\Models\User::find($validated['user_id']);
        if ($leaver) {
            \App\Services\HrNotificationService::raiseExitClearances(
                $leaver, 'resignation', $resignation->id
            );
        }

        return redirect()->back()->with(
            'success',
            'Resignation recorded. Clearance requests have been sent to IT, HR, Finance and Accounts.'
        );
    }

    /**
     * Display terminations page
     */
    public function terminations(): View
    {
        $terminations = $this->exitRows(false);

        return view('tenant.employee-lifecycle.terminations', compact('terminations'));
    }

    /**
     * Store new termination
     */
    public function storeTermination(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'termination_date' => 'required|date',
            'last_working_day' => 'required|date|after_or_equal:termination_date',
            'termination_type' => 'required|in:layoff,misconduct,performance,violation,contract_end,resignation_without_notice_period',
            'reason' => 'nullable|string|max:1000',
            'remarks' => 'nullable|string|max:1000',
            'is_eligible_for_rehire' => 'boolean',
        ]);

        $returnedAssetIds = $request->input('returned_assets', []);

        DB::beginTransaction();
        try {
            // 1. Create the termination log record
            $terminationData = [
                'user_id' => $validated['user_id'],
                'approved_by_id' => Auth::id(),
                'termination_date' => $validated['termination_date'],
                'last_working_day' => $validated['last_working_day'],
                'termination_type' => $validated['termination_type'],
                'reason' => $validated['reason'] ?? $validated['remarks'] ?? null,
                'termination_notes' => $validated['remarks'] ?? null,
                'is_eligible_for_rehire' => $request->boolean('is_eligible_for_rehire'),
                // The termination takes effect immediately (access is revoked
                // below), but the record stays 'pending' until IT, HR, Finance
                // and Accounts have each given their NOC.
                'status' => 'pending',
            ];
            $termination = EmployeeTermination::create($terminationData);

            // 2. Deactivate the employee's user account
            $user = User::findOrFail($validated['user_id']);
            $user->update([
                'status' => \App\Enums\UserAccountStatus::TERMINATED,
                'exit_date' => $validated['termination_date'],
                'last_working_day' => $validated['last_working_day'],
                'termination_type' => $validated['termination_type'],
                'exit_reason' => $validated['reason'] ?? $validated['remarks'] ?? null,
                'is_eligible_for_rehire' => $request->boolean('is_eligible_for_rehire'),
                'updated_by_id' => Auth::id(),
            ]);

            // 3. Restock selected assets to available
            if (!empty($returnedAssetIds)) {
                $assets = \App\Models\Asset::whereIn('id', $returnedAssetIds)
                    ->where('assigned_to', $user->id)
                    ->get();

                foreach ($assets as $asset) {
                    // Update AssetAssignment history if table exists
                    if (\Illuminate\Support\Facades\Schema::hasTable('asset_assignments')) {
                        \App\Models\AssetAssignment::where('asset_id', $asset->id)
                            ->where('user_id', $user->id)
                            ->whereNull('returned_at')
                            ->update([
                                'returned_at' => now(),
                                'notes' => 'Returned during employee termination',
                            ]);
                    }

                    // Reset asset assignment and set status to available
                    $asset->update([
                        'assigned_to' => null,
                        'status' => 'available',
                    ]);
                }
            }

            // 4. Direct reports of a terminated manager must not dangle with
            // reporting_to_id pointing at someone no longer active - skip up
            // to this manager's own manager. A manager with no manager of
            // their own leaves their reports with reporting_to_id = null
            // rather than guessing.
            User::where('reporting_to_id', $user->id)
                ->where('status', '!=', \App\Enums\UserAccountStatus::TERMINATED)
                ->update(['reporting_to_id' => $user->reporting_to_id]);

            DB::commit();

            // Raise the IT / HR / Finance / Accounts NOCs and alert everyone.
            // After the commit and self-guarded: a mail failure must not undo
            // a termination that has already taken effect.
            \App\Services\HrNotificationService::raiseExitClearances(
                $user, 'termination', $termination->id
            );

            return redirect()->back()->with(
                'success',
                'Termination recorded and access revoked. Clearance requests sent to IT, HR, Finance and Accounts.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Termination store error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process termination: ' . $e->getMessage());
        }
    }

    /**
     * Get list of assets assigned to a user (AJAX)
     */
    public function getUserAssetsAjax($userId)
    {
        try {
            $assets = \App\Models\Asset::where('assigned_to', $userId)
                ->select('id', 'name', 'asset_code', 'serial_number')
                ->get();

            return response()->json([
                'success' => true,
                'assets' => $assets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load user assets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display complaints page
     */
    public function complaints(): View
    {
        $complaints = EmployeeComplaint::with(['complainant', 'respondent', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.complaints', compact('complaints'));
    }

    /**
     * Store new complaint
     */
    public function storeComplaint(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'complaint_type' => 'required|in:harassment,discrimination,safety,policy',
            'severity' => 'required|in:low,medium,high,critical',
            'complaint_date' => 'required|date',
            'description' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $data = [
            'respondent_id' => $validated['user_id'],
            'complainant_id' => Auth::id(),
            'complaint_type' => $validated['complaint_type'],
            'severity' => $validated['severity'],
            'complaint_date' => $validated['complaint_date'],
            'description' => $validated['description'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . '_' . \Illuminate\Support\Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $data['attachment_path'] = $file->storeAs('employee-complaints', $filename, 'r2');
        }

        $complaint = EmployeeComplaint::create($data);

        \App\Services\HrNotificationService::notifyLifecycleNotice($complaint, 'complaint');

        return redirect()->back()->with('success', 'Complaint recorded successfully.');
    }

    /**
     * Self-service: the signed-in user's own warnings and complaints only —
     * never anyone else's. Warnings/complaints are lodged solely by HR/Admin
     * (see the role-gated routes), but the employee they concern can always
     * see what was recorded about them.
     */
    public function myNotices(): View
    {
        $user = Auth::user();
        $userId = $user->id;

        $myWarnings = EmployeeWarning::with('givenBy')
            ->where('user_id', $userId)
            ->latest()
            ->get();

        $myComplaints = EmployeeComplaint::with(['complainant', 'approvedBy'])
            ->where('respondent_id', $userId)
            ->latest()
            ->get();

        // "My Team": read-only visibility into what's recorded about this
        // user's own direct reports, so a manager is never surprised by
        // something raised against one of their people. Nothing here is
        // editable or acknowledgeable by the manager — only the employee
        // the record concerns can do that.
        $directReportIds = User::whereIn('reporting_to_id', $user->managerIdentityIds())
            ->pluck('id');

        $teamWarnings = $directReportIds->isNotEmpty()
            ? EmployeeWarning::with(['user', 'givenBy'])->whereIn('user_id', $directReportIds)->latest()->get()
            : collect();

        $teamComplaints = $directReportIds->isNotEmpty()
            ? EmployeeComplaint::with(['respondent', 'complainant'])->whereIn('respondent_id', $directReportIds)->latest()->get()
            : collect();

        return view('tenant.employee-lifecycle.my-notices', compact(
            'myWarnings', 'myComplaints', 'teamWarnings', 'teamComplaints'
        ));
    }

    /**
     * The signed-in employee acknowledges a warning issued to them. Nobody
     * else — not even HR/Admin — can acknowledge on someone else's behalf.
     */
    public function acknowledgeWarning(EmployeeWarning $warning): RedirectResponse
    {
        if ($warning->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$warning->acknowledged_at) {
            $warning->update(['acknowledged_at' => now()]);
        }

        // Explicit destination, not back(): back() falls through to the site
        // root when Laravel has no usable "previous URL" in session (e.g. a
        // delegate-access session, or a background poll overwriting it), and
        // the root route IS the dashboard (routes/web.php) — so the click
        // silently landed on the dashboard instead of staying on this page.
        return redirect()->route('employee-lifecycle.my-notices')->with('success', 'Acknowledged.');
    }

    /**
     * The signed-in employee acknowledges a complaint recorded against them.
     */
    public function acknowledgeComplaint(EmployeeComplaint $complaint): RedirectResponse
    {
        if ($complaint->respondent_id !== Auth::id()) {
            abort(403);
        }

        if (!$complaint->acknowledged_at) {
            $complaint->update(['acknowledged_at' => now()]);
        }

        // See the comment in acknowledgeWarning() above: explicit destination,
        // not back(), so a stale/missing "previous URL" can't silently bounce
        // this to the dashboard instead of this page.
        return redirect()->route('employee-lifecycle.my-notices')->with('success', 'Acknowledged.');
    }

    /**
     * The signed-in employee replies to a warning issued to them, from the
     * "Read more" popup. One reply per record — posting again overwrites it
     * rather than threading, to match what the popup shows. Visible to
     * HR/Admin on the same popup immediately; no notification is sent.
     */
    public function replyToWarning(Request $request, EmployeeWarning $warning): RedirectResponse
    {
        if ($warning->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_remarks' => 'required|string|max:1000',
        ]);

        $warning->update([
            'employee_remarks' => $validated['employee_remarks'],
            'employee_remarks_at' => now(),
        ]);

        // See acknowledgeWarning() above for why this is an explicit route,
        // not back().
        return redirect()->route('employee-lifecycle.my-notices')->with('success', 'Your reply was posted.');
    }

    /**
     * The signed-in employee replies to a complaint recorded against them.
     */
    public function replyToComplaint(Request $request, EmployeeComplaint $complaint): RedirectResponse
    {
        if ($complaint->respondent_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_remarks' => 'required|string|max:1000',
        ]);

        $complaint->update([
            'employee_remarks' => $validated['employee_remarks'],
            'employee_remarks_at' => now(),
        ]);

        // See acknowledgeWarning() above for why this is an explicit route,
        // not back().
        return redirect()->route('employee-lifecycle.my-notices')->with('success', 'Your reply was posted.');
    }

    /**
     * Whether $user may view this record's attachment: the employee it
     * concerns, their reporting manager (direct, not grand-manager), or
     * HR/Admin.
     */
    private function canViewLifecycleRecord(User $user, int $subjectUserId): bool
    {
        if ($subjectUserId === $user->id) {
            return true;
        }
        if ($user->hasRole(['admin', 'hr']) || $user->can('lifecycle.manage')) {
            return true;
        }

        $subject = User::find($subjectUserId);
        return $subject && in_array($subject->reporting_to_id, $user->managerIdentityIds(), true);
    }

    /**
     * Download a warning's attachment. Allowed for HR/Admin, the employee
     * the warning was issued to, or their reporting manager — nobody else.
     */
    public function downloadWarningAttachment(EmployeeWarning $warning)
    {
        if (!$this->canViewLifecycleRecord(Auth::user(), $warning->user_id)) {
            abort(403);
        }

        if (!$warning->attachment_path || !Storage::disk('r2')->exists($warning->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return redirect()->away(Storage::disk('r2')->temporaryUrl($warning->attachment_path, now()->addMinutes(30)));
    }

    /**
     * Download a complaint's attachment. Allowed for HR/Admin, the employee
     * the complaint concerns (the respondent), or their reporting manager.
     */
    public function downloadComplaintAttachment(EmployeeComplaint $complaint)
    {
        $user = Auth::user();
        $isComplainant = $complaint->complainant_id === $user->id;
        if (!$isComplainant && !$this->canViewLifecycleRecord($user, $complaint->respondent_id)) {
            abort(403);
        }

        if (!$complaint->attachment_path || !Storage::disk('r2')->exists($complaint->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return redirect()->away(Storage::disk('r2')->temporaryUrl($complaint->attachment_path, now()->addMinutes(30)));
    }

    /**
     * Display trips page
     */
    public function trips(): View
    {
        $trips = EmployeeTrip::with(['user', 'approvedBy'])
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.trips', compact('trips'));
    }

    /**
     * Store new trip
     */
    public function storeTrip(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'trip_title' => 'required|string|max:200',
            'trip_type' => 'required|in:business,training,conference',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'destination' => 'required|string|max:200',
            'purpose' => 'required|string|max:1000',
            'estimated_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        EmployeeTrip::create($validated);

        return redirect()->back()->with('success', 'Trip request submitted successfully.');
    }

    /**
     * Display announcements page
     */
    public function announcements(): View
    {
        $announcements = Announcement::with('createdBy')
            ->latest()
            ->paginate(15);

        return view('tenant.employee-lifecycle.announcements', compact('announcements'));
    }

    /**
     * Store new announcement
     */
    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:2000',
            'type' => 'required|in:general,urgent,policy,holiday,event',
            'priority' => 'required|in:low,medium,high,critical',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
            'requires_acknowledgment' => 'boolean',
        ]);

        $validated['created_by_id'] = Auth::id();

        Announcement::create($validated);

        return redirect()->back()->with('success', 'Announcement created successfully.');
    }
}
