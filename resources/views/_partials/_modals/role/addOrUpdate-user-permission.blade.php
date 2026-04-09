<!-- User Special Permission Modal -->
<div class="modal fade" id="addOrUpdateUserPermissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
      <div class="modal-header modal-header-hitech py-4 px-5">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3" style="background: rgba(255,255,255,0.2); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
            <i class="bx bx-user-pin fs-3 text-white"></i>
          </div>
          <div>
            <h4 class="modal-title text-white fw-bold mb-0">@lang('User Special Permissions')</h4>
            <p class="text-white opacity-75 mb-0 small">@lang('Grant direct access overrides to individual employees')</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <form id="addUserPermissionForm" class="row g-0" onsubmit="return false">
          
          {{-- User Selection --}}
          <div class="col-12 p-5 pb-4" style="background: rgba(18, 116, 100, 0.02);">
            <div class="row align-items-end g-4">
                <div class="col-lg-6">
                    <label class="form-label fw-bold text-heading mb-2 ms-1" for="user_id">@lang('Select Employee')</label>
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
          </div>

          {{-- Permissions Table --}}
          <div class="col-12 px-5 py-4">
            <h6 class="fw-bold text-heading mb-4 d-flex align-items-center">
                <i class="bx bx-list-check me-2 text-primary fs-4"></i>@lang('Individual Permission Overrides')
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
                            <input class="user-row-select-all" type="checkbox" data-module="user-{{ \Illuminate\Support\Str::slug($moduleName) }}" />
                            <span class="checkmark"></span>
                        </label>
                    </td>
                    @foreach(['view', 'create', 'edit', 'delete'] as $action)
                        <td class="text-center">
                            @php
                                $matchedPerm = null;
                                foreach($perms as $p) {
                                    if(str_contains(strtolower($p), $action)) {
                                        $matchedPerm = $p;
                                        break;
                                    }
                                }
                                if(!$matchedPerm && $action == 'view' && count($perms) > 0) $matchedPerm = $perms[0];
                            @endphp
                            @if($matchedPerm)
                            <label class="hitech-checkbox justify-content-center m-0">
                                <input class="user-permission-checkbox user-{{ \Illuminate\Support\Str::slug($moduleName) }}-checkbox" 
                                       type="checkbox" 
                                       name="permissions[]" 
                                       value="{{ $matchedPerm }}" />
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

          {{-- Section 4: Actions --}}
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
