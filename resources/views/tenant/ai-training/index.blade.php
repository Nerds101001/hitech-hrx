@extends('layouts/layoutMaster')

@section('title', 'AI Training Center')

@section('page-style')
<style>
    :root {
        --sentinel-teal: #0D5C63;
        --sentinel-light: #f4f7f7;
        --hs-gradient: linear-gradient(135deg, #0D5C63 0%, #17898d 100%);
    }

    .training-header { background: var(--hs-gradient); border-radius: 20px; padding: 50px; color: white; margin-bottom: 40px; box-shadow: 0 15px 35px rgba(13, 92, 99, 0.25); position: relative; overflow: hidden; }
    .training-header::after { content: 'BRN'; position: absolute; right: -20px; top: -20px; font-size: 200px; font-weight: 900; opacity: 0.1; color: white; }
    
    .knowledge-card { border: none; border-radius: 20px; background: white; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f0f4f4; }
    .knowledge-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); border-color: var(--sentinel-teal); }
    
    .category-badge { padding: 6px 14px; border-radius: 30px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: var(--sentinel-light); color: var(--sentinel-teal); }
    
    .training-status { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 600; color: #64748b; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 10px rgba(34, 197, 94, 0.5); }

    .btn-sentinel { background: var(--sentinel-teal); color: white; border-radius: 30px; padding: 12px 25px; font-weight: 700; border: none; transition: 0.3s; }
    .btn-sentinel:hover { background: #0a484e; color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(13, 92, 99, 0.3); }

    .empty-state { padding: 80px 20px; text-align: center; }
    .empty-icon { font-size: 4rem; color: #cbd5e1; margin-bottom: 20px; }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    
    <div class="training-header d-flex justify-content-between align-items-center">
        <div class="z-1">
            <div class="d-flex align-items-center gap-3 mb-3">
                <i class="ti ti-brain fs-1"></i>
                <h1 class="text-white mb-0 fw-extrabold">Hitech Sentinel Brain</h1>
            </div>
            <p class="text-white opacity-75 mb-0 max-w-lg fs-5">Train your AI with specific Hitech policies, industrial guidelines, and consultative logic.</p>
        </div>
        <div class="z-1">
            <button class="btn btn-white text-primary rounded-pill px-5 py-3 fw-bold shadow-lg" data-bs-toggle="modal" data-bs-target="#addKnowledgeModal">
                <i class="ti ti-plus me-2"></i> Add Knowledge Snippet
            </button>
        </div>
    </div>

    <!-- Core Brain Instructions (Tuning) -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 24px; background: #fff; border: 1px solid #e2e8f0 !important;">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-label-warning text-uppercase px-3">Brain Tuning</span>
                                <h3 class="fw-extrabold mb-0">Core AI Instructions</h3>
                            </div>
                            <p class="text-muted mb-0">Define the global rules, persona, and response methodology your AI must follow.</p>
                        </div>
                        <button type="button" class="btn btn-hitech-primary px-5" onclick="document.getElementById('instructions-form').submit()">
                            <i class="ti ti-device-floppy me-2"></i> Save Brain Core
                        </button>
                    </div>

                    <form id="instructions-form" action="{{ route('ai-training.update-instructions') }}" method="POST">
                        @csrf
                        <div class="hitech-input-group-glass p-1 rounded-4" style="background: rgba(13, 92, 99, 0.03);">
                            <label class="form-label fw-bold small text-teal mb-3 d-flex align-items-center px-4 pt-3">
                                <i class="ti ti-markdown fs-4 me-2"></i> SYSTEM PROMPT (BRAIN RULES)
                            </label>
                            <textarea name="content" class="form-control bg-transparent border-0 font-monospace px-4 pb-4" rows="8" 
                                placeholder="Define rules in markdown..." 
                                style="resize: vertical; font-size: 0.95rem; line-height: 1.7; box-shadow: none;">{{ $systemPrompt->content ?? '' }}</textarea>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats & Filters -->
    <div class="row mb-5">
        <div class="col-md-3">
            <div class="card knowledge-card p-4">
                <div class="training-status">
                    <div class="status-dot"></div>
                    <span>AI Brain Sync: Active</span>
                </div>
                <h2 class="mt-2 mb-0 fw-bold">{{ $knowledges->total() }}</h2>
                <small class="text-muted">Stored Truths</small>
            </div>
        </div>
        <div class="col-md-9 d-flex align-items-center justify-content-end gap-3">
            <div class="nav glass-nav p-1 bg-white rounded-pill shadow-sm d-inline-flex border">
                <a href="#" class="nav-link px-4 py-2 rounded-pill active bg-primary text-white shadow-sm">All Knowledge</a>
                <a href="#" class="nav-link px-4 py-2 rounded-pill text-muted">HR Policies</a>
                <a href="#" class="nav-link px-4 py-2 rounded-pill text-muted">Tech Specs</a>
            </div>
        </div>
    </div>

    <!-- Knowledge Grid -->
    <div class="row g-4 mb-5">
        @forelse($knowledges as $item)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 knowledge-card border-0">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="category-badge">{{ $item->category }}</span>
                            <div class="dropdown">
                                <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                    <form action="{{ route('ai-training.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this knowledge snippet?')">
                                        @csrf @method('DELETE')
                                        <button class="dropdown-item text-danger"><i class="ti ti-trash me-2"></i> Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-3 text-heading">{{ $item->title }}</h5>
                        <div class="text-muted small mb-4" style="display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.6;">
                            {!! nl2br(e($item->content)) !!}
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-top-style: dashed !important;">
                            <small class="text-muted"><i class="ti ti-history me-1"></i> {{ $item->created_at->diffForHumans() }}</small>
                            <span class="text-primary fw-bold small"><i class="ti ti-check me-1"></i> Synced</span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card knowledge-card empty-state">
                    <div class="empty-icon"><i class="ti ti-brain-off text-muted opacity-50"></i></div>
                    <h3>Your AI Brain is Empty</h3>
                    <p class="text-muted">Start training the Hitech Sentinel by adding your first policy or FAQ snippet.</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- BRAIN SIMULATOR -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 30px; background: #fff;">
                <div class="row g-0">
                    <div class="col-md-5 p-5 border-end" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                        <div class="mb-5">
                            <span class="badge bg-label-primary mb-3 px-3">Live Simulation</span>
                            <h3 class="fw-extrabold mb-3" style="color: #0D5C63;">Brain Simulator</h3>
                            <p class="text-muted fs-6">Interact with the Sentinel's core logic in real-time. This preview reflects both your markdown rules and knowledge snippets.</p>
                        </div>
                        
                        <div class="hitech-note-card p-4 rounded-4" style="background: #fff; border: 1px dashed var(--sentinel-teal);">
                            <div class="d-flex">
                                <i class="ti ti-info-circle fs-3 me-3 text-primary"></i>
                                <div>
                                    <h6 class="fw-bold mb-1">Interactive Sandbox</h6>
                                    <p class="mb-0 small text-muted">Test boundary cases. If the AI hallucinates, add a clarifying rule to the 'Brain Core' section above.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7 p-0 d-flex flex-column" style="height: 600px; background: #fff;">
                        <div id="sim-chat-body" class="flex-grow-1 p-5 overflow-auto d-flex flex-column gap-5" style="background: rgba(13, 92, 99, 0.01);">
                            <div class="d-flex justify-content-start align-items-end gap-3">
                                <div class="sim-avatar animate__animated animate__zoomIn">
                                    <i class="bx bx-bot"></i>
                                </div>
                                <div style="max-width: 85%;">
                                    <div class="p-4 bg-white border shadow-sm sim-bubble bot">
                                        <p class="mb-2 small fw-bold text-primary text-uppercase tracking-wider">SENTINEL_CORE</p>
                                        <p class="mb-0 fs-6">System online. Training data loaded. Ready for interactive verification.</p>
                                    </div>
                                    <div class="mt-2 text-muted small fw-bold text-uppercase opacity-50" style="font-size: 9px; letter-spacing: 1px; margin-left: 4px;">{{ now()->format('H:i A') }} • SENTINEL</div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-white border-top">
                            <div class="search-wrapper-hitech shadow-sm mx-auto" style="max-width: 90%;">
                                <input type="text" id="sim-input" class="form-control ps-4" placeholder="Ask your question to the Sentinel Brain..." required style="font-weight: 500;">
                                <button type="submit" form="sim-form-actual" class="btn-search shadow-sm rounded-pill" id="customSearchBtn" style="height: 44px; padding: 0 24px; display: flex; align-items: center; gap: 8px; border: none; margin: 0;">
                                    <span class="fw-bold">Send</span>
                                    <i class="bx bx-send fs-5"></i>
                                </button>
                            </div>
                            <form id="sim-form-actual" onsubmit="event.preventDefault(); window.submitSimChat();" class="d-none"></form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-5 d-flex justify-content-center">
        {{ $knowledges->links() }}
    </div>
</div>

<!-- Add Knowledge Modal -->
<div class="modal fade" id="addKnowledgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-4">
                    <h3 class="mb-2">Inject New Knowledge</h3>
                    <p class="text-muted">Every sentence added here increases the Sentinel's intelligence.</p>
                </div>
                <form action="{{ route('ai-training.store') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-bold">Snippet Title</label>
                        <input type="text" name="title" class="form-control form-control-lg rounded-pill" placeholder="e.g. Employee Bonus Structure 2024" required />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold">Category</label>
                        <select name="category" class="form-select form-select-lg rounded-pill" required>
                            <option value="HR Policy">HR Policy</option>
                            <option value="Technical Specs">Technical Specs</option>
                            <option value="Safety Protocol">Safety Protocol</option>
                            <option value="Company FAQ">Company FAQ</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Detailed Content (The Source of Truth)</label>
                        <textarea name="content" class="form-control" rows="10" placeholder="Break down the rules, steps, or data exactly as you want the AI to remember them..." required style="border-radius: 20px; padding: 20px;"></textarea>
                    </div>
                    <div class="col-12 text-center mt-4">
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fs-5 shadow-lg">
                            <i class="ti ti-bolt me-2"></i> Update AI Brain
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-style')
<style>
    .sim-bubble {
        padding: 1.25rem 1.5rem;
        font-size: 14px;
        line-height: 1.6;
        position: relative;
    }
    .sim-bubble.user {
        background: #0D5C63;
        color: #fff !important;
        border-radius: 1.5rem 1.5rem 0 1.5rem;
        box-shadow: 0 4px 15px rgba(13, 92, 99, 0.2);
    }
    .sim-bubble.bot {
        background: #fff;
        color: #1E293B;
        border-radius: 1.5rem 1.5rem 1.5rem 0;
        border: 1px solid #E2E8F0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.02);
    }
    .sim-avatar {
        width: 36px;
        height: 36px;
        background: #0D5C63;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
        flex-shrink: 0;
    }
    .sim-meta {
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 1px;
        margin-top: 8px;
    }
</style>
@endsection

@section('page-script')
<!-- Load marked.js for markdown rendering -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
    const simInput = document.getElementById('sim-input');
    const simBody = document.getElementById('sim-chat-body');

    // Configure marked options
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false,
        mangle: false
    });

    function appendMsg(text, role) {
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const div = document.createElement('div');
        div.className = `d-flex align-items-end gap-3 justify-content-${role === 'user' ? 'end' : 'start'} animate__animated animate__fadeInUp mb-4`;
        
        // Parse markdown only for bot messages
        const processedText = role === 'bot' ? marked.parse(text) : text;

        if (role === 'user') {
            div.innerHTML = `
                <div class="text-end" style="max-width: 85%;">
                    <div class="sim-bubble user">${processedText}</div>
                    <div class="sim-meta me-1">${time} • YOU</div>
                </div>
            `;
        } else {
            div.innerHTML = `
                <div class="sim-avatar mb-4">
                    <i class="bx bx-bot"></i>
                </div>
                <div style="max-width: 85%;">
                    <div class="sim-bubble bot markdown-content">${processedText}</div>
                    <div class="sim-meta ms-1">${time} • SENTINEL</div>
                </div>
            `;
        }
        
        simBody.appendChild(div);
        simBody.scrollTop = simBody.scrollHeight;
    }

    window.submitSimChat = async function() {
        const val = simInput.value.trim();
        if (!val) return;

        appendMsg(val, 'user');
        simInput.value = '';

        const thinkingId = 'thinking-' + Date.now();
        const thinking = document.createElement('div');
        thinking.id = thinkingId;
        thinking.className = 'd-flex align-items-center gap-2 text-muted small ms-5 animate__animated animate__fadeIn mb-4';
        thinking.innerHTML = `
            <div class="spinner-grow spinner-grow-sm text-primary" role="status"></div>
            <span class="fw-bold text-uppercase" style="font-size: 10px; letter-spacing: 1px;">Sentinel is synthesizing...</span>
        `;
        simBody.appendChild(thinking);
        simBody.scrollTop = simBody.scrollHeight;

        try {
            const res = await fetch('/digital-library/chat', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content 
                },
                body: JSON.stringify({ message: val })
            });
            const data = await res.json();
            const thinkingEl = document.getElementById(thinkingId);
            if (thinkingEl) thinkingEl.remove();
            appendMsg(data.message, 'bot');
        } catch (err) {
            const thinkingEl = document.getElementById(thinkingId);
            if (thinkingEl) thinkingEl.remove();
            appendMsg("Training communication error. Please check your core instructions for valid markdown.", 'bot');
        }
    };

    // Also support 'Enter' key directly on input
    simInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            window.submitSimChat();
        }
    });
</script>

<style>
    /* Styling for markdown content in chat bubbles */
    .markdown-content table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1rem;
        font-size: 0.85rem;
    }
    .markdown-content table th, .markdown-content table td {
        border: 1px solid #e2e8f0;
        padding: 8px 12px;
        text-align: left;
    }
    .markdown-content table th {
        background-color: #f8fafc;
        font-weight: 700;
    }
    .markdown-content p:last-child {
        margin-bottom: 0;
    }
    .markdown-content a {
        color: var(--sentinel-teal);
        text-decoration: underline;
        font-weight: 600;
    }
    .sentinel-download-btn {
        display: inline-flex;
        align-items: center;
        background: var(--sentinel-teal);
        color: white !important;
        padding: 6px 16px;
        border-radius: 8px;
        text-decoration: none !important;
        font-size: 0.8rem;
        font-weight: 700;
        transition: all 0.2s;
        box-shadow: 0 4px 10px rgba(13, 92, 99, 0.2);
    }
    .sentinel-download-btn:hover {
        background: #0a484e;
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(13, 92, 99, 0.3);
    }
</style>
@endsection
