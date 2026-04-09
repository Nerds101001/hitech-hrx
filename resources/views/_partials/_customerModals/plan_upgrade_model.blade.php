<div class="modal fade" id="upgradeModal" tabindex="-1" aria-labelledby="upgradeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-trending-up fs-3"></i></div>
          <h5 class="modal-title modal-title-hitech" id="upgradeModalLabel">Upgrade Plan</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <div class="hitech-card-glass p-3 mb-4 rounded-3">
          <p class="mb-1"><span class="form-label-hitech">Plan</span><br><strong><span id="upgradeModalPlanName"></span></strong></p>
          <p class="mb-0"><span class="form-label-hitech">Price Difference</span><br><strong style="color:#005a5a;">{{ $settings->currency_symbol }}<span id="upgradeModalDifference"></span></strong></p>
        </div>
        <form method="POST">
          @csrf
          <input type="hidden" name="upgradeModal-planId" id="upgradeModal-planId">
          <div class="d-grid gap-2">
            @if($gateways['paypal'])
              <button formaction="{{ route('paypal.paypalPaymentForUpgrade', ['gateway' => 'paypal']) }}" class="btn btn-hitech">
                <i class="bx bxl-paypal me-2"></i>Pay with PayPal
              </button>
            @endif
            @if($gateways['razorpay'])
              <a href="#" onclick="startRazorpayPaymentForUpgrade('{{ route('razorpay.razorPayPaymentForUpgrade') }}')" class="btn btn-hitech-secondary">
                <i class="bx bx-rupee me-2"></i>Pay with Razorpay
              </a>
            @endif
            @if($gateways['offline'])
              <hr class="my-2">
              <p class="text-muted small">{{ $gateways['offlineInstructions'] }}</p>
              <button formaction="{{ route('offlinePayment.payOfflineForUpgrade', ['gateway' => 'offline']) }}" class="btn btn-hitech-secondary">
                <i class="bx bx-money me-2"></i>Pay Offline
              </button>
            @endif
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
