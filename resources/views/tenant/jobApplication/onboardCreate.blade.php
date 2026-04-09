<form action="{{ route('job.on.board.store', $id) }}" method="POST" class="needs-validation" novalidate>
  @csrf
<div class="modal-body pt-5">
  <div class="row g-4">
    @if ($id == 0)
      <div class="col-md-12">
        <label class="form-label fw-bold small text-muted text-uppercase mb-2">Candidate <span class="text-danger">*</span></label>
        <select class="form-select select2" name="application" required>
            <option value="">{{ __('Select Candidate') }}</option>
            @foreach($applications as $key => $val)
                <option value="{{ $key }}">{{ $val }}</option>
            @endforeach
        </select>
      </div>
    @endif
    <div class="col-md-12">
      <label class="form-label fw-bold small text-muted text-uppercase mb-2">Joining Date <span class="text-danger">*</span></label>
      <input class="form-control" name="joining_date" type="date" value="{{ date('Y-m-d') }}" required>
    </div>

    <div class="col-md-6">
      <label class="form-label fw-bold small text-muted text-uppercase mb-2">Days Of Week <span class="text-danger">*</span></label>
      <input class="form-control" name="days_of_week" type="number" min="0" required placeholder="e.g. 5">
    </div>
    <div class="col-md-6">
      <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salary <span class="text-danger">*</span></label>
      <div class="input-group">
        <span class="input-group-text">$</span>
        <input class="form-control" name="salary" type="number" min="0" required placeholder="Enter salary">
      </div>
    </div>

    <div class="col-md-6">
      <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salary Type <span class="text-danger">*</span></label>
      <select class="form-select select2" name="salary_type" required>
          <option value="">{{ __('Select Salary Type') }}</option>
          @foreach($salary_type as $key => $val)
              <option value="{{ $key }}">{{ $val }}</option>
          @endforeach
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salary Duration <span class="text-danger">*</span></label>
      <select class="form-select select2" name="salary_duration" required>
          <option value="">{{ __('Select Salary Duration') }}</option>
          @foreach($salary_duration as $key => $val)
              <option value="{{ $key }}">{{ $val }}</option>
          @endforeach
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label fw-bold small text-muted text-uppercase mb-2">Job Type <span class="text-danger">*</span></label>
      <select class="form-select select2" name="job_type" required>
          <option value="">{{ __('Select Job Type') }}</option>
          @foreach($job_type as $key => $val)
              <option value="{{ $key }}">{{ $val }}</option>
          @endforeach
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status <span class="text-danger">*</span></label>
      <select class="form-select select2" name="status" required>
          <option value="">{{ __('Select Status') }}</option>
          @foreach($status as $key => $val)
              <option value="{{ $key }}">{{ $val }}</option>
          @endforeach
      </select>
    </div>
  </div>
</div>
<div class="modal-footer border-top pt-4">
  <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
  <button type="submit" class="btn btn-primary px-6">Create On-Boarding</button>
</div>
</form>
