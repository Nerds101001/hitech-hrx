@extends('layouts.layoutMaster')

@section('title', 'Training Content Management')

@section('page-style')
<style>
    :root {
        --tm-primary: #005f6b;
        --tm-secondary: #00acc1;
        --tm-gradient: linear-gradient(135deg, #00474e 0%, #007a87 55%, #00acc1 100%);
        --tm-border: #d0e8ea;
        --tm-ink: #1e293b;
        --tm-muted: #64748b;
    }

    /* ── Header ───────────────────────────────────────────── */
    .tm-header {
        background: var(--tm-gradient);
        border-radius: 20px; padding: 28px 36px;
        color: white; margin-bottom: 28px;
        box-shadow: 0 12px 36px rgba(0,96,100,0.22);
        position: relative; overflow: hidden;
    }
    .tm-header::before {
        content:''; position:absolute; top:-60px; right:-60px;
        width:260px; height:260px;
        background:rgba(255,255,255,0.07); border-radius:50%;
    }
    .tm-header-content { position:relative; z-index:1; }
    .btn-add-phase {
        background:white; border:none;
        color:var(--tm-primary) !important;
        font-weight:700; border-radius:30px; padding:9px 22px;
        box-shadow:0 6px 18px rgba(0,0,0,0.15); white-space:nowrap;
        transition:all 0.18s ease; cursor:pointer;
    }
    .btn-add-phase:hover { background:#f0fdfd !important; transform:translateY(-1px); }

    /* ── Phase Section ─────────────────────────────────────── */
    .phase-section {
        background:white; border-radius:18px;
        border:1px solid var(--tm-border);
        box-shadow:0 6px 22px rgba(0,96,100,0.06);
        margin-bottom:24px; overflow:hidden;
    }
    .phase-section-header {
        padding:16px 22px;
        display:flex; align-items:center; gap:14px;
        border-bottom:1px solid #f0f8f8;
        background:linear-gradient(to right,#f3fbfb,white);
    }
    .phase-num {
        width:44px; height:44px; border-radius:12px; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        font-weight:800; font-size:1.05rem; color:white;
        box-shadow:0 4px 10px rgba(0,0,0,0.15);
    }
    /* phase colors */
    .pc-1{background:linear-gradient(135deg,#005f6b,#0097a7);}
    .pc-2{background:linear-gradient(135deg,#1565c0,#42a5f5);}
    .pc-3{background:linear-gradient(135deg,#6a1b9a,#ab47bc);}
    .pc-4{background:linear-gradient(135deg,#e65100,#ff7043);}
    .pc-5{background:linear-gradient(135deg,#2e7d32,#66bb6a);}

    /* ── Module Cards ─────────────────────────────────────── */
    .modules-grid {
        padding:18px 22px 22px;
        display:grid;
        grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
        gap:16px;
    }
    .module-card {
        border:1px solid var(--tm-border); border-radius:14px;
        background:white; overflow:hidden;
        display:flex; flex-direction:column;
        transition:border-color 0.18s,box-shadow 0.18s,transform 0.18s;
        box-shadow:0 3px 12px rgba(0,96,100,0.05);
    }
    .module-card:hover {
        border-color:var(--tm-secondary);
        box-shadow:0 8px 24px rgba(0,96,100,0.12);
        transform:translateY(-2px);
    }

    /* preview area */
    .mc-preview {
        width:100%; height:90px;
        overflow:hidden; position:relative;
        background:#f4f8f8;
        border-bottom:1px solid #eef4f4;
        flex-shrink:0;
    }
    /* PDF scaler: wrap is 4× the container, then scaled 0.25 from top-left fills it exactly */
    .mc-pdf-scaler {
        position:absolute; top:0; left:0;
        width:400%; height:360px; /* 90px × 4 */
        transform:scale(0.25);
        transform-origin:top left;
        pointer-events:none;
    }
    .mc-pdf-scaler iframe {
        width:100%; height:360px;
        border:none; display:block;
    }
    .mc-preview img {
        width:100%; height:100%; object-fit:cover; display:block;
    }
    .mc-preview-placeholder {
        width:100%; height:100%;
        display:flex; flex-direction:column;
        align-items:center; justify-content:center;
        gap:4px; font-size:0.72rem; color:var(--tm-muted);
    }
    .mc-preview-placeholder i { font-size:1.6rem; opacity:0.45; }
    .mc-preview-overlay {
        position:absolute; inset:0;
        display:flex; align-items:center; justify-content:center;
        background:rgba(0,0,0,0.0);
        transition:background 0.18s;
    }
    .mc-preview:hover .mc-preview-overlay { background:rgba(0,0,0,0.25); }
    .mc-preview-overlay a {
        opacity:0; transition:opacity 0.18s;
        background:white; color:var(--tm-primary);
        border-radius:20px; padding:5px 14px;
        font-size:0.72rem; font-weight:700;
        text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,0.2);
    }
    .mc-preview:hover .mc-preview-overlay a { opacity:1; }

    /* card header */
    .mc-header {
        padding:12px 14px 10px;
        display:flex; align-items:center; gap:8px;
        border-bottom:1px solid #f4f8f8;
    }
    .mc-type-tag {
        display:inline-block; padding:2px 8px; border-radius:20px;
        font-size:0.6rem; font-weight:700; text-transform:uppercase;
        letter-spacing:0.4px; margin-bottom:3px;
    }
    .mc-title { font-size:0.84rem; font-weight:700; color:var(--tm-ink); line-height:1.3; }

    .mc-body { padding:10px 14px 12px; flex:1; display:flex; flex-direction:column; }
    .mc-desc {
        font-size:0.76rem; color:var(--tm-muted); line-height:1.4;
        flex:1; margin-bottom:9px;
        display:-webkit-box; -webkit-line-clamp:2;
        -webkit-box-orient:vertical; overflow:hidden;
    }
    .mc-stats { display:flex; gap:6px; flex-wrap:wrap; }
    .stat-chip {
        background:#f1f6f6; border-radius:20px; padding:3px 9px;
        font-size:0.67rem; color:#475569; font-weight:600;
        display:inline-flex; align-items:center; gap:3px;
    }

    /* footer actions */
    .mc-footer {
        padding:10px 14px 12px; border-top:1px solid #f4f8f8;
        display:flex; align-items:center; gap:7px;
    }
    .btn-mc-edit {
        flex:1; padding:7px 10px; border:1.5px solid var(--tm-border);
        border-radius:999px; background:white; color:var(--tm-primary);
        font-size:0.76rem; font-weight:600;
        display:flex; align-items:center; justify-content:center; gap:4px;
        cursor:pointer; transition:all 0.15s; white-space:nowrap;
    }
    .btn-mc-edit:hover { background:#e8f7f8; border-color:var(--tm-secondary); }
    .btn-mc-questions {
        flex:1.2; padding:7px 10px; border:none; border-radius:999px;
        background:var(--tm-primary); color:white;
        font-size:0.76rem; font-weight:600;
        display:flex; align-items:center; justify-content:center; gap:4px;
        cursor:pointer; transition:all 0.15s; white-space:nowrap;
        box-shadow:0 3px 8px rgba(0,96,100,0.22);
    }
    .btn-mc-questions:hover { background:#004d55; }
    /* delete button uses inline styles to prevent theme override */

    /* add module card */
    .add-module-card {
        border:2px dashed #c8e6ea; border-radius:14px; background:#f9fdfd;
        display:flex; flex-direction:column; align-items:center;
        justify-content:center; gap:10px; min-height:140px;
        cursor:pointer; transition:all 0.18s; color:var(--tm-muted);
    }
    .add-module-card:hover {
        border-color:var(--tm-secondary); background:#eef9fa;
        color:var(--tm-primary); transform:translateY(-2px);
    }
    .add-module-card .add-icon {
        width:48px; height:48px; border-radius:50%;
        background:#005f6b; display:flex; align-items:center;
        justify-content:center; font-size:1.6rem; font-weight:300;
        color:white; line-height:1; transition:background 0.18s;
    }
    .add-module-card:hover .add-icon { background:#00838f; }

    /* ── Modals ─────────────────────────────────────────────── */
    .tm-modal .modal-content { border-radius:16px; overflow:hidden; border:none; }
    .tm-modal .modal-header { background:var(--tm-gradient); padding:18px 24px; border:none; }
    .tm-modal .modal-body { max-height:calc(100vh - 180px); overflow-y:auto; padding:20px; }
    .tm-modal .modal-footer {
        padding:12px 20px 16px; background:#fafbfb;
        border-top:1px solid #f0f0f0;
    }
    .form-panel { background:#f7fbfb; border:1px solid #e0eeee; border-radius:12px; padding:16px; margin-bottom:14px; }
    .questions-list { max-height:40vh; overflow-y:auto; padding-right:4px; }
    .question-item {
        border:1px solid #e0eeee; border-radius:12px; background:white;
        padding:14px 16px; transition:border-color 0.15s;
    }
    .question-item:hover { border-color:#9dd8de; }
    .q-opt-row {
        display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:10px;
    }
    .q-opt-pill {
        display:flex; align-items:center; gap:8px;
        padding:6px 10px; border-radius:8px;
        border:1.5px solid #e2e8f0; background:#f8fafc;
        font-size:0.77rem; color:#475569;
    }
    .q-opt-pill.correct {
        border-color:#bbf7d0; background:#f0fdf4; color:#166534; font-weight:600;
    }
    .q-opt-dot {
        width:22px; height:22px; border-radius:50%; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        font-size:0.65rem; font-weight:700;
        background:#e2e8f0; color:#94a3b8;
    }
    .q-opt-dot.correct { background:#22c55e; color:white; }

    /* option input cards in Add Question form */
    .opt-input-card {
        border:1.5px solid #e2e8f0; border-radius:10px; overflow:hidden;
        display:flex; align-items:stretch; transition:border-color 0.15s;
    }
    .opt-input-card:focus-within { border-color:#00acc1; }
    .opt-input-label {
        width:42px; background:#f1f5f9; border-right:1.5px solid #e2e8f0;
        display:flex; flex-direction:column; align-items:center;
        justify-content:center; gap:2px; cursor:pointer; flex-shrink:0;
    }
    .opt-input-label span { font-size:0.7rem; font-weight:700; color:#64748b; }
    .opt-input-card input[type="text"] {
        flex:1; border:none; padding:9px 12px; font-size:0.82rem;
        outline:none; background:white; color:#1e293b;
    }
    .opt-input-card input[type="radio"] { accent-color:#005f6b; }
    .btn-teal { background:var(--tm-primary); color:white; border:none; }
    .btn-teal:hover { background:#004d55; color:white; }

    @media(max-width:767.98px) {
        .tm-header { padding:22px 18px; }
        .phase-section-header { padding:14px 16px; flex-wrap:wrap; }
        .modules-grid { padding:14px; grid-template-columns:1fr 1fr; }
    }
    @media(max-width:479.98px) { .modules-grid { grid-template-columns:1fr; } }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- ── Top Header ─────────────────────────────────────── --}}
    <div class="tm-header animate__animated animate__fadeIn">
        <div class="tm-header-content d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h2 class="fw-bold text-white mb-1" style="font-size:1.4rem;">Training Content Studio</h2>
                <p class="mb-0" style="color:rgba(255,255,255,0.78);font-size:0.86rem;">
                    Build phases, design modules, and craft assessments for your team.
                </p>
            </div>
            <button class="btn-add-phase" data-bs-toggle="modal" data-bs-target="#phaseModal">
                <i class="ti ti-plus me-1"></i> New Phase
            </button>
        </div>
    </div>

    {{-- ── Phase Sections ──────────────────────────────────── --}}
    @foreach($phases as $phase)
    @php $ci = (($phase->order - 1) % 5) + 1; @endphp
    <div class="phase-section animate__animated animate__fadeInUp" style="animation-delay:{{ $loop->index * 0.07 }}s">

        {{-- Phase Header --}}
        <div class="phase-section-header">
            <div class="phase-num pc-{{ $ci }}">{{ $phase->order }}</div>
            <div class="flex-grow-1" style="min-width:0;">
                <div style="font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--tm-muted);">Phase {{ $phase->order }}</div>
                <h5 class="fw-bold mb-0" style="color:var(--tm-primary);font-size:0.98rem;">{{ $phase->title }}</h5>
                @if($phase->description)<p class="mb-0" style="font-size:0.74rem;color:var(--tm-muted);">{{ $phase->description }}</p>@endif
            </div>
            {{-- Phase Actions --}}
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <button type="button"
                    onclick="editPhase({{ $phase }})"
                    style="border:none;background:#005f6b;color:white;font-size:0.76rem;font-weight:600;border-radius:999px;padding:7px 16px;cursor:pointer;white-space:nowrap;box-shadow:0 3px 8px rgba(0,96,100,0.25);">
                    <i class="ti ti-edit me-1"></i>Edit
                </button>
                <form action="{{ route('training.manage.phase.destroy', $phase->id) }}" method="POST"
                    onsubmit="return confirm('Delete this phase and all its modules?')"
                    style="margin:0;padding:0;display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit"
                        style="border:none;background:#ef4444;color:white;font-size:0.76rem;font-weight:600;border-radius:999px;padding:7px 16px;cursor:pointer;white-space:nowrap;box-shadow:0 3px 8px rgba(239,68,68,0.25);">
                        <i class="ti ti-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>

        {{-- Modules Grid --}}
        <div class="modules-grid">
            @foreach($phase->modules as $module)
            @php
                $previewUrl = $module->content_url ?? null;
                $ytId = null;
                if ($previewUrl && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $previewUrl, $m)) {
                    $ytId = $m[1];
                }
            @endphp
            <div class="module-card">

                {{-- ── Preview strip ───────────────────────────── --}}
                <div class="mc-preview">
                    @if($module->content_type === 'video' && $ytId)
                        <img src="https://img.youtube.com/vi/{{ $ytId }}/mqdefault.jpg" alt="{{ $module->title }}">
                        <div class="mc-preview-overlay">
                            <a href="{{ $previewUrl }}" target="_blank"><i class="ti ti-player-play me-1"></i>Watch</a>
                        </div>
                    @elseif($module->content_type === 'video' && $previewUrl)
                        <video src="{{ $previewUrl }}" muted preload="metadata" style="width:100%;height:100%;object-fit:cover;"></video>
                        <div class="mc-preview-overlay">
                            <a href="{{ $previewUrl }}" target="_blank"><i class="ti ti-player-play me-1"></i>Watch</a>
                        </div>
                    @elseif($module->content_type === 'catalog' && $previewUrl)
                        <div class="mc-pdf-scaler">
                            <iframe src="{{ $previewUrl }}#toolbar=0&navpanes=0&scrollbar=0&view=FitH" scrolling="no"></iframe>
                        </div>
                        <div class="mc-preview-overlay">
                            <a href="{{ $previewUrl }}" target="_blank"><i class="ti ti-eye me-1"></i>Open PDF</a>
                        </div>
                    @elseif($module->content_type === 'policy')
                        <div class="mc-preview-placeholder">
                            <i class="ti ti-file-text" style="color:#1e88e5;opacity:0.5;font-size:1.8rem;"></i>
                            <span>Policy Document</span>
                        </div>
                    @else
                        <div class="mc-preview-placeholder">
                            <i class="ti ti-photo" style="color:#94a3b8;font-size:1.8rem;"></i>
                            <span>No preview</span>
                        </div>
                    @endif
                </div>

                {{-- ── Card header: type tag + title ──── --}}
                <div class="mc-header">
                    <div style="min-width:0;flex:1;">
                        <div class="mc-type-tag
                            @if($module->content_type==='video') bg-label-warning
                            @elseif($module->content_type==='policy') bg-label-info
                            @else bg-label-success @endif">
                            {{ $module->content_type }}
                        </div>
                        <div class="mc-title text-truncate">{{ $module->title }}</div>
                    </div>
                </div>

                {{-- ── Body ──────────────────────────────────── --}}
                <div class="mc-body">
                    <p class="mc-desc">{{ $module->description ?: 'No description provided.' }}</p>
                    <div class="mc-stats">
                        <span class="stat-chip"><i class="ti ti-clock"></i> {{ $module->estimated_time_minutes }}m</span>
                        <span class="stat-chip"><i class="ti ti-help-circle"></i> {{ $module->questions->count() }} Qs</span>
                    </div>
                </div>

                {{-- ── Footer Actions ────────────────────────── --}}
                <div class="mc-footer">
                    <button class="btn-mc-edit" type="button" onclick="editModule({{ $module }})">
                        <i class="ti ti-edit"></i> Edit
                    </button>
                    <button class="btn-mc-questions" type="button" onclick="manageQuestions({{ $module->id }}, {{ $module->questions }})">
                        <i class="ti ti-help-circle"></i> Questions
                    </button>
                    <form action="{{ route('training.manage.module.destroy', $module->id) }}" method="POST"
                        onsubmit="return confirm('Delete this module?')"
                        style="margin:0;padding:0;display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit"
                            style="height:34px;padding:0 12px;border-radius:999px;border:1.5px solid #fca5a5;background:#fff5f5;color:#ef4444;font-size:0.75rem;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:4px;"
                            title="Delete module">
                            <i class="ti ti-trash" style="font-size:0.8rem;"></i> Del
                        </button>
                    </form>
                </div>
            </div>
            @endforeach

            {{-- Add Module tile --}}
            <div class="add-module-card" onclick="addModule({{ $phase->id }})">
                <div class="add-icon">+</div>
                <span style="font-size:0.82rem;font-weight:600;">Add Module</span>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── Phase Modal ──────────────────────────────────────── --}}
<div class="modal fade tm-modal" id="phaseModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('training.manage.phase.store') }}" method="POST" class="modal-content shadow-lg">
            @csrf
            <input type="hidden" name="id" id="phase_id">
            <div class="modal-header">
                <h5 class="modal-title text-white fw-bold">Manage Phase</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Phase Title</label>
                    <input type="text" name="title" id="phase_title" class="form-control" placeholder="e.g. Company Culture & Integrity" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" id="phase_description" class="form-control" rows="3" placeholder="What should employees learn in this phase?"></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Sequence Order</label>
                    <input type="number" name="order" id="phase_order" class="form-control" value="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-teal rounded-pill px-4">Save Phase</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Module Modal ─────────────────────────────────────── --}}
<div class="modal fade tm-modal" id="moduleModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form action="{{ route('training.manage.module.store') }}" method="POST" enctype="multipart/form-data" class="modal-content shadow-lg">
            @csrf
            <input type="hidden" name="id" id="module_id">
            <input type="hidden" name="phase_id" id="module_phase_id">
            <div class="modal-header">
                <h5 class="modal-title text-white fw-bold">Design Training Module</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-panel">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label fw-semibold">Module Title</label>
                            <input type="text" name="title" id="module_title" class="form-control" placeholder="e.g. Code of Conduct 2024" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-semibold">Content Type</label>
                            <select name="content_type" id="module_type" class="form-select" onchange="toggleContentFields()">
                                <option value="policy">Interactive Policy</option>
                                <option value="catalog">Product Catalog (PDF)</option>
                                <option value="video">Training Video</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Brief Description</label>
                        <textarea name="description" id="module_description" class="form-control" rows="2" placeholder="Short summary shown on the module card..."></textarea>
                    </div>
                </div>

                <div id="text_content_area" class="form-panel">
                    <label class="form-label fw-semibold d-flex justify-content-between">
                        Content Body <small class="text-muted">Double enter = new page/step</small>
                    </label>
                    <textarea name="content_body" id="module_body" class="form-control" rows="10" placeholder="Paste your policy text here..."></textarea>
                </div>

                <div id="pdf_content_area" class="form-panel" style="display:none;">
                    <label class="form-label fw-semibold">Upload Product Catalog (PDF)</label>
                    <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                    <div id="pdf_current_file" class="mt-2" style="display:none;">
                        <a href="#" target="_blank" class="badge bg-label-info text-decoration-none">View Current PDF</a>
                    </div>
                    <small class="text-muted mt-1 d-block">Displayed with a page-turning animation.</small>
                </div>

                <div id="url_content_area" class="form-panel" style="display:none;">
                    <label class="form-label fw-semibold">Video URL (MP4 or YouTube)</label>
                    <input type="text" name="content_url" id="module_url" class="form-control" placeholder="https://youtube.com/watch?v=...">
                </div>

                <div id="video_settings_area" class="form-panel" style="display:none;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex justify-content-between">
                            Video Chapters (JSON) <small class="text-muted">[{"title":"Intro","time":0}]</small>
                        </label>
                        <textarea name="video_chapters" id="module_video_chapters" class="form-control font-monospace" rows="3" placeholder='[{"title": "Introduction", "time": 0}]'></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold d-flex justify-content-between">
                            Pop-up Quiz Milestones (JSON) <small class="text-muted">[{"time":60,"question":"...","options":[...],"correct":0}]</small>
                        </label>
                        <textarea name="video_milestones" id="module_video_milestones" class="form-control font-monospace" rows="3"></textarea>
                    </div>
                </div>

                <div class="form-panel mb-0">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Est. Time (mins)</label>
                            <input type="number" name="estimated_time_minutes" id="module_time" class="form-control" value="10" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Passing Score (%)</label>
                            <input type="number" name="passing_percentage" id="module_passing" class="form-control" value="80" min="0" max="100" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Questions Per Test</label>
                            <input type="number" name="questions_per_test" id="module_q_count" class="form-control" value="5" min="1" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Card Order</label>
                            <input type="number" name="order" id="module_order" class="form-control" value="1" required>
                        </div>
                        <div class="col-md-6 mb-0 d-flex align-items-end pb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="show_all_at_once" id="module_show_all">
                                <label class="form-check-label fw-semibold" for="module_show_all">Show all questions at once</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary rounded-pill" data-bs-dismiss="modal">Discard</button>
                <button type="submit" class="btn btn-teal rounded-pill px-4">Publish Module</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Questions Modal ──────────────────────────────────── --}}
<div class="modal fade tm-modal" id="questionsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title text-white fw-bold">Assessment Engine</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Active questions list --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="color:#1e293b;">Active Questions</h6>
                    <button onclick="generateAIQuestions()"
                        style="border:none;background:#005f6b;color:white;font-size:0.76rem;font-weight:600;border-radius:999px;padding:7px 16px;cursor:pointer;box-shadow:0 3px 8px rgba(0,96,100,0.25);">
                        &#9881; AI Auto-Generate
                    </button>
                </div>
                <div id="questions_list" class="questions-list mb-3 d-flex flex-column gap-2"></div>

                <hr class="my-3">

                {{-- Add / Edit Question form --}}
                <div class="form-panel mb-0">
                    <h6 class="fw-bold mb-3" id="q_form_title" style="color:#005f6b;">Add Question</h6>
                    <form id="questionForm" action="{{ route('training.manage.question.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="id" id="q_id">
                        <input type="hidden" name="module_id" id="q_module_id">

                        {{-- Question text + marks --}}
                        <div class="row mb-3 g-2">
                            <div class="col-md-9">
                                <label class="form-label fw-semibold" style="font-size:0.82rem;">Question Text</label>
                                <input type="text" name="question" id="q_text" class="form-control"
                                    required placeholder="e.g. What is the main purpose of this policy?">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold" style="font-size:0.82rem;">Marks</label>
                                <input type="number" name="marks" id="q_marks" class="form-control" value="1" min="1" required>
                            </div>
                        </div>

                        {{-- Options with radio select-correct --}}
                        <label class="form-label fw-semibold mb-2" style="font-size:0.82rem;">
                            Options <small class="text-muted fw-normal">(select the radio next to the correct answer)</small>
                        </label>
                        <div class="row g-2 mb-3">
                            @foreach(['A','B','C','D'] as $index => $label)
                            <div class="col-md-6">
                                <div class="opt-input-card">
                                    <label class="opt-input-label" for="q_opt_{{ $index }}">
                                        <input type="radio" name="correct_option_index" id="q_opt_radio_{{ $index }}"
                                            value="{{ $index }}" @if($index==0) checked @endif>
                                        <span>{{ $label }}</span>
                                    </label>
                                    <input type="text" name="options[]" id="q_opt_{{ $index }}"
                                        placeholder="Type option {{ $label }}..." required>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button"
                                style="border:1.5px solid #94a3b8;background:white;color:#475569;font-size:0.78rem;font-weight:600;border-radius:999px;padding:7px 18px;cursor:pointer;"
                                id="q_cancel_edit" style="display:none;" onclick="resetQuestionForm()">
                                Cancel
                            </button>
                            <button type="submit" id="q_submit_btn"
                                style="border:none;background:#005f6b;color:white;font-size:0.78rem;font-weight:600;border-radius:999px;padding:7px 22px;cursor:pointer;box-shadow:0 3px 8px rgba(0,96,100,0.25);">
                                Add to Test
                            </button>
                        </div>
                    </form>
                </div>
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
        ['module_id','module_title','module_description','module_body','module_url','module_video_chapters','module_video_milestones']
            .forEach(id => document.getElementById(id).value = '');
        document.getElementById('module_phase_id').value = phaseId;
        document.getElementById('module_passing').value = 80;
        document.getElementById('module_q_count').value = 5;
        document.getElementById('module_show_all').checked = false;
        document.getElementById('pdf_current_file').style.display = 'none';
        toggleContentFields();
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
        document.getElementById('module_video_chapters').value = module.video_chapters ? JSON.stringify(module.video_chapters, null, 2) : '';
        document.getElementById('module_video_milestones').value = module.video_milestones ? JSON.stringify(module.video_milestones, null, 2) : '';
        document.getElementById('module_time').value = module.estimated_time_minutes;
        document.getElementById('module_order').value = module.order;
        document.getElementById('module_passing').value = module.passing_percentage || 80;
        document.getElementById('module_q_count').value = module.questions_per_test || 5;
        document.getElementById('module_show_all').checked = module.show_all_at_once == 1;
        const pdf = document.getElementById('pdf_current_file');
        if (module.content_type === 'catalog' && module.content_url) {
            pdf.style.display = 'block';
            pdf.querySelector('a').href = module.content_url;
        } else { pdf.style.display = 'none'; }
        toggleContentFields();
        new bootstrap.Modal(document.getElementById('moduleModal')).show();
    }

    function toggleContentFields() {
        const t = document.getElementById('module_type').value;
        document.getElementById('text_content_area').style.display  = t === 'policy'  ? 'block' : 'none';
        document.getElementById('pdf_content_area').style.display   = t === 'catalog' ? 'block' : 'none';
        document.getElementById('url_content_area').style.display   = t === 'video'   ? 'block' : 'none';
        document.getElementById('video_settings_area').style.display= t === 'video'   ? 'block' : 'none';
    }

    const optLabels = ['A','B','C','D'];

    function manageQuestions(moduleId, questions) {
        document.getElementById('q_module_id').value = moduleId;
        resetQuestionForm();
        const list = document.getElementById('questions_list');
        list.innerHTML = '';

        if (questions.length === 0) {
            list.innerHTML = '<div style="text-align:center;padding:24px 0;color:#94a3b8;font-size:0.84rem;">No questions added yet. Use the form below to add the first one.</div>';
        }

        questions.forEach((q, qi) => {
            // Build options grid
            const optsHtml = q.options.map((opt, i) => {
                const isC = i == q.correct_option_index;
                return `<div class="q-opt-pill ${isC ? 'correct' : ''}">
                    <div class="q-opt-dot ${isC ? 'correct' : ''}">${isC ? '&#10003;' : optLabels[i]}</div>
                    <span>${opt}</span>
                </div>`;
            }).join('');

            const div = document.createElement('div');
            div.className = 'question-item';
            div.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px;">
                    <div style="flex:1;min-width:0;">
                        <span style="font-size:0.7rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.4px;">Q${qi+1}</span>
                        <div style="font-size:0.875rem;font-weight:600;color:#1e293b;line-height:1.4;margin-top:2px;">${q.question}</div>
                        <span style="font-size:0.68rem;font-weight:700;background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:20px;display:inline-block;margin-top:4px;">${q.marks} pt${q.marks>1?'s':''}</span>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;align-items:center;">
                        <button type="button"
                            style="border:none;background:#005f6b;color:white;font-size:0.72rem;font-weight:600;border-radius:999px;padding:5px 14px;cursor:pointer;"
                            onclick='editQuestion(${JSON.stringify(q)})'>Edit</button>
                        <form action="{{ url('training/manage/question') }}/${q.id}" method="POST"
                            onsubmit="return confirm('Delete this question?')" style="margin:0;padding:0;">
                            @csrf @method('DELETE')
                            <button type="submit"
                                style="border:none;background:#ef4444;color:white;font-size:0.72rem;font-weight:600;border-radius:999px;padding:5px 14px;cursor:pointer;">Del</button>
                        </form>
                    </div>
                </div>
                <div class="q-opt-row">${optsHtml}</div>`;
            list.appendChild(div);
        });

        new bootstrap.Modal(document.getElementById('questionsModal')).show();
    }

    function editQuestion(q) {
        document.getElementById('q_id').value = q.id;
        document.getElementById('q_text').value = q.question;
        document.getElementById('q_marks').value = q.marks || 1;
        document.getElementById('q_form_title').textContent = 'Edit Question';
        document.getElementById('q_submit_btn').textContent = 'Update Question';
        document.getElementById('q_cancel_edit').style.cssText = 'border:1.5px solid #94a3b8;background:white;color:#475569;font-size:0.78rem;font-weight:600;border-radius:999px;padding:7px 18px;cursor:pointer;display:inline-block;';
        q.options.forEach((opt, idx) => {
            document.getElementById(`q_opt_${idx}`).value = opt;
            if (q.correct_option_index == idx) document.getElementById(`q_opt_radio_${idx}`).checked = true;
        });
        document.getElementById('q_text').focus();
        document.getElementById('questionForm').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function resetQuestionForm() {
        document.getElementById('q_id').value = '';
        document.getElementById('q_text').value = '';
        document.getElementById('q_marks').value = 1;
        document.getElementById('q_form_title').textContent = 'Add Question';
        document.getElementById('q_submit_btn').textContent = 'Add to Test';
        document.getElementById('q_cancel_edit').style.display = 'none';
        for (let i = 0; i < 4; i++) document.getElementById(`q_opt_${i}`).value = '';
        document.getElementById('q_opt_radio_0').checked = true;
    }

    async function generateAIQuestions() {
        const moduleId = document.getElementById('q_module_id').value;
        Swal.fire({ title:'Generating...', text:'Nerds AI is analysing the module content.', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
        try {
            const res = await fetch(`{{ url('training/manage/module') }}/${moduleId}/generate-ai-questions`, {
                method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'}
            });
            const data = await res.json();
            if (data.success) Swal.fire('Done!', `Generated ${data.count} questions.`, 'success').then(()=>location.reload());
            else Swal.fire('Error', data.message || 'Failed.', 'error');
        } catch(e) { Swal.fire('Error','An unexpected error occurred.','error'); }
    }
</script>
@endsection
