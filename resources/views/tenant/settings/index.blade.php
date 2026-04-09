@php
  use App\Models\SuperAdmin\SaSettings;
  use App\Services\AddonService\IAddonService;
  use App\Models\PayrollAdjustment;
  $addonService = app(IAddonService::class);
  $settings = SaSettings::first();
  $payrollAdjustments = PayrollAdjustment::all();
@endphp
@extends('layouts/layoutMaster')

@section('title', __('Settings Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/apex-charts/apexcharts.js'
  ])
@endsection

@section('page-script')
  @vite(['resources/js/main-helper.js'])
  @vite(['resources/assets/js/app/settings-index.js'])
@endsection

@section('content')
<div class="row g-6 px-4">
  <!-- Hero Banner -->
  <div class="col-lg-12">
    <x-hero-banner 
      title="Settings Management" 
      subtitle="Configure system settings and preferences"
      icon="bx-cog"
      gradient="info"
    />
  </div>

  <!-- Stats Cards -->
  <x-stat-card 
    title="System Health" 
    value="{{ $systemHealth ?? 95 }}%" 
    icon="bx-check-shield" 
    color="success"
    animation-delay="0.1s"
  />
  
  <x-stat-card 
    title="Active Modules" 
    value="{{ $activeModules ?? 0 }}" 
    icon="bx-package" 
    color="primary"
    animation-delay="0.2s"
  />
  
  <x-stat-card 
    title="Storage Used" 
    value="{{ $storageUsed ?? 0 }}GB" 
    icon="bx-hard-disk" 
    color="amber"
    animation-delay="0.3s"
  />
  
  <x-stat-card 
    title="Backup Status" 
    value="{{ $backupStatus ?? 'OK' }}" 
    icon="bx-cloud-download" 
    color="info"
    animation-delay="0.4s"
  />
</div>

    <!-- Navigation -->
    <div class="col-12 col-lg-4">
      <div class="d-flex justify-content-between flex-column mb-4 mb-md-0 hitech-card p-4 animate__animated animate__fadeInLeft">
        <h5 class="mb-4 text-primary fw-bold text-uppercase small ls-1">@lang('Settings')</h5>
        <ul class="nav nav-align-left nav-pills flex-column" id="settingsMenu">
          <li class="nav-item mb-1">
            <a class="nav-link active rounded-pill d-flex align-items-center py-3" href="?tab=generalSettings" data-bs-toggle="pill"
               data-bs-target="#generalSettings">
              <i class="bx bx-shape-square bx-sm me-3"></i>
              <span class="align-middle fw-semibold">General</span>
            </a>
          </li>
          <li class="nav-item mb-1">
            <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=appSettings" data-bs-toggle="pill" data-bs-target="#appSettings">
              <i class="bx bx-cog bx-sm me-3"></i>
              <span class="align-middle fw-semibold">App Settings</span>
            </a>
          </li>
          <li class="nav-item mb-1">
            <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=employeeSettings" data-bs-toggle="pill" data-bs-target="#employeeSettings">
              <i class="bx bx-user bx-sm me-3"></i>
              <span class="align-middle fw-semibold">Employee Settings</span>
            </a>
          </li>
          @if($addonService->isAddonEnabled(ModuleConstants::PAYROLL))
            <li class="nav-item mb-1">
              <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=payrollSettings" data-bs-toggle="pill" data-bs-target="#payrollSettings">
                <i class="bx bx-money bx-sm me-3"></i>
                <span class="align-middle fw-semibold">Payroll Settings</span>
              </a>
            </li>
          @endif
          <li class="nav-item mb-1">
            <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=trackingSettings" data-bs-toggle="pill" data-bs-target="#trackingSettings">
              <i class="bx bx-location-plus bx-sm me-3"></i>
              <span class="align-middle fw-semibold">Tracking</span>
            </a>
          </li>
          <li class="nav-item mb-1">
            <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=codePrefixSettings" data-bs-toggle="pill"
               data-bs-target="#codePrefixSettings">
              <i class="bx bx-code-block bx-sm me-3"></i>
              <span class="align-middle fw-semibold">Code Prefix/Suffix</span>
            </a>
          </li>
          <li class="nav-item mb-1">
            <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=mapsSettings" data-bs-toggle="pill" data-bs-target="#mapsSettings">
              <i class="bx bx-map bx-sm me-3"></i>
              <span class="align-middle fw-semibold">Maps</span>
            </a>
          </li>
          <li class="nav-item mb-1">
            <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=companySettings" data-bs-toggle="pill" data-bs-target="#companySettings">
              <i class="bx bx-buildings bx-sm me-3"></i> 
              <span class="align-middle fw-semibold">Company Settings</span>
            </a>
          </li>
          @if($addonService->isAddonEnabled(ModuleConstants::AI_CHATBOT))
            <li class="nav-item mb-1">
              <a class="nav-link rounded-pill d-flex align-items-center py-3" href="?tab=aiSettings" data-bs-toggle="pill" data-bs-target="#aiSettings">
                <i class="bx bx-brain bx-sm me-3"></i> 
                <span class="align-middle fw-semibold">AI Settings</span>
                <span class="badge bg-danger rounded-pill ms-auto small">Beta</span>
              </a>
            </li>
          @endif
        </ul>
      </div>
    </div>
    <!-- /Navigation -->

    <!-- Options -->
    <div class=" col-12 col-lg-8 pt-6 pt-lg-0">
      <div class="tab-content p-0">

        <!-- General Settings Tab -->
        <div class="tab-pane fade show active animate__animated animate__fadeIn" id="generalSettings" role="tabpanel">
          <form action="{{route('settings.updateGeneralSettings')}}" method="POST">
            @csrf
            <div class="hitech-card mb-6">
              <div class="hitech-card-header">
                <h5 class="title m-0">General Settings</h5>
              </div>
              <div class="card-body pt-4">
                <div class="row g-6">
                  <div class="col-12 col-md-6">
                    <label for="appName" class="form-label-hitech">App Name</label>
                    <input type="text" class="form-control form-control-hitech" id="appName" name="appName"
                           value="{{ $settings->app_name ?? '' }}">
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="country" class="form-label-hitech">Country</label>
                    <input type="text" class="form-control form-control-hitech" id="country" name="country"
                           value="{{ $settings->country ?? '' }}">
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="phoneCountryCode" class="form-label-hitech">Phone Country Code</label>
                    <input type="text" class="form-control form-control-hitech" id="phoneCountryCode" name="phoneCountryCode"
                           value="{{ $settings->phone_country_code ?? '' }}">
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="currency" class="form-label-hitech">Currency</label>
                    <input type="text" class="form-control form-control-hitech" id="currency" name="currency"
                           value="{{ $settings->currency ?? '' }}">
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="currencySymbol" class="form-label-hitech">Currency Symbol</label>
                    <input type="text" class="form-control form-control-hitech" id="currencySymbol" name="currencySymbol"
                           value="{{ $settings->currency_symbol ?? '' }}">
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="distanceUnit" class="form-label-hitech">Distance Unit</label>
                    <select id="distanceUnit" class="form-select form-select-hitech" name="distanceUnit">
                      <option value="km" {{ ($settings->distance_unit ?? 'km') == 'km' ? 'selected' : '' }}>
                        Kilometers
                      </option>
                      <option
                        value="miles" {{ ($settings->distance_unit ?? 'km') == 'miles' ? 'selected' : '' }}>
                        Miles
                      </option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label-hitech">Enable Helper Text</label>
                    <div class="form-check form-switch custom-option-basic">
                      <input class="form-check-input" type="checkbox" id="isHelperTextEnabled"
                             name="isHelperTextEnabled"
                        {{ $settings->is_helper_text_enabled ? 'checked' : '' }}>
                      <label class="form-check-label ms-2" for="isHelperTextEnabled">
                        @if($settings->is_helper_text_enabled)
                          Enabled
                        @else
                          Disabled
                        @endif
                      </label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-footer bg-transparent border-top border-light text-end p-4">
                <button type="submit" class="btn btn-hitech">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
        <!-- /General Settings -->

        <!-- App Settings -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="appSettings" role="tabpanel">
          <form action="{{route('settings.updateAppSettings')}}" method="POST">
            @csrf
            <div class="hitech-card mb-6">
              <div class="hitech-card-header">
                <h5 class="title m-0">Mobile App Settings</h5>
              </div>
              <div class="card-body pt-4">
                <div class="row g-6">
                  <div class="col-12 col-md-6">
                    <label for="mAppVersion" class="form-label-hitech">Mobile App Version</label>
                    <input type="text" class="form-control form-control-hitech" id="mAppVersion" name="mAppVersion"
                           value="{{ $settings->m_app_version ?? '' }}">
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="locationDistanceFilter" class="form-label-hitech">Location Distance Filter(in
                      meters)</label>
                    <input type="number" class="form-control form-control-hitech" id="locationDistanceFilter"
                           name="locationDistanceFilter"
                           value="{{ $settings->m_location_distance_filter ?? '' }}">
                  </div>
                  @if($settings->is_helper_text_enabled)
                    <div class="alert alert-primary alert-dismissible shadow-sm border-0" role="alert" style="background:rgba(13,110,253,0.1); color:#0d6efd;">
                      <h6 class="alert-heading fw-bold mb-1"><i class="bx bx-info-circle me-1"></i> Important Note: </h6>
                      <p class="mb-0 small opacity-75">Please note that the location distance filter is used to filter out
                        location updates that are less than the specified distance.</p>
                      <p class="mb-0 small opacity-75 mt-1"> We recommend using a <strong>10 meters distance filter</strong> for most use cases.</p>
                    </div>
                  @endif
                </div>
              </div>
              <div class="card-footer bg-transparent border-top border-light text-end p-4">
                <button type="submit" class="btn btn-hitech">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
        <!-- /App Settings -->

        <!-- Employee Settings -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="employeeSettings" role="tabpanel">
          <form action="{{ route('settings.updateEmployeeSettings') }}" method="POST">
            @csrf
            <div class="hitech-card mb-6">
              <div class="hitech-card-header">
                <h5 class="title m-0">Employee Settings</h5>
              </div>
              <div class="card-body pt-4">
                <div class="row g-6">

                  <!-- Biometric Verification -->
                  <div class="col-12 col-md-6">
                    <label class="form-label-hitech">Enable Biometric Verification</label>
                    <div class="form-check form-switch custom-option-basic">
                      <input class="form-check-input" type="checkbox" id="isBioMetricVerificationEnabled"
                             name="isBioMetricVerificationEnabled"
                        {{ $settings->is_biometric_verification_enabled ? 'checked' : '' }}>
                      <label class="form-check-label ms-2"
                             for="isBioMetricVerificationEnabled"> {{ $settings->is_biometric_verification_enabled ? 'Enabled' : 'Disabled' }}</label>
                    </div>
                  </div>

                  <!-- Device Verification -->
                  <div class="col-12 col-md-6">
                    <label class="form-label-hitech">Enable Device Verification</label>
                    <div class="form-check form-switch custom-option-basic">
                      <input class="form-check-input" type="checkbox" id="isDeviceVerificationEnabled"
                             name="isDeviceVerificationEnabled"
                        {{ $settings->is_device_verification_enabled ? 'checked' : '' }}>
                      <label class="form-check-label ms-2"
                             for="isDeviceVerificationEnabled"> {{ $settings->is_device_verification_enabled ? 'Enabled' : 'Disabled' }}</label>
                    </div>
                  </div>

                  <!-- Default Password -->
                  <div class="col-12">
                    <label for="defaultPassword" class="form-label-hitech">Default Password</label>
                    <input type="password" class="form-control form-control-hitech" id="defaultPassword" name="defaultPassword"
                           value="{{ $settings->default_password }}">
                  </div>
                </div>
              </div>
              <div class="card-footer bg-transparent border-top border-light text-end p-4">
                <button type="submit" class="btn btn-hitech">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
        <!-- /Employee Settings -->

        <!-- Tracking Settings -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="trackingSettings" role="tabpanel">
          <form action="{{route('settings.updateTrackingSettings')}}" method="POST">
            @csrf
            <div class="hitech-card mb-6">
              <div class="hitech-card-header">
                <h5 class="title m-0">Tracking Settings</h5>
              </div>
              <div class="card-body pt-4">
                <div class="row g-6">
                  <!-- Offline Check Time -->
                  <div class="col-12 col-md-6">
                    <label for="offlineCheckTime" class="form-label-hitech">Offline Check Time (In Seconds)</label>
                    <input type="number" class="form-control form-control-hitech" id="offlineCheckTime" name="offlineCheckTime"
                           value="{{ $settings->offline_check_time ?? 900 }}">
                  </div>
                </div>
              </div>
              <div class="card-footer bg-transparent border-top border-light text-end p-4">
                <button type="submit" class="btn btn-hitech">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
        <!-- /Tracking Settings -->

        <!-- Code Prefix & Suffix Settings -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="codePrefixSettings" role="tabpanel">
          <form action="" method="POST">
            @csrf
            <div class="hitech-card mb-6">
              <div class="hitech-card-header">
                <h5 class="title m-0">Code Prefix & Suffix</h5>
              </div>
              <div class="card-body pt-4">
                <div class="row g-6">
                  <!-- Employee Code Prefix -->
                  <div class="col-12 col-md-6">
                    <label for="employeeCodePrefix" class="form-label-hitech">Employee Code Prefix</label>
                    <input type="text" class="form-control form-control-hitech" id="employeeCodePrefix" name="employee_code_prefix"
                           value="{{ $settings->employee_code_prefix ?? 'EMP' }}">
                  </div>
                  @if(Nwidart\Modules\Facades\Module::has('ProductOrder'))
                    <!-- Order Prefix -->
                    <div class="col-12 col-md-6">
                      <label for="orderPrefix" class="form-label-hitech">Order Prefix</label>
                      <input type="text" class="form-control form-control-hitech" id="orderPrefix" name="order_prefix"
                             value="{{ $settings->order_prefix ?? 'FM_ORD' }}">
                    </div>
                  @endif
                </div>
              </div>
              <div class="card-footer bg-transparent border-top border-light text-end p-4">
                <button type="submit" class="btn btn-hitech">Save Changes</button>
              </div>
            </div>
          </form>
        </div>



        <!-- Company Settings -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="companySettings" role="tabpanel">
          <form action="{{ route('settings.updateCompanySettings') }}" method="POST"
                enctype="multipart/form-data">
            @csrf
            <div class="hitech-card mb-6">
              <div class="hitech-card-header">
                <h5 class="title m-0">Company Settings</h5>
              </div>
              <div class="card-body pt-4">
                <div class="row g-6">
                  <!-- Company Logo Upload with Preview -->
                  <div class="col-12 col-md-12">
                    <label for="companyLogo" class="form-label-hitech">Company Logo</label>
                    <div class="position-relative d-flex justify-content-start align-items-center">
                      <!-- Logo Preview -->
                      <div class="border rounded-2 p-1 d-flex justify-content-center align-items-center bg-transparent"
                           style="width: 150px; height: 150px; overflow: hidden; cursor: pointer; border: 2px dashed rgba(0,0,0,0.1) !important;"
                           onclick="document.getElementById('companyLogo').click();">
                        <img id="companyLogoPreview"
                             src="{{ $settings->company_logo ? asset('images/'.$settings->company_logo) : 'https://placehold.co/150x150?text=Upload+Logo' }}"
                             alt="Company Logo"
                             class="img-fluid"
                             style="max-width: 100%; max-height: 100%; object-fit: contain;">
                      </div>

                      <!-- Hidden File Input -->
                      <input type="file" class="form-control d-none" id="companyLogo" name="company_logo"
                             accept="image/*">

                      <!-- Delete Logo Button -->
                      @if($settings->company_logo)
                        <button type="button" class="btn btn-outline-danger btn-sm ms-4 rounded-pill" id="removeLogoButton">
                          <i class="bx bx-trash me-1"></i> Remove Logo
                        </button>
                      @endif
                    </div>
                    <small class="text-muted mt-2 d-block small opacity-75">Click on the placeholder to upload. Max size: 2MB (JPG, PNG)</small>
                  </div>
                  <!-- Company Name -->
                  <div class="col-12 col-md-6">
                    <label for="companyName" class="form-label-hitech">Company Name</label>
                    <input type="text" class="form-control form-control-hitech" id="companyName" name="company_name"
                           value="{{ $settings->company_name ?? '' }}">
                  </div>
                  <!-- Company Phone -->
                  <div class="col-12 col-md-6">
                    <label for="companyPhone" class="form-label-hitech">Company Phone</label>
                    <input type="text" class="form-control form-control-hitech" id="companyPhone" name="company_phone"
                           value="{{ $settings->company_phone ?? '' }}">
                  </div>
                  <!-- Company Email -->
                  <div class="col-12 col-md-6">
                    <label for="companyEmail" class="form-label-hitech">Company Email</label>
                    <input type="email" class="form-control form-control-hitech" id="companyEmail" name="company_email"
                           value="{{ $settings->company_email ?? '' }}">
                  </div>
                  <!-- Company Website -->
                  <div class="col-12 col-md-6">
                    <label for="companyWebsite" class="form-label-hitech">Company Website</label>
                    <input class="form-control form-control-hitech" id="companyWebsite" name="company_website"
                           value="{{ $settings->company_website ?? '' }}">
                  </div>
                  <!-- Company Address -->
                  <div class="col-12">
                    <label for="companyAddress" class="form-label-hitech">Company Address</label>
                    <textarea type="text" class="form-control form-control-hitech" id="companyAddress" name="company_address" rows="3">{{$settings->company_address ?? ''}}</textarea>
                  </div>

                  <!-- Company City -->
                  <div class="col-12 col-md-6">
                    <label for="companyCity" class="form-label-hitech">Company City</label>
                    <input type="text" class="form-control form-control-hitech" id="companyCity" name="company_city"
                           value="{{ $settings->company_city ?? '' }}">
                  </div>

                  <!-- Company Country -->
                  <div class="col-12 col-md-6">
                    <label for="companyCountry" class="form-label-hitech">Company Country</label>
                    <input type="text" class="form-control form-control-hitech" id="companyCountry" name="company_country"
                           value="{{ $settings->company_country ?? '' }}">
                  </div>
                  <!-- Company State -->
                  <div class="col-12 col-md-6">
                    <label for="companyState" class="form-label-hitech">Company State</label>
                    <input type="text" class="form-control form-control-hitech" id="companyState" name="company_state"
                           value="{{ $settings->company_state ?? '' }}">
                  </div>
                  <!-- Company ZIP -->
                  <div class="col-12 col-md-6">
                    <label for="companyZipcode" class="form-label-hitech">Company Zipcode</label>
                    <input type="text" class="form-control form-control-hitech" id="companyZipcode" name="company_zipcode"
                           value="{{ $settings->company_zipcode ?? '' }}">
                  </div>
                  <!-- TAX No -->
                  <div class="col-12 col-md-6">
                    <label for="companyTaxId" class="form-label-hitech">Company Tax Id</label>
                    <input type="text" class="form-control form-control-hitech" id="companyTaxId" name="company_tax_id"
                           value="{{ $settings->company_tax_id ?? '' }}">
                  </div>
                  <!-- Reg No -->
                  <div class="col-12 col-md-6">
                    <label for="companyRegNo" class="form-label-hitech">Company Reg No</label>
                    <input type="text" class="form-control form-control-hitech" id="companyRegNo" name="company_reg_no"
                           value="{{ $settings->company_reg_no ?? '' }}">
                  </div>

                </div>
              </div>
              <div class="card-footer bg-transparent border-top border-light text-end p-4">
                <button type="submit" class="btn btn-hitech">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
        <!-- /Company Settings -->

        <!-- AI Settings -->
        @if($addonService->isAddonEnabled(ModuleConstants::AI_CHATBOT))
          <div class="tab-pane fade animate__animated animate__fadeIn" id="aiSettings" role="tabpanel">
            <form action="{{route('settings.updateAiSettings')}}" method="POST">
              @csrf
              <div class="hitech-card mb-6">
                <div class="hitech-card-header">
                  <h5 class="title m-0">AI Chatbot Settings</h5>
                </div>
                <div class="card-body pt-4">
                  <div class="row g-6">
                    <div class="col-12 col-md-6">
                        <label class="form-label-hitech">AI Model</label>
                        <select class="form-select form-select-hitech" name="aiModel">
                            <option value="gemini-pro" {{ $settings->ai_model == 'gemini-pro' ? 'selected' : '' }}>Gemini Pro</option>
                            {{-- <option value="gpt-4" {{ $settings->ai_model == 'gpt-4' ? 'selected' : '' }}>GPT-4</option> --}}
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label-hitech">API Key</label>
                        <input type="password" class="form-control form-control-hitech" name="aiApiKey" value="{{ $settings->ai_api_key ?? '' }}">
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-transparent border-top border-light text-end p-4">
                  <button type="submit" class="btn btn-hitech">Save Changes</button>
                </div>
              </div>
            </form>
          </div>
        @endif
        <!-- Maps Settings -->
        <div class="tab-pane fade animate__animated animate__fadeIn" id="mapsSettings" role="tabpanel">
          <form action="" method="POST">
            @csrf
            <div class="hitech-card mb-6">
              <div class="hitech-card-header">
                <h5 class="title m-0">Maps Settings</h5>
              </div>
              <div class="card-body pt-4">
                <div class="row g-6">
                  <div class="col-12 col-md-6">
                    <label for="googleMapsKey" class="form-label-hitech">Google Maps Key</label>
                    <input type="text" class="form-control form-control-hitech" id="googleMapsKey" name="google_maps_key"
                           value="{{ $settings->google_maps_key ?? '' }}">
                  </div>
                </div>
              </div>
              <div class="card-footer bg-transparent border-top border-light text-end p-4">
                <button type="submit" class="btn btn-hitech">Save Changes</button>
              </div>
            </div>
          </form>
        </div>
        <!-- /Maps Settings -->



        @if($addonService->isAddonEnabled(ModuleConstants::PAYROLL))
          <!-- Payroll Settings -->
          <div class="tab-pane fade animate__animated animate__fadeIn" id="payrollSettings" role="tabpanel">
            <form action="{{ route('settings.updatePayrollSettings') }}" method="POST">
              @csrf
              <div class="hitech-card mb-6">
                <div class="hitech-card-header">
                  <h5 class="title m-0">Payroll Configuration</h5>
                </div>
                <div class="card-body pt-4">
                  <div class="row g-6">
                    <!-- Payroll Frequency -->
                    <div class="col-12 col-md-6">
                      <label for="payrollFrequency" class="form-label-hitech">Payroll Frequency</label>
                      <select id="payrollFrequency" class="form-select form-select-hitech" name="payrollFrequency">
                        <option
                          value="monthly" {{ ($settings->payroll_frequency ?? 'monthly') == 'monthly' ? 'selected' : '' }}>
                          Monthly
                        </option>
                        <option
                          value="bi-weekly" {{ ($settings->payroll_frequency ?? 'monthly') == 'bi-weekly' ? 'selected' : '' }}>
                          Bi-Weekly
                        </option>
                        <option
                          value="weekly" {{ ($settings->payroll_frequency ?? 'monthly') == 'weekly' ? 'selected' : '' }}>
                          Weekly
                        </option>
                        <option
                          value="daily" {{ ($settings->payroll_frequency ?? 'monthly') == 'daily' ? 'selected' : '' }}>
                          Daily
                        </option>
                      </select>
                    </div>
                    <!-- Payroll Start Date -->
                    <div class="col-12 col-md-6">
                      <label for="payrollStartDate" class="form-label-hitech">Payroll Start Date</label>
                      <input type="number" class="form-control form-control-hitech" id="payrollStartDate" name="payrollStartDate"
                             min="1" max="31"
                             value="{{ $settings->payroll_start_date ?? '1' }}">
                    </div>

                    <div class="col-12 col-md-6">
                      <label for="payrollCutoffDate" class="form-label-hitech">Payroll Cut-Off Date</label>
                      <input type="number" class="form-control form-control-hitech" id="payrollCutoffDate" name="payrollCutoffDate"
                             min="1" max="31" value="{{ $settings->payroll_cutoff_date ?? '25' }}">
                    </div>

                    <div class="col-12 col-md-6">
                      <label class="form-label-hitech">Enable Automatic Payroll Processing</label>
                      <div class="form-check form-switch custom-option-basic">
                        <input class="form-check-input" type="checkbox" id="autoPayrollProcessing"
                               name="autoPayrollProcessing"
                          {{ ($settings->auto_payroll_processing ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label ms-2" for="autoPayrollProcessing">Enable</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-footer bg-transparent border-top border-light text-end p-4">
                  <button type="submit" class="btn btn-hitech">Save Changes</button>
                </div>
              </div>
            </form>


            <!-- Adjustments Info Card -->
            <div class="hitech-card mt-6">
              <div class="hitech-card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="title m-0"><i class="bx bx-adjust text-primary me-2"></i> Global Salary Adjustments</h5>
                <button class="btn btn-sm btn-hitech" data-bs-toggle="offcanvas"
                        data-bs-target="#offcanvasAddAdjustmentGlobal" id="addPayrollAdjustment">
                  <i class="bx bx-plus"></i> Add Adjustment
                </button>
              </div>
              <div class="card-body p-0">
                @if($payrollAdjustments->count() > 0)
                  <div class="table-responsive text-nowrap">
                    <table class="table table-hover mb-0">
                      <thead>
                      <tr>
                        <th>Adjustment Name</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Amount / %</th>
                        <th>Actions</th>
                      </tr>
                      </thead>
                      <tbody>
                      @foreach($payrollAdjustments as $adjustment)
                        <tr>
                          <td>
                            <div class="d-flex align-items-center">
                                <div class="stat-icon-wrap icon-{{ $adjustment->type === 'benefit' ? 'teal' : 'danger' }} me-2" style="width:30px; height:30px; font-size:0.8rem;">
                                    <i class="bx bx-{{ $adjustment->type === 'benefit' ? 'plus' : 'minus' }}"></i>
                                </div>
                                <span class="fw-bold text-heading">{{ $adjustment->name }}</span>
                            </div>
                          </td>
                          <td><code class="px-2 py-1 bg-label-secondary rounded">{{ $adjustment->code }}</code></td>
                          <td>
                            @if($adjustment->type === 'benefit')
                              <span class="badge badge-hitech bg-label-success">Benefit</span>
                            @else
                              <span class="badge badge-hitech bg-label-danger">Deduction</span>
                            @endif
                          </td>
                          <td>
                            @if($adjustment->percentage)
                                <span class="fw-bold text-primary">{{ $adjustment->percentage }}%</span> <small class="text-muted">of Basic</small>
                            @else
                                <span class="fw-bold text-dark">{{ $settings->currency_symbol }}{{ number_format($adjustment->amount, 2) }}</span>
                            @endif
                          </td>
                          <td>
                            <div class="d-flex align-items-center">
                              <button class="btn btn-icon btn-sm btn-label-warning rounded-pill me-2"
                                 onclick="editAdjustmentGlobal({{$adjustment}})">
                                <i class="bx bx-edit-alt"></i>
                              </button>
                              <form action="{{ route('employees.deletePayrollAdjustment', $adjustment->id) }}"
                                    method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-icon btn-sm btn-label-danger rounded-pill"
                                        onclick="return confirm('Are you sure you want to delete this global adjustment?')">
                                  <i class="bx bx-trash"></i>
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      @endforeach
                      </tbody>
                    </table>
                  </div>
                @else
                  <div class="text-center py-5">
                    <i class="bx bx-adjust bx-lg text-muted opacity-25 mb-3"></i>
                    <p class="text-muted">@lang('No payroll adjustments found.')</p>
                  </div>
                @endif
              </div>
            </div>

            @if($settings->is_helper_text_enabled)
              <div class="alert alert-primary alert-dismissible mt-5 shadow-sm border-0 animate__animated animate__fadeInUp" role="alert" style="background:rgba(105, 108, 255, 0.08); color:#696cff;">
                <h6 class="alert-heading fw-bold mb-1"><i class="bx bx-info-circle me-1"></i> About Payroll Adjustments</h6>
                <p class="mb-0 small opacity-75">Global adjustments are applied to all employees automatically. You can also define employee-specific adjustments in their individual profiles.
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                  </button>
              </div>
            @endif
            <!-- /Adjustments Info Card -->
          </div>
          <!-- /Payroll Settings -->
        @endif

      </div>
    </div>
    <!-- /Options -->
  </div>
@endsection

@if($addonService->isAddonEnabled(ModuleConstants::PAYROLL))
  @include('tenant.payroll.partials.add_orUpdate_payroll_adjustment_global')
@endif
@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
          integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
          crossorigin="anonymous"></script>

  <script>
    $(function () {

      window.toggleAIOptions = function (checkbox) {
        const aiOptions = document.getElementById('aiOptions');
        if (checkbox.checked) {
          aiOptions.style.display = 'block';
        } else {
          aiOptions.style.display = 'none';
        }
      }

      document.getElementById('companyLogo').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function (e) {
            document.getElementById('companyLogoPreview').src = e.target.result;
          };
          reader.readAsDataURL(file);
        }
      });

      // Remove Logo Functionality
      const removeLogoButton = document.getElementById('removeLogoButton');
      if (removeLogoButton) {
        removeLogoButton.addEventListener('click', function () {
          document.getElementById('companyLogoPreview').src = 'https://placehold.co/150x150';
          document.getElementById('companyLogo').value = '';
        });
      }

      $('#timezone').select2();
      // Get the tab parameter from the URL
      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab');

      if (activeTab) {
        // Activate the tab
        $('.nav-link').removeClass('active');
        $('.tab-pane').removeClass('show active');

        // Add active classes
        $(`[data-bs-target="#${activeTab}"]`).addClass('active');
        $(`#${activeTab}`).addClass('show active');
      } else {
        // Default to the first tab if no tab param is provided
        $('.nav-link').first().addClass('active');
        $('.tab-pane').first().addClass('show active');
      }

      $('#adjustmentCategory').on('change', function () {
        if ($(this).val() === 'percentage') {
          $('#adjustmentPercentage').parent().removeClass('d-none');
          $('#adjustmentAmount').parent().addClass('d-none');
        } else {
          $('#adjustmentAmount').parent().removeClass('d-none');
          $('#adjustmentPercentage').parent().addClass('d-none');
        }
      })

      window.editAdjustment = function (adjustment) {
        $('#offcanvasPayrollAdjustmentLabel').text('Edit Payroll Adjustment');
        $('#adjustmentId').val(adjustment.id);
        $('#adjustmentName').val(adjustment.name);
        $('#adjustmentType').val(adjustment.type);
        $('#adjustmentAmount').val(adjustment.amount);
        $('#adjustmentPercentage').val(adjustment.percentage);

        if (adjustment.amount) {
          $('#adjustmentCategory').val('fixed');
          $('#adjustmentAmount').parent().removeClass('d-none');
          $('#adjustmentPercentage').parent().addClass('d-none');
        } else {
          $('#adjustmentCategory').val('percentage');
          $('#adjustmentPercentage').parent().removeClass('d-none');
          $('#adjustmentAmount').parent().addClass('d-none');
        }

        $('#adjustmentNotes').val(adjustment.notes);
        $('#adjustmentSubmitBtn').text('Update Adjustment');

      }

      $('#addPayrollAdjustment').on('click', function () {
        $('#offcanvasPayrollAdjustmentLabel').text('Add Payroll Adjustment');
        $('#adjustmentId').val('');
        $('#adjustmentName').val('');
        $('#adjustmentType').val('benefit');
        $('#adjustmentAmount').val('');
        $('#adjustmentPercentage').val('');
        $('#adjustmentCategory').val('fixed');
        $('#adjustmentAmount').parent().removeClass('d-none');
        $('#adjustmentPercentage').parent().addClass('d-none');
        $('#adjustmentNotes').val('');
        $('#adjustmentSubmitBtn').text('Add Adjustment');
      });
    });

  </script>
@endsection
