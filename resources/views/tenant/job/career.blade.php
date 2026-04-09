@extends('layouts/layoutMaster')

@section('title', 'Join Our Team | Professional Opportunities')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate-css.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
  ])
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Unique to Career Page */
    .filter-sidebar {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        border: 1px solid #E2E8F0;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.02);
        position: sticky;
        top: 100px;
    }

    .filter-group-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748B;
        letter-spacing: 0.08em;
        margin-bottom: 1rem;
        display: block;
    }

    .job-card {
        background: white;
        border-radius: 20px;
        padding: 1.75rem;
        border: 1px solid #E2E8F0;
        margin-bottom: 1.5rem;
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
        cursor: pointer;
        position: relative;
    }

    .job-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 40px rgba(0, 109, 119, 0.08);
        border-color: #008080;
    }

    .job-title {
        font-weight: 800;
        font-size: 1.2rem;
        color: #004d54;
        margin-bottom: 0.5rem;
    }

    .job-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1.25rem;
        color: #64748B;
        font-size: 0.875rem;
        margin-bottom: 1.25rem;
    }

    .job-meta i {
        color: #008080;
        margin-right: 0.4rem;
    }

    .salary-estimate {
        font-weight: 700;
        color: #059669;
        font-size: 0.9rem;
    }

    .job-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
        margin-top: 2rem;
    }

    .info-item label {
        display: block;
        font-size: 11px;
        font-weight: 800;
        color: #94A3B8;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }

    .info-item span {
        font-weight: 700;
        color: #004d54;
    }

    .modal-tabs {
        border-bottom: 2px solid #F1F5F9;
        padding: 0 4rem;
        background: #fff;
    }

    .modal-tabs .nav-link {
        border: none;
        padding: 1.5rem 0;
        margin-right: 3rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.8rem;
        color: #64748B;
        position: relative;
    }

    .modal-tabs .nav-link.active {
        color: #008080;
        background: transparent;
    }

    .modal-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 3px;
        background: #008080;
    }

    .modal-body-content {
        padding: 3rem 4rem;
        max-height: 500px;
        overflow-y: auto;
    }

    .content-section-title {
        font-weight: 800;
        color: #004d54;
        margin-bottom: 1.5rem;
    }

    .content-section p, .content-section li {
        line-height: 1.8;
        color: #475569;
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
    <div class="header-actions d-none d-md-block">
        <span class="text-muted small fw-bold">Professional Recruitment Portal</span>
    </div>
</div>

<div class="container py-12">
    <div class="row">
        {{-- Filter Sidebar --}}
        <div class="col-lg-3">
            <div class="filter-sidebar">
                <form action="{{ route('career', [$currantLang]) }}" method="GET" id="filter-form">
                    <div class="mb-8">
                        <label class="filter-group-title">Search Roles</label>
                        <div class="input-group border rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-0"><i class="bx bx-search text-muted"></i></span>
                            <input type="text" name="title" class="form-control border-0" placeholder="Keyword..." value="{{ request()->title }}">
                        </div>
                    </div>

                    <div class="mb-8">
                        <label class="filter-group-title">Location</label>
                        <select name="location" class="form-select border rounded-3" onchange="this.form.submit()">
                            <option value="">All Locations</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ request()->location == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-8">
                        <label class="filter-group-title">Category</label>
                        <select name="category" class="form-select border rounded-3" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ request()->category == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <a href="{{ route('career', [$currantLang]) }}" class="text-primary small fw-bold text-decoration-none">Clear All Filters</a>
                </form>
            </div>
        </div>

        {{-- Job Listings --}}
        <div class="col-lg-9 ps-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-8">
                <div>
                    <h2 class="fw-extrabold text-heading mb-0">Open Positions</h2>
                    <p class="text-muted small">We found {{ $jobs->count() }} matches for your criteria</p>
                </div>
            </div>

            @forelse($jobs as $job)
                <div class="job-card animate__animated animate__fadeInUp" onclick="showJobDetails({{ $job->id }})">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="job-title">{{ $job->title }}</div>
                            <div class="job-meta">
                                <span><i class="bx bx-map"></i>{{ $job->branches->name ?? 'Remote' }}</span>
                                <span><i class="bx bx-briefcase-alt-2"></i>{{ $job->job_type ?? 'Full-time' }}</span>
                                <span><i class="bx bx-category"></i>{{ $job->categories->title ?? 'General' }}</span>
                                @if($job->salary)
                                    <span class="salary-estimate"><i class="bx bx-wallet"></i>{{ $job->salary }}</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <button class="btn-job-apply" onclick="event.stopPropagation(); window.location.href='{{ route('job.apply', [$job->code, $currantLang]) }}'">Apply Now</button>
                        </div>
                    </div>
                </div>

                {{-- Job Detail Modal --}}
                <div class="modal fade modal-job-detail" id="jobModal_{{ $job->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <div class="modal-content modal-content-hitech">
                            <div class="modal-job-header">
                                <button type="button" class="btn-close position-absolute" style="top: 2rem; right: 2rem;" data-bs-dismiss="modal"></button>
                                
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="modal-social-share">
                                        <a href="#"><i class="bx bxl-linkedin"></i></a>
                                        <a href="#"><i class="bx bxl-twitter"></i></a>
                                        <a href="#"><i class="bx bxl-facebook"></i></a>
                                        <a href="mailto:?subject=Job Opportunity: {{ $job->title }}"><i class="bx bx-envelope"></i></a>
                                    </div>
                                    <a href="{{ route('job.apply', [$job->code, $currantLang]) }}" class="btn btn-apply-modal">Apply</a>
                                </div>

                                <h2 class="fw-extrabold text-heading" style="font-size: 2.25rem;">{{ $job->title }}</h2>

                                <div class="job-info-grid">
                                    <div class="info-item">
                                        <label>Salary</label>
                                        <span>{{ $job->salary ?? 'Competitive' }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Location <i class="bx bx-info-circle small text-muted"></i></label>
                                        <span>{{ $job->branches->name ?? 'Remote' }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Job Type</label>
                                        <span>{{ $job->job_type ?? 'Regular Full-time' }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Department</label>
                                        <span>{{ $job->categories->title ?? 'General' }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Opening Date</label>
                                        <span>{{ $job->created_at->format('m/d/Y') }}</span>
                                    </div>
                                    <div class="info-item">
                                        <label>Positions Remaining</label>
                                        <span>{{ $job->position }}</span>
                                    </div>
                                </div>
                            </div>

                            <ul class="nav nav-tabs modal-tabs" id="jobTabs_{{ $job->id }}" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#desc_{{ $job->id }}">Description</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#benefits_{{ $job->id }}">Benefits</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reqs_{{ $job->id }}">Requirements</button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active modal-body-content" id="desc_{{ $job->id }}">
                                    <div class="content-section">
                                        <h4 class="content-section-title">Position Description</h4>
                                        {!! $job->description !!}
                                    </div>
                                </div>
                                <div class="tab-pane fade modal-body-content" id="benefits_{{ $job->id }}">
                                    <div class="content-section">
                                        <h4 class="content-section-title">Employee Benefits</h4>
                                        @if($job->benefits)
                                            {!! $job->benefits !!}
                                        @else
                                            <p>We offer a comprehensive benefits package including health insurance, retirement plans, and paid time off. Details will be provided during the interview process.</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="tab-pane fade modal-body-content" id="reqs_{{ $job->id }}">
                                    <div class="content-section">
                                        <h4 class="content-section-title">Job Requirements</h4>
                                        {!! $job->requirement !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-20 bg-white rounded-4 border">
                    <i class="bx bx-search fs-1 text-muted mb-4"></i>
                    <h4 class="fw-bold">No jobs match your filters</h4>
                    <p class="text-muted">Try adjusting your filters or search keywords.</p>
                    <a href="{{ route('career', [$currantLang]) }}" class="btn btn-primary rounded-pill">Reset Filters</a>
                </div>
            @endforelse
        </div>
    </div>
</div>

<footer class="py-10 border-top bg-white mt-20">
    <div class="container text-center">
        <p class="mb-0 text-muted small">&copy; {{ date('Y') }} {{ isset($companySettings['footer_text']->value) ? $companySettings['footer_text']->value : 'Hi Tech HRX' }}. All rights reserved.</p>
    </div>
</footer>

@endsection

@section('page-script')
<script>
    function showJobDetails(id) {
        var myModal = new bootstrap.Modal(document.getElementById('jobModal_' + id));
        myModal.show();
    }
</script>
@endsection
