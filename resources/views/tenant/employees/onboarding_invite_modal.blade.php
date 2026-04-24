@php
    // BACKEND DATA FETCH
    $rolesFixed = \Illuminate\Support\Facades\DB::table('roles')->get();
    $departmentsFixed = \Illuminate\Support\Facades\DB::table('departments')->get();
    $designationsFixed = \Illuminate\Support\Facades\DB::table('designations')->get();
    $managersFixed = \Illuminate\Support\Facades\DB::table('users')->whereIn('status', ['active', 'ACTIVE'])->get();
    $sitesFixed = \Illuminate\Support\Facades\DB::table('sites')->get();
@endphp

<div class="modal fade" id="onboardingInviteModalV2" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <!-- Header -->
      <div class="modal-header modal-header-hitech p-4">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-paper-plane fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech text-white text-uppercase fw-bold" style="letter-spacing: 1px;">
                Invite for Onboarding
            </h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
      </div>

      <div class="modal-body modal-body-hitech p-5">
        <form id="onboardingInviteForm" action="{{ route('employees.initiateOnboarding') }}" method="POST">
          @csrf
          
          <!-- Basic Info -->
          <div class="row g-4 mb-4">
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">First Name <span class="text-danger">*</span></label>
              <input type="text" name="firstName" class="form-control form-control-hitech" placeholder="Enter first name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Last Name <span class="text-danger">*</span></label>
              <input type="text" name="lastName" class="form-control form-control-hitech" placeholder="Enter last name" required>
            </div>
          </div>

          <div class="row g-4 mb-4">
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control form-control-hitech" placeholder="email@example.com" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Phone Number <span class="text-danger">*</span></label>
              <input type="text" name="phone" class="form-control form-control-hitech" placeholder="10-digit mobile" required>
            </div>
          </div>

          <!-- EMPLOYEE ID & JOINING DATE -->
          <div class="row g-4 mb-4">
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Employee ID (Optional)</label>
              <input type="text" name="employeeCode" class="form-control form-control-hitech" placeholder="e.g. EMP001">
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Joining Date <span class="text-danger">*</span></label>
              <input type="date" name="doj" class="form-control form-control-hitech" required>
            </div>
          </div>

          <!-- ASSIGNED ROLE & DEPARTMENT -->
          <div class="row g-4 mb-4">
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Assigned Role <span class="text-danger">*</span></label>
              <select name="role" class="form-select select2-hitech" required>
                <option value="">Select Role</option>
                @foreach($rolesFixed as $r)
                  <option value="{{ $r->name }}">{{ $r->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Department <span class="text-danger">*</span></label>
              <select name="departmentId" class="form-select select2-hitech" required>
                <option value="">Select Department</option>
                @foreach($departmentsFixed as $d)
                  <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <!-- DESIGNATION & REPORTING MANAGER -->
          <div class="row g-4 mb-4">
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Designation <span class="text-danger">*</span></label>
              <select name="designationId" class="form-select select2-hitech" required>
                <option value="">Select Designation</option>
                @foreach($designationsFixed as $ds)
                  <option value="{{ $ds->id }}">{{ $ds->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Reporting Manager <span class="text-danger">*</span></label>
              <select name="reportingToId" class="form-select select2-hitech" required>
                <option value="">Select Manager</option>
                @foreach($managersFixed as $m)
                  <option value="{{ $m->id }}">{{ $m->first_name }} {{ $m->last_name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <!-- CTC & PROBATION -->
          <div class="row g-4 mb-4">
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Annual CTC (₹)</label>
              <input type="number" name="baseSalary" class="form-control form-control-hitech" placeholder="e.g. 600000">
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech fw-bold small text-muted text-uppercase mb-2">Probation Period (Months)</label>
              <input type="number" name="probationPeriodMonths" class="form-control form-control-hitech" value="6" placeholder="e.g. 6">
            </div>
          </div>

          <hr class="my-4 opacity-50">
          
          <div class="d-flex justify-content-end gap-3 mt-4">
            <button type="button" class="btn btn-label-secondary px-6 rounded-pill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech px-8 shadow-sm rounded-pill" id="onboardingSubmitBtn">
                <i class="bx bx-paper-plane me-2"></i>Invite Candidate
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
    // Initialize Select2 for Onboarding Modal
    const modalId = '#onboardingInviteModalV2';
    $(modalId).on('shown.bs.modal', function() {
        $('.select2-hitech').each(function() {
            $(this).select2({
                dropdownParent: $(modalId),
                placeholder: $(this).find('option:first').text(),
                allowClear: true,
                width: '100%'
            });
        });
    });
});
</script>

