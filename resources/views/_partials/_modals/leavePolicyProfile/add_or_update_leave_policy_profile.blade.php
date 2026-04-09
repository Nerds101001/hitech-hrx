<div class="modal fade" id="modalAddOrUpdateLeavePolicyProfile" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
            {{-- Header exactly as screenshot --}}
            <div class="modal-header bg-teal-premium p-3 px-4 d-flex align-items-center justify-content-between border-0">
                <div class="d-flex align-items-center">
                    <div class="header-icon-square me-3">
                        <i class="bx bx-shield-quarter text-white fs-4"></i>
                    </div>
                    <h5 class="modal-title text-white fw-bold mb-0" id="modalProfileLabel">Policy Configuration Profile</h5>
                </div>
                <button type="button" class="btn-close-square" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x text-white"></i>
                </button>
            </div>

            <form id="profileForm">
                <div class="modal-body p-4 pt-5 px-5 custom-scrollbar bg-white" style="max-height: 75vh; overflow-y: auto;">
                    <input type="hidden" name="id" id="profile_id">
                    
                    <div class="row g-4 mb-5">
                        {{-- Profile Name --}}
                        <div class="col-md-5">
                            <label class="form-label-target-design mb-2">PROFILE NAME</label>
                            <input type="text" class="form-control target-input-design rounded-4" id="profile_name" name="name" placeholder="Management Tier..." required />
                        </div>

                        {{-- Saturday & Deduction Config --}}
                        <div class="col-md-7">
                            <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                                <label class="form-label-target-design mb-0">WEEKEND & DEDUCTION RULES</label>
                            </div>
                            
                            <div class="p-4 rounded-5 border bg-light bg-opacity-10 mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="x-small fw-extrabold text-teal-premium">SATURDAY OFF CONFIGURATIONS</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="x-small fw-bold text-muted">ALL SATURDAYS OFF</span>
                                        <label class="ios-switch ios-switch-sm">
                                            <input type="checkbox" id="all_saturday_off_toggle">
                                            <span class="ios-slider rounded-pill"></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap mb-4">
                                    @foreach([1, 2, 3, 4, 5] as $day)
                                        <div class="sat-tile-target">
                                            <input type="checkbox" name="saturday_off[]" value="{{ $day }}" id="sat_{{ $day }}" class="btn-check saturday-item-check">
                                            <label class="sat-label-target px-3 py-2 fs-6 rounded-4" for="sat_{{ $day }}">
                                                @if($day == 1) 1st @elseif($day == 2) 2nd @elseif($day == 3) 3rd @else {{ $day }}th @endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>

                                <hr class="my-4 opacity-25">

                                <div class="row g-3">
                                    <div class="col-12 mb-1">
                                        <span class="x-small fw-extrabold text-teal-premium text-uppercase">Late Arrival Deductions</span>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="x-small text-muted fw-bold">After</span>
                                            <input type="number" class="form-control form-control-sm target-input-design rounded-3 py-1 px-2 fs-7" name="deduction_config[late_arrival_limit]" placeholder="Lates" style="max-width: 60px;" id="late_arrival_limit">
                                            <span class="x-small text-muted fw-bold">Lates, Deduct</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select form-select-sm target-input-design rounded-3 py-1 px-2 fs-7" name="deduction_config[late_arrival_type]" id="late_arrival_type">
                                            <option value="half_day">Half Day Salary</option>
                                            <option value="full_day">Full Day Salary</option>
                                        </select>
                                    </div>

                                    <div class="col-12 mt-3 mb-1">
                                        <span class="x-small fw-extrabold text-teal-premium text-uppercase">Half Day Deductions</span>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="x-small text-muted fw-bold">After</span>
                                            <input type="number" class="form-control form-control-sm target-input-design rounded-3 py-1 px-2 fs-7" name="deduction_config[half_day_limit]" placeholder="Freq" style="max-width: 60px;" id="half_day_limit">
                                            <span class="x-small text-muted fw-bold">Occurrences, Deduct</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select form-select-sm target-input-design rounded-3 py-1 px-2 fs-7" name="deduction_config[half_day_type]" id="half_day_type">
                                            <option value="half_day">Half Day Salary</option>
                                            <option value="full_day">Full Day Salary</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Description --}}
                        <div class="col-12 mt-4">
                            <label class="form-label-target-design mb-2">INTERNAL REMARKS / POLICY OVERVIEW</label>
                            <textarea class="form-control target-input-design rounded-4" id="profile_description" name="description" placeholder="Summarize policy rules here..." rows="2"></textarea>
                        </div>

                        {{-- Leave Rules Section --}}
                        <div class="col-12 mt-5">
                            <label class="form-label-target-design mb-4 border-bottom pb-2 w-100">CONFIGURE LEAVE RULES</label>
                            
                            <div class="row g-4" id="leaveTypeRulesContainer">
                                @foreach($leaveTypes as $type)
                                    <div class="col-12">
                                        <div class="rule-group-premium border rounded-5 p-0 transition-all-200 bg-white">
                                            <div class="p-4 d-flex align-items-center justify-content-between cursor-pointer" 
                                                 data-bs-toggle="collapse" 
                                                 data-bs-target="#collapseType{{ $type->id }}">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="rule-icon-circle rounded-circle">
                                                        <i class="bx {{ $type->is_short_leave ? 'bx-time-five' : 'bx-calendar-event' }} text-teal-premium fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold text-dark fs-5 mb-0 d-block">{{ $type->name }}</span>
                                                        <span class="x-small fw-bold text-muted text-uppercase tracking-wider">{{ $type->is_short_leave ? 'HOURLY BASIS' : 'DAILY BASIS' }}</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex align-items-center gap-4">
                                                    <label class="ios-switch">
                                                        <input type="checkbox" 
                                                               class="type-applicable-toggle" 
                                                               name="rules[{{ $type->id }}][is_applicable]" 
                                                               id="is_applicable_{{ $type->id }}" 
                                                               value="1">
                                                        <span class="ios-slider rounded-pill"></span>
                                                    </label>
                                                    <i class="bx bx-chevron-down text-muted fs-3 accordion-arrow"></i>
                                                </div>
                                            </div>

                                            <div class="collapse" id="collapseType{{ $type->id }}">
                                                <div class="p-4 border-top">
                                                    <div class="row g-4">
                                                        {{-- Eligibility & Entitlement Row (Unified) --}}
                                                        @if($type->is_strict_rules || $type->is_split_entitlement)
                                                            <div class="col-12">
                                                                <div class="p-3 rounded-4 border border-dashed d-flex align-items-center flex-wrap gap-4">
                                                                    @if($type->is_strict_rules)
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span class="x-small text-muted fw-bold text-uppercase">Gender</span>
                                                                            <select name="rules[{{ $type->id }}][applicable_gender]" class="form-select form-select-sm target-input-design rounded-3 py-1 px-2 fs-7" style="min-width: 140px;">
                                                                                <option value="all">All Genders</option>
                                                                                <option value="male">Male Only</option>
                                                                                <option value="female">Female Only</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span class="x-small text-muted fw-bold text-uppercase">Status</span>
                                                                            <select name="rules[{{ $type->id }}][applicable_marital_status]" class="form-select form-select-sm target-input-design rounded-3 py-1 px-2 fs-7" style="min-width: 140px;">
                                                                                <option value="all">Any Status</option>
                                                                                <option value="single">Single Only</option>
                                                                                <option value="married">Married Only</option>
                                                                            </select>
                                                                        </div>
                                                                    @endif

                                                                    @if($type->is_split_entitlement)
                                                                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                                                                            <span class="x-small text-muted fw-bold text-uppercase">Remote/WFH</span>
                                                                            <input type="number" class="form-control form-control-sm target-input-design rounded-3 py-1 px-2 fs-7" name="rules[{{ $type->id }}][wfh_days_entitlement]" placeholder="Days" style="max-width: 100px;">
                                                                        </div>
                                                                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                                                                            <span class="x-small text-muted fw-bold text-uppercase">Off-Duty</span>
                                                                            <input type="number" class="form-control form-control-sm target-input-design rounded-3 py-1 px-2 fs-7" name="rules[{{ $type->id }}][off_days_entitlement]" placeholder="Days" style="max-width: 100px;">
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif

                                                        {{-- Short Leave Configuration --}}
                                                        @if($type->is_short_leave)
                                                            <div class="col-md-6">
                                                                <label class="form-label-target-design mb-2">MAX HOURS / USE</label>
                                                                <input type="number" step="0.5" class="form-control target-input-design rounded-4 bg-light bg-opacity-10" name="rules[{{ $type->id }}][short_leave_hours]" placeholder="e.g. 2.0">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label-target-design mb-2">MAX TIMES / MONTH</label>
                                                                <input type="number" class="form-control target-input-design rounded-4 bg-light bg-opacity-10" name="rules[{{ $type->id }}][short_leave_per_month]" placeholder="e.g. 1">
                                                            </div>
                                                        @else
                                                            {{-- Standard Leave Configuration --}}
                                                            @if(!$type->is_split_entitlement && !in_array($type->code, ['ML', 'PL_PAT']))
                                                                <div class="col-md-4">
                                                                    <label class="form-label-target-design mb-2">MONTHLY ALLOTMENT</label>
                                                                    <input type="number" step="0.5" class="form-control target-input-design rounded-4 bg-light bg-opacity-10" name="rules[{{ $type->id }}][max_per_month]" placeholder="e.g. 1.0">
                                                                </div>
                                                            @endif

                                                            @if($type->is_carry_forward)
                                                                <div class="col-md-4">
                                                                    <label class="form-label-target-design mb-2">CARRY FORWARD</label>
                                                                    <div class="input-group">
                                                                        <select class="form-select target-input-design rounded-start-4 bg-light bg-opacity-10" name="rules[{{ $type->id }}][is_carry_forward]">
                                                                            <option value="1">ENABLED</option>
                                                                            <option value="0">DISABLED</option>
                                                                        </select>
                                                                        <input type="number" class="form-control target-input-design rounded-end-4 bg-light bg-opacity-10" name="rules[{{ $type->id }}][carry_forward_max_days]" placeholder="Cap" title="Max Carry Forward Days">
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            @if($type->is_consecutive_allowed)
                                                                <div class="col-md-4">
                                                                    <label class="form-label-target-design mb-2">MAX CONSECUTIVE</label>
                                                                    <input type="number" class="form-control target-input-design rounded-4 bg-light bg-opacity-10" name="rules[{{ $type->id }}][max_consecutive_days]" placeholder="Days (e.g. 3)">
                                                                </div>
                                                            @endif

                                                            {{-- Tenure Upgrade Section (Depends on Consecutive) --}}
                                                            @if($type->is_consecutive_allowed)
                                                                <div class="col-12 mt-2">
                                                                    <div class="p-4 rounded-4 tenure-premium-bar d-flex align-items-center flex-wrap gap-4 border shadow-sm">
                                                                        <div class="d-flex align-items-center gap-3 pe-4 border-end border-teal-light">
                                                                            <div class="bg-teal-premium rounded-circle p-2">
                                                                                <i class="bx bx-trending-up text-white fs-5"></i>
                                                                            </div>
                                                                            <span class="fw-black text-dark text-uppercase tracking-wider small">Tenure Upgrade:</span>
                                                                        </div>
                                                                        
                                                                        <div class="d-flex align-items-center gap-4 flex-grow-1">
                                                                            <div class="d-flex align-items-center gap-2">
                                                                                <span class="x-small text-muted fw-bold">IF TENURE ></span>
                                                                                <input type="number" class="form-control form-control-sm border-0 border-bottom border-teal-premium rounded-0 bg-transparent fw-black text-center" name="rules[{{ $type->id }}][tenure_required_months]" placeholder="Mo" style="width: 80px; min-height: 35px;">
                                                                                <span class="x-small text-muted fw-bold">MONTHS</span>
                                                                            </div>
                                                                            
                                                                            <div class="h-px-30 border-start mx-2"></div>
                                                                            
                                                                            <div class="d-flex align-items-center gap-3">
                                                                                <span class="x-small text-muted fw-bold">INCREASE MAX CONSECUTIVE TO</span>
                                                                                <input type="number" class="form-control form-control-sm border-0 border-bottom border-teal-premium rounded-0 bg-transparent fw-black text-center" name="rules[{{ $type->id }}][tenure_consecutive_allowed]" placeholder="Days" style="width: 80px; min-height: 35px;">
                                                                                <span class="x-small text-muted fw-bold">DAYS</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Action Footer --}}
                <div class="modal-footer p-4 border-0 justify-content-end gap-3 bg-white">
                    <button type="button" class="btn btn-target-cancel px-5 h-px-50 rounded-pill" data-bs-dismiss="modal">Discard</button>
                    <button type="submit" class="btn btn-target-submit px-6 h-px-50 rounded-pill fw-bold text-white fs-5 shadow-sm">Save Policy Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    :root {
        --target-teal: #127464;
        --target-teal-light: #e6f1ef;
    }
    .bg-teal-premium { background-color: var(--target-teal) !important; }
    .text-teal-premium { color: var(--target-teal) !important; }
    .border-teal-premium { border-color: var(--target-teal) !important; }
    .border-teal-light { border-color: var(--target-teal-light) !important; }
    
    .header-icon-square { width: 36px; height: 36px; border: 1.5px solid rgba(255,255,255,0.4); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .btn-close-square { background: rgba(255,255,255,0.15); border: 0; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .form-label-target-design { font-size: 11px; font-weight: 800; color: #64748b; letter-spacing: 0.08em; }
    .target-input-design { border: 1.5px solid #e2e8f0; padding: 0.8rem 1.1rem; font-weight: 700; box-shadow: none !important; color: #1e293b; }
    .target-input-design:focus { border-color: var(--target-teal); background: #fff; }

    .btn-target-cancel { background-color: #fff1f2; color: #f43f5e; border: 0; font-weight: 800; transition: 0.2s; }
    .btn-target-submit { background-color: var(--target-teal); color: white; border: 0; transition: 0.3s; }
    .btn-target-submit:hover { opacity: 0.95; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(18, 116, 100, 0.2); }

    /* IOS Style Toggle Switch - PRO VERSION */
    .ios-switch { position: relative; display: inline-block; width: 60px; height: 32px; }
    .ios-switch input { opacity: 0; width: 0; height: 0; }
    .ios-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #f1f5f9; border: 2px solid #e2e8f0; transition: .3s cubic-bezier(0.19, 1, 0.22, 1); }
    .ios-slider:before { position: absolute; content: ""; height: 24px; width: 24px; left: 2px; bottom: 2px; background-color: white; transition: .3s cubic-bezier(0.19, 1, 0.22, 1); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    input:checked + .ios-slider { background-color: var(--target-teal); border-color: var(--target-teal); }
    input:checked + .ios-slider:before { transform: translateX(28px); }

    .ios-switch-sm { width: 44px; height: 24px; }
    .ios-switch-sm .ios-slider:before { height: 18px; width: 18px; left: 1px; bottom: 1px; }
    .ios-switch-sm input:checked + .ios-slider:before { transform: translateX(20px); }
    .tracking-tighter { letter-spacing: -0.02em; }

    .sat-label-target { font-weight: 800; border: 2.5px solid #f1f5f9; cursor: pointer; transition: 0.2s; background: #fff; color: #94a3b8; min-width: 75px; text-align: center; }
    .btn-check:checked + .sat-label-target { background: var(--target-teal); color: white; border-color: var(--target-teal); box-shadow: 0 4px 15px rgba(18, 116, 100, 0.25); }
    
    .rule-group-premium { background: white; transition: 0.3s; border-width: 2px !important; }
    .rule-group-premium:hover { border-color: var(--target-teal); background: #fdfdfd; }
    .rule-icon-circle { width: 50px; height: 50px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; }

    .tenure-premium-bar { background: linear-gradient(to right, #ffffff, var(--target-teal-light)); border-color: var(--target-teal-light); }
    
    .accordion-arrow { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .collapsed .accordion-arrow { transform: rotate(-90deg); }

    .fw-black { font-weight: 900 !important; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .tracking-wider { letter-spacing: 0.08em; }
</style>

<script>
    // Logic for All Saturdays Off
    document.getElementById('all_saturday_off_toggle').addEventListener('change', function() {
        const checks = document.querySelectorAll('.saturday-item-check');
        checks.forEach(c => {
            c.checked = this.checked;
        });
    });
</script>
