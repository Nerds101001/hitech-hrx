<div class="modal-content modal-content-hitech">
    <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-git-repo-forked fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech">Edit Job Stage</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
    </div>
    <form action="{{ route('job-stage.update', $jobStage->id) }}" method="POST" class="needs-validation" novalidate>
        @csrf
        @method('PUT')
        <div class="modal-body modal-body-hitech">
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label-hitech">Stage Title <span class="text-danger">*</span></label>
                    <input class="form-control form-control-hitech" name="title" type="text" value="{{ $jobStage->title }}" placeholder="Enter stage title" required>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech px-4">Update Stage</button>
        </div>
    </form>
</div>
