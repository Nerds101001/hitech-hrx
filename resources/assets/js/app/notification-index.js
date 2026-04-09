'use strict';

$(function () {

// ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  var offCanvasForm = $('#offcanvasCreateNotification');


//Set click event for notification send button

  $(document).on('click', '.data-submit', function () {
    console.log('click');

  });

  const addNotificationForm = document.getElementById('createNotificationForm');

  const fv = FormValidation.formValidation(addNotificationForm, {
    fields: {
      title: {
        validators: {
          notEmpty: {
            message: 'The title is required'
          }
        }
      },
      message: {
        validators: {
          notEmpty: {
            message: 'The message is required'
          }
        }
      }
    },
    plugins: {
      trigger: new FormValidation.plugins.Trigger(),
      bootstrap5: new FormValidation.plugins.Bootstrap5({
        // Use this for enabling/changing valid/invalid class
        eleValidClass: '',
        rowSelector: function (field, ele) {
          // field is the field name & ele is the field element
          return '.mb-6';
        }
      }),
      submitButton: new FormValidation.plugins.SubmitButton(),
      // Submit the form when all fields are valid
      // defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
      autoFocus: new FormValidation.plugins.AutoFocus()
    }
  }).on('core.form.valid', function () {
    // adding or updating user when form successfully validate
    $.ajax({
      data: $('#createNotificationForm').serialize(),
      url: `${baseUrl}notifications/createAjax`,
      type: 'POST',
      success: function (response) {
        console.log('Save Notification Response: ' + JSON.stringify((response)))
        if (response.statusCode === 200) {
          offCanvasForm.offcanvas('hide');
          // sweetalert
          Swal.fire({
            icon: 'success',
            title: `Successfully ${response.data}!`,
            text: `User ${response.data} Successfully.`,
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });

          // reload the page after adding or updating user delay 1 second
          setTimeout(() => {
            location.reload();
          }, 1000);
        }
      },
      error: function (err) {
        var responseJson = JSON.parse(err.responseText);
        console.log('Error Response: ' + JSON.stringify((responseJson)))
        if (err.status === 400) {
          Swal.fire({
            title: 'Unable to create Notification',
            text: `${responseJson.data}`,
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });
        } else {
          Swal.fire({
            title: 'Unable to create Notification',
            text: 'Please try again',
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });
        }
      }
    });
  });

// clearing form data when offcanvas hidden
  offCanvasForm.on('hidden.bs.offcanvas', function () {
    fv.resetForm(true);
  });


  // Delete Record
  window.deleteNotification = function (id) {

    var dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

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
          url: `${baseUrl}notifications/deleteAjax/${id}`,
          success: function () {
            // success sweetalert
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The notification has been deleted!',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            });
            setTimeout(() => {
              location.reload();
            }, 1000);
          },
          error: function (error) {
            console.log(error);
          }
        });
      }
    });
  }

  $('#notificationFor').on('change', function () {
    console.log(this.value);
    if (this.value === 'role') {
      loadRoles();
      $('#roleSelect').show();
      $('#userSelect').hide();
    } else if (this.value === 'user') {
      loadUsers();
      $('#roleSelect').hide();
      $('#userSelect').show();
    } else {
      $('#roleSelect').hide();
      $('#userSelect').hide();
    }
  });

  function loadRoles() {
    //Set Roles in select box
    getRoles().then(function (roles) {
      if (roles.length > 0) {
        $('#role').empty();
        $('#role').append('<option value="">Select Role</option>');
        roles.forEach(function (role) {
          const formattedRole = role.name
            .replace(/[_-]/g, ' ')
            .toLowerCase()
            .split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
          $('#role').append(`<option value="${role.name}">${formattedRole}</option>`);
        });
      }
    });
  }


  function loadUsers() {
    //Set Users in select box
    getUsers().then(function (users) {
      if (users) {
        $('#user').empty();
        $('#user').append('<option value="">Select User</option>');
        users.forEach(function (user) {
          $('#user').append(`<option value="${user.id}">${user.first_name} ${user.last_name}</option>`);
        });
      }
    });
  }

});
