@php use App\Enums\Gender;use Carbon\Carbon; @endphp
<!-- Edit Basic Info Modal -->
<div class="modal fade" id="offcanvasEditBasicInfo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-content-hitech">
      {{-- Modal Header --}}
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3">
            <i class="bx bx-user fs-4"></i>
          </div>
          <h5 class="modal-title modal-title-hitech">Edit Full Profile Details</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
          <i class="bx bx-x"></i>
        </button>
      </div>
      
      <div class="modal-body p-4 pt-5">
        <form id="basicInfoForm" action="{{route('employees.updateBasicInfo')}}" method="POST">
          @csrf
          <input type="hidden" name="id" id="id" value="{{ $user->id }}">
          
          {{-- Redundant style block removed, now using hitech-portal.scss --}}

          {{-- Section 1 --}}
          <div class="hitech-section-banner">
            <div class="hitech-section-icon"><i class="bx bx-user-circle" style="color:#127464;"></i></div>
            <h6 class="hitech-section-title">Personal Information</h6>
          </div>
          <div class="row g-4 mb-5">
            <div class="col-md-4">
              <label class="form-label-hitech">First Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" name="firstName" value="{{ $user->first_name }}" required placeholder="e.g. Alex" {{ !auth()->user()->hasRole(['admin', 'hr', 'manager']) ? 'readonly' : '' }} />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Last Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" name="lastName" value="{{ $user->last_name }}" required placeholder="e.g. Saini" {{ !auth()->user()->hasRole(['admin', 'hr', 'manager']) ? 'readonly' : '' }} />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Date of Birth <span class="text-danger">*</span></label>
              <input type="date" name="dob" class="form-control form-control-hitech" value="{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : '' }}" required {{ !auth()->user()->hasRole(['admin', 'hr', 'manager']) ? 'readonly' : '' }} />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Gender <span class="text-danger">*</span></label>
              @if(auth()->user()->hasRole(['admin', 'hr', 'manager']))
                <select class="form-select form-select-hitech" id="gender" name="gender" required>
                  <option value="" disabled {{ !$user->gender ? 'selected' : '' }}>Select Gender</option>
                  @foreach(Gender::cases() as $gender)
                    <option value="{{$gender->value}}" {{$user->gender == $gender->value ? 'selected':''}} >{{ucfirst($gender->value)}}</option>
                  @endforeach
                </select>
              @else
                <input type="text" class="form-control form-control-hitech" value="{{ ucfirst($user->gender) }}" readonly />
                <input type="hidden" name="gender" value="{{ $user->gender }}">
              @endif
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Blood Group</label>
              <input type="text" class="form-control form-control-hitech" name="blood_group" value="{{ $user->blood_group }}" placeholder="e.g. O+" />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Marital Status</label>
              @if(auth()->user()->hasRole(['admin', 'hr', 'manager']))
                <select class="form-select form-select-hitech" id="maritalStatus" name="marital_status">
                  <option value="" disabled {{ !$user->marital_status ? 'selected' : '' }}>Select Marital Status</option>
                  <option value="single" {{ $user->marital_status == 'single' ? 'selected' : '' }}>Single</option>
                  <option value="married" {{ $user->marital_status == 'married' ? 'selected' : '' }}>Married</option>
                  <option value="divorced" {{ $user->marital_status == 'divorced' ? 'selected' : '' }}>Divorced</option>
                  <option value="widowed" {{ $user->marital_status == 'widowed' ? 'selected' : '' }}>Widowed</option>
                </select>
              @else
                <input type="text" class="form-control form-control-hitech" value="{{ ucfirst($user->marital_status) }}" readonly />
                <input type="hidden" name="marital_status" value="{{ $user->marital_status }}">
              @endif
            </div>
            @if(auth()->user()->hasRole(['admin', 'hr', 'manager']))
            <div class="col-md-4">
              <label class="form-label-hitech">Biometric ID (Machine ID)</label>
              <input type="text" class="form-control form-control-hitech" name="biometric_id" value="{{ $user->biometric_id }}" placeholder="e.g. 101" />
            </div>
            @else
            <input type="hidden" name="biometric_id" value="{{ $user->biometric_id }}">
            @endif
          </div>

          {{-- Section 2 --}}
          <div class="hitech-section-banner">
            <div class="hitech-section-icon"><i class="bx bx-group" style="color:#127464;"></i></div>
            <h6 class="hitech-section-title">Family &amp; Nationality</h6>
          </div>
          <div class="row g-4">
            <div class="col-md-4">
              <label class="form-label-hitech">Father's Name</label>
              <input type="text" name="father_name" class="form-control form-control-hitech" value="{{ $user->father_name }}" placeholder="e.g. John Doe Sr." />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Mother's Name</label>
              <input type="text" name="mother_name" class="form-control form-control-hitech" value="{{ $user->mother_name }}" placeholder="e.g. Jane Doe" />
            </div>
            <div class="col-md-4" id="marriedDiv" style="{{ $user->marital_status != 'married' ? 'display:none;' : '' }}">
              <label class="form-label-hitech">Spouse Name</label>
              <input type="text" name="spouse_name" class="form-control form-control-hitech" value="{{ $user->spouse_name }}" placeholder="e.g. Mary Doe" />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">No. of Children</label>
              <input type="number" name="no_of_children" class="form-control form-control-hitech" value="{{ $user->no_of_children }}" min="0" placeholder="e.g. 2" />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Birth Country</label>
              <input type="text" name="birth_country" class="form-control form-control-hitech" value="{{ $user->birth_country }}" placeholder="e.g. USA" />
            </div>
            <div class="col-md-4">
              <label class="form-label-hitech">Citizenship</label>
              <input type="text" name="citizenship" class="form-control form-control-hitech" value="{{ $user->citizenship }}" placeholder="e.g. American" />
            </div>
          </div>

          {{-- Utility Hidden Fields --}}
          <input type="hidden" name="email" value="{{ $user->email }}">
          <input type="hidden" name="phone" value="{{ $user->phone }}">
          <input type="hidden" name="altPhone" value="{{ $user->alternate_number }}">

          <div class="d-flex justify-content-end gap-3 mt-5">
            <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-save">
              Save Basic Info <i class="bx bx-check-circle ms-1"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const maritalStatus = document.getElementById('maritalStatus');
  const spouseName = document.querySelector('input[name="spouse_name"]');
  const noOfChildren = document.querySelector('input[name="no_of_children"]');
  const marriedDiv = document.getElementById('marriedDiv');

  function updateLockStatus() {
    const val = maritalStatus ? maritalStatus.value : null;
    if (val === 'single') {
      if (marriedDiv) marriedDiv.style.display = 'none';
      if (spouseName) {
        spouseName.value = '';
        spouseName.readOnly = true;
      }
      if (noOfChildren) {
        noOfChildren.value = '0';
        noOfChildren.readOnly = true;
      }
    } else {
      if (marriedDiv) marriedDiv.style.display = 'block';
      if (spouseName) {
        spouseName.readOnly = false;
      }
      if (noOfChildren) {
        noOfChildren.readOnly = false;
      }
    }
  }

  if (maritalStatus) {
    maritalStatus.addEventListener('change', updateLockStatus);
    updateLockStatus(); 
  }
});
</script>
<!-- /Edit Basic Info Modal -->
