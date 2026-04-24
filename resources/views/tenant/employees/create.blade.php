@php
  use App\Enums\Gender;
  use App\Helpers\StaticDataHelpers;
  use App\Services\AddonService\IAddonService;use Nwidart\Modules\Facades\Module;
  $banks = StaticDataHelpers::getIndianBanksList();
  $addonService = app(IAddonService::class);
@endphp
@extends('layouts/layoutMaster')

@section('title', 'Create Employee')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/bs-stepper/bs-stepper.scss',
    'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/bs-stepper/bs-stepper.js',
    'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',

  ])
@endsection

<!-- Page Scripts -->
@section('page-script')
  @vite([
    'resources/assets/js/app/employee-create-validation.js',
    'resources/assets/js/app/employee-create.js',
  ])
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
          integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
    $(function () {
      $('#attendanceType').on('change', function () {
        var value = this.value;
        console.log(value);

        $('#ipGroupDiv').hide();
        $('#ipGroupId').val('');
        $('#qrGroupDiv').hide();
        $('#qrGroupId').val('');
        $('#dynamicQrDiv').hide();
        $('#dynamicQrId').val('');
        $('#siteId').val('');
        $('#siteDiv').hide();
        $('#geofenceGroupId').val('');
        $('#geofenceGroupDiv').hide();

        if (value === 'geofence') {
          $('#geofenceGroupDiv').show();
          getGeofenceGroups();
        } else if (value === 'ipAddress') {
          $('#ipGroupDiv').show();
          getIpGroups();
        } else if (value === 'staticqr') {
          $('#qrGroupDiv').show();
          getQrGroups();
        } else if (value == 'site') {
          $('#siteDiv').show();
          getSites();
        } else if (value == 'dynamicqr') {
          $('#dynamicQrDiv').show();
          getDynamicQrDevices();
        }else {
          $('#geofenceGroupDiv').hide();
          $('#ipGroupDiv').hide();
          $('#qrGroupDiv').hide();
          $('#dynamicQrDiv').hide();
          $('#siteDiv').hide();
        }
      });
    });

    function getDynamicQrDevices() {
      $.ajax({
        url: '{{route('employee.getDynamicQrDevices')}}',
        type: 'GET',
        success: function (response) {
          if (response.length === 0) {
            showErrorToast('Please create a dynamic qr device first');
            return;
          }
          var options = '<option value="">Please select a dynamic qr device</option>';
          response.forEach(function (item) {
            options += '<option value="' + item.id + '">' + item.name + '</option>';
          });
          $('#dynamicQrId').html(options);
        },
        error: function (error) {
          console.log(error);
        }
      });
    }

    function getGeofenceGroups() {
      $.ajax({
        url: '{{route('employee.getGeofenceGroups')}}',
        type: 'GET',
        success: function (response) {
          if (response.length === 0) {
            showErrorToast('Please create a geofence group first');
            return;
          }
          var options = '<option value="">Please select a geofence group</option>';
          response.forEach(function (item) {
            options += '<option value="' + item.id + '">' + item.name + '</option>';
          });
          $('#geofenceGroupId').html(options);
        },
        error: function (error) {
          console.log(error);
        }
      });
    }

    function getIpGroups() {
      $.ajax({
        url: '{{route('employee.getIpGroups')}}',
        type: 'GET',
        success: function (response) {
          if (response.length === 0) {
            showErrorToast('Please create a ip group first');
            return;
          }
          var options = '<option value="">Please select a ip group</option>';
          response.forEach(function (item) {
            options += '<option value="' + item.id + '">' + item.name + '</option>';
          });
          $('#ipGroupId').html(options);
        },
        error: function (error) {
          console.log(error);
        }
      });
    }

    function getQrGroups() {
      $.ajax({
        url: '{{route('employee.getQrGroups')}}',
        type: 'GET',
        success: function (response) {
          if (response.length === 0) {
            showErrorToast('Please create a qr group first');
            return;
          }
          var options = '<option value="">Please select a qr group</option>';
          response.forEach(function (item) {
            options += '<option value="' + item.id + '">' + item.name + '</option>';
          });
          $('#qrGroupId').html(options);
        },
        error: function (error) {
          console.log(error);
        }
      });
    }

    function getSites() {
      $.ajax({
        url: '{{route('employee.getSites')}}',
        type: 'GET',
        success: function (response) {
          if (response.length === 0) {
            showErrorToast('Please create a site first');
            return;
          }
          var options = '<option value="">Please select a site</option>';
          response.forEach(function (item) {
            options += '<option value="' + item.id + '">' + item.name + '</option>';
          });
          $('#siteId').html(options);
        },
        error: function (error) {
          console.log(error);
        }
      });
    }
  </script>
@endsection
@section('content')

  <!-- Premium HITECH Header -->
  <div class="hitech-page-hero animate__animated animate__fadeInDown mb-6">
    <div class="hitech-page-hero-text">
        <h2 class="greeting">Employee Creation</h2>
        <p class="sub-text">Onboard new talent with our sophisticated management system.</p>
    </div>
    <div class="hitech-page-hero-meta">
        <div class="hero-quick-stat">
            <div class="stat-value text-white">{{ count($users) }}</div>
            <div class="stat-label">Total Staff</div>
        </div>
    </div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger hitech-alert animate__animated animate__shakeX mb-6" role="alert">
      <div class="d-flex align-items-center">
        <i class="bx bx-error-circle fs-4 me-3"></i>
        <div class="alert-message">
          <h6 class="alert-heading mb-1">Registration Error</h6>
          @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
          @endforeach
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  @endif

  <div class="col-12 mb-6">
    <div id="wizard-validation" class="bs-stepper hitech-stepper mt-2 animate__animated animate__fadeInUp">
      <div class="bs-stepper-header">
        <div class="step" data-target="#personal-details-validation">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-circle"><i class="bx bx-user fs-4"></i></span>
            <span class="bs-stepper-label">
            <span class="bs-stepper-title">Personal Info</span>
            <small class="bs-stepper-subtitle text-muted">Basic identity</small>
          </span>
          </button>
        </div>
        <div class="line">
          <i class="bx bx-chevron-right text-muted fs-4"></i>
        </div>
        <div class="step" data-target="#employee-info-validation">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-circle"><i class="bx bx-briefcase-alt-2 fs-4"></i></span>
            <span class="bs-stepper-label">
            <span class="bs-stepper-title">Job Details</span>
            <small class="bs-stepper-subtitle text-muted">Position & Role</small>
          </span>
          </button>
        </div>
        <div class="line">
          <i class="bx bx-chevron-right text-muted fs-4"></i>
        </div>
        <div class="step" data-target="#salary-validation">
          <button type="button" class="step-trigger">
            <span class="bs-stepper-circle"><i class="bx bx-wallet fs-4"></i></span>
            <span class="bs-stepper-label">
            <span class="bs-stepper-title">Compensation</span>
            <small class="bs-stepper-subtitle text-muted">Salary & Benefits</small>
          </span>
          </button>
        </div>
      </div>
      <div class="bs-stepper-content">
        <form id="wizard-validation-form" method="post" action="{{route('employees.store')}}"
              enctype="multipart/form-data"
              onSubmit="return false">
          @csrf
          <!-- Personal details -->
          <div id="personal-details-validation" class="content">
            <div class="content-header mb-4">
              <h4 class="hitech-form-section-title">Personal Information</h4>
              <p class="text-muted small">Enter identity and contact details for the new staff member.</p>
            </div>
            <div class="row g-6">
              <div class="col-sm-6">
                <label class="form-label-hitech" for="file">Profile Picture</label>
                <input type="file" name="file" id="file" class="form-control hitech-input-group"/>
                <span class="text-muted small mt-1 d-block">Supported formats: JPG, PNG (Max 5MB)</span>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="firstName">First Name <span class="text-danger">*</span> </label>
                <input type="text" name="firstName" id="firstName" class="form-control hitech-input-group"
                       placeholder="e.g. John"/>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="lastName">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="lastName" id="lastName" class="form-control hitech-input-group"
                       placeholder="e.g. Doe"/>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="gender">Gender <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="gender" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="gender">
                  <option value="" selected disabled>Select Gender</option>
                  @foreach(Gender::cases() as $gender)
                    <option value="{{$gender->value}}" data-icon="bx-{{ $gender->value == 'male' ? 'male-sign' : ($gender->value == 'female' ? 'female-sign' : 'user') }}">{{ucfirst($gender->value)}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="phone">Phone Number <span class="text-danger">*</span></label>
                <div class="input-group hitech-input-group">
                    <span class="input-group-text bg-transparent border-0"><i class="bx bx-phone"></i></span>
                    <input type="number" name="phone" id="phone" class="form-control border-0 bg-transparent ps-0" placeholder="10-digit number"/>
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="altPhone">Alternative Mobile No</label>
                <div class="input-group hitech-input-group">
                    <span class="input-group-text bg-transparent border-0"><i class="bx bx-mobile-alt"></i></span>
                    <input type="number" name="altPhone" id="altPhone" class="form-control border-0 bg-transparent ps-0" placeholder="Optional contact"/>
                </div>
              </div>
              <div class="col-sm-12 col-md-6">
                <label class="form-label-hitech" for="email">Email Address <span class="text-danger">*</span></label>
                <div class="input-group hitech-input-group">
                    <span class="input-group-text bg-transparent border-0"><i class="bx bx-envelope"></i></span>
                    <input type="email" name="email" id="email" class="form-control border-0 bg-transparent ps-0" placeholder="john.doe@example.com"/>
                </div>
              </div>
              <div class="col-sm-12 col-md-6">
                <label class="form-label-hitech" for="role">Access Role <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="role" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="role">
                  <option value="" selected disabled>Assign a Role</option>
                  @foreach ($roles as $role)
                    <option value="{{$role->name}}">{{$role->display_name}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-sm-12 col-md-6">
                <label class="form-label-hitech" for="dob">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" name="dob" id="dob" class="form-control hitech-input-group"/>
              </div>
              <div class="col-sm-12 col-md-6">
                <label class="form-label-hitech" for="address">Residential Address</label>
                <textarea name="address" id="address" class="form-control hitech-input-group" rows="1"
                          placeholder="Full residential address"></textarea>
              </div>
              <div class="col-12 mt-4">
                <div class="form-check form-switch hitech-switch">
                  <input class="form-check-input" type="checkbox" id="useDefaultPassword" name="useDefaultPassword"
                         checked>
                  <label class="form-check-label fw-bold" for="useDefaultPassword">@lang('Use Default Password')</label>
                </div>
              </div>
              <div class="row g-6 mb-4 mt-1" id="passwordDiv" style="display: none;">
                <div class="col-sm-6">
                  <label class="form-label-hitech" for="password">System Password <span class="text-danger">*</span></label>
                  <input type="password" name="password" id="password" class="form-control hitech-input-group"
                         placeholder="••••••••"/>
                </div>
                <div class="col-sm-6">
                  <label class="form-label-hitech" for="confirmPassword">Confirm Password <span
                      class="text-danger">*</span></label>
                  <input type="password" name="confirmPassword" id="confirmPassword" class="form-control hitech-input-group"
                         placeholder="••••••••"/>
                </div>
              </div>

              @if($settings->is_helper_text_enabled)
                <div class="col-12 mt-4">
                  <div class="alert hitech-note-card animate__animated animate__fadeIn" role="alert">
                    <h6 class="alert-heading"><i class="bx bx-shield-quarter fs-4"></i> Security Note</h6>
                    <p class="mb-0">If "Use Default Password" is active, the system will assign:
                      <span class="badge bg-label-primary px-3 py-2 ms-1 font-monospace">{{$settings->default_password}}</span>
                    </p>
                  </div>
                </div>
              @endif
            </div>
            <div class="col-12 d-flex justify-content-between mt-6">
              <button class="btn btn-label-secondary btn-prev px-4" disabled>
                <i class="bx bx-left-arrow-alt bx-sm ms-sm-n2 me-sm-2"></i>
                <span class="align-middle d-sm-inline-block d-none">Previous</span>
              </button>
              <button class="btn btn-hitech btn-next px-4">
                <span class="align-middle d-sm-inline-block d-none me-sm-2">Next Step</span>
                <i class="bx bx-chevron-right bx-sm me-sm-n2"></i>
              </button>
            </div>
          </div>

          <!-- employment details -->
          <div id="employee-info-validation" class="content">
            <div class="content-header mb-4">
              <h4 class="hitech-form-section-title">Work Details</h4>
              <p class="text-muted small">Configure the employee's professional role and attendance settings.</p>
            </div>
            <div class="row g-6">
              <div class="col-sm-6">
                <label class="form-label-hitech" for="code">Employee ID/Code <span class="text-danger">*</span></label>
                <div class="input-group hitech-input-group">
                    <span class="input-group-text bg-transparent border-0"><i class="bx bx-id-card"></i></span>
                    <input type="text" name="code" id="code" class="form-control border-0 bg-transparent ps-0" placeholder="e.g. EMP001"/>
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="doj">Date of Joining <span class="text-danger">*</span></label>
                <input type="date" id="doj" name="doj" class="form-control hitech-input-group"/>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="designationId">Designation <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="designationId" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="designationId">
                  <option value="" selected disabled>Select Designation</option>
                  @foreach ($designations as $designation)
                    <option value="{{$designation->id}}">{{$designation->name}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="teamId">Assigned Team <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="teamId" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="teamId">
                  <option value="" selected disabled>Select Team</option>
                  @foreach ($teams as $team)
                    <option value="{{$team->id}}">{{$team->code}} - {{$team->name}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="shiftId">Working Shift <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="shiftId" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="shiftId">
                  <option value="" selected disabled>Select Shift</option>
                  @foreach ($shifts as $shift)
                    <option value="{{$shift->id}}">{{$shift->code}} - {{$shift->name}}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="reportingToId">Reporting Manager <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="reportingToId" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="reportingToId">
                  <option value="" selected disabled>Select Manager</option>
                  @foreach ($users as $user)
                    <option value="{{$user->id}}" data-subtext="{{$user->code}}">{{$user->first_name.' '.$user->last_name}}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-sm-6">
                <label class="form-label-hitech" for="leavePolicyProfileId">Leave Policy Profile <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="leavePolicyProfileId" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="leavePolicyProfileId">
                  <option value="" selected disabled>Select Leave Policy</option>
                  @foreach ($leavePolicyProfiles as $profile)
                    <option value="{{$profile->id}}">{{$profile->name}}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-sm-6">
                <label class="form-label-hitech" for="attendanceType">Tracking Method <span
                    class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="attendanceType" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="attendanceType">
                  <option value="open" selected>Open Gateway</option>
                  @if($addonService->isAddonEnabled(ModuleConstants::GEOFENCE))
                    <option value="geofence" data-icon="bx-map-alt">Geofence Verified</option>
                  @endif
                  @if($addonService->isAddonEnabled(ModuleConstants::IP_ADDRESS_ATTENDANCE))
                    <option value="ipAddress" data-icon="bx-network-chart">IP Restricted</option>
                  @endif
                  @if($addonService->isAddonEnabled(ModuleConstants::QR_ATTENDANCE))
                    <option value="staticqr" data-icon="bx-qr-scan">Static QR</option>
                  @endif
                  @if($addonService->isAddonEnabled(ModuleConstants::DYNAMIC_QR_ATTENDANCE))
                    <option value="dynamicqr" data-icon="bx-qr">Dynamic QR Device</option>
                  @endif
                  @if($addonService->isAddonEnabled(ModuleConstants::SITE_ATTENDANCE))
                    <option value="site" data-icon="bx-buildings">Site Specific</option>
                  @endif
                  <option value="face" data-icon="bx-user-check">Facial Recognition</option>
                </select>
              </div>

              <div class="col-sm-6">
                <label class="form-label-hitech" for="work_type">Work Environment <span class="text-danger">*</span></label>
                <select class="select2 w-100 hitech-input-group" id="work_type" data-style="btn-default"
                        data-icon-base="bx" data-tick-icon="bx-check text-success" name="work_type">
                  <option value="office" selected data-icon="bx-buildings">On Premise / Office</option>
                  <option value="field" data-icon="bx-map-pin">Field Job / Site</option>
                  <option value="wfh" data-icon="bx-home">Work From Home</option>
                  <option value="hybrid" data-icon="bx-refresh">Hybrid Mode</option>
                </select>
              </div>
              <div class="form-group col-sm-6 mb-3" id="geofenceGroupDiv" style="display:none;">
                <label for="geofenceGroupId" class="control-label">Geofence Group</label>
                <select id="geofenceGroupId" name="geofenceGroupId" class="form-select mb-3"></select>
                <span class="text-danger">{{ $errors->first('geofenceGroupId', ':message') }}</span>
              </div>
              <div class="form-group col-sm-6 mb-3" id="ipGroupDiv" style="display:none;">
                <label for="ipGroupId" class="control-label">Ip Group</label>
                <select id="ipGroupId" name="ipGroupId" class="form-select mb-3"></select>
                <span class="text-danger">{{ $errors->first('ipGroupId', ':message') }}</span>
              </div>
              <div class="form-group col-sm-6 mb-3" id="dynamicQrDiv" style="display:none;">
                <label for="dynamicQrId" class="control-label">Qr Device</label>
                <select id="dynamicQrId" name="dynamicQrId" class="form-select mb-3"></select>
                <span class="text-danger">{{ $errors->first('dynamicQrId', ':message') }}</span>
              </div>
              <div class="form-group col-sm-6 mb-3" id="qrGroupDiv" style="display:none;">
                <label for="qrGroupId" class="control-label">Qr Group</label>
                <select id="qrGroupId" name="qrGroupId" class="form-select mb-3"></select>
                <span class="text-danger">{{ $errors->first('qrGroupId', ':message') }}</span>
              </div>
              <div class="form-group col-md-3 mb-3" id="siteDiv" style="display:none;">
                <label for="siteId" class="control-label">Site</label>
                <select id="siteId" name="siteId" class="form-select mb-3"></select>
                <span class="text-danger">{{ $errors->first('siteId', ':message') }}</span>
              </div>
              @if($settings->is_helper_text_enabled)
                <div class="col-12 mt-4">
                  <div class="alert hitech-note-card animate__animated animate__fadeIn" role="alert">
                    <h6 class="alert-heading"><i class="bx bx-info-circle fs-4"></i> Tracking Overview</h6>
                    <div class="row small g-2 mt-2">
                        <div class="col-md-6"><strong>• Open Gateway:</strong> No restrictions.</div>
                        <div class="col-md-6"><strong>• Geofence:</strong> Location verified.</div>
                        <div class="col-md-6"><strong>• IP Restricted:</strong> Network verified.</div>
                        <div class="col-md-6"><strong>• QR/Dynamic:</strong> Physical device scan.</div>
                        <div class="col-md-6"><strong>• Site/Face:</strong> Advanced verification.</div>
                    </div>
                  </div>
                </div>
              @endif

              <div class="col-12 d-flex justify-content-between mt-6">
                <button class="btn btn-label-secondary btn-prev px-4">
                  <i class="bx bx-left-arrow-alt bx-sm ms-sm-n2 me-sm-2"></i>
                  <span class="align-middle d-sm-inline-block d-none">Previous</span>
                </button>
                <button class="btn btn-hitech btn-next px-4">
                  <span class="align-middle d-sm-inline-block d-none me-sm-2">Next Step</span>
                  <i class="bx bx-chevron-right bx-sm me-sm-n2"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Compensation -->
          <div id="salary-validation" class="content">
            <div class="content-header mb-4">
              <h4 class="hitech-form-section-title">Compensation & Benefits</h4>
              <p class="text-muted small">Define baseline salary and initial leave entitlement.</p>
            </div>
            <div class="row g-6">
              <div class="col-sm-6">
                <label class="form-label-hitech" for="baseSalary">Base Salary <span class="text-danger">*</span></label>
                <div class="input-group hitech-input-group">
                    <span class="input-group-text bg-transparent border-0"><i class="bx bx-rupee"></i></span>
                    <input type="number" name="baseSalary" id="baseSalary" class="form-control border-0 bg-transparent ps-0" placeholder="e.g. 25000"/>
                </div>
              </div>
              <div class="col-sm-6">
                <label class="form-label-hitech" for="availableLeaveCount">Initial Leave Balance</label>
                <div class="input-group hitech-input-group">
                    <span class="input-group-text bg-transparent border-0"><i class="bx bx-calendar-plus"></i></span>
                    <input type="number" name="availableLeaveCount" id="availableLeaveCount" class="form-control border-0 bg-transparent ps-0" placeholder="e.g. 12"/>
                </div>
              </div>
              <div class="col-12 d-flex justify-content-between mt-6">
                <button class="btn btn-label-secondary btn-prev px-4">
                  <i class="bx bx-left-arrow-alt bx-sm ms-sm-n2 me-sm-2"></i>
                  <span class="align-middle d-sm-inline-block d-none">Previous</span>
                </button>
                <button class="btn btn-hitech btn-next btn-submit px-5 py-2">
                    <i class="bx bx-check-circle me-1"></i> Finalize & Create
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection


