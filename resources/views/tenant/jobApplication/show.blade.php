@php
    $isAjax = request()->ajax();
@endphp

@if(!$isAjax)
    @extends('layouts/layoutMaster')
    @section('title', 'Application Details')
@endif

@if(!$isAjax)
    @section('vendor-style')
      @vite([
        'resources/assets/vendor/libs/tagify/tagify.scss',
        'resources/assets/vendor/scss/pages/hitech-portal.scss'
      ])
      <style>
        #stars { list-style-type: none; padding: 0; display: flex; gap: 0.5rem; }
        #stars li.star { cursor: pointer; color: #cbd5e1; transition: color 0.2s; }
        #stars li.star i { font-size: 1.5rem; }
        #stars li.star.hover, #stars li.star.selected { color: #ffc107; }
      </style>
    @endsection

    @section('vendor-script')
      @vite([
        'resources/assets/vendor/libs/tagify/tagify.js'
      ])
    @endsection
@else
    <style>
        #stars { list-style-type: none; padding: 0; display: flex; gap: 0.5rem; }
        #stars li.star { cursor: pointer; color: #cbd5e1; transition: color 0.2s; }
        #stars li.star i { font-size: 1.5rem; }
        #stars li.star.hover, #stars li.star.selected { color: #ffc107; }
        .modal-body .layout-full-width { padding: 0 !important; }
    </style>
@endif

@if(!$isAjax)
    @section('page-script')
      @include('tenant.jobApplication.show_script')
    @endsection
@endif

@if(!$isAjax)
    @section('content')
@endif
<div class="layout-full-width animate__animated animate__fadeIn {{ $isAjax ? 'p-0' : '' }}">
    @if($isAjax)
        @include('tenant.jobApplication.show_script')
    @endif
  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div class="d-flex align-items-center gap-4">
      @php
        $avatarPath = !empty($jobApplication->profile) ? 'uploads/job/profile/' . $jobApplication->profile : 'uploads/avatar/avatar.png';
      @endphp
      <img src="{{ Utility::get_file($avatarPath) }}" class="rounded-circle shadow-sm border border-2 border-primary" style="width: 64px; height: 64px; object-fit: cover;">
      <div>
        <h3 class="mb-0 fw-bold text-heading" style="font-size: 1.5rem;">{{ $jobApplication->name }}</h3>
        <span class="text-muted small">{{ $jobApplication->email }} • {{ !empty($jobApplication->jobs) ? $jobApplication->jobs->title : '-' }}</span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('job-application.index') }}" class="btn btn-label-secondary shadow-sm">
        <i class="bx bx-chevron-left me-1"></i>Back
      </a>
      <div class="dropdown">
        <button class="btn btn-hitech-primary shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
          Manage Application
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <form action="{{ route('job.application.archive', $jobApplication->id) }}" method="POST" class="d-inline">
              @csrf
              @method('DELETE')
              <button type="submit" class="dropdown-item">
                <i class="bx {{ $jobApplication->is_archive ? 'bx-undo' : 'bx-archive' }} me-2"></i>
                {{ $jobApplication->is_archive ? 'Unarchive' : 'Archive' }}
              </button>
            </form>
          </li>
          @can('Delete Job Application')
            <li><hr class="dropdown-divider"></li>
            <li>
              <form action="{{ route('job-application.destroy', $jobApplication->id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="dropdown-item text-danger">
                  <i class="bx bx-trash me-2"></i>Delete Candidate
                </button>
              </form>
            </li>
          @endcan
        </ul>
      </div>
    </div>
  </div>

  <div class="px-4">
    <div class="row g-6">
      {{-- Left Column: Details --}}
      <div class="col-lg-8">
        {{-- Application Status / Stage --}}
        <div class="hitech-card-white mb-6">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Application Stage</h5>
          </div>
          <div class="card-body pt-5">
            <div class="d-flex flex-wrap gap-3">
              @foreach ($stages as $stage)
                <div class="form-check custom-option custom-option-basic {{ $jobApplication->stage == $stage->id ? 'checked' : '' }}" style="flex: 1 0 150px;">
                  <label class="form-check-label custom-option-content" for="stage_{{ $stage->id }}">
                    <input class="form-check-input stage-radio" type="radio" name="stage" id="stage_{{ $stage->id }}" value="{{ $stage->id }}" data-scheduleid="{{ $jobApplication->id }}" {{ $jobApplication->stage == $stage->id ? 'checked' : '' }}>
                    <span class="custom-option-header">
                      <span class="h6 mb-0">{{ $stage->title }}</span>
                    </span>
                  </label>
                </div>
              @endforeach
            </div>
          </div>
        </div>

        {{-- Candidate Info --}}
        <div class="hitech-card-white mb-6">
          <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Basic Information</h5>
            <div id='rating-stars'>
              <ul id='stars'>
                @for($i=1; $i<=5; $i++)
                  <li class='star {{ ($jobApplication->rating >= $i) ? 'selected' : '' }}' data-value='{{ $i }}' title='{{ $i }} Stars'>
                    <i class='bx bxs-star'></i>
                  </li>
                @endfor
              </ul>
            </div>
          </div>
          <div class="card-body pt-5">
            <div class="row g-4">
              <div class="col-md-4">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Phone</span>
                <span class="h6 mb-0">{{ $jobApplication->phone }}</span>
              </div>
              @if($jobApplication->dob)
                <div class="col-md-4">
                  <span class="d-block text-muted small fw-bold text-uppercase mb-1">DOB</span>
                  <span class="h6 mb-0">{{ auth()->user()->dateFormat($jobApplication->dob) }}</span>
                </div>
              @endif
              @if($jobApplication->gender)
                <div class="col-md-4">
                  <span class="d-block text-muted small fw-bold text-uppercase mb-1">Gender</span>
                  <span class="h6 mb-0">{{ ucfirst($jobApplication->gender) }}</span>
                </div>
              @endif
              <div class="col-md-12">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Address</span>
                <span class="h6 mb-0">
                  {{ $jobApplication->address ? $jobApplication->address . ', ' : '' }}
                  {{ $jobApplication->city ? $jobApplication->city . ', ' : '' }}
                  {{ $jobApplication->state ? $jobApplication->state . ' ' : '' }}
                  {{ $jobApplication->zip_code ?? '' }}
                  {{ $jobApplication->country ? ' - ' . $jobApplication->country : '' }}
                </span>
              </div>
              <div class="col-md-6">
                <span class="d-block text-muted small fw-bold text-uppercase mb-1">Applied At</span>
                <span class="h6 mb-0">{{ auth()->user()->dateFormat($jobApplication->created_at) }}</span>
              </div>
              <div class="col-md-6 text-end">
                @if($jobApplication->resume)
                  <a href="{{ Utility::get_file('uploads/job/resume/' . $jobApplication->resume) }}" class="btn btn-label-primary btn-sm" download>
                    <i class="bx bx-download me-1"></i>Download CV
                  </a>
                  <a href="{{ Utility::get_file('uploads/job/resume/' . $jobApplication->resume) }}" target="_blank" class="btn btn-label-info btn-sm ms-2">
                    <i class="bx bx-show me-1"></i>Preview
                  </a>
                @else
                  <span class="text-muted italic">No resume provided</span>
                @endif
              </div>
            </div>

            @if(!empty($jobApplication->cover_letter))
              <div class="mt-6 p-4 rounded bg-light border">
                <h6 class="fw-bold text-heading small text-uppercase mb-3">Cover Letter</h6>
                <p class="mb-0 text-muted">{{ $jobApplication->cover_letter }}</p>
              </div>
            @endif
          </div>
        </div>

        {{-- Custom Questions --}}
        @php $questions = json_decode($jobApplication->custom_question, true); @endphp
        @if(!empty($questions))
          <div class="hitech-card-white mb-6">
            <div class="card-header border-bottom">
              <h5 class="card-title mb-0">Questionnaire Answers</h5>
            </div>
            <div class="card-body pt-5">
              <div class="d-flex flex-column gap-4">
                @foreach ($questions as $que => $ans)
                  @if(!empty($ans))
                    <div class="border-start border-4 border-primary ps-4 py-1">
                      <h6 class="mb-1 fw-bold">{{ $que }}</h6>
                      <p class="mb-0 text-muted">{{ is_array($ans) ? implode(', ', $ans) : $ans }}</p>
                    </div>
                  @endif
                @endforeach
              </div>
            </div>
          </div>
        @endif
      </div>

      {{-- Right Column: Side Actions & Notes --}}
      <div class="col-lg-4">
        {{-- OnBoarding --}}
        <div class="hitech-card-white mb-6">
          <div class="card-body text-center py-6">
            <h6 class="mb-4">Ready to move forward?</h6>
            <a href="#" data-url="{{ route('job.on.board.create', $jobApplication->id) }}" data-ajax-popup="true" data-size="md" data-title="Add to Job Board" class="btn btn-primary d-block">
              <i class="bx bx-user-plus me-1"></i>Add to Onboarding
            </a>
          </div>
        </div>

        {{-- Skills Management --}}
        <div class="hitech-card-white mb-6">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Candidate Skills</h5>
          </div>
          <div class="card-body pt-5">
            <form action="{{ route('job.application.skill.store', $jobApplication->id) }}" method="POST">
              @csrf
            <div class="mb-4">
              <input type="text" class="form-control" name="skill" id="skill_input" value="{{ $jobApplication->skill }}" placeholder="Type and press enter">
            </div>
            @can('Add Job Application Skill')
              <button type="submit" class="btn btn-label-primary btn-sm w-100">Update Skills</button>
            @endcan
            </form>
          </div>
        </div>

        {{-- Notes --}}
        <div class="hitech-card-white">
          <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Interview Notes</h5>
          </div>
          <div class="card-body pt-5">
            <form action="{{ route('job.application.note.store', $jobApplication->id) }}" method="POST">
              @csrf
            <div class="mb-3">
              <textarea name="note" class="form-control" rows="3" placeholder="Add private feedback..." required></textarea>
            </div>
            @can('Add Job Application Note')
              <button type="submit" class="btn btn-label-info btn-sm w-100 mb-4">Add Note</button>
            @endcan
            </form>

            <div class="d-flex flex-column gap-4 mt-4">
              @foreach ($notes as $note)
                <div class="p-3 rounded bg-light border position-relative">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="fw-bold small">{{ $note->noteCreated->name ?? '-' }}</span>
                    <span class="text-muted tiny text-end">{{ auth()->user()->dateFormat($note->created_at) }}</span>
                  </div>
                  <p class="mb-0 small text-muted">{{ $note->note }}</p>
                  @if ($note->note_created == \auth()->user()->id)
                    <div class="text-end mt-2">
                      <form action="{{ route('job.application.note.destroy', $note->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-link text-danger p-0 tiny">Delete</button>
                      </form>
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@if(!$isAjax)
    @endsection
@endif
