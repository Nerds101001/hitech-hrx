@extends('layouts.layoutMaster')

@section('title', 'Create Job Application')

@section('content')
<div class="modal-content hitech-card-white border-0 modal-content-hitech shadow-lg">
  <div class="modal-header modal-header-hitech border-bottom-0 pb-3 pt-4 px-4 d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
          <div class="modal-icon-header bg-white text-primary me-3 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
              <i class="bx bx-briefcase fs-4"></i>
          </div>
          <h5 class="modal-title mb-0 modal-title-hitech text-white fw-bold">Create New Job Application</h5>
      </div>
      <button type="button" class="btn text-white opacity-75 hover-opacity-100 p-0 m-0" data-bs-dismiss="modal" aria-label="Close" style="background: transparent; border: none; font-size: 1.5rem; line-height: 1;">
          <i class="bx bx-x"></i>
      </button>
  </div>
  <form action="{{ url('job-application') }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    @csrf
  <div class="modal-body modal-body-hitech pt-4 px-4 pb-4">
    <div class="row g-4">
      <div class="col-md-12">
        <label class="form-label form-label-hitech">Job Posting <span class="text-danger">*</span></label>
        <select class="form-select form-select-hitech select2" id="jobs" name="job" required>
            <option value="">{{ __('Select Job') }}</option>
            @foreach($jobs as $key => $val)
                <option value="{{ $key }}">{{ $val }}</option>
            @endforeach
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label form-label-hitech">Full Name <span class="text-danger">*</span></label>
        <input class="form-control form-control-hitech" name="name" type="text" required placeholder="Enter candidate name">
      </div>
      <div class="col-md-6">
        <label class="form-label form-label-hitech">Email Address <span class="text-danger">*</span></label>
        <input class="form-control form-control-hitech" name="email" type="email" required placeholder="Enter email address">
      </div>
      <div class="col-md-6">
        <label class="form-label form-label-hitech">Phone Number <span class="text-danger">*</span></label>
        <input class="form-control form-control-hitech" name="phone" type="text" required placeholder="Enter phone number">
      </div>

      {{-- Dynamically shown based on job selection --}}
      <div class="col-md-6 dob d-none">
        <label class="form-label form-label-hitech">Date of Birth</label>
        <input class="form-control form-control-hitech" name="dob" type="date" value="{{ old('dob') }}" autocomplete="off">
      </div>
      <div class="col-md-6 gender d-none">
        <label class="form-label form-label-hitech">Gender</label>
        <div class="d-flex gap-4 pt-2">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="gender" id="g_male" value="Male">
            <label class="form-check-label" for="g_male">Male</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="gender" id="g_female" value="Female">
            <label class="form-check-label" for="g_female">Female</label>
          </div>
        </div>
      </div>

      <div class="col-md-12 address d-none">
        <label class="form-label form-label-hitech">Street Address</label>
        <textarea class="form-control form-control-hitech" name="address" rows="2" placeholder="Enter address"></textarea>
      </div>
      <div class="col-md-4 address d-none">
        <label class="form-label form-label-hitech">City</label>
        <input class="form-control form-control-hitech" name="city" type="text" placeholder="City">
      </div>
      <div class="col-md-4 address d-none">
        <label class="form-label form-label-hitech">State</label>
        <input class="form-control form-control-hitech" name="state" type="text" placeholder="State">
      </div>
      <div class="col-md-4 address d-none">
        <label class="form-label form-label-hitech">ZIP/Postal Code</label>
        <input class="form-control form-control-hitech" name="zip_code" type="text" placeholder="ZIP Code">
      </div>
      <div class="col-md-12 address d-none">
        <label class="form-label form-label-hitech">Country</label>
        <input class="form-control form-control-hitech" name="country" type="text" placeholder="Country">
      </div>
      
      <div class="col-md-6 profile d-none">
        <label class="form-label form-label-hitech">Profile Picture</label>
        <input type="file" class="form-control form-control-hitech" name="profile" id="profile" onchange="previewImage(this, 'profile-preview')">
        <div class="mt-2 text-center">
            <img id="profile-preview" src="#" alt="Preview" class="img-thumbnail rounded-circle" style="display:none; max-width: 100px; height: 100px; object-fit: cover;">
        </div>
      </div>
      
      <div class="col-md-6 resume d-none">
        <label class="form-label form-label-hitech">Resume / CV <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="file" class="form-control form-control-hitech" name="resume" id="resume" accept=".pdf,.doc,.docx" required>
        </div>
      </div>
      
      <div class="col-md-12 letter d-none">
        <label class="form-label form-label-hitech">Cover Letter</label>
        <textarea class="form-control form-control-hitech" name="cover_letter" rows="3" placeholder="Write a brief cover letter..."></textarea>
      </div>

      @foreach ($questions as $question)
        <div class="col-md-12 question_{{ $question->id }} d-none">
          <label class="form-label form-label-hitech">{{ $question->question }} @if($question->is_required == 'yes')<span class="text-danger">*</span>@endif</label>
          <input type="text" class="form-control form-control-hitech" name="question[{{ $question->question }}]" {{ $question->is_required == 'yes' ? 'required' : '' }} placeholder="Enter answer">
        </div>
      @endforeach
    </div>
  </div>
  <div class="modal-footer border-top pt-4 pb-4 px-4 d-flex justify-content-end gap-2">
    <button type="button" class="btn btn-label-secondary border-0 btn-close-hitech px-4" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-hitech px-5 rounded-pill shadow-sm">Submit Application</button>
  </div>
  </form>
</div>

<script>
  function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        $('#' + previewId).attr('src', e.target.result).show();
      }
      reader.readAsDataURL(input.files[0]);
    }
  }

  $(document).on('change', '#jobs', function() {
    var id = $(this).val();
    $.ajax({
      url: "{{ route('get.job.application') }}",
      type: 'POST',
      data: {
        "id": id,
        "_token": "{{ csrf_token() }}",
      },
      success: function(data) {
        var job = JSON.parse(data);
        var applicant = job.applicant || [];
        var visibility = job.visibility || [];
        var question = job.custom_question || [];

        // Reset visibility
        $('.dob, .gender, .address, .profile, .resume, .letter, [class*="question_"]').addClass('d-none');

        // Apply new visibility
        if (applicant.includes("dob")) $('.dob').removeClass('d-none');
        if (applicant.includes("gender")) $('.gender').removeClass('d-none');
        if (applicant.includes("address")) $('.address').removeClass('d-none');
        if (visibility.includes("profile")) $('.profile').removeClass('d-none');
        if (visibility.includes("resume")) $('.resume').removeClass('d-none');
        if (visibility.includes("letter")) $('.letter').removeClass('d-none');

        if (question.length > 0) {
          question.forEach(function(qid) {
            $('.question_' + qid).removeClass('d-none');
          });
        }
      }
    });
  });
</script>
@endsection
