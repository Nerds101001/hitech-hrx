<?php

namespace App\Http\Controllers;

use App\Enums\OfflineRequestStatus;
use App\Models\SuperAdmin\DomainRequest;
use App\Models\SuperAdmin\OfflineRequest;
use App\Models\SuperAdmin\Order;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{

  public function index()
  {

    if (!auth()->user()->hasRole('super_admin')) {
      return redirect()->route('customer.dashboard');
    }

    // Order History (Last 6 months)
    $orderHistory = Order::selectRaw('MONTH(created_at) as month, SUM(total_amount) as total')
      ->where('created_at', '>=', Carbon::now()->subMonths(6))
      ->groupBy('month')
      ->get();

    return view('dashboard.index', [
      'orderHistory' => $orderHistory,
      'totalOrders' => Order::count(),
      'completedOrders' => Order::where('status', 'completed')->count(),
      'pendingRequests' => OfflineRequest::where('status', OfflineRequestStatus::PENDING)->count(),
      'activeDomains' => DomainRequest::where('status', OfflineRequestStatus::APPROVED)->count(),
      'newCustomers' => User::whereMonth('created_at', now()->month)->count(),
      'offlineRequests' => OfflineRequest::latest()->take(5)->get(),
      'domainRequests' => DomainRequest::latest()->take(5)->get(),
      'recentCustomers' => User::where('is_customer', true)->latest()->take(5)->get(),
    ]);
  }

}
