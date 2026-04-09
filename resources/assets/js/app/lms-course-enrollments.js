'use strict';

$(function () { // jQuery document ready

  // --- Selectors ---
  const enrollmentsDataTableElement = $('.datatables-enrollments');
  const enrollUsersModalElement = $('#enrollUsersModal');
  const enrollUsersModal = enrollUsersModalElement.length ? new bootstrap.Modal(enrollUsersModalElement[0]) : null;
  const enrollUsersForm = $('#enrollUsersForm');
  const submitEnrollmentBtn = $('#submitEnrollmentBtn');
  const enrollUserSelect = $('#enrollUserIds'); // Select2 for users
  const enrollDesignationSelect = $('#enrollDesignationIds'); // Select2 for designations
  const enrollGeneralError = $('#enrollUsersForm .general-error-message'); // Error display in modal


  // Progress Modal Selectors
  const progressModalElement = $('#enrollmentProgressModal');
  const progressModal = progressModalElement.length ? new bootstrap.Modal(progressModalElement[0]) : null;
  const progressModalLabel = $('#enrollmentProgressModalLabel');
  const progressModalLoading = $('#progress-modal-loading');
  const progressModalContent = $('#progress-modal-content');
  const progressUserName = $('#progress-user-name');
  const progressCourseTitle = $('#progress-course-title');
  const progressEnrollmentStatus = $('#progress-enrollment-status');
  const progressEnrolledAt = $('#progress-enrolled-at');
  const progressStartedAt = $('#progress-started-at');
  const progressCompletedAt = $('#progress-completed-at');
  const progressSummaryText = $('#progress-summary-text');
  const progressBarIndicator = $('#progress-bar-indicator');
  const progressLessonList = $('#modal-lesson-completion-list');


  // --- CSRF Setup ---
  // Assumes csrfToken is globally available via Blade <script> block
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

  // --- Initialize Plugins ---

  // Select2 for Enrollment Modal
  function initializeEnrollmentModalPlugins() {
    if (enrollUsersModalElement.length) {
      const modalBody = enrollUsersModalElement.find('.modal-body');
      enrollUserSelect.select2({
        placeholder: 'Select Employee(s)',
        dropdownParent: modalBody, // Attach dropdown to modal
        closeOnSelect: false // Allow multiple selections easily
      });
      enrollDesignationSelect.select2({
        placeholder: 'Select Designation(s)',
        dropdownParent: modalBody,
        closeOnSelect: false
      });
    }
  }
  initializeEnrollmentModalPlugins(); // Init on page load

  // --- Helper Functions ---

  // Reset Enrollment Modal Validation
  function resetEnrollmentFormValidation() {
    enrollUsersForm.find('.is-invalid').removeClass('is-invalid');
    enrollUsersForm.find('.invalid-feedback').text('').hide();
    enrollUsersForm.find('.select2-container').removeClass('is-invalid'); // Target Select2 container
    enrollGeneralError.text('').hide();
  }

  // Reset Enrollment Modal Form
  function resetEnrollmentForm() {
    resetEnrollmentFormValidation();
    enrollUsersForm[0]?.reset();
    enrollUserSelect.val(null).trigger('change'); // Clear user Select2
    enrollDesignationSelect.val(null).trigger('change'); // Clear designation Select2
    enrollGeneralError.text('').hide();
    submitEnrollmentBtn.text('Enroll Selected').prop('disabled', false); // Use correct default text
  }

  // Set Enrollment Button Loading State
  function setEnrollmentButtonLoading(isLoading) {
    submitEnrollmentBtn.prop('disabled', isLoading);
    submitEnrollmentBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Enrolling...' : 'Enroll Selected Users');
  }

  // Progress Modal Helpers
  function resetProgressModal() {
    progressModalLabel.text('Enrollment Progress');
    progressUserName.text('N/A');
    progressCourseTitle.text('N/A');
    progressEnrollmentStatus.text('N/A').removeClass (function (index, className) { return (className.match (/(^|\s)badge\s\S+/g) || []).join(' '); }); // Remove old badge class
    progressEnrolledAt.text('N/A');
    progressStartedAt.text('N/A');
    progressCompletedAt.text('N/A');
    progressSummaryText.text('Progress');
    progressBarIndicator.css('width', '0%').attr('aria-valuenow', 0);
    progressLessonList.empty().append('<li class="list-group-item text-muted">Loading lesson status...</li>');
    progressModalLoading.hide();
    progressModalContent.show();
  }

  // Display Validation Errors in Enrollment Modal
  function displayEnrollmentValidationErrors(errors) {
    resetEnrollmentFormValidation();
    let firstErrorElement = null;
    console.log("Enrollment Validation Errors:", errors);
    for (const fieldName in errors) {
      let inputName = fieldName;
      // Handle array fields like user_ids.0 or designation_ids.0
      if (fieldName.includes('.')) {
        inputName = fieldName.split('.')[0] + '[]'; // Target the select element name
      }
      let errorMessage = errors[fieldName][0];
      const inputElement = enrollUsersForm.find(`[name="${inputName}"]`);
      let targetElement = inputElement;
      let feedbackElement = targetElement.closest('.mb-3').find('.invalid-feedback');

      if (!inputElement.length) { enrollGeneralError.append(`<div>${escapeHtml(fieldName)}: ${escapeHtml(errorMessage)}</div>`).show(); if (!firstErrorElement) firstErrorElement = enrollGeneralError; continue; }
      if (inputElement.hasClass('select2-hidden-accessible')) { targetElement = inputElement.siblings('.select2-container'); }

      if (targetElement.length) {
        targetElement.addClass('is-invalid');
        if(feedbackElement.length) { feedbackElement.text(errorMessage).show(); }
        else { targetElement.after(`<div class="invalid-feedback d-block">${errorMessage}</div>`); }
        if (!firstErrorElement) firstErrorElement = targetElement;
      }
    }
    // Focus first error
    if (firstErrorElement) {
      if (firstErrorElement.hasClass('select2-container')) { firstErrorElement.prev('select').select2('open'); }
      else if (firstErrorElement.is(':visible')){ firstErrorElement.focus(); }
    }
  }
  // Basic HTML escaping helper
  function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }


  // --- DataTable Initialization ---
  let dtEnrollments;
  if (enrollmentsDataTableElement.length) {
    dtEnrollments = enrollmentsDataTableElement.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: enrollmentsListAjaxUrl, // URL passed from Blade
        type: 'GET'
      },
      columns: [
        // Define columns based on controller listAjax response
        // Adjust 'name' attributes if controller output differs
        { data: 'user', name: 'user', orderable: false, searchable: false },
        { data: 'status', name: 'status' }, // Rendered as badge server-side
        { data: 'enrolled_at', name: 'enrolled_at' },
        { data: 'completed_at', name: 'completed_at' },
        { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
      ],
      order: [[2, 'desc']], // Order by Enrollment Date descending initially
      // Standard DOM layout
      dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: { search: '', searchPlaceholder: 'Search Enrollments...' },
    });
  }

  // --- Enrollment Modal Handling ---

  // Reset form when modal is hidden
  if(enrollUsersModalElement.length) {
    enrollUsersModalElement.on('hidden.bs.modal', resetEnrollmentForm);
    // Reset form when shown (optional, ensures clean state)
    // enrollUsersModalElement.on('show.bs.modal', resetEnrollmentForm);
  }

  // Enroll Users Form Submission
  if (enrollUsersForm.length) {
    enrollUsersForm.on('submit', function(e) {
      e.preventDefault();
      resetEnrollmentFormValidation();

      const selectedUsers = enrollUserSelect.val() || [];
      const selectedDesignations = enrollDesignationSelect.val() || [];

      // Client-side check if at least one is selected
      if (selectedUsers.length === 0 && selectedDesignations.length === 0) {
        showErrorToast('Please select at least one user or designation.');
        // Optionally highlight both fields
        enrollUserSelect.siblings('.select2-container').addClass('is-invalid');
        enrollDesignationSelect.siblings('.select2-container').addClass('is-invalid');
        enrollGeneralError.text('Please select users or designations.').show();
        return;
      }

      const payload = {
        user_ids: selectedUsers,
        designation_ids: selectedDesignations
      };

      setEnrollmentButtonLoading(true);

      $.ajax({
        url: enrollmentsStoreUrl, // POST URL from Blade vars
        method: 'POST',
        data: JSON.stringify(payload), // Send as JSON
        contentType: 'application/json; charset=utf-8',
        dataType: 'json',
        success: function (response) {
          if(response.success) {
            enrollUsersModal?.hide();
            showSuccessToast(response.message || 'Enrollment successful.');
            dtEnrollments?.ajax.reload(null, false); // Refresh enrollment table
            // TODO: Maybe update enrollment count on main course card?
          } else {
            showErrorToast(response.message || 'Enrollment failed.');
            enrollGeneralError.text(response.message || 'Enrollment failed.').show();
          }
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
            displayEnrollmentValidationErrors(jqXHR.responseJSON.errors);
            showErrorToast('Validation failed.');
          } else {
            showErrorToast(jqXHR.responseJSON?.message || 'An error occurred during enrollment.');
          }
        },
        complete: function () {
          setEnrollmentButtonLoading(false);
        }
      });
    });
  } // end if enrollUsersForm


  // --- Unenroll User Handling ---
  enrollmentsDataTableElement.on('click', '.delete-enrollment', function () {
    const button = $(this);
    const enrollmentId = button.data('id');

    // replace this __enrollmentId__ in url unEnrollUrlTemplate
    const unEnrollUrlTemplate = button.data('url'); // URL template passed from Blade

    console.log(unEnrollUrlTemplate);

    const unenrollUrl = unEnrollUrlTemplate; // Template URL passed from Blade

    Swal.fire({
      title: 'Unenroll User?', text: "Are you sure you want to remove this user's enrollment?", icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Yes, Unenroll',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: unenrollUrl,
          method: 'POST', // Use POST
          data: { _method: 'DELETE' }, // Override method
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showSuccessToast(response.message);
              dtEnrollments?.row(button.closest('tr')).remove().draw(false); // Remove row
            } else { showErrorToast(response.message || 'Failed to unenroll.'); }
          },
          error: function(jqXHR) { showErrorToast(jqXHR.responseJSON?.message || 'Unenrollment failed.'); }
        });
      }
    });
  });


  // --- View Progress Modal Trigger ---
  enrollmentsDataTableElement.on('click', '.view-enrollment-progress', function () {
    const button = $(this);
    const enrollmentId = button.data('id');
    console.log(enrollmentId);
    const url =  button.data('url'); // URL from blade vars
    console.log(url);

    resetProgressModal(); // Clear previous data
    progressModalContent.hide(); // Hide content area
    progressModalLoading.show(); // Show loading spinner
    progressModal?.show(); // Show the modal

    $.ajax({
      url: url, type: 'GET', dataType: 'json',
      success: function(response) {
        console.log(response);
        progressModalLoading.hide();
        if(response.success && response.data) {
          const data = response.data;
          // Populate modal header/summary
          progressModalLabel.text(`Progress for ${data.userName}`);
          progressUserName.text(data.userName || 'N/A');
          progressCourseTitle.text(data.courseTitle || 'N/A');
          progressEnrollmentStatus.html(`<span class="badge ${data.statusClass || 'bg-label-secondary'}">${data.status || 'N/A'}</span>`);
          progressEnrolledAt.text(data.enrolledAt || '-');
          progressStartedAt.text(data.startedAt || '-');
          progressCompletedAt.text(data.completedAt || '-');
          progressSummaryText.text(`Progress (${data.completedCount}/${data.totalLessons})`);
          progressBarIndicator.css('width', `${data.progressPercent || 0}%`).attr('aria-valuenow', data.progressPercent || 0);

          // Populate lesson list
          progressLessonList.empty(); // Clear loading message
          if (data.lessons && data.lessons.length > 0) {
            data.lessons.forEach(lesson => {
              const iconClass = lesson.isCompleted ? 'bx bx-check-circle text-success' : 'bx bx-circle text-muted';
              const completionDate = lesson.isCompleted ? `<small class="text-muted ms-2">(${lesson.completedAt || 'Completed'})</small>` : '';
              progressLessonList.append(`
                                 <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                     <span>${escapeHtml(lesson.title)}</span>
                                     <span><i class="${iconClass}"></i> ${completionDate}</span>
                                 </li>
                             `);
            });
          } else {
            progressLessonList.append('<li class="list-group-item px-0 text-muted">No lessons found in this course.</li>');
          }
          progressModalContent.show(); // Show populated content

        } else {
          progressModalContent.show(); // Show content area to display error
          progressLessonList.html(`<li class="list-group-item px-0 text-danger">${response.message || 'Could not load progress.'}</li>`);
          showErrorToast(response.message || 'Could not load progress.');
        }
      },
      error: function(jqXHR) {
        progressModalLoading.hide();
        progressModalContent.show();
        progressLessonList.html(`<li class="list-group-item px-0 text-danger">Error loading progress details.</li>`);
        showErrorToast(jqXHR.responseJSON?.message || 'Failed to load progress details.');
      }
    });

  });

  // Reset progress modal on hide
  if (progressModalElement) {
    //progressModalElement.addEventListener('hidden.bs.modal', resetProgressModal);
  }

}); // End Document Ready
