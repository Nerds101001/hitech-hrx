<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-credit-card fs-3"></i></div>
          <h5 class="modal-title modal-title-hitech" id="paymentModalLabel">Choose Payment Method</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <div class="hitech-card-glass p-3 mb-4 rounded-3">
          <p class="mb-1"><span class="form-label-hitech">Plan</span><br><strong id="paymentModal-modalPlanName"></strong></p>
          <p class="mb-1"><span class="form-label-hitech">Base Price</span><br><strong>$<span id="paymentModal-modalPlanPrice"></span></strong></p>
          <p class="mb-1"><span class="form-label-hitech">Price Per User</span><br><strong>$<span id="paymentModal-modalPlanPerUserPrice"></span></strong></p>
          <p class="mb-0"><span class="form-label-hitech">Included Users</span><br><strong><span id="paymentModal-modalPlanUsers"></span></strong></p>
        </div>
        <form id="paymentForm" method="POST">
          @csrf
          <input type="hidden" name="paymentModal-planId" id="paymentModal-planId">
          <div class="mb-4">
            <label class="form-label-hitech">Additional Users</label>
            <div class="input-group">
              <button type="button" class="btn btn-hitech-secondary" id="paymentModal-decreaseUsers">-</button>
              <input type="number" name="paymentModal-users" id="paymentModal-users" class="form-control form-control-hitech text-center" value="0" min="0">
              <button type="button" class="btn btn-hitech-secondary" id="paymentModal-increaseUsers">+</button>
            </div>
          </div>
          <p class="text-end fw-bold mb-4" style="color:#005a5a;">Total: {{ $settings->currency_symbol }}<span id="paymentModal-totalPrice"></span></p>
          <div class="d-grid gap-2">
            @if($gateways['paypal'])
              <button type="submit" onclick="startPaypalPayment('{{ route('paypal.paypalPayment') }}')" class="btn btn-hitech">
                <i class="bx bxl-paypal me-2"></i>Pay with PayPal
              </button>
            @endif
            @if($gateways['razorpay'])
              <a href="#" onclick="startRazorpayPayment('{{ route('razorpay.razorPayPayment') }}')" class="btn btn-hitech-secondary">
                <i class="bx bx-rupee me-2"></i>Pay with Razorpay
              </a>
            @endif
            @if($gateways['offline'])
              <hr class="my-2">
              <p class="text-muted small">{{ $gateways['offlineInstructions'] }}</p>
              <button formaction="{{ route('offlinePayment.create', ['gateway' => 'offline', 'type' => 'plan']) }}" class="btn btn-hitech-secondary">
                <i class="bx bx-money me-2"></i>Pay Offline
              </button>
            @endif
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
