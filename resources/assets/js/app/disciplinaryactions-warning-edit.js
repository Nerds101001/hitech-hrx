$(function () {
  'use strict';

  // CSRF setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Initialize flatpickr
  $('.flatpickr-date').flatpickr({
    dateFormat: 'Y-m-d',
    allowInput: true
  });

  // Initialize Select2
  $('.select2').select2({
    placeholder: function() {
      return $(this).data('placeholder') || 'Select...';
    },
    allowClear: true
  });

  // Load data and populate form
  loadActiveTypes();
  loadEmployees();

  // Form submission
  $('#warningForm').on('submit', function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Fix checkbox value
    const isAppealable = $('#is_appealable').is(':checked');
    formData.delete('is_appealable');
    formData.append('is_appealable', isAppealable ? '1' : '0');
    
    // Submit button handling
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.text();
    submitBtn.prop('disabled', true).text('Updating...');
    
    $.ajax({
      url: pageData.urls.update,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            text: response.data.message,
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            window.location.href = pageData.urls.index;
          });
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
        
        if (xhr.status === 422) {
          const errors = xhr.responseJSON.errors;
          errorMessage = Object.values(errors).flat().join('\n');
        } else if (xhr.responseJSON && xhr.responseJSON.data) {
          errorMessage = xhr.responseJSON.data;
        }
        
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: errorMessage
        });
      },
      complete: function () {
        submitBtn.prop('disabled', false).text(originalText);
      }
    });
  });

  // Load active warning types
  function loadActiveTypes() {
    $.ajax({
      url: pageData.urls.getActiveTypes,
      method: 'GET',
      success: function (response) {
        if (response.status === 'success') {
          const $select = $('#warning_type_id');
          $select.empty().append('<option value="">Select Warning Type</option>');
          
          response.data.forEach(function (type) {
            const selected = type.id == pageData.warning.warning_type_id ? 'selected' : '';
            $select.append(`<option value="${type.id}" ${selected}>${type.name} (${type.severity})</option>`);
          });
        }
      }
    });
  }

  // Load employees
  function loadEmployees() {
    $.ajax({
      url: pageData.urls.getEmployees,
      method: 'GET',
      success: function (response) {
        if (response.data) {
          const $select = $('#user_id');
          $select.empty().append('<option value="">Select Employee</option>');
          
          response.data.forEach(function (employeeType) {
            const selected = employeeType.id == pageData.warning.user_id ? 'selected' : '';
            $select.append(`<option value="${employeeType.id}" ${selected}>${employeeType.first_name} ${employeeType.last_name}</option>`);
          });
        }
      }
    });
  }

  // Warning type change - update expiry date based on validity days
  $('#warning_type_id').on('change', function () {
    const selectedTypeId = $(this).val();
    if (selectedTypeId) {
      loadPreviousWarnings(selectedTypeId);
    }
  });

  // Employee change - load their previous warnings
  $('#user_id').on('change', function () {
    const selectedUserId = $(this).val();
    const selectedTypeId = $('#warning_type_id').val();
    
    if (selectedUserId && selectedTypeId) {
      loadPreviousWarnings(selectedTypeId, selectedUserId);
    }
  });

  // Load previous warnings for the selected employee and type
  function loadPreviousWarnings(typeId, userId = null) {
    if (!userId) {
      userId = $('#user_id').val();
    }
    
    if (!userId) return;
    
    // This would be implemented to load previous warnings
    // For now, just populate with existing data
    const $select = $('#previous_warning_id');
    $select.empty().append('<option value="">None</option>');
    
    if (pageData.warning.previous_warning_id) {
      $select.append(`<option value="${pageData.warning.previous_warning_id}" selected>Previous Warning</option>`);
    }
  }
});