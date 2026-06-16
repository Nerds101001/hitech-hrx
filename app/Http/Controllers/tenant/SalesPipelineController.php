<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalesPipelineController extends Controller
{
    public function index(Request $request)
    {
        // Get current financial year as default (April to March)
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        
        if ($currentMonth >= 4) {
            $fyStart = Carbon::create($currentYear, 4, 1);
            $fyEnd = Carbon::create($currentYear + 1, 3, 31);
            $defaultFy = $currentYear . '-' . ($currentYear + 1);
        } else {
            $fyStart = Carbon::create($currentYear - 1, 4, 1);
            $fyEnd = Carbon::create($currentYear, 3, 31);
            $defaultFy = ($currentYear - 1) . '-' . $currentYear;
        }

        $selectedFy = $request->input('fy', $defaultFy);
        
        // Parse selected FY
        $parts = explode('-', $selectedFy);
        if (count($parts) == 2) {
            $fyStart = Carbon::create((int)$parts[0], 4, 1);
            $fyEnd = Carbon::create((int)$parts[1], 3, 31);
        }

        // Generate the months for the selected FY, stopping at the current month
        $months = [];
        $tempDate = $fyStart->copy();
        
        $currentMonthEnd = Carbon::now()->endOfMonth();

        while ($tempDate <= $fyEnd) {
            // Stop if the month is in the future relative to the current real date
            if ($tempDate->copy()->startOfMonth() > $currentMonthEnd) {
                break;
            }
            $months[] = [
                'key' => $tempDate->format('Y-m'),
                'display' => $tempDate->format('M y')
            ];
            $tempDate->addMonth();
        }
        // Most recent month first so users see the current month without scrolling right
        $months = array_reverse($months);

        $query = SalesPipeline::with(['salesperson', 'ccare', 'months' => function($q) use ($months) {
            $monthKeys = array_column($months, 'key');
            $q->whereIn('month_year', $monthKeys);
        }])->where('is_locked', true);

        $user = auth()->user();
        $isCcare = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        
        if (!$user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager'])) {
            if ($isCcare || $isNewBiz) {
                $mappedSalespersonIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id');
                $query->whereIn('salesperson_id', $mappedSalespersonIds);
            } else {
                $query->where('salesperson_id', $user->id);
            }
        }

        $pipelines = $query->orderBy('id', 'desc')->get();
        
        if (($isCcare || $isNewBiz) && !$user->hasRole('Admin')) {
            $salespersonIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)
                ->pluck('sales_user_id')
                ->unique()
                ->toArray();
            $assignedUsers = User::where('status', 'active')->whereIn('id', $salespersonIds)->get();
        } else {
            $assignedUsers = User::where('status', 'active')->get();
        }
        
        $salespersonTargets = \App\Models\SalespersonTarget::all();

        // Calculate YoY totals
        $totals = [
            '25_26' => $pipelines->sum('yoy_25_26'),
            '24_25' => $pipelines->sum('yoy_24_25'),
            '23_24' => $pipelines->sum('yoy_23_24'),
            '22_23' => $pipelines->sum('yoy_22_23'),
        ];

        $monthlyActuals = [];
        $monthlyTargets = [];
        foreach ($months as $m) {
            $monthlyActuals[$m['key']] = $pipelines->map(function($p) use ($m) {
                $mData = $p->months->firstWhere('month_year', $m['key']);
                return $mData ? $mData->sale_amount : 0;
            })->sum();

            $targetsForMonth = $salespersonTargets->where('month_year', $m['key']);
            if (!$isCcare && !$user->hasRole('Admin')) {
                $targetsForMonth = $targetsForMonth->where('salesperson_id', $user->id);
            }

            $monthlyTargets[$m['key']] = [
                'kingo' => $targetsForMonth->sum('kingo_target'),
                'bingo' => $targetsForMonth->sum('bingo_target'),
            ];
        }

        $currentMonthKey = \Carbon\Carbon::now()->format('Y-m');
        $totalNewClients = $pipelines->count();

        // Calculate total pending for current month
        $totalPendingCurrentMonth = $pipelines->map(function($p) use ($currentMonthKey) {
            $mData = $p->months->firstWhere('month_year', $currentMonthKey);
            return $mData ? $mData->pending_amount : 0;
        })->sum();

        \Illuminate\Support\Facades\Log::info('PIPELINES COUNT: ' . $pipelines->count() . ' User Dept: ' . ($user->department->name ?? 'none'));

        return view('tenant.sales-pipeline.index', compact('pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyTargets', 'currentMonthKey', 'totalNewClients', 'totalPendingCurrentMonth'));
    }

    public function actualPipelineIndex(Request $request)
    {
        // Get current financial year as default (April to March)
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        
        if ($currentMonth >= 4) {
            $fyStart = Carbon::create($currentYear, 4, 1);
            $fyEnd = Carbon::create($currentYear + 1, 3, 31);
            $defaultFy = $currentYear . '-' . ($currentYear + 1);
        } else {
            $fyStart = Carbon::create($currentYear - 1, 4, 1);
            $fyEnd = Carbon::create($currentYear, 3, 31);
            $defaultFy = ($currentYear - 1) . '-' . $currentYear;
        }

        $selectedFy = $request->input('fy', $defaultFy);
        
        // Parse selected FY
        $parts = explode('-', $selectedFy);
        if (count($parts) == 2) {
            $fyStart = Carbon::create((int)$parts[0], 4, 1);
            $fyEnd = Carbon::create((int)$parts[1], 3, 31);
        }

        // Generate the months for the selected FY, stopping at the current month
        $months = [];
        $tempDate = $fyStart->copy();
        
        $currentMonthEnd = Carbon::now()->endOfMonth();

        while ($tempDate <= $fyEnd) {
            // Stop if the month is in the future relative to the current real date
            if ($tempDate->copy()->startOfMonth() > $currentMonthEnd) {
                break;
            }
            $months[] = [
                'key' => $tempDate->format('Y-m'),
                'display' => $tempDate->format('M y')
            ];
            $tempDate->addMonth();
        }
        // Most recent month first so users see the current month without scrolling right
        $months = array_reverse($months);

        $query = SalesPipeline::with(['salesperson', 'ccare', 'newBiz', 'months' => function($q) use ($months) {
            $monthKeys = array_column($months, 'key');
            $q->whereIn('month_year', $monthKeys);
        }])->where('is_locked', false);

        $user = auth()->user();
        $isCcare = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        
        if (!$user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager'])) {
            if ($isCcare || $isNewBiz) {
                $mappedSalespersonIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)->pluck('sales_user_id');
                $query->whereIn('salesperson_id', $mappedSalespersonIds);
            } else {
                $query->where('salesperson_id', $user->id);
            }
        }

        $pipelines = $query->orderBy('id', 'desc')->get();
        
        if (($isCcare || $isNewBiz) && !$user->hasRole('Admin')) {
            $salespersonIds = \App\Models\CcSalespersonMap::where('cc_user_id', $user->id)
                ->pluck('sales_user_id')
                ->unique()
                ->toArray();
            $assignedUsers = User::where('status', 'active')->whereIn('id', $salespersonIds)->get();
        } else {
            $assignedUsers = User::where('status', 'active')->get();
        }
        
        $salespersonTargets = \App\Models\SalespersonTarget::all();

        // Calculate YoY totals
        $totals = [
            '25_26' => $pipelines->sum('yoy_25_26'),
            '24_25' => $pipelines->sum('yoy_24_25'),
            '23_24' => $pipelines->sum('yoy_23_24'),
            '22_23' => $pipelines->sum('yoy_22_23'),
        ];

        $monthlyActuals = [];
        $monthlyTargets = [];
        foreach ($months as $m) {
            $monthlyActuals[$m['key']] = $pipelines->map(function($p) use ($m) {
                $mData = $p->months->firstWhere('month_year', $m['key']);
                return $mData ? $mData->sale_amount : 0;
            })->sum();

            $targetsForMonth = $salespersonTargets->where('month_year', $m['key']);
            if (!$isCcare && !$user->hasRole('Admin')) {
                $targetsForMonth = $targetsForMonth->where('salesperson_id', $user->id);
            }

            $monthlyTargets[$m['key']] = [
                'kingo' => $targetsForMonth->sum('kingo_target'),
                'bingo' => $targetsForMonth->sum('bingo_target'),
            ];
        }

        $currentMonthKey = \Carbon\Carbon::now()->format('Y-m');

        return view('tenant.sales-pipeline.actual-pipeline', compact('pipelines', 'months', 'selectedFy', 'defaultFy', 'assignedUsers', 'isCcare', 'isNewBiz', 'totals', 'salespersonTargets', 'monthlyActuals', 'monthlyTargets', 'currentMonthKey'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'party_name' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $isCcareUser = $user->department && $user->department->name === 'Customer Care';
        $isNewBizUser = $user->department && $user->department->name === 'New Biz';
        $isAdmin = $user->hasRole(['admin', 'Admin', 'hr', 'manager']);

        $salespersonId = $request->salesperson_id;
        $ccareId = $request->ccare_id;
        $newBizId = $request->new_biz_id;

        // Auto-assign the current user to their role column if not admin
        if (!$isAdmin) {
            if ($isCcareUser) {
                $ccareId = $ccareId ?: $user->id;
            } elseif ($isNewBizUser) {
                $newBizId = $newBizId ?: $user->id;
            } else {
                // Salesperson
                $salespersonId = $salespersonId ?: $user->id;
            }
        }

        $pipeline = SalesPipeline::create([
            'party_name'               => $request->party_name,
            'salesperson_id'           => $salespersonId,
            'ccare_id'                 => $ccareId,
            'new_biz_id'               => $newBizId,
            'type'                     => $request->type,
            'status_stage'             => $request->status_stage ?? 'Prospect',
            'status_remarks'           => $request->status_remarks,
            'total_business_potential' => $request->total_business_potential ?? 0,
            'product'                  => $request->product,
            'address'                  => $request->address,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $pipeline->id]);
        }

        return redirect()->back()->with('success', 'Pipeline added successfully.');
    }

    public function update(Request $request, $id)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        if (!$isPrivileged && !$isCcare && !$isNewBiz && $pipeline->salesperson_id !== $user->id) {
            if ($request->ajax()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $data = $request->only([
            'party_name',
            'salesperson_id',
            'ccare_id',
            'new_biz_id',
            'status_stage',
            'type',
            'status_remarks',
            'total_business_potential',
            'yoy_22_23',
            'yoy_23_24',
            'yoy_24_25',
            'yoy_25_26',
            'address',
            'product'
        ]);

        if (isset($data['status_stage']) && in_array($data['status_stage'], ['Order', 'Repeat Order'])) {
            $data['is_locked'] = true;
        }

        $pipeline->update($data);

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Pipeline updated.');
    }

    public function monthUpdate(Request $request)
    {
        $request->validate([
            'sales_pipeline_id' => 'required|exists:sales_pipelines,id',
            'month_year' => 'required|string|size:7', // YYYY-MM
            'field' => 'required|string|in:forecast_amount,sale_amount,pending_amount,remarks',
            'value' => 'nullable'
        ]);

        $pipeline = SalesPipeline::findOrFail($request->sales_pipeline_id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        if (!$isPrivileged && !$isCcare && !$isNewBiz && $pipeline->salesperson_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $monthData = SalesPipelineMonth::firstOrCreate(
            [
                'sales_pipeline_id' => $request->sales_pipeline_id,
                'month_year' => $request->month_year
            ]
        );

        $field = $request->field;
        $monthData->$field = $request->value;
        $monthData->save();

        if ($field === 'sale_amount') {
            if ($pipeline->type === 'New Biz') {
                $monthsWithSales = SalesPipelineMonth::where('sales_pipeline_id', $pipeline->id)
                    ->where('sale_amount', '>', 0)
                    ->count();
                if ($monthsWithSales >= 5) {
                    $pipeline->type = 'CCare';
                    $pipeline->converted_from_newbiz = true;
                    $pipeline->save();
                }
            }
        }

        return response()->json(['success' => true, 'data' => $monthData]);
    }
    
    public function destroy($id)
    {
        $pipeline = SalesPipeline::findOrFail($id);
        $user = auth()->user();
        $isPrivileged = $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'manager', 'Manager']);
        $isCcare  = $user->department && $user->department->name === 'Customer Care';
        $isNewBiz = $user->department && $user->department->name === 'New Biz';
        if (!$isPrivileged && !$isCcare && !$isNewBiz && $pipeline->salesperson_id !== $user->id) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }
        $pipeline->delete();
        return redirect()->back()->with('success', 'Deleted successfully.');
    }
}
