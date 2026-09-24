/**
 * Add new role Modal JS
 */

'use strict';

document.addEventListener('DOMContentLoaded', function (e) {
  var roleAdd = document.querySelector('.add-new-role'),
    roleEdit = document.querySelectorAll('.edit-role'),
    roleTitle = document.querySelector('.role-title');

  var offCanvasForm = $('#addRoleForm');
  var addRoleModel = $('#addOrUpdateRoleModal');

  // Select All Module Row
  $(document).on('change', '.row-select-all', function() {
    var module = $(this).data('module');
    $(`.${module}-checkbox`).prop('checked', $(this).is(':checked'));
  });

  // Global Select All
  $(document).on('change', '#selectAll', function() {
    $('.permission-checkbox, .row-select-all').prop('checked', $(this).is(':checked'));
  });

  // Re-sync the row-select-all and global-select-all checkboxes after the
  // permission set is changed programmatically (preset pick / edit load).
  function refreshSelectAllStates() {
    $('.row-select-all').each(function() {
      var module = $(this).data('module');
      var checkboxes = $(`.${module}-checkbox`);
      var allChecked = checkboxes.length > 0 && checkboxes.not(':checked').length === 0;
      $(this).prop('checked', allChecked);
    });
    var total = $('.permission-checkbox').length;
    var checked = $('.permission-checkbox:checked').length;
    $('#selectAll').prop('checked', total > 0 && total === checked);
  }

  // Persona template: pick one → auto-tick exactly its permissions.
  $(document).on('change', '#rolePreset', function() {
    var selected = this.options[this.selectedIndex];
    var hint = $('#rolePresetHint');
    if (!this.value) { hint.text(''); return; }

    var perms = (selected.getAttribute('data-permissions') || '').split(',').filter(Boolean);
    // Start clean so the template is exactly what gets applied, then tweakable.
    $('.permission-checkbox, .row-select-all, #selectAll').prop('checked', false);
    perms.forEach(function(p) {
      $(`.permission-checkbox[value="${p}"]`).prop('checked', true);
    });
    refreshSelectAllStates();
    hint.text((selected.getAttribute('title') || '') + '  (' + perms.length + ' permissions applied — adjust below if needed)');
  });

  roleAdd.onclick = function () {
    roleTitle.innerHTML = 'Add New Role';
    $('#id').val('');
    $('#name').val('');
    $('#rolePreset').val('');
    $('#rolePresetHint').text('');
    $('#isMultiCheckInEnabled').prop('checked', false);
    $('#mobileAppAccess').prop('checked', false);
    $('#webAppAccess').prop('checked', false);
    $('#locationActivityTracking').prop('checked', false);
    $('.permission-checkbox, .row-select-all, #selectAll').prop('checked', false);
  };

  document.querySelectorAll('.edit').forEach(function (element) {
    element.addEventListener('click', function () {
      var role = JSON.parse(element.getAttribute('data-value'));

      $('#id').val(role['id']);
      $('#name').val(role['name']);
      $('#rolePreset').val('');
      $('#rolePresetHint').text('');
      $('#isMultiCheckInEnabled').prop('checked', role['is_multiple_check_in_enabled']);
      $('#mobileAppAccess').prop('checked', role['is_mobile_app_access_enabled']);
      $('#webAppAccess').prop('checked', role['is_web_access_enabled']);
      $('#locationActivityTracking').prop('checked', role['is_location_activity_tracking_enabled']);
      $('.role-title').text('Update Role');

      // Set Permissions
      $('.permission-checkbox, .row-select-all, #selectAll').prop('checked', false);
      if (role.permissions) {
          role.permissions.forEach(function(permission) {
              // Try to find checkbox by value
              $(`.permission-checkbox[value="${permission.name}"]`).prop('checked', true);
          });
          
          // Update row-select-all states
          $('.row-select-all').each(function() {
              var module = $(this).data('module');
              var checkboxes = $(`.${module}-checkbox`);
              var allChecked = checkboxes.length > 0 && checkboxes.not(':checked').length === 0;
              $(this).prop('checked', allChecked);
          });

          // Update global select all
          var totalCheckboxes = $('.permission-checkbox').length;
          var checkedCheckboxes = $('.permission-checkbox:checked').length;
          $('#selectAll').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
      }

      addRoleModel.modal('show');
    });
  });

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  (function () {
    // add role form validation
    FormValidation.formValidation(document.getElementById('addRoleForm'), {
      fields: {
        name: {
          validators: {
            notEmpty: {
              message: 'Please enter role name'
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          // eleInvalidClass: '',
          eleValidClass: '',
          rowSelector: '.col-12'
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        // Submit the form when all fields are valid
        // defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    }).on('core.form.valid', function () {
      $.ajax({
        data: $('#addRoleForm').serialize(),
        url: `${baseUrl}roles/addOrUpdateAjax`,
        type: 'POST',
        success: function (status) {
          console.log('Response: ' + JSON.stringify(status));

          // close the modal
          addRoleModel.modal('hide');

          // reset form
          offCanvasForm.trigger('reset');

          showSuccessToast('Role added/updated successfully');

          setTimeout(() => {
            location.reload();
          }, 1000);
        },
        error: function (err) {
          console.log(err);

          //Get Response
          var response = err.responseJSON;
          Swal.fire({
            title: 'Failed',
            text: response && response.data ? response.data : 'Forbidden (403) or Session Expired. Please refresh.',
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });
        }
      });
    });
  })();

  window.deleteRole = function (id) {
    // sweetalert for confirmation of delete
    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: {
        confirmButton: 'btn btn-primary me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        // delete the data
        $.ajax({
          type: 'DELETE',
          url: `${baseUrl}roles/deleteAjax/${id}`,
          success: function () {
            // success sweetalert
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The role has been deleted!',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            }).then(function () {
              location.reload();
            });
          },
          error: function (error) {
            console.log(error);
            // error sweetalert
            var response = error.responseJSON;
            Swal.fire({
              icon: 'error',
              title: 'Oops...',
              text: response.data,
              customClass: {
                confirmButton: 'btn btn-success'
              }
            });
          }
        });
      }
    });
  };

  // --- USER SPECIAL PERMISSIONS LOGIC ---
  var userPermModal = $('#addOrUpdateUserPermissionModal');
  var userPermForm = $('#addUserPermissionForm');

  $('.add-user-permission').on('click', function() {
      userPermForm.trigger('reset');
      $('.user-permission-checkbox, .user-row-select-all, #selectAllUser').prop('checked', false);
      userPermModal.modal('show');
  });

  // Fetch current special permissions when user is selected
  $('#user_id').on('change', function() {
      var userId = $(this).val();
      if(!userId) return;

      $.get(`${baseUrl}roles/getUserPermissionsAjax/${userId}`, function(res) {
          $('.user-permission-checkbox, .user-row-select-all, #selectAllUser').prop('checked', false);
          if(res.data) {
              res.data.forEach(function(p) {
                  $(`.user-permission-checkbox[value="${p.name}"]`).prop('checked', true);
              });
              // Update row-select-all
              $('.user-row-select-all').each(function() {
                  var mod = $(this).data('module');
                  var all = $(`.user-permission-checkbox.${mod}-checkbox:not(:checked)`).length === 0 && $(`.user-permission-checkbox.${mod}-checkbox`).length > 0;
                  $(this).prop('checked', all);
              });
          }
      });
  });

  // Edit Special Permissions from section
  $('.edit-user-perm').on('click', function() {
      var userId = $(this).data('id');
      $('#user_id').val(userId).trigger('change');
      userPermModal.modal('show');
  });

  // Select All Row (User)
  $(document).on('change', '.user-row-select-all', function() {
      var mod = $(this).data('module');
      $(`.${mod}-checkbox`).prop('checked', $(this).is(':checked'));
  });

  // Global Select All (User)
  $('#selectAllUser').on('change', function() {
      $('.user-permission-checkbox, .user-row-select-all').prop('checked', $(this).is(':checked'));
  });

  // Save User Permissions
  userPermForm.on('submit', function(e) {
      e.preventDefault();
      $.ajax({
          url: `${baseUrl}roles/syncUserPermissionsAjax`,
          type: 'POST',
          data: $(this).serialize(),
          success: function(res) {
              userPermModal.modal('hide');
              showSuccessToast(res.data);
              setTimeout(() => location.reload(), 1000);
          },
          error: function(err) {
              Swal.fire({ icon: 'error', title: 'Error', text: err.responseJSON.data });
          }
      });
  });

  // ── Preview Access ────────────────────────────────────────────────────
  function escapeHtml(str) {
    return $('<div>').text(str == null ? '' : str).html();
  }

  function renderPreviewMenu(payload) {
    $('#previewEmpty, #previewLoading').addClass('d-none');
    $('#previewResults').removeClass('d-none');
    $('#previewLabel').text(payload.label);

    var visibleTop = payload.menu.filter(function (m) { return m.visible; }).length;
    $('#previewStat').text(visibleTop + ' / ' + payload.menu.length);

    var html = '';
    payload.menu.forEach(function (item) {
      html += '<div class="pmt-item ' + (item.visible ? 'pmt-visible' : 'pmt-hidden') + '">';
      html += '  <div class="pmt-row">';
      html += '    <div class="pmt-icon"><i class="' + (item.icon || 'bx bx-square') + '"></i></div>';
      html += '    <div class="pmt-name">' + escapeHtml(item.name) + '</div>';
      html += '    <span class="pmt-badge ' + (item.visible ? 'pmt-badge-on' : 'pmt-badge-off') + '">' + (item.visible ? 'VISIBLE' : 'HIDDEN') + '</span>';
      html += '  </div>';
      if (!item.visible && item.reason) {
        html += '  <div class="pmt-reason">' + escapeHtml(item.reason) + '</div>';
      }
      if (item.submenu && item.submenu.length) {
        html += '  <div class="pmt-sub">';
        item.submenu.forEach(function (sub) {
          var subColor = sub.visible ? 'var(--hitech-primary)' : '#ced4da';
          var subStyle = sub.visible ? '' : 'color:#adb5bd;text-decoration:line-through;text-decoration-color:#e9ecef;';
          html += '    <div class="pmt-row"' + (sub.reason ? ' title="' + escapeHtml(sub.reason) + '"' : '') + '>';
          html += '      <i class="bx bx-chevron-right" style="color:' + subColor + ';font-size:14px;"></i>';
          html += '      <div class="pmt-name" style="' + subStyle + '">' + escapeHtml(sub.name) + '</div>';
          html += '    </div>';
        });
        html += '  </div>';
      }
      html += '</div>';
    });
    $('#previewMenuTree').html(html);
  }

  $(document).on('click', '#runPreview', function () {
    var val = $('#previewTarget').val();
    if (!val) return;

    var payload = val.indexOf('role:') === 0
      ? { role: val.substring(5) }
      : { user_id: val.substring(5) };

    $('#previewEmpty, #previewResults').addClass('d-none');
    $('#previewLoading').removeClass('d-none');
    $('#runPreview').prop('disabled', true);

    $.ajax({
      url: `${baseUrl}roles/previewAccessAjax`,
      type: 'POST',
      data: payload,
      success: function (res) {
        $('#runPreview').prop('disabled', false);
        if (!res || res.status !== 'success') {
          $('#previewLoading').addClass('d-none');
          $('#previewEmpty').removeClass('d-none');
          Swal.fire({ icon: 'error', title: 'Error', text: (res && res.data) || 'Could not load the preview.' });
          return;
        }
        renderPreviewMenu(res.data);
      },
      error: function (err) {
        $('#runPreview').prop('disabled', false);
        $('#previewLoading').addClass('d-none');
        $('#previewEmpty').removeClass('d-none');
        Swal.fire({ icon: 'error', title: 'Error', text: (err.responseJSON && err.responseJSON.data) || 'Could not load the preview.' });
      }
    });
  });

  // Reset to the picker each time the modal is reopened, rather than
  // showing a stale preview from whoever was looked up last time.
  $(document).on('hidden.bs.modal', '#previewAccessModal', function () {
    $('#previewTarget').val('');
    $('#previewResults').addClass('d-none');
    $('#previewLoading').addClass('d-none');
    $('#previewEmpty').removeClass('d-none');
  });
});
