$(function () {
  'use strict';

  // CSRF setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Initialize DataTable
  const dt = $('.datatables-my-warnings').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: pageData.urls.datatable,
      data: function (d) {
        d.status = $('#filter-status').val();
      }
    },
    columns: [
      { data: 'warning_info', name: 'warning_info', orderable: false },
      { data: 'dates', name: 'dates', orderable: false },
      { data: 'status_badge', name: 'status', searchable: false },
      { data: 'issued_by', name: 'issued_by', orderable: false },
      { data: 'actions', name: 'actions', orderable: false, searchable: false }
    ],
    order: [[2, 'desc']],
    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
    language: {
      search: '',
      searchPlaceholder: 'Search warnings...'
    }
  });

  // Load statistics
  loadStatistics();

  // Filter change
  $('#filter-status').on('change', function () {
    dt.draw();
    loadStatistics();
  });

  // Reset filters
  $('#reset-filters').on('click', function () {
    $('#filter-status').val('');
    dt.draw();
    loadStatistics();
  });

  // Warning actions
  $(document).on('click', '.acknowledge-warning', function () {
    const warningId = $(this).data('id');
    acknowledgeWarning(warningId);
  });

  $(document).on('click', '.appeal-warning', function () {
    const warningId = $(this).data('id');
    window.location.href = pageData.urls.appeal.replace(':id', warningId);
  });

  $(document).on('click', '.view-warning', function () {
    const warningId = $(this).data('id');
    window.location.href = pageData.urls.show.replace(':id', warningId);
  });

  $(document).on('click', '.download-letter', function () {
    const warningId = $(this).data('id');
    window.open(pageData.urls.downloadLetter.replace(':id', warningId), '_blank');
  });

  // Load statistics
  function loadStatistics() {
    $.ajax({
      url: pageData.urls.stats,
      method: 'GET',
      data: {
        my_warnings: true
      },
      success: function(response) {
        if (response.status === 'success' && response.data) {
          const stats = response.data;
          $('#active-warnings').text(stats.active_warnings || 0);
          $('#acknowledged-warnings').text(stats.acknowledged_warnings || 0);
          $('#appealed-warnings').text(stats.appealed_warnings || 0);
        }
      },
      error: function() {
        // Keep default values on error
        console.log('Failed to load warning statistics');
      }
    });
  }

  // Acknowledge warning
  function acknowledgeWarning(warningId) {
    Swal.fire({
      title: 'Acknowledge Warning',
      text: 'Do you want to add any comments?',
      input: 'textarea',
      inputPlaceholder: 'Optional comments...',
      showCancelButton: true,
      confirmButtonText: 'Acknowledge',
      cancelButtonText: 'Cancel',
      showLoaderOnConfirm: true,
      preConfirm: (comments) => {
        return $.ajax({
          url: pageData.urls.acknowledge.replace(':id', warningId),
          method: 'POST',
          data: {
            comments: comments
          }
        });
      },
      allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
      if (result.isConfirmed) {
        if (result.value.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Warning acknowledged successfully',
            timer: 2000,
            showConfirmButton: false
          });
          dt.draw(false);
          loadStatistics(); // Reload statistics after acknowledging
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.value.data || 'Failed to acknowledge warning'
          });
        }
      }
    }).catch((error) => {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Failed to acknowledge warning'
      });
    });
  }
});