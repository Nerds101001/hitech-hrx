@extends('layouts/layoutMaster')

@section('title', 'Apply for ' . $job->title)

@php
  $pageConfigs = ['myLayout' => 'blank'];
  $logo = \App\Models\Utility::get_file('uploads/logo/');
@endphp

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate-css.scss',
    'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/flatpickr/flatpickr.js'
  ])
@endsection

@section('page-script')
<script>
  $(document).ready(function() {
    $('.datepicker').flatpickr({
      altInput: true,
      altFormat: "F j, Y",
      dateFormat: "Y-m-d",
    });
  });
</script>
@endsection

@section('content')
<div class="apply-page pb-20">
  {{-- Unified Header --}}
  <div class="hitech-header">
    <div class="brand-logo-area">
        <div class="brand-icon-box">
            <i class="bx bx-layer"></i>
        </div>
        <h1 class="brand-text">HI TECH <span>HRX</span></h1>
    </div>
    <div class="header-actions">
      <a href="{{ route('job.requirement', [$job->code, $currantLang]) }}" class="btn btn-label-secondary btn-sm rounded-pill px-4">
        <i class="bx bx-left-arrow-alt me-1"></i>Back to Role
      </a>
    </div>
  </div>

  {{-- Subtle Hero Blur --}}
  <div style="height: 250px; background: linear-gradient(135deg, rgba(0, 128, 128, 0.15), rgba(0, 128, 128, 0.05));">
    <div class="container pt-15 text-center">
      <h1 class="fw-extrabold text-heading mb-2">Apply for {{ $job->title }}</h1>
      <p class="text-muted">Tell us about yourself and why you're a great fit.</p>
    </div>
  </div>

  <div class="container apply-container">
    <div class="row justify-content-center">
      <div class="col-xl-9 col-lg-10 animate__animated animate__fadeInUp">
        <div class="glass-panel-premium">
          <form action="{{ route('job.apply.data', $job->code) }}" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            @csrf
            {{-- Personal Information --}}
            <div class="mb-12">
              <h5 class="fw-bold mb-6 d-flex align-items-center">
                <span class="avatar avatar-sm bg-label-primary rounded-circle me-3"><i class="bx bx-user"></i></span>
                Personal Details
              </h5>
              <div class="row g-6">
                <div class="col-md-6">
                  <label class="form-label">Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email Address <span class="text-danger">*</span></label>
                  <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                  <input type="text" name="phone" class="form-control" placeholder="+1 (555) 000-0000" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Date of Birth</label>
                  <input type="text" name="dob" class="form-control datepicker" placeholder="Select date">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Gender</label>
                  <div class="d-flex gap-4 mt-2">
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male">
                      <label class="form-check-label" for="gender_male">Male</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female">
                      <label class="form-check-label" for="gender_female">Female</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- Address --}}
            <div class="mb-12">
              <h5 class="fw-bold mb-6 d-flex align-items-center">
                <span class="avatar avatar-sm bg-label-primary rounded-circle me-3"><i class="bx bx-map-pin"></i></span>
                Location Information
              </h5>
              <div class="row g-6">
                <div class="col-md-12">
                  <label class="form-label">Address</label>
                  <input type="text" name="address" class="form-control" placeholder="Enter street address">
                </div>
                <div class="col-md-4">
                  <label class="form-label">City</label>
                  <input type="text" name="city" class="form-control" placeholder="City">
                </div>
                <div class="col-md-4">
                  <label class="form-label">State</label>
                  <input type="text" name="state" class="form-control" placeholder="State">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Zip Code</label>
                  <input type="text" name="zip_code" class="form-control" placeholder="Zip Code">
                </div>
              </div>
            </div>

            {{-- Custom Questions --}}
            @if($questions->count() > 0)
            <div class="mb-12">
              <h5 class="fw-bold mb-6 d-flex align-items-center">
                <span class="avatar avatar-sm bg-label-primary rounded-circle me-3"><i class="bx bx-help-circle"></i></span>
                Additional Questions
              </h5>
              <div class="row g-6">
                @foreach ($questions as $question)
                  <div class="col-md-12">
                    <label class="form-label">{{ $question->question }} @if($question->is_required == 'yes') <span class="text-danger">*</span> @endif</label>
                    @if($question->type == 'text')
                      <input type="text" name="question[{{ $question->id }}]" class="form-control" placeholder="Your answer..." {{ ($question->is_required == 'yes' ? 'required' : '') }}>
                    @elseif($question->type == 'number')
                      <input type="number" name="question[{{ $question->id }}]" class="form-control" placeholder="Enter number..." {{ ($question->is_required == 'yes' ? 'required' : '') }}>
                    @elseif($question->type == 'date')
                      <input type="date" name="question[{{ $question->id }}]" class="form-control" {{ ($question->is_required == 'yes' ? 'required' : '') }}>
                    @elseif($question->type == 'file')
                      <input type="file" name="question[{{ $question->id }}]" class="form-control" {{ ($question->is_required == 'yes' ? 'required' : '') }}>
                    @elseif($question->type == 'select')
                      <select name="question[{{ $question->id }}]" class="form-select form-select-hitech" {{ ($question->is_required == 'yes' ? 'required' : '') }}>
                        <option value="">Select an option</option>
                        @php $options = explode(',', $question->options); @endphp
                        @foreach($options as $option)
                          <option value="{{ trim($option) }}">{{ trim($option) }}</option>
                        @endforeach
                      </select>
                    @elseif($question->type == 'checkbox')
                      <div class="mt-2">
                        @php $options = explode(',', $question->options); @endphp
                        @foreach($options as $index => $option)
                          <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="question[{{ $question->id }}][]" id="chk_{{ $question->id }}_{{ $index }}" value="{{ trim($option) }}">
                            <label class="form-check-label" for="chk_{{ $question->id }}_{{ $index }}">{{ trim($option) }}</label>
                          </div>
                        @endforeach
                      </div>
                    @else
                      <textarea name="question[{{ $question->id }}]" class="form-control" rows="3" placeholder="Your answer..." {{ ($question->is_required == 'yes' ? 'required' : '') }}></textarea>
                    @endif
                  </div>
                @endforeach
              </div>
            </div>
            @endif

            {{-- Attachments --}}
            <div class="mb-12">
              <h5 class="fw-bold mb-6 d-flex align-items-center">
                <span class="avatar avatar-sm bg-label-primary rounded-circle me-3"><i class="bx bx-paperclip"></i></span>
                Attachments
              </h5>
              <div class="row g-6">
                <div class="col-md-6">
                  <label class="form-label-hitech">Resume / CV <span class="text-danger">*</span></label>
                  <input type="file" class="form-control form-control-hitech" name="resume" required>
                  <small class="text-muted mt-2 d-block">PDF, DOCX formats only (Max 5MB)</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label-hitech">Profile Photo</label>
                  <input type="file" class="form-control form-control-hitech" name="profile">
                  <small class="text-muted mt-2 d-block">JPG, PNG formats (Max 2MB)</small>
                </div>
                <div class="col-md-12">
                  <label class="form-label-hitech">Cover Letter</label>
                  <textarea name="cover_letter" class="form-control form-control-hitech" rows="4" placeholder="Tell us why you are the perfect candidate..."></textarea>
                </div>
              </div>
            </div>

            <div class="mt-8 pt-8 border-top d-flex justify-content-between align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="terms_condition_check" id="terms_check" required>
                <label class="form-check-label small" for="terms_check">
                  I agree to the <a href="#" class="text-primary">Terms and Conditions</a> and privacy policy.
                </label>
              </div>
              <button type="submit" class="btn-submit-premium shadow-lg">
                 Submit Application <i class="bx bx-right-arrow-alt ms-2"></i>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Footer --}}
  <footer class="mt-20 py-10 border-top bg-white">
    <div class="container text-center">
      <p class="mb-0 text-muted small">&copy; {{ date('Y') }} {{ isset($companySettings['footer_text']->value) ? $companySettings['footer_text']->value : 'Hi Tech HRX' }}. All rights reserved.</p>
    </div>
  </footer>
</div>
@endsection
