<?php

namespace App\Http\Controllers\Payment;

use App\ApiClasses\Error;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\SuperAdmin\Order;
use App\Models\SuperAdmin\Plan;
use App\Models\SuperAdmin\SaSettings;
use App\Services\PlanService\ISubscriptionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayPaymentController extends Controller
{

  private ISubscriptionService $planService;

  function __construct(ISubscriptionService $planService)
  {
    $this->planService = $planService;
  }

  public function razorPayPayment(Request $request)
  {

    $plan = Plan::findOrFail($request->plan_id);

    $usersCount = $request->users;

    $settings = SaSettings::first();

    $api = new Api($settings->razorpay_key, $settings->razorpay_secret);

    $totalAmount = $this->planService->getPlanTotalAmount($plan, $usersCount);

    $totalInPaise = $totalAmount * 100;

    $order = $api->order->create([
      'receipt' => uniqid(),
      'amount' => $totalInPaise,
      'currency' => $request->currency,
      'notes' => [
        'plan_id' => $plan->id,
        'user_id' => auth()->id()
      ]
    ]);

    $localOrder = new Order();
    $localOrder->user_id = auth()->id();
    $localOrder->plan_id = $plan->id;
    $localOrder->amount = $plan->base_price;
    $localOrder->per_user_price = $plan->per_user_price;
    $localOrder->total_amount = $totalAmount;
    $localOrder->status = OrderStatus::PROCESSING;
    $localOrder->type = OrderType::PLAN;
    $localOrder->payment_gateway = 'razorpay';
    $localOrder->payment_data = json_encode($order);
    $localOrder->save();

    $response = [
      'success' => true,
      'key' => config('services.razorpay.key'),
      'amount' => $totalInPaise,
      'currency' => $request->currency,
      'name' => env('APP_NAME'),
      'description' => "Payment for {$plan->name}",
      'order_id' => $order->id,
      'local_order_id' => $localOrder->id,
      'prefill' => [
        'name' => auth()->user()->getFullName(),
        'email' => auth()->user()->email,
        'contact' => auth()->user()->phone
      ]
    ];

    return response()->json($response);
  }

  public function razorPayPaymentForAddUser(Request $request)
  {

    try {
      $usersCount = $request->users;

      $settings = SaSettings::first();

      $api = new Api($settings->razorpay_key, $settings->razorpay_secret);

      $totalAmount = round($this->planService->getAddUserTotalAmount($usersCount), 0);

      $totalInPaise = $totalAmount * 100;

      $order = $api->order->create([
        'receipt' => uniqid(),
        'amount' => $totalInPaise,
        'currency' => $request->currency,
        'notes' => [
          'type' => 'add_user',
          'user_id' => auth()->id()
        ]
      ]);

      $plan = Plan::findOrFail(auth()->user()->plan_id);

      $localOrder = new Order();
      $localOrder->user_id = auth()->id();
      $localOrder->plan_id = $plan->id;
      $localOrder->amount = $plan->base_price;
      $localOrder->per_user_price = $plan->per_user_price;
      $localOrder->additional_users = $usersCount;
      $localOrder->total_amount = $totalAmount;
      $localOrder->status = OrderStatus::PROCESSING;
      $localOrder->type = OrderType::ADDITIONAL_USER;
      $localOrder->payment_gateway = 'razorpay';
      $localOrder->payment_data = json_encode($order);
      $localOrder->save();

      $response = [
        'success' => true,
        'key' => config('services.razorpay.key'),
        'amount' => $totalInPaise,
        'currency' => $request->currency,
        'name' => env('APP_NAME'),
        'description' => "Payment for adding {$usersCount} users of plan {$plan->name}",
        'order_id' => $order->id,
        'local_order_id' => $localOrder->id,
        'prefill' => [
          'name' => auth()->user()->getFullName(),
          'email' => auth()->user()->email,
          'contact' => auth()->user()->phone
        ]
      ];

      return response()->json($response);
    } catch (Exception $e) {
      Log::error('Razorpay payment error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ]);
    }
  }

  public function razorPayPaymentForRenewal(Request $request)
  {

    try {
      $settings = SaSettings::first();

      $api = new Api($settings->razorpay_key, $settings->razorpay_secret);

      $totalAmount = round($this->planService->getRenewalAmount(), 0);

      $totalInPaise = $totalAmount * 100;

      $order = $api->order->create([
        'receipt' => uniqid(),
        'amount' => $totalInPaise,
        'currency' => $request->currency,
        'notes' => [
          'type' => 'renewal',
          'user_id' => auth()->id()
        ]
      ]);

      $plan = Plan::findOrFail(auth()->user()->plan_id);

      $subscription = $this->planService->getSubscription();

      $localOrder = new Order();
      $localOrder->user_id = auth()->id();
      $localOrder->plan_id = $plan->id;
      $localOrder->amount = $plan->base_price;
      $localOrder->per_user_price = $plan->per_user_price;
      $localOrder->additional_users = $subscription->additional_users;
      $localOrder->total_amount = $totalAmount;
      $localOrder->status = OrderStatus::PROCESSING;
      $localOrder->type = OrderType::RENEWAL;
      $localOrder->payment_gateway = 'razorpay';
      $localOrder->payment_data = json_encode($order);
      $localOrder->save();

      $response = [
        'success' => true,
        'key' => config('services.razorpay.key'),
        'amount' => $totalInPaise,
        'currency' => $request->currency,
        'name' => env('APP_NAME'),
        'description' => "Payment for renewal of plan {$plan->name}",
        'order_id' => $order->id,
        'local_order_id' => $localOrder->id,
        'prefill' => [
          'name' => auth()->user()->getFullName(),
          'email' => auth()->user()->email,
          'contact' => auth()->user()->phone
        ]
      ];

      return response()->json($response);
    } catch (Exception $e) {
      Log::error('Razorpay payment error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ]);
    }
  }

  public function razorPayPaymentForUpgrade(Request $request)
  {

    try {
      $settings = SaSettings::first();

      $plan = Plan::findOrFail($request->plan_id);

      if (auth()->user()->plan_id == $plan->id) {
        return Error::response('You already have this plan');
      }

      $api = new Api($settings->razorpay_key, $settings->razorpay_secret);

      $totalAmount = round($this->planService->getRenewalAmount(), 0);

      $totalInPaise = $totalAmount * 100;

      $order = $api->order->create([
        'receipt' => uniqid(),
        'amount' => $totalInPaise,
        'currency' => $request->currency,
        'notes' => [
          'type' => 'upgrade',
          'user_id' => auth()->id()
        ]
      ]);

      $subscription = $this->planService->getSubscription();

      $localOrder = new Order();
      $localOrder->user_id = auth()->id();
      $localOrder->plan_id = $plan->id;
      $localOrder->amount = $plan->base_price;
      $localOrder->per_user_price = $plan->per_user_price;
      $localOrder->additional_users = $subscription->additional_users;
      $localOrder->total_amount = $totalAmount;
      $localOrder->status = OrderStatus::PROCESSING;
      $localOrder->type = OrderType::UPGRADE;
      $localOrder->payment_gateway = 'razorpay';
      $localOrder->payment_data = json_encode($order);
      $localOrder->save();

      $response = [
        'success' => true,
        'key' => config('services.razorpay.key'),
        'amount' => $totalInPaise,
        'currency' => $request->currency,
        'name' => env('APP_NAME'),
        'description' => "Payment for upgrading to plan {$plan->name}",
        'order_id' => $order->id,
        'local_order_id' => $localOrder->id,
        'prefill' => [
          'name' => auth()->user()->getFullName(),
          'email' => auth()->user()->email,
          'contact' => auth()->user()->phone
        ]
      ];

      return response()->json($response);
    } catch (Exception $e) {
      Log::error('Razorpay payment error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ]);
    }
  }

  public function razorpayCallback(Request $request, $transaction_id, $local_order_id)
  {
    Log::info('Razorpay callback: ' . $transaction_id . ' ' . $local_order_id);
    try {

      $order = Order::find($local_order_id);

      $settings = SaSettings::first();
      $api = new Api($settings->razorpay_key, $settings->razorpay_secret);
      $payment = $api->payment->fetch($transaction_id);

      if ($payment->status === 'captured') {

        $order->status = OrderStatus::COMPLETED;
        $order->payment_response = json_encode($payment);
        $order->paid_at = now();
        $order->save();

        //Activation
        if ($order->type == OrderType::PLAN) {
          $this->planService->activatePlan($order);
        } else if ($order->type == OrderType::ADDITIONAL_USER) {
          $this->planService->addUsersToSubscription($order);
        } else if ($order->type == OrderType::RENEWAL) {
          $this->planService->renewPlan($order);
        } else if ($order->type == OrderType::UPGRADE) {
          $this->planService->upgradePlan($order);
        }

        return redirect()->route('customer.dashboard')->with('success', 'Payment successful! Plan activated.');
      } else {
        $order->status = OrderStatus::FAILED;
        $order->payment_response = json_encode($payment);
        $order->save();
        return redirect()->route('customer.dashboard')->with('error', 'Payment failed or not captured.');
      }
    } catch (Exception $e) {
      return redirect()->route('customer.dashboard')->with('error', 'Error verifying payment: ' . $e->getMessage());
    }
  }


}
