@php use App\Enums\OrderStatus;use App\Enums\UserAccountStatus; @endphp
@php use Carbon\Carbon; @endphp
@php use App\Enums\SubscriptionStatus; @endphp
@extends('layouts/layoutMaster')

@section('title', 'User Details')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/select2/select2.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/moment/moment.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/select2/select2.js'
  ])
@endsection

@section('page-script')
  <script>

    function showDeleteAlert() {
      Swal.fire({
        title: 'Are you sure?',
        text: 'You are about to delete this user account!, This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        customClass: {
          confirmButton: 'btn btn-danger',
          cancelButton: 'btn btn-primary'
        },
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          deleteUser();
        }
      });
    }

    function showSuspendUserAlert() {
      Swal.fire({
        title: 'Are you sure?',
        text: 'You are about to suspend this user account!',
        icon: 'warning',
        showCancelButton: true,
        customClass: {
          confirmButton: 'btn btn-danger',
          cancelButton: 'btn btn-primary'
        },
        confirmButtonText: 'Yes, suspend it!'
      }).then((result) => {
        if (result.isConfirmed) {
          suspendUser();
        }
      });
    }

    function showInactiveUserAlert() {
      Swal.fire({
        title: 'Are you sure?',
        text: 'You are about to deactivate this user account!',
        icon: 'warning',
        showCancelButton: true,
        customClass: {
          confirmButton: 'btn btn-success',
          cancelButton: 'btn btn-primary'
        },
        confirmButtonText: 'Yes, deactivate it!'
      }).then((result) => {
        if (result.isConfirmed) {
          activateInactivateUser();
        }
      });
    }

    function showActivateUserAlert() {
      Swal.fire({
        title: 'Are you sure?',
        text: 'You are about to activate this user account!',
        icon: 'warning',
        showCancelButton: true,
        customClass: {
          confirmButton: 'btn btn-success',
          cancelButton: 'btn btn-primary'
        },
        confirmButtonText: 'Yes, activate it!'
      }).then((result) => {
        if (result.isConfirmed) {
          activateInactivateUser(true);
        }
      });
    }

    function activateInactivateUser(isInactivate = false) {
      $.ajax({
        url: "{{ route('account.activeInactiveUserAjax',['id'=>$user['id']]) }}",
        type: 'GET',
        success: function (response) {
          if (isInactivate) {
            Swal.fire(
              {
                title: 'Activated!',
                text: 'User account has been activated.',
                icon: 'success',
                customClass: {
                  confirmButton: 'btn btn-success'
                }
              }
            ).then((result) => {
              location.reload();
            });
          } else {
            Swal.fire(
              {
                title: 'Deactivated!',
                text: 'User account has been deactivated.',
                icon: 'success',
                customClass: {
                  confirmButton: 'btn btn-success'
                }
              }
            ).then((result) => {
              location.reload();
            });
          }

        },
        error: function (error) {
          console.log(error);
          Swal.fire(
            {
              title: 'Error!',
              text: 'An error occurred while deactivating the user account.',
              icon: 'error',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            }
          );
        }
      });
    }

    function deleteUser() {
      $.ajax({
        url: "{{ route('account.deleteUserAjax',['id'=>$user['id']]) }}",
        type: 'GET',
        success: function (response) {
          Swal.fire(
            {
              title: 'Deleted!',
              text: 'User account has been deleted.',
              icon: 'success',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            }
          ).then((result) => {
            location.href = "{{ route('account.index') }}";
          });
        },
        error: function (error) {
          console.log(error);
          Swal.fire(
            {
              title: 'Error!',
              text: 'An error occurred while deleting the user account.',
              icon: 'error',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            }
          );
        }
      });
    }

    function suspendUser() {
      $.ajax({
        url: "{{ route('account.suspendUserAjax',['id'=>$user['id']]) }}",
        type: 'GET',
        success: function (response) {
          Swal.fire(
            {
              title: 'Suspended!',
              text: 'User account has been suspended.',
              icon: 'success',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            }
          ).then((result) => {
            location.reload();
          });
        },
        error: function (error) {
          console.log(error);
          Swal.fire(
            {
              title: 'Error!',
              text: 'An error occurred while suspending the user account.',
              icon: 'error',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            }
          );
        }
      });
    }
  </script>
@endsection

@section('content')
  <div class="row mt-3">
    <div class="col">
      <div class="float-start">
        <h4 class=""><a href="{{route('account.index')}}" class="btn btn-link" data-bs-toggle="tooltip"
                        data-popup="tooltip-custom" data-bs-placement="bottom" title="Back"><i
              class="bx bx-arrow-back"></i>
          </a>User Details</h4>
      </div>
      <div class="float-end">
        @if($user['status'] == UserAccountStatus::ACTIVE)
          <a href="#" onclick="showInactiveUserAlert()" class="btn btn-label-warning"><i
              class='bx bx-time me-1'></i>Deactivate</a>
        @elseif($user['status'] == UserAccountStatus::INACTIVE)
          <a href="#" onclick="showActivateUserAlert()" class="btn btn-label-success"><i
              class='bx bx-check-circle me-1'></i>Activate</a>
        @endif
        {{--
                @if($user['status'] != \App\Enums\UserStatus::BANNED)
                  <a href="#" onclick="showSuspendUserAlert()" class="btn btn-label-danger"><i
                      class='bx bx-block me-1'></i>Suspend</a>
        @endif--}}

        <a href="#" onclick="showDeleteAlert()" class="btn btn-label-danger"><i
            class='bx bx-trash me-1'></i>Delete</a>


      </div>
    </div>
  </div>
  <div class="row">
    <!-- User Details Sidebar -->
    <div class="col-xl-4 col-lg-5 order-1 order-md-0">
      <div class="card mb-4 h-100">
        <div class="card-body pt-4">
          <div class="user-avatar-section text-center">
            <img class="img-fluid rounded-circle mb-4"
                 src="{{ $user['profile_picture'] ?? ('https://avatar.iran.liara.run/username?username='.$user['first_name'] .'+'.$user['last_name']) }}"
                 height="120"
                 width="120" alt="User avatar"/>
            <div class="user-info">
              <h5>{{ $user['first_name'] }} {{ $user['last_name'] }}</h5>
              @if($user['status'] == UserAccountStatus::ACTIVE)
                <span class="badge bg-label-success"><i class='bx bx-check-circle me-1'></i>{{ $user['status'] }}</span>
              @elseif($user['status'] == UserAccountStatus::INACTIVE)
                <span class="badge bg-label-warning"><i class='bx bx-time me-1'></i>{{ $user['status'] }}</span>
              @else
                <span class="badge bg-label-danger"><i class='bx bx-block me-1'></i>{{ $user['status'] }}</span>
              @endif
            </div>
          </div>
          <h5 class="pb-2 border-bottom mb-4">Details</h5>
          <div class="info-container">
            <ul class="list-unstyled mb-4">
              <li class="mb-2">
                <i class='bx bx-envelope me-2'></i><span class="h6">Email:</span> {{ $user['email'] }}
              </li>
              <li class="mb-2">
                <i class='bx bx-phone me-2'></i><span class="h6">Phone Number:</span> {{ $user['phone'] }}
              </li>
              <li class="mb-2">
                <i class='bx bx-calendar me-2'></i><span
                  class="h6">Date of Birth:</span> {{ Carbon::parse($user['dob'])->format('M d, Y') }}
              </li>
              <li class="mb-2">
                <i class='bx bx-user me-2'></i><span class="h6">Gender:</span> {{ ucfirst($user['gender']) }}
              </li>
              <li class="mb-2">
                <i class='bx bx-globe me-2'></i><span class="h6">Language:</span> {{ strtoupper($user['language']) }}
              </li>
              <li class="mb-2">
                <i class='bx bx-calendar-check me-2'></i><span
                  class="h6">Account Created:</span> {{ Carbon::parse($user['created_at'])->format(Constants::DateTimeHumanFormatShort) }}
              </li>
              <li class="mb-2">
                <i class='bx bx-check-shield me-2'></i><span class="h6">Email Verified:</span>
                {{ $user['email_verified_at'] ? Carbon::parse($user['email_verified_at'])->format(Constants::DateTimeHumanFormatShort) : 'Not Verified' }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- User Activity Timeline -->
    <div class="col-xl-8 col-lg-7 order-0 order-md-1">
      <div class="card mb-4 h-100 d-flex flex-column">
        <h5 class="card-header">User Activity Timeline</h5>
        <div class="card-body overflow-auto flex-grow-1" style="max-height: 500px;">
          <ul class="timeline mb-0">
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-primary"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-2">
                  <h6 class="mb-0"><i class='bx bx-calendar-plus me-1'></i>Created Account</h6>
                  <small class="text-muted">{{ Carbon::parse($user['created_at'])->diffForHumans() }}</small>
                </div>
                <p class="mb-0">User registered on the platform</p>
              </div>
            </li>
            <li class="timeline-item timeline-item-transparent">
              <span class="timeline-point timeline-point-success"></span>
              <div class="timeline-event">
                <div class="timeline-header mb-2">
                  <h6 class="mb-0"><i class='bx bx-check-shield me-1'></i>Verified Email</h6>
                  <small
                    class="text-muted">{{ $user['email_verified_at'] ? Carbon::parse($user['email_verified_at'])->diffForHumans() : 'Pending Verification' }}</small>
                </div>
                <p class="mb-0">User's email has been verified</p>
              </div>
            </li>
            <!-- Add more user activities if available -->
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-3">
    <!-- Subscription Details Card -->
    <div class="col-xl-4 col-lg-5 col-md-6 mb-4">
      <div class="card h-100 d-flex flex-column">
        <div class="card-header">
          <h5 class="mb-0">Subscription Details</h5>
        </div>
        <div class="card-body overflow-auto flex-grow-1" style="max-height: 500px;">
          @if($user->activeSubscription())
            @php $subscription = $user->activeSubscription(); @endphp
            <ul class="list-unstyled mb-4">
              <li class="mb-2"><i class='bx bx-cube me-2'></i><span
                  class="h6">Plan Name:</span> {{ $subscription->plan->name }}</li>
              <li class="mb-2"><i class='bx bx-calendar me-2'></i><span
                  class="h6">Start Date:</span> {{ Carbon::parse($subscription->start_date)->format('M d, Y') }}</li>
              <li class="mb-2"><i class='bx bx-calendar-check me-2'></i><span
                  class="h6">End Date:</span> {{ Carbon::parse($subscription->end_date)->format('M d, Y') }}</li>
              <li class="mb-2"><i class='bx bx-group me-2'></i><span
                  class="h6">Included Users:</span> {{ $subscription->users_count }}</li>
              <li class="mb-2"><i class='bx bx-user-plus me-2'></i><span
                  class="h6">Additional Users:</span> {{ $subscription->additional_users }}</li>
              <li class="mb-2"><i class='bx bx-dollar me-2'></i><span class="h6">Total Price:</span>
                ${{ number_format($subscription->total_price, 2) }}</li>
              <li class="mb-2"><i class='bx bx-check-circle me-2'></i><span class="h6">Status:</span>
                @if($subscription->status == SubscriptionStatus::ACTIVE)
                  <span class="badge bg-label-success">{{ $subscription->status->value }}</span>
                @else
                  <span class="badge bg-label-warning">{{ $subscription->status->value }}</span>
                @endif
              </li>
            </ul>
          @else
            <p class="text-muted">No active subscription found for this user.</p>
          @endif
        </div>
      </div>
    </div>

    <!-- Orders Card -->
    <div class="col-xl-8 col-lg-7 col-md-6 mb-4">
      <div class="card h-100 d-flex flex-column">
        <div class="card-header">
          <h5 class="mb-0">Orders</h5>
        </div>
        <div class="card-body overflow-auto flex-grow-1" style="max-height: 500px;">
          @if($orders->count() > 0)
            <div class="table-responsive">
              <table class="table table-bordered orders-table">
                <thead class="thead-light">
                <tr>
                  <th>#</th>
                  <th>Plan</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Amount</th>
                  <th>Paid At</th>
                </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                  <tr>
                    <td>{{ $order->id }}</td>
                    <td>{{ $order->plan->name ?? 'N/A' }}</td>
                    <td>{{ $order->type->value }}</td>
                    <td>
                      @if($order->status == OrderStatus::COMPLETED)
                        <span class="badge bg-label-success">{{ $order->status->value }}</span>
                      @elseif($order->status == OrderStatus::PENDING)
                        <span class="badge bg-label-warning">{{ $order->status->value }}</span>
                      @elseif($order->status == OrderStatus::FAILED)
                        <span class="badge bg-label-danger">{{ $order->status->value }}</span>
                      @else
                        <span class="badge bg-label-secondary">{{ $order->status->value }}</span>
                      @endif
                    </td>
                    <td>${{ number_format($order->total_amount, 2) }}</td>
                    <td>{{ $order->paid_at ? Carbon::parse($order->paid_at)->format('M d, Y') : 'Not Paid' }}</td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          @else
            <p class="text-muted">No orders found for this user.</p>
          @endif
        </div>
      </div>
    </div>

    <!-- Domains Card -->
    <div class="col-12 mb-4">
      <div class="card h-100 d-flex flex-column">
        <div class="card-header">
          <h5 class="mb-0">Domains</h5>
        </div>
        <div class="card-body overflow-auto flex-grow-1" style="max-height: 500px;">
          @if($domains->count() > 0)
            <div class="table-responsive">
              <table class="table table-bordered domains-table">
                <thead class="thead-light">
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Status</th>
                  <th>Created At</th>
                </tr>
                </thead>
                <tbody>
                @foreach($domains as $domain)
                  <tr>
                    <td>{{ $domain->id }}</td>
                    <td>{{ $domain->name }}</td>
                    <td>
                      @if($domain->status == 'approved')
                        <span class="badge bg-label-success">{{ ucfirst($domain->status->value) }}</span>
                      @else
                        <span class="badge bg-label-warning">{{ ucfirst($domain->status->value) }}</span>
                      @endif
                    </td>
                    <td>{{ Carbon::parse($domain->created_at)->format('M d, Y') }}</td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          @else
            <p class="text-muted">No domains found for this user.</p>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
