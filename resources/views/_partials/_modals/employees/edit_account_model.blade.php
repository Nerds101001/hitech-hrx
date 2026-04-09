@php use App\Helpers\StaticDataHelpers; @endphp
<!-- Edit Account Model -->
<div class="modal fade" id="offcanvasEditBankAccount" tabindex="-1" aria-labelledby="offcanvasEditBankAccountLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center gap-2">
          <div class="modal-icon-header">
            <i class="bx bx-building-house fs-4"></i>
          </div>
          <h5 id="offcanvasEditBankAccountLabel" class="modal-title modal-title-hitech">@lang('Edit Bank Account')</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
          <i class="bx bx-x"></i>
        </button>
      </div>

      <div class="modal-body p-6">
        <form id="bankAccountForm" action="{{route('employees.addOrUpdateBankAccount')}}" method="POST">
          @csrf
          <input type="hidden" name="userId" id="userId" value="{{$user->id}}">
          <input type="hidden" name="id" id="id" value="{{$user->bankAccount != null ? $user->bankAccount->id : ''}}">

          <div class="row g-6 mb-4">
            <div class="col-md-6">
              <label class="form-label-hitech" for="accountNumber">@lang('Account Number')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="accountNumber" placeholder="@lang('Enter account number')" value="{{$user->bankAccount != null ?$user->bankAccount->account_number: ''}}" name="accountNumber" />
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="ifscCode">@lang('IFSC Code')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="ifscCode" placeholder="@lang('Enter IFSC code')" value="{{$user->bankAccount != null ?$user->bankAccount->ifsc_code : ''}}" name="ifscCode" />
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="bankName">@lang('Bank Name')<span class="text-danger">*</span></label>
              <select class="form-select select2-hitech" id="bankName" name="bankName">
                <option value="">Select Bank</option>
                @foreach (StaticDataHelpers::getIndianBanksList() as $bank)
                  <option value="{{$bank}}" {{($user->bankAccount != null && $user->bankAccount->bank_name == $bank) ? 'selected' : ''}}>{{$bank}}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="branch">@lang('Branch')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="branch" placeholder="@lang('Enter branch name')" name="branch" value="{{$user->bankAccount != null ?$user->bankAccount->branch:''}}" />
            </div>
          </div>

          <div class="d-flex justify-content-end gap-3 mt-6">
            <button type="reset" class="btn btn-label-danger px-6 rounded-pill fw-bold" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-hitech px-8 rounded-pill fw-bold">
              @lang('Save Changes') <i class="bx bx-check-circle ms-2"></i>
            </button>
          </div>
        </form>
      </div>
    </div>

    {{-- Redundant style block removed, now using hitech-portal.scss --}}
  </div>
</div>
<!-- /Edit Account Model -->
