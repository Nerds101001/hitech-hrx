<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LeaverHandoverService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin / HR screen for handing a leaver's customers to a replacement from a
 * chosen month on. Every step is a preview first; applying needs the preview's
 * fingerprint and the word HANDOVER typed, and a batch can be undone.
 */
class LeaverHandoverController extends Controller
{
    private function authorizeHandover(): void
    {
        abort_unless(auth()->user() && auth()->user()->hasRole(['admin', 'Admin', 'hr', 'HR']), 403);
    }

    public function index()
    {
        $this->authorizeHandover();

        // Everyone who can hold customers: those who have left (a handover) and those
        // still working (a transfer). Not-yet-started staff are left out.
        $everyone = User::whereNotIn('status', ['onboarding', 'onboarding_submitted'])->get()->keyBy('id');
        $count = function (string $col) use ($everyone) {
            return DB::table('sales_pipelines')->whereNull('deleted_at')->whereIn($col, $everyone->keys())
                ->groupBy($col)->selectRaw("$col uid, COUNT(*) n")->pluck('n', 'uid');
        };
        $sp = $count('salesperson_id');
        $cc = $count('ccare_id');
        $nb = $count('new_biz_id');
        $nb2 = $count('new_biz_2_id');
        $tags = DB::table('sales_pipeline_extra_reps')->whereIn('user_id', $everyone->keys())->groupBy('user_id')->selectRaw('user_id uid, COUNT(*) n')->pluck('n', 'uid');

        $leavers = [];
        $workers = [];
        foreach ($everyone as $id => $u) {
            $held = (int) ($sp[$id] ?? 0) + (int) ($cc[$id] ?? 0) + (int) ($nb[$id] ?? 0) + (int) ($nb2[$id] ?? 0);
            $t = (int) ($tags[$id] ?? 0);
            if ($held + $t === 0) {
                continue;
            }
            $left = $u->last_working_day ?: $u->exit_date;
            $row = [
                'id' => $id, 'name' => trim($u->first_name . ' ' . $u->last_name), 'status' => LeaverHandoverService::statusOf($u),
                'left' => $left ? Carbon::parse($left)->toDateString() : null,
                'salesperson' => (int) ($sp[$id] ?? 0), 'ccare' => (int) ($cc[$id] ?? 0),
                'new_biz' => (int) ($nb[$id] ?? 0) + (int) ($nb2[$id] ?? 0), 'tags' => $t,
            ];
            if (LeaverHandoverService::statusOf($u) === 'active') {
                $workers[] = $row;
            } else {
                $leavers[] = $row;
            }
        }
        usort($leavers, fn ($a, $b) => strcmp((string) $b['left'], (string) $a['left']));
        usort($workers, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $people = User::where('status', 'active')->with('department')->orderBy('first_name')->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name), 'dept' => optional($u->department)->name ?: 'No department']);

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = now()->startOfMonth()->addMonths($i);
            $months[] = ['key' => $m->format('Y-m'), 'label' => $m->format('F Y')];
        }

        $names = User::pluck(DB::raw("TRIM(CONCAT(first_name,' ',COALESCE(last_name,'')))"), 'id');
        $batches = DB::table('sales_pipeline_handover_batches')->orderByDesc('id')->limit(30)->get()->map(function ($b) use ($names) {
            $b->leaver_name = $names[$b->leaver_id] ?? ('#' . $b->leaver_id);
            $b->summary = json_decode((string) $b->summary, true) ?: [];
            $b->by = $b->created_by ? ($names[$b->created_by] ?? '#' . $b->created_by) : 'System';

            return $b;
        });

        return view('tenant.sales-pipeline.leaver-handover', ['leavers' => $leavers, 'workers' => $workers, 'people' => $people, 'months' => $months, 'batches' => $batches]);
    }

    private function inputs(Request $request): array
    {
        $data = $request->validate([
            'leaver_id'     => 'required|integer|exists:users,id',
            'month'         => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'salesperson'   => 'nullable|integer',
            'ccare'         => 'nullable|integer',
            'new_biz'       => 'nullable|integer',
            'billing'       => 'nullable|integer',
            'finance'       => 'nullable|integer',
        ]);

        return [
            User::findOrFail($data['leaver_id']),
            ['salesperson' => $data['salesperson'] ?? null, 'ccare' => $data['ccare'] ?? null, 'new_biz' => $data['new_biz'] ?? null,
             'billing' => $data['billing'] ?? null, 'finance' => $data['finance'] ?? null],
            $data['month'],
        ];
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorizeHandover();
        [$leaver, $repl, $month] = $this->inputs($request);
        $plan = LeaverHandoverService::plan($leaver, $repl, $month);

        $names = User::whereIn('id', array_values(array_filter($plan['replacements'])))->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);
        $sample = collect($plan['moves'])->take(60)->map(fn ($m) => [
            'id' => $m['pipeline_id'], 'party' => $m['party'], 'role' => str_replace('_id', '', $m['role']), 'to' => $names[$m['to']] ?? ('#' . $m['to']),
        ])->values();

        return response()->json([
            'errors' => $plan['errors'], 'warnings' => $plan['warnings'], 'summary' => $plan['summary'],
            'sample' => $sample, 'more' => max(0, count($plan['moves']) - 60), 'fingerprint' => $plan['fingerprint'],
            'month' => $month,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $this->authorizeHandover();
        [$leaver, $repl, $month] = $this->inputs($request);
        $request->validate(['fingerprint' => 'required|string', 'confirm' => 'required|in:HANDOVER']);

        try {
            $id = LeaverHandoverService::apply($leaver, $repl, $month, (string) $request->input('fingerprint'), auth()->id(), $request->input('note'));
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'batch' => $id, 'message' => 'Handover applied.']);
    }

    public function revert(Request $request, int $id): JsonResponse
    {
        $this->authorizeHandover();
        try {
            $r = LeaverHandoverService::revert($id, auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => "Undone ({$r['reverted']} change(s) put back)."]);
    }
}
