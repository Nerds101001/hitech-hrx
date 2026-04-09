<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class MyAssetsController extends Controller
{
    public function index()
    {
        $assets = \App\Models\Asset::with(['category', 'assignedUser'])
            ->where('assigned_to', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total' => \App\Models\Asset::where('assigned_to', auth()->id())->count(),
            'available' => \App\Models\Asset::where('assigned_to', auth()->id())
                ->where('status', 'available')->count(),
            'assigned' => \App\Models\Asset::where('assigned_to', auth()->id())
                ->where('status', 'assigned')->count(),
            'maintenance' => \App\Models\Asset::where('assigned_to', auth()->id())
                ->where('status', 'maintenance')->count(),
            'retired' => \App\Models\Asset::where('assigned_to', auth()->id())
                ->where('status', 'retired')->count(),
            'total_value' => \App\Models\Asset::where('assigned_to', auth()->id())->sum('current_value'),
        ];

        return view('tenant.my-assets.index', compact('assets', 'stats'));
    }

    public function show($id)
    {
        $asset = \App\Models\Asset::with(['category', 'assignedUser', 'maintenanceRecords'])
            ->where('assigned_to', auth()->id())
            ->findOrFail($id);

        return view('tenant.my-assets.show', compact('asset'));
    }

    public function getListAjax(Request $request)
    {
        $assets = \App\Models\Asset::with(['category', 'assignedUser'])
            ->where('assigned_to', auth()->id())
            ->orderBy('created_at', 'desc');

        return DataTables::of($assets)
            ->addColumn('action', function ($asset) {
                $viewBtn = '';
                $requestBtn = '';
                
                if ($asset->status === 'maintenance') {
                    $requestBtn = '<button class="btn btn-sm btn-warning" onclick="requestMaintenance(' . $asset->id . ')"><i class="bx bx-message"></i> Request Maintenance</button>';
                }
                
                $viewBtn = '<button class="btn btn-sm btn-info" onclick="viewAssetDetails(' . $asset->id . ')"><i class="bx bx-eye"></i></button>';

                return $viewBtn . ' ' . $requestBtn;
            })
            ->addColumn('status_badge', function ($asset) {
                return '<span class="badge ' . $asset->status_badge . '">' . ucfirst($asset->status) . '</span>';
            })
            ->addColumn('category_name', function ($asset) {
                return $asset->category ? $asset->category->name : 'N/A';
            })
            ->addColumn('assigned_date', function ($asset) {
                return $asset->pivot && $asset->pivot->assigned_at ? $asset->pivot->assigned_at->format('M d, Y') : 'N/A';
            })
            ->addColumn('formatted_current_value', function ($asset) {
                return '$' . number_format($asset->current_value, 2);
            })
            ->addColumn('warranty_status', function ($asset) {
                if (!$asset->warranty_expiry) {
                    return '<span class="text-muted">No Warranty</span>';
                }
                
                $daysUntil = now()->diffInDays($asset->warranty_expiry);
                if ($daysUntil <= 30) {
                    return '<span class="badge bg-label-warning">Expiring in ' . $daysUntil . ' days</span>';
                }
                
                return '<span class="badge bg-label-success">Valid</span>';
            })
            ->make(true)
            ->toJson();
    }

    public function requestMaintenance(Request $request, $id)
    {
        try {
            $asset = \App\Models\Asset::findOrFail($id);
            
            // Create maintenance request (you could implement a separate maintenance request system)
            Log::info('Maintenance requested for asset: ' . $asset->name . ' by user: ' . auth()->user()->first_name);

            return response()->json([
                'success' => true, 
                'message' => 'Maintenance request submitted successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Maintenance request failed: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to submit maintenance request.'
            ], 500);
        }
    }
}
