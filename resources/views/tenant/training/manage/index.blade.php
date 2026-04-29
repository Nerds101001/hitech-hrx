@extends('layouts.layoutMaster')

@section('title', 'Training Content Management')

@section('page-style')
<style>
    :root {
        --hrx-primary: #006064;
        --hrx-secondary: #00acc1;
        --hrx-gradient: linear-gradient(135deg, #006064 0%, #00acc1 100%);
    }

    .training-manage-header { 
        background: var(--hrx-gradient); 
        border-radius: 20px; 
        padding: 40px; 
        color: white; 
        margin-bottom: 30px; 
        box-shadow: 0 10px 30px rgba(0, 96, 100, 0.2); 
    }

    .phase-section {
        background: #f8f9fa;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 40px;
        border: 1px solid #e0e0e0;
    }

    .module-card { 
        border: 1px solid #00acc1; 
        border-radius: 15px; 
        background: white; 
        transition: all 0.4s ease; 
        box-shadow: 0 5px 15px rgba(0,0,0,0.05); 
        overflow: hidden; 
        position: relative; 
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .module-card:hover { 
        transform: translateY(-8px); 
        box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
    }

    .card-icon-wrapper { 
        width: 100%; 
        height: 120px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        background: #fcfcfc; 
        font-size: 3rem; 
        border-bottom: 1px solid #f0f0f0;
    }

    .type-tag { 
        position: absolute; 
        top: 15px; 
        right: 15px; 
        padding: 5px 12px; 
        border-radius: 20px; 
        font-size: 0.65rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        z-index: 10; 
    }

    .btn-teal { background: var(--hrx-primary); color: white; border: none; }
    .btn-teal:hover { background: #004d40; color: white; }
    .btn-outline-teal { border: 2px solid var(--hrx-primary); color: var(--hrx-primary); background: transparent; }
    .btn-outline-teal:hover { background: var(--hrx-primary); color: white; }

    .stat-pill {
        background: #f0f4f4;
        padding: 4px 10px;
        border-radius: 10px;
        font-size: 0.75rem;
        color: #555;
        font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="training-manage-header d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeIn">
        <div>
            <h2 class="fw-bold mb-1 text-white">Training Content Studio</h2>
            <p class="text-white opacity-75 mb-0">Design interactive policies, product catalogs, and video assessments.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-white rounded-pill px-4 shadow-lg fw-bold" data-bs-toggle="modal" data-bs-target="#phaseModal" style="color: var(--hrx-primary) !important; background: white !important;">
                <i class="ti ti-plus me-1"></i> New Phase
            </button>
        </div>
    </div>

    @foreach($phases as $phase)
    <div class="phase-section animate__animated animate__fadeInUp" style="animation-delay: {{ $loop->index * 0.1 }}s">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="badge bg-label-primary rounded-pill mb-1">Phase {{ $phase->order }}</span>
                <h3 class="fw-bold mb-0" style="color: var(--hrx-primary);">{{ $phase->title }}</h3>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-teal rounded-pill px-3" onclick="addModule({{ $phase->id }})">
                    <i class="ti ti-plus me-1"></i> Add Module
                </button>
                <button class="btn btn-outline-secondary btn-icon rounded-pill" onclick="editPhase({{ $phase }})">
                    <i class="ti ti-edit"></i>
                </button>
            </div>
        </div>

        <div class="row g-4">
            @forelse($phase->modules as $module)
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="module-card">
                    <div class="type-tag @if($module->content_type == 'video') bg-label-warning @elseif($module->content_type == 'policy') bg-label-info @else bg-label-success @endif">
                        {{ $module->content_type }}
                    </div>
                    
                    <div class="card-icon-wrapper">
                        @if($module->content_type == 'video')
                            <i class="ti ti-player-play-filled text-warning"></i>
                        @elseif($module->content_type == 'policy')
                            <i class="ti ti-file-text text-info"></i>
                        @else
                            <i class="ti ti-book text-success"></i>
                        @endif
                    </div>

                    <div class="card-body p-4 flex-grow-1 d-flex flex-column">
                        <h5 class="fw-bold mb-2">{{ $module->title }}</h5>
                        <p class="text-muted small mb-4 flex-grow-1">{{ \Illuminate\Support\Str::limit($module->description, 80) }}</p>
                        
                        <div class="d-flex gap-2 mb-4">
                            <span class="stat-pill"><i class="ti ti-clock me-1"></i>{{ $module->estimated_time_minutes }}m</span>
                            <span class="stat-pill"><i class="ti ti-help me-1"></i>{{ $module->questions->count() }} Qs</span>
                        </div>

                        <div class="d-flex gap-2 border-top pt-3">
                            <button class="btn btn-outline-teal btn-sm flex-grow-1 rounded-pill" onclick="editModule({{ $module }})">
                                <i class="ti ti-edit me-1"></i> Edit
                            </button>
                            <button class="btn btn-teal btn-sm flex-grow-1 rounded-pill" onclick="manageQuestions({{ $module->id }}, {{ $module->questions }})">
                                <i class="ti ti-help me-1"></i> Questions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="text-center py-5 bg-white rounded-3 border border-dashed">
                    <i class="ti ti-stack-2 fs-1 text-muted opacity-50 mb-3"></i>
                    <p class="text-muted">No modules added to this phase yet.</p>
                </div>
            </div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>

{{-- Phase Modal (No changes to logic, just minor styling) --}}
<div class="modal fade" id="phaseModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('training.manage.phase.store') }}" method="POST" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="id" id="phase_id">
            <div class="modal-header bg-teal">
                <h5 class="modal-title text-white" id="phaseModalLabel">Manage Phase</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Phase Title</label>
                    <input type="text" name="title" id="phase_title" class="form-control" placeholder="e.g. Company Culture" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Strategic Description</label>
                    <textarea name="description" id="phase_description" class="form-control" rows="3" placeholder="What should they learn here?"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Sequence Order</label>
                    <input type="number" name="order" id="phase_order" class="form-control" value="1" required>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-label-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-teal rounded-pill px-4">Secure Phase</button>
            </div>
        </form>
    </div>
</div>

{{-- Module Modal (Improved as per request) --}}
<div class="modal fade" id="moduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('training.manage.module.store') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            @csrf
            <input type="hidden" name="id" id="module_id">
            <input type="hidden" name="phase_id" id="module_phase_id">
            <div class="modal-header bg-teal">
                <h5 class="modal-title text-white" id="moduleModalLabel">Design Training Module</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label fw-bold">Module Name / Title</label>
                        <input type="text" name="title" id="module_title" class="form-control" placeholder="e.g. Code of Conduct 2024" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Module Category</label>
                        <select name="content_type" id="module_type" class="form-select shadow-sm" onchange="toggleContentFields()">
                            <option value="policy">📜 Interactive Policy</option>
                            <option value="catalog">🛍️ Product Catalog (PDF)</option>
                            <option value="video">🎥 Training Video</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Brief Overview (Shown on Card)</label>
                    <textarea name="description" id="module_description" class="form-control" rows="2" placeholder="Summary of the module content..."></textarea>
                </div>
                
                <div id="text_content_area" class="mb-3">
                    <label class="form-label fw-bold d-flex justify-content-between">
                        Content Body 
                        <small class="text-muted">Pro tip: Double enter creates a new page/step</small>
                    </label>
                    <textarea name="content_body" id="module_body" class="form-control" rows="12" placeholder="Paste your policy text or catalog details here..."></textarea>
                </div>

                <div id="pdf_content_area" class="mb-3" style="display: none;">
                    <label class="form-label fw-bold">Upload Product Catalog (PDF)</label>
                    <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                    <div id="pdf_current_file" class="mt-2" style="display: none;">
                        <a href="#" target="_blank" class="badge bg-label-info text-decoration-none">View Current PDF Catalog</a>
                    </div>
                    <small class="text-muted mt-1 d-block">This will be shown with a book-turning animation.</small>
                </div>

                <div id="url_content_area" class="mb-3" style="display: none;">
                    <label class="form-label fw-bold">Direct Video Link (MP4 or YouTube)</label>
                    <input type="text" name="content_url" id="module_url" class="form-control" placeholder="https://youtube.com/watch?v=...">
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Reading Time (Mins)</label>
                        <input type="number" name="estimated_time_minutes" id="module_time" class="form-control" value="10" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Passing Score (%)</label>
                        <input type="number" name="passing_percentage" id="module_passing" class="form-control" value="80" min="0" max="100" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Questions Per Test</label>
                        <input type="number" name="questions_per_test" id="module_q_count" class="form-control" value="5" min="1" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Card Position</label>
                        <input type="number" name="order" id="module_order" class="form-control" value="1" required>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end pb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="show_all_at_once" id="module_show_all">
                            <label class="form-check-label fw-bold" for="module_show_all">Show all questions at once</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-label-secondary rounded-pill" data-bs-dismiss="modal">Discard</button>
                <button type="submit" class="btn btn-teal rounded-pill px-4">Publish Module</button>
            </div>
        </form>
    </div>
</div>

{{-- Questions Modal (With AI Generation Option) --}}
<div class="modal fade" id="questionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-teal">
                <h5 class="modal-title text-white">Assessment Engine</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">Active Questions</h6>
                    <button class="btn btn-sm btn-outline-teal rounded-pill" onclick="generateAIQuestions()">
                        <i class="ti ti-cpu me-1"></i> AI Auto-Generate
                    </button>
                </div>
                <div id="questions_list" class="mb-4 d-flex flex-column gap-2"></div>
                
                <hr class="my-4">
                
                <h6 class="fw-bold mb-3" id="q_form_title">Manually Add Question</h6>
                <form id="questionForm" action="{{ route('training.manage.question.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" id="q_id">
                    <input type="hidden" name="module_id" id="q_module_id">
                    <div class="row mb-3">
                        <div class="col-md-9">
                            <label class="form-label small fw-bold">Question Text</label>
                            <input type="text" name="question" id="q_text" class="form-control shadow-sm" required placeholder="What is the main goal of this policy?">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Marks</label>
                            <input type="number" name="marks" id="q_marks" class="form-control shadow-sm" value="1" min="1" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        @foreach(['A', 'B', 'C', 'D'] as $index => $label)
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <div class="input-group-text bg-white border-end-0">
                                    <input type="radio" name="correct_option_index" id="q_opt_radio_{{ $index }}" value="{{ $index }}" @if($index==0) checked @endif>
                                </div>
                                <input type="text" name="options[]" id="q_opt_{{ $index }}" class="form-control border-start-0" placeholder="Option {{ $label }}" required>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="text-end mt-3 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-label-secondary rounded-pill" id="q_cancel_edit" style="display: none;" onclick="resetQuestionForm()">Cancel Edit</button>
                        <button type="submit" class="btn btn-teal rounded-pill px-4" id="q_submit_btn">Add to Test</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script>
    function editPhase(phase) {
        document.getElementById('phase_id').value = phase.id;
        document.getElementById('phase_title').value = phase.title;
        document.getElementById('phase_description').value = phase.description;
        document.getElementById('phase_order').value = phase.order;
        new bootstrap.Modal(document.getElementById('phaseModal')).show();
    }

    function addModule(phaseId) {
        document.getElementById('module_id').value = '';
        document.getElementById('module_phase_id').value = phaseId;
        document.getElementById('module_title').value = '';
        document.getElementById('module_description').value = '';
        document.getElementById('module_body').value = '';
        document.getElementById('module_url').value = '';
        document.getElementById('module_passing').value = 80;
        document.getElementById('module_q_count').value = 5;
        document.getElementById('module_show_all').checked = false;
        document.getElementById('pdf_current_file').style.display = 'none';
        new bootstrap.Modal(document.getElementById('moduleModal')).show();
    }

    function editModule(module) {
        document.getElementById('module_id').value = module.id;
        document.getElementById('module_phase_id').value = module.phase_id;
        document.getElementById('module_title').value = module.title;
        document.getElementById('module_description').value = module.description;
        document.getElementById('module_type').value = module.content_type;
        document.getElementById('module_body').value = module.content_body || '';
        document.getElementById('module_url').value = module.content_url || '';
        document.getElementById('module_time').value = module.estimated_time_minutes;
        document.getElementById('module_order').value = module.order;
        document.getElementById('module_passing').value = module.passing_percentage || 80;
        document.getElementById('module_q_count').value = module.questions_per_test || 5;
        document.getElementById('module_show_all').checked = module.show_all_at_once == 1;
        
        const currentPdf = document.getElementById('pdf_current_file');
        if (module.content_type === 'catalog' && module.content_url) {
            currentPdf.style.display = 'block';
            currentPdf.querySelector('a').href = module.content_url;
            currentPdf.querySelector('a').textContent = 'View Current PDF Catalog';
        } else {
            currentPdf.style.display = 'none';
        }

        toggleContentFields();
        new bootstrap.Modal(document.getElementById('moduleModal')).show();
    }

    function toggleContentFields() {
        const type = document.getElementById('module_type').value;
        document.getElementById('text_content_area').style.display = type === 'policy' ? 'block' : 'none';
        document.getElementById('pdf_content_area').style.display = type === 'catalog' ? 'block' : 'none';
        document.getElementById('url_content_area').style.display = type === 'video' ? 'block' : 'none';
    }

    function manageQuestions(moduleId, questions) {
        document.getElementById('q_module_id').value = moduleId;
        resetQuestionForm();
        const list = document.getElementById('questions_list');
        list.innerHTML = '';
        
        if (questions.length === 0) {
            list.innerHTML = '<p class="text-center text-muted py-3">No assessment questions for this module yet.</p>';
        }

        questions.forEach(q => {
            const div = document.createElement('div');
            div.className = 'p-3 border rounded shadow-sm bg-white mb-2';
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="fw-bold mb-1">${q.question} <span class="badge bg-label-secondary ms-1">${q.marks} pts</span></div>
                        <div class="text-success small"><i class="ti ti-check me-1"></i>Correct: ${q.options[q.correct_option_index]}</div>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-icon btn-label-primary" onclick='editQuestion(${JSON.stringify(q)})'>
                            <i class="ti ti-edit"></i>
                        </button>
                        <form action="{{ url('training/manage/question') }}/${q.id}" method="POST" onsubmit="return confirm('Delete this question?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-icon btn-label-danger"><i class="ti ti-trash"></i></button>
                        </form>
                    </div>
                </div>
            `;
            list.appendChild(div);
        });
        
        new bootstrap.Modal(document.getElementById('questionsModal')).show();
    }

    function editQuestion(q) {
        document.getElementById('q_id').value = q.id;
        document.getElementById('q_text').value = q.question;
        document.getElementById('q_marks').value = q.marks || 1;
        document.getElementById('q_form_title').textContent = "Edit Question";
        document.getElementById('q_submit_btn').textContent = "Update Question";
        document.getElementById('q_cancel_edit').style.display = 'block';

        q.options.forEach((opt, idx) => {
            document.getElementById(`q_opt_${idx}`).value = opt;
            if (q.correct_option_index == idx) {
                document.getElementById(`q_opt_radio_${idx}`).checked = true;
            }
        });
        
        document.getElementById('q_text').focus();
    }

    function resetQuestionForm() {
        document.getElementById('q_id').value = '';
        document.getElementById('q_text').value = '';
        document.getElementById('q_marks').value = 1;
        document.getElementById('q_form_title').textContent = "Manually Add Question";
        document.getElementById('q_submit_btn').textContent = "Add to Test";
        document.getElementById('q_cancel_edit').style.display = 'none';
        
        for (let i = 0; i < 4; i++) {
            document.getElementById(`q_opt_${i}`).value = '';
        }
        document.getElementById('q_opt_radio_0').checked = true;
    }

    async function generateAIQuestions() {
        const moduleId = document.getElementById('q_module_id').value;
        Swal.fire({
            title: 'Generating Questions...',
            text: 'Nerds AI is analyzing the module content to create relevant questions.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const response = await fetch(`{{ url('training/manage/module') }}/${moduleId}/generate-ai-questions`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const data = await response.json();
            
            if (data.success) {
                Swal.fire('Success!', `Generated ${data.count} questions automatically.`, 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', data.message || 'Failed to generate questions.', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An unexpected error occurred.', 'error');
        }
    }
</script>
@endsection
