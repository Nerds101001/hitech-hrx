<?php

namespace App\Http\Controllers;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\PlanDurationType;
use App\Enums\Status;
use App\Models\SuperAdmin\Plan;
use App\Services\PlanService\ISubscriptionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
  private ISubscriptionService $subscriptionService;

  function __construct(ISubscriptionService $subscriptionService)
  {
    $this->subscriptionService = $subscriptionService;
  }

  public function index()
  {
    return view('superAdmin.plan.index');
  }

  public function create()
  {
    return view('superAdmin.plan.create');
  }

  public function store(Request $request)
  {
    $validatedData = $request->validate([
      'name' => 'required|string|max:255',
      'duration' => 'required|numeric|min:1',
      'includedUsers' => 'nullable|numeric|min:0',
      'basePrice' => 'required|numeric|min:0',
      'perUserPrice' => 'required|numeric|min:0',
      'durationType' => ['required', 'string', Rule::in(array_column(PlanDurationType::cases(), 'value'))],
      'description' => 'nullable|string',
    ]);


    // Get dynamic module fields (fields with specific keys)
    $moduleFields = collect($request->all())->filter(function ($value, $key) {
      return preg_match('/^[A-Za-z0-9_]+$/', $key) && $value === 'on'; // Only fields with value 'on'
    })->keys();

    $plan = new Plan();
    $plan->name = $validatedData['name'];
    $plan->duration = $validatedData['duration'];
    $plan->included_users = $validatedData['includedUsers'] ?? 0;
    $plan->base_price = $validatedData['basePrice'];
    $plan->per_user_price = $validatedData['perUserPrice'];
    $plan->duration_type = $validatedData['durationType'];
    $plan->description = $validatedData['description'] ?? null;

    $plan->modules = $moduleFields;
    $plan->save();

    return redirect()->route('plans.index')->with('success', 'Plan added successfully');
  }

  public function edit($id)
  {
    return view('superAdmin.plan.edit', ['plan' => Plan::findOrFail($id)]);
  }

  public function update(Request $request)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $validatedData = $request->validate([
      'name' => 'required|string|max:255',
      'duration' => 'required|numeric|min:1',
      'includedUsers' => 'nullable|numeric|min:0',
      'basePrice' => 'required|numeric|min:0',
      'perUserPrice' => 'required|numeric|min:0',
      'durationType' => ['required', 'string', Rule::in(array_column(PlanDurationType::cases(), 'value'))],
      'description' => 'nullable|string',
    ]);

    // Get dynamic module fields (fields with specific keys)
    $moduleFields = collect($request->all())->filter(function ($value, $key) {
      return preg_match('/^[A-Za-z0-9_]+$/', $key) && $value === 'on'; // Only fields with value 'on'
    })->keys();

    $plan = Plan::findOrFail($request->id);
    // Update the plan details
    $plan->name = $validatedData['name'];
    $plan->duration = $validatedData['duration'];
    $plan->included_users = $validatedData['includedUsers'] ?? 0;
    $plan->base_price = $validatedData['basePrice'];
    $plan->per_user_price = $validatedData['perUserPrice'];
    $plan->duration_type = $validatedData['durationType'];
    $plan->description = $validatedData['description'] ?? null;

    // Update modules
    $plan->modules = $moduleFields;
    $plan->save();

    Log::info('Plan updated: ' . json_encode($plan));

    $this->subscriptionService->refreshPlanAccessForTenants($plan->id);

    return redirect()->route('plans.index')->with('success', 'Plan updated successfully');
  }


  public function indexAjax(Request $request)
  {
    try {
      $columns = [
        1 => 'id',
        2 => 'name',
        3 => 'duration_type',
        4 => 'base_price',
        5 => 'included_users',
        6 => 'per_user_price',
        7 => 'status',
      ];

      $search = [];

      $totalData = Plan::count();

      $totalFiltered = $totalData;

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      if (empty($request->input('search.value'))) {
        $plans = Plan::offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');
        $plans = Plan::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhere('notes', 'like', "%{$search}%")
          ->get();

        $totalFiltered = Plan::where('id', 'like', "%{$search}%")
          ->orWhere('name', 'like', "%{$search}%")
          ->orWhere('price', 'like', "%{$search}%")
          ->orWhere('duration_type', 'like', "%{$search}%")
          ->count();
      }

      $data = [];

      if (!empty($plans)) {
        foreach ($plans as $plan) {
          $nestedData['id'] = $plan->id;
          $nestedData['name'] = $plan->name;
          $nestedData['base_price'] = $plan->base_price;
          $nestedData['duration_type'] = $plan->duration_type;
          $nestedData['included_users'] = $plan->included_users;
          $nestedData['per_user_price'] = $plan->per_user_price;
          $nestedData['status'] = $plan->status;
          $data[] = $nestedData;
        }
      }

      return response()->json([
        "draw" => intval($request->input('draw')),
        "recordsTotal" => $totalData,
        "recordsFiltered" => $totalFiltered,
        "code" => 200,
        "data" => $data
      ]);

    } catch (Exception $e) {
      return Error::response($e->getMessage());
    }

  }

  public function addOrUpdatePlanAjax(Request $request)
  {
    
    $planId = $request->id;
    $request->validate([
      'name' => 'required|unique:plans,name,' . $planId,
      'duration' => 'required|numeric|min:0',
      'durationType' => 'required',
      "description" => "nullable",
      "includedUsers" => "required",
      "basePrice" => "required",
      "perUserPrice" => "required",
    ]);

    if ($planId) {
      $plan = Plan::find($planId);
      $plan->name = $request->name;
      $plan->duration = $request->duration;
      $plan->duration_type = $request->durationType;
      $plan->description = $request->description;
      $plan->included_users = $request->includedUsers;
      $plan->base_price = $request->basePrice;
      $plan->per_user_price = $request->perUserPrice;
      $plan->save();

      return Success::response('Updated');
    } else {
      $plan = new Plan();
      $plan->name = $request->name;
      $plan->duration = $request->duration;
      $plan->duration_type = $request->durationType;
      $plan->description = $request->description;
      $plan->included_users = $request->includedUsers;
      $plan->base_price = $request->basePrice;
      $plan->per_user_price = $request->perUserPrice;
      $plan->save();

      return Success::response('Added');
    }
  }

  public function getPlanAjax($id)
  {
    $plan = Plan::findOrFail($id);

    if (!$plan) {
      return Error::response('Plan not found');
    }
    $response = [
      "id" => $plan->id,
      "name" => $plan->name,
      "duration" => $plan->duration,
      "durationType" => $plan->duration_type,
      "description" => $plan->description,
      "includedUsers" => $plan->included_users,
      "basePrice" => $plan->base_price,
      "perUserPrice" => $plan->per_user_price
    ];
    return response()->json($response);
  }

  public function changeStatusAjax($id)
  {
    if (env('APP_DEMO')) {
      return redirect()->back()->with('error', 'This feature is disabled in the demo.');
    }

    $plan = Plan::findOrFail($id);


    if (!$plan) {
      return Error::response('Plan not found');
    }
    $plan->status = $plan->status == Status::ACTIVE ? Status::INACTIVE : Status::ACTIVE;
    $plan->save();
    return Success::response('Status changed successfully');
  }
}
