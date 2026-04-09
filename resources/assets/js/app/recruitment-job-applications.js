'use strict';

$(function () {
  // --- Variable Definitions ---
  var dtApplicationTable = $('.datatables-job-applications');
  var statusFilter = $('#applicationStatusFilter');
  var select2 = $('.select2'); // Reusable select2 instance

  // CSRF Token Setup for all AJAX requests
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': csrfToken // Passed from Blade
    }
  });

  // --- Initialize Select2 ---
  if (select2.length) {
    select2.select2({
      placeholder: 'Select Status',
      allowClear: true // Optional: Allows clearing the filter
    });
  }

  // --- DataTable Initialization ---
  if (dtApplicationTable.length) {
    var dtApplication = dtApplicationTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: applicationsListAjaxUrl, // Passed from Blade
        type: 'POST',
        data: function (d) {
          // Add filters to the request data
          d.statusFilter = statusFilter.val();
          d.jobOpeningId = jobOpeningId; // Passed from Blade
        }
      },
      columns: [
        // Responsive control column
        // { data: '', name: '', orderable: false, searchable: false, render: function() { return ''; } },
        { data: 'id', name: 'id' },
        { data: 'candidateFullName', name: 'candidateFullName', orderable: false, searchable: true }, // Search might require backend adjustment if searching concat name
        { data: 'candidate_email', name: 'candidate_email' },
        { data: 'candidate_phone', name: 'candidate_phone' },
        { data: 'submitted_at', name: 'submitted_at' },
        { data: 'resumeLink', name: 'resumeLink', orderable: false, searchable: false },
        { data: 'status', name: 'status' },
        { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
      ],
      order: [[4, 'desc']], // Order by submitted_at descending by default
      // Add other options like buttons, dom, etc. as needed from your theme
      dom: '<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: {
        sLengthMenu: '_MENU_', search: '', searchPlaceholder: 'Search Applications..'
      },
      buttons: [
        // Add export buttons etc. if needed
      ],
      // Responsive configuration
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) { var data = row.data(); return 'Details for ' + data['candidateFullName']; }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col, i) {
              return col.columnIndex < (columns.length - 1) && col.title !== '' ? // Exclude actions column
                '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                '<td>' + col.title + ':' + '</td> ' +
                '<td>' + col.data + '</td>' +
                '</tr>' : '';
            }).join('');
            return data ? $('<table class="table"/><tbody />').append(data) : false;
          }
        }
      },
      // Map table headers to data columns if needed after responsive column is added
      columnDefs: [
        // Example if responsive control is first column:
        // { className: 'control', orderable: false, searchable: false, responsivePriority: 2, targets: 0, render: function () { return '' } },
        // { responsivePriority: 1, targets: 1 } // Make ID less prioritary for hiding
        { targets: 1, data: 'candidateFullName', name: 'candidateFullName' },
        { targets: 2, data: 'candidate_email', name: 'candidate_email' },
        { targets: 3, data: 'candidate_phone', name: 'candidate_phone' },
        { targets: 4, data: 'submitted_at', name: 'submitted_at' },
        { targets: 5, data: 'resumeLink', name: 'resumeLink' },
        { targets: 6, data: 'status', name: 'status' },
        { targets: 7, data: 'actions', name: 'actions' }
      ]
    });
  }

  // --- Filter Changes ---
  statusFilter.on('change', function () {
    if (dtApplication) {
      dtApplication.ajax.reload(); // Reload DataTable when status filter changes
    }
  });

  // --- Status Change Handling ---
  $(document).on('click', '.change-status-btn', function (e) {
    e.preventDefault();
    var button = $(this);
    var updateUrl = button.data('url');
    var newStatus = button.data('new-status');
    var appId = button.data('app-id');
    var currentStatus = button.closest('td').find('.badge').data('current-status'); // Get current status from badge

    if (newStatus === currentStatus) {
      // Optional: Show message or just do nothing if status is already the same
      // console.log('Status is already ' + newStatus);
      return;
    }

    // Confirmation maybe? Or directly update? Let's directly update for simplicity now.
    // Add SweetAlert confirmation if desired.

    $.ajax({
      url: updateUrl,
      type: 'PUT', // Method for updating status
      data: {
        status: newStatus
        // _token: csrfToken // Not needed with ajaxSetup
      },
      beforeSend: function() {
        // Optional: Indicate loading state on the button/row
        button.closest('tr').css('opacity', 0.5);
      },
      success: function (response) {
        if (response.success) {
          // Show success message (optional)
          // Swal.fire({ icon: 'success', title: 'Success', text: response.message, timer: 1000, showConfirmButton: false });

          // Reload the specific row for a smoother update
          if(dtApplication) {
            dtApplication.row(button.closest('tr')).invalidate().draw(false);
          }
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to update status.' });
          if(dtApplication) { button.closest('tr').css('opacity', 1); } // Restore opacity on error
        }
      },
      error: function (jqXHR) {
        let message = 'An error occurred.';
        if (jqXHR.responseJSON?.message) { message = jqXHR.responseJSON.message; }
        Swal.fire({ icon: 'error', title: 'Error', text: message });
        if(dtApplication) { button.closest('tr').css('opacity', 1); } // Restore opacity on error
      },
      complete: function() {
        // Ensure opacity is restored even if ajax call fails unexpectedly
        if(dtApplication) { button.closest('tr').css('opacity', 1); }
      }
    });
  });

  // --- Delete Application Handling ---
  $(document).on('click', '.delete-application', function () {
    var button = $(this);
    var deleteUrl = button.data('url');
    var appId = button.data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this! The resume file will also be deleted.",
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
          // data: { _token: csrfToken }, // Not needed with ajaxSetup
          success: function (response) {
            if (response.success) {
              Swal.fire({ icon: 'success', title: 'Deleted!', text: response.message, customClass: { confirmButton: 'btn btn-success' } });
              // Remove the row from the table
              if(dtApplication) {
                dtApplication.row(button.closest('tr')).remove().draw(false);
              }
            } else {
              Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Failed to delete application.', customClass: { confirmButton: 'btn btn-danger' } });
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

  // --- Placeholder for View Details ---
  // $(document).on('click', '.view-application-details', function() {
  //     var appId = $(this).data('id');
  //     // TODO: Implement AJAX call to fetch details and display in a modal
  //     console.log('View details for application ID:', appId);
  //     alert('View details functionality not yet implemented.');
  // });


}); // End of document ready
