<div class="modal fade" id="renewModal" tabindex="-1" aria-labelledby="renewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-refresh fs-3"></i></div>
          <h5 class="modal-title modal-title-hitech" id="renewModalLabel">Renew Subscription</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <div class="hitech-card-glass p-3 mb-4 rounded-3">
          <p class="mb-1"><span class="form-label-hitech">Plan</span><br><strong>{{ $activePlan->name }}</strong></p>
          <p class="mb-1"><span class="form-label-hitech">Base Price</span><br><strong>{{ $settings->currency_symbol }}{{ $activePlan->base_price }}</strong></p>
          <p class="mb-1"><span class="form-label-hitech">Per User Price</span><br><strong>{{ $settings->currency_symbol }}{{ $activePlan->per_user_price }}</strong></p>
          <p class="mb-1"><span class="form-label-hitech">Duration</span><br><strong>{{ $activePlan->duration }} {{ ucfirst($activePlan->duration_type->value) }}</strong></p>
          <p class="mb-1"><span class="form-label-hitech">Additional Users</span><br><strong>{{ $subscription->additional_users }}</strong></p>
          <p class="mb-0"><span class="form-label-hitech">Amount to be Paid</span><br><strong style="color:#005a5a;">{{ $settings->currency_symbol }}{{ $activePlan->base_price + ($activePlan->additional_users * $subscription->users_count) }}</strong></p>
        </div>
        <form method="POST">
          @csrf
          <div class="d-grid gap-2">
            @if($gateways['paypal'])
              <button formaction="{{ route('paypal.paypalPaymentForRenewal', ['gateway' => 'paypal']) }}" class="btn btn-hitech">
                <i class="bx bxl-paypal me-2"></i>Pay with PayPal
              </button>
            @endif
            @if($gateways['razorpay'])
              <a href="#" onclick="startRazorpayPaymentForRenewal('{{ route('razorpay.razorPayPaymentForRenewal') }}')" class="btn btn-hitech-secondary">
                <i class="bx bx-rupee me-2"></i>Pay with Razorpay
              </a>
            @endif
            @if($gateways['offline'])
              <hr class="my-2">
              <p class="text-muted small">{{ $gateways['offlineInstructions'] }}</p>
              <button formaction="{{ route('offlinePayment.payOfflineForRenewal', ['gateway' => 'offline']) }}" class="btn btn-hitech-secondary">
                <i class="bx bx-money me-2"></i>Pay Offline
              </button>
            @endif
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
