<?php

namespace App\Http\Controllers\Payment;

use App\Enums\OfflineRequestStatus;
use App\Enums\OrderType;
use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\SuperAdmin\OfflineRequest;
use App\Models\SuperAdmin\Plan;
use App\Notifications\SuperAdmin\Order\NewOfflineOrderRequestSa;
use App\Notifications\SuperAdmin\Order\OfflineOrderRequestCancellationSa;
use App\Services\PlanService\ISubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfflinePaymentController extends Controller
{
  private ISubscriptionService $subscriptionService;

  function __construct(ISubscriptionService $subscriptionService)
  {
    $this->subscriptionService = $subscriptionService;
  }

  public function create(Request $request)
  {
    $validated = $request->validate([
      'paymentModal-planId' => 'required|exists:plans,id',
      'gateway' => 'required|in:offline',
      'paymentModal-users' => 'nullable|numeric',
      'type' => ['required', Rule::in(array_column(OrderType::cases(), 'value'))],
    ]);

    $user = auth()->user();

    if ($user->hasActivePlan()) {
      return redirect()->back()->with('error', 'You already have an active plan');
    }

    if ($user->hasPendingOfflineRequest()) {
      return redirect()->back()->with('error', 'You already have a pending request');
    }

    $plan = Plan::find($validated['paymentModal-planId']);

    $offlineRequest = new OfflineRequest();
    $offlineRequest->user_id = $user->id;
    $offlineRequest->plan_id = $plan->id;
    $offlineRequest->per_user_price = $plan->per_user_price;
    $offlineRequest->amount = $plan->base_price;
    $offlineRequest->additional_users = $validated['paymentModal-users'] ?? 0;
    $offlineRequest->type = OrderType::from($validated['type']);

    $totalAmount = $plan->base_price;
    if ($offlineRequest->additional_users > 0) {
      $totalAmount += $offlineRequest->additional_users * $plan->per_user_price;
    }

    $offlineRequest->total_amount = $totalAmount;
    $offlineRequest->status = OfflineRequestStatus::PENDING;
    $offlineRequest->created_by_id = $user->id;
    $offlineRequest->save();

    NotificationHelper::notifySuperAdmins(new NewOfflineOrderRequestSa($offlineRequest));

    return redirect()->back()->with('success', 'Your request has been submitted. Please wait for approval');
  }


  public function cancelOfflineRequest($id)
  {
    $user = auth()->user();
    $offlineRequest = OfflineRequest::where('user_id', $user->id)
      ->where('id', $id)
      ->first();

    if ($offlineRequest) {
      $offlineRequest->status = OfflineRequestStatus::CANCELLED;
      $offlineRequest->save();
    }

    NotificationHelper::notifySuperAdmins(new OfflineOrderRequestCancellationSa($offlineRequest));

    return redirect()->back()->with('success', 'Your request has been cancelled');
  }

  public function payOfflineForUserAdd(Request $request)
  {
    $validated = $request->validate([
      'addUserModal-users' => 'required|numeric',
      'gateway' => 'required|in:offline',
    ]);

    $user = auth()->user();

    if (!$user->hasActivePlan()) {
      return redirect()->back()->with('error', 'You do not have an active plan');
    }

    if ($user->hasPendingOfflineRequest()) {
      return redirect()->back()->with('error', 'You already have a pending request');
    }

    $amount = $this->subscriptionService->getAddUserTotalAmount($validated['addUserModal-users']);

    $offlineRequest = new OfflineRequest();
    $offlineRequest->user_id = $user->id;
    $offlineRequest->plan_id = $user->plan_id;
    $offlineRequest->amount = $amount;
    $offlineRequest->total_amount = $amount;
    $offlineRequest->additional_users = $validated['addUserModal-users'];
    $offlineRequest->per_user_price = $amount / $validated['addUserModal-users'];
    $offlineRequest->type = OrderType::ADDITIONAL_USER;
    $offlineRequest->status = OfflineRequestStatus::PENDING;
    $offlineRequest->created_by_id = $user->id;
    $offlineRequest->save();

    return redirect()->back()->with('success', 'Your request has been submitted. Please wait for approval');
  }

  public function payOfflineForRenewal(Request $request)
  {
    $validated = $request->validate([
      'gateway' => 'required|in:offline',
    ]);

    $user = auth()->user();

    if ($user->hasPendingOfflineRequest()) {
      return redirect()->back()->with('error', 'You already have a pending request');
    }

    $subscription = $this->subscriptionService->getSubscription();

    $amount = $this->subscriptionService->getRenewalAmount();

    $offlineRequest = new OfflineRequest();
    $offlineRequest->user_id = $user->id;
    $offlineRequest->plan_id = $user->plan_id;
    $offlineRequest->amount = $amount;
    $offlineRequest->total_amount = $amount;
    $offlineRequest->additional_users = $subscription->additional_users;
    $offlineRequest->per_user_price = $subscription->plan->per_user_price;
    $offlineRequest->type = OrderType::RENEWAL;
    $offlineRequest->status = OfflineRequestStatus::PENDING;
    $offlineRequest->created_by_id = $user->id;
    $offlineRequest->save();

    return redirect()->back()->with('success', 'Your request has been submitted. Please wait for approval');
  }

  public function payOfflineForUpgrade(Request $request)
  {
    $validated = $request->validate([
      'gateway' => 'required|in:offline',
      'upgradeModal-planId' => 'required|exists:plans,id',
    ]);

    $planId = $validated['upgradeModal-planId'];

    $user = auth()->user();

    if ($user->hasPendingOfflineRequest()) {
      return redirect()->back()->with('error', 'You already have a pending request');
    }

    if ($user->plan_id == $planId) {
      return redirect()->back()->with('error', 'You already have this plan');
    }

    $subscription = $this->subscriptionService->getSubscription();

    $amount = $this->subscriptionService->getDifferencePriceForUpgrade($planId);

    $plan = Plan::find($planId);

    $offlineRequest = new OfflineRequest();
    $offlineRequest->user_id = $user->id;
    $offlineRequest->plan_id = $plan->id;
    $offlineRequest->amount = $amount;
    $offlineRequest->total_amount = $amount;
    $offlineRequest->additional_users = $subscription->additional_users;
    $offlineRequest->per_user_price = $plan->per_user_price;
    $offlineRequest->type = OrderType::UPGRADE;
    $offlineRequest->status = OfflineRequestStatus::PENDING;
    $offlineRequest->created_by_id = $user->id;
    $offlineRequest->save();

    return redirect()->back()->with('success', 'Your request has been submitted. Please wait for approval');
  }
}
