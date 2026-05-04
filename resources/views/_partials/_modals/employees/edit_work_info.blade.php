@php use Carbon\Carbon; @endphp
<!-- Edit Work Information Modal -->
<div class="modal fade" id="offcanvasEditWorkInfo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-briefcase"></i>
            </div>
            <h5 class="modal-title modal-title-hitech mb-0">@lang('Edit Work Information')</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
          <i class="bx bx-x"></i>
        </button>
      </div>
      
      <div class="modal-body modal-body-hitech">
        <form id="workInfoForm" action="{{route('employees.updateWorkInformation')}}" method="POST">
          @csrf
          <input type="hidden" name="id" id="id" value="{{ $user->id }}">

          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label-hitech" for="role">@lang('Role') <span class="text-danger">*</span></label>
              <select class="form-select form-select-hitech select2" id="role" name="role" required>
                <option value="">Select Role</option>
                @foreach($roles ?? [] as $roleOption)
                  @php
                    $roleName = str_replace(['_', '-'], ' ', $roleOption->name);
                    $roleDisplayName = ucwords(strtolower($roleName));
                  @endphp
                  <option value="{{ $roleOption->name }}" {{ ($role ?? 'Employee') == $roleOption->name ? 'selected' : '' }}>
                    {{ $roleDisplayName }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="departmentId">@lang('Department') <span class="text-danger">*</span></label>
              <select class="form-select form-select-hitech select2" id="departmentId" name="departmentId" required>
                <option value="">Select Department</option>
                @foreach($departments ?? [] as $deptOption)
                  <option value="{{ $deptOption->id }}" {{ $user->department_id == $deptOption->id ? 'selected' : '' }}>
                    {{ $deptOption->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="leavePolicyProfileId">@lang('Leave Policy Profile') <span class="text-danger">*</span></label>
              <select name="leavePolicyProfileId" id="leavePolicyProfileId" class="form-select form-select-hitech select2" required>
                <option value="">Select Policy</option>
                @foreach($leavePolicyProfiles ?? [] as $profile)
                  <option value="{{ $profile->id }}" {{ $user->leave_policy_profile_id == $profile->id ? 'selected' : '' }}>
                    {{ $profile->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="reportingToId">@lang('Reporting To') <span class="text-danger">*</span></label>
              <select class="form-select form-select-hitech select2" id="reportingToId" name="reportingToId" required>
                <option value="">Select Reporting To</option>
                @foreach($allUsers ?? [] as $reportingUser)
                  <option value="{{ $reportingUser->id }}" {{ $user->reporting_to_id == $reportingUser->id ? 'selected' : '' }}>
                    {{ $reportingUser->first_name }} {{ $reportingUser->last_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="designationId">@lang('Designation') <span class="text-danger">*</span></label>
              <select class="form-select form-select-hitech select2" id="designationId" name="designationId" required>
                <option value="">Select Designation</option>
                @foreach($designations ?? [] as $designationOption)
                  <option value="{{ $designationOption->id }}" {{ $user->designation_id == $designationOption->id ? 'selected' : '' }}>
                    {{ $designationOption->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="doj">Date of Joining <span class="text-danger">*</span></label>
              <input type="date" name="doj" id="doj" class="form-control form-control-hitech" required
                     value="{{ $user->date_of_joining != null ? \Carbon\Carbon::parse($user->date_of_joining)->format('Y-m-d') : '' }}"/>
            </div>

            <div class="col-md-6">
                <label class="form-label-hitech" for="attendanceType">@lang('Attendance Type') <span class="text-danger">*</span></label>
                <select class="form-select form-select-hitech" id="attendanceType" name="attendanceType" required>
                    <option value="open" {{ $user->attendance_type == 'open' ? 'selected' : '' }}>Open (Anywhere)</option>
                    <option value="geofence" {{ $user->attendance_type == 'geofence' ? 'selected' : '' }}>Geofence</option>
                    <option value="ipAddress" {{ $user->attendance_type == 'ip_address' ? 'selected' : '' }}>IP Address</option>
                    <option value="staticqr" {{ $user->attendance_type == 'qr_code' ? 'selected' : '' }}>Static QR</option>
                    <option value="site" {{ $user->attendance_type == 'site' ? 'selected' : '' }}>Site</option>
                    <option value="dynamicqr" {{ $user->attendance_type == 'dynamic_qr' ? 'selected' : '' }}>Dynamic QR</option>
                    <option value="face" {{ $user->attendance_type == 'face_recognition' ? 'selected' : '' }}>Face</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label-hitech" for="biometric_id">@lang('Biometric ID (Machine ID)')</label>
                <input type="text" name="biometric_id" id="biometric_id" class="form-control form-control-hitech" 
                       value="{{ $user->biometric_id }}" placeholder="e.g. 101" />
            </div>
          </div>

          <!-- Dynamic Attendance Settings Groups -->
          <div id="geofenceGroupDiv" class="mt-4" style="display:none;">
            <label class="form-label-hitech">Geofence Group</label>
            <select id="geofenceGroupId" name="geofenceGroupId" class="form-select form-select-hitech"></select>
          </div>

          <div id="ipGroupDiv" class="mt-4" style="display:none;">
            <label class="form-label-hitech">IP Group</label>
            <select id="ipGroupId" name="ipGroupId" class="form-select form-select-hitech"></select>
          </div>

          <div id="qrGroupDiv" class="mt-4" style="display:none;">
            <label class="form-label-hitech">QR Group</label>
            <select id="qrGroupId" name="qrGroupId" class="form-select form-select-hitech"></select>
          </div>

          <div id="dynamicQrDiv" class="mt-4" style="display:none;">
            <label class="form-label-hitech">QR Device</label>
            <select id="dynamicQrId" name="dynamicQrId" class="form-select form-select-hitech"></select>
          </div>

          <div id="siteDiv" class="mt-4" style="display:none;">
            <label class="form-label-hitech">Site</label>
            <select id="siteId" name="siteId" class="form-select form-select-hitech"></select>
          </div>

          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-hitech-modal-submit">
              @lang('Save Changes') <i class="bx bx-check-circle ms-1"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<style>
/* Forcing Hi-Tech Theme on Select2 within this modal */
#offcanvasEditWorkInfo .select2-container--default .select2-selection--single {
    border-radius: 12px !important;
    border: 1px solid #E2E8F0 !important;
    background-color: #F8FAFC !important;
    height: 48px !important;
    display: flex !important;
    align-items: center !important;
}

#offcanvasEditWorkInfo .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #0f172a !important;
    font-weight: 500 !important;
    text-transform: capitalize !important;
}

#offcanvasEditWorkInfo .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #007a7a !important;
    color: white !important;
}

#offcanvasEditWorkInfo .select2-results__option {
    text-transform: capitalize !important;
    padding: 8px 12px !important;
}
</style>
<script>
(function() {
    const initSelect2 = () => {
        const $jq = window.jQuery;
        if (!$jq) return;

        function formatRoleText(text) {
            if (!text) return text;
            return text.replace(/[_-]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        $jq('#role').select2({
            dropdownParent: $jq('#offcanvasEditWorkInfo'),
            placeholder: 'Select Role',
            templateResult: function(data) {
                if (!data.id) return data.text;
                return $jq('<span>' + formatRoleText(data.text) + '</span>');
            },
            templateSelection: function(data) {
                if (!data.id) return data.text;
                return formatRoleText(data.text);
            }
        });

        // Re-initialize on modal show just in case
        $jq('#offcanvasEditWorkInfo').on('shown.bs.modal', function () {
            $jq('#role').select2({
                dropdownParent: $jq('#offcanvasEditWorkInfo'),
                placeholder: 'Select Role'
            });
        });
    };

    const timer = setInterval(() => {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            clearInterval(timer);
            initSelect2();
        }
    }, 100);
})();
</script>
<!-- /Edit Work Information Modal -->
