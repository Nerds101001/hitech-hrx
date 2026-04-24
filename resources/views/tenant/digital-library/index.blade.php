@extends('layouts/layoutMaster')

@section('title', 'Digital Library')

@section('page-style')
<style>
    :root {
        --hrx-primary: #006064;
        --hrx-secondary: #00acc1;
        --hrx-accent: #ff9800;
        --hrx-gradient: linear-gradient(135deg, #006064 0%, #00acc1 100%);
    }

    .library-header { background: var(--hrx-gradient); border-radius: 20px; padding: 40px; color: white; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0, 96, 100, 0.2); }
    .library-card { border: 1px solid #00acc1; border-radius: 15px; background: white; transition: all 0.4s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.05); overflow: hidden; position: relative; }
    .library-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
    .card-icon-wrapper { width: 100%; height: 120px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; font-size: 3rem; color: var(--hrx-primary); }
    .category-tag { position: absolute; top: 15px; right: 15px; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; background: rgba(0,0,0,0.05); z-index: 10; }
    
    .glass-nav .nav-link { border-radius: 30px; padding: 10px 20px; margin-right: 10px; font-weight: 500; color: #555; border: none; }
    .glass-nav .nav-link.active { background: var(--hrx-primary) !important; color: white !important; box-shadow: 0 4px 12px rgba(0,96,100,0.3); }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    
    <div class="library-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1 text-white">AI Digital Library</h2>
            @if(auth()->user()->hasRole(['admin', 'hr']))
                <p class="text-white opacity-75 mb-0">Secure documents & intelligent assistant powered by Nerds Bot.</p>
            @else
                <p class="text-white opacity-75 mb-0">Secure technical documents and brand assets vault.</p>
            @endif
        </div>
        @if(auth()->user()->can('library.upload') || auth()->user()->hasRole(['admin', 'hr']))
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-white rounded-pill px-4 border-2 text-white bg-transparent" data-bs-toggle="modal" data-bs-target="#bulkUploadModal" style="border-color: white !important; border-width: 2px !important;">
               <i class="ti ti-files me-1"></i> AI Bulk Upload
            </button>
            <button type="button" class="btn btn-white rounded-pill px-4 shadow-lg fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal" style="color: #008080 !important; background: white !important;">
                <i class="ti ti-plus me-1"></i> Add Document
            </button>
        </div>
        @endif
    </div>

    <!-- Category Filter & Search -->
    <div class="row mb-5 align-items-center">
        <div class="col-md-7">
            <div class="nav glass-nav p-1 bg-white rounded-pill shadow-sm d-inline-flex border">
                <a href="{{ route('library.index') }}" class="nav-link {{ !$category || $category == 'all' ? 'active' : '' }}">All Files</a>
                <a href="{{ route('library.index', ['category' => 'SDS']) }}" class="nav-link {{ $category === 'SDS' ? 'active' : '' }}">SDS</a>
                <a href="{{ route('library.index', ['category' => 'TDS']) }}" class="nav-link {{ $category === 'TDS' ? 'active' : '' }}">TDS</a>
                <a href="{{ route('library.index', ['category' => 'MOM']) }}" class="nav-link {{ $category === 'MOM' ? 'active' : '' }}">MOM</a>
                <a href="{{ route('library.index', ['category' => 'LEARN']) }}" class="nav-link {{ $category === 'LEARN' ? 'active' : '' }}">Learn @ Hitech</a>
                <a href="{{ route('library.index', ['category' => 'Video']) }}" class="nav-link {{ $category === 'Video' ? 'active' : '' }}">Videos</a>
            </div>
        </div>
        <div class="col-md-5">
            <div class="search-wrapper-hitech shadow-sm">
                <i class="bx bx-search text-muted ms-3"></i>
                <input type="text" class="form-control" placeholder="Search Assets..." id="librarySearch">
                <button class="btn-search shadow-sm rounded-pill" id="customSearchBtn">
                  <i class="bx bx-search fs-5"></i><span>Search</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Files Grid -->
    <div class="row g-4">
        @forelse($libraryFiles as $file)
            <div class="col-md-4 col-lg-3">
                <div class="card h-100 library-card">
                    <div class="category-tag @if($file->category == 'Video') bg-label-warning @else bg-label-info @endif">
                        {{ $file->category }}
                    </div>
                    <div class="card-icon-wrapper" style="background: white; border-bottom: 1px solid #f0f0f0; height: 160px;">
                        @if($file->category == 'Video') <i class="ti ti-video text-warning" style="font-size: 4rem;"></i>
                        @elseif($file->mime_type == 'application/pdf') 
                            <img src="{{ asset('assets/img/pdf icon.png') }}" style="width: 80px; height: auto;" alt="PDF">
                        @else <i class="ti ti-file text-secondary" style="font-size: 4rem;"></i> @endif
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-2 text-truncate" title="{{ $file->product_name ?? $file->title }}">
                            {{ $file->product_name ?? $file->title }}
                        </h5>
                        
                        <div class="mb-3">
                            <p class="text-muted small mb-0" style="display: -webkit-box; -webkit-line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; height: 8.4em;">
                                @php
                                    $summary = $file->summary ?? 'No strategic summary available.';
                                    // Strip Markdown bolding
                                    $summary = str_replace('**', '', $summary);
                                    // Strip leading numbers/lists (e.g. "1. ", " - ")
                                    $summary = preg_replace('/^\d+\.\s*|^\s*[-*]\s*/m', '', $summary);
                                    // Strip common AI-generated labels
                                    $summary = str_ireplace(['Application:', 'Hazard Class:', 'Safety Gear:', 'Storage:', 'Features:'], '', $summary);
                                    echo trim($summary);
                                @endphp
                            </p>
                        </div>

                        
                        <div class="pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">{{ number_format($file->size / 1048576, 2) }} MB</small>
                                <span class="badge bg-label-secondary rounded-pill">
                                    @php $parts = explode('.', $file->title); @endphp
                                    {{ count($parts) > 1 ? end($parts) : 'N/A' }}
                                </span>
                            </div>
                            
                            <div class="d-flex gap-2">
                                @if($file->youtube_url)
                                    <a href="{{ $file->youtube_url }}" target="_blank" class="btn btn-danger btn-sm flex-grow-1 rounded-pill">
                                        <i class="ti ti-brand-youtube me-1"></i> Watch Video
                                    </a>
                                @else
                                    <a href="{{ route('library.access', $file->id) }}" target="_blank" class="btn btn-primary btn-sm flex-grow-1 rounded-pill">
                                        <i class="ti ti-download me-1"></i> Download
                                    </a>
                                @endif
                                
                                <div class="dropdown">
                                    <button class="btn btn-primary btn-sm rounded-pill px-3 dropdown-toggle hide-arrow" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-send me-1"></i> Share
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; min-width: 180px;">
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center py-2" href="https://wa.me/?text={{ urlencode('Check out this technical document: ' . ($file->youtube_url ?? route('library.access', $file->id))) }}" target="_blank">
                                                <i class="ti ti-brand-whatsapp text-success me-2 fs-4"></i> Share on WhatsApp
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center py-2" href="mailto:?subject={{ urlencode('Hitech Library Asset: ' . $file->title) }}&body={{ urlencode('You can view the document at: ' . ($file->youtube_url ?? route('library.access', $file->id))) }}">
                                                <i class="ti ti-mail text-danger me-2 fs-4"></i> Share via Email
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider opacity-50"></li>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-center py-2" href="javascript:void(0);" onclick="copyLibraryLink('{{ $file->youtube_url ?? route('library.access', $file->id) }}')">
                                                <i class="ti ti-copy text-primary me-2 fs-4"></i> Copy Direct Link
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <h3>Library is currently empty</h3>
                <p class="text-muted">Start by adding your first industrial document.</p>
            </div>
        @endforelse
    </div>

    <div class="d-flex justify-content-center mt-5">
        @if($libraryFiles instanceof \Illuminate\Pagination\LengthAwarePaginator)
            {{ $libraryFiles->links() }}
        @endif
    </div>
</div>

@include('tenant.digital-library.modals')
@endsection

@section('page-script')
<script>
    let pendingFiles = [];

    // --- REPAIR: Sync all IDs for a single Strategic Flow ---
    document.addEventListener('DOMContentLoaded', function() {
        const dropzone = document.getElementById('dropzone');
        const bulkInput = document.getElementById('bulkFileInput');
        const fileInput = document.getElementById('fileSelector');
        
        // Use ONE list for both modals if needed, but we'll focus on the 'bulkUploadList'
        if (dropzone) {
            dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('bg-light'); });
            dropzone.addEventListener('dragleave', () => { dropzone.classList.remove('bg-light'); });
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('bg-light');
                handleFiles(e.dataTransfer.files);
            });
        }

        [bulkInput, fileInput].forEach(el => {
            if (el) el.addEventListener('change', (e) => handleFiles(e.target.files));
        });

        // --- Client-side Search Implementation ---
        const searchInput = document.getElementById('librarySearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                const cards = document.querySelectorAll('.library-card');
                
                cards.forEach(card => {
                    const title = card.querySelector('h5').innerText.toLowerCase();
                    if (title.includes(term)) {
                        card.closest('.col-md-4').style.display = 'block';
                    } else {
                        card.closest('.col-md-4').style.display = 'none';
                    }
                });
            });
        }
    });

    function handleFiles(files) {
        const container = document.getElementById('bulkUploadList');
        if (!container) return;
        
        Array.from(files).forEach(async (file) => {
            const id = Math.random().toString(36).substr(2, 9);
            const item = document.createElement('div');
            item.className = 'p-3 bg-white/5 rounded-lg border border-white/10 mb-3 transition-all';
            item.id = `file-${id}`;
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ti ti-file-type-pdf text-danger fs-3"></i>
                        <div>
                            <div class="text-sm font-bold text-white">${file.name}</div>
                            <div class="text-xs text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                        </div>
                    </div>
                    <div id="status-${id}" class="badge bg-primary">Phase 1: Auditing...</div>
                </div>
                <div id="summary-${id}" class="text-xs text-info border-start border-2 border-info ps-2 my-2 italic">
                    <div class="spinner-border spinner-border-sm me-1"></div> Examining technical crux...
                </div>
                <div id="action-${id}" class="text-end" style="display:none;">
                    <button id="commit-btn-${id}" onclick="confirmIngestion('${id}')" class="btn btn-sm btn-success py-1 shadow-sm">Secure to Sentinel</button>
                    <button id="replace-btn-${id}" onclick="confirmIngestion('${id}', true)" class="btn btn-sm btn-warning py-1 shadow-sm" style="display:none;">Secure & Replace</button>
                </div>
            `;
            container.appendChild(item);
            
            // --- Phase 1: Strategic Audit (Auto-Triggered) ---
            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            try {
                const response = await fetch('{{ route("library.analyze") }}', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                if (!response.ok) throw new Error(data.error || 'Audit Failed');

                const statusEl = document.getElementById(`status-${id}`);
                const summaryEl = document.getElementById(`summary-${id}`);
                const actionEl = document.getElementById(`action-${id}`);
                const commitBtn = document.getElementById(`commit-btn-${id}`);
                const replaceBtn = document.getElementById(`replace-btn-${id}`);

                statusEl.textContent = data.category;
                statusEl.className = data.is_duplicate ? 'badge bg-warning text-dark' : 'badge bg-info';
                
                if (data.is_duplicate) {
                    statusEl.innerHTML = `<i class="ti ti-alert-triangle me-1"></i> DUPLICATE: ${data.category}`;
                    commitBtn.style.display = 'none';
                    replaceBtn.style.display = 'inline-block';
                }

                summaryEl.innerHTML = `
                    <div class="mb-2"><span class="text-white-50">Document:</span> <span class="text-white fw-bold">${data.name}</span></div>
                    <div class="mb-2"><span class="text-white-50">Asset Class:</span> <span class="badge bg-label-info">${data.category}</span></div>
                    <div><span class="text-white-50">Technical Crux:</span><br><span class="text-info">${data.summary}</span></div>
                `;
                actionEl.style.display = 'block';
                
                pendingFiles.push({ 
                    id, 
                    file, 
                    category: data.category, 
                    summary: data.summary, 
                    name: data.name,
                    is_duplicate: data.is_duplicate 
                });
                
                // Show the global commit button if any files are ready
                const bulkBtn = document.getElementById('startBulkCommit');
                if (bulkBtn) {
                    bulkBtn.style.display = 'inline-block';
                    bulkBtn.onclick = processAll;
                }
            } catch (err) {
                document.getElementById(`status-${id}`).textContent = 'REJECTED';
                document.getElementById(`status-${id}`).className = 'badge bg-danger';
                document.getElementById(`summary-${id}`).className = 'text-xs text-danger ps-2 my-2';
                document.getElementById(`summary-${id}`).innerHTML = `⚠️ ${err.message}`;
                Log.error("CLIENT_AUDIT_ERROR: " + err.message);
            }
        });
    }

    async function confirmIngestion(id, overwrite = false) {
        const item = document.getElementById(`file-${id}`);
        const actionArea = document.getElementById(`action-${id}`);
        const statusArea = document.getElementById(`status-${id}`);
        const entry = pendingFiles.find(f => f.id === id);
        if (!entry) return;

        if (overwrite) {
            if (!confirm(`Warning: This will overwrite the existing version of '${entry.name}'. Proceed?`)) return;
        }

        actionArea.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Archiving...';
        statusArea.textContent = 'Vaulting...';
        
        const formData = new FormData();
        formData.append('file', entry.file);
        formData.append('category', entry.category);
        formData.append('name', entry.name);
        formData.append('summary', entry.summary);
        if (overwrite) formData.append('overwrite', '1');
        formData.append('_token', '{{ csrf_token() }}');

        try {
            const response = await fetch('{{ route("library.store") }}', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            
            if (response.status === 409) {
                // Should not happen with check above but for safety
                statusArea.textContent = 'DUPLICATE';
                statusArea.className = 'badge bg-warning';
                actionArea.innerHTML = `<button onclick="confirmIngestion('${id}', true)" class="btn btn-sm btn-warning py-1">Replace Existing</button>`;
                return;
            }

            if (!response.ok) throw new Error(data.error || 'Vault Error');

            statusArea.textContent = 'SECURED';
            statusArea.className = 'badge bg-success';
            actionArea.innerHTML = '✅ Assets Finalized';
            item.style.borderColor = '#198754';
            
            checkAllDone();
        } catch (err) {
            statusArea.textContent = 'ERROR';
            statusArea.className = 'badge bg-danger';
            actionArea.innerHTML = `<span class="text-danger text-xs italic">${err.message}</span>`;
        }
    }

    async function copyLibraryLink(url) {
        if (!navigator.clipboard) {
            // Fallback for non-secure contexts or older browsers
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            alert('Share link copied to clipboard!');
            return;
        }

        try {
            await navigator.clipboard.writeText(url);
            alert('Share link copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
    }

    function checkAllDone() {
        const total = pendingFiles.length;
        const secured = document.querySelectorAll('.badge.bg-success').length;
        const rejected = document.querySelectorAll('.badge.bg-danger').length;
        
        if (secured + rejected === total && total > 0) {
            setTimeout(() => { location.reload(); }, 1500);
        }
    }

    async function processAll() {
        const btn = document.getElementById('startBulkCommit');
        if (!btn || pendingFiles.length === 0) return;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Ingesting...';

        for (const entry of pendingFiles) {
             const statusEl = document.getElementById(`status-${entry.id}`);
             const statusText = statusEl.textContent;
             if (statusText === 'SECURED' || statusText === 'REJECTED' || statusText.includes('DUPLICATE')) continue;
             await confirmIngestion(entry.id);
        }
        
        btn.innerText = 'Sync Completed';
        checkAllDone();
    }
</script>
@endsection
