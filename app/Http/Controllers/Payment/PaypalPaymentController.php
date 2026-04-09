<?php

namespace App\Http\Controllers\Payment;

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
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalPaymentController extends Controller
{
  private ISubscriptionService $planService;

  function __construct(ISubscriptionService $planService)
  {
    $this->planService = $planService;
  }

  public function paypalPayment(Request $request)
  {

    try {
      $plan = Plan::findOrFail($request->planId);

      $usersCount = $request->users;

      $settings = SaSettings::first();

      $totalAmount = $this->planService->getPlanTotalAmount($plan, $usersCount);

      $localOrder = new Order();
      $localOrder->user_id = auth()->id();
      $localOrder->plan_id = $plan->id;
      $localOrder->amount = $plan->base_price;
      $localOrder->per_user_price = $plan->per_user_price;
      $localOrder->total_amount = $totalAmount;
      $localOrder->status = OrderStatus::PROCESSING;
      $localOrder->type = OrderType::PLAN;
      $localOrder->payment_gateway = 'paypal';
      $localOrder->payment_data = json_encode("paypal");
      $localOrder->save();

      $provider = new PayPalClient;

      $provider->setApiCredentials(config('paypal'));

      $provider->getAccessToken();

      $response = $provider->createOrder([
        "intent" => "CAPTURE",
        "application_context" => [
          'return_url' => route('paypal.success', ['orderId' => $localOrder->id]),
          'cancel_url' => route('paypal.cancel', ['orderId' => $localOrder->id])
        ],
        "purchase_units" => [
          0 => [
            "amount" => [
              "currency_code" => $settings->currency,
              "value" => $totalAmount
            ]
          ]
        ]
      ]);

      if (isset($response['id']) && $response['id'] != null) {
        foreach ($response['links'] as $links) {
          if ($links['rel'] == 'approve') {
            return redirect()->away($links['href']);
          }
        }
        return redirect()->back()->with('failed', 'Something went wrong.');
      } else {
        return redirect()->back()->with('failed', 'Something went wrong.');
      }
    } catch (Exception $e) {
      Log::error('Paypal payment error: ' . $e->getMessage());
      return redirect()->back()->with('failed', 'Something went wrong.');
    }
  }

  public function paypalPaymentForAddUser(Request $request)
  {

    try {
      $plan = Plan::find(auth()->user()->plan_id);

      $usersCount = $request->input('addUserModal-users');

      $settings = SaSettings::first();

      Log::info('PayPal Settings:', [
        'mode' => $settings->paypal_mode,
        'client_id' => $settings->paypal_client_id,
        'client_secret' => $settings->paypal_secret,
      ]);

      $totalAmount = $this->planService->getAddUserTotalAmount($usersCount);

      $localOrder = new Order();
      $localOrder->user_id = auth()->id();
      $localOrder->plan_id = $plan->id;
      $localOrder->amount = $plan->base_price;
      $localOrder->per_user_price = $plan->per_user_price;
      $localOrder->additional_users = $usersCount;
      $localOrder->total_amount = $totalAmount;
      $localOrder->status = OrderStatus::PROCESSING;
      $localOrder->type = OrderType::ADDITIONAL_USER;
      $localOrder->payment_gateway = 'paypal';
      $localOrder->payment_data = json_encode("paypal");
      $localOrder->save();


      $provider = new PayPalClient;

      $provider->setApiCredentials(config('paypal'));

      $provider->getAccessToken();

      $response = $provider->createOrder([
        "intent" => "CAPTURE",
        "application_context" => [
          'return_url' => route('paypal.success', ['orderId' => $localOrder->id]),
          'cancel_url' => route('paypal.cancel', ['orderId' => $localOrder->id])
        ],
        "purchase_units" => [
          0 => [
            "amount" => [
              "currency_code" => $settings->currency,
              "value" => $totalAmount
            ]
          ]
        ]
      ]);

      if (isset($response['id']) && $response['id'] != null) {
        foreach ($response['links'] as $links) {
          if ($links['rel'] == 'approve') {
            return redirect()->away($links['href']);
          }
        }
        return redirect()->back()->with('failed', 'Something went wrong.');
      } else {
        return redirect()->back()->with('failed', 'Something went wrong.');
      }
    } catch (Exception $e) {
      Log::error('Paypal payment errors: ' . $e->getMessage());
      return redirect()->back()->with('failed', 'Something went wrong.');
    }
  }

  public function paypalPaymentForRenewal(Request $request)
  {

    try {
      $plan = Plan::find(auth()->user()->plan_id);

      $subscription = $this->planService->getSubscription();

      $settings = SaSettings::first();

      $totalAmount = $this->planService->getRenewalAmount();

      $localOrder = new Order();
      $localOrder->user_id = auth()->id();
      $localOrder->plan_id = $plan->id;
      $localOrder->amount = $plan->base_price;
      $localOrder->per_user_price = $plan->per_user_price;
      $localOrder->additional_users = $subscription->additional_users;
      $localOrder->total_amount = $totalAmount;
      $localOrder->status = OrderStatus::PROCESSING;
      $localOrder->type = OrderType::RENEWAL;
      $localOrder->payment_gateway = 'paypal';
      $localOrder->payment_data = json_encode("paypal");
      $localOrder->save();

      $provider = new PayPalClient;

      $provider->setApiCredentials(config('paypal'));

      $provider->getAccessToken();

      $response = $provider->createOrder([
        "intent" => "CAPTURE",
        "application_context" => [
          'return_url' => route('paypal.success', ['orderId' => $localOrder->id]),
          'cancel_url' => route('paypal.cancel', ['orderId' => $localOrder->id])
        ],
        "purchase_units" => [
          0 => [
            "amount" => [
              "currency_code" => $settings->currency,
              "value" => $totalAmount
            ]
          ]
        ]
      ]);

      if (isset($response['id']) && $response['id'] != null) {
        foreach ($response['links'] as $links) {
          if ($links['rel'] == 'approve') {
            return redirect()->away($links['href']);
          }
        }
        return redirect()->back()->with('failed', 'Something went wrong.');
      } else {
        return redirect()->back()->with('failed', 'Something went wrong.');
      }
    } catch (Exception $e) {
      Log::error('Paypal payment error: ' . $e->getMessage());
      return redirect()->back()->with('failed', 'Something went wrong.');
    }
  }

  public function paypalPaymentForUpgrade(Request $request)
  {

    try {
      $newPlan = Plan::find($request->input('upgradeModal-planId'));

      $subscription = $this->planService->getSubscription();

      $settings = SaSettings::first();

      $totalAmount = $this->planService->getDifferencePriceForUpgrade($newPlan->id);

      $localOrder = new Order();
      $localOrder->user_id = auth()->id();
      $localOrder->plan_id = $newPlan->id;
      $localOrder->amount = $newPlan->base_price;
      $localOrder->per_user_price = $newPlan->per_user_price;
      $localOrder->additional_users = $subscription->additional_users;
      $localOrder->total_amount = $totalAmount;
      $localOrder->status = OrderStatus::PROCESSING;
      $localOrder->type = OrderType::UPGRADE;
      $localOrder->payment_gateway = 'paypal';
      $localOrder->payment_data = json_encode("paypal");
      $localOrder->save();

      $provider = new PayPalClient;

      $provider->setApiCredentials(config('paypal'));

      $provider->getAccessToken();

      $response = $provider->createOrder([
        "intent" => "CAPTURE",
        "application_context" => [
          'return_url' => route('paypal.success', ['orderId' => $localOrder->id]),
          'cancel_url' => route('paypal.cancel', ['orderId' => $localOrder->id])
        ],
        "purchase_units" => [
          0 => [
            "amount" => [
              "currency_code" => $settings->currency,
              "value" => $totalAmount
            ]
          ]
        ]
      ]);

      if (isset($response['id']) && $response['id'] != null) {
        foreach ($response['links'] as $links) {
          if ($links['rel'] == 'approve') {
            return redirect()->away($links['href']);
          }
        }
        return redirect()->back()->with('failed', 'Something went wrong.');
      } else {
        return redirect()->back()->with('failed', 'Something went wrong.');
      }
    } catch (Exception $e) {
      Log::error('Paypal payment error: ' . $e->getMessage());
      return redirect()->back()->with('failed', 'Something went wrong.');
    }
  }

  public function success(Request $request)
  {
    $order = Order::find($request->orderId);
    $order->status = OrderStatus::COMPLETED;
    $order->payment_response = json_encode($request->all());
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

    return redirect()->route('customer.dashboard')->with('success', 'Payment successful');
  }

  public function cancel(Request $request)
  {
    Log::info('Paypal cancel response: ' . json_encode($request->all()));

    $order = Order::find($request->orderId);
    $order->status = OrderStatus::FAILED;
    $order->payment_response = json_encode($request->all());
    $order->save();

    return redirect()->route('customer.dashboard')->with('error', 'Payment cancelled');
  }
}
