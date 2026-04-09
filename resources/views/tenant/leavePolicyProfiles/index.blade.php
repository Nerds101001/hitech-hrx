@extends('layouts/layoutMaster')

@section('title', __('Leave Profiles'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/js/app/leave-policy-profile-index.js'
  ])
@endsection

@section('content')
<div class="hitech-portal-layout-light animate__animated animate__fadeIn">
  {{-- Custom Page CSS for Light Mode --}}
  <style>
    .hitech-portal-layout-light { padding: 1.5rem; background: #f8fafc; min-height: 100vh; border-radius: 20px; }
    .page-header-premium { background: white; border: 1px solid #e2e8f0; border-radius: 24px; padding: 2.5rem; margin-bottom: 2.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
    .btn-hitech-primary { background: #127464; color: white; font-weight: 700; border-radius: 14px; transition: all 0.3s; border: none; }
    .btn-hitech-primary:hover { background: #0d5c4f; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(18, 116, 100, 0.2); color: white; }
    .btn-hitech-secondary { background: #f1f5f9; color: #475569; font-weight: 700; border-radius: 14px; border: 1px solid #e2e8f0; }
    .btn-hitech-secondary:hover { background: #e2e8f0; color: #1e293b; }
    
    .policy-card-white { background: white; border: 1px solid #e2e8f0; border-radius: 30px; transition: 0.3s ease; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative; overflow: hidden; }
    .policy-card-white:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1); border-color: #127464; }
    .policy-card-white::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: #127464; }

    .rule-item-light { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 18px; padding: 1.2rem; margin-bottom: 0.8rem; transition: 0.2s; }
    .rule-item-light:hover { background: white; border-color: #cbd5e1; transform: scale(1.01); }

    .icon-box { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 14px; font-size: 1.2rem; }
    .badge-soft-teal { background: rgba(18, 116, 100, 0.08); color: #127464; font-weight: 700; padding: 0.5rem 1rem; border-radius: 10px; font-size: 0.75rem; }
    .badge-soft-slate { background: #f1f5f9; color: #64748b; font-weight: 600; padding: 0.5rem 1rem; border-radius: 10px; font-size: 0.75rem; }

    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
  </style>

  <div class="page-header-premium">
    <div class="row align-items-center g-4">
      <div class="col-md-7">
        <div class="d-flex align-items-center mb-1">
            <div class="icon-box bg-hitech text-white me-3" style="background-color: #127464 !important;">
                <i class="bx bx-shield-quarter"></i>
            </div>
            <h2 class="text-dark fw-bold mb-0 tracking-tight">Organization Leave Policies</h2>
        </div>
        <p class="text-muted mb-0">Manage custom leave rules and weekend patterns for different staff profiles.</p>
      </div>
      <div class="col-md-5">
        <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-3">
            <button type="button" class="btn btn-hitech-secondary px-4 h-px-50 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalManualLeaveCredit">
                <i class="bx bxs-gift me-2"></i> Allot COFF Credits
            </button>
            <button type="button" class="btn btn-hitech-primary px-4 h-px-50 d-flex align-items-center add-new" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateLeavePolicyProfile">
              <i class="bx bx-plus me-2"></i> Create New Policy
            </button>
        </div>
      </div>
    </div>
  </div>

  @include('_partials._modals.leavePolicyProfile.add_or_update_leave_policy_profile')
  @include('_partials._modals.leavePolicyProfile.manual_credit')

  <div class="row g-6">
    @foreach($profiles as $profile)
      <div class="col-md-6 col-xxl-4">
        <div class="policy-card-white h-100 p-5">
          <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
              <h4 class="text-dark fw-bold mb-2 tracking-tight">{{ $profile->name }}</h4>
              <div class="d-flex gap-2">
                <span class="badge-soft-teal">
                   {{ $profile->saturday_off_config ? count($profile->saturday_off_config) : 0 }} Saturdays Off
                </span>
                <span class="badge-soft-slate">
                   {{ count($profile->rules) }} Active Rules
                </span>
              </div>
            </div>
            <a href="javascript:void(0);" class="btn btn-sm btn-icon btn-label-dark edit-record rounded-pill" data-id="{{ $profile->id }}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateLeavePolicyProfile">
              <i class="bx bx-edit"></i>
            </a>
          </div>

          <p class="text-muted small mb-5 line-height-relaxed" style="min-height: 40px;">
            {{ $profile->description ?: 'No policy description provided.' }}
          </p>
          
          <div class="rules-box mt-auto">
            <h6 class="text-uppercase x-small tracking-widest text-muted mb-4 fw-bold">Active Benefits</h6>
            <div class="rules-scroll custom-scrollbar" style="max-height: 250px; overflow-y: auto;">
                @forelse($profile->rules as $rule)
                  @if($rule->leaveType)
                    <div class="rule-item-light d-flex align-items-center gap-3">
                      <div class="icon-box {{ $rule->leaveType->is_short_leave ? 'bg-label-info' : 'bg-label-success' }}">
                          <i class="bx {{ $rule->leaveType->is_short_leave ? 'bx-time-five' : 'bx-calendar-check' }}"></i>
                      </div>
                      <div class="flex-grow-1">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                              <span class="fw-bold text-dark small">{{ $rule->leaveType->name }}</span>
                              @if($rule->is_carry_forward)
                                  <span class="badge bg-label-secondary x-small p-1" title="Carry Forward"><i class="bx bx-redo fs-px-10"></i></span>
                              @endif
                          </div>
                          <div class="d-flex align-items-center gap-2">
                              @if($rule->leaveType->is_short_leave)
                                  <span class="text-muted x-small fw-semibold">{{ $rule->short_leave_hours }}h x {{ $rule->short_leave_per_month }}/mo</span>
                              @else
                                  <span class="text-muted x-small fw-semibold">{{ $rule->max_per_month ?? 'Unlimited' }} / mo (Cap: {{ $rule->max_per_year ?? '∞' }}/yr)</span>
                              @endif
                          </div>
                      </div>
                    </div>
                  @endif
                @empty
                  <div class="py-5 text-center border-dashed rounded-4">
                    <i class="bx bx-info-circle text-muted fs-3 mb-2"></i>
                    <p class="text-muted x-small mb-0">No leave entitlements currently active.</p>
                  </div>
                @endforelse
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection
