
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
$(function () {
  'use strict';
  

  // CSRF setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Initialize Quill editors
  let reasonQuill, evidenceQuill;

  if ($('#reason_editor').length) {
    reasonQuill = new Quill('#reason_editor', {
      theme: 'snow',
      placeholder: 'Explain why you believe this warning should be reconsidered...',
      modules: {
        toolbar: [
          ['bold', 'italic', 'underline'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          ['clean']
        ]
      }
    });
  }

  if ($('#evidence_editor').length) {
    evidenceQuill = new Quill('#evidence_editor', {
      theme: 'snow',
      placeholder: 'Provide any evidence or documentation...',
      modules: {
        toolbar: [
          ['bold', 'italic', 'underline'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          ['clean']
        ]
      }
    });
  }


  // Form submission
  $('#appealForm').on('submit', function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Get Quill content
    if (reasonQuill) {
      const reasonContent = reasonQuill.root.innerHTML;
      formData.set('appeal_reason', reasonContent);
    }
    
    if (evidenceQuill) {
      const evidenceContent = evidenceQuill.root.innerHTML;
      formData.set('employee_statement', evidenceContent);
    }
    
    // Validate required fields
    if (!reasonQuill || reasonQuill.getText().trim() === '') {
      Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'Please provide a reason for your appeal'
      });
      return;
    }
    
    // Submit button handling
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.find('.submit-text').text();
    submitBtn.prop('disabled', true);
    submitBtn.find('.submit-text').text('Submitting...');
    
    $.ajax({
      url: pageData.urls.store,
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
            window.location.href = pageData.urls.success;
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
        submitBtn.prop('disabled', false);
        submitBtn.find('.submit-text').text(originalText);
      }
    });
  });
});