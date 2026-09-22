<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalNote;
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

        $goals = NinetyDayGoal::with('checkins.notes.author')->where('round_id', $round->id)->where('user_id', $user->id)
            ->whereNull('closed_at')->orderBy('id')->get();
        $pending = Goals::pending($round, $today)->groupBy('week_no');

        return view('tenant.performance.ninety-day-goals.index', [
            'mode'      => 'mine',
            'isAdmin'   => Goals::seesEveryone($user),
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
        $roundFilter = $request->input('round') === 'all' ? 'all' : 'current';

        $q = NinetyDayGoal::with(['user.department', 'round', 'checkins.notes.author'])->orderBy('id');
        if ($scope !== null) {
            $q->whereIn('user_id', $scope);
        }
        if ($roundFilter === 'current') {
            $q->whereHas('round', fn ($r) => $r->where('status', 'active'));
        }
        // One list for the whole team, people in name order; the page filters and sorts it.
        $goals = $q->get()->sortBy(fn ($g) => strtolower(trim(optional($g->user)->first_name . ' ' . optional($g->user)->last_name)) . '|' . str_pad($g->id, 9, '0', STR_PAD_LEFT))->values();

        // "This week" only means something for a round that is actually running right now; the cards,
        // the strip and the overdue count follow whichever round filter is selected, so choosing "All
        // rounds, including closed" visibly changes the numbers instead of silently staying on the
        // active round only.
        $trueActive = $goals->filter(fn ($g) => $g->round && $g->round->status === 'active' && !$g->closed_at);
        $scoped = $roundFilter === 'current' ? $trueActive : $goals;
        $weekOfGoal = fn ($g) => Goals::weekOf($g->round, $today);
        $people = Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))->values();

        $summary = [
            'staff'    => $people->count(),
            'people'   => $scoped->pluck('user_id')->unique()->count(),
            'goals'    => $scoped->count(),
            'potential' => round((float) $scoped->sum('monthly_potential'), 2),
            'thisWeekDone'  => $trueActive->filter(fn ($g) => optional($g->checkins->firstWhere('week_no', $weekOfGoal($g)))->status !== null)->count(),
            'thisWeekTotal' => $trueActive->filter(fn ($g) => $g->checkins->firstWhere('week_no', $weekOfGoal($g)) !== null)->count(),
            'overdue'  => $scoped->sum(fn ($g) => $g->checkins->filter(fn ($c) => $c->status === null && $c->due_on->lt($today))->count()),
        ];

        $strip = [];
        for ($w = 0; $w < Goals::WEEKS; $w++) {
            $rows = $scoped->map(fn ($g) => $g->checkins->firstWhere('week_no', $w))->filter();
            $strip[$w] = ['total' => $rows->count(), 'done' => $rows->whereNotNull('status')->count(), 'late' => $rows->where('is_late', true)->count()];
        }

        // Everyone on the team appears, including people who have not added a customer yet.
        $withGoals = $goals->pluck('user_id')->unique();
        $notStarted = $roundFilter === 'current' ? $people->reject(fn ($u) => $withGoals->contains($u->id))->values() : collect();

        return view('tenant.performance.ninety-day-goals.index', [
            'mode'       => 'overview',
            'canSwitch'  => $isSales,
            'goals'      => $goals,
            'summary'    => $summary,
            'strip'      => $strip,
            'notStarted' => $notStarted,
            'people'     => $people,
            'isAdmin'    => Goals::seesEveryone($viewer),
            'preUser'    => $request->integer('user_id') ?: null,
            'filters'    => ['round' => $roundFilter],
            'today'      => $today,
            'statuses'   => Goals::STATUSES,
            'weeks'      => Goals::WEEKS,
        ]);
    }

    // ── dashboard and comments ───────────────────────────────────────────

    /** Same rule as the tracker: a salesperson sees their own numbers, a lead sees their team's, admin/HR see all. */
    private function scopeOrRedirect(): array
    {
        $user = Auth::user();
        if (!Goals::canOpenPage($user)) {
            return [null, null, redirect()->route('tenant.dashboard')->with('error', 'The 90-Day Goals tracker is for the Sales team.')];
        }

        return [$user, Goals::visibleUserIds($user), null];
    }

    private function peopleIn(?array $scope)
    {
        return Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))->values();
    }

    public function dashboard(Request $request)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }

        $f = [
            'round'   => $request->input('round') === 'all' ? 'all' : 'current',
            'user_id' => $request->integer('user_id') ?: null,
            'brand'   => $request->input('brand') ?: null,
        ];
        $people = $this->peopleIn($scope);
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }

        return view('tenant.performance.ninety-day-goals.dashboard', [
            'tab'      => 'dashboard',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany' => $scope === null || count($scope) > 1,
            'd'        => Goals::dashboard($scope, $f),
            'filters'  => $f,
            'people'   => $people,
            'brands'   => array_merge(array_keys(Goals::BRANDS), ['Other']),
            'weeks'    => Goals::WEEKS,
        ]);
    }

    public function comments(Request $request)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }

        $f = [
            'user_id' => $request->integer('user_id') ?: null,
            'status'  => in_array($request->input('status'), Goals::STATUSES, true) ? $request->input('status') : null,
            'late'    => $request->boolean('late'),
        ];
        $people = $this->peopleIn($scope);
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }

        return view('tenant.performance.ninety-day-goals.comments', [
            'tab'       => 'comments',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany'  => $scope === null || count($scope) > 1,
            'comments'  => Goals::comments($scope, $f),
            'filters'   => $f,
            'people'    => $people,
            'statuses'  => Goals::STATUSES,
        ]);
    }

    // ── past rounds ──────────────────────────────────────────────────────

    /** Every finished round in scope, as its own pick — no merging different people's history together. */
    public function past(Request $request)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }

        $rounds = NinetyDayRound::with('user.department')
            ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))
            ->where('status', 'closed')
            ->orderByDesc('starts_on')->orderBy('user_id')->get();

        $rounds->each(function (NinetyDayRound $r) {
            $r->stats = Goals::dashboard([$r->user_id], ['round_id' => $r->id])['cards'];
        });

        return view('tenant.performance.ninety-day-goals.past', [
            'tab'      => 'past',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany' => $scope === null || count($scope) > 1,
            'rounds'   => $rounds,
        ]);
    }

    /** The report for exactly one past round: its customers, week by week, and its comments. Nothing else mixed in. */
    public function pastRound($id)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }
        $round = NinetyDayRound::with('user.department')->findOrFail($id);
        if ($scope !== null && !in_array((int) $round->user_id, $scope, true)) {
            abort(403, 'That round is not in your team.');
        }

        $goals = NinetyDayGoal::with('checkins.notes.author')->where('round_id', $round->id)->orderBy('id')->get();

        return view('tenant.performance.ninety-day-goals.past-round', [
            'tab'      => 'past',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany' => $scope === null || count($scope) > 1,
            'round'    => $round,
            'goals'    => $goals,
            'd'        => Goals::dashboard([$round->user_id], ['round_id' => $round->id]),
            'comments' => Goals::comments([$round->user_id], ['round_id' => $round->id]),
            'weeks'    => Goals::WEEKS,
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
        // Once a customer is added, the sales team cannot remove it: the history stays on record.
        // Only admin/HR can delete, and the check-ins and comments go with it.
        if (!Goals::seesEveryone(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Once a customer is added it stays on record. Ask an admin if one has to be removed.'], 403);
        }
        $goal = NinetyDayGoal::findOrFail($id);
        $goal->checkins()->each(fn ($c) => $c->notes()->delete());
        $goal->checkins()->delete();
        $goal->delete();

        return response()->json(['success' => true, 'message' => 'Customer removed.']);
    }

    // ── comments on a week ───────────────────────────────────────────────

    /** The salesperson or an admin/manager adds a comment to one week of a goal. */
    public function note(Request $request)
    {
        $data = $request->validate([
            'goal_id' => 'required|integer',
            'week_no' => 'required|integer|min:0|max:' . (Goals::WEEKS - 1),
            'body'    => 'required|string|max:1000',
        ]);
        $user = Auth::user();
        $goal = NinetyDayGoal::find($data['goal_id']);
        if (!$goal || !Goals::canNote($user, $goal)) {
            return response()->json(['success' => false, 'message' => 'You cannot comment on this goal.'], 403);
        }
        $note = Goals::addNote($user, $goal, (int) $data['week_no'], $data['body']);
        if (!$note) {
            return response()->json(['success' => false, 'message' => 'That week has not started yet.'], 422);
        }

        return response()->json(['success' => true, 'note' => [
            'id' => $note->id, 'by' => trim($user->first_name . ' ' . $user->last_name), 'body' => $note->body,
            'at' => $note->created_at->format('d M'), 'can_delete' => Goals::seesEveryone($user),
        ]]);
    }

    /** Only admin/HR remove a comment. */
    public function destroyNote($id)
    {
        if (!Goals::seesEveryone(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Comments stay on record.'], 403);
        }
        NinetyDayGoalNote::findOrFail($id)->delete();

        return response()->json(['success' => true]);
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
