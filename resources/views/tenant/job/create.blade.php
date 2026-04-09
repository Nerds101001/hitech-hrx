@extends('layouts.layoutMaster')

@section('title', 'Create Job')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/libs/quill/typography.scss',
        'resources/assets/vendor/libs/quill/katex.scss',
        'resources/assets/vendor/libs/quill/editor.scss',
        'resources/assets/vendor/libs/select2/select2.scss',
        'resources/assets/vendor/libs/tagify/tagify.scss',
        'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
        'resources/assets/vendor/libs/animate-css/animate.scss'
    ])
@endsection

@section('page-style')
    @vite(['resources/assets/vendor/scss/pages/page-user-view.scss', 'resources/assets/css/employee-view.css'])
    <style>
        .rosemary-nav-tabs-wrapper {
            background-color: #F8FAFC;
            border-radius: 50px;
            border: 1px solid #E2E8F0;
            padding: 8px;
            width: 100%;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
        }
        .rosemary-nav-tabs {
            display: flex;
            justify-content: center;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            gap: 1rem;
            border: none !important;
            width: 100%;
            scrollbar-width: none;
            pointer-events: none; /* Disable manual tab clicking to enforce stepper */
        }
        .rosemary-nav-tabs::-webkit-scrollbar { display: none; }
        .rosemary-nav-tabs .nav-link {
            color: #718096 !important;
            font-weight: 700;
            font-size: 0.75rem;
            border: none;
            padding: 0.75rem 1.25rem !important;
            border-radius: 50px !important;
            transition: all 0.3s ease;
            background-color: transparent !important;
            display: flex;
            align-items: center;
            white-space: nowrap !important;
        }
        .rosemary-nav-tabs .nav-link.active {
            background-color: #127464 !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(18, 116, 100, 0.25);
        }
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
        .form-label-hitech {
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: #64748B; margin-bottom: 0.6rem;
            display: block;
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
                    quillDescription.on('text-change', function() {
                        document.getElementById('description').value = quillDescription.root.innerHTML;
                    });
                }

                const reqEditor = document.querySelector('#requirement-editor');
                if (reqEditor) {
                    const quillRequirement = new Quill(reqEditor, quillConfig);
                    quillRequirement.on('text-change', function() {
                        document.getElementById('requirement').value = quillRequirement.root.innerHTML;
                    });
                }

                const termsEditor = document.querySelector('#terms-editor');
                if (termsEditor) {
                    const quillTerms = new Quill(termsEditor, quillConfig);
                    quillTerms.on('text-change', function() {
                        document.getElementById('terms_and_conditions').value = quillTerms.root.innerHTML;
                    });
                }

                // Handle Terms and Conditions visibility
                const checkTerms = document.getElementById('check-terms');
                const legalTabItem = document.getElementById('legal-tab-item');
                
                // Check visibility checkbox (if exists)
                const visibilityCheckTerms = document.querySelector('input[name="visibility[]"][value="terms"]');
                if (visibilityCheckTerms) {
                    visibilityCheckTerms.addEventListener('change', function() {
                        if (legalTabItem) {
                            legalTabItem.style.display = this.checked ? 'block' : 'none';
                            updateStepper(); // Re-evaluate buttons
                        }
                    });
                    // Initial state
                    if (legalTabItem) legalTabItem.style.display = visibilityCheckTerms.checked ? 'block' : 'none';
                }

                // Interactivity (Preview updates)
                const titleInput = document.querySelector('input[name="title"]');
                if (titleInput) {
                    titleInput.addEventListener('input', function() {
                        const preview = document.getElementById('preview-title');
                        if (preview) preview.textContent = this.value || 'New Job Opportunity';
                    });
                }
                
                const branchSelect = document.querySelector('select[name="branch"]');
                if (branchSelect) {
                    branchSelect.addEventListener('change', function() {
                        const preview = document.getElementById('preview-branch');
                        if (preview) preview.textContent = this.options[this.selectedIndex].text || 'Select Branch';
                    });
                }

                // Stepper Navigation
                const tabs = ['general', 'application', 'content-info', 'legal-info'];
                let currentTabIndex = 0;

                const updateStepper = function() {
                    const currentTabId = tabs[currentTabIndex];
                    const activeTabLink = document.querySelector(`a[href="#${currentTabId}"]`);
                    if (activeTabLink) {
                        const bootstrapTab = new bootstrap.Tab(activeTabLink);
                        bootstrapTab.show();
                    }

                    // Handle button visibility
                    const prevBtn = document.getElementById('prev-btn');
                    const nextBtn = document.getElementById('next-btn');
                    const submitBtn = document.getElementById('submit-btn');

                    if (prevBtn) prevBtn.classList.toggle('d-none', currentTabIndex === 0);
                    
                    // Determine if legal tab is visible
                    const isLegalVisible = legalTabItem && legalTabItem.style.display !== 'none';
                    
                    // Final tab check
                    const isFinalTab = currentTabIndex === tabs.length - 1 || 
                                      (!isLegalVisible && currentTabIndex === 2);

                    if (nextBtn) nextBtn.classList.toggle('d-none', isFinalTab);
                    if (submitBtn) submitBtn.classList.toggle('d-none', !isFinalTab);
                }

                const nextBtn = document.getElementById('next-btn');
                if (nextBtn) {
                    nextBtn.addEventListener('click', function() {
                        if (currentTabIndex < tabs.length - 1) {
                            currentTabIndex++;
                            updateStepper();
                        }
                    });
                }

                const prevBtn = document.getElementById('prev-btn');
                if (prevBtn) {
                    prevBtn.addEventListener('click', function() {
                        if (currentTabIndex > 0) {
                            currentTabIndex--;
                            updateStepper();
                        }
                    });
                }

                // Initial run
                updateStepper();
            };

            // Run init
            initPage();
        });
    </script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Create Job</h4>
            <span class="text-muted" style="font-size: 0.85rem;">Post a new job vacancy to your portal.</span>
        </div>
        <div>
            <a href="{{ route('job.index') }}" class="btn btn-outline-secondary rounded-pill btn-sm d-flex align-items-center" style="font-size: 0.8rem; font-weight: 500;">
                <i class="bx bx-arrow-back me-1" style="font-size: 1rem;"></i> Back to List
            </a>
        </div>
    </div>

    <form id="jobForm" action="{{ route('job.store') }}" method="post" class="needs-validation" novalidate>
    @csrf
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
                        <h5 class="mt-3 mb-1 fw-bold" id="preview-title" style="color: #1E293B; font-size: 1.25rem;">New Job Opportunity</h5>
                        <p class="text-muted mb-2" id="preview-branch" style="font-size: 0.9rem; font-weight: 500;">Select Branch</p>
                        <span class="badge" style="background-color: #E0F2F1; color: #127464; font-size: 0.7rem; font-weight: 700; padding: 0.4em 1em; border-radius: 50px;">DRAFT MODE</span>
                    </div>

                    <div class="border-top pt-4">
                        <ul class="list-unstyled mb-0" style="font-size: 0.85rem; color: #64748B;">
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-time text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                Validity: Open Ended
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-group text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                Positions: <span class="ms-1" id="preview-positions">0</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Management Control -->
            <div class="card mb-4 border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);">
                    <h6 class="fw-bold mb-0 text-white small text-uppercase" style="letter-spacing: 1.5px;"><i class="bx bx-cog me-2"></i>Job Controls</h6>
                </div>
                <div class="card-body p-4 bg-white">
                    <div class="mb-4">
                        <label class="form-label-hitech">Job Status</label>
                        <select name="status" class="form-select-hitech w-100" required>
                            @foreach($status as $key => $val)
                                <option value="{{ $key }}">{{ $val }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label-hitech">Priority Level</label>
                        <div class="p-3 rounded-3 border" style="background: rgba(18, 116, 100, 0.03); border-color: rgba(18, 116, 100, 0.1) !important;">
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
        </div>

        <!-- Main Content (Tabs) -->
        <div class="col-xl-9 col-lg-8 col-md-7 col-12">
            <!-- Tabs Navigation (Stepper Indicator) -->
            <div class="rosemary-nav-tabs-wrapper mb-4">
                <ul class="nav nav-pills border-0 rosemary-nav-tabs" id="jobTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#general"><i class="bx bx-info-circle me-1"></i> General Information</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#application"><i class="bx bx-file-find me-1"></i> Application Requirements</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#content-info"><i class="bx bx-edit-alt me-1"></i> Job Content</a>
                    </li>
                    <li class="nav-item" id="legal-tab-item" style="display: none;">
                        <a class="nav-link" data-bs-toggle="tab" href="#legal-info"><i class="bx bx-shield me-1"></i> Legal</a>
                    </li>
                </ul>
            </div>

            <div class="tab-content m-0 p-0 border-0 shadow-none">
                <!-- General Info Tab -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card mb-4 hitech-card-white border-0">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-4">
                                <i class="bx bx-list-ul me-2 fs-5" style="color: #127464;"></i>
                                <h6 class="mb-0 fw-bold" style="color: #1E293B;">Primary Details</h6>
                            </div>
                            
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="emp-field-box">
                                        <label class="form-label-hitech">Job Title <span class="text-danger">*</span></label>
                                        <input type="text" name="title" class="form-control-hitech w-100" required placeholder="e.g. Senior Software Engineer">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <label class="form-label-hitech">Branch / Site <span class="text-danger">*</span></label>
                                        <select name="branch" class="form-select-hitech w-100" required>
                                            <option value="">Select Branch</option>
                                            @foreach($branches as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <label class="form-label-hitech">Job Category <span class="text-danger">*</span></label>
                                        <select name="category" class="form-select-hitech w-100" required>
                                            @foreach($categories as $id => $title)
                                                <option value="{{ $id }}">{{ $title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <label class="form-label-hitech">Number of Positions <span class="text-danger">*</span></label>
                                        <input type="number" name="position" class="form-control-hitech w-100" required placeholder="1" oninput="$('#preview-positions').text(this.value || 0)">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <label class="form-label-hitech">Skills <span class="text-danger">*</span></label>
                                        <input id="skill" name="skill" class="form-control-hitech w-100" required placeholder="Enter skills">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <label class="form-label-hitech">Start Date</label>
                                        <input type="text" name="start_date" class="form-control-hitech w-100 flatpickr-date" placeholder="Select Date" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <label class="form-label-hitech">End Date</label>
                                        <input type="text" name="end_date" class="form-control-hitech w-100 flatpickr-date" placeholder="Select Date" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application Requirements Tab -->
                <div class="tab-pane fade" id="application">
                    <div class="card mb-4 hitech-card-white border-0">
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="p-4 rounded-4" style="background: #f8fafc; border: 1px solid #eef2f6;">
                                        <h6 class="fw-bold mb-3 d-flex align-items-center"><i class="bx bx-question-mark me-2 text-primary"></i> Data Collection</h6>
                                        <div class="d-flex flex-column gap-2">
                                            @foreach(['gender' => 'Gender', 'dob' => 'Date of Birth', 'address' => 'Full Address'] as $val => $label)
                                                <div class="position-relative">
                                                    <input class="form-check-input custom-option-input d-none" type="checkbox" name="applicant[]" value="{{ $val }}" id="check-{{ $val }}">
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
                                                    <input class="form-check-input custom-option-input d-none" type="checkbox" name="visibility[]" value="{{ $val }}" id="check-{{ $val }}">
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
                                
                                <div class="col-12 mt-4">
                                    <div class="p-4 rounded-4 border border-dashed">
                                        <h6 class="fw-bold mb-3 text-muted text-uppercase small">Additional Screening Questions</h6>
                                        <div class="row g-3">
                                            @foreach ($customQuestion as $question)
                                                <div class="col-md-6">
                                                    <div class="position-relative">
                                                        <input class="form-check-input custom-option-input d-none" type="checkbox" name="custom_question[]" value="{{ $question->id }}" id="custom_question_{{ $question->id }}" @if($question->is_required == 'yes') required @endif>
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
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content Tab -->
                <div class="tab-pane fade" id="content-info">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card hitech-card-white">
                                <div class="card-header py-3 px-4 border-bottom">
                                    <h6 class="mb-0 fw-bold">Job Description <span class="text-danger">*</span></h6>
                                </div>
                                <div class="card-body p-4">
                                    <div id="description-editor" style="height: 400px;"></div>
                                    <input type="hidden" name="description" id="description" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card hitech-card-white">
                                <div class="card-header py-3 px-4 border-bottom">
                                    <h6 class="mb-0 fw-bold">Requirements & Qualifications <span class="text-danger">*</span></h6>
                                </div>
                                <div class="card-body p-4">
                                    <div id="requirement-editor" style="height: 400px;"></div>
                                    <input type="hidden" name="requirement" id="requirement" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legal Tab -->
                <div class="tab-pane fade" id="legal-info">
                    <div class="card hitech-card-white">
                        <div class="card-header py-3 px-4 border-bottom">
                            <h6 class="mb-0 fw-bold">Terms & Conditions <span class="text-danger">*</span></h6>
                        </div>
                        <div class="card-body p-4">
                            <div id="terms-editor" style="height: 350px;"></div>
                            <input type="hidden" name="terms_and_conditions" id="terms_and_conditions">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons (Stepper Navigation) -->
            <div class="d-flex justify-content-between gap-3 mt-4 mb-5">
                <button type="button" id="prev-btn" class="btn btn-hitech-secondary d-none">
                    <i class="bx bx-chevron-left me-1"></i> Previous
                </button>
                <div class="d-flex gap-3 ms-auto">
                    <a href="{{ route('job.index') }}" class="btn btn-label-secondary px-5 rounded-pill">Cancel</a>
                    <button type="button" id="next-btn" class="btn btn-hitech-primary">
                        Next Step <i class="bx bx-chevron-right ms-1"></i>
                    </button>
                    <button type="submit" id="submit-btn" class="btn btn-hitech-primary d-none shadow-sm">
                        <i class="bx bx-check-double me-1"></i> Launch Job Post
                    </button>
                </div>
            </div>
        </div>
    </div>
    </form>
</div>
@endsection
