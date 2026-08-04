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
        return $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'manager', 'Manager'])
            || $user->can('sales_report.manage')
            || $user->isCcareAgent()
            || $user->isNewBizAgent()
            || SalesPipeline::withTrashed()->where('salesperson_id', $user->id)->exists();
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

    /** Review screen — Pending / Approved / Rejected. */
    public function index(Request $request)
    {
        $this->authorizeApprover();

        $tab = in_array($request->get('tab'), ['approved', 'rejected']) ? $request->get('tab') : 'pending';

        $requests = DeleteRequest::query()
            ->with(['requester', 'actioner'])
            ->where('status', $tab)
            ->orderByDesc($tab === 'pending' ? 'created_at' : 'actioned_at')
            ->paginate(25)
            ->withQueryString();

        $counts = DeleteRequest::query()
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return view('tenant.sales-delete-requests.index', [
            'requests' => $requests,
            'tab'      => $tab,
            'counts'   => $counts,
            'reasons'  => DeleteRequest::REASONS,
        ]);
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
