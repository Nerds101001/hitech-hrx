<!-- Onboarding Invite Modal -->
<style>
  #onboardingInviteModal .hitech-input-group {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    padding-left: 10px !important;
  }
  #onboardingInviteModal .hitech-input-group .input-group-text {
    background: transparent !important;
    border: none !important;
    padding: 0 10px 0 0 !important;
    flex-shrink: 0 !important;
  }
  #onboardingInviteModal .hitech-input-group .select2-container {
    flex-grow: 1 !important;
    width: auto !important;
    min-width: 0 !important;
  }
  #onboardingInviteModal .select2-selection--single {
    border: none !important;
    background: transparent !important;
    height: 45px !important;
    display: flex !important;
    align-items: center !important;
  }
  #onboardingInviteModal .select2-container--default .select2-selection--single .select2-selection__rendered {
    padding-left: 0 !important;
    line-height: normal !important;
    color: #334155 !important;
  }
  #onboardingInviteModal .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 45px !important;
  }

  /* Select2 Dropdown Premium Styling */
  .select2-container--open .select2-dropdown {
    border: 1px solid rgba(0, 128, 128, 0.15) !important;
    border-radius: 12px !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
    overflow: hidden !important;
    z-index: 10000 !important;
    background: #fff !important;
  }

  .select2-results__option {
    padding: 10px 15px !important;
    font-size: 0.9rem !important;
    color: #334155 !important;
    background-color: #fff !important;
  }

  /* Fix: Force text color on hover/highlight */
  .select2-container--default .select2-results__option--highlighted[aria-selected],
  .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
    background-color: #008080 !important;
    color: #ffffff !important;
  }

  .select2-container--default .select2-results__option[aria-selected=true] {
    background-color: rgba(0, 128, 128, 0.1) !important;
    color: #008080 !important;
    font-weight: 600 !important;
  }

  /* Search box styling */
  .select2-search--dropdown .select2-search__field {
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 8px 12px !important;
  }
</style>
<div class="modal fade" id="onboardingInviteModal" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <h5 class="modal-title modal-title-hitech d-flex align-items-center gap-3">
           <i class="bx bx-paper-plane fs-4"></i>
           Invite for Onboarding
        </h5>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
           <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body p-sm-5 p-4">
        <form id="onboardingInviteForm" class="invite-form" action="{{ route('employees.initiateOnboarding') }}" method="POST">
          @csrf
          <div class="row g-6">
            <!-- Personal Info Section -->
            <div class="col-md-6">
              <label class="form-label-hitech">First Name <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-user"></i></span>
                <input type="text" name="firstName" class="form-control form-control-hitech" placeholder="Enter first name" required>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Last Name <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-user"></i></span>
                <input type="text" name="lastName" class="form-control form-control-hitech" placeholder="Enter last name" required>
              </div>
            </div>
            
            <div class="col-md-6">
              <label class="form-label-hitech">Email Address <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                <input type="email" name="email" class="form-control form-control-hitech" placeholder="email@example.com" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Phone Number <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-phone"></i></span>
                <input type="text" name="phone" class="form-control form-control-hitech" placeholder="10-digit mobile" required maxlength="10">
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Employee ID</label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-id-card"></i></span>
                <input type="text" name="employeeCode" class="form-control form-control-hitech" placeholder="Auto-generated if left blank">
              </div>
            </div>

            <div class="col-12"><hr class="my-1 border-light opacity-50"></div>

            <!-- Job Info Section -->
            <div class="col-md-6">
              <label class="form-label-hitech">Assigned Role <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-shield-quarter"></i></span>
                <select name="role" class="form-select select2" required>
                  <option value="">Select Role</option>
                  @foreach($roles as $role)
                    <option value="{{ $role->name }}">{{ ucwords(str_replace(['_', '-'], ' ', $role->name)) }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Department <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-buildings"></i></span>
                <select name="departmentId" class="form-select select2" required>
                  <option value="">Select Department</option>
                  @forelse($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                  @empty
                    <option value="">No active departments in DB</option>
                  @endforelse
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Designation <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-briefcase"></i></span>
                <select name="designationId" class="form-select select2" required>
                  <option value="">Select Designation</option>
                  @foreach($designations as $designation)
                    <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Reporting Manager <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-user-voice"></i></span>
                <select name="reportingToId" class="form-select select2" required>
                  <option value="">Select Manager</option>
                  @foreach($managers as $manager)
                    <option value="{{ $manager->id }}">{{ $manager->name }} ({{ $manager->code }})</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Joining Date <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-calendar"></i></span>
                <input type="date" name="doj" class="form-control form-control-hitech" id="onboarding_doj" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Annual CTC (₹)</label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-rupee"></i></span>
                <input type="number" name="baseSalary" class="form-control form-control-hitech" placeholder="Optional CTC">
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech">Probation Period (Months) <span class="text-danger">*</span></label>
              <div class="input-group input-group-merge hitech-input-group">
                <span class="input-group-text"><i class="bx bx-time-five"></i></span>
                <input type="number" name="probationPeriodMonths" class="form-control form-control-hitech" value="6" required min="0" max="24">
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-3 mt-4 pt-2">
            <button type="button" class="btn btn-label-danger px-6 rounded-pill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" id="onboardingSubmitBtn" class="btn btn-hitech px-8 rounded-pill fw-bold">Send Invitation</button>
          </div>
        </form>
      </div>
    </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('onboardingInviteForm');
    const submitBtn = document.getElementById('onboardingSubmitBtn');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // UI State: Loading
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Sending...';
            
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(async response => {
                const data = await response.json();
                if (response.ok) {
                    // Success
                    bootstrap.Modal.getInstance(document.getElementById('onboardingInviteModal')).hide();
                    
                    // Populate Success Modal
                    document.getElementById('successDetailsEmail').innerText = formData.get('email');
                    document.getElementById('successDetailsPass').innerText = data.plain_password || '********';
                    
                    const successModal = new bootstrap.Modal(document.getElementById('onboardingSuccessDetailsModal'));
                    successModal.show();
                    
                } else {
                    // Validation Errors
                    let errorMsg = data.message || 'Something went wrong. Please check your input.';
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0][0];
                        errorMsg = firstError;
                    }
                    
                    if (window.showErrorSwal) {
                        window.showErrorSwal(errorMsg);
                    } else if (window.showErrorToast) {
                        window.showErrorToast(errorMsg);
                    } else {
                        alert(errorMsg);
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                if (window.showErrorSwal) {
                    window.showErrorSwal('Failed to connect to server. Please try again.');
                } else {
                    alert('Network error. Please try again.');
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            });
        });
    }
});
</script>

<!-- PREMIUM ONBOARDING SUCCESS MODAL -->
<div class="modal fade" id="onboardingSuccessDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
      <div class="modal-body p-0">
        <div class="p-6 text-center" style="background: linear-gradient(135deg, #004D54 0%, #008080 100%);">
            <div class="mb-4 animate__animated animate__bounceIn">
                <i class="bx bx-check-circle text-white" style="font-size: 5rem;"></i>
            </div>
            <h3 class="text-white fw-bold mb-1">Invitation Sent!</h3>
            <p class="text-white opacity-75 small mb-0">The candidate has been registered in the system.</p>
        </div>
        
        <div class="p-6 bg-white">
            <div class="mb-4">
                <label class="text-uppercase small fw-bold text-muted mb-2 d-block">Login Credentials</label>
                <div class="p-4 rounded-4" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <span class="text-muted small">Username/Email</span>
                        <span class="fw-bold text-dark" id="successDetailsEmail">---</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-muted small">Temporary Password</span>
                        <code class="fw-bold fs-5 text-primary" id="successDetailsPass" style="letter-spacing: 2px;">---</code>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info border-0 rounded-4 d-flex align-items-start gap-3 mb-0" style="background: rgba(0, 77, 84, 0.05);">
                <i class="bx bx-info-circle fs-4 text-primary"></i>
                <div class="small">
                    <p class="mb-1 fw-bold text-primary">Important Task</p>
                    <p class="mb-0 text-muted">Please copy these credentials or inform the employee. They will also receive an automated email invitation.</p>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-top bg-light-soft d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bx bx-shield-quarter text-muted small"></i>
                <span class="text-uppercase text-muted fw-bold" style="font-size: 9px; letter-spacing: 1px;">Protocol: SYSTEM_INVITATION_DEPLOYED</span>
            </div>
            <button type="button" class="btn btn-hitech px-6 rounded-pill" onclick="window.location.reload()">Great, Done</button>
        </div>
      </div>
    </div>
  </div>
</div>


