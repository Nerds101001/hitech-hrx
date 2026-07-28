{{-- Add / Edit Holiday — location-aware --}}
<div class="modal fade" id="modalAddOrUpdateHoliday" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-calendar-plus"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0" id="holidayModalTitle">Add Holiday</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>

      <div class="modal-body modal-body-hitech">
        <form id="holidayForm">
          @csrf
          <input type="hidden" name="id" id="holidayId">

          <div class="row g-4">
            <div class="col-md-8">
              <label class="form-label-hitech" for="holidayName">Holiday Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="holidayName" class="form-control form-control-hitech"
                     placeholder="e.g. Diwali" required>
            </div>

            <div class="col-md-4">
              <label class="form-label-hitech" for="holidayCode">Code</label>
              <input type="text" name="code" id="holidayCode" class="form-control form-control-hitech"
                     placeholder="Auto">
              <div class="form-text small">Leave blank to auto-generate.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="holidayDate">Date <span class="text-danger">*</span></label>
              <input type="date" name="date" id="holidayDate" class="form-control form-control-hitech" required>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="holidaySiteIds">
                Locations
                <i class="bx bx-info-circle text-muted"
                   title="Pick one or more locations. Leave empty to apply company-wide."></i>
              </label>
              <select name="site_ids[]" id="holidaySiteIds" class="form-select form-select-hitech holiday-multi"
                      multiple data-placeholder="All Locations (company-wide)">
                @foreach($sites as $site)
                  <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
              </select>
              <div class="form-text small">Leave empty = every location.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label-hitech" for="holidayDepartmentIds">
                Departments
                <i class="bx bx-info-circle text-muted"
                   title="Optional. Narrow the holiday to specific departments, e.g. a Production-only shutdown day."></i>
              </label>
              <select name="department_ids[]" id="holidayDepartmentIds" class="form-select form-select-hitech holiday-multi"
                      multiple data-placeholder="All Departments">
                @foreach(($departments ?? []) as $dept)
                  <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
              </select>
              <div class="form-text small">Leave empty = every department.</div>
            </div>

            {{-- Holiday type: full day off vs a part-day concession --}}
            <div class="col-12">
              <label class="form-label-hitech d-block mb-2">How much time off? <span class="text-danger">*</span></label>
              <div class="d-flex flex-wrap gap-4">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="holiday_type" value="full" id="htFull" checked>
                  <label class="form-check-label fw-semibold" for="htFull">
                    <i class="bx bx-calendar-x me-1"></i>Full day off
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="holiday_type" value="half" id="htHalf">
                  <label class="form-check-label fw-semibold" for="htHalf">
                    <i class="bx bx-adjust me-1"></i>Half day
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="holiday_type" value="hours" id="htHours">
                  <label class="form-check-label fw-semibold" for="htHours">
                    <i class="bx bx-time-five me-1"></i>A few hours off
                  </label>
                </div>
              </div>
            </div>

            <div class="col-md-4" id="hoursOffWrap" style="display:none;">
              <label class="form-label-hitech" for="holidayHoursOff">Hours off <span class="text-danger">*</span></label>
              <input type="number" name="hours_off" id="holidayHoursOff" class="form-control form-control-hitech"
                     step="0.5" min="0.5" max="7" placeholder="e.g. 2">
            </div>

            <div class="col-md-4" id="sessionWrap" style="display:none;">
              <label class="form-label-hitech" for="holidaySession">When</label>
              <select name="session" id="holidaySession" class="form-select form-select-hitech">
                <option value="">Not specified</option>
                <option value="morning">Morning (late start)</option>
                <option value="afternoon">Afternoon (early off)</option>
              </select>
            </div>

            <div class="col-md-4" id="genderWrap">
              <label class="form-label-hitech" for="holidayGender">Applies to</label>
              <select name="applies_to_gender" id="holidayGender" class="form-select form-select-hitech">
                <option value="all">All staff</option>
                <option value="female">Female staff only</option>
                <option value="male">Male staff only</option>
              </select>
              <div class="form-text small">e.g. Karwa Chauth — female staff only.</div>
            </div>

            <div class="col-12">
              <div class="p-3 rounded-3" id="holidayEffectNote"
                   style="background:#e6f4f1; border:1px solid rgba(0,90,90,.18); color:#005a5a; font-size:0.85rem;">
                <i class="bx bx-info-circle me-1"></i>
                <span id="effectText">Whole day off — no attendance expected, and anyone who works 4+ hours earns a comp-off day.</span>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label-hitech" for="holidayNotes">Notes</label>
              <textarea name="notes" id="holidayNotes" class="form-control form-control-hitech" rows="2"
                        placeholder="Any extra detail for HR"></textarea>
            </div>
          </div>

          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">
              Save Holiday <i class="bx bx-check-circle ms-1"></i>
            </button>
          </div>
        </form>

        <script>
        (function () {
          // Multi-select pickers for locations / departments
          if (window.jQuery && jQuery.fn.select2) {
            jQuery('.holiday-multi').select2({
              dropdownParent: jQuery('#modalAddOrUpdateHoliday'),
              width: '100%',
              placeholder: function () { return jQuery(this).data('placeholder'); },
              allowClear: true
            });
          }

          // Show only the fields the chosen holiday type needs, and explain
          // in plain words what the choice will do to attendance.
          function syncHolidayType() {
            var v = document.querySelector('input[name=holiday_type]:checked');
            v = v ? v.value : 'full';
            var hours = document.getElementById('hoursOffWrap');
            var sess  = document.getElementById('sessionWrap');
            var txt   = document.getElementById('effectText');
            var hoursIn = document.getElementById('holidayHoursOff');

            hours.style.display = (v === 'hours') ? 'block' : 'none';
            sess.style.display  = (v === 'full')  ? 'none'  : 'block';
            if (hoursIn) hoursIn.required = (v === 'hours');

            if (v === 'full') {
              txt.textContent = 'Whole day off — no attendance expected, and anyone who works 4+ hours earns a comp-off day.';
            } else if (v === 'half') {
              txt.textContent = 'Half day — still a working day, but staff need only about half the usual hours to be counted fully present.';
            } else {
              var h = (hoursIn && hoursIn.value) ? hoursIn.value : 'N';
              txt.textContent = 'Working day with ' + h + ' hour(s) off — an 7h45m day drops by that much, so a shorter day still counts as fully present.';
            }
          }
          document.querySelectorAll('input[name=holiday_type]').forEach(function (r) {
            r.addEventListener('change', syncHolidayType);
          });
          var hi = document.getElementById('holidayHoursOff');
          if (hi) hi.addEventListener('input', syncHolidayType);
          window.syncHolidayType = syncHolidayType;
          syncHolidayType();
        })();
        </script>
      </div>
    </div>
  </div>
</div>
