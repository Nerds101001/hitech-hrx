@extends('layouts/layoutMaster')

@section('title', __('Payroll Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    :root {
      --hitech-primary: #008080;
      --hitech-secondary: #f8f9fa;
      --hitech-glass: rgba(255, 255, 255, 0.7);
    }
    
    .hitech-card {
      background: var(--hitech-glass);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 1.25rem;
      box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .hitech-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.12);
    }

    .datatables-payroll tr {
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .datatables-payroll tr:hover {
      background-color: rgba(var(--bs-primary-rgb), 0.04) !important;
      transform: scale(1.002);
    }

    .hitech-btn-main {
      background: linear-gradient(135deg, var(--bs-primary) 0%, #0056b3 100%);
      border: none;
      box-shadow: 0 4px 15px rgba(var(--bs-primary-rgb), 0.3);
    }
    
    .hitech-btn-main:hover {
      box-shadow: 0 6px 20px rgba(var(--bs-primary-rgb), 0.4);
      transform: translateY(-1px);
    }

    .modal-content-hitech {
      border-radius: 1.5rem;
      border: none;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    .modal-header-hitech {
      background: linear-gradient(to right, #f8f9fa, #ffffff);
      border-bottom: 1px solid #edf2f7;
    }

    .salary-breakdown-pill {
      background: #f1f5f9;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 0.75rem;
      color: #475569;
      display: inline-block;
      margin: 2px;
    }

    .hitech-action-icon-sm {
      width: 26px;
      height: 26px;
      border-radius: 6px;
      background: #ffffff;
      border: 1px solid #edf2f7;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .hitech-action-icon-sm:hover {
      background: #f8fafc;
      transform: translateY(-1px);
    }

    .hitech-action-icon-sm i {
      font-size: 0.9rem;
    }

    .datatables-payroll td, .datatables-payroll th {
      padding: 0.5rem 0.4rem !important;
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
  <script>
    'use strict';
    (function checkDependencies() {
      if (typeof jQuery === 'undefined' || typeof bootstrap === 'undefined' || !jQuery.fn.DataTable || !jQuery.fn.select2) {
        setTimeout(checkDependencies, 100);
        return;
      }
      
      jQuery(function($) {
        // Initialize Select2
        $('.select2').each(function() {
          $(this).select2({
            dropdownParent: $(this).parent()
          });
        });

        // Initialize DataTable for Payroll
        const dt_payroll = $('.datatables-payroll');
        if (dt_payroll.length) {
          console.log("Initializing DataTable...");
          try {
            dt_payroll.DataTable({
              processing: true,
              ajax: {
                url: "{{ route('payroll.indexAjax') }}",
                data: function(d) {
                  d.department_id = $('#filter_department').val();
                  d.site_id = $('#filter_site').val();
                  d.month = $('#filter_month').val();
                }
              },
              order: [[10, 'desc']],
              columns: [
                { data: 'id' },
                { data: 'employee' },
                { data: 'present_days' },
                { data: 'absent_days' },
                { data: 'off_days' },
                { data: 'allotted_basic' },
                { data: 'allotted_hra' },
                { data: 'allotted_other' },
                { data: 'payable_basic' },
                { data: 'payable_hra' },
                { data: 'payable_other' },
                { data: 'net_payable' },
                { data: 'actions', orderable: false, searchable: false }
              ],
              columnDefs: [
                {
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
                  targets: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
                  className: 'text-center'
                },
                {
                  targets: 1,
                  render: function(data, type, full, meta) {
                    return data;
                  }
                }
              ],
              dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>'
            });
          } catch (e) {
            console.error("DataTable init failed:", e);
          }
        }

        // Filter Event Listeners
        $('#filter_department, #filter_site, #filter_month').on('change', function() {
            $('.datatables-payroll').DataTable().ajax.reload();
        });

        // Form Submission Loader
        $(document).on('submit', '#generatePayrollForm', function() {
            $(this).find('button[type="submit"]')
                .prop('disabled', true)
                .html('<i class="bx bx-loader-alt bx-spin me-2"></i> Processing Batch...');
        });
      });
    })();

    function handleBulkApprove() {
        var selectedIds = [];
        jQuery('.dt-checkboxes:checked').each(function() {
            selectedIds.push(jQuery(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Selection Required', text: 'Please select at least one record to approve.' });
            return;
        }

        bulkApprove(selectedIds);
    }

    function downloadPayslip(id) {
       window.location.href = "{{ url('user/payroll') }}/" + id + "/download";
    }

    function viewPayslip(id) {
      jQuery.ajax({
        url: "{{ url('user/payroll') }}/" + id + "/show-ajax",
        type: 'GET',
        success: function(response) {
          if (response.success) {
            jQuery('#payslipModalContent').html(response.html);
            var payslipModal = new bootstrap.Modal(document.getElementById('payslipModal'));
            payslipModal.show();
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load payslip preview.' });
          }
        }
      });
    }

    function deletePayroll(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        customClass: { confirmButton: 'btn btn-primary me-1', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(function(result) {
        if (result.isConfirmed) {
          jQuery.ajax({
            url: "{{ url('payroll/destroyAjax') }}/" + id,
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function(response) {
              if (response.success) {
                jQuery('.datatables-payroll').DataTable().ajax.reload();
                Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message });
              }
            }
          });
        }
      });
    }

    function publishSingle(id) {
      Swal.fire({
        title: 'Publish Payslip?',
        html: 'This will make this payslip <strong>visible to the employee</strong>.<br><small class="text-muted">This action cannot be undone.</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bx bx-send me-1"></i> Yes, Publish',
        confirmButtonColor: '#16a34a',
        cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn rounded-pill px-4', cancelButton: 'btn btn-light rounded-pill px-4' }
      }).then(function(result) {
        if (!result.isConfirmed) return;
        jQuery.ajax({
          url: "{{ url('payroll/publishSingle') }}/" + id,
          type: 'POST',
          data: { _token: "{{ csrf_token() }}" },
          success: function(response) {
            if (response.success) {
              jQuery('.datatables-payroll').DataTable().ajax.reload();
              Swal.fire({ icon: 'success', title: 'Published!', text: response.message, timer: 2000, showConfirmButton: false });
            } else {
              Swal.fire('Error', response.message, 'error');
            }
          },
          error: function() { Swal.fire('Error', 'Failed to publish payslip.', 'error'); }
        });
      });
    }

    function bulkApprove(ids) {
      Swal.fire({
        title: 'Approve & Publish?',
        text: "This will make " + ids.length + " payslips visible to employees.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Yes, Publish All',
        customClass: { confirmButton: 'btn btn-primary me-1', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(function(result) {
        if (result.isConfirmed) {
          jQuery.ajax({
            url: "{{ route('payroll.bulkApprove') }}",
            type: 'POST',
            data: { _token: "{{ csrf_token() }}", ids: ids },
            success: function(response) {
              if (response.success) {
                jQuery('.datatables-payroll').DataTable().ajax.reload();
                Swal.fire({ icon: 'success', title: 'Published!', text: response.message });
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

  <!-- Filter Bar -->
  <div class="col-12 mt-4">
    <div class="hitech-card animate__animated animate__fadeIn">
      <div class="card-body p-4">
        <div class="row g-4 align-items-end">
          <div class="col-md-3">
            <label class="form-label hitech-label">DEPARTMENT</label>
            <select id="filter_department" class="form-select hitech-input select2">
              <option value="">All Departments</option>
              @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label hitech-label">UNIT / LOCATION</label>
            <select id="filter_site" class="form-select hitech-input select2">
              <option value="">All Sites</option>
              @foreach($sites as $site)
                <option value="{{ $site->id }}">{{ $site->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label hitech-label">MONTH</label>
            <select id="filter_month" class="form-select hitech-input">
              <option value="">Any Month</option>
              @for($m=1; $m<=12; $m++)
                <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
              @endfor
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100 hitech-btn-outline" onclick="window.location.reload()">
              <i class="bx bx-reset me-1"></i> Reset
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Payroll Table -->
  <div class="col-12 mt-6">
    <div class="hitech-card animate__animated animate__fadeInUp">
      <div class="hitech-card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h5 class="title mb-0">Monthly Payroll Records</h5>
        <div class="d-flex align-items-center gap-2">
            <div class="badge bg-label-info me-2">Draft Review Active</div>
            <button class="btn btn-outline-primary rounded-pill" onclick="handleBulkApprove()">
                <i class="bx bx-check-double me-1"></i> Approve & Publish
            </button>
            @if(auth()->user()->hasRole(['admin', 'hr', 'accounts']))
            <button class="btn btn-outline-success rounded-pill" data-bs-toggle="modal" data-bs-target="#importSalaryModal">
                <i class="bx bx-upload me-1"></i> Import Salaries
            </button>
            @endif
            <button class="btn btn-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
                <i class="bx bx-plus me-1"></i> Generate Payroll
            </button>
        </div>
      </div>
      <div class="card-datatable table-responsive p-0">
        <table class="datatables-payroll table table-hover border-top mb-0">
          <thead>
            <tr>
              <th rowspan="2" class="align-middle" style="width: 20px;"></th>
              <th rowspan="2" class="align-middle" style="min-width: 150px;">Employee</th>
              <th colspan="3" class="text-center bg-light border-start border-end py-1" style="width: 140px;">Attendance</th>
              <th colspan="3" class="text-center bg-label-info border-start border-end py-1">User Allotted Salary</th>
              <th colspan="3" class="text-center bg-label-primary border-start border-end py-1">Payable Salary</th>
              <th rowspan="2" class="align-middle text-center" style="width: 110px;">Net Payable</th>
              <th rowspan="2" class="align-middle" style="width: 80px;"></th>
            </tr>
            <tr class="bg-label-secondary small">
              <th class="text-center border-start" style="width: 40px;" title="Present">P</th>
              <th class="text-center" style="width: 40px;" title="Absent">A</th>
              <th class="text-center border-end" style="width: 40px;" title="Offs / Holidays">OFF</th>
              <th class="text-center" style="width: 70px;">Basic</th>
              <th class="text-center" style="width: 70px;">HRA</th>
              <th class="text-center border-end" style="width: 70px;">Other</th>
              <th class="text-center" style="width: 70px;">Basic</th>
              <th class="text-center" style="width: 70px;">HRA</th>
              <th class="text-center border-end" style="width: 70px;">Other</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>
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

  <!-- Import Salaries Modal -->
  <div class="modal fade" id="importSalaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content modal-content-hitech">
        <div class="modal-header modal-header-hitech">
          <div class="d-flex align-items-center">
              <div class="modal-icon-header me-3"><i class="bx bx-upload fs-3"></i></div>
              <div>
                  <h5 class="modal-title modal-title-hitech mb-0">Salary Breakup Import</h5>
                  <p class="text-white opacity-75 mb-0" style="font-size:0.72rem;">Upload Excel/CSV to bulk update employee salaries & breakups</p>
              </div>
          </div>
          <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body p-4">
          <form action="{{ route('payroll.salary-import.preview') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
              <label class="form-label-hitech" for="salaryFile">Select CSV/Excel File</label>
              <input type="file" id="salaryFile" name="file" class="form-control form-control-hitech" accept=".xlsx,.xls,.csv" required>
            </div>
            
            <div class="d-flex justify-content-between align-items-center pt-2">
              <a href="{{ route('payroll.salary-import.download-sample') }}" class="btn btn-outline-teal btn-sm">
                <i class="bx bx-download me-1"></i> Download Sample CSV
              </a>
              <div>
                <button type="button" class="btn btn-label-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-teal">
                  Preview Import <i class="bx bx-right-arrow-alt ms-1"></i>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

@endsection
