$(function () {
  'use strict';

  // CSRF setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  let dt_warnings_table = $('.datatables-warnings');

  // Initialize DataTable
  if (dt_warnings_table.length) {
    let dt_warnings = dt_warnings_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: pageData.urls.datatable,
        data: function (d) {
          d.status = $('#filter-status').val();
          d.warning_type = $('#filter-warning-type').val();
          d.employee = $('#filter-employee').val();
        }
      },
      columns: [
        { data: 'employee_info', name: 'employee_info', searchable: true },
        { data: 'warning_info', name: 'warning_info', searchable: true },
        { data: 'dates', name: 'dates', searchable: false },
        { data: 'status_info', name: 'status_info', searchable: false },
        { data: 'issued_by', name: 'issued_by', searchable: true },
        { data: 'actions', name: 'actions', orderable: false, searchable: false }
      ],
      order: [[2, 'desc']],
      dom: '<"row mx-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      displayLength: 10,
      lengthMenu: [10, 25, 50, 75, 100],
      buttons: [],
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Details of ' + data['warning'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col, i) {
              return col.columnIndex !== 5
                ? '<tr data-dt-row="' +
                    col.rowIdx +
                    '" data-dt-column="' +
                    col.columnIndex +
                    '">' +
                    '<td>' +
                    col.title +
                    ':' +
                    '</td> ' +
                    '<td>' +
                    col.data +
                    '</td>' +
                    '</tr>'
                : '';
            }).join('');

            return data ? $('<table class="table"/><tbody />').append(data) : false;
          }
        }
      },
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search warnings, employees, subjects...'
      }
    });
  }

  // Initialize Select2
  $('.select2').select2({
    placeholder: function() {
      return $(this).data('placeholder') || 'Select...';
    },
    allowClear: true
  });

  // Load warning types and employees for filters
  loadWarningTypes();
  loadEmployees();
  loadWarningStats();

  // Set up periodic refresh of statistics (every 5 minutes)
  setInterval(function() {
    loadWarningStats();
  }, 5 * 60 * 1000); // 5 minutes in milliseconds

  // Refresh stats when user returns to the tab
  $(window).on('focus', function() {
    loadWarningStats();
  });

  // Filter functionality
  $('#filter-status, #filter-warning-type, #filter-employee').on('change', function () {
    if (dt_warnings_table.length) {
      dt_warnings_table.DataTable().ajax.reload();
      loadWarningStats();
    }
  });

  // Reset filters
  $('#reset-filters').on('click', function () {
    $('#filter-status').val('').trigger('change');
    $('#filter-warning-type').val('').trigger('change');
    $('#filter-employee').val('').trigger('change');
    if (dt_warnings_table.length) {
      dt_warnings_table.DataTable().ajax.reload();
      loadWarningStats();
    }
  });

  // Actions
  $(document).on('click', '.view-warning', function (e) {
    e.preventDefault();
    const warningId = $(this).data('id');
    window.location.href = pageData.urls.show.replace(':id', warningId);
  });

  $(document).on('click', '.edit-warning', function (e) {
    e.preventDefault();
    const warningId = $(this).data('id');
    window.location.href = pageData.urls.edit.replace(':id', warningId);
  });

  $(document).on('click', '.issue-warning', function (e) {
    e.preventDefault();
    const warningId = $(this).data('id');

    Swal.fire({
      title: 'Issue Warning',
      text: 'Are you sure you want to issue this warning?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, Issue'
    }).then((result) => {
      if (result.isConfirmed) {
        issueWarning(warningId);
      }
    });
  });

  $(document).on('click', '.delete-warning', function (e) {
    e.preventDefault();
    const warningId = $(this).data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: 'This action cannot be undone!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        deleteWarning(warningId);
      }
    });
  });

  // Load warning types for filter
  function loadWarningTypes() {
    $.ajax({
      url: pageData.urls.getActiveTypes || '/disciplinary-actions/warning-types/active-types',
      method: 'GET',
      success: function (response) {
        if (response.status === 'success') {
          const $select = $('#filter-warning-type');
          $select.empty().append('<option value="">All Types</option>');

          response.data.forEach(function (type) {
            $select.append(`<option value="${type.id}">${type.name}</option>`);
          });
        }
      }
    });
  }

  // Load employees for filter
function loadEmployees() {
  $.ajax({
    url: pageData.urls.getEmployees || '/employees/getReportingToUsersAjax', // Use the same route as create
    method: 'GET',
    success: function (response) {
      if (response.data) {
        const $select = $('#filter-employee');
        $select.empty().append('<option value="">All Employees</option>');

        response.data.forEach(function (employee) {
          // Use first_name and last_name if name is not available
          const employeeName = employee.name || `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim();
          $select.append(`<option value="${employee.id}">${employeeName}</option>`);
        });
      }
    }
  });
}

  // Load warning statistics
  function loadWarningStats() {
    const filters = {
      status: $('#filter-status').val(),
      warning_type: $('#filter-warning-type').val(),
      employee: $('#filter-employee').val()
    };

    $.ajax({
      url: pageData.urls.stats,
      method: 'GET',
      data: filters,
      success: function (response) {
        if (response.status === 'success') {
          const stats = response.data;
          $('#active-warnings').text(stats.active_warnings || 0);
          $('#draft-warnings').text(stats.draft_warnings || 0);
          $('#under-appeal').text(stats.under_appeal || 0);
          $('#overdue-acknowledgments').text(stats.overdue_acknowledgments || 0);
        }
      },
      error: function (xhr) {
        console.error('Failed to load warning statistics:', xhr);
        // Fallback to zeros on error
        $('#active-warnings').text('0');
        $('#draft-warnings').text('0');
        $('#under-appeal').text('0');
        $('#overdue-acknowledgments').text('0');
      }
    });
  }

  // Issue warning function
  function issueWarning(warningId) {
    $.ajax({
      url: pageData.urls.issue.replace(':id', warningId),
      method: 'POST',
      success: function (response) {
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: response.data.message,
            timer: 2000,
            showConfirmButton: false
          });
          if (dt_warnings_table.length) {
            dt_warnings_table.DataTable().ajax.reload();
          }
          loadWarningStats();
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: response.data
          });
        }
      },
      error: function (xhr) {
        let errorMessage = 'An error occurred. Please try again.';

        if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMessage = xhr.responseJSON.data;
        }

        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errorMessage
        });
      }
    });
  }

  // Delete warning function
  function deleteWarning(warningId) {
    $.ajax({
      url: pageData.urls.destroy.replace(':id', warningId),
      method: 'DELETE',
      success: function (response) {
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: response.data.message,
            timer: 2000,
            showConfirmButton: false
          });
          if (dt_warnings_table.length) {
            dt_warnings_table.DataTable().ajax.reload();
          }
          loadWarningStats();
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: response.data
          });
        }
      },
      error: function (xhr) {
        let errorMessage = 'An error occurred. Please try again.';

        if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMessage = xhr.responseJSON.data;
        }

        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errorMessage
        });
      }
    });
  }
});
