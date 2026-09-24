<!-- User Special Permission Modal -->
<style>
.usp-edit-matrix { width: 100%; border-collapse: collapse; }
.usp-edit-matrix thead th {
  background: rgba(18,116,100,.03); border: none; padding: 10px 12px; color: #127464;
  font-weight: 700; font-size: .65rem; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap;
}
.usp-edit-matrix thead th.text-center { text-align: center; }
.usp-edit-matrix tbody tr { border-top: 1px solid #f1f5f9; }
.usp-edit-matrix td { padding: 10px 12px; vertical-align: middle; }
.usp-edit-matrix .rp-crud-cell { text-align: center; width: 58px; }
.usp-edit-module { display: flex; align-items: center; gap: 8px; font-size: .8rem; font-weight: 800; color: #1e293b; min-width: 170px; }

/* Same visual language as the role editor's matrix (roles/index.blade.php
   and roles/_permission-panel.blade.php) — duplicated here (not shared via
   hitech-portal.scss) since this modal is also included from pages that
   never load those pages' scoped stylesheets. */
.rp-matrix-na { color: #d8dfe4; font-size: 1rem; }
.hitech-checkbox.rp-crud-check .checkmark { width: 17px; height: 17px; margin-right: 0; }
.rp-other-chips { display: flex; flex-wrap: wrap; gap: 6px 14px; }
.rp-chip { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; margin: 0; }
.rp-chip .chip-box {
  position: relative; width: 16px; height: 16px; border-radius: 5px; flex-shrink: 0;
  background: rgba(18,116,100,.05); border: 1.5px solid rgba(18,116,100,.25); transition: all .15s;
}
.rp-chip .chip-box::after {
  content: ''; position: absolute; display: none; left: 4px; top: 1px; width: 4px; height: 8px;
  border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
}
.rp-chip input { position: absolute; opacity: 0; width: 0; height: 0; }
.rp-chip input:checked ~ .chip-box { background: #127464; border-color: #127464; }
.rp-chip input:checked ~ .chip-box::after { display: block; }
.rp-chip-label { font-size: .76rem; font-weight: 600; color: #475569; }
.rp-chip-unwired .rp-chip-label { color: #94a3b8; }
.rp-unwired-dot { color: #f5a623; font-size: .8rem; cursor: help; }
</style>
<div class="modal fade" id="addOrUpdateUserPermissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
      <div class="modal-header modal-header-hitech py-4 px-5">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3" style="background: rgba(255,255,255,0.2); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
            <i class="bx bx-user-pin fs-3 text-white"></i>
          </div>
          <div>
            <h4 class="modal-title text-white fw-bold mb-0">@lang('Edit Special Permissions')</h4>
            <p class="text-white opacity-75 mb-0 small">@lang('Direct overrides on top of whatever the role already grants')</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <form id="addUserPermissionForm" class="row g-0" onsubmit="return false">

          {{-- Employee Selection --}}
          <div class="col-12 p-5 pb-4" style="background: rgba(18, 116, 100, 0.02);">
            <div class="row align-items-end g-4">
                <div class="col-lg-6">
                    <label class="form-label fw-bold text-heading mb-2 ms-1" for="user_id">@lang('Employee')</label>
                    <div class="hitech-input-group-glass">
                        <span class="input-group-text"><i class="bx bx-user fs-4"></i></span>
                        <select id="user_id" name="user_id" class="form-select hitech-select border-0 shadow-none">
                            <option value="">@lang('Choose an employee...')</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex justify-content-lg-end">
                        <div class="hitech-checkbox-row d-flex align-items-center bg-white p-2 px-3 rounded-pill border shadow-sm">
                            <label class="hitech-checkbox mb-0">
                                <input type="checkbox" id="selectAllUser" class="select-all-user-permissions" />
                                <span class="checkmark"></span>
                                <span class="fw-bold text-teal small">@lang('Grant All Special Permissions')</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-muted small mb-0 mt-3 ms-1"><i class="bx bx-info-circle me-1"></i>@lang('These are additional to the role — they never remove access the role already grants.')</p>
          </div>

          {{-- Permissions Matrix (same shape as the role editor) --}}
          <div class="col-12 px-5 py-4">
            <div class="table-responsive">
              <table class="usp-edit-matrix">
                <thead>
                  <tr>
                    <th>@lang('Module / Feature')</th>
                    <th class="text-center">@lang('View')</th>
                    <th class="text-center">@lang('Create')</th>
                    <th class="text-center">@lang('Edit')</th>
                    <th class="text-center">@lang('Delete')</th>
                    <th class="text-center">@lang('All')</th>
                    <th>@lang('Other Permissions')</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($permissionMatrix as $moduleName => $m)
                    <tr>
                      <td><div class="usp-edit-module"><i class="bx bx-folder text-primary"></i>{{ $moduleName }}</div></td>
                      @foreach(['view','create','edit','delete'] as $verb)
                        <td class="rp-crud-cell">
                          @if($m['crud'][$verb])
                            <label class="hitech-checkbox rp-crud-check justify-content-center m-0" data-bs-toggle="tooltip" title="{{ $m['crud'][$verb]['label'] }}">
                              <input type="checkbox" class="user-permission-checkbox user-{{ $m['slug'] }}-checkbox" data-module="user-{{ $m['slug'] }}" name="permissions[]" value="{{ $m['crud'][$verb]['name'] }}">
                              <span class="checkmark"></span>
                            </label>
                          @else
                            <span class="rp-matrix-na">—</span>
                          @endif
                        </td>
                      @endforeach
                      <td class="rp-crud-cell">
                        <label class="hitech-checkbox rp-crud-check justify-content-center m-0">
                          <input class="user-row-select-all" type="checkbox" data-module="user-{{ $m['slug'] }}" />
                          <span class="checkmark"></span>
                        </label>
                      </td>
                      <td>
                        @if(count($m['other']))
                          <div class="rp-other-chips">
                            @foreach($m['other'] as $permName => $label)
                              <label class="rp-chip {{ !($wiredPermissions[$permName] ?? true) ? 'rp-chip-unwired' : '' }}">
                                <input type="checkbox" class="user-permission-checkbox user-{{ $m['slug'] }}-checkbox" data-module="user-{{ $m['slug'] }}" name="permissions[]" value="{{ $permName }}">
                                <span class="chip-box"></span>
                                <span class="rp-chip-label">{{ $label }}</span>
                                @unless($wiredPermissions[$permName] ?? true)
                                  <i class="bx bx-info-circle rp-unwired-dot" data-bs-toggle="tooltip" title="@lang('Not checked anywhere in the app yet — granting this currently has no effect.')"></i>
                                @endunless
                              </label>
                            @endforeach
                          </div>
                        @else
                          <span class="rp-matrix-na">—</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

          {{-- Actions --}}
          <div class="col-12 px-5 py-4 border-top text-end" style="background: #fff;">
            <button type="reset" class="btn btn-label-secondary px-4 me-3" data-bs-dismiss="modal">@lang('Discard')</button>
            <button type="submit" class="btn btn-primary btn-hitech-glow px-5">
                <i class="bx bx-check-double me-1"></i> @lang('Apply Special Permissions')
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
