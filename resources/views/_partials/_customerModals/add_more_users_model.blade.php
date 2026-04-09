<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-user-plus fs-3"></i></div>
          <h5 class="modal-title modal-title-hitech" id="addUserModalLabel">Add Users to Subscription</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form method="POST">
          @csrf
          <div class="mb-4">
            <label class="form-label-hitech">Number of Users to Add</label>
            <div class="input-group">
              <button type="button" class="btn btn-hitech-secondary" id="decreaseUsers">-</button>
              <input type="number" name="addUserModal-users" id="addUserModal-users" class="form-control form-control-hitech text-center" value="1" min="0">
              <button type="button" class="btn btn-hitech-secondary" id="increaseUsers">+</button>
            </div>
          </div>
          <div class="hitech-card-glass p-3 mb-4 rounded-3">
            <p class="mb-1"><span class="form-label-hitech">Per User Price</span><br><strong>{{ $settings->currency_symbol }}{{ $activePlan->per_user_price }}</strong></p>
            <p class="mb-1"><span class="form-label-hitech">Total Additional Cost</span><br><strong>{{ $settings->currency_symbol }}<span id="addUserModal-totalCost">0</span> / {{ ucfirst($activePlan->duration_type->value) }}</strong></p>
            <p class="mb-0"><span class="form-label-hitech">Amount to be Paid Now</span><br><strong style="color:#005a5a;">{{ $settings->currency_symbol }}<span id="addUserModal-amountToBePaid">0</span></strong></p>
          </div>
          <div class="d-grid gap-2">
            @if($gateways['paypal'])
              <button formaction="{{ route('paypal.paypalPaymentForAddUser', ['gateway' => 'paypal']) }}" class="btn btn-hitech">
                <i class="bx bxl-paypal me-2"></i>Pay with PayPal
              </button>
            @endif
            @if($gateways['razorpay'])
              <a href="#" onclick="startRazorpayPaymentForUserAdd('{{ route('razorpay.razorPayPaymentForAddUser') }}')" class="btn btn-hitech-secondary">
                <i class="bx bx-rupee me-2"></i>Pay with Razorpay
              </a>
            @endif
            @if($gateways['offline'])
              <hr class="my-2">
              <p class="text-muted small">{{ $gateways['offlineInstructions'] }}</p>
              <button formaction="{{ route('offlinePayment.payOfflineForUserAdd', ['gateway' => 'offline']) }}" class="btn btn-hitech-secondary">
                <i class="bx bx-money me-2"></i>Pay Offline
              </button>
            @endif
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
