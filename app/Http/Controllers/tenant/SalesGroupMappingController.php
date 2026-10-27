<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\SalesGroupMapping;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

/**
 * Admin screen over sales_group_mappings — the reviewed link from a Tally
 * Tally salesperson tag (tally_invoices.salesperson) to the HRX user it refers to (or
 * a confirmed exclusion, for accounting buckets/territories/brands).
 * Populated by hrx:sales-group-mapping-sync; this page is where that
 * classification gets corrected. Mirrors SetuMappingController exactly,
 * which this supersedes for sales reconciliation purposes.
 *
 * Rows are never created or deleted here — only reclassified.
 */
class SalesGroupMappingController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => SalesGroupMapping::count(),
            'confirmed' => SalesGroupMapping::where('status', SalesGroupMapping::STATUS_CONFIRMED)->count(),
            'pending' => SalesGroupMapping::where('status', SalesGroupMapping::STATUS_PENDING)->count(),
            'excluded' => SalesGroupMapping::where('status', SalesGroupMapping::STATUS_EXCLUDED)->count(),
        ];

        $users = User::where('status', 'active')->notTerminated()
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('tenant.salesGroupMappings.index', [
            'stats' => $stats,
            'users' => $users,
            'pageConfigs' => ['contentLayout' => 'wide'],
        ]);
    }

    public function getListAjax(Request $request)
    {
        try {
            $query = SalesGroupMapping::with('user')->select('sales_group_mappings.*');

            if ($request->filled('statusFilter') && $request->statusFilter !== 'All') {
                $query->where('status', $request->statusFilter);
            }
            if ($request->filled('searchTerm')) {
                $term = $request->searchTerm;
                $query->where(function ($q) use ($term) {
                    $q->where('tally_salesperson', 'like', "%{$term}%")
                      ->orWhere('note', 'like', "%{$term}%");
                });
            }

            return DataTables::of($query)
                ->addColumn('user_name', fn ($m) => $m->user ? trim($m->user->first_name . ' ' . $m->user->last_name) : null)
                ->addColumn('status_display', function ($m) {
                    $labels = [
                        SalesGroupMapping::STATUS_CONFIRMED => ['bg-success', 'Confirmed'],
                        SalesGroupMapping::STATUS_PENDING => ['bg-warning text-dark', 'Pending'],
                        SalesGroupMapping::STATUS_EXCLUDED => ['bg-secondary', 'Excluded'],
                    ];
                    [$class, $label] = $labels[$m->status] ?? ['bg-secondary', $m->status];
                    return "<span class=\"badge {$class}\">{$label}</span>";
                })
                ->addColumn('actions', fn ($m) => '
                    <div class="d-flex align-items-center justify-content-center">
                      <a href="javascript:;" class="text-hitech edit-record" data-id="' . $m->id . '" data-bs-toggle="modal" data-bs-target="#modalEditSalesGroupMapping" title="Edit"><i class="bx bx-edit fs-4"></i></a>
                    </div>')
                ->rawColumns(['status_display', 'actions'])
                ->make(true);
        } catch (Exception $e) {
            Log::error('SalesGroupMapping getListAjax: ' . $e->getMessage());
            return Error::response($e->getMessage());
        }
    }

    public function getByIdAjax($id)
    {
        $mapping = SalesGroupMapping::find($id);
        if (!$mapping) {
            return Error::response('Mapping not found');
        }

        return Success::response([
            'id' => $mapping->id,
            'tally_salesperson' => $mapping->tally_salesperson,
            'user_id' => $mapping->user_id,
            'status' => $mapping->status,
            'note' => $mapping->note,
        ]);
    }

    public function addOrUpdateAjax(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:sales_group_mappings,id',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'required|in:' . implode(',', [
                SalesGroupMapping::STATUS_PENDING,
                SalesGroupMapping::STATUS_CONFIRMED,
                SalesGroupMapping::STATUS_EXCLUDED,
            ]),
            'note' => 'nullable|string|max:500',
        ]);

        if ($request->status === SalesGroupMapping::STATUS_CONFIRMED && !$request->user_id) {
            return Error::response('Pick a person before marking this as confirmed.');
        }

        try {
            $mapping = SalesGroupMapping::findOrFail($request->id);
            $mapping->user_id = $request->status === SalesGroupMapping::STATUS_EXCLUDED ? null : $request->user_id;
            $mapping->status = $request->status;
            $mapping->note = $request->note ?: $mapping->note;
            $mapping->save();

            return Success::response('Mapping updated');
        } catch (Exception $e) {
            Log::error('SalesGroupMapping addOrUpdateAjax: ' . $e->getMessage());
            return Error::response('Something went wrong. Please try again later.');
        }
    }
}
