<!-- Edit Bank Account Information Modal -->
<div class="modal fade" id="editBankAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-building-house"></i>
            </div>
            <h5 class="modal-title modal-title-hitech mb-0">@lang('Bank Account Update')</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
          <i class="bx bx-x"></i>
        </button>
      </div>

      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employees.addOrUpdateBankAccount') }}" method="POST" id="bankAccountForm" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="userId" id="userId" value="{{ $user->id }}">

          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label-hitech" for="accountName">@lang('Account Holder Name') <span class="text-danger">*</span></label>
              <input type="text" name="accountName" id="accountName" class="form-control form-control-hitech" placeholder="@lang('Full Name as per Bank')" value="{{ $user->bank_account ? $user->bank_account->account_name : '' }}" required/>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech" for="bankName">@lang('Bank Name') <span class="text-danger">*</span></label>
              <input type="text" name="bankName" id="bankName" class="form-control form-control-hitech" placeholder="@lang('e.g. HDFC Bank')" value="{{ $user->bank_account ? $user->bank_account->bank_name : '' }}" required/>
            </div>

            <div class="col-md-6">
               <label class="form-label-hitech" for="bankCode">@lang('IFSC / Bank Code') <span class="text-danger">*</span></label>
               <input type="text" name="bankCode" id="bankCode" class="form-control form-control-hitech" placeholder="@lang('e.g. HDFC0001234')" value="{{ $user->bank_account ? $user->bank_account->bank_code : '' }}" required/>
            </div>
            <div class="col-md-6">
               <label class="form-label-hitech" for="branchName">@lang('Branch Name')</label>
               <input type="text" name="branchName" id="branchName" class="form-control form-control-hitech" placeholder="@lang('Branch Location')" value="{{ $user->bank_account ? $user->bank_account->branch_name : '' }}"/>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="accountNumber">@lang('Bank Account Number') <span class="text-danger">*</span></label>
              <div class="d-flex gap-2">
                <input type="password" name="accountNumber" id="accountNumber" class="form-control form-control-hitech" placeholder="@lang('Account Number')" value="{{ $user->bank_account ? $user->bank_account->account_number : '' }}" autocomplete="off" required style="border-radius:10px !important;"/>
                <button type="button" class="btn btn-secondary btn-icon flex-shrink-0" style="width:44px;height:44px;border-radius:10px;" onclick="var f=document.getElementById('accountNumber'); var c=document.getElementById('confirmAccountNumber'); if(f.type==='password'){f.type='text';c.type='text';this.innerHTML='<i class=\'bx bx-hide\'></i>';}else{f.type='password';c.type='password';this.innerHTML='<i class=\'bx bx-show\'></i>';}"><i class="bx bx-show"></i></button>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="confirmAccountNumber">@lang('Confirm Bank Account Number') <span class="text-danger">*</span></label>
              <input type="password" id="confirmAccountNumber" class="form-control form-control-hitech" placeholder="@lang('Re-type Account Number')" value="{{ $user->bank_account ? $user->bank_account->account_number : '' }}" autocomplete="off" required/>
              <div id="accountNumberFeedback" class="invalid-feedback">Account numbers do not match.</div>
            </div>

            <!-- Bank Document Upload -->
            <div class="col-12">
              <label class="form-label-hitech" for="bankDocument">@lang('Bank Passbook / Cancelled Cheque') <span class="text-danger">*</span></label>
              <div class="p-3 border-2 rounded-3 text-center" style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                  <i class="bx bx-cloud-upload text-muted mb-2" style="font-size: 2rem;"></i>
                  <p class="small text-muted mb-2">Upload JPG, PNG or PDF (Max 5MB)</p>
                  <input type="file" name="bankDocument" id="bankDocument" class="form-control form-control-hitech" accept=".jpg,.jpeg,.png,.pdf" required/>
                  @if($user->bank_account && $user->bank_account->passbook_path)
                      <div class="mt-2">
                          <span class="badge bg-label-success">Current Document: 
                              <a href="{{ \App\Helpers\FileSecurityHelper::generateSecureUrl($user->bank_account->passbook_path) }}" target="_blank" class="text-success">View</a>
                          </span>
                      </div>
                  @endif
              </div>
            </div>
          </div>

          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-primary px-5 d-flex align-items-center" id="saveBankBtn">
              @lang('Save Bank Details') <i class="bx bx-check-circle ms-2 fs-5"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bankAccountForm');
    const accNumber = document.getElementById('accountNumber');
    const confirmAccNumber = document.getElementById('confirmAccountNumber');
    const feedback = document.getElementById('accountNumberFeedback');

    function validateAccountNumber() {
        if (confirmAccNumber.value !== accNumber.value) {
            confirmAccNumber.classList.add('is-invalid');
            return false;
        } else {
            confirmAccNumber.classList.remove('is-invalid');
            return true;
        }
    }

    if(accNumber && confirmAccNumber) {
        accNumber.addEventListener('input', validateAccountNumber);
        confirmAccNumber.addEventListener('input', validateAccountNumber);
    }

    if(form) {
        form.addEventListener('submit', function(e) {
            console.log("Submitting bank account form...");
            if (!validateAccountNumber()) {
                console.log("Validation failed: Account numbers do not match.");
                e.preventDefault();
                confirmAccNumber.focus();
            } else {
                console.log("Validation successful. Form will be submitted.");
            }
        });
    }
});
</script>
