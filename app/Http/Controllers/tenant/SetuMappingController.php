<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\SetuSalespersonMapping;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

/**
 * Admin screen over setu_salesperson_mappings — the reviewed link from a
 * Tally salesperson-ledger name to the HRX user it refers to (or a
 * confirmed exclusion, for ledger buckets/territories/brands). Populated by
 * hrx:setu-mapping-report; this page is where that classification gets
 * corrected, any time, without a CSV round-trip.
 *
 * Rows are never created or deleted here — only reclassified. A row that
 * should map to nobody is "excluded", not removed; that status is itself
 * the correct terminal state for a non-person ledger entry.
 */
class SetuMappingController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => SetuSalespersonMapping::count(),
            'confirmed' => SetuSalespersonMapping::where('status', SetuSalespersonMapping::STATUS_CONFIRMED)->count(),
            'pending' => SetuSalespersonMapping::where('status', SetuSalespersonMapping::STATUS_PENDING)->count(),
            'excluded' => SetuSalespersonMapping::where('status', SetuSalespersonMapping::STATUS_EXCLUDED)->count(),
        ];

        // Loaded once for the edit modal's "assign to" search — confirmed
        // with the user this should span all active employees, not just
        // Sales dept, since some Tally names turn out to be CCare/New Biz
        // coordinators rather than salespeople.
        $users = User::where('status', 'active')->notTerminated()
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('tenant.setuMappings.index', [
            'stats' => $stats,
            'users' => $users,
            'pageConfigs' => ['contentLayout' => 'wide'],
        ]);
    }

    public function getListAjax(Request $request)
    {
        try {
            $query = SetuSalespersonMapping::with('user')->select('setu_salesperson_mappings.*');

            if ($request->filled('statusFilter') && $request->statusFilter !== 'All') {
                $query->where('status', $request->statusFilter);
            }
            if ($request->filled('searchTerm')) {
                $term = $request->searchTerm;
                $query->where(function ($q) use ($term) {
                    $q->where('tally_name', 'like', "%{$term}%")
                      ->orWhere('note', 'like', "%{$term}%");
                });
            }

            return DataTables::of($query)
                ->addColumn('user_name', fn ($m) => $m->user ? trim($m->user->first_name . ' ' . $m->user->last_name) : null)
                ->addColumn('status_display', function ($m) {
                    $labels = [
                        SetuSalespersonMapping::STATUS_CONFIRMED => ['bg-success', 'Confirmed'],
                        SetuSalespersonMapping::STATUS_PENDING => ['bg-warning text-dark', 'Pending'],
                        SetuSalespersonMapping::STATUS_EXCLUDED => ['bg-secondary', 'Excluded'],
                    ];
                    [$class, $label] = $labels[$m->status] ?? ['bg-secondary', $m->status];
                    return "<span class=\"badge {$class}\">{$label}</span>";
                })
                ->addColumn('actions', fn ($m) => '
                    <div class="d-flex align-items-center justify-content-center">
                      <a href="javascript:;" class="text-hitech edit-record" data-id="' . $m->id . '" data-bs-toggle="modal" data-bs-target="#modalEditSetuMapping" title="Edit"><i class="bx bx-edit fs-4"></i></a>
                    </div>')
                ->rawColumns(['status_display', 'actions'])
                ->make(true);
        } catch (Exception $e) {
            Log::error('SetuMapping getListAjax: ' . $e->getMessage());
            return Error::response($e->getMessage());
        }
    }

    public function getByIdAjax($id)
    {
        $mapping = SetuSalespersonMapping::find($id);
        if (!$mapping) {
            return Error::response('Mapping not found');
        }

        return Success::response([
            'id' => $mapping->id,
            'tally_name' => $mapping->tally_name,
            'user_id' => $mapping->user_id,
            'status' => $mapping->status,
            'note' => $mapping->note,
        ]);
    }

    public function addOrUpdateAjax(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:setu_salesperson_mappings,id',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'required|in:' . implode(',', [
                SetuSalespersonMapping::STATUS_PENDING,
                SetuSalespersonMapping::STATUS_CONFIRMED,
                SetuSalespersonMapping::STATUS_EXCLUDED,
            ]),
            'note' => 'nullable|string|max:500',
        ]);

        // A row can only be "confirmed" once it actually points at someone —
        // otherwise the sync command would silently skip it anyway, but the
        // status would misleadingly claim it's ready.
        if ($request->status === SetuSalespersonMapping::STATUS_CONFIRMED && !$request->user_id) {
            return Error::response('Pick a person before marking this as confirmed.');
        }

        try {
            $mapping = SetuSalespersonMapping::findOrFail($request->id);
            $mapping->user_id = $request->status === SetuSalespersonMapping::STATUS_EXCLUDED ? null : $request->user_id;
            $mapping->status = $request->status;
            $mapping->note = $request->note ?: $mapping->note;
            $mapping->save();

            return Success::response('Mapping updated');
        } catch (Exception $e) {
            Log::error('SetuMapping addOrUpdateAjax: ' . $e->getMessage());
            return Error::response('Something went wrong. Please try again later.');
        }
    }
}
