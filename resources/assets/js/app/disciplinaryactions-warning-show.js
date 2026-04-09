$(function () {
  'use strict';

  // CSRF setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Acknowledge warning
  $('#acknowledgeBtn').on('click', function () {
    Swal.fire({
      title: 'Acknowledge Warning',
      text: 'Are you sure you want to acknowledge this warning?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, Acknowledge'
    }).then((result) => {
      if (result.isConfirmed) {
        acknowledgeWarning();
      }
    });
  });

  // Acknowledge warning function
  function acknowledgeWarning() {
    const $btn = $('#acknowledgeBtn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Processing...');

    $.ajax({
      url: pageData.urls.acknowledge,
      method: 'POST',
      success: function (response) {
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: response.data.message,
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: response.data
          });
          $btn.prop('disabled', false).text(originalText);
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
        
        $btn.prop('disabled', false).text(originalText);
      }
    });
  }
});