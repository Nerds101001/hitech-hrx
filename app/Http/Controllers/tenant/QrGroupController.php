<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\QrGroup;
use App\Enums\Status;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class QrGroupController extends Controller
{
    public function index()
    {
        return view('tenant.qrcode.index', [
            'pageConfigs' => ['contentLayout' => 'wide']
        ]);
    }

    public function indexAjax(Request $request)
    {
        $query = QrGroup::query();

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status', function ($item) {
                $status = $item->status ?: 'active';
                $color = $status == 'active' ? 'success' : 'danger';
                return '<span class="badge bg-label-' . $color . '">' . ucfirst($status) . '</span>';
            })
            ->addColumn('actions', function ($item) {
                return '<div class="d-flex align-items-center gap-2">' .
                    '<button class="btn btn-sm btn-icon hitech-action-icon" onclick="editRecord(' . $item->id . ')" title="Edit"><i class="bx bx-edit"></i></button>' .
                    '<button class="btn btn-sm btn-icon hitech-action-icon text-danger" onclick="deleteRecord(' . $item->id . ')" title="Delete"><i class="bx bx-trash"></i></button>' .
                    '</div>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }
}
