{{-- Unified Document Viewer Modal --}}
<div class="modal fade" id="modalViewDocument" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-hitech border-0 shadow-lg">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-file" id="modalViewIcon"></i>
                    </div>
                    <div>
                        <h5 class="modal-title modal-title-hitech mb-1" id="modalViewTitle">Document Viewer</h5>
                        <p id="modalViewSubtitle" class="mb-0 small text-white opacity-75 fw-medium" style="font-size: 0.75rem;"></p>
                    </div>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>

            <div class="modal-body modal-body-hitech p-0" style="min-height: 500px; background: #f8fafc; position: relative;">
                <div id="modalViewContent" class="w-100 h-100 d-flex align-items-center justify-content-center">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Streaming document content...</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 bg-white p-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-medium"><i class='bx bx-info-circle me-1'></i>Secure Hitech Document Stream</span>
                <div class="d-flex gap-2">
                    <a href="#" id="modalDownloadBtn" class="btn btn-hitech-primary rounded-pill px-4" download>
                        <i class='bx bx-download me-1'></i>Download
                    </a>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
