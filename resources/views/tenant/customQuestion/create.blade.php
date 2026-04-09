<div class="modal-content modal-content-hitech">
    <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-question-mark fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech">Create New Custom Question</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
    </div>
    <form action="{{ url('custom-question') }}" method="POST" class="needs-validation" novalidate>
        @csrf
        <div class="modal-body modal-body-hitech">
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label-hitech">Question <span class="text-danger">*</span></label>
                    <input class="form-control form-control-hitech" name="question" type="text" placeholder="Enter interview question" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label-hitech">Question Type <span class="text-danger">*</span></label>
                    <select class="form-select form-select-hitech select2" name="type" required>
                        <option value="text">Short Answer</option>
                        <option value="textarea">Long Answer</option>
                        <option value="number">Number</option>
                        <option value="date">Date</option>
                        <option value="select">MCQ (Single Select)</option>
                        <option value="checkbox">Checkbox (Multi Select)</option>
                        <option value="file">File Upload</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-hitech">Is Required? <span class="text-danger">*</span></label>
                    <select class="form-select form-select-hitech select2" id="is_required" name="is_required" required>
                        @foreach($is_required as $key => $val)
                            <option value="{{ $key }}">{{ $val }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12 d-none" id="options_container">
                    <label class="form-label-hitech">Choices (Comma Separated) <span class="text-danger">*</span></label>
                    <input class="form-control form-control-hitech" name="options" id="options_input" type="text" placeholder="e.g. Option A, Option B, Option C">
                    <small class="text-muted mt-1 d-block"><i class="bx bx-info-circle me-1"></i>Enter options separated by commas.</small>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech px-4">Create Question</button>
        </div>
    </form>
</div>
<script>
    $(document).ready(function() {
        $('select[name="type"]').on('change', function() {
            var val = $(this).val();
            if(val == 'select' || val == 'checkbox') {
                $('#options_container').removeClass('d-none');
                $('#options_input').prop('required', true);
            } else {
                $('#options_container').addClass('d-none');
                $('#options_input').prop('required', false);
            }
        });
    });
</script>
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech px-4">Create Question</button>
        </div>
    </form>
</div>
