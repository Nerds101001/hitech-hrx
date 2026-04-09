'use strict';
let baseUrl = $('html').attr('data-base-url') + '/';

$(function () {
  var basicInfoForm = $('#basicInfoForm');
  var workInfoForm = $('#workInfoForm');
  var profilePictureForm = $('#profilePictureForm');

  //Sales Targets
  $('#period').datepicker({
    format: 'yyyy',
    viewMode: 'years',
    minViewMode: 'years',
    autoclose: true,
    clearBtn: true,
    startDate: new Date(new Date().getFullYear(), 0, 1)
  });

  $('#incentiveType').on('change', function () {
    var value = this.value;
    if (value === 'none') {
      $('#amountDiv').hide();
      $('#percentageDiv').hide();
    } else if (value === 'fixed') {
      $('#amountDiv').show();
      $('#percentageDiv').hide();
    } else if (value === 'percentage') {
      $('#amountDiv').hide();
      $('#percentageDiv').show();
    } else {
      $('#amountDiv').hide();
      $('#percentageDiv').hide();
    }
  });

  $(document).on('click', '.edit-target', function () {
    var targetId = $(this).data('id');

    fetch(`${baseUrl}employees/getTargetByIdAjax/${targetId}`)
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        var target = data.data;
        $('#targetId').val(target.id);
        $('#period').val(target.period);
        $('#targetType').val(target.target_type).trigger('change');
        $('#targetAmount').val(target.target_amount);
        $('#incentiveAmount').val(target.incentive_amount);
        $('#incentivePercentage').val(target.incentive_percentage);
        $('#incentiveType').val(target.incentive_type).trigger('change');
      });

    console.log(targetId);
  });

  window.editAdjustment = function (adjustment) {
    console.log("Editing adjustment:", adjustment);
    $('.modal-title-hitech').text('Edit Payroll Adjustment');
    $('#adjustmentId').val(adjustment.id);
    $('#adjustmentName').val(adjustment.name);
    $('#adjustmentCode').val(adjustment.code || '');
    $('#adjustmentType').val(adjustment.type).trigger('change');
    $('#adjustmentAmount').val(adjustment.amount);
    $('#adjustmentPercentage').val(adjustment.percentage);
    $('#adjustmentNotes').val(adjustment.notes);

    if (adjustment.amount > 0) {
      $('#adjustmentCategory').val('fixed').trigger('change');
    } else {
      $('#adjustmentCategory').val('percentage').trigger('change');
    }

    const modalEl = document.getElementById('offcanvasPayrollAdjustment');
    if (modalEl) {
      $('#adjustmentSubmitBtn').text('Update Adjustment');
      var myModal = new bootstrap.Modal(modalEl);
      myModal.show();
    } else {
      console.warn('offcanvasPayrollAdjustment modal not found');
    }
  };

  const adjCategory = $('#adjustmentCategory');
  if (adjCategory.length) {
    adjCategory.on('change', function () {
      var val = $(this).val();
      console.log("Adjustment category changed to:", val);
      if (val === 'percentage') {
        $('#percentageDiv').attr('style', 'display: block !important;');
        $('#amountDiv').attr('style', 'display: none !important;');
        $('#adjustmentAmount').val('');
      } else {
        $('#amountDiv').attr('style', 'display: block !important;');
        $('#percentageDiv').attr('style', 'display: none !important;');
        $('#adjustmentPercentage').val('');
      }
    });
  }

  $('#addPayrollAdjustment').on('click', function () {
    console.log("Adding new adjustment");
    $('.modal-title-hitech').text('Add Payroll Adjustment');
    $('#adjustmentId').val('');
    $('#adjustmentName').val('');
    $('#adjustmentCode').val('');
    $('#adjustmentAmount').val('');
    $('#adjustmentPercentage').val('');
    $('#adjustmentCategory').val('fixed').trigger('change');
    $('#adjustmentNotes').val('');
    $('#adjustmentSubmitBtn').text('Add Adjustment');
  });


  //Sales Targets

  var userRole = role;

  $('#ipGroupDiv').hide();
  $('#qrGroupDiv').hide();
  $('#dynamicQrDiv').hide();
  $('#siteDiv').hide();
  $('#geofenceGroupDiv').hide();
  $('#dynamicQrDiv').hide();

  if (attendanceType !== 'open') {
    console.log('Attendance Type: ' + attendanceType);
    switch (attendanceType) {
      case 'geofence':
        $('#geofenceGroupDiv').show();
        getGeofenceGroups();
        break;
      case 'ip_address':
        $('#ipGroupDiv').show();
        getIpGroups();
        break;
      case 'qr_code':
        $('#qrGroupDiv').show();
        getQrGroups();
        break;
      case 'site':
        $('#siteDiv').show();
        getSites();
        break;
      case 'dynamic_qr':
        $('#dynamicQrDiv').show();
        getDynamicQrDevices();
        break;
      default:
        break;
    }
  }

  $('#attendanceType').on('change', function () {
    var value = this.value;
    console.log(value);

    $('#ipGroupDiv').hide();
    $('#qrGroupDiv').hide();
    $('#dynamicQrDiv').hide();
    $('#siteDiv').hide();
    $('#geofenceGroupDiv').hide();
    $('#dynamicQrDiv').hide();

    if (value === 'geofence') {
      $('#geofenceGroupDiv').show();
      getGeofenceGroups();
    } else if (value === 'ipAddress') {
      $('#ipGroupDiv').show();
      getIpGroups();
    } else if (value === 'staticqr') {
      $('#qrGroupDiv').show();
      getQrGroups();
    } else if (value == 'site') {
      $('#siteDiv').show();
      getSites();
    } else if (value == 'dynamicqr') {
      $('#dynamicQrDiv').show();
      getDynamicQrDevices();
    } else {
      $('#geofenceGroupDiv').hide();
      $('#ipGroupDiv').hide();
      $('#qrGroupDiv').hide();
      $('#siteDiv').hide();
      $('#dynamicQrDiv').hide();
    }
  });

  window.loadSelectList = async function () {
    try {
      var roleSelector = $('#role'),
        departmentSelector = $('#departmentId'),
        leavePolicySelector = $('#leavePolicyProfileId'),
        reportingToSelector = $('#reportingToId'),
        designationSelector = $('#designationId');

      // Show loading state if needed
      [roleSelector, departmentSelector, leavePolicySelector, reportingToSelector, designationSelector].forEach(s => s.prop('disabled', true));

      // Fetch all data in parallel
      const [roles, departments, profiles, reportingUsers, designations] = await Promise.all([
        getRoles(),
        getDepartments(),
        getLeavePolicyProfilesAjax(),
        getReportingToUsers(),
        getDesignations()
      ]);

      // Re-enable selectors
      [roleSelector, departmentSelector, leavePolicySelector, reportingToSelector, designationSelector].forEach(s => s.prop('disabled', false));

      // Populate Roles
      roleSelector.empty().append('<option value="">Select Role</option>');
      roles.forEach(role => {
        // Format role name to Title Case (e.g., 'hr_admin' -> 'Hr Admin')
        const formattedRole = role.name
          .replace(/[_-]/g, ' ')
          .toLowerCase()
          .split(' ')
          .map(word => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ');
        roleSelector.append(`<option value="${role.name}" ${userRole == role.name ? 'selected' : ''}>${formattedRole}</option>`);
      });

      // Populate Departments
      departmentSelector.empty().append('<option value="">Select Department</option>');
      departments.forEach(dept => {
        departmentSelector.append(`<option value="${dept.id}" ${dept.id == user.department_id ? 'selected' : ''}>${dept.name}</option>`);
      });

      // Populate Leave Policy Profiles
      leavePolicySelector.empty().append('<option value="">Select Policy</option>');
      profiles.forEach(profile => {
        leavePolicySelector.append(`<option value="${profile.id}" ${profile.id == user.leave_policy_profile_id ? 'selected' : ''}>${profile.name}</option>`);
      });

      // Populate Reporting To
      reportingToSelector.empty().append('<option value="">Select Reporting To</option>');
      reportingUsers.filter(u => u.id != user.id).forEach(u => {
        reportingToSelector.append(`<option value="${u.id}" ${u.id == user.reporting_to_id ? 'selected' : ''}>${u.first_name} ${u.last_name}</option>`);
      });

      // Populate Designations
      designationSelector.empty().append('<option value="">Select Designation</option>');
      designations.forEach(d => {
        designationSelector.append(`<option value="${d.id}" ${d.id == user.designation_id ? 'selected' : ''}>${d.name}</option>`);
      });

      // Initialize/Refresh Select2
      const s2Config = { dropdownParent: $('#offcanvasEditWorkInfo'), width: '100%' };
      roleSelector.select2(s2Config);
      departmentSelector.select2(s2Config);
      leavePolicySelector.select2(s2Config);
      reportingToSelector.select2(s2Config);
      designationSelector.select2(s2Config);

      setupWorkInfoFormValidator();
    } catch (err) {
      console.error('Error loading selects:', err);
    }
  };

  window.setupWorkInfoFormValidator = function () {
    console.log('Loading Work Info form validator');
    var workInfoForm = document.getElementById('workInfoForm');
    if (workInfoForm) {
      var fv = FormValidation.formValidation(workInfoForm, {
        fields: {
          role: {
            validators: {
              notEmpty: {
                message: 'The Role is required'
              }
            }
          },
          departmentId: {
            validators: {
              notEmpty: {
                message: 'The Department is required'
              }
            }
          },
          leavePolicyProfileId: {
            validators: {
              notEmpty: {
                message: 'The Leave Policy is required'
              }
            }
          },
          designationId: {
            validators: {
              notEmpty: {
                message: 'The Designation is required'
              }
            }
          },
          doj: {
            validators: {
              notEmpty: {
                message: 'The Joining Date is required'
              }
            }
          }
        },
        plugins: {
          trigger: new FormValidation.plugins.Trigger(),
          bootstrap5: new FormValidation.plugins.Bootstrap5({
            eleValidClass: '',
            rowSelector: '.mb-6, .col-md-6, .col-md-4, .col-12'
          }),
          submitButton: new FormValidation.plugins.SubmitButton(),
          autoFocus: new FormValidation.plugins.AutoFocus()
        }
      }).on('core.form.valid', function () {
        console.log('Form Submitted');
        workInfoForm.submit();
      });
    }

    console.log('Form validator loaded!');
  };

  // Called when "Update Details" (Basic Info) button is clicked
  // Pre-populates the modal fields from the server-rendered user object
  window.loadUserOnboardingData = function () {
    if (typeof user === 'undefined') return;

    // Populate fields that may not be server-rendered (dynamic ones)
    var dob = document.getElementById('dob') || document.querySelector('#basicInfoForm [name="dob"]');
    if (dob && user.dob) dob.value = user.dob.substring(0, 10);

    var firstName = document.querySelector('#basicInfoForm [name="firstName"]');
    if (firstName) firstName.value = user.first_name || '';

    var lastName = document.querySelector('#basicInfoForm [name="lastName"]');
    if (lastName) lastName.value = user.last_name || '';

    // Trigger form validator setup
    window.loadEditBasicInfo();
  };

  window.loadEditBasicInfo = function () {

    var basicInfoForm = document.getElementById('basicInfoForm');
    if (!basicInfoForm) return;

    $('#gender').select2({
      dropdownParent: $(basicInfoForm)
    });

    // Destroy existing instance if any
    if (window.fvBasicInfo) {
      window.fvBasicInfo.destroy();
    }

    window.fvBasicInfo = FormValidation.formValidation(basicInfoForm, {
      fields: {
        firstName: {
          validators: {
            notEmpty: {
              message: 'The First name is required'
            }
          }
        },
        lastName: {
          validators: {
            notEmpty: {
              message: 'The last name is required'
            }
          }
        },
        gender: {
          validators: {
            notEmpty: {
              message: 'Please choose gender'
            }
          }
        },
        dob: {
          validators: {
            notEmpty: {
              message: 'The Date of Birth is required'
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: '.col-md-4, .col-12, .mb-3'
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    }).on('core.form.valid', function () {
      console.log('Form Valid - Submitting');
      basicInfoForm.submit();
    });

    console.log('Form validator updated!');
  };

  window.toggleUploadForm = function (formId) {
    const form = document.getElementById(formId);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  };

  // Profile Picture Update using Delegated Events (Robust against missing elements)
  $(document).on('click', '#changeProfilePictureButton', function () {
    const profilePictureInput = document.getElementById('file');
    if (profilePictureInput) profilePictureInput.click();
  });

  $(document).on('change', '#file', function () {
    console.log('Profile Picture Changed');
    if (this.files && this.files.length > 0) {
      if (profilePictureForm) {
        $(profilePictureForm).submit();
      } else {
        const form = document.getElementById('profilePictureForm');
        if (form) form.submit();
      }
    }
  });

  const maritalStatusSelector = $('#maritalStatus');
  if (maritalStatusSelector.length) {
    const updateMarriedDiv = (val) => {
      if (val === 'married') {
        $('#marriedDiv').show();
      } else {
        $('#marriedDiv').hide();
      }
    };

    updateMarriedDiv(maritalStatusSelector.val());
    maritalStatusSelector.on('change', function () {
      updateMarriedDiv($(this).val());
    });
  }

  // Additional Management Control Logic
  window.approveOnboarding = function (userId) {
    Swal.fire({
      html: `
              <div class="text-center mb-4">
                  <div class="mx-auto bg-label-success rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                      <i class="bx bx-check-shield text-success" style="font-size: 3rem;"></i>
                  </div>
                  <h4 class="mb-2 fw-bold text-dark">Approve Onboarding?</h4>
                  <p class="text-muted small mb-0">This will move the employee to ACTIVE status.</p>
              </div>
          `,
      showCancelButton: true,
      confirmButtonText: 'Yes, Approve',
      cancelButtonText: 'Cancel',
      customClass: {
        popup: 'rounded-4 shadow-lg border-0',
        confirmButton: 'btn btn-success rounded-pill px-4 fw-bold shadow-sm',
        cancelButton: 'btn btn-light rounded-pill px-4 fw-bold ms-3'
      },
      buttonsStyling: false,
      showCloseButton: false
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(`${baseUrl}employees/onboarding/approve/${userId}`, { _token: $('meta[name="csrf-token"]').attr('content') }, function (response) {
          Swal.fire({
            html: `
                    <div class="text-center">
                        <div class="mx-auto bg-label-success rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bx bx-check text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h4 class="mb-0 fw-bold text-dark">Approved!</h4>
                    </div>
                `,
            timer: 1500,
            showConfirmButton: false,
            customClass: { popup: 'rounded-4 shadow-lg border-0' }
          });
          setTimeout(() => location.reload(), 1500);
        }).fail((xhr) => {
          console.error("Approve Error:", xhr);
          Swal.fire('Error', xhr.responseJSON?.message || 'Unable to approve onboarding', 'error');
        });
      }
    });
  };

  // Onboarding Review Flow Handler
  $(document).on('change', '#formReviewOnboarding .btn-check', function() {
    const hasRejections = $('#formReviewOnboarding .btn-check:checked').length > 0;
    if (hasRejections) {
      $('#btnApproveAndActivate').fadeOut(200, () => $('#btnSendModification').fadeIn(200));
    } else {
      $('#btnSendModification').fadeOut(200, () => $('#btnApproveAndActivate').fadeIn(200));
    }
  });

  $('#formReviewOnboarding').on('submit', function (e) {
    e.preventDefault();
    const form = this;
    const btn = $('#btnSendModification');
    const userId = user.id;

    const notes = $('#reviewNotes').val().trim();
    if (!notes) {
      Swal.fire('Input Required', 'Please provide feedback notes explaining the issues.', 'warning');
      return;
    }

    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span> Sending...');

    $.post(`${baseUrl}employees/onboarding/resubmit/${userId}`, $(form).serialize(), function (response) {
      if (response.success) {
        const modalEl = document.getElementById('modalReviewOnboarding');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        Swal.fire({
          html: `
                <div class="text-center">
                    <div class="mx-auto bg-label-success rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bx bx-check text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="mb-0 fw-bold text-dark">Modification Requested</h4>
                    <p class="text-muted small">Employee has been notified to correct the flagged sections.</p>
                </div>
            `,
          timer: 2000,
          showConfirmButton: false,
          customClass: { popup: 'rounded-4 shadow-lg border-0' }
        });
        setTimeout(() => location.reload(), 2000);
      }
    }).fail((xhr) => {
      console.error("Modification Error:", xhr);
      btn.prop('disabled', false).html('<i class="bx bx-send me-2"></i> Send Request for Correction');
      Swal.fire({
        title: 'Error',
        text: xhr.responseJSON?.message || 'Unable to send modification request',
        icon: 'error',
        customClass: { popup: 'rounded-4 shadow-lg border-0' }
      });
    });
  });

  window.viewTaskDetails = function (title, description, due, status) {
    let statusBadge = '';
    switch (status.toLowerCase()) {
      case 'new': statusBadge = '<span class="badge bg-label-info">New</span>'; break;
      case 'in progress': statusBadge = '<span class="badge bg-label-warning">In Progress</span>'; break;
      case 'completed': statusBadge = '<span class="badge bg-label-success">Completed</span>'; break;
      case 'closed': statusBadge = '<span class="badge bg-label-secondary">Closed</span>'; break;
      case 'late': statusBadge = '<span class="badge bg-label-danger">Late</span>'; break;
      default: statusBadge = `<span class="badge bg-label-primary">${status}</span>`;
    }

    Swal.fire({
      html: `
              <div class="text-start p-2">
                  <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
                      <h4 class="fw-bold mb-0" style="color:#1e293b;">${title}</h4>
                      ${statusBadge}
                  </div>
                  <div class="mb-4">
                      <span class="d-block text-muted text-uppercase fw-bold mb-2" style="font-size: 0.7rem; letter-spacing: 1px;">DESCRIPTION</span>
                      <p class="text-dark small" style="line-height:1.6;">${description || 'No description provided.'}</p>
                  </div>
                  <div class="bg-light p-3 rounded-3 d-flex align-items-center border">
                      <div class="bg-white p-2 rounded me-3 shadow-sm"><i class="bx bx-calendar text-primary fs-4"></i></div>
                      <div>
                          <span class="d-block text-muted text-uppercase fw-bold" style="font-size: 0.6rem; letter-spacing: 1px;">DUE DATE</span>
                          <span class="text-dark fw-bold small">${due}</span>
                      </div>
                  </div>
              </div>
          `,
      showConfirmButton: true,
      confirmButtonText: 'Close',
      customClass: {
        popup: 'rounded-4 shadow-lg border-0',
        confirmButton: 'btn btn-primary rounded-pill px-4 fw-bold shadow-sm'
      },
      buttonsStyling: false
    });
  };

  // Document viewing functions moved to view.blade.php for unified design logic
});
