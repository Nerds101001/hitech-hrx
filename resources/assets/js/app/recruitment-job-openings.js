'use strict';

$(function () {
  // Variable definition
  var dtJobOpeningTable = $('.datatables-job-openings');
  var jobOpeningForm = $('#jobOpeningForm');
  var statusFilter = $('#statusFilter');
  var teamFilter = $('#teamFilter');
  var offcanvasElement = $('#offcanvasAddEditJobOpening');
  var offcanvas = new bootstrap.Offcanvas(offcanvasElement[0]); // Get the BS instance
  var select2 = $('.select2'); // Reusable select2 instance

  // Init Select2
  if (select2.length) {
    select2.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: $this.parent() // Required for dropdowns in offcanvas
      });
    });
  }

  // Date Picker
  const flatpickrDate = document.querySelector('#closesAt');
  if (flatpickrDate) {
    flatpickrDate.flatpickr({
      monthSelectorType: 'static',
      altInput: true, // Show readable format to user
      altFormat: 'M j, Y',
      dateFormat: 'Y-m-d', // Format sent to server
      minDate: 'today' // Prevent selecting past dates
    });
  }


  // --- Helper: Reset Form Validation & Content ---
  function resetFormValidation(formElement) {
    const form = $(formElement);
    form.find('.is-invalid').removeClass('is-invalid');
    form.find('.invalid-feedback').text('');
  }

  function resetOffcanvasForm() {
    resetFormValidation(jobOpeningForm[0]); // Clear validation
    jobOpeningForm[0].reset(); // Reset form fields
    $('#job_opening_id').val(''); // Clear hidden ID
    $('#jobOpeningMethod').val('POST'); // Default to POST
    // Reset select2 fields
    jobOpeningForm.find('select.select2').val('').trigger('change');
    // Reset date picker
    const closesAtPicker = document.querySelector('#closesAt')._flatpickr;
    if (closesAtPicker) {
      closesAtPicker.clear();
    }
    // Re-enable submit button
    $('#submitJobOpeningBtn').prop('disabled', false).text('Submit');
  }

  // Reset form when offcanvas is hidden
  offcanvasElement.on('hidden.bs.offcanvas', function () {
    resetOffcanvasForm();
  });


  // --- DataTable Initialization ---
  if (dtJobOpeningTable.length) {
    var dtJobOpening = dtJobOpeningTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: jobOpeningsListAjaxUrl, // Provided via Blade
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken }, // Add CSRF token
        data: function (d) {
          // Add filters to the request data
          d.statusFilter = statusFilter.val();
          d.teamFilter = teamFilter.val();
        }
      },
      columns: [
        // {data: '', name: ''}, // For responsive control
        { data: 'id', name: 'id' },
        { data: 'title', name: 'title' },
        { data: 'teamName', name: 'team.name', orderable: false, searchable: false }, // Assuming teamName is added in controller
        { data: 'location', name: 'location' },
        { data: 'job_type', name: 'job_type' },
        { data: 'applications_count', name: 'applications_count', searchable: false, orderable: true, className: 'text-center' }, // Make count orderable if desired
        // {data: 'experience_level', name: 'experience_level'}, // Uncomment if needed
        { data: 'status', name: 'status' },
        { data: 'published_at', name: 'published_at' },/*
        { data: 'closes_at', name: 'closes_at' }, */
        { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
      ],
      order: [[0, 'desc']], // Order by ID descending
      // Add other options like buttons, dom, etc. as needed from your theme
      // Example (adjust based on your theme's setup):
      dom: '<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: {
        sLengthMenu: '_MENU_', search: '', searchPlaceholder: 'Search..'
      },
      // Buttons with Dropdown
      buttons: [
        // Standard buttons like Copy, CSV, Excel, PDF, Print can be added here
        // Example:
        // { extend: 'collection', className: 'btn btn-label-secondary dropdown-toggle mx-3', text: '<i class="bx bx-export me-1"></i>Export',
        //   buttons: [ { extend: 'print', ... }, { extend: 'csv', ... }, ... ]
        // }
      ],
      responsive: { // Basic responsive setup
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) { var data = row.data(); return 'Details of ' + data['title']; }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col, i) {
              return col.title !== '' ? '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' + '<td>' + col.title + ':' + '</td> ' + '<td>' + col.data + '</td>' + '</tr>' : '';
            }).join('');
            return data ? $('<table class="table"/><tbody />').append(data) : false;
          }
        }
      }
    });
  }

  // --- Filter Changes ---
  statusFilter.add(teamFilter).on('change', function () {
    dtJobOpening.ajax.reload(); // Reload DataTable when filters change
  });

  // --- Open Offcanvas for Create ---
  $('#addJobOpeningBtn').on('click', function () {
    resetOffcanvasForm(); // Ensure form is clean
    $('#offcanvasAddEditJobOpeningLabel').text('Add Job Opening');
    offcanvas.show();
  });


  // --- Open Offcanvas for Edit ---
  $(document).on('click', '.edit-job-opening', function () {
    var id = $(this).data('id');
    // Construct the specific URL for the edit data endpoint
    var editDataUrl = jobOpeningsBaseUrl + '/' + id + '/edit';

    resetOffcanvasForm(); // Reset before loading new data

    $.ajax({
      url: editDataUrl,
      type: 'GET',
      beforeSend: function() {
        // Optional: Show a loading indicator
        $('#offcanvasAddEditJobOpeningLabel').text('Loading Data...');
      },
      success: function (response) {
        if (response.success && response.jobOpening) {
          const jobData = response.jobOpening;
          $('#offcanvasAddEditJobOpeningLabel').text('Edit Job Opening #' + jobData.id);
          $('#job_opening_id').val(jobData.id); // Set the hidden ID for update
          $('#jobOpeningMethod').val('PUT'); // Set method for update

          // Populate form fields
          $('#title').val(jobData.title);
          $('#description').val(jobData.description);
          $('#location').val(jobData.location);
          $('#teamId').val(jobData.teamId).trigger('change'); // Set Select2 value
          $('#jobType').val(jobData.jobType);
          $('#experienceLevel').val(jobData.experienceLevel);
          $('#skillsRequired').val(jobData.skillsRequired);
          $('#status').val(jobData.status).trigger('change'); // Set Select2 value

          // Set date picker value
          const closesAtPicker = document.querySelector('#closesAt')._flatpickr;
          if (closesAtPicker && jobData.closesAt) {
            closesAtPicker.setDate(jobData.closesAt, true); // Set date without triggering change event
          } else if (closesAtPicker) {
            closesAtPicker.clear();
          }


          offcanvas.show();
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load job opening data.' });
        }
      },
      error: function (jqXHR) {
        let message = 'An error occurred while fetching data.';
        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
          message = jqXHR.responseJSON.message;
        }
        Swal.fire({ icon: 'error', title: 'Error', html: message });
      },
      complete: function() {
        // Optional: Hide loading indicator
      }
    });
  });


  // --- Form Submission (Create/Update) ---
  jobOpeningForm.on('submit', function (e) {
    e.preventDefault();
    resetFormValidation(this); // Clear previous errors

    var formData = new FormData(this);
    var id = $('#job_opening_id').val();
    var method = $('#jobOpeningMethod').val(); // Should be POST or PUT
    var url = id ? (jobOpeningsBaseUrl + '/' + id) : jobOpeningsStoreUrl;
    var submitButton = $('#submitJobOpeningBtn');

    // Add PUT method to FormData if needed (Laravel handles this via _method field)
    if(method === 'PUT') {
      formData.append('_method', 'PUT'); // Ensure Laravel recognizes PUT
    }


    submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

    $.ajax({
      url: url,
      type: 'POST', // Always POST, _method handles PUT/PATCH for Laravel
      data: formData,
      processData: false,
      contentType: false,
      headers: { 'X-CSRF-TOKEN': csrfToken }, // Ensure CSRF token is sent
      success: function (response) {
        if (response.success) {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: response.message,
            timer: 1500,
            showConfirmButton: false
          });
          offcanvas.hide();
          dtJobOpening.ajax.reload(null, false); // Reload table, stay on current page
        } else {
          // This case might happen if server returns success=false but status 2xx
          Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Operation failed.' });
        }
      },
      error: function (jqXHR) {
        console.error('Submit Error:', jqXHR);
        let message = 'An error occurred.';
        if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
          message = jqXHR.responseJSON.message || 'Please correct the errors below.';
          // Display validation errors
          $.each(jqXHR.responseJSON.errors, function (key, value) {
            // Convert snake_case key from backend (like teamId) to camelCase if form uses camelCase names
            let inputName = key; // Assuming form uses snake_case names matching validator keys
            // If form uses camelCase: let inputName = key.replace(/_([a-z])/g, g => g[1].toUpperCase());
            let input = $('[name="' + inputName + '"]');
            input.addClass('is-invalid').siblings('.invalid-feedback').text(value[0]);
            // Handle Select2 validation error display
            if (input.hasClass('select2-hidden-accessible')) {
              input.siblings('.select2-container').addClass('is-invalid');
            }
          });
          $('.is-invalid').first().focus();

        } else if (jqXHR.responseJSON?.message) {
          message = jqXHR.responseJSON.message;
          Swal.fire({ icon: 'error', title: 'Error', html: message });
        } else {
          Swal.fire({ icon: 'error', title: 'Error', html: message + ' (' + jqXHR.status + ' ' + jqXHR.statusText + ')' });
        }

      },
      complete: function () {
        submitButton.prop('disabled', false).text('Submit');
      }
    });
  });


  // --- Delete Handling ---
  $(document).on('click', '.delete-job-opening', function () {
    var id = $(this).data('id');
    var deleteUrl = $(this).data('url'); // Get URL from button

    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: deleteUrl,
          type: 'DELETE',
          headers: { 'X-CSRF-TOKEN': csrfToken }, // Add CSRF token
          success: function (response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, customClass: { confirmButton: 'btn btn-success' } });
              dtJobOpening.ajax.reload(null, false); // Reload table
            } else {
              Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to delete.', customClass: { confirmButton: 'btn btn-danger' } });
            }
          },
          error: function (jqXHR) {
            let message = 'An error occurred during deletion.';
            if (jqXHR.responseJSON?.message) { message = jqXHR.responseJSON.message; }
            Swal.fire({ icon: 'error', title: 'Error!', text: message, customClass: { confirmButton: 'btn btn-danger' } });
          }
        });
      }
    });
  });


}); // End of document ready
