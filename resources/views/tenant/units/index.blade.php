@extends('layouts/layoutMaster')

@section('title', __('Unit Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .policy-row { 
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px; 
        margin-bottom: 20px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .policy-row:hover { 
        background: #fff; 
        border-color: #008080; 
        box-shadow: 0 4px 12px rgba(0, 128, 128, 0.05); 
    }
    .policy-row .policy-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; }
    .policy-row .policy-name { font-weight: 700; font-size: 1.1rem; color: #1e293b; }
    .policy-row .policy-code { font-size: 0.85rem; color: #008080; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
    
    .policy-fields { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 15px; }
    .policy-field { flex: 1 1 200px; }
    .policy-field label { font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 8px; font-weight: 600; }
    


    .tenure-tiers-container { 
        margin-top: 25px; 
        background: #f1f5f9; 
        border-radius: 10px; 
        padding: 20px; 
        border: 1px solid #e2e8f0; 
    }
    .tier-row { 
        display: flex; 
        gap: 12px; 
        align-items: flex-end; 
        margin-bottom: 12px; 
        padding: 12px;
        background: #fff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .form-check-input:checked { background-color: #008080 !important; border-color: #008080 !important; }

    .badge-teal { background: rgba(0, 128, 128, 0.12); color: #008080; border: 1px solid rgba(0, 128, 128, 0.2); font-weight: 700; padding: 4px 10px; border-radius: 4px; font-size: 0.65rem; text-transform: uppercase; }

    .badge-sat { background: rgba(0, 128, 128, 0.1); color: #005a5a; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; border: 1px solid rgba(0, 128, 128, 0.2); font-weight: 700; }
    .policy-saving .bx { animation: spin 0.7s linear infinite; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
  ])
@endsection

@section('page-script')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const dt_table = $('.datatables-units');
      let dt_units;

      if (dt_table.length) {
        dt_units = dt_table.DataTable({
          processing: true,
          serverSide: true,
          ajax: {
            url: "{{ route('units.indexAjax') }}",
            data: function (d) {
              d.searchTerm = $('#customSearchInput').val();
              d.statusFilter = $('input[name="statusFilter"]:checked').val();
            }
          },
          columns: [
            { data: 'id', className: 'text-center' },
            { 
              data: 'name',
              render: function(data) {
                return `<span class="fw-bold text-dark">${data}</span>`;
              }
            },
            { data: 'address' },
            { data: 'status' },
            { data: 'radius', render: function(data){ return data ? data + 'm' : 'Default'; } },
            { data: 'actions', className: 'text-center', orderable: false, searchable: false }
          ],
          order: [[0, 'asc']],
          dom: 'rt<"d-flex justify-content-between align-items-center mx-3 mt-4 mb-2" <"small text-muted" i> <"pagination-wrapper" p>>',
          buttons: [
            {
              extend: 'excel',
              className: 'd-none',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            },
            {
              extend: 'csv',
              className: 'd-none',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
            },
            {
              extend: 'pdf',
              className: 'd-none',
              exportOptions: { columns: [0, 1, 2, 3, 4] }
            }
          ]
        });
      }

      // Add New — Reset Form
      $('.add-new-unit').on('click', function() {
        $('#unit_id').val('');
        $('#addNewUnitForm')[0].reset();
        $('#modalAddUnitLabel').html('Add Unit');
        $('#auto-checkout-time-group').hide();
      });

      // Custom Filters & Search
      $('#customSearchBtn').on('click', function () { dt_units.draw(); });
      $('#customSearchInput').on('keyup', function (e) { if (e.key === 'Enter') dt_units.draw(); });
      $('input[name="statusFilter"]').on('change', function () { dt_units.draw(); });
      $('#customLengthMenu').on('change', function () { dt_units.page.len($(this).val()).draw(); });
      
      $('#btnExportUnits').on('click', function () {
        dt_units.button('.buttons-excel').trigger();
      });

      // Toggle Auto Check-out Time visibility
      $('#auto-checkout').on('change', function() {
        $(this).is(':checked') ? $('#auto-checkout-time-group').slideDown() : $('#auto-checkout-time-group').slideUp();
      });

      // Unit Add/Edit form submission
      const form = $('#addNewUnitForm');
      form.on('submit', function(e) {
        e.preventDefault();
        $.ajax({
          url: "{{ route('units.addOrUpdateAjax') }}",
          method: 'POST',
          data: form.serialize(),
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
          success: function(response) {
            if (response.success) {
              $('#modalAddUnit').modal('hide');
              dt_units.ajax.reload();
              Swal.fire({ icon: 'success', title: 'Success', text: response.message, customClass: { confirmButton: 'btn btn-primary' } });
            }
          },
          error: function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Something went wrong', customClass: { confirmButton: 'btn btn-primary' } });
          }
        });
      });

      const modalAddUnitEl = document.getElementById('modalAddUnit');
      const modalAddUnit = new bootstrap.Modal(modalAddUnitEl);

      // Edit record
      window.editRecord = function(id) {
        $.ajax({
          url: "{{ url('units/getByIdAjax') }}/" + id, method: 'GET',
          success: function(response) {
            if (response.success) {
              const data = response.data;
              $('#unit_id').val(data.id);
              $('#unit-name').val(data.name);
              $('#unit-address').val(data.address);
              $('#multiple-checkin').prop('checked', data.is_multiple_check_in_enabled == 1);
              $('#auto-checkout').prop('checked', data.is_auto_check_out_enabled == 1);
              $('#auto-checkout-time').val(data.auto_check_out_time);
              $('#biometric-verification').prop('checked', data.is_biometric_verification_enabled == 1);



              data.is_auto_check_out_enabled == 1 ? $('#auto-checkout-time-group').show() : $('#auto-checkout-time-group').hide();
              $('#modalAddUnitLabel').html('Edit Unit');
              modalAddUnit.show();
            }
          }
        });
      };

      // Delete record
      window.deleteRecord = function(id) {
        Swal.fire({
          title: 'Are you sure?', text: "You won't be able to revert this!", icon: 'warning',
          showCancelButton: true, confirmButtonText: 'Yes, delete it!',
          customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
          buttonsStyling: false
        }).then(function(result) {
          if (result.value) {
            $.ajax({
              url: "{{ url('units/deleteAjax') }}/" + id, method: 'DELETE',
              headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
              success: function(response) {
                if (response.success) {
                  dt_units.ajax.reload();
                  Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, customClass: { confirmButton: 'btn btn-primary' } });
                }
              }
            });
          }
        });
      };

      // =============================================
      // LEAVE POLICIES MODAL
      // =============================================
      let currentPolicySiteId = null;
      const policiesModal = new bootstrap.Modal(document.getElementById('unitPoliciesModal'));

      window.openPolicies = function(siteId, siteName) {
        currentPolicySiteId = siteId;
        $('#policiesModalTitle').text('Leave Policies — ' + siteName);
        $('#policiesContainer').html('<div class="text-center py-5"><i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i><p class="mt-2 text-muted">Loading policies...</p></div>');
        policiesModal.show();



        $.get("{{ url('unit-leave-policies/forUnit') }}/" + siteId, function(response) {
          if (!response.success) { $('#policiesContainer').html('<div class="alert alert-danger">Failed to load policies.</div>'); return; }
          renderPolicies(response.data);
        });
      };

      function renderPolicies(policies) {
        if (!policies.length) {
          $('#policiesContainer').html('<div class="text-center py-5 text-muted">No active leave types found.</div>');
          return;
        }

        let html = '';
        policies.forEach(function(p) {
          const isShort = p.is_short_leave;
          
          html += `
          <div class="policy-row animate__animated animate__fadeIn" data-leave-type-id="${p.leave_type_id}">
            <div class="policy-header">
              <div>
                <div class="policy-name">${p.leave_type_name} ${isShort ? '<span class="badge-teal ms-2">Short Leave</span>' : ''}</div>
                <div class="policy-code">${p.leave_type_code}</div>
              </div>
              <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                  <label class="form-label mb-0 small text-muted">Applicable</label>
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input policy-applicable" type="checkbox" id="applicable_${p.leave_type_id}" ${p.is_applicable ? 'checked' : ''}>
                  </div>
                </div>
                <button class="btn btn-sm btn-hitech save-policy-btn" data-leave-type-id="${p.leave_type_id}">
                  <i class="bx bx-save me-1"></i> Save
                </button>
              </div>
            </div>

            <div class="policy-fields" id="fields_${p.leave_type_id}" style="${p.is_applicable ? '' : 'display:none;'}">
              ${isShort ? `
                <div class="policy-field">
                  <label>Max Duration (Hours)</label>
                  <input type="number" class="form-control form-control-sm hitech-input" name="short_leave_hours" min="0.5" step="0.5" value="${p.short_leave_hours ?? ''}" placeholder="Unlimited">
                </div>
                <div class="policy-field">
                  <label>Max Requests / Month</label>
                  <input type="number" class="form-control form-control-sm hitech-input" name="short_leave_per_month" min="0" value="${p.short_leave_per_month ?? ''}" placeholder="Unlimited">
                </div>
              ` : `
                <div class="policy-field">
                  <label>Max / Month (Days)</label>
                  <input type="number" class="form-control form-control-sm hitech-input" name="max_per_month" min="0" value="${p.max_per_month ?? ''}" placeholder="Unlimited">
                </div>
                <div class="policy-field">
                  <label>Max / Year (Days)</label>
                  <input type="number" class="form-control form-control-sm hitech-input" name="max_per_year" min="0" value="${p.max_per_year ?? ''}" placeholder="Unlimited">
                </div>
                <div class="policy-field">
                  <label>Standard Max Consecutive</label>
                  <input type="number" class="form-control form-control-sm hitech-input" name="max_consecutive_days" min="1" value="${p.max_consecutive_days ?? ''}" placeholder="Unlimited">
                </div>
                <div class="policy-field">
                  <label>Service Required (Months)</label>
                  <input type="number" class="form-control form-control-sm hitech-input" name="tenure_required_months" min="0" value="${p.tenure_required_months ?? ''}" placeholder="None">
                </div>
              `}
            </div>

            ${!isShort ? `
            <div class="tenure-tiers-container" id="tiers_container_${p.leave_type_id}" style="${p.is_applicable ? '' : 'display:none;'}">
              <label class="form-label-hitech small mb-3 d-flex justify-content-between align-items-center" style="color: #008080; font-weight: 800; letter-spacing: 0.5px;">
                <span><i class="bx bx-trending-up me-1"></i> TENURE-BASED RULES (TIERED BENEFITS)</span>
                <button type="button" class="btn btn-xxs btn-hitech add-tier-btn" data-lt-id="${p.leave_type_id}">
                  <i class="bx bx-plus me-1"></i> ADD TIER
                </button>
              </label>
              <div class="tenure-tiers-list">
                ${(p.tenure_tiers || []).map((tier, idx) => renderTierRow(p.leave_type_id, tier)).join('')}
              </div>
              <div class="small text-muted mt-2" style="font-size:0.75rem; font-style: italic;">* Rules above override standard limits based on service duration.</div>
            </div>
            ` : ''}
          </div>`;
        });
        $('#policiesContainer').html(html);
      }

      function renderTierRow(ltId, tier = { months: '', consecutive: '' }) {
        return `
          <div class="tier-row animate__animated animate__fadeIn">
            <div style="flex:1">
              <label class="small text-muted fw-semibold" style="font-size:0.7rem">After Months</label>
              <input type="number" class="form-control form-control-sm hitech-input tier-months" placeholder="Months" value="${tier.months}">
            </div>
            <div style="flex:1">
              <label class="small text-muted fw-semibold" style="font-size:0.7rem">Max Consecutive Days</label>
              <input type="number" class="form-control form-control-sm hitech-input tier-consecutive" placeholder="Days" value="${tier.consecutive}">
            </div>
            <button type="button" class="btn btn-sm btn-icon btn-label-danger remove-tier-btn"><i class="bx bx-x"></i></button>
          </div>
        `;
      }

      // Add tier row
      $(document).on('click', '.add-tier-btn', function() {
        const ltId = $(this).data('lt-id');
        $(this).closest('.tenure-tiers-container').find('.tenure-tiers-list').append(renderTierRow(ltId));
      });

      // Remove tier row
      $(document).on('click', '.remove-tier-btn', function() {
        $(this).closest('.tier-row').remove();
      });

      // Toggle visibility
      $('#policiesContainer').on('change', '.policy-applicable', function() {
        const ltId = $(this).closest('.policy-row').data('leave-type-id');
        const checked = $(this).is(':checked');
        $(`#fields_${ltId}`).toggle(checked);
        $(`#tiers_container_${ltId}`).toggle(checked);
      });

      // Save Policy
      $('#policiesContainer').on('click', '.save-policy-btn', function() {
        const btn = $(this);
        const ltId = btn.data('leave-type-id');
        const row  = btn.closest('.policy-row');
        
        // Collect tiers
        const tiers = [];
        row.find('.tier-row').each(function() {
            const m = $(this).find('.tier-months').val();
            const c = $(this).find('.tier-consecutive').val();
            if (m && c) tiers.push({ months: parseInt(m), consecutive: parseInt(c) });
        });

        const payload = {
          site_id:               currentPolicySiteId,
          leave_type_id:         ltId,
          is_applicable:         row.find('.policy-applicable').is(':checked') ? 1 : 0,
          max_per_month:         row.find('[name="max_per_month"]').val() || null,
          max_per_year:          row.find('[name="max_per_year"]').val() || null,
          max_consecutive_days:  row.find('[name="max_consecutive_days"]').val() || null,
          tenure_required_months:row.find('[name="tenure_required_months"]').val() || null,
          short_leave_hours:     row.find('[name="short_leave_hours"]').val() || null,
          short_leave_per_month: row.find('[name="short_leave_per_month"]').val() || null,
          tenure_tiers:          tiers,
          _token: $('meta[name="csrf-token"]').attr('content')
        };

        btn.html('<i class="bx bx-loader-alt bx-spin me-1"></i>').prop('disabled', true);

        $.post("{{ route('unitLeavePolicies.save') }}", payload)
          .done(function(res) {
            btn.html('<i class="bx bx-check"></i>').removeClass('btn-hitech').addClass('btn-success');
            setTimeout(() => btn.html('<i class="bx bx-save me-1"></i> Save').removeClass('btn-success').addClass('btn-hitech').prop('disabled', false), 2000);
          })
          .fail(function(xhr) {
            btn.html('<i class="bx bx-save me-1"></i> Save').prop('disabled', false);
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to save.' });
          });
      });
    });
  </script>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Standardized Header --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4 py-2">
    <h3 class="fw-extrabold mb-0 text-dark">Unit Management</h3>
    <button type="button" class="btn btn-hitech-primary shadow-sm rounded-pill px-5 add-new-unit" data-bs-toggle="modal" data-bs-target="#modalAddUnit">
      <i class="bx bx-plus-circle me-1"></i>Add New Unit
    </button>
  </div>

  {{-- Standardized Filter Card --}}
  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="d-flex flex-wrap align-items-center gap-3">
          {{-- Search Section --}}
          <div class="search-wrapper-hitech w-px-350 mw-100">
            <i class="bx bx-search text-muted ms-3 fs-5"></i>
            <input type="text" class="form-control" placeholder="Search by name or address..." id="customSearchInput">
            <button class="btn-search shadow-sm" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>
              <span>Search</span>
            </button>
          </div>

          {{-- Status Filter (Segmented Control) --}}
          <div class="segmented-control-hitech shadow-sm p-1 d-flex gap-1 bg-light rounded-pill ms-lg-2">
            <input type="radio" name="statusFilter" value="All" id="status_all" checked>
            <label for="status_all" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">All Units</label>

            <input type="radio" name="statusFilter" value="Active" id="status_active">
            <label for="status_active" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">Active</label>

            <input type="radio" name="statusFilter" value="Inactive" id="status_inactive">
            <label for="status_inactive" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">Inactive</label>
          </div>

          {{-- Spacer --}}
          <div class="flex-grow-1"></div>

          {{-- Length Menu & Export --}}
          <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted fw-semibold small">Per Page:</span>
              <select class="form-select w-px-80 rounded-pill border-light shadow-none fw-bold" id="customLengthMenu">
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
              </select>
            </div>

            <button type="button" class="btn btn-hitech-primary px-4 shadow-sm" id="btnExportUnits">
              <i class="bx bx-export fs-5"></i>
              <span>EXPORT</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Standardized Table Card --}}
  <div class="px-4">
    <div class="hitech-card-white p-0 overflow-hidden">
      <div class="card-datatable table-responsive">
        <table class="datatables-units table m-0 shadow-none border-top">
          <thead>
            <tr>
              <th>ID</th>
              <th>Unit Name</th>
              <th>Address</th>
              <th>Status</th>
              <th>@lang('Radius')</th>

              <th class="text-center">Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>


<!-- Modal: Add/Edit Unit -->
<div class="modal fade" id="modalAddUnit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3">
                <i class="bx bx-building fs-3"></i>
            </div>
            <h5 class="modal-title modal-title-hitech" id="modalAddUnitLabel">Add Unit</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
            <i class="bx bx-x"></i>
        </button>
      </div>
      <form class="add-new-unit pt-0" id="addNewUnitForm" method="POST">
        <div class="modal-body modal-body-hitech">
            <input type="hidden" name="id" id="unit_id">
            <div class="mb-4">
              <label class="form-label form-label-hitech" for="unit-name">Unit Name</label>
              <input type="text" class="form-control form-control-hitech" id="unit-name" placeholder="Main Office" name="name" required />
            </div>
            <div class="mb-4">
              <label class="form-label form-label-hitech" for="unit-address">Address</label>
              <textarea class="form-control form-control-hitech" id="unit-address" name="address" rows="2"></textarea>
            </div>

            <hr class="my-6">
            <h6 class="mb-5 text-teal fw-bold">Attendance Rule Overrides</h6>

            <div class="row g-3">
              <div class="col-md-4">
                <div class="interactive-toggle-card h-100">
                  <div class="p-3 rounded-4 bg-glass-teal border-teal-subtle h-100 d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-center mb-3">
                      <div class="icon-stat-teal me-2 p-2 bg-white bg-opacity-10 rounded-3 text-white">
                        <i class="bx bx-repost fs-5"></i>
                      </div>
                      <label class="form-label form-label-hitech mb-0 text-white small fw-bold" for="multiple-checkin">MULTIPLE CHECK-INS</label>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="small text-white-50 lh-1" style="font-size: 0.7rem;">Allow >1 punch/day</div>
                      <div class="hitech-toggle-wrapper">
                        <input class="hitech-switch-input" type="checkbox" id="multiple-checkin" name="is_multiple_check_in_enabled">
                        <label class="hitech-switch-label" for="multiple-checkin"></label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="interactive-toggle-card h-100">
                  <div class="p-3 rounded-4 bg-glass-teal border-teal-subtle h-100 d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-center mb-3">
                      <div class="icon-stat-teal me-2 p-2 bg-white bg-opacity-10 rounded-3 text-white">
                        <i class="bx bx-log-out-circle fs-5"></i>
                      </div>
                      <label class="form-label form-label-hitech mb-0 text-white small fw-bold" for="auto-checkout">AUTO CHECK-OUT</label>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="small text-white-50 lh-1" style="font-size: 0.7rem;">Punch out at shift end</div>
                      <div class="hitech-toggle-wrapper">
                        <input class="hitech-switch-input" type="checkbox" id="auto-checkout" name="is_auto_check_out_enabled">
                        <label class="hitech-switch-label" for="auto-checkout"></label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="interactive-toggle-card h-100">
                  <div class="p-3 rounded-4 bg-glass-teal border-teal-subtle h-100 d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-center mb-3">
                      <div class="icon-stat-teal me-2 p-2 bg-white bg-opacity-10 rounded-3 text-white">
                        <i class="bx bx-fingerprint fs-5"></i>
                      </div>
                      <label class="form-label form-label-hitech mb-0 text-white small fw-bold" for="biometric-verification">BIOMETRICS</label>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="small text-white-50 lh-1" style="font-size: 0.7rem;">Bio match required</div>
                      <div class="hitech-toggle-wrapper">
                        <input class="hitech-switch-input" type="checkbox" id="biometric-verification" name="is_biometric_verification_enabled">
                        <label class="hitech-switch-label" for="biometric-verification"></label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-4" id="auto-checkout-time-group" style="display: none;">
              <div class="p-3 rounded-4 border border-dashed border-teal border-opacity-25 bg-teal bg-opacity-5">
                <label class="form-label form-label-hitech" for="auto-checkout-time">Specific Auto Check-out Time</label>
                <input type="time" class="form-control form-control-hitech" id="auto-checkout-time" name="auto_check_out_time" />
              </div>
            </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
            <button type="reset" class="btn btn-label-secondary px-4 h-px-45 d-flex align-items-center" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech px-5 h-px-45 d-flex align-items-center data-submit">
              <span class="submit-text">Save Unit</span>
              <i class="bx bx-check-circle ms-2 fs-5"></i>
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Unit Leave Policies -->
<div class="modal fade" id="unitPoliciesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-shield-quarter fs-3"></i></div>
          <h5 class="modal-title modal-title-hitech" id="policiesModalTitle">Leave Policies</h5>

        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
          <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body modal-body-hitech" style="max-height: 72vh; overflow-y: auto;">
        <p class="text-muted small mb-4">Configure per-leave-type rules for this unit. Leave any field blank to mean <em>Unlimited / Not required</em>. Toggle <strong>Applicable</strong> off to completely block a leave type for this unit.</p>
        <div id="policiesContainer">
          <div class="text-center py-5"><i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i></div>
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection
