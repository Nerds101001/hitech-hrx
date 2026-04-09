<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Exception;

class AssetCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $stats = [
                'total' => AssetCategory::count(),
                'active' => AssetCategory::where('status', 'active')->count(),
                'inactive' => AssetCategory::where('status', 'inactive')->count(),
            ];

            return view('tenant.assets.categories.index', [
                'pageConfigs' => ['contentLayout' => 'wide'],
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            Log::error('Asset Category index error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load asset categories.');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:150|unique:asset_categories,name',
                'description' => 'nullable|string|max:1000',
                'status' => 'required|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            AssetCategory::create([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
                'parameters' => $request->parameters ? array_map('trim', explode(',', $request->parameters)) : [],
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Asset category created successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Asset Category creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset category.'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $category = AssetCategory::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $category
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $category = AssetCategory::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:150|unique:asset_categories,name,' . $id,
                'description' => 'nullable|string|max:1000',
                'status' => 'required|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $category->update([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
                'parameters' => $request->parameters ? array_map('trim', explode(',', $request->parameters)) : [],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Asset category updated successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Asset Category update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update asset category.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $category = AssetCategory::findOrFail($id);
            
            // Check if there are assets attached
            if($category->assets()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category because it has assets assigned to it.'
                ], 400);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Asset category deleted successfully!'
            ]);

        } catch (Exception $e) {
            Log::error('Asset Category deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete asset category.'
            ], 500);
        }
    }

    /**
     * Provide DataTables formatted list of categories
     */
    public function getListAjax(Request $request)
    {
        $categories = AssetCategory::withCount('assets')->orderBy('created_at', 'desc');

        return DataTables::of($categories)
            ->addColumn('action', function ($category) {
                $editBtn = '';
                $deleteBtn = '';
                
                if (auth()->user()->hasRole(['admin', 'hr'])) {
                    $editBtn = '<button class="btn btn-sm btn-primary edit-category" data-id="' . $category->id . '"><i class="bx bx-edit"></i></button>';
                    $deleteBtn = '<button class="btn btn-sm btn-danger delete-category" data-id="' . $category->id . '"><i class="bx bx-trash"></i></button>';
                }

                return '<div class="d-flex text-nowrap gap-2">' . $editBtn . $deleteBtn . '</div>';
            })
            ->addColumn('status_badge', function ($category) {
                $class = $category->status === 'active' ? 'bg-label-success' : 'bg-label-secondary';
                return '<span class="badge ' . $class . '">' . ucfirst($category->status) . '</span>';
            })
            ->addColumn('assets_count', function ($category) {
                return '<span class="badge bg-label-info">' . $category->assets_count . ' Assets</span>';
            })
            ->rawColumns(['action', 'status_badge', 'assets_count'])
            ->make(true);
    }
}
