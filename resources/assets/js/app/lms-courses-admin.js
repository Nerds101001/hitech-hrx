'use strict';

$(function () { // jQuery document ready

  // --- Global Variables & Selectors ---
  const coursesDataTableElement = $('.datatables-lms-courses');
  const courseOffcanvasElement = $('#courseOffcanvas');
  const courseOffcanvas = courseOffcanvasElement.length ? new bootstrap.Offcanvas(courseOffcanvasElement[0]) : null;
  const courseForm = $('#courseForm');
  const courseOffcanvasLabel = $('#courseOffcanvasLabel');
  const submitCourseBtn = $('#submitCourseBtn');
  const courseIdInput = $('#course_id');
  const courseMethodInput = $('#courseMethod');
  const categorySelect = $('#lms_course_category_id');
  const statusSelect = $('#courseStatus');
  const thumbnailInput = $('#thumbnail');
  const thumbnailPreviewArea = $('#thumbnailPreviewArea');
  const thumbnailPreview = $('#thumbnailPreview');
  const removeThumbnailCheckbox = $('#remove_thumbnail');
  const generalErrorDiv = $('#general-error');
  const filterCategorySelect = $('#filter_category_id');
  const filterStatusSelect = $('#filter_status');

  // --- CSRF Setup ---
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

  // --- Initialize Plugins ---
  initializeModalPlugins(); // Call function defined below

  // --- Helper Functions ---

  // Initialize Plugins for Modals/Offcanvas
  function initializeModalPlugins() {
    // Edit Asset Offcanvas Plugins
    if (courseOffcanvasElement.length) {
      $('#lms_course_category_id').select2({ placeholder: 'Select Category (Optional)', dropdownParent: $(courseOffcanvasElement).find('.offcanvas-body'), allowClear: true });
      $('#courseStatus').select2({ placeholder: 'Select Status', dropdownParent: $(courseOffcanvasElement).find('.offcanvas-body'), minimumResultsForSearch: Infinity });
      // Initialize Flatpickr or other plugins for this form if added later
    }
    // Initialize filter plugins
    $('#filter_category_id, #filter_status').select2({ minimumResultsForSearch: Infinity, allowClear: true, placeholder: 'Select value' });
  }

  // Reset Form Validation
  function resetCourseFormValidation() {
    courseForm.find('.is-invalid').removeClass('is-invalid');
    courseForm.find('.invalid-feedback').text('').hide();
    courseForm.find('.select2-container').removeClass('is-invalid');
    generalErrorDiv.text('').hide();
  }

  // Reset Form Content
  function resetCourseForm() {
    resetCourseFormValidation();
    courseForm[0]?.reset();
    courseIdInput.val('');
    courseMethodInput.val('POST');
    categorySelect.val(null).trigger('change');
    statusSelect.val(null).trigger('change');
    thumbnailInput.val(''); // Clear file input
    thumbnailPreviewArea.hide();
    thumbnailPreview.attr('src', '#');
    removeThumbnailCheckbox.prop('checked', false);
    courseOffcanvasLabel.text('Add New Course');
    submitCourseBtn.text('Submit').prop('disabled', false);
  }

  // Set Button Loading State
  function setCourseButtonLoading(isLoading) {
    const buttonText = courseIdInput.val() ? 'Update' : 'Submit';
    submitCourseBtn.prop('disabled', isLoading);
    submitCourseBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : buttonText);
  }

  // Display Validation Errors
  function displayCourseValidationErrors(errors) {
    resetCourseFormValidation();
    let firstErrorElement = null;
    console.log("Validation Errors:", errors); // For debugging
    for (const fieldName in errors) {
      let inputName = fieldName;
      let errorMessage = errors[fieldName][0];
      const inputElement = courseForm.find(`[name="${inputName}"], [name="${inputName}[]"]`);
      let targetElement = inputElement;
      let feedbackElement = targetElement.closest('.mb-3').find('.invalid-feedback');

      if (!inputElement.length) { generalErrorDiv.append(`<div>${escapeHtml(inputName)}: ${escapeHtml(errorMessage)}</div>`).show(); if (!firstErrorElement) firstErrorElement = generalErrorDiv; continue; }
      if (inputElement.hasClass('select2-hidden-accessible')) { targetElement = inputElement.siblings('.select2-container'); }
      else if (inputElement.attr('type') === 'file' || inputElement.is(':checkbox') || inputElement.is(':radio')) { targetElement = inputElement.closest('div'); } // Target container

      if (targetElement.length) {
        targetElement.addClass('is-invalid');
        if (feedbackElement.length) { feedbackElement.text(errorMessage).show(); }
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
  let dtCourses;
  if (coursesDataTableElement.length) {
    dtCourses = coursesDataTableElement.DataTable({
      processing: true, serverSide: true,
      ajax: { url: lmsCoursesListAjaxUrl, type: 'GET', data: function (d) { d.filter_category_id = filterCategorySelect.val(); d.filter_status = filterStatusSelect.val(); } },
      columns: [ { data: 'id', name: 'id' }, { data: 'title', name: 'title' }, { data: 'category_name', name: 'category.name', orderable: false }, { data: 'status', name: 'status' }, { data: 'estimated_duration', name: 'estimated_duration', orderable: false }, { data: 'lessons_count_display', name: 'lessons_count', className: 'text-center', searchable: false }, { data: 'enrollments_count_display', name: 'enrollments_count', className: 'text-center', searchable: false }, { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' } ],
      columnDefs: [ { targets: [3, 5, 6, 7], className: 'text-center' } ], order: [[1, 'asc']],
      dom: '<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: { sLengthMenu: '_MENU_', search: '', searchPlaceholder: 'Search Courses...' }, buttons: [],
    });
  }

  // --- Filter Changes ---
  filterCategorySelect.add(filterStatusSelect).on('change', function () { dtCourses?.ajax.reload(); });

  // --- Offcanvas Handling ---
  if(courseOffcanvasElement.length) { courseOffcanvasElement.on('hidden.bs.offcanvas', resetCourseForm); }

  $('#addCourseBtn').on('click', function () { resetCourseForm(); courseOffcanvasLabel.text('Add New Course'); thumbnailPreviewArea.hide(); courseOffcanvas?.show(); });

  coursesDataTableElement.on('click', '.edit-course', function () {
    const courseId = $(this).data('id'); const editUrl = $(this).data('url');
    resetCourseForm(); courseOffcanvasLabel.text('Loading Data...'); courseOffcanvas?.show();
    $.ajax({ url: editUrl, type: 'GET', dataType: 'json',
      success: function (response) {
      console.log(response);
        if (response.success && response.course) {
          const data = response.course;
          courseOffcanvasLabel.text('Edit Course: ' + (data.title || `#${data.id}`));
          courseIdInput.val(data.id); courseMethodInput.val('PUT');
          $('#courseTitle').val(data.title || ''); $('#lms_course_category_id').val(data.lms_course_category_id || '').trigger('change');
          $('#courseStatus').val(data.status || '').trigger('change'); $('#estimated_duration').val(data.estimated_duration || '');
          $('#courseDescription').val(data.description || '');

          // Handle thumbnail preview
          if (data.thumbnail_url) {
            thumbnailPreview.attr('src', data.thumbnail_url);
            thumbnailPreviewArea.show();
            thumbnailInput.val(''); // Clear file input value
            removeThumbnailCheckbox.prop('checked', false); // Ensure remove is unchecked initially
          } else {
            thumbnailPreviewArea.hide();
            thumbnailPreview.attr('src', '#');
            thumbnailInput.val('');
          }

        } else { courseOffcanvas?.hide(); showErrorToast(response.message || 'Failed to load course data.'); } // Use showErrorToast
      },
      error: function (jqXHR) { courseOffcanvas?.hide(); showErrorToast(jqXHR.responseJSON?.message || 'Could not load course data.'); } // Use showErrorToast
    });
  });

  // Thumbnail Preview on File Select
  thumbnailInput.on('change', function(e) {
    if (e.target.files && e.target.files[0]) {
      const reader = new FileReader();
      reader.onload = function (event) {
        thumbnailPreview.attr('src', event.target.result);
        thumbnailPreviewArea.show(); // Show preview area
        removeThumbnailCheckbox.prop('checked', false); // Uncheck remove if new file selected
      }
      reader.readAsDataURL(e.target.files[0]);
    } else {
      // If no file is selected (e.g., user cancels), maybe hide preview if it was previously shown
      // thumbnailPreviewArea.hide(); // Or keep existing preview if editing
    }
  });

  // --- Form Submission (Add/Edit) ---
  if (courseForm.length) {
    courseForm.on('submit', function(e) {
      e.preventDefault(); resetCourseFormValidation();
      const isUpdate = !!courseIdInput.val();
      const url = isUpdate ? `${lmsCoursesBaseUrl}/${courseIdInput.val()}` : lmsCoursesStoreUrl;
      const method = 'POST'; let formData = new FormData(courseForm[0]);
      if (isUpdate) { formData.append('_method', 'PUT'); formData.set('remove_thumbnail', removeThumbnailCheckbox.is(':checked') ? '1' : '0'); }
      else { formData.delete('remove_thumbnail'); }
      if(statusSelect.val()) { formData.set('status', statusSelect.val()); }

      setCourseButtonLoading(true);
      $.ajax({ url: url, method: method, data: formData, processData: false, contentType: false, dataType: 'json',
        success: function (response) {
          if(response.success) {
            courseOffcanvas?.hide();
            showSuccessToast(response.message); // Use specific success toast
            dtCourses?.ajax.reload(null, false);
          } else { showErrorToast(response.message || 'Operation failed.'); } // Use specific error toast
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
            displayCourseValidationErrors(jqXHR.responseJSON.errors);
            showErrorToast('Validation failed. Please check the form.'); // Use specific error toast
          } else { showErrorToast(jqXHR.responseJSON?.message || 'An error occurred.'); } // Use specific error toast
        },
        complete: function () { setCourseButtonLoading(false); }
      });
    });
  }

  // --- Delete Course Handling ---
  coursesDataTableElement.on('click', '.delete-course', function () {
    const button = $(this); const deleteUrl = button.data('url');
    const enrollmentCount = parseInt(button.data('enrollments') || 0, 10);
    if (enrollmentCount > 0) { Swal.fire({ icon: 'error', title: 'Cannot Delete', text: `This course has ${enrollmentCount} active enrollment(s). Please manage enrollments first.` }); return; } // Updated message
    // *** Use Swal ONLY for confirmation ***
    Swal.fire({ title: 'Are you sure?', text: "Delete this course? Associated lessons may also be deleted!", icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete it!', customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({ url: deleteUrl, method: 'POST', data: { _method: 'DELETE' }, dataType: 'json',
          success: function(response) {
            if (response.success) {
              showSuccessToast(response.message); // Use specific success toast
              dtCourses?.row(button.closest('tr')).remove().draw(false);
            } else { showErrorToast(response.message || 'Failed to delete.'); } // Use specific error toast
          },
          error: function(jqXHR) { showErrorToast(jqXHR.responseJSON?.message || 'Deletion failed.'); } // Use specific error toast
        });
      }
    });
  });

}); // End Document Ready
