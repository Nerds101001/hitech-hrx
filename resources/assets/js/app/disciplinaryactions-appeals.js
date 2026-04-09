$(function () {
  'use strict';

  // CSRF setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  let dt_appeals_table = $('.datatables-appeals');
  let dt_appeals;

  // Initialize DataTable
  if (dt_appeals_table.length) {
    dt_appeals = dt_appeals_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: pageData.urls.datatable,
        data: function (d) {
          d.status = $('#filter-status').val();
        }
      },
      columns: [
        { data: 'employee', name: 'employee' },
        { data: 'warning_info', name: 'warning_info' },
        { data: 'appeal_date', name: 'appeal_date' },
        { data: 'status_badge', name: 'status_badge' },
        { data: 'hearing_info', name: 'hearing_info' },
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
              return 'Appeal Details';
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
        searchPlaceholder: 'Search..'
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

  // Load statistics
  loadAppealStats();

  // Set up periodic refresh of statistics (every 5 minutes)
  setInterval(function() {
    loadAppealStats();
  }, 5 * 60 * 1000); // 5 minutes in milliseconds

  // Refresh stats when user returns to the tab
  $(window).on('focus', function() {
    loadAppealStats();
  });

  // Filter functionality
  $('#filter-status').on('change', function () {
    if (dt_appeals) {
      dt_appeals.draw();
    }
    loadAppealStats();
  });

  // Reset filters
  $('#reset-filters').on('click', function () {
    $('#filter-status').val('').trigger('change');
    if (dt_appeals) {
      dt_appeals.draw();
    }
    loadAppealStats();
  });

  // View appeal
  $(document).on('click', '.view-appeal', function (e) {
    e.preventDefault();
    const appealId = $(this).data('id');
    window.location.href = pageData.urls.show.replace(':id', appealId);
  });

  // Load appeal statistics
  function loadAppealStats() {
    if (!pageData.urls.getStats) {
      // Set default values if stats endpoint is not available
      $('#pending-appeals').text('0');
      $('#under-review-appeals').text('0');
      $('#approved-appeals').text('0');
      $('#rejected-appeals').text('0');
      return;
    }

    $.ajax({
      url: pageData.urls.getStats,
      method: 'GET',
      data: {
        status: $('#filter-status').val()
      },
      success: function (response) {
        if (response.status === 'success') {
          $('#pending-appeals').text(response.data.pending || 0);
          $('#under-review-appeals').text(response.data.under_review || 0);
          $('#approved-appeals').text(response.data.approved || 0);
          $('#rejected-appeals').text(response.data.rejected || 0);
        }
      },
      error: function (xhr) {
        console.error('Failed to load appeal statistics');
        // Set default values on error
        $('#pending-appeals').text('0');
        $('#under-review-appeals').text('0');
        $('#approved-appeals').text('0');
        $('#rejected-appeals').text('0');
      }
    });
  }
});