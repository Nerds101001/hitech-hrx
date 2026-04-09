/*
Change Status JS
 */

'use strict';

// ajax setup
$.ajaxSetup({
  headers: {
    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
  }
});

//Method to change the status
window.changeStatus = function(url,id){
  $.ajax({
    url: url,
    type: 'POST',
    data: {
      id: id,
    },
    success: function (response) {
      if (response.status === 'success') {
        showSuccessToast(response.message);
        setTimeout(function () {
          location.reload();
        }, 1000);
      }
    },
    error: function (response) {
      console.log(response);
      showErrorToast('An error occurred. Please try again later.');
    }
  });
}


