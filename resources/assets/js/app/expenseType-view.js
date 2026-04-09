'use strict';

$(function () {
  //Ajax csrf
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Edit Rule Modal Handler
  $(document).on('click', '.edit-rule', function () {
    const rule = $(this).data('rule'); // Get rule data from the button

    $('#id').val(rule.id); // Set rule ID in the hidden input
    $('#designationId').val(rule.designation_id).trigger('change'); // Set designation in select2 and trigger change
    $('#locationId').val(rule.location_id).trigger('change'); // Set location in select2 and trigger change
    $('#amount').val(rule.amount); // Set the amount

    // Change the modal title and button text
    $('#addRuleModalLabel').text('Edit Expense Rule');
    $('#submitBtn').html('Update Rule');

    // Open the modal
    $('#addRuleModal').modal('show');
  });

  // Reset Modal on Close
  $('#addRuleModal').on('hidden.bs.modal', function () {
    $('#addRuleForm')[0].reset(); // Reset the form
    $('.select2').val('').trigger('change'); // Reset select2 fields
    $('#id').val(''); // Clear the hidden ID field
    $('#addRuleModalLabel').text('Add Expense Rule'); // Reset the modal title
    $('#submitBtn').text('Save Rule'); // Reset button text
  });

  $(document).on('click', '.delete-rule', function () {
    const ruleId = $(this).data('id');
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
        //delete request
        $.ajax({
          url: '/expenseTypes/deleteRule/' + ruleId,
          type: 'DELETE',
          success: function success(response) {
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'Your record has been deleted.',
              customClass: {
                confirmButton: 'btn btn-hitech'
              }
            }).then(function () {
              location.reload();
            });
          },
          error: function error(response) {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'There was an error deleting the record.',
              customClass: {
                confirmButton: 'btn btn-hitech'
              }
            });
          }
        });
      }
    });
  });
});
