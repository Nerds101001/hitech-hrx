<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\UniformRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Uniform requests: any employee asks for a shirt or t-shirt, admin/HR decides.
 *
 * Employee side  — index() / store() / cancel()
 * Approver side  — manage() / decide()
 */
class UniformRequestController extends Controller
{
    /** Who may approve or reject. Mirrors the Asset Management route group. */
    private function canApprove(): bool
    {
        $u = auth()->user();
        return $u && ($u->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])
            || $u->can('assets.assign'));
    }

    // ── Employee ────────────────────────────────────────────────────────────

    /** My requests, plus the form to raise a new one. */
    public function index()
    {
        $requests = UniformRequest::with('actionedBy:id,first_name,last_name')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('tenant.uniform-requests.index', [
            'requests'   => $requests,
            'items'      => UniformRequest::ITEMS,
            'sizes'      => UniformRequest::SIZES,
            'canApprove' => $this->canApprove(),
            // One open request at a time keeps the queue honest; the form is
            // hidden rather than failing after they have typed everything.
            'hasPending' => UniformRequest::where('user_id', auth()->id())->pending()->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_type' => ['required', Rule::in(array_keys(UniformRequest::ITEMS))],
            'size'      => ['required', Rule::in(UniformRequest::SIZES)],
            'quantity'  => ['required', 'integer', 'min:1', 'max:5'],
            'remarks'   => ['nullable', 'string', 'max:1000'],
        ]);

        if (UniformRequest::where('user_id', auth()->id())->pending()->exists()) {
            return back()->with('error', 'You already have a request awaiting a decision.');
        }

        $data['user_id']   = auth()->id();
        $data['status']    = UniformRequest::STATUS_PENDING;
        $data['tenant_id'] = auth()->user()->tenant_id;

        $req = UniformRequest::create($data);

        Log::info("Uniform request #{$req->id} raised by user " . auth()->id()
            . " ({$req->item_label}, size {$req->size}, qty {$req->quantity})");

        return back()->with('success', 'Request submitted. You will be notified once it is reviewed.');
    }

    /** Withdraw my own request, while it is still pending. */
    public function cancel($id)
    {
        $req = UniformRequest::where('user_id', auth()->id())->findOrFail($id);

        if (!$req->isPending()) {
            return back()->with('error', 'Only a pending request can be withdrawn.');
        }

        $req->update(['status' => UniformRequest::STATUS_CANCELLED]);

        return back()->with('success', 'Request withdrawn.');
    }

    // ── Approver ────────────────────────────────────────────────────────────

    /** Every request, for admin/HR. */
    public function manage(Request $request)
    {
        if (!$this->canApprove()) {
            abort(403);
        }

        $query = UniformRequest::with(['user:id,first_name,last_name,code,email', 'actionedBy:id,first_name,last_name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('item_type')) {
            $query->where('item_type', $request->input('item_type'));
        }
        if ($request->filled('q')) {
            $term = trim($request->input('q'));
            $query->whereHas('user', function ($u) use ($term) {
                $u->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%");
            });
        }

        return view('tenant.uniform-requests.manage', [
            'requests' => $query->latest()->paginate(20)->withQueryString(),
            'items'    => UniformRequest::ITEMS,
            'counts'   => [
                'pending'  => UniformRequest::where('status', UniformRequest::STATUS_PENDING)->count(),
                'approved' => UniformRequest::where('status', UniformRequest::STATUS_APPROVED)->count(),
                'rejected' => UniformRequest::where('status', UniformRequest::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    /** Approve or reject, with a remark either way. */
    public function decide(Request $request, $id)
    {
        if (!$this->canApprove()) {
            abort(403);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            // A rejection has to say why; an approval note is welcome but optional.
            'decision_note' => [
                Rule::requiredIf(fn () => $request->input('decision') === 'reject'),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'decision_note.required' => 'Please give a reason for rejecting this request.',
        ]);

        $req = UniformRequest::findOrFail($id);

        if (!$req->isPending()) {
            return back()->with('error', 'That request has already been decided.');
        }

        $req->update([
            'status'        => $data['decision'] === 'approve'
                                ? UniformRequest::STATUS_APPROVED
                                : UniformRequest::STATUS_REJECTED,
            'actioned_by'   => auth()->id(),
            'actioned_at'   => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        Log::info("Uniform request #{$req->id} {$req->status} by user " . auth()->id());

        return back()->with('success', 'Request ' . $req->status . '.');
    }
}
