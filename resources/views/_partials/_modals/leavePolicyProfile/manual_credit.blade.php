<div class="modal fade" id="modalManualLeaveCredit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            {{-- Header exactly matching screenshot header --}}
            <div class="modal-header modal-header-hitech d-flex align-items-center justify-content-between border-0">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-plus-circle text-white fs-4"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech mb-0">Manual Leave Credit</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            
            <form id="formManageLeaveAdjustment" onsubmit="handleManualLeaveCreditSubmission(event, this)" action="{{ route('leavePolicyProfiles.addManualCreditAjax') }}" class="bg-white" method="POST">
                @csrf
                <div class="modal-body p-4 pt-5 px-5">
                    <div class="row g-4">
                        @if(isset($user) && $user instanceof \App\Models\User)
                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                        @else
                            <div class="col-12">
                                <label class="form-label-target-design mb-2">TARGET EMPLOYEE</label>
                                <select class="form-select target-input-design w-100 select2" name="user_id" id="credit_user_id" required>
                                    <option value="">Select Employee...</option>
                                    @foreach($users ?? [] as $u)
                                        <option value="{{ $u->id }}">{{ ($u->first_name ?? '') . ' ' . ($u->last_name ?? '') }} ({{ $u->employee_id ?? 'N/A' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        
                        <div class="col-12">
                            <label class="form-label-target-design mb-2">LEAVE CATEGORY</label>
                            <select class="form-select target-input-design" name="leave_type_id" required>
                                @foreach($leaveTypes as $type)
                                    <option value="{{ $type->id }}" {{ $type->code == 'COFF' ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label-target-design mb-2">AMOUNT (DAYS)</label>
                            <input type="number" step="0.5" class="form-control target-input-design" name="amount" value="1" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label-target-design mb-2">DATE EARNED</label>
                            <input type="date" class="form-control target-input-design" name="date_earned" value="{{ date('Y-m-d') }}">
                        </div>

                        <div class="col-12 mt-4">
                            <label class="form-label-target-design mb-2">REASON / DESCRIPTION</label>
                            <textarea class="form-control target-input-design" name="adj_reason_text" placeholder="e.g. Worked on off-day for the annual audit..." rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3 bg-white">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-5 d-flex align-items-center fw-bold">Confirm Allotment <i class="bx bx-check-circle ms-2 fs-5"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>


