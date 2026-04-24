/**
 * Page User List
 */

'use strict';

// Datatable (jquery)
$(function () {
  // Variable declaration for directory table
  var dt_user_table = $('#directoryTable');
  var employeeView = baseUrl + 'employees/view/';

  var statusObj = {
    inactive: { title: 'Inactive', class: 'bg-label-warning' },
    pending: { title: 'Pending', class: 'bg-label-warning' },
    active: { title: 'Active', class: 'bg-label-success' },
    retired: { title: 'Inactive', class: 'bg-label-secondary' },
    onboarding: { title: 'Onboarding', class: 'bg-label-info' },
    onboarding_submitted: { title: 'Review Required', class: 'bg-label-warning' },
    relieved: { title: 'Relieved', class: 'bg-label-danger' },
    terminated: { title: 'Terminated', class: 'bg-label-danger' },
    probation: { title: 'Probation', class: 'bg-label-primary' },
    resigned: { title: 'Resigned', class: 'bg-label-danger' },
    suspended: { title: 'Suspended', class: 'bg-label-danger' },
    default: { title: 'Unknown', class: 'bg-label-secondary' }
  };

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Redraw datatable on filter change
  $('#roleFilter, #teamFilter, #designationFilter, #statusFilter').on('change', function () {
    if (window.dt_user) window.dt_user.ajax.reload();
  });

  //Initialize select2
  $('#roleFilter, #teamFilter, #designationFilter, #statusFilter').select2({});

  // Users datatable
  if (dt_user_table.length) {
    window.dt_user = dt_user_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'employees/indexAjax',
        type: 'POST',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: function (d) {
          d.roleFilter = $('#roleFilter').val();
          d.teamFilter = $('#teamFilter').val();
          d.designationFilter = $('#designationFilter').val();
          d.statusFilter = $('#statusFilter').val();
        }
      },
      columns: [
        { data: 'name' },
        { data: 'code' },
        { data: 'team' },
        { data: 'designation' },
        { data: 'status' },
        { data: 'joined' },
        { data: '' }
      ],
      columnDefs: [
        {
          // User full name
          targets: 0,
          render: function (data, type, full, meta) {
            var $name = full['name'] || 'Unknown';
            var $email = full['email'] || '';
            var initials = $name.match(/\b\w/g) || [];
            var $initials = ((initials.shift() || '') + (initials.pop() || '')).toUpperCase();
            var $output;
            if (full['profile_picture']) {
              $output = '<img src="' + full['profile_picture'] + '" alt="Avatar" class="avatar rounded-circle" />';
            } else {
              $output = '<span class="avatar-initial-hitech">' + $initials + '</span>';
            }
            return (
              '<div class="d-flex justify-content-start align-items-center user-name">' +
              '<div class="avatar-wrapper"><div class="avatar avatar-sm me-3">' + $output + '</div></div>' +
              '<div class="d-flex flex-column">' +
              '<a href="' + employeeView + full['id'] + '" class="text-heading text-truncate"><span class="fw-medium mb-0" style="font-size: 0.875rem;">' + $name + '</span></a>' +
              '<small class="text-muted" style="font-size: 0.75rem;">' + $email + '</small>' +
              '</div></div>'
            );
          }
        },
        {
          targets: 1, // Employee ID
          render: function (data, type, full, meta) {
            return '<span class="badge badge-code-hitech">' + (full['code'] || 'N/A') + '</span>';
          }
        },
        {
          targets: 2, // Department
          render: function (data, type, full, meta) {
            return '<span class="text-body">' + (full['team'] || 'N/A') + '</span>';
          }
        },
        {
          targets: 3, // Designation
          render: function (data, type, full, meta) {
            return '<span class="text-body">' + (full['designation'] || 'N/A') + '</span>';
          }
        },
        {
          targets: 4, // Status
          render: function (data, type, full, meta) {
            var $status = full['status'];
            var statusInfo = statusObj[$status] || statusObj['default'];
            var badgeClass = ($status === 'active') ? 'bg-success-light text-success' : 'bg-teal-light text-teal';
            if (['inactive', 'relieved', 'terminated'].includes($status)) badgeClass = 'bg-danger-light text-danger';
            return '<span class="badge ' + badgeClass + ' rounded-pill px-3 py-1 fw-bold">' + statusInfo.title + '</span>';
          }
        },
        {
          targets: 5, // Joined
          render: function (data, type, full, meta) {
            return '<span class="text-body">' + (full['joined'] || 'N/A') + '</span>';
          }
        },
        {
          targets: 6, // Actions
          searchable: false, orderable: false,
          render: function (data, type, full, meta) {
            var unlockBtn = '';
            if (full['is_security_locked']) {
              unlockBtn = `<a class="icon-sophisticated security-unlock" data-id="${full['id']}" href="javascript:;" title="Unlock Security"><i class="bx bx-lock-open-alt text-success"></i></a>`;
            }
            return (
              '<div class="d-flex align-items-center justify-content-center gap-2">' +
              `<a class="icon-sophisticated view" href="${employeeView + full['id']}" title="View"><i class="bx bx-show"></i></a>` +
              `<a class="icon-sophisticated edit edit-record" data-id="${full['id']}" href="javascript:;" title="Edit"><i class="bx bx-edit"></i></a>` +
              unlockBtn +
              `<a class="icon-sophisticated toggle-status-record" data-id="${full['id']}" href="javascript:;" title="Toggle Account"><i class="bx bx-lock-alt"></i></a>` +
              '</div>'
            );
          }
        }
      ],
      order: [[0, 'asc']],
      dom: 'rtip',
      responsive: false, // MANDATORY: Stop CSS column hiding
      language: {
        sLengthMenu: '_MENU_',
        paginate: {
          next: '<i class="bx bx-chevron-right bx-18px"></i>',
          previous: '<i class="bx bx-chevron-left bx-18px"></i>'
        }
      }
    });

    // Cleanup for original buttons and search
    $('#customSearchBtn').on('click', function () {
      window.dt_user.search($('#customSearchInput').val()).draw();
    });
    $('#customSearchInput').on('keyup', function (e) {
      if (e.key === 'Enter') window.dt_user.search($(this).val()).draw();
    });
    $('#customLengthMenu').on('change', function () {
      window.dt_user.page.len($(this).val()).draw();
    });
  }

  // Toggle Status Record (Lock/Unlock)
  $(document).on('click', '.toggle-status-record', function () {
    var user_id = $(this).data('id'),
      isLocked = $(this).hasClass('locked'),
      dtrModal = $('.dtr-bs-modal.show');

    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    var title = isLocked ? 'Unlock Account?' : 'Lock Account?';
    var text = isLocked ? "This will restore the user's access to the portal." : "The user will no longer be able to login until unlocked.";
    var confirmText = isLocked ? 'Yes, Unlock it!' : 'Yes, Lock it!';

    Swal.fire({
      title: title,
      text: text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: confirmText,
      customClass: {
        confirmButton: 'btn btn-primary me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        $.ajax({
          type: 'POST',
          url: `${baseUrl}employees/toggleStatus/${user_id}`,
          success: function (response) {
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: response.message || 'Account status updated successfully.',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            });
            dt_user.draw();
            // Also reload if in card view (though card view might need a separate refresh logic or just page reload)
            if (typeof window.location.reload === 'function' && !$('.datatables-users').is(':visible')) {
               window.location.reload();
            }
          },
          error: function (xhr) {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: xhr.responseJSON?.message || 'Failed to update account status.',
              customClass: {
                confirmButton: 'btn btn-danger'
              }
            });
          }
        });
      }
    });
  });
  // security unlock
  $(document).on('click', '.security-unlock', function () {
    var user_id = $(this).data('id');

    Swal.fire({
      title: 'Remove Security Lock?',
      text: "This will reset login attempts and unlock the user immediately.",
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'Yes, Unlock!',
      customClass: {
        confirmButton: 'btn btn-success me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        $.ajax({
          type: 'POST',
          url: `${baseUrl}employees/unlockSecurityAjax`,
          data: {
            id: user_id,
            _token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function (response) {
            Swal.fire({
              icon: 'success',
              title: 'Unlocked!',
              text: 'Security lock removed successfully.',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            });
            if (window.dt_user) window.dt_user.draw();
            else window.location.reload();
          }
        });
      }
    });
  });

  // edit record
  $(document).on('click', '.edit-record', function () {
    var user_id = $(this).data('id'),
      dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // changing the title of offcanvas
    $('#offcanvasAddUserLabel').html('Edit User');

    // get data
    $.get(`${baseUrl}account/editUserAjax/${user_id}`, function (data) {
      console.log(data);
      $('#userId').val(data.id);
      $('#firstName').val(data.firstName);
      $('#lastName').val(data.lastName);
      $('#email').val(data.email);
      $('#phone').val(data.phone);
      $('#role').val(data.role);
    });
  });

  // changing the title
  $('.add-new').on('click', function () {
    $('#userId').val(''); //reseting input field
    $('#offcanvasAddUserLabel').html('Add User');
    // loadRoles();
  });

  // Filter form control to default size
  // ? setTimeout used for multilingual table initialization
  setTimeout(() => {
    $('.dataTables_filter .form-control').removeClass('form-control-sm');
    $('.dataTables_length .form-select').removeClass('form-select-sm');
  }, 300);

});
