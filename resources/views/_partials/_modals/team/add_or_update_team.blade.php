<div class="modal fade" id="modalAddOrUpdateTeam" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-group fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech" id="modalTeamLabel">@lang('Create Team')</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form id="teamForm">
          <input type="hidden" name="id" id="id">
          <input type="hidden" name="status" id="status">
          
          <div class="row">
            <div class="col-md-6 mb-5">
              <label class="form-label form-label-hitech" for="name">@lang('Team Name')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="name" name="name" placeholder="@lang('e.g. Development')" required />
            </div>
            <div class="col-md-6 mb-5">
              <label class="form-label form-label-hitech" for="code">@lang('Team Code')<span class="text-danger">*</span></label>
              <input type="text" class="form-control form-control-hitech" id="code" name="code" placeholder="@lang('e.g. DEV')" required />
            </div>
          </div>

          <div class="mb-5">
            <label class="form-label form-label-hitech" for="manager_ids">@lang('Assign Managers')</label>
            <select class="form-select select2-modal-team" id="manager_ids" name="manager_ids[]" multiple>
              @foreach(\App\Models\User::all() as $user)
                <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-5">
            <label class="form-label form-label-hitech" for="notes">@lang('Description')</label>
            <textarea class="form-control form-control-hitech" id="notes" name="notes" rows="3" placeholder="@lang('Optional description...')"></textarea>
          </div>

          @php
            $chatEnabled = app(\App\Services\AddonService\IAddonService::class)->isAddonEnabled('AiChat');
          @endphp
          @if($chatEnabled)
          <!-- Awesome Interactive Toggle Card -->
          <div class="interactive-toggle-card mb-6">
            <div class="d-flex align-items-center justify-content-between p-4 rounded-4 bg-glass-teal border-teal-subtle">
              <div class="d-flex align-items-center">
                <div class="icon-stat-teal me-3 p-2 bg-white bg-opacity-10 rounded-3 text-white">
                  <i class="bx bx-message-square-dots fs-4"></i>
                </div>
                <div>
                  <label class="form-label form-label-hitech mb-0 text-white" for="isChatEnabledToggle">@lang('Internal Messaging')</label>
                  <div class="small text-white-50 mt-1">Enable team collaborative chat</div>
                </div>
              </div>
              <div class="form-check form-switch-awesome mb-0">
                <input class="form-check-input hitech-awesome-toggle" type="checkbox" id="isChatEnabledToggle" checked>
                <input type="hidden" name="isChatEnabled" id="isChatEnabled" value="1">
              </div>
            </div>
          </div>
          @else
            <input type="hidden" name="isChatEnabled" id="isChatEnabled" value="0">
          @endif

          <div class="d-flex justify-content-end gap-3 mt-4">
            <button type="reset" class="btn btn-label-secondary px-4 h-px-45 d-flex align-items-center" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-hitech px-5 h-px-45 d-flex align-items-center data-submit">
              <span class="submit-text">@lang('Save Changes')</span>
              <i class="bx bx-check-circle ms-2 fs-5"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
