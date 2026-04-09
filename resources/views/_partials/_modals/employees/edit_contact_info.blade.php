<!-- Edit Contact Info Modal -->
<div class="modal fade" id="offcanvasEditContactInfo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-phone-call"></i>
            </div>
            <h5 class="modal-title modal-title-hitech mb-0">Edit Contact & Address Details</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
          <i class="bx bx-x"></i>
        </button>
      </div>

      <div class="modal-body modal-body-hitech">
        <form id="contactInfoForm" action="{{ route('employees.updateBasicInfo') }}" method="POST">
          @csrf
          <input type="hidden" name="id" value="{{ $user->id }}">
          {{-- Pass through all required basic info fields as hidden so controller passes --}}
          <input type="hidden" name="firstName" value="{{ $user->first_name }}">
          <input type="hidden" name="lastName" value="{{ $user->last_name }}">
          <input type="hidden" name="dob" value="{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : '' }}">
          <input type="hidden" name="gender" value="{{ $user->gender?->value ?? $user->gender }}">
          <input type="hidden" name="blood_group" value="{{ $user->blood_group }}">
          <input type="hidden" name="marital_status" value="{{ $user->marital_status }}">
          <input type="hidden" name="father_name" value="{{ $user->father_name }}">
          <input type="hidden" name="mother_name" value="{{ $user->mother_name }}">
          <input type="hidden" name="spouse_name" value="{{ $user->spouse_name }}">
          <input type="hidden" name="no_of_children" value="{{ $user->no_of_children }}">
          <input type="hidden" name="birth_country" value="{{ $user->birth_country }}">
          <input type="hidden" name="citizenship" value="{{ $user->citizenship }}">

          <!-- SECTION 1: CONTACT INFORMATION -->
          <div class="d-flex align-items-center mb-3 mt-2">
            <div class="badge p-1 me-2" style="background-color: rgba(18, 116, 100, 0.1); color: #127464;"><i class="bx bx-envelope"></i></div>
            <h6 class="mb-0 fw-bold text-uppercase small" style="color: #127464;">Contact Information</h6>
          </div>
          <div class="row g-3 mb-5">
            <div class="col-md-6">
              <label class="form-label-hitech" for="ci_official_email">Official Email (Login) <span class="text-danger">*</span></label>
              <input type="email" class="form-control form-control-hitech" id="ci_official_email" name="email" value="{{ $user->email }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech" for="ci_personal_email">Personal Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control form-control-hitech" id="ci_personal_email" name="personal_email" value="{{ $user->personal_email }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech" for="ci_phone">Primary / Personal Phone <span class="text-danger">*</span></label>
              <input type="text" name="phone" id="ci_phone" class="form-control form-control-hitech" value="{{ $user->phone }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech" for="ci_official_phone">Official Phone <span class="text-danger">*</span></label>
              <input type="text" name="official_phone" id="ci_official_phone" class="form-control form-control-hitech" value="{{ $user->official_phone }}" required />
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech" for="ci_altPhone">Alternate Phone</label>
              <input type="text" name="altPhone" id="ci_altPhone" class="form-control form-control-hitech" value="{{ $user->alternate_number }}" />
            </div>
          </div>

          <!-- SECTION 2: ADDRESS DETAILS -->
          <div class="d-flex align-items-center mb-3">
            <div class="badge p-1 me-2" style="background-color: rgba(18, 116, 100, 0.1); color: #127464;"><i class="bx bx-map"></i></div>
            <h6 class="mb-0 fw-bold text-uppercase small" style="color: #127464;">Address Details</h6>
          </div>
          <div class="row g-3 mb-5">
            <!-- Current Address -->
            <div class="col-md-6">
              <p class="fw-bold small mb-2 text-dark border-bottom pb-2"><i class="bx bx-map-pin me-1" style="color: #127464;"></i> Current Address</p>
              <div class="row g-2">
                <div class="col-12"><input type="text" name="temp_building" id="ci_temp_building" class="form-control form-control-hitech py-1" placeholder="Building/Flat/House" value="{{ $user->temp_building }}"></div>
                <div class="col-12"><input type="text" name="temp_street" id="ci_temp_street" class="form-control form-control-hitech py-1" placeholder="Street/Area" value="{{ $user->temp_street }}"></div>
                <div class="col-md-6"><input type="text" name="temp_city" id="ci_temp_city" class="form-control form-control-hitech py-1" placeholder="City" value="{{ $user->temp_city }}"></div>
                <div class="col-md-6"><input type="text" name="temp_state" id="ci_temp_state" class="form-control form-control-hitech py-1" placeholder="State" value="{{ $user->temp_state }}"></div>
                <div class="col-md-6"><input type="text" name="temp_zip" id="ci_temp_zip" class="form-control form-control-hitech py-1" placeholder="Zip Code" value="{{ $user->temp_zip }}"></div>
                <div class="col-md-6"><input type="text" name="temp_country" id="ci_temp_country" class="form-control form-control-hitech py-1" placeholder="Country" value="{{ $user->temp_country }}"></div>
              </div>
            </div>
            <!-- Permanent Address -->
            <div class="col-md-6">
              <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-2">
                <p class="fw-bold small mb-0 text-dark"><i class="bx bx-home-heart me-1" style="color: #127464;"></i> Permanent Address</p>
                <div class="form-check form-switch mb-0" title="Copy current address to permanent">
                  <input class="form-check-input" type="checkbox" id="ci_sameAddressToggle" style="cursor:pointer;">
                  <label class="form-check-label small text-muted" for="ci_sameAddressToggle" style="cursor:pointer; font-size:0.75rem;">Same as Current</label>
                </div>
              </div>
              <div class="row g-2">
                <div class="col-12"><input type="text" name="perm_building" id="ci_perm_building" class="form-control form-control-hitech py-1" placeholder="Building/Flat/House" value="{{ $user->perm_building }}"></div>
                <div class="col-12"><input type="text" name="perm_street" id="ci_perm_street" class="form-control form-control-hitech py-1" placeholder="Street/Area" value="{{ $user->perm_street }}"></div>
                <div class="col-md-6"><input type="text" name="perm_city" id="ci_perm_city" class="form-control form-control-hitech py-1" placeholder="City" value="{{ $user->perm_city }}"></div>
                <div class="col-md-6"><input type="text" name="perm_state" id="ci_perm_state" class="form-control form-control-hitech py-1" placeholder="State" value="{{ $user->perm_state }}"></div>
                <div class="col-md-6"><input type="text" name="perm_zip" id="ci_perm_zip" class="form-control form-control-hitech py-1" placeholder="Zip Code" value="{{ $user->perm_zip }}"></div>
                <div class="col-md-6"><input type="text" name="perm_country" id="ci_perm_country" class="form-control form-control-hitech py-1" placeholder="Country" value="{{ $user->perm_country }}"></div>
              </div>
            </div>
          </div>

          <!-- SECTION 3: EMERGENCY CONTACT -->
          <div class="d-flex align-items-center mb-3">
            <div class="badge p-1 me-2" style="background-color: rgba(18, 116, 100, 0.1); color: #127464;"><i class="bx bx-error-alt"></i></div>
            <h6 class="mb-0 fw-bold text-uppercase small" style="color: #127464;">Emergency Contact</h6>
          </div>
          <div class="row g-3 mb-2">
            <div class="col-md-4">
              <label class="form-label-hitech">Contact Name</label>
              <input type="text" name="emergency_contact_name" class="form-control form-control-hitech" value="{{ $user->emergency_contact_name }}" placeholder="Full Name" />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Relationship</label>
              <input type="text" name="emergency_contact_relation" class="form-control form-control-hitech" value="{{ $user->emergency_contact_relation }}" placeholder="e.g. Spouse, Parent" />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Phone Number</label>
              <input type="text" name="emergency_contact_phone" class="form-control form-control-hitech" value="{{ $user->emergency_contact_phone }}" placeholder="Mobile Number" />
            </div>
          </div>

          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit px-5">
              Save Contact Details <i class="bx bx-check-circle ms-1"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var toggle = document.getElementById('ci_sameAddressToggle');
  if (!toggle) return;
  var fields = ['building','street','city','state','zip','country'];
  toggle.addEventListener('change', function() {
    if (this.checked) {
      fields.forEach(function(f) {
        var src = document.getElementById('ci_temp_' + f);
        var dst = document.getElementById('ci_perm_' + f);
        if (src && dst) { dst.value = src.value; dst.readOnly = true; dst.classList.add('bg-light'); }
      });
      fields.forEach(function(f) {
        var src = document.getElementById('ci_temp_' + f);
        if(src) src.addEventListener('input', function() {
          if (!toggle.checked) return;
          var dst = document.getElementById('ci_perm_' + f);
          if (dst) dst.value = this.value;
        });
      });
    } else {
      fields.forEach(function(f) {
        var dst = document.getElementById('ci_perm_' + f);
        if (dst) { dst.readOnly = false; dst.classList.remove('bg-light'); }
      });
    }
  });
})();
</script>
<!-- /Edit Contact Info Modal -->
