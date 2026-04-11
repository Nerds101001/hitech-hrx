@php
  use App\Enums\Gender;
  use App\Helpers\StaticDataHelpers;
  use Illuminate\Support\Facades\Auth;
  $banks = StaticDataHelpers::getIndianBanksList();
  
  $userStatus = $user->status instanceof \UnitEnum ? $user->status->value : $user->status;
  $isSubmitted = ($userStatus === \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED->value);
  $isResubmission = !empty($user->onboarding_resubmission_notes);
  $rejectedSections = (array) ($user->onboarding_rejected_sections ?? []);
  $existingPhoto = !empty($user->profile_picture);
  $existingAadhaar = (bool) $user->getAadhaarUrl();
  $existingPan = (bool) $user->getPanUrl();
  $existingCheque = (bool) $user->getChequeUrl();
  $existingMatric = (bool) $user->getMatricUrl();
  $existingInter = (bool) $user->getInterUrl();
  $existingBachelor = (bool) $user->getBachelorUrl();
  $existingMaster = (bool) $user->getMasterUrl();
  $existingExperience = (bool) $user->getExperienceUrl();
  
  // Revised Logic:
  // 1. If submitted (Under Review): EVERYTHING LOCKED.
  // 2. If not submitted:
  //    - If first time: EVERYTHING OPEN.
  //    - If flagged for resubmission: ONLY REJECTED OPEN.
  $canEditPersonal = !$isSubmitted && (!$isResubmission || in_array('personal', $rejectedSections));
  $canEditContact = !$isSubmitted && (!$isResubmission || in_array('contact', $rejectedSections));
  $canEditBanking = !$isSubmitted && (!$isResubmission || in_array('banking', $rejectedSections));
  $canEditDocs = !$isSubmitted && (!$isResubmission || in_array('documents', $rejectedSections));
@endphp

@extends('layouts/blankLayout')

@section('title', 'Onboarding Form')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/bs-stepper/bs-stepper.scss',
    'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
  <style>
    :root {
        --primary-teal: #006D77;
        --deep-teal: #004d54;
        --bg-light: #F8FAFC;
    }

    /* Unique variants for Onboarding Form (Overriding BS Stepper) */
    .step-circle-custom {
        width: 32px;
        height: 32px;
    }

    .step-label-custom {
        font-size: 11px;
    }

    .stepper-line-custom {
        transform: translateY(-12px);
    }

    /* LOCKED SECTION STYLES */
    .step-locked .step-circle {
        background: #f1f5f9 !important;
        border-color: #e2e8f0 !important;
        color: #94a3b8 !important;
        opacity: 0.6;
    }
    
    .step-locked .step-label {
        opacity: 0.6;
    }

    .section-locked-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* SUBMISSION BANNER */
    .submission-received-banner {
        background: linear-gradient(135deg, var(--primary-teal-dark) 0%, var(--primary-teal) 100%);
        border-radius: 20px;
        padding: 1.5rem 2.5rem;
        display: flex;
        align-items: center;
        gap: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 10px 30px rgba(0, 77, 84, 0.15);
        color: white;
    }

    /* Document Upload UI Improvements */
    .upload-progress-wrapper {
        margin-top: 8px;
        display: none;
    }
    .progress-hitech {
        height: 6px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }
    .progress-bar-hitech {
        height: 100%;
        background: var(--primary-teal);
        width: 0%;
        transition: width 0.3s ease;
    }
    .upload-status-compact {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 10px;
        padding: 8px 14px;
        background: #f0fdfa;
        border: 1px dashed #5eead4;
        border-radius: 12px;
    }
    .status-icon-success {
        color: #0d9488;
        font-size: 1.25rem;
    }
    .view-doc-link {
        color: #0d9488;
        font-weight: 800;
        font-size: 11px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 4px;
        background: white;
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid #ccfbf1;
        transition: all 0.2s;
    }
    .view-doc-link:hover {
        background: #ccfbf1;
        color: var(--deep-teal);
    }
    .replace-doc-btn {
        color: #64748b;
        font-weight: 700;
        font-size: 10px;
        text-transform: uppercase;
        cursor: pointer;
        border: none;
        background: transparent;
        padding: 0;
        display: flex;
        align-items: center;
        gap: 4px;
        border-bottom: 1px solid transparent;
    }
    .replace-doc-btn:hover {
        color: #ef4444;
        border-bottom-color: #ef4444;
    }
    
    .badge-uploaded, .badge-error {
        display: none;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 20px;
    }
    .badge-uploaded { background: #dcfce7; color: #166534; }
    .badge-error { background: #fee2e2; color: #991b1b; }

    /* Footer / Step Toggles Alignment */
    .hitech-footer {
        background: #F8FAFC;
        border-top: 1px solid #E2E8F0;
        padding: 2.5rem 3rem !important; /* Increased padding */
        display: flex;
        justify-content: center;
        border-bottom-left-radius: 20px;
        border-bottom-right-radius: 20px;
        margin-top: 1rem;
    }

    .footer-content {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btn-prev-hitech {
        background: white;
        border: 1px solid #E2E8F0;
        color: #64748B;
        padding: 0.75rem 1.75rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .btn-prev-hitech:hover {
        color: var(--deep-teal);
        background: #F8FAFC;
    }

    .btn-next-hitech {
        background: var(--deep-teal) !important;
        color: white !important;
        border: none !important;
        padding: 0.8rem 2.5rem !important;
        border-radius: 12px !important;
        font-weight: 800 !important;
        font-size: 0.9rem !important;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 4px 12px rgba(0, 77, 84, 0.15);
    }

    .btn-next-hitech:hover {
        background: var(--primary-teal) !important;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 109, 119, 0.25);
    }

    #wizard-onboarding {
        margin-bottom: 2rem !important;
    }

  </style>
@endsection

@section('content')
<div class="hitech-header">
    <div class="brand-logo-area">
        <div class="brand-icon-box">
            <i class="bx bx-layer"></i>
        </div>
        <h1 class="brand-text">HI TECH <span>HRX</span></h1>
    </div>
    <div class="header-actions">
        <div class="dropdown">
            <a class="nav-link dropdown-toggle hide-arrow p-0 align-items-center d-flex gap-2" href="javascript:void(0);" data-bs-toggle="dropdown" style="border: 1px solid rgba(0,0,0,0.05); border-radius: 50px; padding: 0.25rem 0.75rem !important; background-color: #f8f9fa;">
                <div class="avatar avatar-sm rounded-circle d-flex align-items-center justify-content-center" style="background-color: var(--primary-teal); color: white;">
                    @if (Auth::user() && !is_null(Auth::user()->profile_picture))
                        <img src="{{ Auth::user()->getProfilePicture() }}" alt class="w-100 h-100 rounded-circle object-fit-cover">
                    @else
                        <span class="fw-semibold" style="font-size: 0.8rem;">{{ Auth::user()->getInitials() }}</span>
                    @endif
                </div>
                <div class="d-none d-md-flex flex-column text-start justify-content-center" style="line-height: 1.1;">
                    <span class="fw-bold text-heading" style="font-size: 0.75rem;">{{ Auth::user() ? Auth::user()->getFullName() : 'Candidate' }}</span>
                    <span class="text-muted" style="font-size: 0.65rem;">Onboarding</span>
                </div>
                <i class="bx bx-chevron-down text-muted ms-1" style="font-size: 1rem;"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; margin-top: 10px;">
                <li>
                    <a class="dropdown-item text-danger fw-bold" href="{{ route('auth.logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class='bx bx-log-out me-2'></i><span>Logout</span>
                    </a>
                </li>
            </ul>
            <form method="POST" id="logout-form" action="{{ route('auth.logout') }}" style="display: none;">
                @csrf
            </form>
        </div>
    </div>
</div>

<div class="hitech-stepper-wrapper" style="margin-top: 2rem;">
    @if($isSubmitted)
    <div class="submission-received-banner animate__animated animate__fadeInDown">
        <div class="banner-icon">
            <i class="bx bx-time-five"></i>
        </div>
        <div class="banner-content">
            <h3>Submission Received & Under Review</h3>
            <p>Hello {{ $user->first_name }}, your application is currently being verified by HR. You can view your details below.</p>
        </div>
        <div class="banner-badge">
            <span>Awaiting Validation</span>
        </div>
    </div>
    @endif

    @if(session('success'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        showSuccessSwal("{{ session('success') }}");
      });
    </script>
    @endif

    @if(session('error'))
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        showErrorSwal("{{ session('error') }}");
      });
    </script>
    @endif

    <div class="d-flex align-items-center justify-content-between">
        <div class="step-custom @if($canEditPersonal) active @else step-locked @endif d-flex flex-column align-items-center">
            <div class="step-circle step-circle-custom">
                @if($canEditPersonal) 1 @else <i class='bx bx-check-shield'></i> @endif
            </div>
            <span class="step-label step-label-custom">Personal</span>
        </div>
        <div class="stepper-line stepper-line-custom @if(!$canEditPersonal) active @endif"></div>
        <div class="step-custom @if(!$canEditPersonal && $canEditContact) active @elseif(!$canEditContact) step-locked @endif d-flex flex-column align-items-center">
            <div class="step-circle step-circle-custom">
                @if($canEditContact) 2 @else <i class='bx bx-check-shield'></i> @endif
            </div>
            <span class="step-label step-label-custom">Contact</span>
        </div>
        <div class="stepper-line stepper-line-custom @if(!$canEditContact) active @endif"></div>
        <div class="step-custom @if(!$canEditContact && $canEditBanking) active @elseif(!$canEditBanking) step-locked @endif d-flex flex-column align-items-center">
            <div class="step-circle step-circle-custom">
                @if($canEditBanking) 3 @else <i class='bx bx-check-shield'></i> @endif
            </div>
            <span class="step-label step-label-custom">Banking</span>
        </div>
        <div class="stepper-line stepper-line-custom @if(!$canEditBanking) active @endif"></div>
        <div class="step-custom @if(!$canEditBanking && $canEditDocs) active @elseif(!$canEditDocs) step-locked @endif d-flex flex-column align-items-center">
            <div class="step-circle step-circle-custom">
                @if($canEditDocs) 4 @else <i class='bx bx-check-shield'></i> @endif
            </div>
            <span class="step-label step-label-custom">Docs</span>
        </div>
        <div class="stepper-line stepper-line-custom @if(!$canEditDocs) active @endif"></div>
        <div class="step-custom d-flex flex-column align-items-center">
            <div class="step-circle step-circle-custom">5</div>
            <span class="step-label step-label-custom">Review</span>
        </div>
    </div>

    <div id="wizard-onboarding" class="bs-stepper">
        <div class="bs-stepper-header d-none" role="tablist">
          <div class="step" data-target="#personal-info"><button type="button" class="step-trigger" role="tab"></button></div>
          <div class="step" data-target="#contact-info"><button type="button" class="step-trigger" role="tab"></button></div>
          <div class="step" data-target="#bank-details"><button type="button" class="step-trigger" role="tab"></button></div>
          <div class="step" data-target="#documents"><button type="button" class="step-trigger" role="tab"></button></div>
          <div class="step" data-target="#legal-consent"><button type="button" class="step-trigger" role="tab"></button></div>
        </div>

        <div class="bs-stepper-content" style="padding: 0; background: transparent;">
          <form id="onboardingForm" method="POST" action="{{ route('onboarding.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="saving-indicator" id="saving_indicator" style="display: none; position: fixed; top: 20px; right: 20px; z-index: 9999; background: rgba(0,0,0,0.8); color: white; padding: 10px 20px; border-radius: 30px; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); font-weight: 700; border: 1px solid rgba(6, 237, 249, 0.3);">
              <div class="saving-spinner"></div>
              <span>Saving Progress...</span>
            </div>
            
            <!-- Step 1: Personal Info -->
            <div id="personal-info" class="content onboarding-card" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
              <div class="card-header-hitech" style="border-top-left-radius: 20px; border-top-right-radius: 20px; margin: 0;">
                <h2 style="margin-top: 0;">Personal Information</h2>
                <p>Please provide your basic family and personal details.</p>
              </div>
              <div class="card-body-hitech">
                @if ($user->onboarding_resubmission_notes)
                  <div class="alert alert-warning border-0 d-flex align-items-start mb-4" style="background-color: #fffbeb; border-radius: 16px; padding: 1.5rem; border: 1px solid #fef3c7 !important;">
                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; min-width: 40px; background-color: #f59e0b !important; color: white;">
                      <i class="bx bx-error-alt fs-4"></i>
                    </div>
                    <div>
                      <h6 class="fw-extrabold text-warning mb-1" style="color: #92400e !important;">Action Required: HR Feedback</h6>
                      <p class="mb-0 small text-dark fw-semibold" style="line-height: 1.5;">{{ $user->onboarding_resubmission_notes }}</p>
                    </div>
                  </div>
                @endif
                
                <h4 class="section-title">Core Identity</h4>
                <div class="row g-4 mb-4">
                  <div class="col-md-6">
                    <label class="hitech-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" id="first_name" class="hitech-input @error('first_name') is-invalid @enderror" value="{{ old('first_name', $user->first_name) }}" placeholder="First Name" required {{ !$canEditPersonal ? 'readonly' : '' }}>
                    @error('first_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="invalid-feedback" id="fname_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Name should only contain letters.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="hitech-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" id="last_name" class="hitech-input @error('last_name') is-invalid @enderror" value="{{ old('last_name', $user->last_name) }}" placeholder="Last Name" required {{ !$canEditPersonal ? 'readonly' : '' }}>
                    @error('last_name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="invalid-feedback" id="lname_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Name should only contain letters.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="hitech-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" id="dob_input" class="hitech-input @error('dob') is-invalid @enderror" value="{{ old('dob', $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : '') }}" required {{ !$canEditPersonal ? 'readonly' : '' }}>
                    @error('dob') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="invalid-feedback" id="dob_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">You must be at least 18 years old.</div>
                  </div>
                   <div class="col-md-4">
                    <label class="hitech-label">Gender <span class="text-danger">*</span></label>
                    <select name="gender" class="hitech-input" required {{ !$canEditPersonal ? 'disabled' : '' }}>
                      <option value="">Select Gender</option>
                      @foreach(Gender::cases() as $gender)
                        <option value="{{$gender->value}}" {{ $user->gender == $gender->value ? 'selected' : '' }}>{{ucfirst($gender->value)}}</option>
                      @endforeach
                    </select>
                    @if(!$canEditPersonal) <input type="hidden" name="gender" value="{{ $user->gender }}"> @endif
                  </div>
                  <div class="col-md-4">
                    <label class="hitech-label">Blood Group</label>
                    <select name="blood_group" class="hitech-input" {{ !$canEditPersonal ? 'disabled' : '' }}>
                      <option value="">Select</option>
                      @foreach(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg)
                        <option value="{{$bg}}" {{ $user->blood_group == $bg ? 'selected' : '' }}>{{$bg}}</option>
                      @endforeach
                    </select>
                    @if(!$canEditPersonal) <input type="hidden" name="blood_group" value="{{ $user->blood_group }}"> @endif
                  </div>
                  <div class="col-md-12">
                     <label class="hitech-label">Highest Qualification <span class="text-danger">*</span></label>
                     <select name="highest_qualification" class="hitech-input" required {{ !$canEditPersonal ? 'disabled' : '' }}>
                        <option value="">Select Qualification</option>
                        <option value="matric" {{ $user->highest_qualification == 'matric' ? 'selected' : '' }}>Matric / 10th</option>
                        <option value="intermediate" {{ $user->highest_qualification == 'intermediate' ? 'selected' : '' }}>Intermediate / 12th</option>
                        <option value="bachelor" {{ $user->highest_qualification == 'bachelor' ? 'selected' : '' }}>Bachelor's Degree</option>
                        <option value="master" {{ $user->highest_qualification == 'master' ? 'selected' : '' }}>Master's Degree</option>
                        <option value="doctorate" {{ $user->highest_qualification == 'doctorate' ? 'selected' : '' }}>Doctorate / PhD</option>
                     </select>
                     @if(!$canEditPersonal) <input type="hidden" name="highest_qualification" value="{{ $user->highest_qualification }}"> @endif
                  </div>
                </div>

                <h4 class="section-title">Family Details</h4>
                <div class="row g-4 mb-4">
                  <div class="col-md-6">
                    <label class="hitech-label">Father's Name <span class="text-danger">*</span></label>
                    <input type="text" name="father_name" id="father_name" class="hitech-input" value="{{ $user->father_name }}" placeholder="Full Name" required {{ !$canEditPersonal ? 'readonly' : '' }}>
                    <div class="invalid-feedback" id="father_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Name should only contain letters.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="hitech-label">Mother's Name <span class="text-danger">*</span></label>
                    <input type="text" name="mother_name" id="mother_name" class="hitech-input" value="{{ $user->mother_name }}" placeholder="Full Name" required {{ !$canEditPersonal ? 'readonly' : '' }}>
                    <div class="invalid-feedback" id="mother_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Name should only contain letters.</div>
                  </div>
                  
                  <div class="col-md-6">
                    <label class="hitech-label">Marital Status <span class="text-danger">*</span></label>
                    <select name="marital_status" id="marital_status" class="hitech-input" required {{ !$canEditPersonal ? 'disabled' : '' }}>
                      <option value="single" {{ $user->marital_status == 'single' ? 'selected' : '' }}>Single</option>
                      <option value="married" {{ $user->marital_status == 'married' ? 'selected' : '' }}>Married</option>
                    </select>
                    @if(!$canEditPersonal) <input type="hidden" name="marital_status" value="{{ $user->marital_status }}"> @endif
                  </div>
                  <div class="col-md-6">
                     <label class="hitech-label">No. of Children</label>
                     <input type="number" name="no_of_children" id="no_of_children" class="hitech-input" value="{{ $user->no_of_children ?? 0 }}" {{ !$canEditPersonal ? 'readonly' : '' }}>
                  </div>

                  <div class="col-12" id="spouse-details-div" style="display: {{ $user->marital_status == 'married' ? 'block' : 'none' }};">
                    <div class="row g-4">
                      <div class="col-md-6">
                        <label class="hitech-label">Spouse Name</label>
                        <input type="text" name="spouse_name" id="spouse_name" class="hitech-input" value="{{ $user->spouse_name }}" placeholder="Spouse Full Name" {{ !$canEditPersonal ? 'readonly' : '' }}>
                      </div>
                    </div>
                  </div>
                </div>

                <h4 class="section-title">Nationality & Citizenship</h4>
                <div class="row g-4">
                  <div class="col-md-6">
                    <label class="hitech-label">Citizenship</label>
                    <input type="text" name="citizenship" class="hitech-input" value="{{ $user->citizenship }}" placeholder="e.g. Indian" {{ !$canEditPersonal ? 'readonly' : '' }}>
                  </div>
                  <div class="col-md-6">
                    <label class="hitech-label">Birth Country</label>
                    <input type="text" name="birth_country" class="hitech-input" value="{{ $user->birth_country }}" placeholder="e.g. India" {{ !$canEditPersonal ? 'readonly' : '' }}>
                  </div>
                </div>

              </div>
              <div class="hitech-footer">
                <div class="footer-content">
                  <div style="flex:1"></div>
                  <button type="button" onclick="goNext()" class="btn-next-hitech">Continue <i class="bx bx-right-arrow-alt"></i></button>
                </div>
              </div>
            </div>

            <!-- Step 2: Contact & Address -->
            <div id="contact-info" class="content onboarding-card">
              <div class="card-header-hitech">
                <h2>Contact & Address</h2>
                <p>How we can reach you and your emergency contacts.</p>
              </div>
              <div class="card-body-hitech">
                @if ($user->onboarding_resubmission_notes)
                  <div class="alert alert-warning border-0 d-flex align-items-start mb-4" style="background-color: #fffbeb; border-radius: 16px; padding: 1.5rem; border: 1px solid #fef3c7 !important;">
                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; min-width: 40px; background-color: #f59e0b !important; color: white;">
                      <i class="bx bx-error-alt fs-4"></i>
                    </div>
                    <div>
                      <h6 class="fw-extrabold text-warning mb-1" style="color: #92400e !important;">Action Required: HR Feedback</h6>
                      <p class="mb-0 small text-dark fw-semibold" style="line-height: 1.5;">{{ $user->onboarding_resubmission_notes }}</p>
                    </div>
                  </div>
                @endif
                
                <h4 class="section-title">Contact Methods</h4>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="hitech-label">Login/Official Email</label>
                        <input type="email" name="email" class="hitech-input" value="{{ $user->email }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Personal Email <span class="text-danger">*</span></label>
                        <input type="email" name="personal_email" id="personal_email" class="hitech-input" value="{{ $user->personal_email }}" placeholder="example@gmail.com" required {{ !$canEditContact ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="personal_email_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Please enter a valid personal email.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Primary/Personal Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" id="phone_input" class="hitech-input" value="{{ $user->phone }}" required {{ !$canEditContact ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="phone_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Phone must be exactly 10 digits.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Official Phone <span class="text-danger">*</span></label>
                        <input type="text" name="official_phone" id="official_phone" class="hitech-input" value="{{ $user->official_phone }}" placeholder="Office number" required {{ !$canEditContact ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="official_phone_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Official phone must be exactly 10 digits.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Alternate Phone</label>
                        <input type="text" name="alternate_number" class="hitech-input" value="{{ $user->alternate_number }}" {{ !$canEditContact ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Home Phone</label>
                        <input type="text" name="home_phone" class="hitech-input" value="{{ $user->home_phone }}" {{ !$canEditContact ? 'readonly' : '' }}>
                    </div>
                </div>

                <h4 class="section-title">Permanent Address</h4>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="hitech-label">Street Address <span class="text-danger">*</span></label>
                        <input type="text" name="perm_street" class="hitech-input" value="{{ $user->perm_street }}" placeholder="House No, Street name, Area" required {{ !$canEditContact ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">Building / Apt</label>
                        <input type="text" name="perm_building" class="hitech-input" value="{{ $user->perm_building }}" placeholder="Bldg name" {{ !$canEditContact ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">State <span class="text-danger">*</span></label>
                        <select name="perm_state" id="perm_state" class="form-select select2-hitech" required {{ !$canEditContact ? 'disabled' : '' }}>
                            <option value="{{ $user->perm_state }}" selected>{{ $user->perm_state ?: 'Select State' }}</option>
                        </select>
                        @if(!$canEditContact) <input type="hidden" name="perm_state" value="{{ $user->perm_state }}"> @endif
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">District <span class="text-danger">*</span></label>
                        <select name="perm_city" id="perm_city" class="form-select select2-hitech" required {{ !$canEditContact ? 'disabled' : '' }}>
                            <option value="{{ $user->perm_city }}" selected>{{ $user->perm_city ?: 'Select District' }}</option>
                        </select>
                        @if(!$canEditContact) <input type="hidden" name="perm_city" value="{{ $user->perm_city }}"> @endif
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">ZIP Code <span class="text-danger">*</span></label>
                        <input type="text" name="perm_zip" id="perm_zip" class="hitech-input" value="{{ $user->perm_zip }}" placeholder="6-digit PIN" required {{ !$canEditContact ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="perm_zip_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">ZIP code must be 6 digits.</div>
                    </div>
                    <div class="col-12">
                        <label class="hitech-label">Country <span class="text-danger">*</span></label>
                        <select name="perm_country" id="perm_country" class="form-select select2-hitech" required {{ !$canEditContact ? 'disabled' : '' }}>
                            <option value="India" {{ ($user->perm_country ?? 'India') == 'India' ? 'selected' : '' }}>India</option>
                            <option value="United States" {{ $user->perm_country == 'United States' ? 'selected' : '' }}>United States</option>
                            <option value="United Kingdom" {{ $user->perm_country == 'United Kingdom' ? 'selected' : '' }}>United Kingdom</option>
                        </select>
                        @if(!$canEditContact) <input type="hidden" name="perm_country" value="{{ $user->perm_country }}"> @endif
                    </div>
                </div>

                <h4 class="section-title d-flex justify-content-between align-items-center">
                    Current Address
                    <label class="custom-checkbox fw-normal" style="font-size: 0.85rem;">
                        <input type="checkbox" id="same_as_permanent" name="same_as_permanent" value="1">
                        Same as Permanent
                    </label>
                </h4>
                <div class="row g-3 mb-4" id="temporary-address-section">
                    <div class="col-12">
                        <label class="hitech-label">Street Address</label>
                        <input type="text" name="temp_street" class="hitech-input" value="{{ $user->temp_street }}" placeholder="House No, Street name, Area" {{ !$canEditContact ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">Building / Apt</label>
                        <input type="text" name="temp_building" class="hitech-input" value="{{ $user->temp_building }}" placeholder="Bldg name" {{ !$canEditContact ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">State</label>
                        <select name="temp_state" id="temp_state" class="form-select select2-hitech" {{ !$canEditContact ? 'disabled' : '' }}>
                            <option value="{{ $user->temp_state }}" selected>{{ $user->temp_state ?: 'Select State' }}</option>
                        </select>
                        @if(!$canEditContact) <input type="hidden" name="temp_state" value="{{ $user->temp_state }}"> @endif
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">District</label>
                        <select name="temp_city" id="temp_city" class="form-select select2-hitech" {{ !$canEditContact ? 'disabled' : '' }}>
                            <option value="{{ $user->temp_city }}" selected>{{ $user->temp_city ?: 'Select District' }}</option>
                        </select>
                        @if(!$canEditContact) <input type="hidden" name="temp_city" value="{{ $user->temp_city }}"> @endif
                    </div>
                    <div class="col-md-3">
                        <label class="hitech-label">ZIP Code</label>
                        <input type="text" name="temp_zip" id="temp_zip" class="hitech-input" value="{{ $user->temp_zip }}" placeholder="6-digit PIN" {{ !$canEditContact ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="temp_zip_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">ZIP code must be 6 digits.</div>
                    </div>
                    <div class="col-12">
                        <label class="hitech-label">Country</label>
                        <select name="temp_country" id="temp_country" class="form-select select2-hitech" {{ !$canEditContact ? 'disabled' : '' }}>
                            <option value="India" {{ ($user->temp_country ?? 'India') == 'India' ? 'selected' : '' }}>India</option>
                            <option value="United States" {{ $user->temp_country == 'United States' ? 'selected' : '' }}>United States</option>
                            <option value="United Kingdom" {{ $user->temp_country == 'United Kingdom' ? 'selected' : '' }}>United Kingdom</option>
                        </select>
                        @if(!$canEditContact) <input type="hidden" name="temp_country" value="{{ $user->temp_country }}"> @endif
                    </div>
                </div>

                <h4 class="section-title">Emergency Contact</h4>
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="hitech-label">Contact Name <span class="text-danger">*</span></label>
                        <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="hitech-input" value="{{ $user->emergency_contact_name }}" required {{ !$canEditContact ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="emergency_contact_name_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Name should only contain letters.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="hitech-label">Relationship <span class="text-danger">*</span></label>
                        <select name="emergency_contact_relation" id="emergency_contact_relation" class="hitech-input" required {{ !$canEditContact ? 'disabled' : '' }}>
                            <option value="">Select Relationship</option>
                            <option value="Father" {{ $user->emergency_contact_relation == 'Father' ? 'selected' : '' }}>Father</option>
                            <option value="Mother" {{ $user->emergency_contact_relation == 'Mother' ? 'selected' : '' }}>Mother</option>
                            <option value="Brother" {{ $user->emergency_contact_relation == 'Brother' ? 'selected' : '' }}>Brother</option>
                            <option value="Sister" {{ $user->emergency_contact_relation == 'Sister' ? 'selected' : '' }}>Sister</option>
                            <option value="Spouse" {{ $user->emergency_contact_relation == 'Spouse' ? 'selected' : '' }}>Spouse</option>
                            <option value="Friend" {{ $user->emergency_contact_relation == 'Friend' ? 'selected' : '' }}>Friend</option>
                            <option value="Other" {{ $user->emergency_contact_relation == 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @if(!$canEditContact) <input type="hidden" name="emergency_contact_relation" value="{{ $user->emergency_contact_relation }}"> @endif
                    </div>
                    <div class="col-md-4">
                        <label class="hitech-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="hitech-input" value="{{ $user->emergency_contact_phone }}" required {{ !$canEditContact ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="emergency_contact_phone_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Phone must be exactly 10 digits.</div>
                    </div>
                </div>

              </div>
              <div class="hitech-footer">
                <div class="footer-content">
                  <button type="button" onclick="goPrev()" class="btn-prev-hitech"><i class="bx bx-left-arrow-alt"></i> Back</button>
                  <button type="button" onclick="goNext()" class="btn-next-hitech">Continue <i class="bx bx-right-arrow-alt"></i></button>
                </div>
              </div>
            </div>

            <!-- Step 3: Bank Details -->
            <div id="bank-details" class="content onboarding-card">
              <div class="card-header-hitech">
                <h2>Banking Information</h2>
                <p>Details for your monthly salary disbursement.</p>
              </div>
              <div class="card-body-hitech">
                @if ($user->onboarding_resubmission_notes)
                  <div class="alert alert-warning border-0 d-flex align-items-start mb-4" style="background-color: #fffbeb; border-radius: 16px; padding: 1.5rem; border: 1px solid #fef3c7 !important;">
                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; min-width: 40px; background-color: #f59e0b !important; color: white;">
                      <i class="bx bx-error-alt fs-4"></i>
                    </div>
                    <div>
                      <h6 class="fw-extrabold text-warning mb-1" style="color: #92400e !important;">Action Required: HR Feedback</h6>
                      <p class="mb-0 small text-dark fw-semibold" style="line-height: 1.5;">{{ $user->onboarding_resubmission_notes }}</p>
                    </div>
                  </div>
                @endif
                <div class="security-notice">
                    <i class="bx bxs-shield-check security-notice-icon"></i>
                    <div>
                        <h4>Data Security</h4>
                        <p>Your financial information is encrypted and accessible only to the payroll department. Access will be granted after HR approval.</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="hitech-label">Bank Name <span class="text-danger">*</span></label>
                        <select name="bank_name" class="hitech-input" required {{ !$canEditBanking ? 'disabled' : '' }}>
                            <option value="">Select Bank</option>
                            @foreach($banks as $bank) <option value="{{ $bank }}" {{ (optional($user->bankAccount)->bank_name == $bank) ? 'selected' : '' }}>{{ $bank }}</option> @endforeach
                        </select>
                        @if(!$canEditBanking) <input type="hidden" name="bank_name" value="{{ optional($user->bankAccount)->bank_name }}"> @endif
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Account Holder Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" class="hitech-input" value="{{ optional($user->bankAccount)->account_name ?? $user->name }}" required {{ !$canEditBanking ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Account Number <span class="text-danger">*</span></label>
                        <div class="position-relative d-flex align-items-center">
                            <input type="password" name="account_number" id="account_number" class="hitech-input" value="{{ optional($user->bankAccount)->account_number }}" placeholder="•••• •••• ••••" required {{ !$canEditBanking ? 'readonly' : '' }}>
                            <i class="bx bx-show position-absolute end-0 me-3" id="toggle_acc_icon" style="cursor: pointer; color: #94a3b8; font-size: 1.2rem; transform: translateY(-2px);" onclick="toggleFieldVisibility('account_number', 'toggle_acc_icon')"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">Confirm Account Number <span class="text-danger">*</span></label>
                        <div class="position-relative d-flex align-items-center">
                            <input type="text" name="confirm_account_number" id="confirm_account_number" class="hitech-input" value="{{ optional($user->bankAccount)->account_number }}" placeholder="Confirm Account Number" required {{ !$canEditBanking ? 'readonly' : '' }}>
                            <i class="bx bx-hide position-absolute end-0 me-3" id="toggle_confirm_icon" style="cursor: pointer; color: #005a5a; font-size: 1.2rem; transform: translateY(-2px);" onclick="toggleFieldVisibility('confirm_account_number', 'toggle_confirm_icon')"></i>
                        </div>
                        <div class="invalid-feedback" id="account_number_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Account numbers do not match.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">IFSC / Routing Number <span class="text-danger">*</span></label>
                        <input type="text" name="ifsc_code" id="ifsc_code" class="hitech-input" value="{{ optional($user->bankAccount)->bank_code }}" placeholder="11 character code (e.g. SBIN0012345)" required {{ !$canEditBanking ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="ifsc_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid IFSC format (e.g. SBIN0123456).</div>
                    </div>
                    <div class="col-md-12 mt-2">
                        <label class="hitech-label">Upload Bank Cheque / Passbook <span class="text-danger">*</span></label>
                        <input type="file" id="cheque_file" name="cheque_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if(!$existingCheque) required @endif @if($existingCheque) data-existing="true" @endif {{ !$canEditBanking ? 'disabled' : '' }}>
                        <div class="invalid-feedback" id="cheque_file_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid file: PDF/JPG/PNG max 500KB.</div>
                        <p class="text-muted small mt-1">PDF, JPG, PNG only. Max 500KB.</p>
                    </div>
                </div>
              </div>
              <div class="hitech-footer">
                <div class="footer-content">
                  <button type="button" onclick="goPrev()" class="btn-prev-hitech"><i class="bx bx-left-arrow-alt"></i> Back</button>
                  <button type="button" onclick="goNext()" class="btn-next-hitech">Continue <i class="bx bx-right-arrow-alt"></i></button>
                </div>
              </div>
            </div>

            <!-- Step 4: Documents -->
            <div id="documents" class="content onboarding-card">
              <div class="card-header-hitech">
                <h2>Identity & Documents</h2>
                <p>Upload digital copies of your identification certificates.</p>
              </div>
              <div class="card-body-hitech">
                @if ($user->onboarding_resubmission_notes)
                  <div class="alert alert-warning border-0 d-flex align-items-start mb-4" style="background-color: #fffbeb; border-radius: 16px; padding: 1.5rem; border: 1px solid #fef3c7 !important;">
                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; min-width: 40px; background-color: #f59e0b !important; color: white;">
                      <i class="bx bx-error-alt fs-4"></i>
                    </div>
                    <div>
                      <h6 class="fw-extrabold text-warning mb-1" style="color: #92400e !important;">Action Required: HR Feedback</h6>
                      <p class="mb-0 small text-dark fw-semibold" style="line-height: 1.5;">{{ $user->onboarding_resubmission_notes }}</p>
                    </div>
                  </div>
                @endif
                
                <h4 class="section-title">Profile Photo</h4>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="hitech-label">Upload Display Picture <span class="text-danger">*</span></label>
                        @if($existingPhoto)
                            <div class="mb-3 d-flex align-items-center gap-3 p-3 border rounded-3 bg-light">
                                <img src="{{ $user->getProfilePicture() }}" alt="Profile" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 2px solid var(--primary-teal);">
                                <div>
                                    <span class="d-block fw-bold small text-dark">Current Photo</span>
                                    <a href="{{ $user->getProfilePicture() }}" target="_blank" class="text-info small fw-bold"><i class="bx bx-show me-1"></i>View Full Size</a>
                                </div>
                            </div>
                        @endif
                        @if($canEditDocs)
                        <input type="file" id="photo_input" name="photo" class="hitech-input" accept=".jpg,.jpeg,.png" @if(!$existingPhoto) required @endif @if($existingPhoto) data-existing="true" data-file-url="{{ $user->getProfilePicture() }}" @endif>
                        <div class="invalid-feedback" id="photo_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid photo: JPG/PNG max 100KB.</div>
                        <p class="text-muted small mt-1">JPG, PNG only. Max 100KB.</p>
                        @endif
                    </div>
                </div>

                <h4 class="section-title">National Identity</h4>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="hitech-label">Aadhaar Card Number <span class="text-danger">*</span></label>
                        <input type="text" name="aadhaar_no" id="aadhaar_no" class="hitech-input mb-2" value="{{ $user->aadhaar_no }}" placeholder="12-digit number" required {{ !$canEditDocs ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="aadhaar_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Aadhaar must be exactly 12 digits.</div>
                        @if($existingAadhaar)
                           <div class="mb-2"><a href="{{ $user->getAadhaarUrl() }}" target="_blank" class="badge bg-label-info"><i class="bx bx-link-external me-1"></i>View Uploaded Aadhaar</a></div>
                        @endif
                        @if($canEditDocs)
                           <input type="file" id="aadhaar_file" name="aadhaar_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if(!$existingAadhaar) required @endif @if($existingAadhaar) data-existing="true" data-file-url="{{ $user->getAadhaarUrl() }}" @endif>
                           <div class="invalid-feedback" id="aadhaar_file_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid file: PDF/JPG/PNG max 300KB.</div>
                           <p class="text-muted small mt-1">PDF, JPG, PNG only. Max 300KB.</p>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="hitech-label">PAN Card Number <span class="text-danger">*</span></label>
                        <input type="text" name="pan_no" id="pan_no" class="hitech-input mb-2" value="{{ $user->pan_no }}" placeholder="10-digit number (e.g. ABCDE1234F)" required {{ !$canEditDocs ? 'readonly' : '' }}>
                        <div class="invalid-feedback" id="pan_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid PAN format or details.</div>
                        @if($existingPan)
                           <div class="mb-2"><a href="{{ $user->getPanUrl() }}" target="_blank" class="badge bg-label-info"><i class="bx bx-link-external me-1"></i>View Uploaded PAN</a></div>
                        @endif
                        @if($canEditDocs)
                           <input type="file" id="pan_file" name="pan_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if(!$existingPan) required @endif @if($existingPan) data-existing="true" data-file-url="{{ $user->getPanUrl() }}" @endif>
                           <div class="invalid-feedback" id="pan_file_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid file: PDF/JPG/PNG max 300KB.</div>
                           <p class="text-muted small mt-1">PDF, JPG, PNG only. Max 300KB.</p>
                        @endif
                    </div>
                </div>

                <h4 class="section-title">Educational Documents</h4>
                <div class="row g-4 mb-4">
                   <!-- Matric -->
                    <div class="col-md-6 border-bottom pb-4">
                        <label class="hitech-label fw-bold">10th Marksheet (Matric) <span class="text-muted small italic">(Optional Details)</span></label>
                        <input type="text" name="matric_university" id="matric_university" class="hitech-input mb-2" value="{{ $user->matric_university }}" placeholder="Board / University Name" {{ !$canEditDocs ? 'readonly' : '' }}>
                        <input type="text" name="matric_marksheet_no" id="matric_marksheet_no" class="hitech-input mb-2" value="{{ $user->matric_marksheet_no }}" placeholder="Marksheet / Serial Number" {{ !$canEditDocs ? 'readonly' : '' }}>
                        @if($existingMatric)
                           <div class="mb-2"><a href="{{ $user->getMatricUrl() }}" target="_blank" class="badge bg-label-info"><i class="bx bx-link-external me-1"></i>View Uploaded Certificate</a></div>
                        @endif
                        <label class="hitech-label small mb-1">Upload Certificate <span class="text-danger">*</span></label>
                        @if($canEditDocs)
                           <input type="file" id="matric_file" name="matric_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if(!$existingMatric) required @endif @if($existingMatric) data-existing="true" data-file-url="{{ $user->getMatricUrl() }}" @endif>
                           <div class="invalid-feedback" id="matric_file_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid file: PDF/JPG/PNG max 300KB.</div>
                           <p class="text-muted small mt-1">Max 300KB.</p>
                        @endif
                   </div>
                   <!-- Intermediate -->
                   <div class="col-md-6 border-bottom pb-4">
                        <label class="hitech-label fw-bold">12th Marksheet (Intermediate) <span class="text-muted small italic">(Optional Details)</span></label>
                        <input type="text" name="inter_university" id="inter_university" class="hitech-input mb-2" value="{{ $user->inter_university }}" placeholder="Board / University Name" {{ !$canEditDocs ? 'readonly' : '' }}>
                        <input type="text" name="inter_marksheet_no" id="inter_marksheet_no" class="hitech-input mb-2" value="{{ $user->inter_marksheet_no }}" placeholder="Marksheet / Serial Number" {{ !$canEditDocs ? 'readonly' : '' }}>
                        <label class="hitech-label small mb-1">Upload Certificate <span class="text-danger">*</span></label>
                        @if($canEditDocs)
                           <input type="file" id="inter_file" name="inter_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if(!$existingInter) required @endif @if($existingInter) data-existing="true" data-file-url="{{ $user->getInterUrl() }}" @endif>
                           <div class="invalid-feedback" id="inter_file_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid file: PDF/JPG/PNG max 300KB.</div>
                           <p class="text-muted small mt-1">Max 300KB.</p>
                        @endif
                   </div>
                    <!-- Bachelor -->
                   <div class="col-md-6">
                        <label class="hitech-label fw-bold">Graduation Marksheet <span class="text-muted small italic">(Optional Details)</span></label>
                        <input type="text" name="bachelor_university" id="bachelor_university" class="hitech-input mb-2" value="{{ $user->bachelor_university }}" placeholder="University Name" {{ !$canEditDocs ? 'readonly' : '' }}>
                        <input type="text" name="bachelor_marksheet_no" id="bachelor_marksheet_no" class="hitech-input mb-2" value="{{ $user->bachelor_marksheet_no }}" placeholder="Degree / Marksheet Number" {{ !$canEditDocs ? 'readonly' : '' }}>
                        <label class="hitech-label small mb-1">Upload Certificate <span class="text-danger">*</span></label>
                        @if($canEditDocs)
                           <input type="file" id="graduation_file" name="graduation_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if(!$existingBachelor) required @endif @if($existingBachelor) data-existing="true" data-file-url="{{ $user->getBachelorUrl() }}" @endif>
                           <div class="invalid-feedback" id="graduation_file_error" style="display:none; font-size: 10px; color: #ef4444; font-weight: 700; margin-top: 4px;">Invalid file: PDF/JPG/PNG max 300KB.</div>
                           <p class="text-muted small mt-1">Max 300KB.</p>
                        @endif
                   </div>
                   <!-- Master -->
                   <div class="col-md-6">
                        <label class="hitech-label fw-bold">Post Graduation Marksheet</label>
                        <input type="text" name="master_university" class="hitech-input mb-2" value="{{ $user->master_university }}" placeholder="University Name" {{ !$canEditDocs ? 'readonly' : '' }}>
                        <input type="text" name="master_marksheet_no" class="hitech-input mb-2" value="{{ $user->master_marksheet_no }}" placeholder="Degree / Marksheet Number" {{ !$canEditDocs ? 'readonly' : '' }}>
                        @if($canEditDocs)
                           <input type="file" name="master_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if($existingMaster) data-existing="true" data-file-url="{{ $user->getMasterUrl() }}" @endif>
                           <p class="text-muted small mt-1">Max 300KB.</p>
                        @endif
                      <!-- Experience Certificate -->
                   <div class="col-md-12 mt-4 pt-4 border-top">
                        <label class="hitech-label fw-bold">Experience Certificate</label>
                        <input type="text" name="experience_certificate_no" id="experience_certificate_no" class="hitech-input mb-2" value="{{ $user->experience_certificate_no }}" placeholder="Experience Certificate / Relieving Letter Number" {{ !$canEditDocs ? 'readonly' : '' }}>
                        @if($existingExperience)
                           <div class="mb-2"><a href="{{ $user->getExperienceUrl() }}" target="_blank" class="badge bg-label-info"><i class="bx bx-link-external me-1"></i>View Uploaded Experience Certificate</a></div>
                        @endif
                        @if($canEditDocs)
                           <input type="file" id="experience_file" name="experience_file" class="hitech-input" accept=".pdf,.png,.jpg,.jpeg" @if($existingExperience) data-existing="true" data-file-url="{{ $user->getExperienceUrl() }}" @endif>
                           <p class="text-muted small mt-1">Max 300KB.</p>
                        @endif
                   </div>
                </div>
                </div>

                <h4 class="section-title">Travel & Visa (Optional)</h4>
                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 1rem;">Complete these if relevant to your employment or citizenship status.</p>
                    </div>
                    <div class="col-md-4">
                        <label class="hitech-label">Passport Number</label>
                        <input type="text" name="passport_no" class="hitech-input" value="{{ $user->passport_no }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-4">
                        <label class="hitech-label">Issue Date</label>
                        <input type="date" name="passport_issue_date" class="hitech-input" value="{{ $user->passport_issue_date ? \Carbon\Carbon::parse($user->passport_issue_date)->format('Y-m-d') : '' }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-4">
                        <label class="hitech-label">Expiry Date</label>
                        <input type="date" name="passport_expiry_date" class="hitech-input" value="{{ $user->passport_expiry_date ? \Carbon\Carbon::parse($user->passport_expiry_date)->format('Y-m-d') : '' }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                    
                    <div class="col-md-4 mt-3">
                        <label class="hitech-label">Visa Type</label>
                        <input type="text" name="visa_type" class="hitech-input" value="{{ $user->visa_type }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-4 mt-3">
                        <label class="hitech-label">Visa Issue Date</label>
                        <input type="date" name="visa_issue_date" class="hitech-input" value="{{ $user->visa_issue_date ? \Carbon\Carbon::parse($user->visa_issue_date)->format('Y-m-d') : '' }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-4 mt-3">
                        <label class="hitech-label">Visa Expiry Date</label>
                        <input type="date" name="visa_expiry_date" class="hitech-input" value="{{ $user->visa_expiry_date ? \Carbon\Carbon::parse($user->visa_expiry_date)->format('Y-m-d') : '' }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>

                    <div class="col-md-4 mt-3">
                        <label class="hitech-label">FRRO Registration</label>
                        <input type="text" name="frro_registration" class="hitech-input" value="{{ $user->frro_registration }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-4 mt-3">
                        <label class="hitech-label">FRRO Issue Date</label>
                        <input type="date" name="frro_issue_date" class="hitech-input" value="{{ $user->frro_issue_date ? \Carbon\Carbon::parse($user->frro_issue_date)->format('Y-m-d') : '' }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-4 mt-3">
                        <label class="hitech-label">FRRO Expiry Date</label>
                        <input type="date" name="frro_expiry_date" class="hitech-input" value="{{ $user->frro_expiry_date ? \Carbon\Carbon::parse($user->frro_expiry_date)->format('Y-m-d') : '' }}" {{ !$canEditDocs ? 'readonly' : '' }}>
                    </div>
                </div>

              </div>
              <div class="hitech-footer">
                <div class="footer-content">
                  <button type="button" onclick="goPrev()" class="btn-prev-hitech"><i class="bx bx-left-arrow-alt"></i> Back</button>
                  <button type="button" onclick="goNext()" class="btn-next-hitech">Continue <i class="bx bx-right-arrow-alt"></i></button>
                </div>
              </div>
            </div>

            <!-- Step 5: Consent -->
            <div id="legal-consent" class="content onboarding-card">
              <div class="card-header-hitech">
                <h2>Declaration & Review</h2>
                <p>Please verify all information before final submission.</p>
              </div>
              <div class="card-body-hitech">
                @if ($user->onboarding_resubmission_notes)
                  <div class="alert alert-warning border-0 d-flex align-items-start mb-4" style="background-color: #fffbeb; border-radius: 16px; padding: 1.5rem; border: 1px solid #fef3c7 !important;">
                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; min-width: 40px; background-color: #f59e0b !important; color: white;">
                      <i class="bx bx-error-alt fs-4"></i>
                    </div>
                    <div>
                      <h6 class="fw-extrabold text-warning mb-1" style="color: #92400e !important;">Action Required: HR Feedback</h6>
                      <p class="mb-0 small text-dark fw-semibold" style="line-height: 1.5;">{{ $user->onboarding_resubmission_notes }}</p>
                    </div>
                  </div>
                @endif
                <div class="security-notice mb-8">
                    <i class="bx bx-check-shield security-notice-icon"></i>
                    <div>
                        <h4>Final Declaration</h4>
                        <p>I hereby declare that the information provided is true and correct to the best of my knowledge and belief. I understand that any misrepresentation may result in termination of onboarding.</p>
                    </div>
                </div>
                <div class="p-4 border rounded" style="background-color: #F8FAFC; border-color: #E2E8F0;">
                  <label class="custom-checkbox fw-bold text-dark w-100" style="font-size: 0.95rem;">
                      <input type="checkbox" id="consent_accepted" name="consent_accepted" required>
                      I confirm that all banking, personal, and identity details are accurate.
                  </label>
                </div>
              </div>
              <div class="hitech-footer">
                <div class="footer-content">
                  <div class="saving-indicator" id="saving_indicator">
                    <div class="saving-spinner"></div>
                    <span>Saving...</span>
                  </div>
                  <button type="button" onclick="goPrev()" class="btn-prev-hitech"><i class="bx bx-left-arrow-alt"></i> Back</button>
                  @if(!$isSubmitted)
                  <button type="submit" class="btn-next-hitech">Complete Onboarding <i class="bx bx-check-circle"></i></button>
                  @else
                  <button type="button" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn-next-hitech" style="background: #94A3B8; color: white;">Logout <i class="bx bx-log-out"></i></button>
                  @endif
                </div>
              </div>
            </div>
          </form>
        </div>
    </div>
</div>
@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/bs-stepper/bs-stepper.js',
    'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
<script>
let stepperEl;
let stepper;
let currentStepIdx = 0; // Track step manually (0-based)
function ensureStepper() {
    if (!stepper) {
        stepperEl = document.querySelector('#wizard-onboarding');
        if (stepperEl && window.Stepper) {
            stepper = new Stepper(stepperEl, {
                linear: true,
                animation: true
            });
        }
    }
}

function showErrorSwal(msg) {
    Swal.fire({
        title: 'Error',
        text: msg,
        icon: 'error',
        customClass: {
            confirmButton: 'btn btn-primary'
        },
        buttonsStyling: false
    });
}
function showSuccessSwal(msg) {
    Swal.fire({
        title: 'Success',
        text: msg,
        icon: 'success',
        customClass: {
            confirmButton: 'btn btn-primary'
        },
        buttonsStyling: false
    });
}
function showWarningSwal(msg) {
    return Swal.fire({
        title: 'Warning',
        text: msg,
        icon: 'warning',
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    });
}

document.addEventListener('DOMContentLoaded', function () {
    ensureStepper();

    window.toggleFieldVisibility = function(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bx-show', 'bx-hide');
            icon.style.color = '#005a5a';
        } else {
            input.type = 'password';
            icon.classList.replace('bx-hide', 'bx-show');
            icon.style.color = '#94a3b8';
        }
    }

    window.goNext = async function() {
        ensureStepper();
        if (!stepper) return;
        if (await validateCurrentStep()) {
            // Auto-save current step BEFORE moving forward (await for reliability)
            try {
                const autoSaveResult = await autoSaveStep(currentStepIdx);
                if (!autoSaveResult.success) {
                   showWarningSwal(autoSaveResult.message || 'Unable to save progress. Please check your connection.');
                    return;
                }
            } catch(e) {
                console.warn('Auto-save error:', e);
                showErrorSwal('We encountered an error while saving your data. Check your internet or session.');
                return;
            }
            currentStepIdx++;
            stepper.next();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    window.goPrev = function() {
        ensureStepper();
        if (!stepper) return;
        if (currentStepIdx > 0) currentStepIdx--;
        stepper.previous();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    async function autoSaveStep(passedStepIdx = null) {
        const step = passedStepIdx !== null ? passedStepIdx : currentStepIdx;
        const container = stepperEl.querySelectorAll('.content')[step];
        if (!container) { console.warn('autoSave: no container for step', step); return false; }
        
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        
        container.querySelectorAll('input:not([type="file"]), select, textarea').forEach(el => {
            if (el.name) {
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) formData.append(el.name, el.value);
                } else {
                    formData.append(el.name, el.value);
                }
            }
        });

        // Show global indicator
        const indicator = document.getElementById('saving_indicator');
        if(indicator) indicator.style.display = 'flex';

        try {
            const response = await fetch('{{ route("onboarding.autoSave") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Header-Auto-Save': 'true',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                keepalive: true
            });
            const result = await response.json();
            if (result.success) {
                console.log('Progress auto-saved for step:', step);
                return { success: true };
            }
            return { success: false, message: result.message };
        } catch (e) {
            console.error('Auto-save failed:', e);
            // On network error, we don't necessarily want to block the user 
            // but we should warn them if they try to navigate.
            return { success: true }; 
        } finally {
            // Hide after a small delay
            setTimeout(() => { if(indicator) indicator.style.display = 'none'; }, 1000);
        }
    }

    // Debounced field-level save
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    const isSubmitted = {{ $isSubmitted ? 'true' : 'false' }};

    if (!isSubmitted) {
        const debouncedSave = debounce(() => autoSaveStep(), 2000);

        // Attach to all inputs for real-time saving
        document.querySelectorAll('#onboardingForm input, #onboardingForm select, #onboardingForm textarea').forEach(el => {
            el.addEventListener('change', debouncedSave);
            if (el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'number')) {
                el.addEventListener('keyup', debouncedSave);
            }
        });
    }

    // AJAX File Upload Logic
    function initFileUploads() {
        if (isSubmitted) return; // Prevent uploading if submitted
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            // Add UI wrapper for progress
            const wrapper = document.createElement('div');
            wrapper.className = 'upload-progress-wrapper';
            wrapper.id = `progress_wrapper_${input.id || input.name}`;
            wrapper.innerHTML = `
                <div class="progress-hitech" style="display: none;">
                    <div class="progress-bar-hitech"></div>
                </div>
                <div class="upload-status-compact" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bx bxs-check-circle status-icon-success"></i>
                        <span class="text-success fw-bold" style="font-size: 11px;">File Uploaded</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="#" target="_blank" class="view-doc-link">
                            <i class="bx bx-show"></i> View
                        </a>
                        <button type="button" class="replace-doc-btn">
                            <i class="bx bx-refresh"></i> Replace
                        </button>
                    </div>
                </div>
                <div class="badge-error mt-1" style="display: none;"></div>
            `;
            input.parentNode.insertBefore(wrapper, input.nextSibling);

            const statusCompact = wrapper.querySelector('.upload-status-compact');
            const viewLink = wrapper.querySelector('.view-doc-link');
            const replaceBtn = wrapper.querySelector('.replace-doc-btn');

            // --- Handle Initial Flow ---
            // If data-existing is true (set by PHP), show the compact UI immediately
            if (input.dataset.existing === 'true' || input.getAttribute('data-existing') === 'true') {
                const existingUrl = input.dataset.fileUrl || ''; // We need to pass this from PHP ideally
                wrapper.style.display = 'block';
                statusCompact.style.display = 'flex';
                input.style.display = 'none';
                
                // If we don't have the URL in data-file-url, we might need to find the PHP-rendered link
                // but it's cleaner to just pass it in data-file-url in the PHP template.
                if (existingUrl) viewLink.href = existingUrl;
                else {
                    // Fallback: try to find the link rendered by PHP nearby
                    const prevLink = input.parentElement.querySelector('a[target="_blank"]');
                    if (prevLink) {
                        viewLink.href = prevLink.href;
                        prevLink.parentElement.style.display = 'none'; // Hide the raw PHP link
                    }
                }

                replaceBtn.onclick = () => {
                    statusCompact.style.display = 'none';
                    input.style.display = 'block';
                    input.value = '';
                    input.removeAttribute('data-uploaded');
                    input.removeAttribute('data-existing');
                    input.dataset.existing = 'false';
                };
            }

            input.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    uploadFileAJAX(this);
                }
            });
        });
    }

    function uploadFileAJAX(input) {
        const file = input.files[0];
        const fieldName = input.name;
        const wrapperId = `progress_wrapper_${input.id || input.name}`;
        const wrapper = document.getElementById(wrapperId);
        const barContainer = wrapper.querySelector('.progress-hitech');
        const bar = wrapper.querySelector('.progress-bar-hitech');
        const statusCompact = wrapper.querySelector('.upload-status-compact');
        const errorBadge = wrapper.querySelector('.badge-error');
        const viewLink = wrapper.querySelector('.view-doc-link');
        const replaceBtn = wrapper.querySelector('.replace-doc-btn');

        // Reset UI
        wrapper.style.display = 'block';
        barContainer.style.display = 'block';
        bar.style.width = '0%';
        statusCompact.style.display = 'none';
        errorBadge.style.display = 'none';
        input.style.display = 'none'; // Hide actual input during upload

        const formData = new FormData();
        formData.append('file', file);
        formData.append('field', fieldName);
        formData.append('_token', '{{ csrf_token() }}');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '{{ route("onboarding.uploadFile") }}', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                bar.style.width = percent + '%';
            }
        };

        xhr.onload = function() {
            barContainer.style.display = 'none'; // Hide bar after finish
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    statusCompact.style.display = 'flex';
                    viewLink.href = response.url;
                    input.dataset.uploaded = 'true';
                    input.dataset.fileUrl = response.url;
                    input.setAttribute('data-existing', 'true');
                    input.removeAttribute('required');
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                    
                    // Setup replace button
                    replaceBtn.onclick = () => {
                        statusCompact.style.display = 'none';
                        input.style.display = 'block';
                        input.value = '';
                        input.removeAttribute('data-uploaded');
                        input.removeAttribute('data-existing');
                    };

                    autoSaveStep();
                } else {
                    input.style.display = 'block';
                    errorBadge.style.display = 'inline-block';
                    errorBadge.textContent = response.message || 'Error';
                }
            } else {
                input.style.display = 'block';
                errorBadge.style.display = 'inline-block';
                errorBadge.textContent = 'Upload failed';
            }
        };

        xhr.onerror = function() {
            input.style.display = 'block';
            barContainer.style.display = 'none';
            errorBadge.style.display = 'inline-block';
            errorBadge.textContent = 'Network error';
        };

        xhr.send(formData);
    }

    initFileUploads();

    // Update custom indicator on change
    stepperEl.addEventListener('show.bs-stepper', function (event) {
        const stepIdx = event.detail.indexStep + 1;
        const circles = document.querySelectorAll('.step-circle-custom');
        const lines = document.querySelectorAll('.stepper-line-custom');

        circles.forEach((c, idx) => {
            const stepWrapper = c.closest('.step-custom');
            if (idx < stepIdx - 1) {
                stepWrapper.classList.add('completed');
                stepWrapper.classList.remove('active');
            } else if (idx === stepIdx - 1) {
                stepWrapper.classList.add('active');
                stepWrapper.classList.remove('completed');
            } else {
                stepWrapper.classList.remove('active', 'completed');
            }
        });

        lines.forEach((l, idx) => {
            if (idx < stepIdx - 1) l.classList.add('active');
            else l.classList.remove('active');
        });
    });

    // --- Select2 Initialization ---
    const initSelect2 = () => {
        $('.select2-hitech').each(function() {
            $(this).select2({
                dropdownParent: $(this).parent(),
                width: '100%'
            });
        });
    };
    initSelect2();

    // --- Address Cascading Data ---
    const addressData = {
        "India": {
            "Andaman and Nicobar Islands": ["Nicobar", "North and Middle Andaman", "South Andaman"],
            "Andhra Pradesh": ["Anantapur", "Chittoor", "East Godavari", "Guntur", "Krishna", "Kurnool", "Prakasam", "Srikakulam", "Sri Potti Sriramulu Nellore", "Visakhapatnam", "Vizianagaram", "West Godavari", "YSR Kadapa"],
            "Arunachal Pradesh": ["Anjaw", "Changlang", "Dibang Valley", "East Kameng", "East Siang", "Kamle", "Kra Daadi", "Kurung Kumey", "Lepa Rada", "Lohit", "Longding", "Lower Dibang Valley", "Lower Siang", "Lower Subansiri", "Namsai", "Pakke Kessang", "Papum Pare", "Shi Yomi", "Siang", "Tawang", "Tirap", "Upper Siang", "Upper Subansiri", "West Kameng", "West Siang"],
            "Assam": ["Baksa", "Barpeta", "Biswanath", "Bongaigaon", "Cachar", "Charaideo", "Chirang", "Darrang", "Dhemaji", "Dhubri", "Dibrugarh", "Dima Hasao", "Goalpara", "Golaghat", "Hailakandi", "Hojai", "Jorhat", "Kamrup", "Kamrup Metropolitan", "Karbi Anglong", "Karimganj", "Kokrajhar", "Lakhimpur", "Majuli", "Morigaon", "Nagaon", "Nalbari", "Sivasagar", "Sonitpur", "South Salmara-Mankachar", "Tinsukia", "Udalguri", "West Karbi Anglong"],
            "Bihar": ["Araria", "Arwal", "Aurangabad", "Banka", "Begusarai", "Bhagalpur", "Bhojpur", "Buxar", "Darbhanga", "East Champaran", "Gaya", "Gopalganj", "Jamui", "Jehanabad", "Kaimur", "Katihar", "Khagaria", "Kishanganj", "Lakhisarai", "Madhepura", "Madhubani", "Munger", "Muzaffarpur", "Nalanda", "Nawada", "Patna", "Purnia", "Rohtas", "Saharsa", "Samastipur", "Saran", "Sheikhpura", "Sheohar", "Sitamarhi", "Siwan", "Supaul", "Vaishali", "West Champaran"],
            "Chandigarh": ["Chandigarh"],
            "Chhattisgarh": ["Balod", "Baloda Bazar", "Balrampur", "Bastar", "Bemetara", "Bijapur", "Bilaspur", "Dantewada", "Dhamtari", "Durg", "Gariaband", "Gaurela-Pendra-Marwahi", "Janjgir-Champa", "Jashpur", "Kabirdham", "Kanker", "Kondagaon", "Korba", "Koriya", "Mahasamund", "Mungeli", "Narayanpur", "Raigarh", "Raipur", "Rajnandgaon", "Sukma", "Surajpur", "Surguja"],
            "Dadra and Nagar Haveli and Daman and Diu": ["Dadra and Nagar Haveli", "Daman", "Diu"],
            "Delhi": ["Central Delhi", "East Delhi", "New Delhi", "North Delhi", "North East Delhi", "North West Delhi", "Shahdara", "South Delhi", "South East Delhi", "South West Delhi", "West Delhi"],
            "Goa": ["North Goa", "South Goa"],
            "Gujarat": ["Ahmedabad", "Amreli", "Anand", "Aravalli", "Banaskantha", "Bharuch", "Bhavnagar", "Botad", "Chhota Udepur", "Dahod", "Dang", "Devbhumi Dwarka", "Gandhinagar", "Gir Somnath", "Jamnagar", "Junagadh", "Kheda", "Kutch", "Mahisagar", "Mehsana", "Morbi", "Narmada", "Navsari", "Panchmahal", "Patan", "Porbandar", "Rajkot", "Sabarkantha", "Surat", "Surendranagar", "Tapi", "Vadodara", "Valsad"],
            "Haryana": ["Ambala", "Bhiwani", "Charkhi Dadri", "Faridabad", "Fatehabad", "Gurugram", "Hisar", "Jhajjar", "Jind", "Kaithal", "Karnal", "Kurukshetra", "Mahendragarh", "Mewat", "Palwal", "Panchkula", "Panipat", "Rewari", "Rohtak", "Sirsa", "Sonipat", "Yamunanagar"],
            "Himachal Pradesh": ["Bilaspur", "Chamba", "Hamirpur", "Kangra", "Kinnaur", "Kullu", "Lahaul and Spiti", "Mandi", "Shimla", "Sirmaur", "Solan", "Una"],
            "Jammu and Kashmir": ["Anantnag", "Bandipora", "Baramulla", "Budgam", "Doda", "Ganderbal", "Jammu", "Kathua", "Kishtwar", "Kulgam", "Kupwara", "Poonch", "Pulwama", "Rajouri", "Ramban", "Reasi", "Samba", "Shopian", "Srinagar", "Udhampur"],
            "Jharkhand": ["Bokaro", "Chatra", "Deoghar", "Dhanbad", "Dumka", "East Singhbhum", "Garhwa", "Giridih", "Godda", "Gumla", "Hazaribagh", "Jamtara", "Khunti", "Koderma", "Latehar", "Lohardaga", "Pakur", "Palamu", "Ramgarh", "Ranchi", "Sahebganj", "Seraikela-Kharsawan", "Simdega", "West Singhbhum"],
            "Karnataka": ["Bagalkot", "Ballari", "Belagavi", "Bengaluru Rural", "Bengaluru Urban", "Bidar", "Chamarajanagar", "Chikkaballapur", "Chikkamagaluru", "Chitradurga", "Dakshina Kannada", "Davanagere", "Dharwad", "Gadag", "Hassan", "Haveri", "Kalaburagi", "Kodagu", "Kolar", "Koppal", "Mandya", "Mysuru", "Raichur", "Ramanagara", "Shivamogga", "Tumakuru", "Udupi", "Uttara Kannada", "Vijayapura", "Vijayanagara", "Yadgir"],
            "Kerala": ["Alappuzha", "Ernakulam", "Idukki", "Kannur", "Kasaragod", "Kollam", "Kottayam", "Kozhikode", "Malappuram", "Palakkad", "Pathanamthitta", "Thiruvananthapuram", "Thrissur", "Wayanad"],
            "Ladakh": ["Kargil", "Leh"],
            "Lakshadweep": ["Lakshadweep"],
            "Madhya Pradesh": ["Agar Malwa", "Alirajpur", "Anuppur", "Ashoknagar", "Balaghat", "Barwani", "Betul", "Bhind", "Bhopal", "Burhanpur", "Chhatarpur", "Chhindwara", "Damoh", "Datia", "Dewas", "Dhar", "Dindori", "Guna", "Gwalior", "Harda", "Hoshangabad", "Indore", "Jabalpur", "Jhabua", "Katni", "Khandwa", "Khargone", "Mandla", "Mandsaur", "Morena", "Narsinghpur", "Neemuch", "Panna", "Raisen", "Rajgarh", "Ratlam", "Rewa", "Sagar", "Satna", "Sehore", "Seoni", "Shahdol", "Shajapur", "Sheopur", "Shivpuri", "Sidhi", "Singrauli", "Tikamgarh", "Ujjain", "Umaria", "Vidisha"],
            "Maharashtra": ["Ahmednagar", "Akola", "Amravati", "Aurangabad", "Beed", "Bhandara", "Buldhana", "Chandrapur", "Dhule", "Gadchiroli", "Gondia", "Hingoli", "Jalgaon", "Jalna", "Kolhapur", "Latur", "Mumbai City", "Mumbai Suburban", "Nagpur", "Nanded", "Nandurbar", "Nashik", "Osmanabad", "Palghar", "Parbhani", "Pune", "Raigad", "Ratnagiri", "Sangli", "Satara", "Sindhudurg", "Solapur", "Thane", "Wardha", "Washim", "Yavatmal"],
            "Manipur": ["Bishnupur", "Chandel", "Churachandpur", "Imphal East", "Imphal West", "Jiribam", "Kakching", "Kamjong", "Kangpokpi", "Noney", "Pherzawl", "Senapati", "Tamenglong", "Tengnoupal", "Thoubal", "Ukhrul"],
            "Meghalaya": ["East Garo Hills", "East Jaintia Hills", "East Khasi Hills", "North Garo Hills", "Ri Bhoi", "South Garo Hills", "South West Garo Hills", "South West Khasi Hills", "West Garo Hills", "West Jaintia Hills", "West Khasi Hills"],
            "Mizoram": ["Aizawl", "Champhai", "Hnahthial", "Khawzawl", "Kolasib", "Lawngtlai", "Lunglei", "Mamit", "Saiha", "Saitual", "Serchhip"],
            "Nagaland": ["Dimapur", "Kiphire", "Kohima", "Longleng", "Mokokchung", "Mon", "Noklak", "Peren", "Phek", "Tuensang", "Wokha", "Zunheboto"],
            "Odisha": ["Angul", "Balangir", "Balasore", "Bargarh", "Bhadrak", "Baudh", "Cuttack", "Deogarh", "Dhenkanal", "Gajapati", "Ganjam", "Jagatsinghapur", "Jajpur", "Jharsuguda", "Kalahandi", "Kandhamal", "Kendrapara", "Kendujhar", "Khordha", "Koraput", "Malkangiri", "Mayurbhanj", "Nabarangpur", "Nayagarh", "Nuapada", "Puri", "Rayagada", "Sambalpur", "Sonepur", "Sundargarh"],
            "Puducherry": ["Karaikal", "Mahe", "Puducherry", "Yanam"],
            "Punjab": ["Amritsar", "Barnala", "Bathinda", "Faridkot", "Fatehgarh Sahib", "Fazilka", "Ferozepur", "Gurdaspur", "Hoshiarpur", "Jalandhar", "Kapurthala", "Ludhiana", "Mansa", "Moga", "Muktsar", "Pathankot", "Patiala", "Rupnagar", "Sahibzada Ajit Singh Nagar", "Sangrur", "Shahid Bhagat Singh Nagar", "Sri Muktsar Sahib", "Tarn Taran"],
            "Rajasthan": ["Ajmer", "Alwar", "Banswara", "Baran", "Barmer", "Bharatpur", "Bhilwara", "Bikaner", "Bundi", "Chittorgarh", "Churu", "Dausa", "Dholpur", "Dungarpur", "Hanumangarh", "Jaipur", "Jaisalmer", "Jalore", "Jhalawar", "Jhunjhunu", "Jodhpur", "Karauli", "Kota", "Nagaur", "Pali", "Pratapgarh", "Rajsamand", "Sawai Madhopur", "Sikar", "Sirohi", "Sri Ganganagar", "Tonk", "Udaipur"],
            "Sikkim": ["East Sikkim", "North Sikkim", "South Sikkim", "West Sikkim"],
            "Tamil Nadu": ["Ariyalur", "Chengalpattu", "Chennai", "Coimbatore", "Cuddalore", "Dharmapuri", "Dindigul", "Erode", "Kallakurichi", "Kanchipuram", "Kanyakumari", "Karur", "Krishnagiri", "Madurai", "Mayiladuthurai", "Nagapattinam", "Namakkal", "Nilgiris", "Perambalur", "Pudukkottai", "Ramanathapuram", "Ranipet", "Salem", "Sivaganga", "Tenkasi", "Thanjavur", "Theni", "Thoothukudi", "Tiruchirappalli", "Tirunelveli", "Tirupathur", "Tiruppur", "Tiruvallur", "Tiruvannamalai", "Tiruvarur", "Vellore", "Viluppuram", "Virudhunagar"],
            "Telangana": ["Adilabad", "Bhadradri Kothagudem", "Hyderabad", "Jagtial", "Jangaon", "Jayashankar Bhupalpally", "Jogulamba Gadwal", "Kamareddy", "Karimnagar", "Khammam", "Kumuram Bheem", "Mahabubabad", "Mahabubnagar", "Mancherial", "Medak", "Medchal", "Mulugu", "Nagarkurnool", "Nalgonda", "Narayanpet", "Nirmal", "Nizamabad", "Peddapalli", "Rajanna Sircilla", "Rangareddy", "Sangareddy", "Siddipet", "Suryapet", "Vikarabad", "Wanaparthy", "Warangal Rural", "Warangal Urban", "Yadadri Bhuvanagiri"],
            "Tripura": ["Dhalai", "Gomati", "Khowai", "North Tripura", "Sepahijala", "South Tripura", "Unakoti", "West Tripura"],
            "Uttar Pradesh": ["Agra", "Aligarh", "Allahabad", "Ambedkar Nagar", "Amethi", "Amroha", "Auraiya", "Azamgarh", "Baghpat", "Bahraich", "Ballia", "Balrampur", "Banda", "Barabanki", "Bareilly", "Basti", "Bhadohi", "Bijnor", "Budaun", "Bulandshahr", "Chandauli", "Chitrakoot", "Deoria", "Etah", "Etawah", "Faizabad", "Farrukhabad", "Fatehpur", "Firozabad", "Gautam Buddh Nagar", "Ghaziabad", "Ghazipur", "Gonda", "Gorakhpur", "Hamirpur", "Hapur", "Hardoi", "Hathras", "Jalaun", "Jaunpur", "Jhansi", "Kannauj", "Kanpur Dehat", "Kanpur Nagar", "Kasganj", "Kaushambi", "Kheri", "Kushinagar", "Lalitpur", "Lucknow", "Maharajganj", "Mahoba", "Mainpuri", "Mathura", "Mau", "Meerut", "Mirzapur", "Moradabad", "Muzaffarnagar", "Pilibhit", "Pratapgarh", "Raebareli", "Rampur", "Saharanpur", "Sambhal", "Sant Kabir Nagar", "Shahjahanpur", "Shamli", "Shravasti", "Siddharthnagar", "Sitapur", "Sonbhadra", "Sultanpur", "Unnao", "Varanasi"],
            "Uttarakhand": ["Almora", "Bageshwar", "Chamoli", "Champawat", "Dehradun", "Haridwar", "Nainital", "Pauri Garhwal", "Pithoragarh", "Rudraprayag", "Tehri Garhwal", "Udham Singh Nagar", "Uttarkashi"],
            "West Bengal": ["Alipurduar", "Bankura", "Birbhum", "Cooch Behar", "Dakshin Dinajpur", "Darjeeling", "Hooghly", "Howrah", "Jalpaiguri", "Jhargram", "Kalimpong", "Kolkata", "Malda", "Murshidabad", "Nadia", "North 24 Parganas", "Paschim Bardhaman", "Paschim Medinipur", "Purba Bardhaman", "Purba Medinipur", "Purulia", "South 24 Parganas", "Uttar Dinajpur"]
        },
        "United States": {
            "California": ["Los Angeles", "San Francisco", "San Diego"],
            "New York": ["New York City", "Buffalo", "Rochester"],
            "Texas": ["Houston", "Austin", "Dallas"]
        }
    };

    function populateStates(countryId, stateId, cityId, currentVal = '') {
        const country = document.getElementById(countryId).value;
        const $stateSelect = $(`#${stateId}`);
        const $citySelect = $(`#${cityId}`);
        
        $stateSelect.html('<option value="">Select State</option>');
        $citySelect.html('<option value="">Select District</option>');
        
        if (addressData[country]) {
            Object.keys(addressData[country]).forEach(state => {
                const opt = new Option(state, state, state === currentVal, state === currentVal);
                $stateSelect.append(opt);
            });
        }
        $stateSelect.trigger('change.select2');
        $citySelect.trigger('change.select2');
    }

    function populateCities(countryId, stateId, cityId, currentVal = '') {
        const country = document.getElementById(countryId).value;
        const state = document.getElementById(stateId).value;
        const $citySelect = $(`#${cityId}`);
        
        $citySelect.html('<option value="">Select District</option>');
        
        if (addressData[country] && addressData[country][state]) {
            addressData[country][state].forEach(city => {
                const opt = new Option(city, city, city === currentVal, city === currentVal);
                $citySelect.append(opt);
            });
        }
        $citySelect.trigger('change.select2');
    }

    // Initialization and Event Listeners
    const $pCountry = $('#perm_country');
    const $pState = $('#perm_state');
    const $pCity = $('#perm_city');
    const $tCountry = $('#temp_country');
    const $tState = $('#temp_state');
    const $tCity = $('#temp_city');

    $pCountry.on('change', () => populateStates('perm_country', 'perm_state', 'perm_city'));
    $pState.on('change', () => populateCities('perm_country', 'perm_state', 'perm_city'));

    $tCountry.on('change', () => populateStates('temp_country', 'temp_state', 'temp_city'));
    $tState.on('change', () => populateCities('temp_country', 'temp_state', 'temp_city'));

    // --- Marital Status Toggle Logic ---
    const maritalStatusEl = document.getElementById('marital_status');
    const toggleMaritalFields = () => {
        if (!maritalStatusEl) return;
        const status = maritalStatusEl.value;
        const noOfChildren = document.getElementById('no_of_children');
        const spouseDetailsDiv = document.getElementById('spouse-details-div');
        const spouseName = document.getElementById('spouse_name');

        if (status === 'single') {
            if (noOfChildren) {
                noOfChildren.value = 0;
                noOfChildren.readOnly = true;
                noOfChildren.style.backgroundColor = '#f8f9fa';
                noOfChildren.style.cursor = 'not-allowed';
            }
            if (spouseDetailsDiv) spouseDetailsDiv.style.display = 'none';
            if (spouseName) {
                spouseName.value = '';
                spouseName.readOnly = true;
                spouseName.style.backgroundColor = '#f8f9fa';
                spouseName.style.cursor = 'not-allowed';
            }
        } else {
            if (noOfChildren) {
                noOfChildren.readOnly = false;
                noOfChildren.style.backgroundColor = '';
                noOfChildren.style.cursor = 'text';
            }
            if (spouseDetailsDiv) spouseDetailsDiv.style.display = 'block';
            if (spouseName) {
                spouseName.readOnly = false;
                spouseName.style.backgroundColor = '';
                spouseName.style.cursor = 'text';
            }
        }
    };

    if (maritalStatusEl) {
        maritalStatusEl.addEventListener('change', toggleMaritalFields);
        // Run once on load
        toggleMaritalFields();
    }

    // Initial load for address
    if ($pCountry.val()) {
        populateStates('perm_country', 'perm_state', 'perm_city', "{{ $user->perm_state }}");
        populateCities('perm_country', 'perm_state', 'perm_city', "{{ $user->perm_city }}");
    }
    if ($tCountry.val()) {
        populateStates('temp_country', 'temp_state', 'temp_city', "{{ $user->temp_state }}");
        populateCities('temp_country', 'temp_state', 'temp_city', "{{ $user->temp_city }}");
    }

    // --- Helper UI Toggles ---
    const maritalSelect = document.getElementById('marital_status');
    if(maritalSelect) {
        maritalSelect.addEventListener('change', function() {
            const spouseDiv = document.getElementById('spouse-details-div');
            spouseDiv.style.display = (this.value === 'married') ? 'block' : 'none';
        });
    }

    const addressToggle = document.getElementById('same_as_permanent');
    if(addressToggle) {
        addressToggle.addEventListener('change', function() {
            const tempDiv = document.getElementById('temporary-address-section');
            tempDiv.style.display = (this.checked) ? 'none' : 'block';
        });
    }

    // --- Strict Validation Logic ---
    async function validateCurrentStep() {
        const step = stepper._currentIndex;
        let valid = true;
        const container = stepperEl.querySelectorAll('.content')[step];
        const errorFields = new Set();

        const getFieldLabel = (el) => {
            const label = container.querySelector(`label[for="${el.id}"]`) || el.closest('.col-md-6, .col-md-4, .col-12')?.querySelector('.hitech-label');
            return label ? label.innerText.replace('*', '').trim() : (el.placeholder || el.name);
        };

        // 1. Check HTML5 Required
        container.querySelectorAll('[required]').forEach(el => {
            if (el.disabled) return;
            if (el.offsetParent === null) return;

            let fieldValid = true;
            if (el.type === 'file') {
                const alreadyUploaded = el.dataset.uploaded === 'true' || el.dataset.existing === 'true';
                if (!alreadyUploaded && (!el.files || el.files.length === 0)) fieldValid = false;
            } else if (!el.value || (el.type === 'checkbox' && !el.checked)) {
                fieldValid = false;
            }

            if (!fieldValid) {
                showError(el, true);
                valid = false;
                errorFields.add(getFieldLabel(el));
            } else {
                showError(el, false);
            }
        });

        // 2. Custom Logic
        if (step === 0) {
            const dob = document.getElementById('dob_input');
            if (dob && dob.value) {
                const birth = new Date(dob.value);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                if (age < 18) {
                    showError(dob, true, 'dob_error');
                    valid = false;
                    errorFields.add("Age (Must be 18+)");
                } else {
                    showError(dob, false, 'dob_error');
                }
            }
            const nameRegex = /^[A-Za-z\s]+$/;
            ['first_name', 'last_name', 'father_name', 'mother_name'].forEach(id => {
                const el = document.getElementById(id);
                if (el && el.value && !nameRegex.test(el.value)) {
                    showError(el, true, id + '_error');
                    valid = false;
                    errorFields.add(getFieldLabel(el) + " (Invalid Name)");
                } else if (el) {
                    showError(el, false, id + '_error');
                }
            });
        }

        if (step === 1) {
            const phone = document.getElementById('phone_input');
            if (phone && phone.value && !/^\d{10}$/.test(phone.value)) {
                showError(phone, true, 'phone_error');
                valid = false;
                errorFields.add("Primary Phone (10 digits)");
            } else if (phone) {
                showError(phone, false, 'phone_error');
            }

            const oPhone = document.querySelector('input[name="official_phone"]');
            if (oPhone && oPhone.value && !/^\d{10}$/.test(oPhone.value)) {
                showError(oPhone, true);
                valid = false;
                errorFields.add("Official Phone (10 digits)");
            } else if (oPhone) {
                showError(oPhone, false);
            }
            const zipFields = ['perm_zip', 'temp_zip'];
            zipFields.forEach(id => {
                const el = document.getElementById(id);
                if (el && el.value && !/^\d{6}$/.test(el.value)) {
                    showError(el, true, id + '_error');
                    valid = false;
                    errorFields.add(getFieldLabel(el) + " (6-digit PIN)");
                } else if (el) {
                    showError(el, false, id + '_error');
                }
            });
            const eName = document.getElementById('emergency_contact_name');
            const nameRegex = /^[A-Za-z\s]+$/;
            if (eName && eName.value && !nameRegex.test(eName.value)) {
                showError(eName, true, 'emergency_contact_name_error');
                valid = false;
                errorFields.add(getFieldLabel(eName) + " (Invalid Name)");
            } else if (eName) {
                showError(eName, false, 'emergency_contact_name_error');
            }
            const ePhone = document.getElementById('emergency_contact_phone');
            if (ePhone && ePhone.value && !/^\d{10}$/.test(ePhone.value)) {
                showError(ePhone, true, 'emergency_contact_phone_error');
                valid = false;
                errorFields.add("Emergency Contact Phone (10 digits)");
            } else if (ePhone) {
                showError(ePhone, false, 'emergency_contact_phone_error');
            }
        }

        if (step === 2) {
            const acct = document.getElementById('account_number');
            const conf = document.getElementById('confirm_account_number');
            if (acct && conf && acct.value && conf.value && acct.value !== conf.value) {
                showError(conf, true, 'account_number_error');
                valid = false;
                errorFields.add("Account Confirmation (Mismatch)");
            } else if (conf) {
                showError(conf, false, 'account_number_error');
            }
            const ifsc = document.getElementById('ifsc_code');
            if (ifsc) {
                ifsc.value = ifsc.value.toUpperCase();
                if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc.value)) {
                    showError(ifsc, true, 'ifsc_error');
                    valid = false;
                    errorFields.add("IFSC Code (Invalid format)");
                } else {
                    showError(ifsc, false, 'ifsc_error');
                }
            }
            const cheque = document.getElementById('cheque_file');
            if (cheque) {
                const fileValid = validateFile(cheque, 500, ['.pdf', '.png', '.jpg', '.jpeg'], 'cheque_file_error');
                if (!fileValid) {
                    valid = false;
                    errorFields.add(getFieldLabel(cheque) + " (Max 500KB, PDF/PNG/JPG)");
                }
            }
        }

        if (step === 3) {
            // Document Step Specifics
            const aadhar = document.getElementById('aadhaar_no');
            if (aadhar && !/^\d{12}$/.test(aadhar.value)) {
                showError(aadhar, true, 'aadhaar_error');
                valid = false;
                errorFields.add("Aadhaar Number (12 digits)");
            } else if(aadhar) showError(aadhar, false, 'aadhaar_error');

            const pan = document.getElementById('pan_no');
            if (pan) {
                pan.value = pan.value.toUpperCase();
                if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.value)) {
                    showError(pan, true, 'pan_error');
                    valid = false;
                    errorFields.add("PAN Number (ABCDE1234F)");
                } else showError(pan, false, 'pan_error');
            }

            const aFile = document.getElementById('aadhaar_file');
            const pFile = document.getElementById('pan_file');
            const photo = document.getElementById('photo_input');

            // Aadhaar File Missing?
            if (aFile && (!aFile.files || aFile.files.length === 0) && !aFile.getAttribute('data-existing')) {
                showError(aFile, true, 'aadhaar_file_error');
                valid = false;
                errorFields.add("Aadhaar File is required");
            } else if (aFile && !validateFile(aFile, 300, ['.pdf', '.png', '.jpg', '.jpeg'], 'aadhaar_file_error')) {
                valid = false;
                errorFields.add("Aadhaar File (Max 300KB)");
            }

            // PAN File Missing?
            if (pFile && (!pFile.files || pFile.files.length === 0) && !pFile.getAttribute('data-existing')) {
                showError(pFile, true, 'pan_file_error');
                valid = false;
                errorFields.add("PAN File is required");
            } else if (pFile && !validateFile(pFile, 300, ['.pdf', '.png', '.jpg', '.jpeg'], 'pan_file_error')) {
                valid = false;
                errorFields.add("PAN File (Max 300KB)");
            }

            // Photo Missing?
            if (photo && (!photo.files || photo.files.length === 0) && !photo.getAttribute('data-existing')) {
                showError(photo, true, 'photo_error');
                valid = false;
                errorFields.add("Profile Photo is required");
            } else if (photo && !validateFile(photo, 100, ['.png', '.jpg', '.jpeg'], 'photo_error')) {
                valid = false;
                errorFields.add("Profile Photo (Max 100KB)");
            }

            // Educational & Experience Documents
            const docGroups = [
                { id: 'matric', label: '10th Marksheet (Matric)', required: false, requiredFile: true },
                { id: 'inter', label: '12th Marksheet (Intermediate)', required: false, requiredFile: true },
                { id: 'bachelor', label: 'Graduation Marksheet', required: false, requiredFile: true, fileId: 'graduation_file' },
                { id: 'master', label: 'Post Graduation Marksheet', required: false },
                { id: 'experience', label: 'Experience Certificate', required: false, fileId: 'experience_file' }
            ];

            docGroups.forEach(group => {
                const prefix = group.id;
                const uni = document.getElementById(prefix + '_university');
                const mark = document.getElementById(prefix + (prefix === 'experience' ? '_certificate_no' : '_marksheet_no'));
                const file = document.getElementById(group.fileId || (prefix + '_file'));
                
                if (group.required) {
                    if (uni && !uni.value) { showError(uni, true); valid = false; errorFields.add(group.label + " Board/Uni"); }
                    else if(uni) showError(uni, false);

                    if (mark && !mark.value) { showError(mark, true); valid = false; errorFields.add(group.label + " Number"); }
                    else if(mark) showError(mark, false);
                }

                // Check Mandatory File Upload
                const fileMissing = file && (!file.files || file.files.length === 0) && !file.getAttribute('data-existing');
                if (group.requiredFile && fileMissing) {
                    showError(file, true, (group.fileId || prefix + '_file') + '_error');
                    valid = false;
                    errorFields.add(group.label + " File is required");
                } else if(file && !validateFile(file, 300, ['.pdf', '.png', '.jpg', '.jpeg'], (group.fileId || prefix + '_file') + '_error')) {
                    valid = false;
                    errorFields.add(group.label + " File (Max 300KB)");
                }
            });
        }
        
        if (step === 4) {
            const consent = document.getElementById('consent_accepted');
            if (consent && !consent.checked) {
                valid = false;
                errorFields.add("Final Declaration Consent");
            }
        }

        if (!valid) {
            const errorList = Array.from(errorFields).map(err => `
                <li style="margin-bottom: 8px; display: flex; align-items: center;">
                    <i class="bx bx-error-circle me-2 text-danger" style="font-size: 1.1rem;"></i>
                    <span>${err}</span>
                </li>
            `).join('');

            Swal.fire({
                title: 'Submission Issues',
                html: `
                    <div style="font-family: inherit;">
                        <p style="margin-bottom: 1rem; color: #1e293b; font-weight: 700;">The following items require your attention:</p>
                        <ul style="list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr; gap: 4px;">
                            ${errorList}
                        </ul>
                    </div>
                `,
                icon: 'warning',
                customClass: {
                    confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false,
                confirmButtonText: 'I understand'
            });
        }

        return valid;
    }

    function showError(el, show, errorId = null) {
        if (show) {
            el.classList.add('is-invalid');
            if (errorId) {
                 const errDiv = document.getElementById(errorId);
                 if(errDiv) errDiv.style.display = 'block';
            }
        } else {
            el.classList.remove('is-invalid');
            if (errorId) {
                const errDiv = document.getElementById(errorId);
                if(errDiv) errDiv.style.display = 'none';
            }
        }
    }

    function validateFile(el, maxSizeKB, allowedTypes, errorId) {
        if (!el.files || el.files.length === 0) return true;
        const file = el.files[0];
        const sizeKB = file.size / 1024;
        const type = file.type;
        const extension = file.name.split('.').pop().toLowerCase();
        
        let valid = true;
        if (sizeKB > maxSizeKB) valid = false;
        
        const typeMatch = allowedTypes.some(t => {
            if (t.startsWith('.')) return extension === t.substring(1);
            return type.includes(t);
        });
        
        if (!typeMatch) valid = false;
        
        showError(el, !valid, errorId);
        return valid;
    }

    function validatePersonalStep() {
        let v = true;
        const dob = document.getElementById('dob_input');
        if (dob && dob.value) {
            const birth = new Date(dob.value);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            
            if (age < 18) {
                showError(dob, true, 'dob_error');
                v = false;
            } else {
                showError(dob, false, 'dob_error');
            }
        }
        
        const fname = document.getElementById('first_name');
        const lname = document.getElementById('last_name');
        const nameRegex = /^[A-Za-z\s]+$/;
        
        if (fname && !nameRegex.test(fname.value)) {
            showError(fname, true, 'fname_error');
            v = false;
        } else if(fname) showError(fname, false, 'fname_error');

        if (lname && !nameRegex.test(lname.value)) {
            showError(lname, true, 'lname_error');
            v = false;
        } else if(lname) showError(lname, false, 'lname_error');

        const father = document.getElementById('father_name');
        const mother = document.getElementById('mother_name');

        if (father && !nameRegex.test(father.value)) {
            showError(father, true, 'father_error');
            v = false;
        } else if(father) showError(father, false, 'father_error');

        if (mother && !nameRegex.test(mother.value)) {
            showError(mother, true, 'mother_error');
            v = false;
        } else if(mother) showError(mother, false, 'mother_error');

        return v;
    }

    function validateContactStep() {
        let v = true;
        
        // Personal Email
        const pEmail = document.getElementById('personal_email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (pEmail && !emailRegex.test(pEmail.value)) {
            showError(pEmail, true, 'personal_email_error');
            v = false;
        } else if(pEmail) showError(pEmail, false, 'personal_email_error');

        // Main Phone
        const phone = document.getElementById('phone_input');
        if (phone && !/^\d{10}$/.test(phone.value)) {
            showError(phone, true, 'phone_error');
            v = false;
        } else if(phone) showError(phone, false, 'phone_error');

        // Official Phone
        const oPhone = document.getElementById('official_phone');
        if (oPhone && !/^\d{10}$/.test(oPhone.value)) {
            showError(oPhone, true, 'official_phone_error');
            v = false;
        } else if(oPhone) showError(oPhone, false, 'official_phone_error');

        const zip = document.getElementById('perm_zip');
        if (zip && zip.value && !/^\d{6}$/.test(zip.value)) {
            showError(zip, true, 'perm_zip_error');
            v = false;
        } else if(zip) showError(zip, false, 'perm_zip_error');

        const tzip = document.getElementById('temp_zip');
        if (tzip && tzip.value && !/^\d{6}$/.test(tzip.value)) {
            showError(tzip, true, 'temp_zip_error');
            v = false;
        } else if(tzip) showError(tzip, false, 'temp_zip_error');

        const eName = document.getElementById('emergency_contact_name');
        const ePhone = document.getElementById('emergency_contact_phone');
        const nameRegex = /^[A-Za-z\s]+$/;

        if (eName && !nameRegex.test(eName.value)) {
            showError(eName, true, 'emergency_contact_name_error');
            v = false;
        } else if(eName) showError(eName, false, 'emergency_contact_name_error');

        if (ePhone && !/^\d{10}$/.test(ePhone.value)) {
            showError(ePhone, true, 'emergency_contact_phone_error');
            v = false;
        } else if(ePhone) showError(ePhone, false, 'emergency_contact_phone_error');

        return v;
    }

    function validateBankingStep() {
        let v = true;
        const acct = document.getElementById('account_number');
        const conf = document.getElementById('confirm_account_number');
        if (acct && conf && acct.value !== conf.value) {
            showError(conf, true, 'account_number_error');
            v = false;
        } else if(conf) showError(conf, false, 'account_number_error');

        const ifsc = document.getElementById('ifsc_code');
        if (ifsc) {
            ifsc.value = ifsc.value.toUpperCase();
            if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc.value)) {
                showError(ifsc, true, 'ifsc_error');
                v = false;
            } else showError(ifsc, false, 'ifsc_error');
        }

        const cheque = document.getElementById('cheque_file');
        if (cheque) v = validateFile(cheque, 500, ['.pdf', '.png', '.jpg', '.jpeg'], 'cheque_file_error') && v;

        return v;
    }

    function validateDocumentStep() {
        let v = true;
        const aadhar = document.getElementById('aadhaar_no');
        if (aadhar && !/^\d{12}$/.test(aadhar.value)) {
            showError(aadhar, true, 'aadhaar_error');
            v = false;
        } else if(aadhar) showError(aadhar, false, 'aadhaar_error');

        const pan = document.getElementById('pan_no');
        if (pan) {
            pan.value = pan.value.toUpperCase();
            if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.value)) {
                showError(pan, true, 'pan_error');
                v = false;
            } else showError(pan, false, 'pan_error');
        }

        const aFile = document.getElementById('aadhaar_file');
        const pFile = document.getElementById('pan_file');
        const photo = document.getElementById('photo_input');

        if (aFile) v = validateFile(aFile, 300, ['.pdf', '.png', '.jpg', '.jpeg'], 'aadhaar_file_error') && v;
        if (pFile) v = validateFile(pFile, 300, ['.pdf', '.png', '.jpg', '.jpeg'], 'pan_file_error') && v;
        if (photo) v = validateFile(photo, 100, ['.png', '.jpg', '.jpeg'], 'photo_error') && v;

        return v;
    }

    // Real-time Listeners
    const rtInputs = [
        { id: 'first_name', fn: validatePersonalStep },
        { id: 'last_name', fn: validatePersonalStep },
        { id: 'father_name', fn: validatePersonalStep },
        { id: 'mother_name', fn: validatePersonalStep },
        { id: 'dob_input', fn: validatePersonalStep },
        { id: 'phone_input', fn: validateContactStep },
        { id: 'personal_email', fn: validateContactStep },
        { id: 'official_phone', fn: validateContactStep },
        { id: 'perm_zip', fn: validateContactStep },
        { id: 'temp_zip', fn: validateContactStep },
        { id: 'emergency_contact_name', fn: validateContactStep },
        { id: 'emergency_contact_phone', fn: validateContactStep },
        { id: 'account_number', fn: validateBankingStep },
        { id: 'confirm_account_number', fn: validateBankingStep },
        { id: 'ifsc_code', fn: validateBankingStep },
        { id: 'cheque_file', fn: validateBankingStep },
        { id: 'aadhaar_no', fn: validateDocumentStep },
        { id: 'aadhaar_file', fn: validateDocumentStep },
        { id: 'pan_no', fn: validateDocumentStep },
        { id: 'pan_file', fn: validateDocumentStep },
        { id: 'first_name', fn: validatePersonalStep },
        { id: 'last_name', fn: validatePersonalStep },
        { id: 'father_name', fn: validatePersonalStep },
        { id: 'mother_name', fn: validatePersonalStep }
    ];

    rtInputs.forEach(item => {
        const el = document.getElementById(item.id);
        if (el) el.addEventListener('input', item.fn);
    });
});
</script>
@endsection
