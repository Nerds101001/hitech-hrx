<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DocumentRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $totalRequests = DocumentRequest::count();
        $pendingRequests = DocumentRequest::where('status', 'Pending')->count();
        $approvedRequests = DocumentRequest::where('status', 'Approved')->count();
        $rejectedRequests = DocumentRequest::where('status', 'Rejected')->count();

        return view('tenant.documentRequests.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'totalRequests' => $totalRequests,
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests
        ]);
    }

    /**
     * Get list of document requests via Ajax for DataTables.
     */
    public function getListAjax(Request $request)
    {
        try {
            $query = DocumentRequest::with(['user', 'documentType']);

            if ($request->has('statusFilter') && !in_array($request->statusFilter, ['All', ''])) {
                $query->where('status', $request->statusFilter);
            }

            if ($request->has('searchTerm') && !empty($request->searchTerm)) {
                $search = $request->searchTerm;
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%")
                             ->orWhere('code', 'like', "%{$search}%");
                      })
                      ->orWhereHas('documentType', function ($dq) use ($search) {
                          $dq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            return \Yajra\DataTables\Facades\DataTables::of($query)
                ->addColumn('user_name', function ($docRequest) {
                    return $docRequest->user ? $docRequest->user->full_name : 'N/A';
                })
                ->addColumn('user_code', function ($docRequest) {
                    return $docRequest->user ? $docRequest->user->code : '';
                })
                ->addColumn('user_initial', function ($docRequest) {
                    return $docRequest->user ? $docRequest->user->getInitials() : '';
                })
                ->addColumn('user_profile_image', function ($docRequest) {
                    return $docRequest->user ? $docRequest->user->getProfilePicture() : null;
                })
                ->editColumn('document_type', function ($docRequest) {
                    return $docRequest->documentType ? $docRequest->documentType->name : 'N/A';
                })
                ->editColumn('created_at', function ($docRequest) {
                    return $docRequest->created_at->format('d M Y');
                })
                ->make(true);
        } catch (Exception $e) {
            Log::error("DocumentRequestController@getListAjax: " . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Update the status of a document request.
     */
    public function actionAjax(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'status' => 'required|in:Approved,Rejected,Pending',
            'admin_remarks' => 'nullable|string'
        ]);

        try {
            $docRequest = DocumentRequest::findOrFail($request->id);
            $docRequest->status = $request->status;
            $docRequest->admin_remarks = $request->admin_remarks;
            $docRequest->action_taken_by_id = Auth::id();
            $docRequest->action_taken_at = now();
            $docRequest->save();

            return Success::response('Document request updated successfully');
        } catch (Exception $e) {
            Log::error("DocumentRequestController@actionAjax: " . $e->getMessage());
            return Error::response('Something went wrong');
        }
    }

    /**
     * Get details of a single request.
     */
    public function getByIdAjax($id)
    {
        try {
            $docRequest = DocumentRequest::with(['user', 'documentType'])->findOrFail($id);
            return Success::response($docRequest);
        } catch (Exception $e) {
            return Error::response('Request not found');
        }
    }
}
