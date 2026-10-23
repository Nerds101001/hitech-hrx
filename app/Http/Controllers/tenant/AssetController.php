<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use App\Exports\AssetsExport;
use Maatwebsite\Excel\Facades\Excel;

class AssetController extends Controller
{
    public function index()
    {
        try {
            $assets = \App\Models\Asset::with(['category', 'assignedUser', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            $categories = AssetCategory::where('status', 'active')
                ->orderBy('name')
                ->get();

            $stats = [
                'total' => \App\Models\Asset::count(),
                'available' => \App\Models\Asset::where('status', 'available')->count(),
                'assigned' => \App\Models\Asset::where('status', 'assigned')->count(),
                'maintenance' => \App\Models\Asset::where('status', 'maintenance')->count(),
                'retired' => \App\Models\Asset::where('status', 'retired')->count(),
                'total_value' => \App\Models\Asset::sum('current_value'),
            ];

            $users = User::whereIn('status', ['active', 'onboarding', 'onboarding_submitted'])
                ->with('department:id,name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'department_id']);

            $departments = \App\Models\Department::orderBy('name')->get(['id', 'name']);

            return view('tenant.assets.index', [
                'pageConfigs' => ['contentLayout' => 'wide'],
                'assets' => $assets,
                'categories' => $categories,
                'users' => $users,
                'departments' => $departments,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Asset index error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to load assets. Please try again.');
        }
    }

    public function create()
    {
        try {
            $categories = AssetCategory::where('status', 'active')
                ->orderBy('name')
                ->get();
                
            $users = User::whereIn('status', ['active', 'onboarding', 'onboarding_submitted'])
                ->with('department:id,name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'department_id']);

            return view('tenant.assets.create', compact('categories', 'users'));

        } catch (Exception $e) {
            Log::error('Asset create form error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to load asset creation form. Please try again.');
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:asset_categories,id',
                'assigned_to' => 'nullable|exists:users,id',
                'status' => 'required|in:available,assigned,maintenance,retired',
                'location' => 'nullable|string|max:255',
                'serial_number' => 'nullable|string|max:100',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'warranty_expiry' => 'nullable|date',
                'description' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $validator->errors()->first(),
                        'errors' => $validator->errors(),
                    ], 422);
                }
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $warranty_bill = null;
            if ($request->hasFile('warranty_bill')) {
                $file = $request->file('warranty_bill');
                $filename = 'bill_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('assets/bills', $filename, 'public');
                $warranty_bill = 'assets/bills/' . $filename;
            }

            $category = AssetCategory::find($request->category_id);
            $prefix = $category ? strtoupper(substr($category->name, 0, 3)) : 'AST';
            $asset_code = $prefix . '-' . strtoupper(substr(uniqid(), -5));

            $status = $request->status;
            $assigned_to = $request->assigned_to;

            if ($status === 'maintenance' || $status === 'retired') {
                $assigned_to = null;
            } elseif ($assigned_to) {
                $status = 'assigned';
            } else {
                if ($status === 'assigned') {
                    $status = 'available';
                }
            }

            $asset = \App\Models\Asset::create([
                'name' => $request->name,
                'asset_code' => $asset_code,
                'category_id' => $request->category_id,
                'assigned_to' => $assigned_to,
                'purchase_date' => now(),
                'purchase_cost' => 0,
                'current_value' => 0,
                'status' => $status,
                'location' => $request->location,
                'serial_number' => $request->serial_number,
                'brand' => $request->brand,
                'model' => $request->model,
                'warranty_expiry' => $request->warranty_expiry,
                'warranty_bill' => $warranty_bill,
                'extra_details' => $request->extra_details ?? [],
                'description' => $request->description,
                'created_by' => auth()->id(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Asset created successfully!',
                    'data' => $asset,
                ]);
            }

            return redirect()->route('assets.index')
                ->with('success', 'Asset created successfully!');

        } catch (Exception $e) {
            Log::error('Asset creation failed: ' . $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create asset. Please try again.',
                ], 500);
            }
            return redirect()->back()
                ->with('error', 'Failed to create asset. Please try again.');
        }
    }

    public function getAssetAjax($id)
    {
        try {
            $asset = \App\Models\Asset::with('category')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $asset
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found.'
            ], 404);
        }
    }

    public function show($id)
    {
        $asset = \App\Models\Asset::with(['category', 'assignedUser', 'createdBy', 'maintenanceRecords'])
            ->findOrFail($id);

        return view('tenant.assets.show', compact('asset'));
    }

    public function edit($id)
    {
        $asset = \App\Models\Asset::findOrFail($id);
        $categories = AssetCategory::where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get();
        $users = User::whereIn('status', ['active', 'onboarding', 'onboarding_submitted'])
            ->with('department:id,name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'department_id']);

        return view('tenant.assets.edit', compact('asset', 'categories', 'users'));
    }

    public function update(Request $request, $id)
    {
        try {
            $asset = \App\Models\Asset::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:asset_categories,id',
                'assigned_to' => 'nullable|exists:users,id',
                'status' => 'required|in:available,assigned,maintenance,retired',
                'location' => 'nullable|string|max:255',
                'serial_number' => 'nullable|string|max:100',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'warranty_expiry' => 'nullable|date',
                'description' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $validator->errors()->first(),
                        'errors' => $validator->errors(),
                    ], 422);
                }
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            if ($request->hasFile('warranty_bill')) {
                $file = $request->file('warranty_bill');
                $filename = 'bill_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('assets/bills', $filename, 'public');
                $asset->warranty_bill = 'assets/bills/' . $filename;
            }

            $status = $request->status;
            $assigned_to = $request->assigned_to;

            if ($status === 'maintenance' || $status === 'retired') {
                $assigned_to = null;
            } elseif ($assigned_to) {
                $status = 'assigned';
            } else {
                if ($status === 'assigned') {
                    $status = 'available';
                }
            }

            $asset->update([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'assigned_to' => $assigned_to,
                'status' => $status,
                'location' => $request->location,
                'serial_number' => $request->serial_number,
                'brand' => $request->brand,
                'model' => $request->model,
                'warranty_expiry' => $request->warranty_expiry,
                'extra_details' => $request->extra_details ?? [],
                'description' => $request->description,
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Asset updated successfully!',
                    'data' => $asset,
                ]);
            }

            return redirect()->route('assets.index')
                ->with('success', 'Asset updated successfully!');

        } catch (Exception $e) {
            Log::error('Asset update failed: ' . $e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update asset. Please try again.',
                ], 500);
            }
            return redirect()->back()
                ->with('error', 'Failed to update asset. Please try again.');
        }
    }

    public function destroy($id)
    {
        try {
            $asset = \App\Models\Asset::findOrFail($id);
            $asset->delete();

            if (request()->ajax()) {
                return response()->json(['success' => true, 'message' => 'Asset deleted successfully!']);
            }

            return redirect()->route('assets.index')
                ->with('success', 'Asset deleted successfully!');

        } catch (Exception $e) {
            Log::error('Asset deletion failed: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete asset.'], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to delete asset. Please try again.');
        }
    }

    public function getListAjax(Request $request)
    {
        try {
            $query = \App\Models\Asset::with(['category', 'assignedUser']);

            // Apply search
            if ($search = $request->input('searchTerm')) {
                $query->where(function ($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('brand', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%")
                      ->orWhereHas('assignedUser', function ($qu) use ($search) {
                          $qu->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                      });
                });
            }

            if ($category = $request->input('category')) {
                $query->where('category_id', $category);
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }
            
            if ($userId = $request->input('user_id')) {
                $query->where('assigned_to', $userId);
            }

            // Department filter: assets have no department of their own, so scope
            // by the department of the user the asset is assigned to.
            if ($deptId = $request->input('department_id')) {
                $query->whereHas('assignedUser', function ($qu) use ($deptId) {
                    $qu->where('department_id', $deptId);
                });
            }

            $statusBadgeMap = [
                'available'   => 'bg-success',
                'assigned'    => 'bg-primary',
                'maintenance' => 'bg-warning',
                'retired'     => 'bg-danger',
            ];

            return DataTables::of($query)
                ->addColumn('action', function ($asset) {
                    $editBtn     = '';
                    $deleteBtn   = '';
                    $transferBtn = '';
                    $canEdit = auth()->user()->hasRole(['admin', 'hr']) || auth()->user()->can('assets.edit');
                    if ($canEdit) {
                        $editBtn = '<button class="btn btn-sm btn-icon edit-record" data-id="' . $asset->id . '"><i class="bx bx-pencil text-primary"></i></button>';
                    }
                    if (auth()->user()->hasRole(['admin', 'hr'])) {
                        $deleteBtn = '<button class="btn btn-sm btn-icon delete-record" data-id="' . $asset->id . '"><i class="bx bx-trash text-danger"></i></button>';
                        if ($asset->status === 'assigned') {
                            $currentUser = $asset->assignedUser
                                ? e($asset->assignedUser->first_name . ' ' . $asset->assignedUser->last_name)
                                : 'Unassigned';
                            $transferBtn = '<button class="btn btn-sm btn-icon transfer-record" data-id="' . $asset->id . '" data-current-user="' . $currentUser . '" title="Transfer to another user"><i class="bx bx-transfer-alt text-info"></i></button>';
                        }
                    }
                    return '<div class="d-flex align-items-center justify-content-center gap-2">' . $editBtn . $transferBtn . $deleteBtn . '</div>';
                })
                ->addColumn('status_badge', function ($asset) use ($statusBadgeMap) {
                    $cls = $statusBadgeMap[$asset->status] ?? 'bg-label-secondary';
                    return '<span class="badge ' . $cls . ' rounded-pill">' . ucfirst($asset->status ?? 'N/A') . '</span>';
                })
                ->addColumn('assigned_user', function ($asset) {
                    return $asset->assignedUser
                        ? e($asset->assignedUser->first_name . ' ' . $asset->assignedUser->last_name)
                        : '<span class="text-muted">Unassigned</span>';
                })
                ->addColumn('category_name', function ($asset) {
                    return $asset->category
                        ? e($asset->category->name)
                        : '<span class="text-muted">N/A</span>';
                })
                ->rawColumns(['action', 'status_badge', 'assigned_user', 'category_name'])
                ->make(true);

        } catch (\Exception $e) {
            Log::error('AssetController@getListAjax: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load assets: ' . $e->getMessage()], 500);
        }
    }

    public function assignAsset(Request $request, $id)
    {
        try {
            $asset = \App\Models\Asset::findOrFail($id);
            $asset->update([
                'assigned_to' => $request->user_id,
                'status' => 'assigned',
            ]);

            // Create assignment history
            \App\Models\AssetAssignment::create([
                'asset_id' => $id,
                'user_id' => $request->user_id,
                'assigned_at' => now(),
                'status' => 'assigned',
                'notes' => $request->notes ?? 'Assigned from Admin Panel'
            ]);

            return response()->json(['success' => true, 'message' => 'Asset assigned successfully!']);

        } catch (Exception $e) {
            Log::error('Asset assignment failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to assign asset.'], 500);
        }
    }

    public function unassignAsset($id)
    {
        try {
            $asset = \App\Models\Asset::findOrFail($id);
            // Record History
            if (Schema::hasTable('asset_assignments')) {
                \App\Models\AssetAssignment::where('asset_id', $id)
                    ->whereNull('returned_at')
                    ->update(['returned_at' => now(), 'notes' => 'Returned to Inventory']);
            }

            $asset->update([
                'assigned_to' => null,
                'status' => 'available',
            ]);

            return response()->json(['success' => true, 'message' => 'Asset unassigned successfully!']);

        } catch (Exception $e) {
            Log::error('Asset unassignment failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to unassign asset.'], 500);
        }
    }

    public function transferAsset(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $asset = \App\Models\Asset::findOrFail($id);
            $fromUser = $asset->assignedUser;

            if ((int) $asset->assigned_to === (int) $request->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset is already assigned to this user.',
                ], 422);
            }

            DB::transaction(function () use ($asset, $request, $fromUser) {
                // Close whatever assignment is currently open before opening the new one,
                // otherwise history shows two overlapping "active" holders for the asset.
                \App\Models\AssetAssignment::where('asset_id', $asset->id)
                    ->whereNull('returned_at')
                    ->update([
                        'returned_at' => now(),
                        'notes' => 'Transferred to ' . optional(\App\Models\User::find($request->user_id))->first_name,
                    ]);

                $asset->update([
                    'assigned_to' => $request->user_id,
                    'status' => 'assigned',
                ]);

                \App\Models\AssetAssignment::create([
                    'asset_id' => $asset->id,
                    'user_id' => $request->user_id,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'notes' => $request->notes ?: ('Transferred from ' . ($fromUser?->first_name ?? 'Unassigned')),
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Asset transferred successfully!']);

        } catch (Exception $e) {
            Log::error('Asset transfer failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to transfer asset.'], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            $searchTerm = $request->input('searchTerm');
            $category = $request->input('category');
            $status = $request->input('status');
            $userId = $request->input('user_id');
            $departmentId = $request->input('department_id');

            return Excel::download(new AssetsExport($searchTerm, $category, $status, $userId, $departmentId), 'assets_export_' . date('Y_m_d_H_i_s') . '.xlsx');
        } catch (\Exception $e) {
            Log::error('Asset Export failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export assets. Please try again.');
        }
    }
}
