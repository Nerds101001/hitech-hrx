<!-- Add Role Modal -->
<div class="modal fade" id="addOrUpdateRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-add-new-role">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
      <div class="modal-header modal-header-hitech py-4 px-5">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header animate__animated animate__pulse animate__infinite me-3" style="background: rgba(255,255,255,0.2); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
            <i class="bx bx-shield-quarter fs-3 text-white"></i>
          </div>
          <div>
            <h4 class="modal-title role-title text-white fw-bold mb-0">@lang('Update Role Permissions')</h4>
            <p class="text-white opacity-75 mb-0 small">@lang('Configure granular access levels across all system modules.')</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <form id="addRoleForm" class="row g-0" onsubmit="return false">
          <input type="hidden" name="id" id="id">
          
          {{-- Section 1: Identity & Primary Controls --}}
          <div class="col-12 p-5 pb-4" style="background: rgba(18, 116, 100, 0.02);">
            <div class="row align-items-end g-4">
                <div class="col-lg-6">
                    <label class="form-label fw-bold text-heading mb-2 ms-1" for="name">@lang('Role Identity')</label>
                    <div class="hitech-input-group-glass">
                        <span class="input-group-text"><i class="bx bx-id-card fs-4"></i></span>
                        <input type="text" id="name" name="name" class="form-control" placeholder="@lang('e.g. Senior HR Manager')" required />
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex justify-content-lg-end">
                        <div class="hitech-checkbox-row d-flex align-items-center bg-white p-2 px-3 rounded-pill border shadow-sm">
                            <label class="hitech-checkbox mb-0">
                                <input type="checkbox" id="selectAll" class="select-all-permissions" />
                                <span class="checkmark"></span>
                                <span class="fw-bold text-teal small">@lang('Grant Full Administrative Access')</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
          </div>

          {{-- Section 2: Permissions Matrix --}}
          <div class="col-12 px-5 py-4">
            <h6 class="fw-bold text-heading mb-4 d-flex align-items-center">
                <i class="bx bx-list-check me-2 text-primary fs-4"></i>@lang('Module-wise Access Permissions')
            </h6>
            <div class="table-responsive">
              <table class="table table-permissions-hitech w-100">
                <thead>
                  <tr>
                    <th style="width: 280px;">@lang('Module / Feature Set')</th>
                    <th class="text-center" style="width: 120px;">@lang('Select All')</th>
                    <th class="text-center">@lang('View')</th>
                    <th class="text-center">@lang('Create')</th>
                    <th class="text-center">@lang('Edit')</th>
                    <th class="text-center">@lang('Delete')</th>
                    <th class="text-center">@lang('Manage')</th>
                  </tr>
                </thead>
                <tbody>
                  @php
                    $modules = [
                        'Roles & Permissions' => ['roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'admin.roles.manage'],
                        'Employees' => ['hr.employees.view', 'hr.employees.create', 'hr.employees.edit', 'hr.employees.delete'],
                        'Attendance' => ['hr.attendance.view', 'hr.attendance.create', 'hr.attendance.edit', 'hr.attendance.delete', 'Manage Attendance'],
                        'Recruitment' => ['recruitment.view', 'recruitment.create', 'recruitment.edit', 'recruitment.delete', 'recruitment.manage'],
                        'Payroll' => ['payroll.view', 'payroll.create', 'payroll.edit', 'payroll.delete', 'payroll.manage'],
                        'Settings' => ['hr.settings.view', 'hr.settings.create', 'hr.settings.edit', 'hr.settings.delete', 'hr.settings.manage'],
                        'Approvals' => ['hr.approvals.view', 'hr.approvals.create', 'hr.approvals.edit', 'hr.approvals.delete'],
                        'Assets' => ['assets.view', 'assets.create', 'assets.edit', 'assets.delete', 'assets.index'],
                        'AI & Library Vault' => ['library.view', 'library.upload', 'bot.chat', 'ai.training.manage'],
                        'LMS' => ['lms.courses.view', 'lms.courses.create', 'lms.courses.edit', 'lms.courses.delete', 'lms.courses.index'],
                        'System Logs' => ['auditLogs.view', 'auditLogs.create', 'auditLogs.edit', 'auditLogs.delete', 'auditLogs.index']
                    ];
                  @endphp

                  @foreach($modules as $moduleName => $perms)
                  <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="stat-icon-wrap icon-primary me-3" style="width: 32px; height: 32px; font-size: 1rem;"><i class="bx bx-folder"></i></span>
                            {{ $moduleName }}
                        </div>
                    </td>
                    <td class="text-center">
                        <label class="hitech-checkbox justify-content-center m-0">
                            <input class="row-select-all" type="checkbox" data-module="{{ \Illuminate\Support\Str::slug($moduleName) }}" />
                            <span class="checkmark"></span>
                        </label>
                    </td>
                    @foreach(['view', 'create', 'edit', 'delete', 'manage'] as $action)
                        <td class="text-center">
                            @php
                                $matchedPerm = null;
                                foreach($perms as $p) {
                                    $pLower = strtolower($p);
                                    
                                    // Custom mappings for specific modules
                                    if($moduleName == 'AI & Library Vault') {
                                        if($action == 'create' && str_contains($pLower, 'upload')) { $matchedPerm = $p; break; }
                                        if($action == 'edit' && str_contains($pLower, 'chat')) { $matchedPerm = $p; break; }
                                        if($action == 'manage' && str_contains($pLower, 'manage')) { $matchedPerm = $p; break; }
                                    }
                                    
                                    if(str_contains($pLower, $action)) {
                                        $matchedPerm = $p;
                                        break;
                                    }
                                }
                                if(!$matchedPerm && $action == 'view' && count($perms) > 0) $matchedPerm = $perms[0];
                            @endphp
                            @if($matchedPerm)
                            <label class="hitech-checkbox justify-content-center m-0">
                                <input class="permission-checkbox {{ \Illuminate\Support\Str::slug($moduleName) }}-checkbox" 
                                       type="checkbox" 
                                       name="permissions[]" 
                                       value="{{ $matchedPerm }}" 
                                       id="perm_{{ md5($matchedPerm) }}" />
                                <span class="checkmark"></span>
                            </label>
                            @endif
                        </td>
                    @endforeach
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

          {{-- Section 3: Advanced Settings Toggles --}}
          <div class="col-12 px-5 py-4 border-top" style="background: rgba(18, 116, 100, 0.01);">
              <h6 class="fw-bold text-heading mb-4 small text-uppercase letter-spacing-05">@lang('Advanced Role Configuration')</h6>
              <div class="row g-4 mb-2">
                  <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <div class="hitech-toggle-wrapper me-3">
                            <input type="checkbox" class="hitech-switch-input" id="isMultiCheckInEnabled" name="isMultiCheckInEnabled" />
                            <label class="hitech-switch-label" for="isMultiCheckInEnabled"></label>
                        </div>
                        <label class="fw-bold text-heading small" for="isMultiCheckInEnabled">@lang('Multi Check-In')</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <div class="hitech-toggle-wrapper me-3">
                            <input type="checkbox" class="hitech-switch-input" id="mobileAppAccess" name="mobileAppAccess" />
                            <label class="hitech-switch-label" for="mobileAppAccess"></label>
                        </div>
                        <label class="fw-bold text-heading small" for="mobileAppAccess">@lang('Mobile App')</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <div class="hitech-toggle-wrapper me-3">
                            <input type="checkbox" class="hitech-switch-input" id="webAppAccess" name="webAppAccess" />
                            <label class="hitech-switch-label" for="webAppAccess"></label>
                        </div>
                        <label class="fw-bold text-heading small" for="webAppAccess">@lang('Web Login')</label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="d-flex align-items-center">
                        <div class="hitech-toggle-wrapper me-3">
                            <input type="checkbox" class="hitech-switch-input" id="locationActivityTracking" name="locationActivityTracking" />
                            <label class="hitech-switch-label" for="locationActivityTracking"></label>
                        </div>
                        <label class="fw-bold text-heading small" for="locationActivityTracking">@lang('GPS Tracking')</label>
                    </div>
                  </div>
              </div>
          </div>

          {{-- Section 4: Actions --}}
          <div class="col-12 px-5 py-4 border-top text-end" style="background: #fff;">
            <button type="reset" class="btn btn-label-secondary px-4 me-3" data-bs-dismiss="modal">@lang('Discard Changes')</button>
            <button type="submit" class="btn btn-primary btn-hitech-glow px-5">
                <i class="bx bx-check-double me-1"></i> @lang('Save Role Permissions')
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
