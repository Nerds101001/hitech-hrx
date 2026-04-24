<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Enums\Status;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class UnitController extends Controller
{
    public function index()
    {
        return view('tenant.units.index', [
            'pageConfigs' => ['contentLayout' => 'wide']
        ]);
    }

    public function indexAjax(Request $request)
    {
        $query = Site::query();

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status', function ($item) {
                $status = $item->status ?: 'active';
                $color = $status == 'active' ? 'success' : 'danger';
                return '<span class="badge bg-label-' . $color . '">' . ucfirst($status) . '</span>';
            })
            ->addColumn('actions', function ($item) {
                $escapedName = addslashes($item->name);
                return '<div class="d-flex align-items-center justify-content-center gap-2">' .
                    '<a href="javascript:;" class="text-hitech edit-record me-2" onclick="editRecord(' . $item->id . ')" title="Edit"><i class="bx bx-edit fs-4"></i></a>' .
                    '<a href="javascript:;" class="text-hitech me-2" onclick="openPolicies(' . $item->id . ', \'' . $escapedName . '\')" title="Leave Policies"><i class="bx bx-shield-quarter fs-4"></i></a>' .
                    '<a href="javascript:;" class="text-danger delete-record" onclick="deleteRecord(' . $item->id . ')" title="Delete"><i class="bx bx-trash fs-4"></i></a>' .
                    '</div>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    public function getByIdAjax($id)
    {
        $unit = Site::find($id);
        if (!$unit) {
            return response()->json(['success' => false, 'message' => 'Unit not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $unit]);
    }

    public function addOrUpdateAjax(Request $request)
    {
        $id = $request->id;
        $rules = [
            'name' => 'required|string|max:255',
        ];

        $request->validate($rules);

        $data = [
            'name' => $request->name,
            'address' => $request->address,
            'is_multiple_check_in_enabled' => $request->has('is_multiple_check_in_enabled') ? 1 : 0,
            'is_auto_check_out_enabled' => $request->has('is_auto_check_out_enabled') ? 1 : 0,
            'auto_check_out_time' => $request->auto_check_out_time,
            'is_biometric_verification_enabled' => $request->has('is_biometric_verification_enabled') ? 1 : 0,
            'latitude' => $request->latitude ?? 0.0,
            'longitude' => $request->longitude ?? 0.0,
            'radius' => 500,
            'client_id' => $request->client_id ?? (\App\Models\Client::first()?->id),
        ];

        if ($id) {
            $unit = Site::find($id);
            $unit->update($data);
        } else {
            $unit = Site::create($data);
        }

        return response()->json(['success' => true, 'message' => 'Unit ' . ($id ? 'updated' : 'created') . ' successfully']);
    }

    public function deleteAjax($id)
    {
        $unit = Site::find($id);
        if ($unit) {
            $unit->delete();
            return response()->json(['success' => true, 'message' => 'Unit deleted successfully']);
        }
        return response()->json(['success' => false, 'message' => 'Unit not found'], 404);
    }
}
