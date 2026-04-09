<?php

namespace App\Http\Controllers;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Enums\OfflineRequestStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\SubscriptionStatus;
use App\Models\SuperAdmin\OfflineRequest;
use App\Models\SuperAdmin\Order;
use App\Models\SuperAdmin\Subscription;
use App\Models\User;
use App\Services\PlanService\ISubscriptionService;
use Constants;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OfflineRequestController extends Controller
{
  private ISubscriptionService $planService;

  function __construct(ISubscriptionService $planService)
  {
    $this->planService = $planService;
  }

  public function index()
  {
    return view('superAdmin.offlineRequest.index');
  }

  public function indexAjax(Request $request)
  {
    try {

      $columns = [
        1 => 'id',
        2 => 'user',
        3 => 'type',
        4 => 'plan',
        5 => 'additional_users',
        6 => 'total_amount',
        7 => 'order',
        8 => 'status',
      ];


      $query = OfflineRequest::query();

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      $totalData = $query->count();

      //Order only allowed by id, no other options are allowed due to complex structure
      if ($order == 'id') {
        $order = 'offline_requests.id';
        $query->orderBy($order, $dir);
      }


      if (empty($request->input('search.value'))) {
        $offlineRequests = $query->select(
          'offline_requests.*',
          'user.first_name',
          'user.last_name',
          'user.email',
          'plan.name as plan_name',
          'plan.included_users as plan_included_users',
          'plan.duration as plan_duration',
          'plan.duration_type as plan_duration_type',
        )
          ->leftJoin('users as user', 'offline_requests.user_id', '=', 'user.id')
          ->leftJoin('plans as plan', 'offline_requests.plan_id', '=', 'plan.id')
          ->leftJoin('orders as order', 'offline_requests.order_id', '=', 'order.id', 'plan.id', 'orders.included_users', '=', 'plan.included_users', 'orders.duration', '=', 'plan.duration', 'orders.duration_type', '=', 'plan.duration_type')
          ->offset($start)
          ->limit($limit)
          ->get();
      } else {
        $search = $request->input('search.value');

        $offlineRequests = $query->select(
          'offline_requests.*',
          'user.first_name',
          'user.last_name',
          'user.email',
          'plan.name as plan_name',
          'plan.included_users as plan_included_users',
          'plan.duration as plan_duration',
          'plan.duration_type as plan_duration_type',
        )
          ->leftJoin('users as user', 'offline_requests.user_id', '=', 'user.id')
          ->leftJoin('plans as plan', 'offline_requests.plan_id', '=', 'plan.id')
          ->leftJoin('orders as order', 'offline_requests.order_id', '=', 'order.id', 'plan.id', 'orders.included_users', '=', 'plan.included_users', 'orders.duration', '=', 'plan.duration', 'orders.duration_type', '=', 'plan.duration_type')
          ->where(function ($query) use ($search) {
            $query->where('offline_requests.id', 'LIKE', "%{$search}%")
              ->orWhere('offline_requests.user_id', 'LIKE', "%{$search}%")
              ->orWhere('user.first_name', 'LIKE', "%{$search}%")
              ->orWhere('user.last_name', 'LIKE', "%{$search}%")
              ->orWhere('user.email', 'LIKE', "%{$search}%")
              ->orWhere('plan.name', 'LIKE', "%{$search}%");
          })
          ->offset($start)
          ->limit($limit)
          ->get();
      }

      $totalFiltered = $offlineRequests->count();

      $data = [];
      if (!empty($offlineRequests)) {
        foreach ($offlineRequests as $offlineRequest) {
          $nestedData['id'] = $offlineRequest->id;
          $nestedData['user_id'] = $offlineRequest->user_id;
          $nestedData['plan_id'] = $offlineRequest->plan_name;
          $nestedData['additional_users'] = $offlineRequest->additional_users;
          $nestedData['total_amount'] = $offlineRequest->total_amount;
          $nestedData['type'] = $offlineRequest->type;
          $nestedData['order_id'] = $offlineRequest->order_id;
          $nestedData['status'] = $offlineRequest->status;

          //Plan
          $nestedData['included_users'] = $offlineRequest->plan_included_users;
          $nestedData['duration'] = $offlineRequest->plan_duration;
          $nestedData['duration_type'] = $offlineRequest->plan_duration_type;

          //User
          $nestedData['user_name'] = $offlineRequest->user->getFullName();
          $nestedData['user_initial'] = $offlineRequest->user->getInitials();
          $nestedData['user_email'] = $offlineRequest->user->email;
          $nestedData['user_profile_image'] =
            $offlineRequest->user->profile_picture != null ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $offlineRequest->user->profile_picture) : null;

          $data[] = $nestedData;
        }
      }

      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'data' => $data,
      ]);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return Error::response('Something went wrong');
    }
  }

  public function getByIdAjax($id)
  {
    $offlineRequest = OfflineRequest::findOrFail($id);

    if (!$offlineRequest) {
      return Error::response('Offline request not found');
    }

    $response = [
      'id' => $offlineRequest->id,
      'userName' => $offlineRequest->user->getFullName(),
      'userEmail' => $offlineRequest->user->email,
      'planName' => $offlineRequest->plan->name,
      'additionalUsers' => $offlineRequest->additional_users,
      'totalAmount' => $offlineRequest->total_amount,
      'type' => $offlineRequest->type,
      'status' => $offlineRequest->status,
      'createdAt' => $offlineRequest->created_at->format(Constants::DateTimeFormat),
    ];

    return Success::response($response);
  }

  public function actionAjax(Request $request)
  {
    $validated = $request->validate([
      'id' => 'required|exists:offline_requests,id',
      'status' => 'required|in:approved,rejected',
      'adminNotes' => 'nullable|string',
    ]);

    try {

      $offlineRequest = OfflineRequest::findOrFail($validated['id']);
      $offlineRequest->status = OfflineRequestStatus::from($validated['status']);

      if ($validated['status'] == 'approved') {
        $offlineRequest->approval_reason = $validated['adminNotes'] ?? null;
        $this->approveRequest($offlineRequest);
      } else {
        $offlineRequest->reject_reason = $validated['adminNotes'] ?? null;
      }

      $offlineRequest->save();

      return back()->with('success', 'Offline request ' . $validated['status'] . ' successfully.');
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return back()->with('error', 'Something went wrong. Please try again.');
    }
  }

  private function approveRequest(OfflineRequest $offlineRequest): void
  {

    if ($offlineRequest->type == OrderType::ADDITIONAL_USER) {
      $this->approveAdditionalUserRequest($offlineRequest);
    } else if ($offlineRequest->type == OrderType::PLAN) {
      $this->approvePlanRequest($offlineRequest);
    } else if ($offlineRequest->type == OrderType::RENEWAL) {
      $this->approveRenewalRequest($offlineRequest);
    } else if ($offlineRequest->type == OrderType::UPGRADE) {
      $this->approveUpgradeRequest($offlineRequest);
    }
  }

  private function approvePlanRequest(OfflineRequest $offlineRequest): void
  {

    $order = new Order();
    $order->user_id = $offlineRequest->user_id;
    $order->plan_id = $offlineRequest->plan_id;
    $order->additional_users = $offlineRequest->additional_users;

    $totalAmount = $offlineRequest->plan->base_price;
    if ($offlineRequest->additional_users > 0) {
      $totalAmount += $offlineRequest->additional_users * $offlineRequest->plan->per_user_price;
    }

    $order->per_user_price = $offlineRequest->plan->per_user_price;
    $order->amount = $offlineRequest->plan->base_price;
    $order->total_amount = $totalAmount;
    $order->status = OrderStatus::COMPLETED;
    $order->payment_gateway = 'offline';
    $order->paid_at = now();
    $order->save();

    $offlineRequest->order_id = $order->id;
    $offlineRequest->save();

    $endDate = $this->planService->generatePlanExpiryDate($offlineRequest->plan);

    $user = User::find($offlineRequest->user_id);
    $user->plan_id = $offlineRequest->plan_id;
    $user->plan_expired_date = $endDate;

    $user->save();

    Subscription::create([
      'user_id' => $user->id,
      'plan_id' => $order->plan_id,
      'users_count' => $offlineRequest->plan->included_users + $order->additional_users,
      'total_price' => $order->total_amount,
      'start_date' => now(),
      'end_date' => $endDate,
      'status' => SubscriptionStatus::ACTIVE,
    ]);
  }

  private function approveAdditionalUserRequest(OfflineRequest $offlineRequest): void
  {
    $order = new Order();
    $order->user_id = $offlineRequest->user_id;
    $order->plan_id = $offlineRequest->plan_id;
    $order->additional_users = $offlineRequest->additional_users;
    $order->per_user_price = $offlineRequest->plan->per_user_price;
    $order->amount = $offlineRequest->amount;
    $order->total_amount = $offlineRequest->total_amount;
    $order->status = OrderStatus::COMPLETED;
    $order->type = OrderType::ADDITIONAL_USER;
    $order->payment_gateway = 'offline';
    $order->paid_at = now();
    $order->save();

    $offlineRequest->order_id = $order->id;

    $offlineRequest->save();

    $this->planService->addUsersToSubscription($order);
  }

  private function approveRenewalRequest(OfflineRequest $offlineRequest): void
  {
    $order = new Order();
    $order->user_id = $offlineRequest->user_id;
    $order->plan_id = $offlineRequest->plan_id;
    $order->additional_users = $offlineRequest->additional_users;
    $order->per_user_price = $offlineRequest->plan->per_user_price;
    $order->type = OrderType::RENEWAL;
    $order->amount = $offlineRequest->amount;
    $order->total_amount = $offlineRequest->total_amount;
    $order->status = OrderStatus::COMPLETED;
    $order->payment_gateway = 'offline';
    $order->paid_at = now();
    $order->save();

    $offlineRequest->order_id = $order->id;

    $offlineRequest->save();

    $this->planService->renewPlan($order);
  }

  private function approveUpgradeRequest(OfflineRequest $offlineRequest): void
  {
    $order = new Order();
    $order->user_id = $offlineRequest->user_id;
    $order->plan_id = $offlineRequest->plan_id;
    $order->additional_users = $offlineRequest->additional_users;
    $order->per_user_price = $offlineRequest->plan->per_user_price;
    $order->type = OrderType::UPGRADE;
    $order->amount = $offlineRequest->amount;
    $order->total_amount = $offlineRequest->total_amount;
    $order->status = OrderStatus::COMPLETED;
    $order->payment_gateway = 'offline';
    $order->paid_at = now();
    $order->save();

    $offlineRequest->order_id = $order->id;

    $offlineRequest->save();

    $this->planService->upgradePlan($order);
  }
}
