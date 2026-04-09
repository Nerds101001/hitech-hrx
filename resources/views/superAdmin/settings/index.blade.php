@extends('layouts/layoutMaster')

@section('title', __('SA Settings'))

@section('page-script')
  <script>
    document.querySelectorAll('.gateway-toggle').forEach(toggle => {
      toggle.addEventListener('change', function () {
        const target = document.querySelector(this.dataset.target);
        if (this.checked) {
          target.classList.remove('d-none');
        } else {
          target.classList.add('d-none');
        }
        disableGateway(this.dataset.target.replace('#', ''));
      });
    });

    function disableGateway(gateway) {
      //Make get api call
      fetch(`settings/changePaymentGatewayStatusAjax/${gateway}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      })
        .then(response => response.json())
        .then(data => {
          console.log(data);
        })
        .catch((error) => {
          console.error('Error:', error);
        });
    }


  </script>
@endsection

@section('content')
  <div class="row g-6">

    <!-- Navigation -->
    <div class="col-12 col-lg-4">
      <div class="d-flex justify-content-between flex-column mb-4 mb-md-0">
        <h5 class="mb-4">@lang('Settings')</h5>
        <ul class="nav nav-align-left nav-pills flex-column" id="settingsMenu">
          <li class="nav-item mb-1">
            <a class="nav-link active" href="#saGeneralSettings" data-bs-toggle="pill">
              <i class="bx bx-cog bx-sm me-1_5"></i>
              <span class="align-middle">General</span>
            </a>
          </li>
          <li class="nav-item mb-1">
            <a class="nav-link" href="#paymentGatewaysSettings" data-bs-toggle="pill">
              <i class="bx bx-money bx-sm me-1_5"></i>
              <span class="align-middle">Payment Gateways</span>
            </a>
          </li>
        </ul>
      </div>
    </div>
    <!-- /Navigation -->

    <!-- Options -->
    <div class="col-12 col-lg-8 pt-6 pt-lg-0">
      <div class="tab-content p-0">
        <!-- General Settings Tab -->
        <div class="tab-pane fade show active" id="saGeneralSettings" role="tabpanel">
          <div class="card mb-6">
            <form action="{{ route('saSettings.update') }}" method="POST">
              @csrf
              <div class="card-header">
                <h5 class="card-title m-0">General Settings</h5>
              </div>
              <div class="card-body">
                <div class="row g-6">
                  <!-- App Version -->
                  <div class="col-12 col-md-6">
                    <label class="form-label mb-1" for="appVersion">App Version</label>
                    <input type="text" class="form-control" id="appVersion" name="appVersion"
                           value="{{ $settings->app_version ?? '1.0.0' }}" placeholder="Enter app version">
                  </div>

                  {{--<!-- Per Tenant Map Key -->
                  <div class="col-12 col-md-6">
                    <label class="form-label">Per Tenant Map Key</label>
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" id="isPerTenantMapKeyEnabled"
                             name="isPerTenantMapKeyEnabled"
                        {{ $settings->use_per_tenant_map_key ? 'checked' : '' }}>
                      <label class="form-check-label"
                             for="isPerTenantMapKeyEnabled">{{$settings->use_per_tenant_map_key ? 'Enabled' : 'Disabled'}}</label>
                    </div>
                  </div>--}}

                  <!-- Currency -->
                  <div class="col-12 col-md-6">
                    <label class="form-label mb-1" for="currency">Currency</label>
                    <input type="text" class="form-control" id="currency" name="currency"
                           value="{{ $settings->currency ?? 'USD' }}" placeholder="Enter currency code (e.g., USD)">
                  </div>

                  <!-- Currency Symbol -->
                  <div class="col-12 col-md-6">
                    <label class="form-label mb-1" for="currencySymbol">Currency Symbol</label>
                    <input type="text" class="form-control" id="currencySymbol" name="currencySymbol"
                           value="{{ $settings->currency_symbol ?? '$' }}" placeholder="Enter currency symbol">
                  </div>

                  <!-- Privacy Policy URL -->
                  <div class="col-12 col-md-6">
                    <label class="form-label mb-1" for="privacyPolicyUrl">Privacy Policy URL</label>
                    <input type="text" class="form-control" id="privacyPolicyUrl" name="privacyPolicyUrl"
                           value="{{ $settings->privacy_policy_url }}" placeholder="Enter privacy policy URL">
                  </div>

                  <!-- Currency Position -->
                  <div class="col-12 col-md-6">
                    <label class="form-label mb-1" for="currencyPosition">Currency Position</label>
                    <select class="form-control" id="currencyPosition" name="currencyPosition">
                      <option value="left" {{ $settings->currency_position === 'left' ? 'selected' : '' }}>Left</option>
                      <option value="right" {{ $settings->currency_position === 'right' ? 'selected' : '' }}>Right
                      </option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
        <!-- /General Settings Tab -->
        <!-- Payment Gateways Settings Section -->
        <div class="tab-pane fade" id="paymentGatewaysSettings" role="tabpanel">
          <div class="card mb-4">
            <div class="card-header">
              <h5 class="card-title m-0">Payment Gateways</h5>
            </div>
            <div class="card-body">
              <!-- PayPal Gateway -->
              <div class="border rounded p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">PayPal</h6>
                  <div class="form-check form-switch">
                    <input class="form-check-input gateway-toggle" type="checkbox" id="paypalEnabled"
                           data-target="#paypalSettings"
                      {{ $settings->paypal_enabled ? 'checked' : '' }}>
                  </div>
                </div>
                <div id="paypalSettings" class="mt-3 {{ $settings->paypal_enabled ? '' : 'd-none' }}">
                  <form action="{{ route('saSettings.paymentGatewayUpdate', 'paypal') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                      <label for="paypalMode" class="form-label">Mode</label>
                      <select class="form-select" id="paypalMode" name="paypalMode">
                        <option value="sandbox" {{ $settings->paypal_mode === 'sandbox' ? 'selected' : '' }}>Sandbox
                        </option>
                        <option value="live" {{ $settings->paypal_mode === 'live' ? 'selected' : '' }}>Live</option>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label for="paypalClientId" class="form-label">Client ID</label>
                      <input type="text" class="form-control" id="paypalClientId" name="paypalClientId"
                             value="{{ $settings->paypal_client_id }}">
                    </div>
                    <div class="mb-3">
                      <label for="paypalSecret" class="form-label">Secret</label>
                      <input type="text" class="form-control" id="paypalSecret" name="paypalSecret"
                             value="{{ $settings->paypal_secret }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                  </form>
                </div>
              </div>

              <!-- Razorpay Gateway -->
              <div class="border rounded p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">Razorpay</h6>
                  <div class="form-check form-switch">
                    <input class="form-check-input gateway-toggle" type="checkbox" id="razorpayEnabled"
                           data-target="#razorpaySettings"
                      {{ $settings->razorpay_enabled ? 'checked' : '' }}>
                  </div>
                </div>
                <div id="razorpaySettings" class="mt-3 {{ $settings->razorpay_enabled ? '' : 'd-none' }}">
                  <form action="{{ route('saSettings.paymentGatewayUpdate', 'razorpay') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                      <label for="razorpayKey" class="form-label">Key</label>
                      <input type="text" class="form-control" id="razorpayKey" name="razorpayKey"
                             value="{{ $settings->razorpay_key }}">
                    </div>
                    <div class="mb-3">
                      <label for="razorpaySecret" class="form-label">Secret</label>
                      <input type="text" class="form-control" id="razorpaySecret" name="razorpaySecret"
                             value="{{ $settings->razorpay_secret }}">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                  </form>
                </div>
              </div>

              <!-- Offline Payments Gateway -->
              <div class="border rounded p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">Offline Payments</h6>
                  <div class="form-check form-switch">
                    <input class="form-check-input gateway-toggle" type="checkbox" id="offlineEnabled"
                           name="offlineEnabled" data-target="#offlineSettings"
                      {{ $settings->offline_payment_enabled ? 'checked' : '' }}>
                  </div>
                </div>
                <div id="offlineSettings" class="mt-3 {{ $settings->offline_payment_enabled ? '' : 'd-none' }}">
                  <form action="{{ route('saSettings.paymentGatewayUpdate', 'offline') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                      <label for="offlineInstructions" class="form-label">Payment Instructions</label>
                      <textarea class="form-control" id="offlineInstructions" name="offlineInstructions"
                                rows="3">{{ $settings->offline_payment_instructions }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <!-- /Options-->
  </div>

@endsection
