@extends('layouts/layoutMaster')

@section('title', __('Loan Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .datatables-loanRequests tr {
      transition: all 0.2s ease;
    }
    .datatables-loanRequests tr:hover {
      background-color: rgba(var(--bs-primary-rgb), 0.05) !important;
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('content')
<div class="row g-6 px-4">
  <!-- Hero Banner -->
  <div class="col-lg-12">
    <x-hero-banner 
      title="Loan Management" 
      subtitle="Manage employee loan requests, approvals, and repayment tracking"
      icon="bx-money"
      gradient="success"
    />
  </div>

  <!-- Stats Cards -->
  <div class="col-12 mt-4">
    <div class="row g-4">
      <x-stat-card 
        title="Total Loan Amount" 
        value="₹{{ number_format($totalLoanAmount ?? 0, 2) }}" 
        icon="bx-wallet" 
        color="primary"
        animation-delay="0.1s"
      />
      
      <x-stat-card 
        title="Pending Requests" 
        value="{{ $pendingLoans ?? 0 }}" 
        icon="bx-time" 
        color="warning"
        animation-delay="0.2s"
      />
      
      <x-stat-card 
        title="Approved Loans" 
        value="{{ $approvedLoans ?? 0 }}" 
        icon="bx-check-circle" 
        color="success"
        animation-delay="0.3s"
      />
      
      <x-stat-card 
        title="Rejected Loans" 
        value="{{ $rejectedLoans ?? 0 }}" 
        icon="bx-trending-down" 
        color="danger"
        animation-delay="0.4s"
      />
    </div>
  </div>

  <!-- Table -->
  <div class="col-12 mt-6">
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
      <div class="hitech-card-header border-bottom">
        <h5 class="title mb-0">Loan Applications History</h5>
      </div>
      <div class="card-datatable table-responsive p-0">
        <table class="datatables-loanRequests table table-hover border-top mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Employee</th>
              <th>Requested</th>
              <th>Approved</th>
              <th>Status</th>
              <th>Applied On</th>
              <th>Actions</th>
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
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3"><i class="bx bx-money fs-3"></i></div>
            <h5 class="modal-title modal-title-hitech">Process Loan Action</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form id="actionForm">
          <input type="hidden" id="requestId" name="id">
          <div class="mb-4">
            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Action Status</label>
            <select class="form-select hitech-input bg-light border-0 rounded-3" name="status" id="requestStatus">
              <option value="Pending">Pending</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Final Approved Amount</label>
            <div class="input-group">
                <span class="input-group-text bg-teal-soft border-0 text-teal fw-bold">₹</span>
                <input type="number" step="0.01" class="form-control hitech-input bg-light border-0 rounded-end" name="approved_amount" id="approvedAmount">
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-bold small text-uppercase letter-spacing-1">Administrative Remarks</label>
            <textarea class="form-control hitech-input bg-light border-0 rounded-3" name="admin_remarks" id="adminRemarks" rows="3" placeholder="Enter reason or notes..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-label-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-hitech px-5 shadow-sm" onclick="submitAction()">Update Request</button>
      </div>
    </div>
  </div>
</div>

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = $('.datatables-loanRequests').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('loan.getListAjax') }}",
        columns: [
            { data: 'id' },
            { data: 'user' },
            { 
                data: 'amount',
                render: function(data) { return data ? '<span class="fw-semibold text-heading">₹' + data.toLocaleString() + '</span>' : '₹0'; }
            },
            { 
                data: 'approved_amount',
                render: function(data) { return data ? '<span class="text-success fw-bold">₹' + data.toLocaleString() + '</span>' : '<span class="text-muted">-</span>'; }
            },
            { 
                data: 'status',
                render: function(data) {
                    let badgeClass = 'bg-label-primary';
                    if (data === 'Approved') badgeClass = 'bg-label-success';
                    if (data === 'Rejected') badgeClass = 'bg-label-danger';
                    if (data === 'Pending') badgeClass = 'bg-label-warning';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            { 
                data: 'created_at',
                render: function(data) {
                    return '<small class="text-muted">' + data + '</small>';
                }
            },
            {
                data: null,
                render: function(data) {
                    return `<button class="btn btn-sm btn-icon btn-hitech" onclick="openActionModal(${data.id}, '${data.status}', '${data.amount}', '${data.admin_remarks || ''}')"><i class="bx bx-edit-alt"></i></button>`;
                }
            }
        ],
        order: [[0, 'desc']],
        dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        buttons: [],
        language: {
            paginate: {
                next: '<i class="bx bx-chevron-right"></i>',
                previous: '<i class="bx bx-chevron-left"></i>'
            }
        }
    });
});

function openActionModal(id, status, amount, remarks) {
    $('#requestId').val(id);
    $('#requestStatus').val(status);
    $('#approvedAmount').val(amount);
    $('#adminRemarks').val(remarks);
    $('#actionModal').modal('show');
}

function submitAction() {
    const formData = $('#actionForm').serialize();
    $.ajax({
        url: "{{ route('loan.actionAjax') }}",
        method: "POST",
        data: formData + "&_token={{ csrf_token() }}",
        success: function(response) {
            Swal.fire({
                icon: 'success',
                title: 'Request Updated',
                text: response.message,
                customClass: { confirmButton: 'btn btn-hitech' }
            });
            $('#actionModal').modal('hide');
            $('.datatables-loanRequests').DataTable().ajax.reload();
        },
        error: function(xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Action Failed',
                text: 'Something went wrong while processing the request',
                customClass: { confirmButton: 'btn btn-hitech' }
            });
        }
    });
}
</script>
@endsection
@endsection
