@extends('layouts/layoutMaster')

@section('title', __('Payroll Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .datatables-payroll tr {
      transition: all 0.2s ease;
    }
    .datatables-payroll tr:hover {
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

@section('page-script')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize DataTable for Payroll
      const dt_payroll = $('.datatables-payroll');
      if (dt_payroll.length) {
        dt_payroll.DataTable({
          processing: true,
          serverSide: true,
          ajax: "{{ route('payroll.indexAjax') }}",
          order: [[1, 'desc']],
          columns: [
            { data: 'id' },
            { data: 'employee' },
            { data: 'month' },
            { data: 'net_salary' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false }
          ],
          columnDefs: [
            {
              // For Checkboxes
              targets: 0,
              searchable: false,
              orderable: false,
              render: function(data, type, full, meta) {
                return '<input type="checkbox" class="form-check-input dt-checkboxes" value="' + data + '">';
              },
              checkboxes: {
                selectAllRender: '<input type="checkbox" class="form-check-input">'
              }
            },
            {
              // Employee Name with ID badge
              targets: 1,
              render: function(data, type, full, meta) {
                return '<div><span class="fw-medium text-heading">' + data + '</span><br><small class="text-muted">#' + full.id + '</small></div>';
              }
            },
            {
              // Net Salary
              targets: 3,
              render: function(data, type, full, meta) {
                return '<span class="fw-bold text-primary">₹' + parseFloat(data).toLocaleString() + '</span>';
              }
            },
            {
               // Status (Handled by Controller but kept for fallback/custom class)
               targets: 4,
               render: function(data, type, full, meta) {
                 return data; // Controller already returns HTML badge
               }
            }
          ],
          dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
          buttons: [
            {
              text: '<i class="bx bx-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Generate Payroll</span>',
              className: 'btn btn-primary me-2 rounded-pill',
              attr: {
                'data-bs-toggle': 'offcanvas',
                'data-bs-target': '#offcanvasGeneratePayroll'
              }
            },
            {
              text: '<i class="bx bx-check-double me-sm-1"></i> <span class="d-none d-sm-inline-block">Approve & Publish</span>',
              className: 'btn btn-label-primary me-2 rounded-pill',
              action: function(e, dt, node, config) {
                var selectedIds = [];
                $('.dt-checkboxes:checked').each(function() {
                  selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                  Swal.fire({ icon: 'warning', title: 'Selection Required', text: 'Please select at least one record to approve.' });
                  return;
                }

                bulkApprove(selectedIds);
              }
            },
            {
              extend: 'collection',
              className: 'btn btn-label-secondary dropdown-toggle me-2',
              text: '<i class="bx bx-export me-sm-1"></i> <span class="d-none d-sm-inline-block">Export</span>',
              buttons: [
                { extend: 'print', className: 'dropdown-item' },
                { extend: 'csv', className: 'dropdown-item' },
                { extend: 'excel', className: 'dropdown-item' },
                { extend: 'pdf', className: 'dropdown-item' },
                { extend: 'copy', className: 'dropdown-item' }
              ]
            }
          ]
        });
      }
    });

    function downloadPayslip(id) {
       window.location.href = "{{ url('user/payroll/download') }}/" + id;
    }

    function viewPayslip(id) {
      $.ajax({
        url: "{{ url('user/payroll/show-ajax') }}/" + id,
        type: 'GET',
        success: function(response) {
          if (response.success) {
            $('#payslipModalContent').html(response.html);
            var payslipModal = new bootstrap.Modal(document.getElementById('payslipModal'));
            payslipModal.show();
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load payslip preview.' });
          }
        },
        error: function() {
          Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred.' });
        }
      });
    }

    function deletePayroll(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this! This will delete the payroll record and the associated payslip.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
        customClass: {
          confirmButton: 'btn btn-primary me-1',
          cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
      }).then(function(result) {
        if (result.isConfirmed) {
          $.ajax({
            url: "{{ url('payroll/destroyAjax') }}/" + id,
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function(response) {
              if (response.success) {
                $('.datatables-payroll').DataTable().ajax.reload();
                Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, customClass: { confirmButton: 'btn btn-success' } });
              } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: response.message, customClass: { confirmButton: 'btn btn-danger' } });
              }
            }
          });
        }
      });
    }

    function bulkApprove(ids) {
      Swal.fire({
        title: 'Approve & Publish?',
        text: "This will make " + ids.length + " payslips visible to employees.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Yes, Publish All',
        customClass: {
          confirmButton: 'btn btn-primary me-1',
          cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
      }).then(function(result) {
        if (result.isConfirmed) {
          $.ajax({
            url: "{{ route('payroll.bulkApprove') }}",
            type: 'POST',
            data: {
              _token: "{{ csrf_token() }}",
              ids: ids
            },
            success: function(response) {
              if (response.success) {
                $('.datatables-payroll').DataTable().ajax.reload();
                Swal.fire({ icon: 'success', title: 'Published!', text: response.message, customClass: { confirmButton: 'btn btn-success' } });
              } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: response.message, customClass: { confirmButton: 'btn btn-danger' } });
              }
            }
          });
        }
      });
    }
  </script>
@endsection

@section('content')
<div class="row g-6 px-4">
  <!-- Hero Banner -->
  <div class="col-lg-12">
    <x-hero-banner 
      title="Payroll Management" 
      subtitle="Process salaries, management adjustments and generate premium payslips"
      icon="bx-money"
      gradient="primary"
    />
  </div>

  <!-- Stats Cards -->
  <div class="col-12 mt-4">
    <div class="row g-4">
      <x-stat-card 
        title="Pending Processing" 
        value="{{ $pendingProcessing }}" 
        icon="bx-time" 
        color="warning"
        animation-delay="0.1s"
      />
      <x-stat-card 
        title="Processed This Month" 
        value="{{ $processedThisMonth }}" 
        icon="bx-check-double" 
        color="success"
        animation-delay="0.2s"
      />
      <x-stat-card 
        title="Total Payout" 
        value="₹{{ number_format($totalPayout, 2) }}" 
        icon="bx-wallet" 
        color="info"
        animation-delay="0.3s"
      />
    </div>
  </div>

  <!-- Payroll Table -->
  <div class="col-12 mt-6">
    <div class="hitech-card animate__animated animate__fadeInUp">
      <div class="hitech-card-header border-bottom">
        <h5 class="title mb-0">Monthly Payroll Records</h5>
      </div>
      <div class="card-datatable table-responsive p-0">
        <table class="datatables-payroll table table-hover border-top mb-0">
          <thead>
            <tr>
              <th class="control d-none"></th>
              <th>Employee</th>
              <th>Month</th>
              <th>Net Salary</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>
@section('modals')
  @include('tenant.payroll.partials.generate_payroll_offcanvas')
  
  <div class="modal fade" id="payslipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content modal-content-hitech">
        <div class="modal-header modal-header-hitech">
            <div class="d-flex align-items-center">
                <div class="modal-icon-header me-3"><i class="bx bx-receipt fs-3"></i></div>
                <h5 class="modal-title modal-title-hitech">Employee Payslip Preview</h5>
            </div>
            <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body modal-body-hitech p-0" id="payslipModalContent">
          <!-- Content loaded via AJAX -->
        </div>
      </div>
    </div>
  </div>
@endsection

@endsection
