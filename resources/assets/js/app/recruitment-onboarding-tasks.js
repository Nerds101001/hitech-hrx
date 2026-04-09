'use strict';

$(function () {
  // --- Variable Definitions ---
  var dtTaskTable = $('.datatables-onboarding-tasks');
  var taskForm = $('#onboardingTaskForm');
  var offcanvasElement = $('#offcanvasAddEditTask');
  var offcanvas = new bootstrap.Offcanvas(offcanvasElement[0]);

  // CSRF Token Setup
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': csrfToken } // Passed from Blade
  });

  // --- Helper: Reset Form Validation & Content ---
  function resetFormValidation(formElement) {
    const form = $(formElement);
    form.find('.is-invalid').removeClass('is-invalid');
    form.find('.invalid-feedback').text('');
  }

  function resetOffcanvasForm() {
    resetFormValidation(taskForm[0]);
    taskForm[0].reset();
    $('#onboarding_task_id').val('');
    $('#taskMethod').val('POST');
    $('#isActive').prop('checked', true); // Default to active
    $('#submitTaskBtn').prop('disabled', false).text('Submit');
    taskForm.find('#taskType').val('').trigger('change');
  }

  // Reset form when offcanvas is hidden
  offcanvasElement.on('hidden.bs.offcanvas', function () {
    resetOffcanvasForm();
  });

  // --- DataTable Initialization ---
  if (dtTaskTable.length) {
    var dtTask = dtTaskTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: onboardingTasksListAjaxUrl, // Passed from Blade
        type: 'POST'
      },
      columns: [
        { data: 'id', name: 'id' },
        { data: 'title', name: 'title' },
        { data: 'description', name: 'description', orderable: false },
        { data: 'task_type_label', name: 'type' }, // <-- Added Type Column (using label from controller)
        { data: 'default_due_days', name: 'default_due_days', className: 'text-center' },
        { data: 'is_active', name: 'is_active', className: 'text-center', orderable: false, searchable: false },
        { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
      ],
      order: [[0, 'desc']], // Order by ID descending
      dom: '<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: { sLengthMenu: '_MENU_', search: '', searchPlaceholder: 'Search Tasks..' },
      buttons: [ /* Add buttons if needed */ ],
      responsive: { /* Add responsive config if needed */ }
      // Add drawCallback to update total count card (example)
      // drawCallback: function (settings) {
      //     var api = this.api();
      //     $('#totalTasksCount').text(api.page.info().recordsTotal);
      // }
    });
  }

  // --- Open Offcanvas for Create ---
  $('#addOnboardingTaskBtn').on('click', function () {
    resetOffcanvasForm();
    $('#offcanvasAddEditTaskLabel').text('Add Task Template');
    offcanvas.show();
  });

  // --- Open Offcanvas for Edit ---
  $(document).on('click', '.edit-onboarding-task', function () {
    var id = $(this).data('id');
    var editDataUrl = $(this).data('url'); // Get URL from button

    resetOffcanvasForm();

    $.ajax({
      url: editDataUrl, type: 'GET',
      beforeSend: function() { $('#offcanvasAddEditTaskLabel').text('Loading Data...'); },
      success: function (response) {
        if (response.success && response.task) {
          const taskData = response.task;
          $('#offcanvasAddEditTaskLabel').text('Edit Task Template #' + taskData.id);
          $('#onboarding_task_id').val(taskData.id);
          $('#taskMethod').val('PUT');

          $('#taskTitle').val(taskData.title);
          $('#taskDescription').val(taskData.description);
          $('#defaultDueDays').val(taskData.defaultDueDays);
          $('#isActive').prop('checked', taskData.isActive);

          $('#taskType').val(taskData.type).trigger('change');

          offcanvas.show();
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load task data.' });
          $('#offcanvasAddEditTaskLabel').text('Edit Task Template');
        }
      },
      error: function (jqXHR) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred while fetching data.' });
        $('#offcanvasAddEditTaskLabel').text('Edit Task Template');
      }
    });
  });

  // --- Form Submission (Create/Update) ---
  taskForm.on('submit', function (e) {
    e.preventDefault();
    resetFormValidation(this);

    var formData = new FormData(this);
    // Ensure isActive value is sent correctly (0 or 1) even when unchecked
    if (!formData.has('isActive')) {
      formData.set('isActive', '0'); // Set to '0' if checkbox is unchecked
    } else {
      formData.set('isActive', '1'); // Ensure it's '1' if checked
    }


    var id = $('#onboarding_task_id').val();
    var method = $('#taskMethod').val();
    var url = id ? (onboardingTasksBaseUrl + '/' + id) : onboardingTasksStoreUrl;
    var submitButton = $('#submitTaskBtn');

    if(method === 'PUT') {
      formData.append('_method', 'PUT');
    }

    submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

    $.ajax({
      url: url, type: 'POST', // Always POST for FormData + _method
      data: formData, processData: false, contentType: false,
      success: function (response) {
        if (response.success) {
          Swal.fire({ icon: 'success', title: 'Success', text: response.message, timer: 1500, showConfirmButton: false });
          offcanvas.hide();
          if (dtTask) dtTask.ajax.reload(null, false);
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Operation failed.' });
        }
      },
      error: function (jqXHR) {
        if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
          $.each(jqXHR.responseJSON.errors, function (key, value) {
            let inputName = key; // Assuming form uses camelCase like validator keys
            $('[name="' + inputName + '"]').addClass('is-invalid').siblings('.invalid-feedback').text(value[0]);
          });
          $('.is-invalid').first().focus();
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: jqXHR.responseJSON?.message || 'An error occurred.' });
        }
      },
      complete: function () { submitButton.prop('disabled', false).text('Submit'); }
    });
  });

  // --- Toggle Status Handling ---
  $(document).on('change', '.toggle-task-status', function () {
    var checkbox = $(this);
    var url = checkbox.data('url');
    var status = checkbox.is(':checked'); // true if checked (Active), false if unchecked (Inactive)
    var label = checkbox.siblings('.switch-label');

    $.ajax({
      url: url,
      type: 'PUT',
      data: {
        // No specific data needed as controller toggles based on current state
        // _token: csrfToken // Handled by ajaxSetup
      },
      success: function(response) {
        if (response.success) {
          // Update label text based on new status from response (more reliable)
          label.text(response.newStatus ? 'Active' : 'Inactive');
          // Optional: Show success toast
        } else {
          // Revert checkbox and show error
          checkbox.prop('checked', !status);
          label.text(status ? 'Inactive' : 'Active'); // Revert label
          Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to update status.' });
        }
      },
      error: function(jqXHR) {
        // Revert checkbox and show error
        checkbox.prop('checked', !status);
        label.text(status ? 'Inactive' : 'Active'); // Revert label
        Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred.' });
      }
    });
  });


  // --- Delete Handling ---
  $(document).on('click', '.delete-onboarding-task', function () {
    var button = $(this);
    var deleteUrl = button.data('url');

    Swal.fire({
      title: 'Are you sure?', text: "You won't be able to revert this!", icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Yes, delete it!',
      customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: deleteUrl, type: 'DELETE',
          success: function (response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, customClass: { confirmButton: 'btn btn-success' } });
              if (dtTask) dtTask.row(button.closest('tr')).remove().draw(false);
            } else {
              Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to delete.', customClass: { confirmButton: 'btn btn-danger' } });
            }
          },
          error: function (jqXHR) {
            Swal.fire({ icon: 'error', title: 'Error!', text: jqXHR.responseJSON?.message || 'An error occurred.', customClass: { confirmButton: 'btn btn-danger' } });
          }
        });
      }
    });
  });


}); // End document ready
