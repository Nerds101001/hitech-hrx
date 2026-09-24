/**
 * Add new role Modal JS
 */

'use strict';

document.addEventListener('DOMContentLoaded', function (e) {
  var roleAdd = document.querySelector('.add-new-role');

  var offCanvasForm = $('#addRoleForm');
  var addRoleModel = $('#addOrUpdateRoleModal');

  // Persona template: purely informational here — the create endpoint
  // resolves the chosen preset into permissions server-side. Fine-grained
  // ticking happens on the dedicated Role Permissions page right after.
  $(document).on('change', '#rolePreset', function() {
    var selected = this.options[this.selectedIndex];
    var hint = $('#rolePresetHint');
    hint.text(this.value ? (selected.getAttribute('title') || '') : '');
  });

  roleAdd.onclick = function () {
    $('#name').val('');
    $('#description').val('');
    $('#rolePreset').val('');
    $('#rolePresetHint').text('');
  };

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
          // close the modal
          addRoleModel.modal('hide');

          // reset form
          offCanvasForm.trigger('reset');

          showSuccessToast('Role created — configure its access next');

          var newRoleId = status && status.data && status.data.id;
          setTimeout(() => {
            window.location.href = newRoleId ? `${baseUrl}roles/${newRoleId}/permissions` : location.href;
          }, 800);
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

});
