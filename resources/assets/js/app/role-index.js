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

  roleAdd.onclick = function () {
    roleTitle.innerHTML = 'Add New Role';
    $('#id').val('');
    $('#name').val('');
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
});
