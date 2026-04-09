@extends('layouts/layoutMaster')

@section('title', 'Edit Job')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/quill/typography.scss',
    'resources/assets/vendor/libs/quill/katex.scss',
    'resources/assets/vendor/libs/quill/editor.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/tagify/tagify.scss',
    'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss'
  ])
@endsection

@section('page-style')
    @vite(['resources/assets/vendor/scss/pages/page-user-view.scss', 'resources/assets/css/employee-view.css'])
    <style>
        .emp-field-box {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            transition: all 0.2s ease;
        }
        .emp-field-box:hover { 
            border-color: #127464; 
            background-color: #F0FAFA; 
            transform: translateY(-2px);
        }
        .form-control-hitech, .form-select-hitech {
            border-radius: 10px !important;
            border: 1px solid #E2E8F0;
            padding: 0.75rem 1rem;
            font-size: 0.92rem;
            background-color: #F8FAFC;
            transition: all 0.2s ease;
        }
        .form-control-hitech:focus, .form-select-hitech:focus {
            background-color: #fff;
            border-color: #127464;
            box-shadow: 0 0 0 4px rgba(18,116,100,0.1);
            outline: none;
        }
        .hitech-card-white {
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        .btn-hitech-primary {
            background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);
            border: none;
            color: white;
            padding: 0.6rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-hitech-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(18, 116, 100, 0.3);
            color: white;
        }
        .btn-hitech-secondary {
            background-color: #F1F5F9;
            color: #475569;
            padding: 0.6rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            border: 1px solid #E2E8F0;
            transition: all 0.3s ease;
        }
        .btn-hitech-secondary:hover {
            background-color: #E2E8F0;
            color: #1E293B;
        }
        .custom-option-label {
            cursor: pointer;
            width: 100%;
        }
        .custom-option-input:checked + .custom-option-label .emp-field-box {
            border-color: #127464;
            background-color: #F0FAFA;
        }
    </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/quill/katex.js',
    'resources/assets/vendor/libs/quill/quill.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/tagify/tagify.js',
    'resources/assets/vendor/libs/flatpickr/flatpickr.js'
  ])
@endsection

@section('page-script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for jQuery to be ready if it's used
            const initPage = function() {
                // Initialize Flatpickr
                const flatpickrConfig = {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    monthSelectorType: 'static'
                };
                
                // Initialize all date inputs
                document.querySelectorAll('.flatpickr-date').forEach(el => {
                    flatpickr(el, flatpickrConfig);
                });

                // Initialize Tagify for Skills
                const skillEl = document.querySelector('#skill');
                if (skillEl) {
                    new Tagify(skillEl);
                }

                // Initialize Quill Editors
                const quillConfig = {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ header: [1, 2, false] }],
                            ['bold', 'italic', 'underline'],
                            ['image', 'code-block']
                        ]
                    }
                };

                const descEditor = document.querySelector('#description-editor');
                if (descEditor) {
                    const quillDescription = new Quill(descEditor, quillConfig);
                    quillDescription.root.innerHTML = `{!! $job->description !!}`;
                    quillDescription.on('text-change', function() {
                        document.getElementById('description').value = quillDescription.root.innerHTML;
                    });
                }

                const reqEditor = document.querySelector('#requirement-editor');
                if (reqEditor) {
                    const quillRequirement = new Quill(reqEditor, quillConfig);
                    quillRequirement.root.innerHTML = `{!! $job->requirement !!}`;
                    quillRequirement.on('text-change', function() {
                        document.getElementById('requirement').value = quillRequirement.root.innerHTML;
                    });
                }

                const termsEditor = document.querySelector('#terms-editor');
                if (termsEditor) {
                    const quillTerms = new Quill(termsEditor, quillConfig);
                    quillTerms.root.innerHTML = `{!! $job->terms_and_conditions !!}`;
                    quillTerms.on('text-change', function() {
                        document.getElementById('terms_and_conditions').value = quillTerms.root.innerHTML;
                    });
                }

                // Show/hide Terms & Conditions editor
                const visibilityTerms = document.querySelector('input[name="visibility[]"][value="terms"]');
                const termsWrapper = document.getElementById('terms-wrapper');
                if (visibilityTerms && termsWrapper) {
                    termsWrapper.style.display = visibilityTerms.checked ? 'block' : 'none';
                    visibilityTerms.addEventListener('change', function() {
                        termsWrapper.style.display = this.checked ? 'block' : 'none';
                    });
                }

                // Live preview updates
                const titleInput = document.querySelector('input[name="title"]');
                if (titleInput) {
                    titleInput.addEventListener('input', function() {
                        const el = document.getElementById('preview-title');
                        if (el) el.textContent = this.value || 'Job Title';
                    });
                }
                const positionInput = document.querySelector('input[name="position"]');
                if (positionInput) {
                    positionInput.addEventListener('input', function() {
                        const el = document.getElementById('preview-positions');
                        if (el) el.textContent = this.value || '0';
                    });
                }
                const branchSelect = document.querySelector('select[name="branch"]');
                if (branchSelect) {
                    branchSelect.addEventListener('change', function() {
                        const el = document.getElementById('preview-branch');
                        if (el) el.textContent = this.options[this.selectedIndex].text || 'Select Branch';
                    });
                }
            };
            initPage();
        });
    </script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Edit Job</h4>
            <span class="text-muted" style="font-size: 0.85rem;">Update the job vacancy details.</span>
        </div>
        <a href="{{ route('job.index') }}" class="btn btn-outline-secondary rounded-pill btn-sm d-flex align-items-center" style="font-size: 0.8rem; font-weight: 500;">
            <i class="bx bx-arrow-back me-1" style="font-size: 1rem;"></i> Back to List
        </a>
    </div>

    <form id="jobForm" action="{{ route('job.update', $job->id) }}" method="post" class="needs-validation" novalidate>
    @csrf
    @method('PUT')
    <div class="row">
        <!-- Sidebar -->
        <div class="col-xl-3 col-lg-4 col-md-5 col-12 mb-4">
            <!-- Job Preview Card -->
            <div class="card mb-4 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 12px;">
                <div style="height: 6px; background-color: #127464; position: absolute; top: 0; left: 0; right: 0;"></div>
                <div class="card-body pt-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center border border-4 border-white shadow-sm" style="width: 100px; height: 100px; background-color: #127464; color: white;">
                            <i class="bx bx-briefcase fs-1"></i>
                        </div>
                        <h5 class="mt-3 mb-1 fw-bold" id="preview-title" style="color: #1E293B; font-size: 1.1rem;">{{ $job->title ?? 'Job Title' }}</h5>
                        <p class="text-muted mb-2" id="preview-branch" style="font-size: 0.85rem; font-weight: 500;">
                            @foreach($branches as $id => $name)
                                @if($job->branch == $id) {{ $name }} @endif
                            @endforeach
                        </p>
                        <span class="badge" style="background-color: #E0F2F1; color: #127464; font-size: 0.7rem; font-weight: 700; padding: 0.4em 1em; border-radius: 50px;">{{ strtoupper($status[$job->status] ?? 'DRAFT') }}</span>
                    </div>
                    <div class="border-top pt-3">
                        <ul class="list-unstyled mb-0" style="font-size: 0.82rem; color: #64748B;">
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-time text-muted me-2" style="font-size: 1rem; width: 18px;"></i> Validity: Open Ended
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-group text-muted me-2" style="font-size: 1rem; width: 18px;"></i>
                                Positions: <span class="ms-1 fw-bold" id="preview-positions">{{ $job->position ?? 0 }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Job Controls -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header py-3 px-4" style="background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);">
                    <h6 class="fw-bold mb-0 text-white small text-uppercase" style="letter-spacing: 1.5px;"><i class="bx bx-cog me-2"></i>Job Controls</h6>
                </div>
                <div class="card-body p-4 bg-white">
                    <div class="mb-4">
                        <label class="form-label-hitech">Job Status</label>
                        <select name="status" class="form-select-hitech w-100" required>
                            @foreach($status as $key => $val)
                                <option value="{{ $key }}" {{ $job->status == $key ? 'selected' : '' }}>{{ $val }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label-hitech">Priority Level</label>
                        <div class="p-3 rounded-3 border" style="background: rgba(18,116,100,0.03); border-color: rgba(18,116,100,0.1) !important;">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="small fw-bold text-dark">Urgent Hiring</span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" style="width: 2.5em; height: 1.25em;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-hitech-primary shadow-sm">
                    <i class="bx bx-save me-1"></i> Update Job
                </button>
                <a href="{{ route('job.index') }}" class="btn btn-label-secondary">Cancel</a>
            </div>
        </div>

        <!-- Main Content (All sections visible at once) -->
        <div class="col-xl-9 col-lg-8 col-md-7 col-12">

            <!-- Section 1: General Info -->
            <div class="card hitech-card-white mb-4">
                <div class="card-header py-3 px-4 border-bottom d-flex align-items-center">
                    <i class="bx bx-list-ul me-2 fs-5" style="color: #127464;"></i>
                    <h6 class="mb-0 fw-bold">Primary Details</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Job Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control-hitech w-100" required placeholder="e.g. Senior Software Engineer" value="{{ $job->title }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Branch / Site <span class="text-danger">*</span></label>
                                <select name="branch" class="form-select-hitech w-100" required>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $id => $name)
                                        <option value="{{ $id }}" {{ $job->branch == $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Job Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select-hitech w-100" required>
                                    @foreach($categories as $id => $title)
                                        <option value="{{ $id }}" {{ $job->category == $id ? 'selected' : '' }}>{{ $title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Number of Positions <span class="text-danger">*</span></label>
                                <input type="number" name="position" class="form-control-hitech w-100" required placeholder="1" value="{{ $job->position }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Skills <span class="text-danger">*</span></label>
                                <input id="skill" name="skill" class="form-control-hitech w-100" required placeholder="Enter skills" value="{{ $job->skill }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Start Date</label>
                                <input type="text" name="start_date" class="form-control-hitech w-100 flatpickr-date" placeholder="Select Date" readonly value="{{ $job->start_date }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">End Date</label>
                                <input type="text" name="end_date" class="form-control-hitech w-100 flatpickr-date" placeholder="Select Date" readonly value="{{ $job->end_date }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Application Requirements -->
            <div class="card hitech-card-white mb-4">
                <div class="card-header py-3 px-4 border-bottom d-flex align-items-center">
                    <i class="bx bx-file-find me-2 fs-5" style="color: #127464;"></i>
                    <h6 class="mb-0 fw-bold">Application Requirements</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-4 rounded-4" style="background: #f8fafc; border: 1px solid #eef2f6;">
                                <h6 class="fw-bold mb-3 d-flex align-items-center"><i class="bx bx-question-mark me-2 text-primary"></i> Data Collection</h6>
                                <div class="d-flex flex-column gap-2">
                                    @foreach(['gender' => 'Gender', 'dob' => 'Date of Birth', 'address' => 'Full Address'] as $val => $label)
                                        <div class="position-relative">
                                            <input class="form-check-input custom-option-input d-none" type="checkbox" name="applicant[]" value="{{ $val }}" id="check-{{ $val }}" {{ in_array($val, is_array($job->applicant) ? $job->applicant : explode(',', $job->applicant)) ? 'checked' : '' }}>
                                            <label class="custom-option-label" for="check-{{ $val }}">
                                                <div class="emp-field-box py-2 px-3 d-flex align-items-center">
                                                    <i class="bx bx-check-circle me-3 fs-5 text-muted check-icon"></i>
                                                    <span class="h6 mb-0">{{ $label }}</span>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 rounded-4" style="background: #fdf2f2; border: 1px solid #fee2e2;">
                                <h6 class="fw-bold mb-3 d-flex align-items-center"><i class="bx bx-show me-2 text-danger"></i> Transparency Options</h6>
                                <div class="d-flex flex-column gap-2">
                                    @foreach(['profile' => 'Profile Image', 'resume' => 'Upload Resume', 'letter' => 'Cover Letter', 'terms' => 'Terms & Conditions'] as $val => $label)
                                        <div class="position-relative">
                                            <input class="form-check-input custom-option-input d-none" type="checkbox" name="visibility[]" value="{{ $val }}" id="visibility-{{ $val }}" {{ in_array($val, is_array($job->visibility) ? $job->visibility : explode(',', $job->visibility)) ? 'checked' : '' }}>
                                            <label class="custom-option-label" for="visibility-{{ $val }}">
                                                <div class="emp-field-box py-2 px-3 d-flex align-items-center">
                                                    <i class="bx bx-check-circle me-3 fs-5 text-muted check-icon"></i>
                                                    <span class="h6 mb-0">{{ $label }}</span>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @if(count($customQuestion) > 0)
                        <div class="col-12">
                            <div class="p-4 rounded-4 border" style="border-style: dashed !important;">
                                <h6 class="fw-bold mb-3 text-muted text-uppercase small">Additional Screening Questions</h6>
                                <div class="row g-3">
                                    @foreach ($customQuestion as $question)
                                        <div class="col-md-6">
                                            <div class="position-relative">
                                                <input class="form-check-input custom-option-input d-none" type="checkbox" name="custom_question[]" value="{{ $question->id }}" id="custom_question_{{ $question->id }}" @if($question->is_required == 'yes') required @endif {{ in_array($question->id, is_array($job->custom_question) ? $job->custom_question : explode(',', $job->custom_question)) ? 'checked' : '' }}>
                                                <label class="custom-option-label" for="custom_question_{{ $question->id }}">
                                                    <div class="emp-field-box py-2 px-3 d-flex align-items-center">
                                                        <i class="bx bx-check-circle me-3 fs-5 text-muted check-icon"></i>
                                                        <span class="h6 mb-0">{{ $question->question }} @if($question->is_required == 'yes') <span class="text-danger">*</span> @endif</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Section 3: Job Content -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card hitech-card-white h-100">
                        <div class="card-header py-3 px-4 border-bottom">
                            <h6 class="mb-0 fw-bold">Job Description <span class="text-danger">*</span></h6>
                        </div>
                        <div class="card-body p-4">
                            <div id="description-editor" style="height: 350px;"></div>
                            <input type="hidden" name="description" id="description" required value="{{ $job->description }}">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card hitech-card-white h-100">
                        <div class="card-header py-3 px-4 border-bottom">
                            <h6 class="mb-0 fw-bold">Requirements & Qualifications <span class="text-danger">*</span></h6>
                        </div>
                        <div class="card-body p-4">
                            <div id="requirement-editor" style="height: 350px;"></div>
                            <input type="hidden" name="requirement" id="requirement" required value="{{ $job->requirement }}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Terms & Conditions (shown when checkbox is ticked) -->
            <div id="terms-wrapper" class="mb-4" style="display: none;">
                <div class="card hitech-card-white">
                    <div class="card-header py-3 px-4 border-bottom">
                        <h6 class="mb-0 fw-bold">Terms & Conditions <span class="text-danger">*</span></h6>
                    </div>
                    <div class="card-body p-4">
                        <div id="terms-editor" style="height: 300px;"></div>
                        <input type="hidden" name="terms_and_conditions" id="terms_and_conditions" value="{{ $job->terms_and_conditions }}">
                    </div>
                </div>
            </div>

        </div>{{-- end main content col --}}
    </div>{{-- end row --}}
    </form>
</div>
@endsection
