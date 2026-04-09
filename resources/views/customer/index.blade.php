@php
  use App\Enums\OfflineRequestStatus;
  use App\Enums\OrderStatus;
  use App\Enums\DomainRequestStatus;use Carbon\Carbon;
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Customer Dashboard')


<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite('resources/assets/vendor/libs/jquery/jquery.js')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/select2/select2.js'
  ])
@endsection
@section('content')
  <section class="section-py bg-transparent first-section-pt">
    <div class="container-fluid">

      <!-- Hero Section -->
      <div class="admin-hero hitech-card border-0 position-relative overflow-hidden mb-4 animate__animated animate__fadeInDown">
        <div class="d-flex align-items-center position-relative z-1 p-4">
          <div class="avatar avatar-xl me-4 border-2 border-white rounded-circle">
             <img src="{{ Auth::user()->profile_photo_url ?? asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle">
          </div>
          <div>
            <h2 class="text-white mb-1 fw-bold">Welcome, {{ Auth::user()->name }}! 👋</h2>
            <p class="text-white opacity-75 mb-0 text-large">Manage your subscription, domains, and billing all in one place.</p>
          </div>
        </div>
         <div class="position-absolute top-0 end-0 h-100 w-50" 
             style="background: linear-gradient(90deg, transparent, rgba(113, 221, 55, 0.1)); clip-path: polygon(20% 0%, 100% 0, 100% 100%, 0% 100%);">
        </div>
      </div>

      <!-- Tabs Navigation -->
      <ul class="nav nav-pills nav-pills-hitech mb-4 animate__animated animate__fadeInUp" id="customerDashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard"
                  type="button" role="tab" aria-controls="dashboard" aria-selected="true">
            <i class="bx bx-home-alt me-1"></i> Dashboard
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="order-history-tab" data-bs-toggle="tab" data-bs-target="#orderHistory"
                  type="button" role="tab" aria-controls="orderHistory" aria-selected="false">
            <i class="bx bx-history me-1"></i> Order History
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="offline-request-tab" data-bs-toggle="tab" data-bs-target="#offlineRequest"
                  type="button" role="tab" aria-controls="offlineRequest" aria-selected="false">
             <i class="bx bx-money me-1"></i> Offline Requests
            @if($offlineRequests->where('status', OfflineRequestStatus::PENDING)->count() > 0)
              <span class="ms-2 badge rounded-pill bg-danger">{{ $offlineRequests->where('status',OfflineRequestStatus::PENDING)->count() }}</span>
            @endif
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="domain-requests-tab" data-bs-toggle="tab" data-bs-target="#domainRequests"
                  type="button" role="tab" aria-controls="domainRequests" aria-selected="false">
            <i class="bx bx-globe me-1"></i> Domain Requests
            @if($domainRequests->where('status', DomainRequestStatus::PENDING)->count() > 0)
              <span class="ms-2 badge rounded-pill bg-danger">{{ $domainRequests->where('status',DomainRequestStatus::PENDING)->count() }}</span>
            @endif
          </button>
        </li>
      </ul>

      <!-- Tabs Content -->
      <div class="tab-content bg-transparent p-0 border-0" id="customerDashboardTabContent">
        <!-- Dashboard Tab -->
        <div class="tab-pane fade show active animate__animated animate__fadeIn" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
          
          @if($domainRequest && $domainRequest->status == DomainRequestStatus::APPROVED)
            <div class="alert alert-success d-flex align-items-center mb-4 border-0 shadow-sm" style="background: rgba(113, 221, 55, 0.1); border-left: 4px solid #71dd37 !important;">
                 <i class="bx bx-check-circle bx-sm me-3 text-success"></i>
                 <div class="d-flex flex-column">
                      <h6 class="alert-heading mb-1 text-success">Application Live</h6>
                      <span class="text-white opacity-75">You can access your application using the domain: <a target="_blank" href="{{ 'https://'.$domainRequest->name.'.'.config('tenancy.central_domains')[0] }}" class="fw-bold text-success text-decoration-underline">{{ 'https://'.$domainRequest->name.'.'.config('tenancy.central_domains')[0] }}</a></span>
                 </div>
            </div>
          @endif

          <!-- Active Plan Section -->
          @if ($activePlan)
            <div class="hitech-card mb-4">
              <div class="hitech-card-header">
                  <div>
                    <h5 class="mb-0 text-white">Active Plan</h5>
                    <small class="text-muted">Subscription Details</small>
                  </div>
                  <button class="btn btn-primary btn-hitech-glow" data-bs-toggle="modal" data-bs-target="#addUserModal"
                          data-per-user-price="{{ $activePlan->per_user_price }}">
                    <i class="bx bx-user-plus me-1"></i> @lang('Add More Users')
                  </button>
              </div>
              <div class="card-body">
                <div class="row">
                  <!-- Plan Details -->
                  <div class="col-md-6 mt-3">
                    <ul class="list-group list-group-flush bg-transparent">
                      <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-bottom border-dark px-0 py-3">
                        <span class="text-white opacity-75"><i class="bx bx-package me-2 text-primary"></i> <strong>@lang('Plan Name')</strong></span>
                        <span class="text-white fw-bold">{{ $activePlan->name }}</span>
                      </li>
                      <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-bottom border-dark px-0 py-3">
                        <span class="text-white opacity-75"><i class="bx bx-dollar me-2 text-success"></i> <strong>@lang('Base Price')</strong></span>
                        <span class="text-success fw-bold">{{$settings->currency_symbol}}{{ $activePlan->base_price }}</span>
                      </li>
                      <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-bottom border-dark px-0 py-3">
                        <span class="text-white opacity-75"><i class="bx bx-user me-2 text-info"></i> <strong>@lang('Per User Price')</strong></span>
                        <span class="text-white">{{$settings->currency_symbol}}{{ $activePlan->per_user_price }}</span>
                      </li>
                      <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-bottom border-dark px-0 py-3">
                        <span class="text-white opacity-75"><i class="bx bx-group me-2 text-warning"></i> <strong>@lang('Included / Added Users')</strong></span>
                        <span class="text-white">{{ $activePlan->included_users }} / <span class="text-warning">{{ $subscription->additional_users }}</span></span>
                      </li>
                       <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-bottom border-dark px-0 py-3">
                        <span class="text-white opacity-75"><i class="bx bx-time me-2 text-danger"></i> <strong>@lang('Expiry Date')</strong></span>
                        <span class="text-danger fw-bold">{{ Carbon::parse(auth()->user()->plan_expired_date)->format('D, d M Y h:i a') }}</span>
                      </li>
                    </ul>
                  </div>

                  <!-- Actions -->
                  <div class="col-md-6 d-flex flex-column justify-content-center align-items-center p-4">
                     <div class="position-relative d-flex justify-content-center align-items-center mb-4" style="width: 150px; height: 150px; background: rgba(0, 207, 232, 0.1); border-radius: 50%; border: 2px solid rgba(0, 207, 232, 0.5);">
                         <div class="text-center">
                             <h2 class="text-primary mb-0 fw-bold">{{ round(now()->diffInDays(auth()->user()->plan_expired_date)) }}</h2>
                             <small class="text-muted text-uppercase">Days Left</small>
                         </div>
                     </div>
                    <a href="#" class="btn btn-primary btn-hitech-glow w-50 mb-3" data-bs-toggle="modal"
                       data-bs-target="#renewModal">@lang('Renew Plan')</a>
                  </div>
                </div>
              </div>
            </div>
          @else
            <div class="hitech-card mb-4 text-center p-5">
              <div class="avatar avatar-xl bg-label-warning rounded-circle mx-auto mb-4">
                 <i class="bx bx-error bx-lg"></i>
              </div>
              <h4 class="text-white fw-bold mb-3">@lang('No Active Plan')</h4>
              <p class="text-muted mb-4">You are currently not subscribed to any plan. Choose a plan below to get started.</p>
              <i class="bx bx-down-arrow-alt bx-fade-down bx-md text-primary"></i>
            </div>
          @endif

          <!-- Pending Alerts -->
          @if($offlineRequests->where('status', OfflineRequestStatus::PENDING)->count() > 0)
            <div class="alert alert-warning mb-4 border-0 shadow-sm" role="alert" style="background: rgba(255, 171, 0, 0.1); border-left: 4px solid #ffab00 !important;">
               <div class="d-flex">
                   <i class="bx bx-time-five me-3 bx-sm text-warning"></i>
                   <div>
                       <h6 class="alert-heading text-warning mb-1">Offline Request Pending</h6>
                       <p class="mb-0 text-white opacity-75">Your offline payment request is being reviewed by the admin.</p>
                   </div>
               </div>
            </div>
          @endif

          <!-- Domain Request Section -->
          @if($activePlan)
            @if(!$domainRequest)
              <div class="hitech-card p-4 mb-4 border border-warning" style="border-style: dashed !important;">
                 <div class="d-flex align-items-center mb-3">
                     <i class="bx bx-globe bx-md text-warning me-3"></i>
                     <h5 class="text-white mb-0">Domain Not Configured</h5>
                 </div>
                 <p class="text-muted mb-3">Please request a subdomain to access your application.</p>
                 <form action="{{ route('customer.requestDomain') }}" method="POST" class="d-flex gap-2">
                  @csrf
                  <input type="text" class="form-control form-control-hitech" placeholder="Sub Domain Name" id="domain" name="domain" required>
                  <button type="submit" class="btn btn-warning text-nowrap">Request Domain</button>
                </form>
              </div>
            @endif
          @endif
          <!-- /Domain Request Section -->

          <!-- Available Plans Section -->
          <div class="hitech-card">
            <div class="hitech-card-header">
               <h5 class="mb-0 text-white">Available Plans</h5>
            </div>
            <div class="card-body">
              <div class="row">
                @foreach ($availablePlans as $plan)
                  <div class="col-md-4">
                    <div class="card h-100 position-relative transition-hover
            @if($activePlan && $activePlan->id == $plan->id) border-primary bg-transparent
            @else bg-transparent border border-dark
            @endif" style="backdrop-filter: blur(10px);">
                      <!-- Badge Section -->
                      @if($activePlan && $activePlan->id == $plan->id)
                        <span class="badge bg-primary position-absolute top-0 end-0 m-3 shadow-sm">Current Plan</span>
                      @elseif($activePlan && $plan->base_price > $activePlan->base_price)
                        <span class="badge bg-success position-absolute top-0 end-0 m-3 shadow-sm">Upgrade</span>
                      @endif

                      <div class="card-body p-4 d-flex flex-column">
                        <div class="text-center mb-4">
                            <h5 class="card-title text-white fw-bold mb-2">{{ $plan->name }}</h5>
                            <p class="text-muted small">{{ $plan->description }}</p>
                             <h2 class="text-primary mb-0 display-5 fw-bold">
                                {{$settings->currency_symbol}}{{ $plan->base_price }}
                             </h2>
                             <span class="text-muted fs-tiny text-uppercase ls-1">
                               / {{ $plan->duration }} {{ ucfirst($plan->duration_type->value) }}s
                             </span>
                        </div>
                   
                        <ul class="list-group list-group-flush bg-transparent mb-4 flex-grow-1">
                          <li class="d-flex justify-content-between align-items-center py-2 border-bottom border-dark px-0">
                            <span class="text-white opacity-75">Included Users</span>
                            <span class="fw-bold text-white">{{ $plan->included_users }}</span>
                          </li>
                          <li class="d-flex justify-content-between align-items-center py-2 border-bottom border-dark px-0">
                            <span class="text-white opacity-75">Extra User Cost</span>
                            <span class="fw-bold text-white">{{$settings->currency_symbol}}{{ $plan->per_user_price }}</span>
                          </li>
                          <!-- Available Modules Section -->
                          @foreach(ModuleConstants::All_MODULES as $module)
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom border-dark px-0">
                              <span class="text-white opacity-75">{{ $module }}</span>
                              @if(collect($plan->modules)->contains($module))
                                <i class="bx bx-check-circle text-success font-medium-3"></i>
                              @else
                                <i class="bx bx-x-circle text-secondary font-medium-3"></i>
                              @endif
                            </li>
                          @endforeach
                        </ul>

                        <!-- Buttons -->
                        <div class="mt-auto">
                            @if($activePlan && $activePlan->id == $plan->id)
                              <button class="btn btn-label-secondary w-100" disabled>Subscribed</button>
                            @elseif($activePlan && $plan->base_price > $activePlan->base_price)
                              <button class="btn btn-primary btn-hitech-glow w-100"
                                      data-bs-toggle="modal"
                                      data-bs-target="#upgradeModal"
                                      data-plan-id="{{ $plan->id }}"
                                      data-plan-name="{{ $plan->name }}"
                                      data-plan-price="{{ $plan->base_price }}"
                                      data-per-user-price="{{ $plan->per_user_price }}"
                                      data-difference="{{ $plan->base_price - $activePlan->base_price }}">
                                Upgrade Now
                              </button>
                            @elseif($activePlan && $plan->base_price <= $activePlan->base_price)
                              <button class="btn btn-outline-secondary w-100" disabled>Plan Unavailable</button>
                            @else
                              <button class="btn btn-success btn-hitech-glow w-100"
                                      data-bs-toggle="modal"
                                      data-bs-target="#paymentModal"
                                      data-plan-id="{{ $plan->id }}"
                                      data-plan-name="{{ $plan->name }}"
                                      data-plan-price="{{ $plan->base_price }}"
                                      data-per-user-price="{{ $plan->per_user_price }}"
                                      data-plan-users="{{ $plan->included_users }}">
                                Subscribe Now
                              </button>
                            @endif
                        </div>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
          <!-- /Available Plans Section -->

        </div>

        <!-- Order History Tab -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="orderHistory" role="tabpanel" aria-labelledby="order-history-tab">
           <div class="hitech-card">
               <div class="hitech-card-header">
                   <h5 class="mb-0 text-white">Transaction History</h5>
               </div>
               <div class="card-datatable table-responsive">
                  @if ($orders->isNotEmpty())
                    <table class="table order-history-table border-top">
                      <thead>
                      <tr class="fw-bold">
                        <th>Order ID</th>
                        <th>Type</th>
                        <th>Plan & Details</th>
                        <th>Add. Users</th>
                        <th>Amount</th>
                        <th>Gateway</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                      </tr>
                      </thead>
                      <tbody>
                      @foreach ($orders as $order)
                        <tr>
                          <td><code class="text-primary">#{{ $order->id }}</code></td>
                          <td><span class="text-white">{{ $order->type }}</span></td>
                          <td>
                            <div class="d-flex flex-column">
                              <span class="text-white fw-bold">{{ $order->plan->name }}</span>
                              <small class="text-muted">{{ $order->plan->duration }} {{ $order->plan->duration_type->value }} | {{ $order->plan->included_users }} Users</small>
                            </div>
                          </td>
                          <td><span class="text-white">{{$order->additional_users}}</span></td>
                          <td><span class="text-success fw-bold">{{$settings->currency_symbol}}{{ $order->amount }}</span></td>
                          <td><span class="badge bg-label-secondary">{{$order->payment_gateway}}</span></td>
                          <td>
                            @if($order->status == OrderStatus::PENDING)
                              <span class="badge bg-label-warning">Pending</span>
                            @elseif($order->status == OrderStatus::COMPLETED)
                              <span class="badge bg-label-success">Completed</span>
                            @elseif($order->status == OrderStatus::CANCELLED)
                              <span class="badge bg-label-danger">Cancelled</span>
                            @else
                              <span class="badge bg-label-danger">{{$order->status}}</span>
                            @endif
                          </td>
                          <td><span class="text-muted">{{ $order->created_at->format('Y-m-d') }}</span></td>
                          <td>
                            @if($order->status == OrderStatus::COMPLETED)
                              <button class="btn btn-sm btn-icon btn-label-primary view-order" data-order-id="{{ $order->id }}"
                                      data-bs-toggle="modal" data-bs-target="#viewOrderModal">
                                <i class="bx bx-show"></i>
                              </button>
                            @else
                              <span class="text-muted">-</span>
                            @endif
                          </td>
                        </tr>
                      @endforeach
                      </tbody>
                    </table>
                  @else
                    <div class="text-center p-5">
                       <i class="bx bx-receipt bx-lg text-muted mb-3 opacity-25"></i>
                       <p class="text-muted">No order history found.</p>
                    </div>
                  @endif
               </div>
           </div>
        </div>

        <!-- Offline Requests Tab -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="offlineRequest" role="tabpanel" aria-labelledby="offline-request-tab">
           <div class="hitech-card">
               <div class="hitech-card-header">
                   <h5 class="mb-0 text-white">Offline Payment Requests</h5>
               </div>
               <div class="card-datatable table-responsive">
                  @if ($offlineRequests->isNotEmpty())
                    <table class="table offline-request-table border-top">
                      <thead>
                      <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Plan</th>
                        <th>Base Price</th>
                        <th>Add. Users</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                      </tr>
                      </thead>
                      <tbody>
                      @foreach ($offlineRequests as $request)
                        <tr>
                          <td><code class="text-primary">#{{ $request->id }}</code></td>
                          <td>{{ ucfirst($request->type->value) }}</td>
                          <td>
                              <span class="text-white fw-bold d-block">{{ $request->plan->name }}</span>
                              <small class="text-muted">{{ $request->plan->included_users }} Users</small>
                          </td>
                          <td>{{$settings->currency_symbol. $request->plan->base_price }}</td>
                          <td>{{ $request->additional_users }}</td>
                          <td><span class="text-success fw-bold">{{$settings->currency_symbol. $request->total_amount }}</span></td>
                          <td>
                            @if($request->status == OfflineRequestStatus::PENDING)
                              <span class="badge bg-label-warning">Pending</span>
                            @elseif($request->status == OfflineRequestStatus::APPROVED)
                              <span class="badge bg-label-success">Approved</span>
                            @elseif($request->status == OfflineRequestStatus::REJECTED)
                              <span class="badge bg-label-danger">Rejected</span>
                            @elseif($request->status == OfflineRequestStatus::CANCELLED)
                              <span class="badge bg-label-danger">Cancelled</span>
                            @endif
                          </td>
                          <td>{{ $request->created_at->format('Y-m-d') }}</td>
                          <td>
                            @if ($request->status == OfflineRequestStatus::PENDING)
                              <a class="btn btn-icon btn-label-danger btn-sm cancel-request" onclick="return confirm('Are you sure?')"
                                 href="{{ route('offlinePayment.cancelOfflineRequest', $request->id) }}">
                                 <i class="bx bx-x"></i>
                              </a>
                            @else
                                -
                            @endif
                          </td>
                        </tr>
                      @endforeach
                      </tbody>
                    </table>
                  @else
                    <div class="text-center p-5">
                       <i class="bx bx-paper-plane bx-lg text-muted mb-3 opacity-25"></i>
                       <p class="text-muted">No offline requests found.</p>
                    </div>
                  @endif
               </div>
           </div>
        </div>

        <!-- Domain Requests Tab -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="domainRequests" role="tabpanel" aria-labelledby="domain-requests-tab">
           <div class="hitech-card">
               <div class="hitech-card-header">
                   <h5 class="mb-0 text-white">Domain Change Requests</h5>
               </div>
               <div class="card-datatable table-responsive">
                  @if ($domainRequests->isNotEmpty())
                    <table class="table domain-request-table border-top">
                      <thead>
                      <tr>
                        <th>ID</th>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                      </tr>
                      </thead>
                      <tbody>
                      @foreach ($domainRequests as $request)
                        <tr>
                          <td><code class="text-primary">#{{ $request->id }}</code></td>
                          <td><span class="text-white fw-bold">{{ $request->name }}</span></td>
                          <td>
                            @if($request->status == DomainRequestStatus::PENDING)
                              <span class="badge bg-label-warning">Pending</span>
                            @elseif($request->status == DomainRequestStatus::APPROVED)
                              <span class="badge bg-label-success">Approved</span>
                            @elseif($request->status == DomainRequestStatus::REJECTED)
                              <span class="badge bg-label-danger">Rejected</span>
                            @elseif($request->status == DomainRequestStatus::CANCELLED)
                              <span class="badge bg-label-danger">Cancelled</span>
                            @endif
                          </td>
                          <td>{{ $request->created_at->format('Y-m-d') }}</td>
                          <td>
                            @if ($request->status == DomainRequestStatus::PENDING)
                              <a class="btn btn-icon btn-label-danger btn-sm cancel-request" onclick="return confirm('Are you sure?')"
                                 href="{{ route('customer.cancelDomainRequest', $request->id) }}">
                                 <i class="bx bx-x"></i>
                              </a>
                            @else
                                -
                            @endif
                          </td>
                        </tr>
                      @endforeach
                      </tbody>
                    </table>
                  @else
                    <div class="text-center p-5">
                       <i class="bx bx-globe bx-lg text-muted mb-3 opacity-25"></i>
                       <p class="text-muted">No domain requests found.</p>
                    </div>
                  @endif
               </div>
           </div>
        </div>

      </div>
    </div>
  </section>

  @if($activePlan)
    @include('_partials._customerModals.add_more_users_model')
    @include('_partials._customerModals.plan_upgrade_model')
    @include('_partials._customerModals.renew_plan_model')
  @else
    @include('_partials._customerModals.initial_payment_model')
  @endif

  <!-- View Order Modal -->
  <div class="modal fade" id="viewOrderModal" tabindex="-1" aria-labelledby="viewOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content hitech-card border-0">
        <div class="modal-header border-bottom border-dark">
          <h5 class="modal-title text-white" id="viewOrderModalLabel">Order Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="orderDetailsContent">
            <div class="text-center">
              <div class="spinner-border text-primary" role="status">
                 <span class="visually-hidden">Loading...</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /View Order Modal -->

@endsection

@section('page-script')
  @if($settings->razorpay_enabled)
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  @endif
  @if($settings->paypal_enabled)
    <script src="https://www.paypal.com/sdk/js?client-id={{ $settings->paypal_client_id }}"></script>
  @endif

  <!-- Initial payment model script -->
  @if(!$activePlan)
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const usersInput = document.getElementById('paymentModal-users');
        const decreaseButton = document.getElementById('paymentModal-decreaseUsers');
        const increaseButton = document.getElementById('paymentModal-increaseUsers');
        const totalPriceElement = document.getElementById('paymentModal-totalPrice');
        const planPriceElement = document.getElementById('paymentModal-modalPlanPrice');
        const perUserPriceElement = document.getElementById('paymentModal-modalPlanPerUserPrice');
        const additionalUsersInput = document.getElementById('paymentModal-additionalUsers');
        const totalCostDisplay = document.getElementById('paymentModal-totalCost');

        if (additionalUsersInput) {
          additionalUsersInput.addEventListener('input', () => {
            const additionalUsers = parseInt(additionalUsersInput.value) || 0;
            const perUserPrice = {{ $activePlan ? $activePlan->per_user_price : 0 }};
            const totalCost = additionalUsers * perUserPrice;

            totalCostDisplay.textContent = totalCost.toFixed(2);
          });
        }

        const calculateTotalPrice = () => {
          const planPrice = parseFloat(planPriceElement.textContent);
          const perUserPrice = parseFloat(perUserPriceElement.textContent);
          const users = parseInt(usersInput.value);
          const totalPrice = planPrice + (perUserPrice * users);
          totalPriceElement.textContent = totalPrice.toFixed(2);
        };

        if(decreaseButton) {
            decreaseButton.addEventListener('click', () => {
              let currentValue = parseInt(usersInput.value);
              if (currentValue > 0) {
                usersInput.value = currentValue - 1;
                calculateTotalPrice();
              }
            });
        }

        if(increaseButton) {
            increaseButton.addEventListener('click', () => {
              let currentValue = parseInt(usersInput.value);
              usersInput.value = currentValue + 1;
              calculateTotalPrice();
            });
        }

        if (usersInput) {
          usersInput.addEventListener('input', () => {
            calculateTotalPrice();
          });
        }

        // Populate Payment Modal with Plan Details
        const paymentModal = document.getElementById('paymentModal');
        if (paymentModal) {
          paymentModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const planId = button.getAttribute('data-plan-id');
            const planName = button.getAttribute('data-plan-name');
            const planPrice = button.getAttribute('data-plan-price');
            const planUsers = button.getAttribute('data-plan-users');
            const perUserPrice = button.getAttribute('data-per-user-price');

            document.getElementById('paymentModal-planId').value = planId;
            document.getElementById('paymentModal-modalPlanName').textContent = planName;
            document.getElementById('paymentModal-modalPlanPrice').textContent = planPrice;
            document.getElementById('paymentModal-modalPlanPerUserPrice').textContent = perUserPrice;
            document.getElementById('paymentModal-modalPlanUsers').textContent = planUsers;

            calculateTotalPrice();

            paymentModal.addEventListener('hidden.bs.modal', function () {
              usersInput.value = 0;
              totalCostDisplay.textContent = '0.00';
            });
          });
        }
      });
    </script>
  @endif
@endsection
