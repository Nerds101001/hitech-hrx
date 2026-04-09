@php use App\Enums\Status; @endphp
@extends('layouts/layoutMaster')

@section('title', __('Document Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .btn:not(.rounded-pill), .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
        border-radius: 10px;
    }
    /* Integrated Filter Styling */
    .bg-light-soft {
        background-color: rgba(0, 90, 90, 0.04) !important;
        border-bottom: 1px solid rgba(0, 90, 90, 0.08);
    }
    .search-wrapper-hitech .form-control:focus {
        background: #fff !important;
    }
    
    /* Toggle Control Styling */
    .hitech-segmented-control .status-toggle-btn {
        border: none;
        background: transparent;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        font-size: 0.72rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 0.5rem 1.5rem;
    }
    .hitech-segmented-control .status-toggle-btn.active {
        background: #fff !important;
        color: #005a5a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        border-radius: 8px;
    }
    .hitech-segmented-control {
        border: 1px solid rgba(0,0,0,0.04);
        padding: 4px !important;
        background: rgba(0,0,0,0.03);
        border-radius: 12px;
        display: inline-flex;
    }

    .search-wrapper-hitech {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 50px !important;
        padding: 4px 4px 4px 0.5rem;
        display: flex;
        align-items: center;
        height: 50px;
        transition: all 0.3s ease;
        min-width: 300px;
    }
    .search-wrapper-hitech:focus-within {
        border-color: #005a5a;
        box-shadow: 0 0 0 4px rgba(0, 90, 90, 0.05);
    }
    .search-wrapper-hitech .form-control {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        height: 100% !important;
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/js/app/document-request-index.js'
  ])
@endsection

@section('content')
<div class="row g-6">
  <!-- Hero Banner -->
  <div class="col-lg-12">
    <x-hero-banner 
      title="Document Management" 
      subtitle="Process and manage employee document requests efficiently"
      icon="bx-file"
      gradient="info"
      quote="Structured documentation is the backbone of organizational clarity."
    />
  </div>

  <!-- Stats Cards -->
  <div class="row g-6 mb-6 px-4">
    <x-stat-card 
      title="Total Requests" 
      value="{{ $totalRequests ?? 0 }}" 
      icon="bx-folder-open" 
      color="info"
      animation-delay="0.1s"
    />
    
    <x-stat-card 
      title="Pending Review" 
      value="{{ $pendingRequests ?? 0 }}" 
      icon="bx-time" 
      color="warning"
      animation-delay="0.2s"
    />
    
    <x-stat-card 
      title="Approved" 
      value="{{ $approvedRequests ?? 0 }}" 
      icon="bx-check-double" 
      color="success"
      animation-delay="0.3s"
    />
    
    <x-stat-card 
      title="Rejected" 
      value="{{ $rejectedRequests ?? 0 }}" 
      icon="bx-x-circle" 
      color="danger"
      animation-delay="0.4s"
    />
  </div>

  <!-- Table Section -->
  <div class="col-12">
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
      <div class="hitech-card-header border-bottom py-4 px-5">
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-4">
           <div>
               <h5 class="title mb-1">Document Request Repository</h5>
               <p class="text-muted small mb-0">Review and action employee certification requests</p>
           </div>
           <div class="d-flex gap-3 align-items-center">
              <div class="search-wrapper-hitech">
                 <i class="bx bx-search ms-2 text-muted fs-4"></i>
                 <input type="text" id="customSearchInput" class="form-control" placeholder="Search by name, ID or type...">
              </div>
           </div>
        </div>
      </div>

      <!-- Integrated Toolbar -->
      <div class="bg-light-soft p-4 px-5 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="hitech-segmented-control">
          <button class="status-toggle-btn active" data-status="All">All Requests</button>
          <button class="status-toggle-btn" data-status="Pending">Pending</button>
          <button class="status-toggle-btn" data-status="Approved">Approved</button>
          <button class="status-toggle-btn" data-status="Rejected">Rejected</button>
        </div>
        <input type="hidden" id="statusFilter" value="All">
      </div>

      <div class="card-datatable table-responsive">
        <table class="datatables-documentRequests table">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Document Type</th>
              <th>Status</th>
              <th>Request Date</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content hitech-card border-0 shadow-lg" style="border-radius: 16px;">
      <div class="modal-header border-0 pb-0 pt-4 px-4">
        <div class="d-flex align-items-center gap-3">
            <div class="hitech-icon-container bg-label-info" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px;">
                <i class="bx bx-file text-info fs-3"></i>
            </div>
            <div>
                <h5 class="modal-title fw-bold text-heading mb-0">Review Request</h5>
                <small class="text-muted" id="employeeName"></small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-4 px-4">
        <!-- Request Summary Area -->
        <div class="bg-label-info p-3 rounded-4 mb-4" style="background: rgba(0, 191, 255, 0.05) !important;">
            <div class="row g-2">
                <div class="col-6">
                    <small class="text-muted d-block mb-1">Document Type</small>
                    <span class="fw-bold text-heading" id="docTypeName"></span>
                </div>
                <div class="col-6 text-end">
                    <small class="text-muted d-block mb-1">Employee Remarks</small>
                    <p class="text-muted small mb-0" id="userRemarks"></p>
                </div>
            </div>
        </div>

        <form id="actionForm">
          <input type="hidden" id="requestId" name="id">
          <div class="mb-4 text-center">
            <label class="form-label d-block text-start mb-3 fw-bold">Outcome Decision</label>
            <div class="d-flex gap-2">
                <input type="radio" class="btn-check" name="status" id="statusApproved" value="Approved">
                <label class="btn btn-outline-success flex-grow-1 py-2" for="statusApproved">
                    <i class="bx bx-check-circle me-1"></i> Approve
                </label>

                <input type="radio" class="btn-check" name="status" id="statusPending" value="Pending">
                <label class="btn btn-outline-warning flex-grow-1 py-2" for="statusPending">
                    <i class="bx bx-time me-1"></i> Pending
                </label>

                <input type="radio" class="btn-check" name="status" id="statusRejected" value="Rejected">
                <label class="btn btn-outline-danger flex-grow-1 py-2" for="statusRejected">
                    <i class="bx bx-x-circle me-1"></i> Reject
                </label>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label mb-2 fw-bold">Administrative Remarks</label>
            <textarea class="form-control" name="admin_remarks" id="adminRemarks" rows="4" placeholder="Provide context for this decision..." style="background: #f8fafc; border: 1px solid #e2e8f0;"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 pt-0 pb-4 px-4 justify-content-center">
        <button type="button" class="btn btn-label-secondary px-5" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary px-5 shadow-sm" onclick="submitAction()">Save Decision</button>
      </div>
    </div>
  </div>
</div>
@endsection
