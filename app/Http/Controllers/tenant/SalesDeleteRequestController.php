<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineDeleteRequest as DeleteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Approval-gated customer deletion for the Sales Report / Sales Pipeline.
 *
 * The sales floor raises a request with a reason; only admin/HR may approve,
 * and approval is what actually performs the delete. Nothing is removed until
 * someone with authority says so.
 */
class SalesDeleteRequestController extends Controller
{
    /** Raising a request — anyone who can work the sales books. */
    private function canRequest($user): bool
    {
        if (!$user) return false;
        // Plain salespeople are read-only on the sales book (decision
        // 2026-08-05), so they can no longer raise a delete request either —
        // the "owns rows as salesperson" clause that used to admit them has
        // been dropped. Mirrors SalesPipelineController::canEditBook().
        return $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'manager', 'Manager'])
            || $user->can('sales_report.manage')
            || $user->isCcareAgent()
            || $user->isNewBizAgent();
    }

    /** Deciding a request — admin/HR only (deletion cannot be undone). */
    private function authorizeApprover()
    {
        if (!DeleteRequest::canApprove(auth()->user())) {
            abort(403, 'Only Admin/HR can review customer deletion requests.');
        }
    }

    /**
     * Raise a deletion request. Replaces the old "delete immediately" action —
     * the customer stays exactly where it is until a decision is made.
     */
    public function store(Request $request, $pipelineId)
    {
        $user = auth()->user();
        if (!$this->canRequest($user)) {
            return back()->with('error', 'You do not have permission to request a deletion.');
        }

        $data = $request->validate([
            'reason'  => ['required', Rule::in(array_keys(DeleteRequest::REASONS))],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Please choose a reason for the deletion.',
            'reason.in'       => 'That reason is not recognised.',
        ]);

        $pipeline = SalesPipeline::withTrashed()->findOrFail($pipelineId);

        // One open request per customer — otherwise two reps queue the same row
        // and the second approval hits an already-deleted record.
        $existing = DeleteRequest::query()
            ->where('sales_pipeline_id', $pipeline->id)
            ->where('status', DeleteRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return back()->with('error', 'A deletion request for "' . $pipeline->party_name . '" is already awaiting approval.');
        }

        DeleteRequest::create([
            'sales_pipeline_id' => $pipeline->id,
            // snapshots — these must survive the customer being deleted
            'party_name'        => $pipeline->party_name,
            'book'              => $pipeline->is_locked ? DeleteRequest::BOOK_REPORT : DeleteRequest::BOOK_PIPELINE,
            'requested_by'      => $user->id,
            'reason'            => $data['reason'],
            'remarks'           => $data['remarks'] ?? null,
            'status'            => DeleteRequest::STATUS_PENDING,
            'tenant_id'         => $pipeline->tenant_id,
        ]);

        return back()->with('success', 'Deletion request sent to Admin/HR for approval. "' . $pipeline->party_name . '" stays until it is approved.');
    }

    /** Review screen — Pending / Approved / Rejected, with filters and sorting. */
    public function index(Request $request)
    {
        $this->authorizeApprover();

        $tab = in_array($request->get('tab'), ['approved', 'rejected']) ? $request->get('tab') : 'pending';

        $query = DeleteRequest::query()
            // The customer is soft-deleted on approval, so withTrashed() is what
            // keeps the representative columns readable on an approved request.
            ->with(['requester', 'actioner', 'pipeline' => fn($q) => $q->withTrashed()
                ->with(['salesperson', 'ccare', 'newBiz'])])
            ->where('status', $tab);

        if ($s = trim((string) $request->get('q'))) {
            $query->where('party_name', 'like', '%' . $s . '%');
        }
        if ($request->filled('reason')) {
            $query->where('reason', $request->get('reason'));
        }
        if ($request->filled('book')) {
            $query->where('book', $request->get('book'));
        }
        if ($request->filled('requested_by')) {
            $query->where('requested_by', $request->get('requested_by'));
        }
        if ($request->filled('actioned_by')) {
            $query->where('actioned_by', $request->get('actioned_by'));
        }
        // One box for all three roles: the reviewer thinks "whose customer was
        // this?", not "which of the three columns is it in?".
        if ($request->filled('rep')) {
            $rep = (int) $request->get('rep');
            $query->whereHas('pipeline', fn($q) => $q->withTrashed()->where(
                fn($w) => $w->where('salesperson_id', $rep)
                    ->orWhere('ccare_id', $rep)
                    ->orWhere('new_biz_id', $rep)
            ));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $sortable = [
            'customer'  => 'party_name',
            'reason'    => 'reason',
            'book'      => 'book',
            'requested' => 'created_at',
            'decided'   => 'actioned_at',
        ];
        $sort = $request->get('sort');
        $dir  = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortable[$sort] ?? ($tab === 'pending' ? 'created_at' : 'actioned_at'), $dir);

        $requests = $query->paginate((int) $request->get('per_page', 25))->withQueryString();

        // ---- headline figures -------------------------------------------------
        $counts = DeleteRequest::query()
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        // Category-wise, for the cards. Counted across the tab in view so the
        // numbers describe the list being looked at, not the whole table.
        $reasonCounts = DeleteRequest::query()
            ->where('status', $tab)
            ->selectRaw('reason, COUNT(*) c')->groupBy('reason')->pluck('c', 'reason');

        // ---- filter dropdown sources -----------------------------------------
        $requesterIds = DeleteRequest::query()->distinct()->pluck('requested_by')->filter();
        $actionerIds  = DeleteRequest::query()->distinct()->pluck('actioned_by')->filter();
        $repIds       = SalesPipeline::withTrashed()
            ->whereIn('id', DeleteRequest::query()->pluck('sales_pipeline_id')->filter())
            ->get(['salesperson_id', 'ccare_id', 'new_biz_id'])
            ->flatMap(fn($p) => [$p->salesperson_id, $p->ccare_id, $p->new_biz_id])
            ->filter()->unique();

        $userName = fn($ids) => \App\Models\User::whereIn('id', $ids)
            ->orderBy('first_name')->get()
            ->mapWithKeys(fn($u) => [$u->id => $u->getFullName() ?: ('#' . $u->id)]);

        return view('tenant.sales-delete-requests.index', [
            'requests'     => $requests,
            'tab'          => $tab,
            'counts'       => $counts,
            'reasonCounts' => $reasonCounts,
            'reasons'      => DeleteRequest::REASONS,
            'requesters'   => $userName($requesterIds),
            'actioners'    => $userName($actionerIds),
            'reps'         => $userName($repIds),
            'sort'         => $sort,
            'dir'          => $dir,
        ]);
    }

    /**
     * Approve or reject several requests in one go.
     *
     * Each request is handled in its own transaction: one customer failing to
     * delete must not silently roll back the ones that already succeeded, and
     * the reviewer is told exactly how many of each outcome there were.
     */
    public function bulk(Request $request)
    {
        $this->authorizeApprover();

        $data = $request->validate([
            'bulk_action'   => ['required', Rule::in(['approve', 'reject'])],
            'ids'           => ['required', 'array', 'min:1'],
            'ids.*'         => ['integer'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'ids.required' => 'Select at least one request first.',
        ]);

        $rows = DeleteRequest::query()
            ->whereIn('id', $data['ids'])
            ->where('status', DeleteRequest::STATUS_PENDING)
            ->get();

        $done = 0; $failed = 0;
        $skipped = count($data['ids']) - $rows->count();

        foreach ($rows as $dr) {
            try {
                if ($data['bulk_action'] === 'approve') {
                    $this->applyApproval($dr);
                } else {
                    $dr->update([
                        'status'        => DeleteRequest::STATUS_REJECTED,
                        'actioned_by'   => auth()->id(),
                        'actioned_at'   => now(),
                        'decision_note' => $data['decision_note'] ?? null,
                    ]);
                }
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Bulk ' . $data['bulk_action'] . ' failed for delete-request #' . $dr->id . ': ' . $e->getMessage());
            }
        }

        $verb = $data['bulk_action'] === 'approve' ? 'Deleted and approved' : 'Rejected';
        $msg  = $verb . ' ' . $done . ' ' . str('request')->plural($done) . '.';
        if ($skipped) $msg .= ' ' . $skipped . ' already decided and left alone.';
        if ($failed)  $msg .= ' ' . $failed . ' could not be processed — nothing was removed for those.';

        return back()->with($failed ? 'error' : 'success', $msg);
    }

    /**
     * The actual deletion, shared by the single and bulk approve paths so the
     * two can never drift apart.
     */
    private function applyApproval(DeleteRequest $dr): void
    {
        DB::transaction(function () use ($dr) {
            $pipeline = $dr->sales_pipeline_id
                ? SalesPipeline::withTrashed()->find($dr->sales_pipeline_id)
                : null;

            // Delete through the model so the removal is audited. If the row
            // is already gone we still close the request rather than erroring.
            if ($pipeline) {
                $pipeline->delete();
            }

            $dr->update([
                'status'      => DeleteRequest::STATUS_APPROVED,
                'actioned_by' => auth()->id(),
                'actioned_at' => now(),
            ]);
        });
    }

    /** Approve — this is the point at which the customer is actually removed. */
    public function approve(Request $request, $id)
    {
        $this->authorizeApprover();

        $dr = DeleteRequest::query()->findOrFail($id);
        if ($dr->status !== DeleteRequest::STATUS_PENDING) {
            return back()->with('error', 'That request has already been ' . $dr->status . '.');
        }

        try {
            $this->applyApproval($dr);
        } catch (\Throwable $e) {
            Log::error('Delete-request approval failed for #' . $dr->id . ': ' . $e->getMessage());
            return back()->with('error', 'Could not delete that customer. Nothing was removed.');
        }

        return back()->with('success', 'Deleted "' . $dr->party_name . '" and approved the request.');
    }

    /** Reject — the customer stays; the note explains why. */
    public function reject(Request $request, $id)
    {
        $this->authorizeApprover();

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:1000']]);

        $dr = DeleteRequest::query()->findOrFail($id);
        if ($dr->status !== DeleteRequest::STATUS_PENDING) {
            return back()->with('error', 'That request has already been ' . $dr->status . '.');
        }

        $dr->update([
            'status'        => DeleteRequest::STATUS_REJECTED,
            'actioned_by'   => auth()->id(),
            'actioned_at'   => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        return back()->with('success', 'Request rejected — "' . $dr->party_name . '" was kept.');
    }
}
