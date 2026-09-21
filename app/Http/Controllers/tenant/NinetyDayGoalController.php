<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayRound;
use App\Models\User;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 90-Day Goals. Open to everyone in the Sales department for their own goals;
 * admin, HR and anyone given goals.manage / scorecards.manage see everyone's;
 * a manager sees their team's. These routes are login-only, so every action
 * checks who is asking here.
 */
class NinetyDayGoalController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!Goals::canOpenPage($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'The 90-Day Goals tracker is for the Sales team.');
        }

        $isSales  = Goals::isSalesStaff($user);
        $scope    = Goals::visibleUserIds($user);
        $seesMany = $scope === null || count($scope) > 1;
        $view     = $request->input('view', $seesMany ? 'overview' : 'mine');
        if ($view === 'mine' && !$isSales) {
            $view = 'overview';
        }
        if ($view === 'overview' && !$seesMany) {
            $view = 'mine';
        }

        return $view === 'mine' ? $this->mine($user) : $this->overview($request, $user, $scope, $isSales);
    }

    // ── my goals ─────────────────────────────────────────────────────────

    private function mine(User $user)
    {
        $today = Carbon::today();
        $round = Goals::roundFor($user, $today);
        Goals::ensureCheckins($round, $today);

        $goals = NinetyDayGoal::with('checkins')->where('round_id', $round->id)->where('user_id', $user->id)
            ->whereNull('closed_at')->orderBy('id')->get();
        $pending = Goals::pending($round, $today)->groupBy('week_no');

        return view('tenant.performance.ninety-day-goals.index', [
            'mode'      => 'mine',
            'canSwitch' => Goals::visibleUserIds($user) !== [$user->id],
            'round'     => $round,
            'week'      => Goals::weekOf($round, $today),
            'dates'     => Goals::weekDates($round),
            'goals'     => $goals,
            'pending'   => $pending,
            'today'     => $today,
            'statuses'  => Goals::STATUSES,
            'brands'    => array_keys(Goals::BRANDS),
            'weeks'     => Goals::WEEKS,
        ]);
    }

    // ── everyone's goals ─────────────────────────────────────────────────

    private function overview(Request $request, User $viewer, ?array $scope, bool $isSales)
    {
        $today = Carbon::today();
        $roundFilter = $request->input('round', 'current');
        $userFilter = $request->integer('user_id') ?: null;
        $statusFilter = $request->input('status');

        $q = NinetyDayGoal::with(['user.department', 'round', 'checkins'])->orderBy('user_id')->orderBy('id');
        if ($scope !== null) {
            $q->whereIn('user_id', $scope);
        }
        if ($userFilter) {
            $q->where('user_id', $userFilter);
        }
        if ($roundFilter === 'current') {
            $q->whereHas('round', fn ($r) => $r->where('status', 'active'));
        }
        $goals = $q->get();

        $latest = fn ($g) => optional($g->checkins->whereNotNull('status')->sortByDesc('week_no')->first())->status;
        if ($statusFilter === 'pending') {
            $goals = $goals->filter(fn ($g) => $latest($g) === null)->values();
        } elseif ($statusFilter && in_array($statusFilter, Goals::STATUSES, true)) {
            $goals = $goals->filter(fn ($g) => $latest($g) === $statusFilter)->values();
        }

        $active = $goals->filter(fn ($g) => $g->round && $g->round->status === 'active' && !$g->closed_at);
        $weekOfGoal = fn ($g) => Goals::weekOf($g->round, $today);

        $summary = [
            'staff'    => Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))->count(),
            'people'   => $active->pluck('user_id')->unique()->count(),
            'goals'    => $active->count(),
            'potential' => round((float) $active->sum('monthly_potential'), 2),
            'thisWeekDone'  => $active->filter(fn ($g) => optional($g->checkins->firstWhere('week_no', $weekOfGoal($g)))->status !== null)->count(),
            'thisWeekTotal' => $active->filter(fn ($g) => $g->checkins->firstWhere('week_no', $weekOfGoal($g)) !== null)->count(),
            'overdue'  => $active->sum(fn ($g) => $g->checkins->filter(fn ($c) => $c->status === null && $c->due_on->lt($today))->count()),
            'byStatus' => $active->map($latest)->filter()->countBy()->all(),
        ];

        $strip = [];
        for ($w = 0; $w < Goals::WEEKS; $w++) {
            $rows = $active->map(fn ($g) => $g->checkins->firstWhere('week_no', $w))->filter();
            $strip[$w] = ['total' => $rows->count(), 'done' => $rows->whereNotNull('status')->count(), 'late' => $rows->where('is_late', true)->count()];
        }

        $withGoals = $goals->pluck('user_id')->unique();
        $notStarted = Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))
            ->reject(fn ($u) => $withGoals->contains($u->id))->values();

        return view('tenant.performance.ninety-day-goals.index', [
            'mode'       => 'overview',
            'canSwitch'  => $isSales,
            'groups'     => $goals->groupBy('user_id'),
            'summary'    => $summary,
            'strip'      => $strip,
            'notStarted' => $notStarted,
            'people'     => Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope)),
            'filters'    => ['round' => $roundFilter, 'user_id' => $userFilter, 'status' => $statusFilter],
            'today'      => $today,
            'statuses'   => Goals::STATUSES,
            'weeks'      => Goals::WEEKS,
        ]);
    }

    // ── the customer picker ──────────────────────────────────────────────

    /** The person's own pipeline customers that are not already in their round. */
    public function pipelineCustomers(Request $request)
    {
        $user = Auth::user();
        $target = $this->targetUser($request, $user);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        $round = Goals::activeRound($target);
        $taken = $round ? NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->pluck('sales_pipeline_id')->filter()->all() : [];

        return response()->json([
            'success'   => true,
            'customers' => Goals::pipelineCustomers($target)->reject(fn ($c) => in_array($c['id'], $taken, true))->values(),
            'brands'    => array_keys(Goals::BRANDS),
        ]);
    }

    /** Add customers to the round. */
    public function store(Request $request)
    {
        $user = Auth::user();
        $target = $this->targetUser($request, $user);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }

        $data = $request->validate([
            'customers'                     => 'required|array|min:1|max:200',
            'customers.*.sales_pipeline_id' => 'required|integer',
            'customers.*.product'           => 'nullable|string|max:255',
            'customers.*.brand'             => 'nullable|string|max:100',
            'customers.*.goal'              => 'required|string|max:255',
            'customers.*.monthly_potential' => 'required|numeric|min:0|max:100000',
        ]);

        $round = Goals::roundFor($target);
        $mine = Goals::pipelineCustomers($target)->keyBy('id');
        $have = NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->pluck('sales_pipeline_id')->filter()->all();

        $added = 0;
        $skipped = [];
        foreach ($data['customers'] as $row) {
            $pid = (int) $row['sales_pipeline_id'];
            $c = $mine->get($pid);
            if (!$c) {
                $skipped[] = "customer #{$pid} is not in this person's pipeline";
                continue;
            }
            if (in_array($pid, $have, true)) {
                $skipped[] = $c['customer'] . ' is already in this round';
                continue;
            }
            NinetyDayGoal::create([
                'user_id'           => $target->id,
                'round_id'          => $round->id,
                'sales_pipeline_id' => $pid,
                'client_name'       => $c['customer'],
                'product'           => $row['product'] ?? $c['product'],
                'brand'             => $row['brand'] ?? $c['brand'],
                'goal'              => $row['goal'],
                'goal_type'         => 'Order',
                'monthly_potential' => (float) $row['monthly_potential'],
                'target_value'      => 0,
                'is_active'         => true,
                'created_by_id'     => $user->id,
                'tenant_id'         => $target->tenant_id,
            ]);
            $have[] = $pid;
            $added++;
        }
        Goals::ensureCheckins($round);

        return response()->json(['success' => true, 'added' => $added, 'skipped' => $skipped]);
    }

    // ── the weekly status ────────────────────────────────────────────────

    public function checkin(Request $request)
    {
        $user = Auth::user();
        if (!Goals::isSalesStaff($user)) {
            return response()->json(['success' => false, 'message' => 'Only the Sales team submits weekly statuses.'], 403);
        }
        $data = $request->validate([
            'week_no'             => 'required|integer|min:0|max:' . (Goals::WEEKS - 1),
            'entries'             => 'required|array|min:1',
            'entries.*.goal_id'   => 'required|integer',
            'entries.*.status'    => 'nullable|string|max:40',
            'entries.*.comment'   => 'nullable|string|max:1000',
        ]);

        $entries = [];
        foreach ($data['entries'] as $e) {
            $entries[(int) $e['goal_id']] = ['status' => $e['status'] ?? '', 'comment' => $e['comment'] ?? ''];
        }
        [$saved, $errors] = Goals::submit($user, (int) $data['week_no'], $entries);

        return response()->json(['success' => $saved > 0 && !$errors, 'saved' => $saved, 'errors' => $errors,
            'message' => $errors ? implode(' ', $errors) : "Saved {$saved} status" . ($saved === 1 ? '' : 'es') . '.'], $errors && !$saved ? 422 : 200);
    }

    // ── change / remove a goal ───────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $goal = NinetyDayGoal::findOrFail($id);
        if (!$this->mayChange($goal)) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        $data = $request->validate([
            'product'           => 'nullable|string|max:255',
            'brand'             => 'nullable|string|max:100',
            'goal'              => 'nullable|string|max:255',
            'monthly_potential' => 'nullable|numeric|min:0|max:100000',
        ]);
        $goal->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Goal updated.']);
    }

    public function destroy($id)
    {
        $goal = NinetyDayGoal::findOrFail($id);
        if (!$this->mayChange($goal)) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        // A person may remove a customer they have not reported on yet; once a
        // status is on record it stays, so the weekly history cannot be rewritten.
        if (!Goals::seesEveryone(Auth::user()) && $goal->checkins()->whereNotNull('status')->exists()) {
            return response()->json(['success' => false, 'message' => 'This customer already has weekly statuses, so it stays on record.'], 422);
        }
        $goal->delete();

        return response()->json(['success' => true, 'message' => 'Customer removed.']);
    }

    // ── who may act on whose goals ───────────────────────────────────────

    /** Sales staff act for themselves; admin/HR (goals.manage) may add for a named person. */
    private function targetUser(Request $request, User $user): ?User
    {
        if ($request->filled('user_id') && (int) $request->input('user_id') !== $user->id) {
            if (!Goals::seesEveryone($user)) {
                return null;
            }
            $other = User::notTerminated()->find((int) $request->input('user_id'));

            return $other && Goals::isSalesStaff($other) ? $other : null;
        }

        return Goals::isSalesStaff($user) ? $user : null;
    }

    private function mayChange(NinetyDayGoal $goal): bool
    {
        $user = Auth::user();

        return Goals::seesEveryone($user) || (int) $goal->user_id === (int) $user->id;
    }
}
