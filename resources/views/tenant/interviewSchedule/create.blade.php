<div class="modal-content modal-content-hitech">
    <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-calendar-check fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech">Schedule New Interview</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
    </div>
    <form action="{{ url('interview-schedule') }}" method="POST" class="needs-validation" novalidate>
        @csrf
        <div class="modal-body modal-body-hitech">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label-hitech">Candidate <span class="text-danger">*</span></label>
                    <select class="form-select form-select-hitech select2" name="candidate" required>
                        <option value="">{{ __('Select Candidate') }}</option>
                        @foreach($candidates as $key => $val)
                            <option value="{{ $key }}">{{ $val }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-hitech">Interviewer <span class="text-danger">*</span></label>
                    <select class="form-select form-select-hitech select2" name="employee" required>
                        <option value="">{{ __('Select Interviewer') }}</option>
                        @foreach($employees as $key => $val)
                            <option value="{{ $key }}">{{ $val }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-hitech">Interview Date <span class="text-danger">*</span></label>
                    <input class="form-control form-control-hitech" name="date" type="date" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label-hitech">Interview Time <span class="text-danger">*</span></label>
                    <input class="form-control form-control-hitech" name="time" type="time" value="{{ date('H:i') }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label-hitech">Comment / Notes</label>
                    <textarea class="form-control form-control-hitech" name="comment" rows="3" placeholder="Additional instructions for the interview..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech px-4">Schedule Interview</button>
        </div>
    </form>
</div>

@if($candidate != 0)
<script>
  $(document).ready(function() {
    $('select[name="candidate"]').val({{ $candidate }}).trigger('change');
  });
</script>
@endif
