@extends('layouts/layoutMaster')

@section('title', 'Job Details')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #1E293B;">{{ $job->title }}</h4>
        <span class="text-muted" style="font-size: 0.85rem;">Manage job details, candidate applications, and requirements.</span>
    </div>
    <div>
      <a href="{{ route('job.index') }}" class="btn btn-outline-secondary rounded-pill btn-sm d-flex align-items-center shadow-sm px-3" style="font-size: 0.8rem; font-weight: 500; border-color: #E2E8F0; color: #475569;">
        <i class="bx bx-arrow-back me-1"></i> Back to Jobs
      </a>
    </div>
  </div>

  <style>
      /* =================== TAB NAVIGATION =================== */
      .rosemary-nav-tabs-wrapper {
          background-color: #F8FAFC;
          border-radius: 50px;
          border: 1px solid #E2E8F0;
          padding: 8px;
          width: 100%;
          max-width: 100%;
          margin-bottom: 2rem;
          box-shadow: 0 4px 15px rgba(0,0,0,0.02);
          display: flex;
          align-items: center;
          position: relative;
          z-index: 10;
      }
      .rosemary-nav-tabs {
          display: flex;
          justify-content: center;
          flex-wrap: nowrap !important;
          overflow-x: auto !important;
          gap: 1.5rem;
          border: none !important;
          width: 100%;
          -ms-overflow-style: none;
          scrollbar-width: none;
      }
      .rosemary-nav-tabs::-webkit-scrollbar { display: none; }
      .rosemary-nav-tabs .nav-link {
          color: #718096 !important;
          font-weight: 700;
          font-size: 0.75rem;
          border: none;
          padding: 0.75rem 1.5rem !important;
          border-radius: 50px !important;
          transition: all 0.3s ease;
          text-transform: capitalize;
          letter-spacing: 0.3px;
          background-color: transparent !important;
          display: flex;
          align-items: center;
          white-space: nowrap !important;
          flex-shrink: 0;
      }
      .rosemary-nav-tabs .nav-link.active {
          background-color: #127464 !important;
          color: #fff !important;
          box-shadow: 0 4px 12px rgba(18, 116, 100, 0.25);
      }
      .rosemary-nav-tabs .nav-link:hover:not(.active) {
          background-color: rgba(18, 116, 100, 0.05) !important;
          color: #127464 !important;
      }

      @media (max-width: 991px) {
          .rosemary-nav-tabs-wrapper {
              display: none !important;
          }
          .mobile-tab-navigation {
              display: flex !important;
              justify-content: space-between;
              padding: 1rem;
              background: #fff;
              border-top: 1px solid #eee;
              position: fixed;
              bottom: 0;
              left: 0;
              right: 0;
              z-index: 100;
              box-shadow: 0 -5px 15px rgba(0,0,0,0.05);
          }
      }
      .mobile-tab-navigation { display: none; }

      /* =================== CARDS & BOXES =================== */
      .card, .emp-card, .hitech-card {
          border: 1px solid #E2E8F0 !important;
          border-radius: 12px !important;
          box-shadow: 0 4px 20px rgba(0,0,0,0.03) !important;
          background: #fff;
          overflow: hidden;
          width: 100%;
      }
      .emp-card .card-body { padding: 1.75rem !important; }
      .emp-field-box {
          background-color: #F8FAFC;
          border: 1px solid #E2E8F0;
          border-radius: 12px;
          padding: 1rem 1.2rem;
          transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      }
      .emp-field-box:hover { 
          border-color: #127464; 
          background-color: #F0FAFA; 
          box-shadow: 0 4px 12px rgba(18, 116, 100, 0.05); 
          transform: translateY(-2px);
      }
  </style>

  <!-- Nav Tabs -->
  <div class="rosemary-nav-tabs-wrapper mb-4 mt-2 mx-4">
    <ul class="nav nav-pills border-0 flex-column flex-md-row rosemary-nav-tabs" id="pills-tab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pills-overview-tab" data-bs-toggle="pill" data-bs-target="#pills-overview" type="button" role="tab" aria-controls="pills-overview" aria-selected="true">
          <i class="bx bx-briefcase-alt-2 me-2 fs-5"></i> Job Overview
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="pills-candidates-tab" data-bs-toggle="pill" data-bs-target="#pills-candidates" type="button" role="tab" aria-controls="pills-candidates" aria-selected="false">
          <i class="bx bx-group me-2 fs-5"></i> Candidates <span class="badge bg-white text-primary rounded-pill ms-2" style="color: #127464 !important;">{{ count($job->applications) }}</span>
        </button>
      </li>
    </ul>
  </div>

  <div class="tab-content px-4 py-0 border-0 bg-transparent" id="pills-tabContent">
    <!-- TAB 1: OVERVIEW -->
    <div class="tab-pane fade show active" id="pills-overview" role="tabpanel" aria-labelledby="pills-overview-tab">
      <div class="row g-6">
        <div class="col-lg-8">
          <div class="card emp-card mb-6">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <i class="bx bx-briefcase me-2 fs-5" style="color: #127464;"></i>
                <h6 class="card-title mb-0 fw-bold" style="color: #1E293B;">Overview</h6>
              </div>
              <div class="d-flex align-items-center gap-3">
                @if($job->status == 'active')
                  <span class="badge bg-label-success rounded-pill px-3 py-1 fw-bold">Active</span>
                @else
                  <span class="badge bg-label-danger rounded-pill px-3 py-1 fw-bold">Inactive</span>
                @endif
                @can('Edit Job')
                  <a href="{{ route('job.edit', $job->id) }}" class="btn btn-hitech-primary rounded-pill btn-sm d-flex align-items-center shadow-sm px-3" style="font-size: 0.8rem; font-weight: 500; background-color: #127464; border-color: #127464;">
                    <i class="bx bx-edit fs-6 me-2"></i> Edit
                  </a>
                @endcan
              </div>
            </div>
            <div class="card-body">
              <div class="row g-4 mb-6">
                <div class="col-md-4">
                  <div class="emp-field-box h-100">
                    <p class="mb-1 text-muted smallest fw-bold text-uppercase">Branch / Site</p>
                    <p class="mb-0 fw-bold text-dark">{{ !empty($job->branches) ? $job->branches->name : __('All') }}</p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="emp-field-box h-100">
                    <p class="mb-1 text-muted smallest fw-bold text-uppercase">Category</p>
                    <p class="mb-0 fw-bold text-dark">{{ !empty($job->categories) ? $job->categories->title : '-' }}</p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="emp-field-box h-100">
                    <p class="mb-1 text-muted smallest fw-bold text-uppercase">Positions</p>
                    <p class="mb-0 fw-bold text-dark">{{ $job->position }}</p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="emp-field-box h-100">
                    <p class="mb-1 text-muted smallest fw-bold text-uppercase">Created At</p>
                    <p class="mb-0 fw-bold text-dark">{{ auth()->user()->dateFormat($job->created_at) }}</p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="emp-field-box h-100">
                    <p class="mb-1 text-muted smallest fw-bold text-uppercase">Start Date</p>
                    <p class="mb-0 fw-bold text-dark">{{ auth()->user()->dateFormat($job->start_date) }}</p>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="emp-field-box h-100">
                    <p class="mb-1 text-muted smallest fw-bold text-uppercase">End Date</p>
                    <p class="mb-0 fw-bold text-dark">{{ auth()->user()->dateFormat($job->end_date) }}</p>
                  </div>
                </div>
              </div>

              <div class="mb-6">
                <div class="d-flex align-items-center mb-3">
                  <i class="bx bx-award me-2 fs-5" style="color: #127464;"></i>
                  <h6 class="fw-bold mb-0" style="color: #1E293B;">Skills Required</h6>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  @foreach($job->skill as $skill)
                    <span class="badge bg-label-primary rounded-pill px-3 py-2 fw-bold">{{ $skill }}</span>
                  @endforeach
                </div>
              </div>

              <div class="mb-6">
                <div class="d-flex align-items-center mb-3">
                  <i class="bx align-left me-2 fs-5" style="color: #127464;"></i>
                  <h6 class="fw-bold mb-0" style="color: #1E293B;">Description</h6>
                </div>
                <div class="emp-field-box text-muted lh-lg">
                  {!! $job->description !!}
                </div>
              </div>

              <div class="mb-6">
                <div class="d-flex align-items-center mb-3">
                  <i class="bx bx-list-check me-2 fs-5" style="color: #127464;"></i>
                  <h6 class="fw-bold mb-0" style="color: #1E293B;">Requirements</h6>
                </div>
                <div class="emp-field-box text-muted lh-lg">
                  {!! $job->requirement !!}
                </div>
              </div>

              @if (!empty($job->terms_and_conditions))
                <div class="mb-4">
                  <div class="p-4 rounded-4" style="background: #fef3c7; border: 1px solid #fde68a;">
                    <div class="d-flex align-items-center mb-3">
                      <div class="bg-white shadow-sm rounded-pill p-2 me-3" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="bx bx-info-circle" style="color: #d97706;"></i></div>
                      <h6 class="mb-0 fw-bold" style="color: #b45309;">Terms & Conditions</h6>
                    </div>
                    <div class="text-muted italic lh-lg">
                      {!! $job->terms_and_conditions !!}
                    </div>
                  </div>
                </div>
              @endif
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card emp-card mb-6">
            <div class="card-header border-bottom d-flex align-items-center py-4">
              <i class="bx bx-cog me-2 fs-5" style="color: #127464;"></i>
              <h6 class="card-title mb-0 fw-bold" style="color: #1E293B;">Application Configuration</h6>
            </div>
            <div class="card-body">
              <div class="mb-6">
                <h6 class="fw-bold small text-muted text-uppercase mb-3">Questions & Fields</h6>
                <ul class="list-group list-group-flush border rounded overflow-hidden shadow-none">
                  @if($job->applicant)
                    @foreach($job->applicant as $applicant)
                      <li class="list-group-item d-flex align-items-center gap-3">
                        <i class="bx bx-check-circle text-success fs-5"></i>
                        <span class="fw-medium text-dark">Ask For: <strong>{{ ucfirst($applicant) }}</strong></span>
                      </li>
                    @endforeach
                  @endif
                  @if($job->visibility)
                    @foreach($job->visibility as $visibility)
                      <li class="list-group-item d-flex align-items-center gap-3 bg-light">
                        <i class="bx bx-show text-info fs-5"></i>
                        <span class="fw-medium text-dark">Show on Board: <strong>{{ ucfirst($visibility) }}</strong></span>
                      </li>
                    @endforeach
                  @endif
                </ul>
              </div>

              @if(count($job->questions()) > 0)
                <div>
                  <h6 class="fw-bold small text-muted text-uppercase mb-3">Custom Questions</h6>
                  <div class="d-flex flex-column gap-3">
                    @foreach($job->questions() as $question)
                      <div class="emp-field-box">
                        <div class="d-flex align-items-start gap-3">
                          <i class="bx bx-question-mark text-primary mt-1 fs-5"></i>
                          <span class="small fw-bold text-dark">{{ $question->question }}</span>
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
              @endif
            </div>
          </div>

          <div class="card emp-card border-0 shadow-sm overflow-hidden mb-6">
            <div class="px-4 py-5 text-center position-relative" style="background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);">
               <i class="bx bx-share-alt fs-1 text-white opacity-50 mb-2"></i>
               <h5 class="text-white fw-bold mb-1">Share Job Listing</h5>
               <p class="text-white text-opacity-75 small mb-0">Direct candidates to apply using the unique link below.</p>
            </div>
            <div class="card-body pt-4">
              <p class="text-muted small mb-4">Direct candidates to apply using the unique link below or share across social platforms.</p>
              
              <div class="d-grid mb-4">
                <button class="btn btn-outline-hitech rounded-pill copy_link py-2 fw-bold d-flex align-items-center justify-content-center" style="color: #127464; border: 2px solid #127464; background: transparent;" data-link="{{ route('job.requirement', [$job->code, 'en']) }}">
                  <i class="bx bx-copy me-2"></i>Copy Career Link
                </button>
              </div>

              <div class="text-center">
                <h6 class="text-muted small fw-bold text-uppercase mb-3">Share On</h6>
                <div class="d-flex justify-content-center gap-3">
                  <a href="https://wa.me/?text={{ urlencode('Check out this job opportunity: ' . $job->title . ' - ' . route('job.requirement', [$job->code, 'en'])) }}" target="_blank" class="btn btn-sm btn-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #E8F5E9; color: #25D366; width: 40px; height: 40px;" data-bs-toggle="tooltip" title="WhatsApp">
                    <i class="bx bxl-whatsapp fs-4"></i>
                  </a>
                  <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('job.requirement', [$job->code, 'en'])) }}" target="_blank" class="btn btn-sm btn-icon rounded-circle d-flex align-items-center justify-content-center" style="background-color: #E3F2FD; color: #0A66C2; width: 40px; height: 40px;" data-bs-toggle="tooltip" title="LinkedIn">
                    <i class="bx bxl-linkedin fs-4"></i>
                  </a>
                  <a href="mailto:?subject={{ urlencode('Job Opportunity: ' . $job->title) }}&body={{ urlencode('Check out this job opportunity: ' . route('job.requirement', [$job->code, 'en'])) }}" class="btn btn-sm btn-icon rounded-circle" style="background-color: #F8FAFC; color: #64748B; width: 40px; height: 40px; border: 1px solid #E2E8F0;" data-bs-toggle="tooltip" title="Email">
                    <i class="bx bx-envelope fs-4"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- TAB 2: CANDIDATES -->
    <div class="tab-pane fade" id="pills-candidates" role="tabpanel" aria-labelledby="pills-candidates-tab">
      <div class="card emp-card border-0 shadow-sm overflow-hidden mb-6">
        <div class="card-header border-bottom py-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                 <i class="bx bx-group me-2 fs-4" style="color: #127464;"></i>
                 <div>
                     <h5 class="card-title mb-1 fw-bold" style="color: #1E293B;">Candidate Applications</h5>
                     <p class="text-muted small mb-0">Showing all {{ count($job->applications) }} resumes received for this position.</p>
                 </div>
            </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-hitech align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="fw-semibold text-muted py-3 ps-4">Candidate</th>
                  <th class="fw-semibold text-muted py-3">Contact</th>
                  <th class="fw-semibold text-muted py-3">Stage</th>
                  <th class="fw-semibold text-muted py-3 text-center">Rating</th>
                  <th class="fw-semibold text-muted py-3 text-center">Applied Date</th>
                  <th class="fw-semibold text-muted py-3 pe-4 text-center">Resume & Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($job->applications as $application)
                  <tr>
                    <td class="ps-4 py-3">
                      <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3">
                          @php
                            $avatarPath = !empty($application->profile) ? 'uploads/job/profile/' . $application->profile : 'uploads/avatar/avatar.png';
                          @endphp
                          <img src="{{ Utility::get_file($avatarPath) }}" alt="Avatar" class="rounded-circle shadow-sm border border-2 border-white d-block" style="width:36px; height:36px; object-fit:cover;">
                        </div>
                        <div class="d-flex flex-column">
                          <a href="#" data-url="{{ route('job-application.show', \Crypt::encrypt($application->id)) }}" data-ajax-popup="true" data-size="xl" data-title="Application Details" class="text-heading fw-bold text-truncate">{{ $application->name }}</a>
                          @if($application->gender)
                            <small class="text-muted">{{ $application->gender }}</small>
                          @endif
                        </div>
                      </div>
                    </td>
                    <td class="py-3">
                      <div class="d-flex flex-column gap-1">
                        <div class="d-flex align-items-center text-muted small">
                          <i class="bx bx-envelope me-2"></i> {{ $application->email }}
                        </div>
                        <div class="d-flex align-items-center text-muted small">
                          <i class="bx bx-phone me-2"></i> {{ $application->phone }}
                        </div>
                      </div>
                    </td>
                    <td class="py-3">
                        @php
                            $stageInfo = \App\Models\JobStage::find($application->stage);
                        @endphp
                        @if($stageInfo)
                            <span class="badge bg-label-info rounded-pill px-3 py-1 fw-semibold shadow-sm border border-info border-opacity-25">{{ $stageInfo->title }}</span>
                        @else
                            <span class="badge bg-label-secondary rounded-pill px-3 py-1 fw-semibold">New</span>
                        @endif
                    </td>
                    <td class="text-center py-3">
                        <div class="d-flex gap-1 justify-content-center">
                          @for ($i = 1; $i <= 5; $i++)
                            <i class="bx bxs-star {{ $i <= $application->rating ? 'text-warning' : 'text-light border-light' }}" style="font-size: 0.9rem;"></i>
                          @endfor
                        </div>
                    </td>
                    <td class="text-center py-3 text-muted small fw-medium">
                        {{ auth()->user()->dateFormat($application->created_at) }}
                    </td>
                    <td class="pe-4 py-3 text-center">
                       <div class="d-flex align-items-center justify-content-center gap-2">
                          @if($application->resume)
                              <a href="{{ Utility::get_file('uploads/job/resume/' . $application->resume) }}" class="btn btn-sm text-white rounded-pill shadow-sm px-3" style="background-color: #127464;" download data-bs-toggle="tooltip" title="Download Resume">
                                <i class="bx bx-download me-1"></i> CV
                              </a>
                          @else
                              <span class="text-muted small fst-italic me-2">No CV Uploaded</span>
                          @endif
                          
                          <a href="#" data-url="{{ route('job-application.show', \Crypt::encrypt($application->id)) }}" data-ajax-popup="true" data-size="xl" data-title="Application Details" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="tooltip" title="Full Application">
                            <i class="bx bx-detail me-1"></i> Details
                          </a>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center py-5">
                       <div class="empty-state text-center">
                            <i class="bx bx-folder-open text-muted opacity-50 mb-3" style="font-size: 3rem;"></i>
                            <h6 class="fw-bold mb-1">No Applications Yet</h6>
                            <p class="text-muted small mb-0">Share the career link to start receiving candidates.</p>
                       </div>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
