<!-- Preview Access Modal -->
<style>
.preview-menu-tree { max-height: 440px; overflow-y: auto; border: 1px solid var(--hitech-border); border-radius: 14px; padding: 10px; background: #fbfcfc; }
.pmt-item { border-radius: 10px; padding: 10px 14px; margin-bottom: 3px; transition: background .15s; }
.pmt-item.pmt-visible:hover { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
.pmt-row { display: flex; align-items: center; gap: 10px; }
.pmt-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
            background: var(--hitech-primary-light); color: var(--hitech-primary); font-size: 15px; flex-shrink: 0; }
.pmt-item.pmt-hidden .pmt-icon { background: #f1f3f5; color: #adb5bd; }
.pmt-name { font-weight: 600; font-size: .85rem; color: #212529; flex: 1; }
.pmt-item.pmt-hidden .pmt-name { color: #adb5bd; text-decoration: line-through; text-decoration-color: #dee2e6; }
.pmt-badge { font-size: .6rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; letter-spacing: .04em; flex-shrink: 0; }
.pmt-badge-on { background: #dcfce7; color: #166534; }
.pmt-badge-off { background: #f1f3f5; color: #868e96; }
.pmt-reason { font-size: .68rem; color: #adb5bd; margin: 2px 0 0 40px; }
.pmt-sub { margin: 4px 0 2px 40px; padding-left: 14px; border-left: 2px solid #edf1f4; }
.pmt-sub .pmt-row { padding: 4px 0; gap: 8px; }
.pmt-sub .pmt-name { font-size: .78rem; font-weight: 500; }
#previewTarget optgroup { font-weight: 700; color: var(--hitech-primary); }
</style>

<div class="modal fade" id="previewAccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
      <div class="modal-header modal-header-hitech py-4 px-5">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3" style="background: rgba(255,255,255,0.2); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
            <i class="bx bx-show-alt fs-3 text-white"></i>
          </div>
          <div>
            <h4 class="modal-title text-white fw-bold mb-0">@lang('Preview Access')</h4>
            <p class="text-white opacity-75 mb-0 small">@lang('See exactly what a role or person can actually reach')</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-5">
        <div class="row g-3 align-items-end mb-4">
          <div class="col-8">
            <label class="form-label fw-bold text-heading mb-2 ms-1" for="previewTarget">@lang('Preview as')</label>
            <div class="hitech-input-group-glass">
              <span class="input-group-text"><i class="bx bx-user-circle fs-4"></i></span>
              <select id="previewTarget" class="form-select hitech-select border-0 shadow-none">
                <option value="">@lang('Choose a role or a person...')</option>
                <optgroup label="Roles — hypothetical, that role only, no personal extras">
                  @foreach($roles as $role)
                    <option value="role:{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                  @endforeach
                </optgroup>
                <optgroup label="Specific people — exactly what they see today">
                  @foreach($users as $u)
                    <option value="user:{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                  @endforeach
                </optgroup>
              </select>
            </div>
          </div>
          <div class="col-4">
            <button id="runPreview" type="button" class="btn btn-primary btn-hitech-glow w-100">
              <i class="bx bx-play-circle me-1"></i> @lang('Preview')
            </button>
          </div>
        </div>

        <div id="previewResults" class="d-none">
          <div class="d-flex align-items-center justify-content-between p-3 mb-4 rounded" style="background: var(--hitech-primary-light);">
            <div>
              <div class="small text-muted text-uppercase fw-bold" style="letter-spacing:.04em; font-size:.65rem;">@lang('Previewing')</div>
              <div id="previewLabel" class="fw-bold text-heading"></div>
            </div>
            <div class="text-end">
              <div id="previewStat" class="fw-bold" style="color: var(--hitech-primary); font-size: 1.25rem;"></div>
              <div class="small text-muted">@lang('sections reachable')</div>
            </div>
          </div>
          <div id="previewMenuTree" class="preview-menu-tree"></div>
        </div>

        <div id="previewEmpty" class="text-center py-5 text-muted">
          <div class="opacity-25 mb-2"><i class="bx bx-search-alt fs-1"></i></div>
          <p class="mb-0">@lang('Pick a role or a person above, then hit Preview.')</p>
        </div>

        <div id="previewLoading" class="text-center py-5 d-none">
          <div class="spinner-border" style="color: var(--hitech-primary);" role="status"></div>
          <p class="text-muted mt-3 mb-0">@lang('Computing exactly what they can reach...')</p>
        </div>
      </div>
    </div>
  </div>
</div>
