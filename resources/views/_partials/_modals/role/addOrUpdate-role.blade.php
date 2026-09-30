<!-- Create Role Modal — name + optional template only; fine-grained permissions
     are configured on the dedicated Role Permissions page right after. -->
<div class="modal fade" id="addOrUpdateRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
      <div class="modal-header modal-header-hitech py-4 px-5">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3" style="background: rgba(255,255,255,0.2); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
            <i class="bx bx-shield-quarter fs-3 text-white"></i>
          </div>
          <div>
            <h4 class="modal-title text-white fw-bold mb-0">@lang('Create New Role')</h4>
            <p class="text-white opacity-75 mb-0 small">@lang("Name it, optionally start from a template — you'll fine-tune access next.")</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-5">
        <form id="addRoleForm" onsubmit="return false">
          <div class="mb-4">
            <label class="form-label fw-bold text-heading mb-2 ms-1" for="name">@lang('Role Identity')</label>
            <div class="hitech-input-group-glass">
                <span class="input-group-text"><i class="bx bx-id-card fs-4"></i></span>
                <input type="text" id="name" name="name" class="form-control" placeholder="@lang('e.g. Senior HR Manager')" required />
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold text-heading mb-2 ms-1" for="description">@lang('Description')
              <span class="text-muted fw-normal small">— @lang('optional')</span>
            </label>
            <div class="hitech-input-group-glass">
                <span class="input-group-text"><i class="bx bx-text fs-4"></i></span>
                <input type="text" id="description" name="description" class="form-control" maxlength="255" placeholder="@lang('e.g. Sales team member — limited access to own data only')" />
            </div>
          </div>

          @if(!empty($rolePresets))
          <div class="mb-1">
            <label class="form-label fw-bold text-heading mb-2 ms-1" for="rolePreset">
                <i class="bx bx-magic-wand me-1 text-primary"></i>@lang('Start from a template')
                <span class="text-muted fw-normal small">— @lang('optional, you can adjust everything afterwards')</span>
            </label>
            <div class="hitech-input-group-glass">
                <span class="input-group-text"><i class="bx bx-category-alt fs-4"></i></span>
                <select id="rolePreset" name="preset" class="form-select">
                    <option value="">@lang('— No template (start empty) —')</option>
                    @foreach($rolePresets as $presetKey => $preset)
                        <option value="{{ $presetKey }}" title="{{ $preset['description'] ?? '' }}">{{ $preset['label'] ?? $presetKey }}</option>
                    @endforeach
                </select>
            </div>
            <p id="rolePresetHint" class="text-muted small mt-2 mb-0 ms-1" style="min-height: 1rem;"></p>
          </div>
          @endif

          <div class="text-end pt-4 mt-4 border-top">
            <button type="button" class="btn btn-label-secondary px-4 me-3" data-bs-dismiss="modal">@lang('Cancel')</button>
            <button type="submit" class="btn btn-primary btn-hitech-glow px-5">
                <i class="bx bx-arrow-forward me-1"></i> @lang('Create & Configure Access')
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
