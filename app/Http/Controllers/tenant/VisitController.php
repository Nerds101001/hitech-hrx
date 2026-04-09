<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visit;
use Constants;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class VisitController extends Controller
{
  public function index()
  {
    return view('tenant.visit.index', [
      'pageConfigs' => ['contentLayout' => 'wide']
    ]);
  }

  public function getListAjax(Request $request)
  {
    try {
      $query = Visit::query()
        ->select('visits.*', 'client.name as client_name', 'users.id as user_id', 'users.first_name as user_first_name', 'users.last_name as user_last_name', 'users.code as user_code', 'users.profile_picture as user_profile_image')
        ->leftJoin('clients as client', 'visits.client_id', '=', 'client.id')
        ->leftJoin('users', 'visits.created_by_id', '=', 'users.id');

      // Apply date filter
      if ($request->has('dateFilter') && !empty($request->dateFilter)) {
        $query->whereDate('visits.created_at', $request->dateFilter);
      }

      return DataTables::of($query)
        ->addColumn('created_at', function ($visit) {
          return $visit->created_at ? $visit->created_at->format('Y-m-d H:i:s') : '-';
        })
        ->addColumn('client_name', function ($visit) {
          return $visit->client_name ?? '-';
        })
        ->addColumn('user', function ($visit) {

          return view('_partials._profile-avatar', [
            'user' => User::find($visit->user_id),
          ])->render();
        })
        ->addColumn('image', function ($visit) {
          return $visit->img_url
            ? '<img src="' . asset('storage/'.Constants::BaseFolderVisitImages . $visit->img_url) . '" class="img-thumbnail" width="50" />'
            : 'N/A';
        })
        ->addColumn('actions', function ($visit) {
          return '
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn

-sm btn-icon show-visit-details" data-id="' . $visit->id . '" data-bs-toggle="offcanvas" data-bs-target="#offcanvasShowVisitDetails">
                            <i class="bx bx-show"></i>
                        </button>
                        <button class="btn btn-sm btn-icon delete-record" data-id="' . $visit->id . '">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                ';
        })
        ->rawColumns(['user', 'image', 'actions'])
        ->make(true);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return response()->json([
        'status' => 'error',
        'message' => 'Something went wrong while fetching visits.'
      ], 500);
    }
  }

  public function deleteVisitAjax($id)
  {
    $visit = Visit::findOrFail($id);
    $visit->delete();
    return Success::response('Visit deleted successfully');
  }

  public function getByIdAjax($id)
  {
    $visit = Visit::findOrFail($id);

    if (is_null($visit)) {
      return Error::response('Visit not found');
    }

    $response = [
      'id' => $visit->id,
      'userName' => $visit->createdBy->getFullName(),
      'userCode' => $visit->createdBy->code,
      'client' => $visit->client->name,
      'createdAt' => $visit->created_at->format(Constants::DateTimeFormat),
      'imageUrl' => $visit->img_url != null ? asset('storage/'.Constants::BaseFolderVisitImages . $visit->img_url) : null,
      'address' => $visit->address,
      'remarks' => $visit->remarks,
    ];

    return Success::response($response);
  }
}
