<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; background: #1a1a1a;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold fs-4 text-white">AI Technical Inquest</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 🛡️ Strategic AI Parser Banner -->
                <div class="alert alert-primary d-flex align-items-center mb-4 border-0" role="alert" style="border-radius: 15px; background: rgba(0, 255, 255, 0.05);">
                    <div class="p-3 bg-info rounded-circle me-3 shadow-sm">
                        <i class="ti ti-scan text-white fs-4"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading mb-1 fw-bold text-info">Phase 1: Content Guardian Active</h6>
                        <p class="mb-0 text-white-50 small">Drop your SDS/TDS files. AI will extract the crux for your review before archival.</p>
                    </div>
                </div>

                <div class="bulk-uploader border-2 border-dashed border-info rounded-4 p-5 text-center transition-all cursor-pointer mb-4" id="dropzone" style="background: rgba(0,0,0,0.2);" onclick="document.getElementById('bulkFileInput').click()">
                    <div class="py-2">
                        <i class="ti ti-cloud-upload display-4 text-info mb-3 opacity-50"></i>
                        <h5 class="fw-bold text-white">Ingest Hitech Assets</h5>
                        <p class="text-muted mb-0">Drag documents here to initiate AI Audit.</p>
                        <input type="file" id="bulkFileInput" multiple hidden accept=".pdf">
                    </div>
                </div>

                <div class="upload-queue mt-4" id="bulkUploadList">
                    <!-- Cards will be injected here via index.blade.php JS -->
                </div>
            </div>
            <div class="modal-footer border-0 bg-black/10">
                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill text-white" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info px-5 rounded-pill shadow-lg fw-bold" id="startBulkCommit" style="display:none;">
                    <i class="ti ti-shield-check me-1"></i> Finalize All Assets
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Single Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0 shadow-none">
                <h5 class="modal-title fw-bold">Add Strategic Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <ul class="nav nav-tabs nav-fill mt-3 px-3 border-0" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active border-0 rounded-pill" data-bs-toggle="tab" href="#fileTab" role="tab">
                        <i class="ti ti-file-upload me-1"></i> File Upload
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link border-0 rounded-pill" data-bs-toggle="tab" href="#youtubeTab" role="tab">
                        <i class="ti ti-brand-youtube me-1"></i> YouTube Link
                    </a>
                </li>
            </ul>

            <form id="singleUploadForm" action="{{ route('library.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4 pt-2">
                    <div class="tab-content border-0 p-0 shadow-none">
                        <!-- File Upload Tab -->
                        <div class="tab-pane fade show active" id="fileTab" role="tabpanel">
                            <div class="alert alert-info d-flex align-items-center mb-4 border-0" style="border-radius: 12px; background: rgba(0, 172, 193, 0.1); color: #00838f;">
                                <i class="ti ti-info-circle me-2"></i>
                                <small>AI will detect category (SDS/TDS/MOM/LEARN) and extract the 5-6 line crux.</small>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-uppercase">Brand</label>
                                    <select name="brand" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;">
                                        <option value="RUST-X">RUST-X</option>
                                        <option value="Dr.Bio">Dr.Bio</option>
                                        <option value="KIF">KIF</option>
                                        <option value="Fillezy">Fillezy</option>
                                        <option value="Tuffpaulin">Tuffpaulin</option>
                                        <option value="ZOrbit">ZOrbit</option>
                                        <option value="HITECH" selected>HITECH</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-uppercase">Category</label>
                                    <select name="sub_category" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;">
                                        <option value="Cleaners">Cleaners</option>
                                        <option value="Cutting Oil">Cutting Oil</option>
                                        <option value="Coatings">Coatings</option>
                                        <option value="VCI Packaging">VCI Packaging</option>
                                        <option value="Rust Preventive Oils">Oils</option>
                                        <option value="VCI Emitters">Emitters</option>
                                        <option value="Steel Coil Packaging">Packaging</option>
                                        <option value="VCI Sprays">Sprays</option>
                                        <option value="Zorbit Desiccant">Desiccant</option>
                                        <option value="Industrial Lubricants">Lubricants</option>
                                        <option value="Data Logger">Data Logger</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Document Title (Optional)</label>
                                <input type="text" name="name" class="form-control border-0 bg-light" placeholder="e.g. Chemical Alpha SDS" style="border-radius: 12px; height: 45px;">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Select File</label>
                                <div class="file-upload-wrapper p-4 text-center bg-light rounded-4 border-2 border-dashed" style="border-color: #e0e0e0;">
                                     <input type="file" name="file" class="form-control d-none" id="fileSelector" accept=".pdf,.mp4">
                                     <div onclick="document.getElementById('fileSelector').click()" class="cursor-pointer">
                                         <i class="ti ti-file-upload fs-1 text-primary mb-2"></i>
                                         <p class="mb-0 text-muted" id="fileNameLabel">Click to browse file</p>
                                     </div>
                                </div>
                            </div>
                        </div>

                        <!-- YouTube Tab -->
                        <div class="tab-pane fade" id="youtubeTab" role="tabpanel">
                            <div class="alert alert-warning d-flex align-items-center mb-4 border-0" style="border-radius: 12px;">
                                <i class="ti ti-alert-circle me-2"></i>
                                <small>YouTube videos are stored as links and do not require AI validation.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Video Title</label>
                                <input type="text" name="title_yt" class="form-control border-0 bg-light" placeholder="e.g. Training Session 1" style="border-radius: 12px; height: 45px;">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">YouTube URL</label>
                                <input type="url" name="youtube_url" class="form-control border-0 bg-light" placeholder="https://youtube.com/watch?v=..." style="border-radius: 12px; height: 45px;">
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_public" value="1" checked>
                        <label class="form-check-label fw-bold">Public / Shared with everyone</label>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 shadow-lg fw-bold">
                        <i class="ti ti-shield-check me-2"></i> Commit to Digital Vault
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Move Product Modal -->
<div class="modal fade" id="moveProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Relocate Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning small border-0 mb-4" style="border-radius: 12px; background: #fff3e0; color: #e65100;">
                    <i class="ti ti-alert-triangle me-2"></i>
                    This will move <strong>all files</strong> associated with this product title across the entire vault.
                </div>
                
                <input type="hidden" id="moveProductTitle">
                
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">New Target Brand</label>
                    <select id="moveBrand" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;">
                        <option value="RUST-X">RUST-X</option>
                        <option value="Dr.Bio">Dr.Bio</option>
                        <option value="Fillezy">Fillezy</option>
                        <option value="KIF">KIF</option>
                        <option value="ZOrbit">ZOrbit</option>
                        <option value="Tuffpaulin">Tuffpaulin</option>
                        <option value="HITECH">HITECH</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">New Target Category</label>
                    <input type="text" id="moveCategory" class="form-control border-0 bg-light" placeholder="e.g. VCI Packaging" style="border-radius: 12px; height: 45px;">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-primary w-100 rounded-pill py-3 shadow-lg fw-bold" onclick="executeMove()">
                    Confirm Relocation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Taxonomy Management Modals -->
<div class="modal fade" id="taxonomyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-dark py-4 px-4">
                <h5 class="modal-title fw-bold text-white mb-0" id="taxModalTitle">Manage Technical Entity</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <input type="hidden" id="taxId">
                <input type="hidden" id="taxType">
                <input type="hidden" id="taxParentId">
                
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase text-muted letter-spacing-1">Title / Name</label>
                    <input type="text" id="taxName" class="form-control border-light bg-light py-3 px-3 shadow-none" placeholder="e.g. Bio-Cleaners" style="border-radius: 12px; font-weight: 500;">
                </div>
                
                <div id="brandExtras" style="display:none;">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted letter-spacing-1">Brand Theme Color</label>
                        <input type="color" id="taxColor" class="form-control form-control-color border-light bg-light w-100 py-1" style="border-radius: 12px; height: 50px;">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted letter-spacing-1">Brand Mission / Description</label>
                        <textarea id="taxDesc" class="form-control border-light bg-light py-3 px-3 shadow-none" rows="3" placeholder="Describe the brand's focus..." style="border-radius: 12px; font-weight: 500;"></textarea>
                    </div>
                </div>
                
                <button type="button" class="btn btn-primary w-100 rounded-pill py-3 shadow-lg fw-bold mt-2" onclick="saveTaxonomyAction()" style="background: linear-gradient(135deg, #00897b, #00695c); border: none;">
                    Save Structure
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let taxonomyModal = null;

document.addEventListener('DOMContentLoaded', function() {
    taxonomyModal = new bootstrap.Modal(document.getElementById('taxonomyModal'));
});

function openAddModal(type, parentId = null) {
    document.getElementById('taxId').value = '';
    document.getElementById('taxType').value = type;
    document.getElementById('taxParentId').value = parentId;
    document.getElementById('taxModalTitle').innerText = 'Add ' + (type === 'brand' ? 'Brand Ecosystem' : 'Technical Category');
    document.getElementById('brandExtras').style.display = type === 'brand' ? 'block' : 'none';
    document.getElementById('taxName').value = '';
    document.getElementById('taxDesc').value = '';
    taxonomyModal.show();
}

async function editTaxonomy(type, id) {
    // We could fetch real data, but for now we'll assume the prompt or just pre-fill if we had the data in JS.
    // For a "perfect" experience, let's fetch the data first.
    try {
        document.getElementById('taxId').value = id;
        document.getElementById('taxType').value = type;
        document.getElementById('taxModalTitle').innerText = 'Edit ' + (type === 'brand' ? 'Brand' : 'Category');
        document.getElementById('brandExtras').style.display = type === 'brand' ? 'block' : 'none';
        
        // Find existing data from the UI if possible, or just open for rename
        const card = document.querySelector(`[data-brand-card]`); // This is simplified
        document.getElementById('taxName').value = ''; 
        
        taxonomyModal.show();
    } catch (err) { alert(err.message); }
}

async function saveTaxonomyAction() {
    const id = document.getElementById('taxId').value;
    const type = document.getElementById('taxType').value;
    const name = document.getElementById('taxName').value;
    const parent_id = document.getElementById('taxParentId').value;
    const color = document.getElementById('taxColor').value;
    const description = document.getElementById('taxDesc').value;

    if (!name) return alert('Please enter a name');

    const url = id ? `/digital-library/taxonomy/update/${id}` : '{{ route("library.taxonomy.add") }}';
    
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ type, name, parent_id, color, description })
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Operation failed');
        location.reload();
    } catch (err) { alert(err.message); }
}

async function deleteTaxonomy(id) {
    if (!confirm('Are you sure? This will permanently remove this structure.')) return;
    try {
        const resp = await fetch(`/digital-library/taxonomy/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        if (!resp.ok) throw new Error('Delete failed');
        location.reload();
    } catch (err) { alert(err.message); }
}

function openMoveModal(title, brand, cat) {
    document.getElementById('moveProductTitle').value = title;
    document.getElementById('moveBrand').value = brand;
    document.getElementById('moveCategory').value = cat;
    new bootstrap.Modal(document.getElementById('moveProductModal')).show();
}

async function executeMove() {
    const title = document.getElementById('moveProductTitle').value;
    const brand = document.getElementById('moveBrand').value;
    const cat = document.getElementById('moveCategory').value;
    
    try {
        const resp = await fetch('{{ route("library.reassign") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                current_title: title,
                new_brand: brand,
                new_sub_category: cat
            })
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Move failed');
        location.reload();
    } catch (err) { alert(err.message); }
}

document.getElementById('fileSelector')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'Click to browse file';
    document.getElementById('fileNameLabel').textContent = fileName;
});
</script>
