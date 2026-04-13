<!-- Onboarding Invite Modal -->
<div class="modal fade" id="onboardingInviteModal" tabindex="-1" aria-hidden="true">
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
                <select name="teamId" class="form-select select2" required>
                  <option value="">Select Team</option>
                  @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                  @endforeach
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
                <input type="date" name="doj" class="form-control form-control-hitech" min="{{ date('Y-m-d') }}" required>
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
            <button type="submit" class="btn btn-hitech px-8 rounded-pill fw-bold">Send Invitation</button>
          </div>
        </form>
      </div>
    </div>
    </div>
  </div>
</div>


