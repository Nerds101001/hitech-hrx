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

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Document Title (Optional)</label>
                                <input type="text" name="title" class="form-control border-0 bg-light" placeholder="e.g. Chemical Alpha SDS" style="border-radius: 12px; height: 45px;">
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

<script>
document.getElementById('fileSelector')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'Click to browse file';
    document.getElementById('fileNameLabel').textContent = fileName;
});
</script>
