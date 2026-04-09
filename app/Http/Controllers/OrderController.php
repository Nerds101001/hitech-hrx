<?php

namespace App\Http\Controllers;

use Constants;
use Exception;
use Illuminate\Http\Request;
use App\Models\SuperAdmin\Order;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
  public function index()
  {
    return view('superAdmin.order.index');
  }

  public function indexAjax(Request $request)
  {
    try {

      $columns = [
        1 => 'id',
        2 => 'user',
        3 => 'plan',
        4 => 'type',
        5 => 'amount',
        6 => 'gateway',
        7 => 'status',
        8 => 'created_at',
      ];

      $query = Order::query();

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')];
      $dir = $request->input('order.0.dir');

      $totalData = $query->count();

      if (empty($request->input('search.value'))) {
        $orders = $query->select(
          'orders.*',
          'user.first_name',
          'user.last_name',
          'user.email',
          'plan.name as plan_name',
          'plan.included_users as plan_included_users',
          'plan.duration as plan_duration',
          'plan.duration_type as plan_duration_type',
        )
          ->leftJoin('users as user', 'orders.user_id', '=', 'user.id')
          ->leftJoin('plans as plan', 'orders.plan_id', '=', 'plan.id', 'orders.included_users', '=', 'plan.included_users', 'orders.duration', '=', 'plan.duration', 'orders.duration_type', '=', 'plan.duration_type')
          ->offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      } else {
        $search = $request->input('search.value');
        $orders = $query->select(
          'orders.*',
          'user.first_name',
          'user.last_name',
          'user.email',
          'plan.name as plan_name',
          'plan.included_users as plan_included_users',
          'plan.duration as plan_duration',
          'plan.duration_type as plan_duration_type',

        )
          ->leftJoin('users as user', 'orders.user_id', '=', 'user.id')
          ->leftJoin('plans as plan', 'orders.plan_id', '=', 'plan.id')
          ->where(function ($query) use ($search) {
            $query->where('orders.id', 'Like', "%{$search}%")
              ->orWhere('user.first_name', 'Like', "%{$search}%")
              ->orWhere('user.last_name', 'Like', "%{$search}%")
              ->orWhere('user.email', 'Like', "%{$search}%")
              ->orWhere('plan.name', 'Like', "%{$search}%")
              ->orWhere('orders.user_id', 'Like', "%{$search}%");
          })
          ->offset($start)
          ->limit($limit)
          ->orderBy($order, $dir)
          ->get();
      }

      $totalFiltered = $orders->count();

      $data = [];
      if (!empty($orders)) {
        foreach ($orders as $order) {
          $nestedData['id'] = $order->id;
          $nestedData['user_id'] = $order->user_id;
          $nestedData['plan_id'] = $order->plan_name;
          $nestedData['type'] = $order->type;
          $nestedData['amount'] = $order->amount;
          $nestedData['payment_gateway'] = $order->payment_gateway;
          $nestedData['status'] = $order->status;
          $nestedData['created_at'] = $order->created_at->format(Constants::DateTimeFormat);

          //user
          $nestedData['user_name'] = $order->first_name . ' ' . $order->last_name;
          $nestedData['user_initial'] = $order->first_name[0] . $order->last_name[0];
          $nestedData['user_email'] = $order->email;
          $nestedData['user_profile_image'] =
            $order->user->profile_picture != null ? asset(Constants::BaseFolderEmployeeProfileWithSlash . $order->user->profile_picture) : null;
          $nestedData['included_users'] = $order->plan_included_users;
          $nestedData['duration'] = $order->plan_duration;
          $nestedData['duration_type'] = $order->plan_duration_type;

          $data[] = $nestedData;
        }
      }

      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => $totalData,
        'recordsFiltered' => $totalFiltered,
        'data' => $data
      ]);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return back()->with('error', 'Something went wrong. Please try again.');
    }
  }
}
