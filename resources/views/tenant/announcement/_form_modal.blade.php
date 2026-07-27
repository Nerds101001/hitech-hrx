{{-- New / Edit Announcement — audience targeting --}}
<div class="modal fade" id="modalAnnouncement" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-broadcast"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0" id="announcementModalTitle">New Announcement</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>

      <div class="modal-body modal-body-hitech">
        <form id="announcementForm">
          @csrf
          <input type="hidden" name="id" id="announcementId">

          <div class="row g-4">
            <div class="col-12">
              <label class="form-label-hitech" for="annTitle">Title <span class="text-danger">*</span></label>
              <input type="text" name="title" id="annTitle" class="form-control form-control-hitech"
                     maxlength="200" placeholder="e.g. Diwali holiday timings" required>
            </div>

            <div class="col-12">
              <label class="form-label-hitech" for="annContent">Message <span class="text-danger">*</span></label>
              <textarea name="content" id="annContent" class="form-control form-control-hitech" rows="5"
                        placeholder="Write the announcement..." required></textarea>
            </div>

            <div class="col-md-3">
              <label class="form-label-hitech" for="annType">Type <span class="text-danger">*</span></label>
              <select name="type" id="annType" class="form-select form-select-hitech" required>
                @foreach(['general','urgent','policy','holiday','event'] as $t)
                  <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label-hitech" for="annPriority">Priority <span class="text-danger">*</span></label>
              <select name="priority" id="annPriority" class="form-select form-select-hitech" required>
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
              <div class="form-text small">Critical always emails, regardless of the email option.</div>
            </div>

            <div class="col-md-3">
              <label class="form-label-hitech" for="annStart">Show From <span class="text-danger">*</span></label>
              <input type="date" name="start_date" id="annStart" class="form-control form-control-hitech"
                     value="{{ now()->toDateString() }}" required>
            </div>

            <div class="col-md-3">
              <label class="form-label-hitech" for="annEnd">Show Until</label>
              <input type="date" name="end_date" id="annEnd" class="form-control form-control-hitech">
              <div class="form-text small">Blank = no expiry.</div>
            </div>

            {{-- Audience --}}
            <div class="col-12">
              <label class="form-label-hitech d-block mb-2">Who should see this? <span class="text-danger">*</span></label>
              <div class="d-flex flex-wrap gap-4">
                @if(!$isManager)
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="audience_type" value="global" id="audGlobal" checked>
                    <label class="form-check-label fw-semibold" for="audGlobal">
                      <i class="bx bx-globe me-1"></i>Everyone in the company
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="audience_type" value="targeted" id="audTargeted">
                    <label class="form-check-label fw-semibold" for="audTargeted">
                      <i class="bx bx-target-lock me-1"></i>Specific department / location / role
                    </label>
                  </div>
                @endif
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="audience_type" value="team" id="audTeam"
                         {{ $isManager ? 'checked' : '' }}>
                  <label class="form-check-label fw-semibold" for="audTeam">
                    <i class="bx bx-group me-1"></i>My team only
                  </label>
                </div>
              </div>
              @if($isManager)
                <div class="alert alert-info mt-3 mb-0 py-2 px-3 small">
                  <i class="bx bx-info-circle me-1"></i>
                  As a manager you can post to your own team. Company-wide announcements are published by HR/Admin.
                </div>
              @endif
            </div>

            {{-- Targeting pickers --}}
            <div class="col-12" id="targetingBlock" style="display:none;">
              <div class="p-4 rounded-3" style="background:#f8fafc; border:1px solid #e2e8f0;">
                <div class="fw-bold small text-uppercase text-muted mb-3">
                  Target audience — anyone matching <em>any</em> selection below will receive it
                </div>
                <div class="row g-4">
                  <div class="col-md-6">
                    <label class="form-label-hitech" for="annDepartments">Departments</label>
                    <select name="target_department_ids[]" id="annDepartments" class="form-select target-select" multiple>
                      @foreach($departments as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-hitech" for="annSites">Locations / Units</label>
                    <select name="target_site_ids[]" id="annSites" class="form-select target-select" multiple>
                      @foreach($sites as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-hitech" for="annRoles">Roles</label>
                    <select name="target_role_names[]" id="annRoles" class="form-select target-select" multiple>
                      @foreach($roles as $r)
                        <option value="{{ $r->name }}">{{ ucwords(str_replace('_', ' ', $r->name)) }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label-hitech" for="annDesignations">Designations</label>
                    <select name="target_designation_ids[]" id="annDesignations" class="form-select target-select" multiple>
                      @foreach($designations as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="d-flex flex-wrap gap-4">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="notify_email" id="annNotifyEmail" value="1" checked>
                  <label class="form-check-label fw-semibold" for="annNotifyEmail">Also send by email</label>
                </div>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="requires_acknowledgment" id="annNeedsAck" value="1">
                  <label class="form-check-label fw-semibold" for="annNeedsAck">Require acknowledgement</label>
                </div>
              </div>
              <div class="form-text small mt-2">
                Everyone in the audience always gets an in-app notification and a push alert.
              </div>
            </div>
          </div>

          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">
              Publish <i class="bx bx-send ms-1"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
