@extends('layouts/layoutMaster')

@section('title', 'Convert Candidate to Employee')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .em-card {
      border: 1px solid rgba(0, 0, 0, 0.05);
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .card-header {
      background: rgba(var(--bs-primary-rgb), 0.05);
      border-bottom: 1px solid rgba(0,0,0,0.05);
      padding: 1.25rem 1.5rem;
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/flatpickr/flatpickr.js'
  ])
@endsection

@section('page-script')
  <script>
    $(document).ready(function() {
      // Initialize Select2
      $('.select2').select2();

      // Initialize Flatpickr
      $('.datepicker').flatpickr({
        altInput: true,
        altFormat: "F j, Y",
        dateFormat: "Y-m-d",
      });

      // Handle Department -> Designation filtering
      const designations = @json($designations);
      
      $(document).on('change', '#department_id', function() {
        const departmentId = $(this).val();
        const designationSelect = $('#designation_id');
        
        designationSelect.empty().append('<option value="">Select Designation</option>');
        
        if (departmentId) {
          const filtered = designations.filter(d => d.department_id == departmentId);
          filtered.forEach(d => {
            designationSelect.append(`<option value="${d.id}">${d.name}</option>`);
          });
        }
        designationSelect.trigger('change');
      });
    });
  </script>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div>
      <h3 class="mb-1 fw-bold text-heading">Convert to Employee</h3>
      <p class="text-muted mb-0">Bridging <strong>{{ $jobOnBoard->applications->name ?? 'Candidate' }}</strong> into the company roster.</p>
    </div>
    <a href="{{ route('job.on.board') }}" class="btn btn-label-secondary shadow-sm">
      <i class="bx bx-arrow-back me-1"></i>Back to On-Boarding
    </a>
  </div>

  <div class="px-4">
        <form action="{{ route('job.on.board.convert', $jobOnBoard->id) }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    @csrf
    
    <div class="row g-6">
      {{-- Personal Details --}}
      <div class="col-md-6">
        <div class="card hitech-card-white em-card h-100">
          <div class="card-header d-flex align-items-center">
            <i class="bx bx-user-circle me-2 text-primary fs-4"></i>
            <h5 class="mb-0 fw-bold">Personal Details</h5>
          </div>
          <div class="card-body pt-5">
            <div class="row g-4">
              <div class="col-md-12">
                <label class="form-label fw-bold small text-muted text-uppercase">Full Name <span class="text-danger">*</span></label>
                <input class="form-control" name="name" type="text" value="{{ $jobOnBoard->applications->name ?? '' }}" required placeholder="Enter full name">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Email Address <span class="text-danger">*</span></label>
                <input class="form-control" name="email" type="email" value="{{ $jobOnBoard->applications->email ?? '' }}" required placeholder="email@example.com">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Phone Number <span class="text-danger">*</span></label>
                <input class="form-control" name="phone" type="text" value="{{ $jobOnBoard->applications->phone ?? '' }}" required placeholder="+1 234 567 890">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Date of Birth <span class="text-danger">*</span></label>
                <input class="form-control datepicker" name="dob" type="text" value="{{ $jobOnBoard->applications->dob ?? '' }}" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Gender <span class="text-danger">*</span></label>
                <div class="pt-2">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="gender" id="gender_male" value="Male" {{ (!empty($jobOnBoard->applications) && $jobOnBoard->applications->gender == 'Male') ? 'checked' : '' }}>
                    <label class="form-check-label" for="gender_male">Male</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="gender" id="gender_female" value="Female" {{ (!empty($jobOnBoard->applications) && $jobOnBoard->applications->gender == 'Female') ? 'checked' : '' }}>
                    <label class="form-check-label" for="gender_female">Female</label>
                  </div>
                </div>
              </div>
              <div class="col-md-12">
                <label class="form-label fw-bold small text-muted text-uppercase">Account Password <span class="text-danger">*</span></label>
                <input class="form-control" name="password" type="password" required placeholder="Set temporary password">
                <small class="text-muted">User will be prompted to change this upon first login.</small>
              </div>
              <div class="col-md-12">
                <label class="form-label fw-bold small text-muted text-uppercase">Residential Address <span class="text-danger">*</span></label>
                <textarea class="form-control" name="address" rows="3" required placeholder="Enter permanent address"></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Company Details --}}
      <div class="col-md-6">
        <div class="card hitech-card-white em-card h-100">
          <div class="card-header d-flex align-items-center">
            <i class="bx bx-buildings me-2 text-primary fs-4"></i>
            <h5 class="mb-0 fw-bold">Company Details</h5>
          </div>
          <div class="card-body pt-5">
            <div class="row g-4">
              <div class="col-md-12">
                <label class="form-label fw-bold small text-muted text-uppercase">Employee ID</label>
                <input class="form-control bg-light" name="employee_id" type="text" value="{{ $employeesId ?? 'AUTO-GEN' }}" disabled>
              </div>
              <div class="col-md-12">
                <label class="form-label fw-bold small text-muted text-uppercase">Branch / Site <span class="text-danger">*</span></label>
                <select class="form-select select2" name="branch_id" required>
                    <option value="">{{ __('Select Site') }}</option>
                    @foreach($branches as $key => $val)
                        <option value="{{ $key }}">{{ $val }}</option>
                    @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Department <span class="text-danger">*</span></label>
                <select class="form-select select2" id="department_id" name="department_id" required>
                    <option value="">{{ __('Select Department') }}</option>
                    @foreach($departments as $key => $val)
                        <option value="{{ $key }}">{{ $val }}</option>
                    @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Designation <span class="text-danger">*</span></label>
                <select name="designation_id" id="designation_id" class="form-select select2" required>
                  <option value="">Select Designation</option>
                </select>
              </div>
              <div class="col-md-12">
                <label class="form-label fw-bold small text-muted text-uppercase">Date of Joining <span class="text-danger">*</span></label>
                <input class="form-control datepicker" name="company_doj" type="text" value="{{ $jobOnBoard->joining_date }}" required>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Bank Account Details --}}
      <div class="col-md-6">
        <div class="card hitech-card-white em-card h-100">
          <div class="card-header d-flex align-items-center">
            <i class="bx bx-credit-card me-2 text-primary fs-4"></i>
            <h5 class="mb-0 fw-bold">Bank Account Details</h5>
          </div>
          <div class="card-body pt-5">
            <div class="row g-4">
              <div class="col-md-12">
                <label class="form-label fw-bold small text-muted text-uppercase">Account Holder Name</label>
                <input class="form-control" name="account_holder_name" type="text" placeholder="Name as per bank">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Account Number</label>
                <input class="form-control" name="account_number" type="text" placeholder="Bank account number">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Bank Name</label>
                <input class="form-control" name="bank_name" type="text" placeholder="e.g. Chase, HSBC">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Bank Identifier (IFSC/SWIFT)</label>
                <input class="form-control" name="bank_identifier_code" type="text" placeholder="BIC/SWIFT Code">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">Branch Location</label>
                <input class="form-control" name="branch_location" type="text" placeholder="City/Branch name">
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Documents --}}
      <div class="col-md-6">
        <div class="card hitech-card-white em-card h-100">
          <div class="card-header d-flex align-items-center">
            <i class="bx bx-file me-2 text-primary fs-4"></i>
            <h5 class="mb-0 fw-bold">Required Documents</h5>
          </div>
          <div class="card-body pt-5">
            <div class="row g-4">
              @foreach ($documents as $key => $document)
                <div class="col-12 pb-3 border-bottom border-light last-child-border-0">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold small text-muted text-uppercase mb-0">
                      {{ $document->name }} {!! $document->is_required == 1 ? '<span class="text-danger">*</span>' : '' !!}
                    </label>
                  </div>
                  <input type="hidden" name="emp_doc_id[{{ $document->id }}]" value="{{ $document->id }}">
                  <input type="file" name="document[{{ $document->id }}]" class="form-control" {{ $document->is_required == 1 ? 'required' : '' }}>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      {{-- Action Buttons --}}
      <div class="col-12 text-end mb-10">
        <hr class="my-6">
        <a href="{{ route('job.on.board') }}" class="btn btn-label-secondary me-2">Cancel</a>
        <button type="submit" class="btn btn-hitech-primary px-8 shadow-sm">
          <i class="bx bx-user-plus me-1"></i>Finalize Conversion
        </button>
      </div>
    </div>
    
    </form>
  </div>
</div>
@endsection
