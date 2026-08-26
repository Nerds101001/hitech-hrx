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
                        <i class="bx bx-scan text-white fs-4"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading mb-1 fw-bold text-info">Phase 1: Content Guardian Active</h6>
                        <p class="mb-0 text-white-50 small">Drop your SDS/TDS files. AI will extract the crux for your review before archival.</p>
                    </div>
                </div>

                <div class="bulk-uploader border-2 border-dashed border-info rounded-4 p-5 text-center transition-all cursor-pointer mb-4" id="dropzone" style="background: rgba(0,0,0,0.2);" onclick="document.getElementById('bulkFileInput').click()">
                    <div class="py-2">
                        <i class="bx bx-cloud-upload display-4 text-info mb-3 opacity-50"></i>
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
                    <i class="bx bx-check-shield me-1"></i> Finalize All Assets
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
                        <i class="bx bx-upload me-1"></i> File Upload
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link border-0 rounded-pill" data-bs-toggle="tab" href="#youtubeTab" role="tab">
                        <i class="bx bxl-youtube me-1"></i> External Video Link
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
                                <i class="bx bx-info-circle me-2"></i>
                                <small>AI will detect category (SDS/TDS/Presentation/Video) and extract the 5-6 line crux.</small>
                            </div>

                            {{-- Document Type — options come from config/digital_library.php, the
                                 same table toggleBrandSection() below reads, so a type only ever
                                 needs to be added or re-tiered in one place. --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Document Type</label>
                                <select name="category" id="manualDocType" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;" onchange="toggleBrandSection()">
                                    @foreach(config('digital_library.document_types') as $code => $meta)
                                        <option value="{{ $code }}">{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2 mb-3" id="brandSection">
                                <div class="col-6" id="brandFieldCol">
                                    <label class="form-label fw-bold small text-uppercase">Brand <span class="text-muted fw-normal">(optional for reports)</span></label>
                                    <select name="brand" id="brandSelect" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;">
                                        <option value="General">General / No Brand</option>
                                        <option value="RUST-X">RUST-X</option>
                                        <option value="Dr.Bio">Dr.Bio</option>
                                        <option value="KIF">KIF</option>
                                        <option value="Fillezy">Fillezy</option>
                                        <option value="Tuffpaulin">Tuffpaulin</option>
                                        <option value="ZOrbit">ZOrbit</option>
                                        <option value="HITECH">HITECH</option>
                                    </select>
                                </div>
                                <div class="col-6" id="subCategorySection">
                                    <label class="form-label fw-bold small text-uppercase" id="subCategoryLabel">Sub-Category / Subject</label>
                                    <select name="sub_category" id="subCategorySelect" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;"></select>
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
                                         <i class="bx bx-upload fs-1 text-primary mb-2"></i>
                                         <p class="mb-0 text-muted" id="fileNameLabel">Click to browse file</p>
                                     </div>
                                </div>
                            </div>
                        </div>

                        <!-- YouTube Tab -->
                        <div class="tab-pane fade" id="youtubeTab" role="tabpanel">
                            <div class="alert alert-warning d-flex align-items-center mb-2 border-0" style="border-radius: 12px;">
                                <i class="bx bx-error-circle me-2"></i>
                                <small>External video links (YouTube/Google Drive) do not require AI validation.</small>
                            </div>
                            <div class="alert alert-danger d-flex align-items-center mb-4 border-0" style="border-radius: 12px;">
                                <i class="bx bxl-google me-2 fs-4"></i>
                                <small><strong>Google Drive Warning:</strong> Even if set to "Public", Google Drive often blocks embedded videos and forces viewers to log in. For seamless playback, please use the <strong>File Upload</strong> tab to upload your .mp4 files directly!</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Video Category</label>
                                <select name="sub_category_yt" id="ytVideoCategorySelect" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;" onchange="toggleYtLearnFields()"></select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Video Title</label>
                                <input type="text" name="title_yt" class="form-control border-0 bg-light" placeholder="e.g. Training Session 1" style="border-radius: 12px; height: 45px;">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Video URL (YouTube/Drive)</label>
                                <input type="url" name="youtube_url" class="form-control border-0 bg-light" placeholder="https://youtube.com/... or https://drive.google.com/..." style="border-radius: 12px; height: 45px;">
                            </div>
                            {{-- Presenter/date only mean something for a Learn @ Hi Tech session —
                                 shown and required only when that category is picked above. --}}
                            <div class="row g-2 mb-3" id="ytLearnFields">
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-uppercase">Presenter <span class="text-danger">*</span></label>
                                    <select name="presenter_id" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;">
                                        <option value="">Select Presenter</option>
                                        @foreach(\App\Models\User::where('status', 'active')->orderBy('first_name')->get() as $u)
                                            <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-uppercase">Session Date <span class="text-danger">*</span></label>
                                    <input type="date" name="session_date" class="form-control border-0 bg-light" style="border-radius: 12px; height: 45px;">
                                </div>
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
                        <i class="bx bx-check-shield me-2"></i> Commit to Digital Vault
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
                    <i class="bx bx-error me-2"></i>
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
                
                <button type="button" class="btn btn-primary w-100 rounded-pill py-3 shadow-lg fw-bold mt-2" onclick="saveTaxonomyAction()" style="background: linear-gradient(135deg, var(--hitech-primary), #006666); border: none;">
                    Save Structure
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Video Player Modal -->
<div class="modal fade" id="videoPlayerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg bg-dark">
            <div class="modal-header border-0 pb-0 flex-column align-items-start">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold text-white" id="videoPlayerTitle">Video Player</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="videoPlayerMeta" class="mt-2 text-white small d-none fw-bold">
                    <span id="videoPlayerPresenter"><i class="bx bx-user me-1 text-primary"></i> Presenter: <span class="name"></span></span>
                    <span class="ms-3" id="videoPlayerDate"><i class="bx bx-calendar me-1 text-primary"></i> Date: <span class="date"></span></span>
                </div>
            </div>
            <div class="modal-body p-0 mt-3 position-relative">
                <div class="position-absolute top-0 start-0 w-100" style="height: 65px; z-index: 10; background: transparent;"></div>
                <div class="ratio ratio-16x9">
                    <iframe class="w-100 h-100 border-0" id="videoPlayerIframe" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Video Summary Modal -->
<div class="modal fade" id="videoSummaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-dark py-4 px-4">
                <h5 class="modal-title fw-bold text-white mb-0" id="videoSummaryTitle">Video Summary</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <p class="mb-0" id="videoSummaryText" style="color: #555; line-height: 1.7; font-size: 0.9rem;"></p>
            </div>
        </div>
    </div>
</div>

<!-- Assign Completion Modal -->
<div class="modal fade" id="assignCompletionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="assignCompletionTitle">Assign Learn @ HiTech Completion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="assignFileId">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Salesperson</label>
                    <select id="assignUserId" class="form-select border-0 bg-light" style="border-radius: 12px; height: 45px;">
                        <option value="">Select Salesperson</option>
                        @foreach(\App\Models\User::where('status', 'active')->orderBy('first_name')->get() as $u)
                            <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }} ({{ $u->employee_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Award Points</label>
                    <input type="number" id="assignPoints" class="form-control border-0 bg-light" value="5" min="1" style="border-radius: 12px; height: 45px;">
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<!-- Score Presenter Modal -->
<div class="modal fade" id="scorePresenterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="scorePresenterTitle">Score Presenter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="scoreFileId">
                <input type="hidden" id="scorePresenterId">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Presenter Name</label>
                    <input type="text" id="scorePresenterNameDisplay" class="form-control border-0 bg-light" disabled style="border-radius: 12px; height: 45px;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Score / Points Awarded</label>
                    <input type="number" id="scorePresenterPoints" class="form-control border-0 bg-light" value="20" min="1" style="border-radius: 12px; height: 45px;">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn text-white w-100 rounded-pill py-3 shadow-lg fw-bold" style="background: #f57f17;" onclick="submitScorePresenter()">
                    <i class="bx bx-star me-2"></i> Award Score to Presenter
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let taxonomyModal = null;

// Same table config/digital_library.php defines server-side — one place
// says what each document type needs, both here and in the browse page.
const DOCUMENT_TYPES = @json(config('digital_library.document_types'));
const LEARN_CATEGORY = @json(\App\Console\Commands\MigrateLearnToVideoCommand::LEARN_CATEGORY);
const VIDEO_CATEGORIES = @json($videoTaxonomies->pluck('name'));
const CHEMICAL_SUBCATEGORIES = [
    { value: 'Cleaners', label: 'Cleaners' },
    { value: 'Cutting Oil', label: 'Cutting Oil' },
    { value: 'Coatings', label: 'Coatings' },
    { value: 'VCI Packaging', label: 'VCI Packaging' },
    { value: 'Rust Preventive Oils', label: 'Oils' },
    { value: 'VCI Emitters', label: 'Emitters' },
    { value: 'Steel Coil Packaging', label: 'Packaging' },
    { value: 'VCI Sprays', label: 'Sprays' },
    { value: 'Zorbit Desiccant', label: 'Desiccant' },
    { value: 'Industrial Lubricants', label: 'Lubricants' },
    { value: 'Data Logger', label: 'Data Logger' },
    { value: 'Multi-Product', label: 'Multi-Product Comparison' },
    { value: 'Lab Test', label: 'Lab Test' },
    { value: 'Field Test', label: 'Field Test' },
    { value: 'Third Party Test', label: 'Third Party Test' },
];

function populateSelect(selectEl, options) {
    if (!selectEl) return;
    const list = options.length ? options : ['General'];
    selectEl.innerHTML = list.map(o =>
        typeof o === 'string' ? `<option value="${o}">${o}</option>` : `<option value="${o.value}">${o.label}</option>`
    ).join('');
}

function toggleBrandSection() {
    const type = document.getElementById('manualDocType')?.value;
    const level = DOCUMENT_TYPES[type]?.level || 'product';
    const brandSection = document.getElementById('brandSection');
    const brandFieldCol = document.getElementById('brandFieldCol');
    const subCategorySection = document.getElementById('subCategorySection');
    const subCategoryLabel = document.getElementById('subCategoryLabel');
    const subCategorySelect = document.getElementById('subCategorySelect');
    if (!brandSection) return;

    brandSection.style.display = 'flex';

    if (level === 'video') {
        // Video is tied to its own category taxonomy, never a brand.
        brandFieldCol.style.display = 'none';
        subCategorySection.style.display = '';
        subCategorySection.className = 'col-12';
        subCategoryLabel.textContent = 'Video Category';
        populateSelect(subCategorySelect, VIDEO_CATEGORIES);
    } else if (level === 'brand') {
        // Presentation / Comparison Report: brand only, no sub-category.
        brandFieldCol.style.display = '';
        brandFieldCol.className = 'col-12';
        subCategorySection.style.display = 'none';
    } else {
        // SDS / TDS / Test Report: brand + chemical sub-category, as before.
        brandFieldCol.style.display = '';
        brandFieldCol.className = 'col-6';
        subCategorySection.style.display = '';
        subCategorySection.className = 'col-6';
        subCategoryLabel.textContent = 'Sub-Category / Subject';
        populateSelect(subCategorySelect, CHEMICAL_SUBCATEGORIES);
    }
}

function toggleYtLearnFields() {
    const sel = document.getElementById('ytVideoCategorySelect');
    const learnFields = document.getElementById('ytLearnFields');
    if (!sel || !learnFields) return;
    // The category select's first option is Learn @ Hi Tech (lowest `order`
    // in library_taxonomies), so it is the default value on every page load
    // — including while the File Upload tab is the one showing. Gating only
    // on the select's value made presenter_id/session_date required (and
    // hidden, since they live inside the inactive video pane) for every
    // plain file upload: the browser's native validation silently blocks
    // the submit for a required-but-unfocusable field, with no error shown
    // and no JS handler ever running. Must also check the video tab is the
    // one actually active.
    const youtubeTabActive = document.getElementById('youtubeTab')?.classList.contains('active');
    const isLearn = sel.value === LEARN_CATEGORY && youtubeTabActive;
    learnFields.style.display = isLearn ? '' : 'none';
    learnFields.querySelectorAll('select, input').forEach(el => {
        if (isLearn) el.setAttribute('required', 'required');
        else el.removeAttribute('required');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    taxonomyModal = new bootstrap.Modal(document.getElementById('taxonomyModal'));
    populateSelect(document.getElementById('ytVideoCategorySelect'), VIDEO_CATEGORIES);
    toggleBrandSection(); // set initial state
    toggleYtLearnFields();
    // Re-check on every tab switch (manual click or openAddDocumentModal's
    // programmatic .show()) so required doesn't stay stuck from whichever
    // tab was active last.
    document.querySelector('a[href="#fileTab"]')?.addEventListener('shown.bs.tab', toggleYtLearnFields);
    document.querySelector('a[href="#youtubeTab"]')?.addEventListener('shown.bs.tab', toggleYtLearnFields);
});

function openAddModal(type, parentId = null) {
    document.getElementById('taxId').value = '';
    document.getElementById('taxType').value = type;
    // A brand has no parent; a video category's parent is also null (it isn't
    // under any brand). Assigning JS null to an input's .value coerces to the
    // literal string "null", which then fails the server's exists:... check —
    // use '' so an empty field round-trips to real NULL like everywhere else.
    document.getElementById('taxParentId').value = parentId ?? '';
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
    const parentIdRaw = document.getElementById('taxParentId').value;
    const parent_id = parentIdRaw ? parentIdRaw : null;
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

async function deleteDocument(title) {
    if (!confirm('Are you sure you want to completely delete this document and all its associated files from the Digital Vault? This action cannot be undone.')) return;
    
    try {
        const resp = await fetch('{{ route("library.delete-product") }}', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ title: title })
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Delete failed');
        alert('Document deleted successfully.');
        location.reload();
    } catch (err) { alert(err.message); }
}

document.getElementById('fileSelector')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'Click to browse file';
    document.getElementById('fileNameLabel').textContent = fileName;
});

function playVideo(url, title, presenterName = '', sessionDate = '') {
    document.getElementById('videoPlayerTitle').innerText = title;
    
    const metaContainer = document.getElementById('videoPlayerMeta');
    if (presenterName || sessionDate) {
        metaContainer.classList.remove('d-none');
        document.querySelector('#videoPlayerPresenter .name').innerText = presenterName || 'N/A';
        document.querySelector('#videoPlayerDate .date').innerText = sessionDate || 'N/A';
    } else {
        metaContainer.classList.add('d-none');
    }
    
    let embedUrl = url;
    
    // Auto-convert YouTube watch URLs
    if (url.includes('youtube.com/watch')) {
        try {
            const urlParams = new URL(url).searchParams;
            if (urlParams.get('v')) embedUrl = 'https://www.youtube.com/embed/' + urlParams.get('v');
        } catch(e) {}
    }
    // Auto-convert Google Drive URLs
    else if (url.includes('drive.google.com/file/d/')) {
        const matches = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
        if (matches && matches[1]) {
            // HACK: docs.google.com sometimes bypasses the aggressive login limits of drive.google.com
            embedUrl = 'https://docs.google.com/file/d/' + matches[1] + '/preview';
        }
    }
    
    document.getElementById('videoPlayerIframe').src = embedUrl;
    new bootstrap.Modal(document.getElementById('videoPlayerModal')).show();
}

document.getElementById('videoPlayerModal')?.addEventListener('hidden.bs.modal', function () {
    document.getElementById('videoPlayerIframe').src = ''; // Stop video playback
});

function openVideoSummaryModal(title, summary) {
    document.getElementById('videoSummaryTitle').innerText = title;
    document.getElementById('videoSummaryText').innerText = summary || 'No summary available for this video yet.';
    new bootstrap.Modal(document.getElementById('videoSummaryModal')).show();
}

function openAssignCompletionModal(fileId, title) {
    document.getElementById('assignFileId').value = fileId;
    document.getElementById('assignCompletionTitle').innerText = 'Assign Completion: ' + title;
    new bootstrap.Modal(document.getElementById('assignCompletionModal')).show();
}

async function submitAssignCompletion() {
    const fileId = document.getElementById('assignFileId').value;
    const userId = document.getElementById('assignUserId').value;
    const points = document.getElementById('assignPoints').value;
    
    if (!userId) {
        alert('Please select a salesperson.');
        return;
    }
    
    try {
        const resp = await fetch('{{ route("library.markLearnCompleted") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                library_file_id: fileId,
                user_id: userId,
                points: points
            })
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Failed to assign completion');
        alert(data.message);
        bootstrap.Modal.getInstance(document.getElementById('assignCompletionModal')).hide();
    } catch (err) {
        alert(err.message);
    }
}

function openScorePresenterModal(fileId, title, presenterId, presenterName) {
    document.getElementById('scoreFileId').value = fileId;
    document.getElementById('scorePresenterId').value = presenterId;
    document.getElementById('scorePresenterNameDisplay').value = presenterName;
    document.getElementById('scorePresenterTitle').innerText = 'Score Presenter: ' + title;
    new bootstrap.Modal(document.getElementById('scorePresenterModal')).show();
}

async function submitScorePresenter() {
    const fileId = document.getElementById('scoreFileId').value;
    const presenterId = document.getElementById('scorePresenterId').value;
    const points = document.getElementById('scorePresenterPoints').value;
    
    if (!points || points < 1) {
        alert('Please enter a valid score.');
        return;
    }
    
    try {
        const resp = await fetch('{{ route("library.scorePresenter") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                library_file_id: fileId,
                presenter_id: presenterId,
                points: points
            })
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Failed to score presenter');
        alert(data.message);
        bootstrap.Modal.getInstance(document.getElementById('scorePresenterModal')).hide();
    } catch (err) {
        alert(err.message);
    }
}

function openAddDocumentModal(category) {
    const fileTabNav = document.querySelector('a[href="#fileTab"]').parentElement;
    const youtubeTabNav = document.querySelector('a[href="#youtubeTab"]').parentElement;
    const fileTabLink = new bootstrap.Tab(document.querySelector('a[href="#fileTab"]'));
    const youtubeTabLink = new bootstrap.Tab(document.querySelector('a[href="#youtubeTab"]'));

    const fileDocTypeSelect = document.getElementById('manualDocType');

    // Reset visibility
    fileTabNav.style.display = 'block';
    youtubeTabNav.style.display = 'block';
    Array.from(fileDocTypeSelect.options).forEach(opt => opt.style.display = 'block');

    if (category === 'Video') {
        // Video only ever creates entries via this quick-add shortcut on the
        // external-link tab — a direct .mp4 upload is still possible from the
        // generic "Add Document" button (no pre-selected category).
        fileTabNav.style.display = 'none';
        youtubeTabLink.show();
    } else if (category && category !== 'All Files') {
        // SDS, TDS, Test Report, Presentation, Comparison Report
        youtubeTabNav.style.display = 'none';
        fileTabLink.show();

        Array.from(fileDocTypeSelect.options).forEach(opt => {
            if (opt.value !== category) opt.style.display = 'none';
        });

        if (Array.from(fileDocTypeSelect.options).some(opt => opt.value === category)) {
            fileDocTypeSelect.value = category;
        }
    } else {
        // Default when no specific category
        fileTabLink.show();
    }

    if (typeof toggleBrandSection === 'function') {
        toggleBrandSection();
    }

    new bootstrap.Modal(document.getElementById('uploadModal')).show();
}

document.getElementById('singleUploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const activeTab = document.querySelector('.nav-link.active').getAttribute('href');
    const isYoutube = activeTab === '#youtubeTab';

    if (isYoutube) {
        // The video-link tab's category select is named sub_category_yt so it
        // never collides with the file tab's own sub_category field while
        // both live in one form — promote it now that we know this is the
        // tab actually being submitted.
        formData.set('sub_category', formData.get('sub_category_yt'));
    }

    if (isYoutube && formData.get('sub_category') === LEARN_CATEGORY) {
        const title = formData.get('title_yt');
        const presenter = formData.get('presenter_id');
        const date = formData.get('session_date');

        if (!title || !presenter || !date) {
            Swal.fire({
                title: 'Missing Required Fields',
                text: 'Video Title, Presenter, and Session Date are compulsory for Learn @ Hi Tech Sessions.',
                icon: 'warning',
                confirmButtonColor: 'var(--hitech-primary)',
                customClass: {
                    popup: 'rounded-4 shadow-lg border-0',
                    title: 'fw-bold text-dark',
                    confirmButton: 'btn btn-primary rounded-pill px-4'
                }
            });
            return;
        }
    }

    // Both tabs sit inside one form, so the tab you are NOT using still gets
    // serialised. Send only the fields belonging to the tab actually in use.
    // Done after the checks above so validation still sees the whole form.
    (isYoutube
        ? ['file', 'name', 'sub_category_yt']
        : ['sub_category_yt', 'title_yt', 'youtube_url', 'presenter_id', 'session_date']
    ).forEach(field => formData.delete(field));

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bx bx-loader-alt spin me-2"></i> Committing...';
    submitBtn.disabled = true;
    
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]');
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf.getAttribute('content') } : {})
            }
        });

        // Parse defensively: a session timeout, gateway error or login redirect
        // hands back an HTML page, which would otherwise throw a cryptic
        // "Unexpected token '<'" when we blindly call response.json().
        // Note fetch() follows redirects silently, so a bounce to the login
        // page arrives here as status 200 with an HTML body.
        const raw = await response.text();
        let data = null;
        try { data = raw ? JSON.parse(raw) : {}; } catch (_) { data = null; }

        if (data === null) {
            if (response.redirected) {
                throw new Error('Your session expired. Please refresh the page and sign in again.');
            }
            if (response.status === 413) {
                throw new Error('File is too large to upload. Please use a smaller file.');
            }
            if (response.status === 401 || response.status === 419) {
                throw new Error('Your session expired. Please refresh the page and sign in again.');
            }
            if (response.status === 403) {
                throw new Error('You do not have permission to upload to the vault.');
            }
            if (response.status === 502 || response.status === 504 || response.status === 524) {
                throw new Error('The upload timed out on the server. Please try again.');
            }
            throw new Error('Upload failed (server returned a non-JSON response, code ' + response.status + '). Please refresh and try again.');
        }

        if (!response.ok) {
            throw new Error(data.error || data.message || 'Failed to upload asset');
        }
        
        // Success Popup
        Swal.fire({
            title: 'Success!',
            text: data.success || 'Asset secured to vault.',
            icon: 'success',
            confirmButtonColor: 'var(--hitech-primary)',
            customClass: {
                popup: 'rounded-4 shadow-lg border-0',
                title: 'fw-bold text-dark',
                confirmButton: 'btn btn-primary rounded-pill px-4'
            }
        }).then(() => {
            location.reload();
        });
        
    } catch (err) {
        Swal.fire({
            title: 'Error',
            text: err.message,
            icon: 'error',
            confirmButtonColor: '#dc3545',
            customClass: {
                popup: 'rounded-4 shadow-lg border-0',
                title: 'fw-bold text-dark',
                confirmButton: 'btn btn-danger rounded-pill px-4'
            }
        });
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});
</script>
