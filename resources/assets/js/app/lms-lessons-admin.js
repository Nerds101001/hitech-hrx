'use strict';

$(function () { // jQuery document ready

  // --- Global Variables & Selectors ---
  const lessonsListContainer = $('#lessonsListContainer'); // Target for SortableJS and list items
  const noLessonsMessage = $('#noLessonsMessage'); // Message when list is empty
  let sortableLessonsInstance = null; // To hold SortableJS instance
  let sortableChecklistInstance = null; // To hold SortableJS instance for checklist

  // Offcanvas & Form Elements
  const lessonOffcanvasElement = $('#lessonOffcanvas');
  const lessonOffcanvas = lessonOffcanvasElement.length ? new bootstrap.Offcanvas(lessonOffcanvasElement[0]) : null;
  const lessonForm = $('#lessonForm');
  const lessonOffcanvasLabel = $('#lessonOffcanvasLabel');
  const submitLessonBtn = $('#submitLessonBtn');
  const lessonIdInput = $('#lesson_id');
  const lessonMethodInput = $('#lessonMethod');

  // Form Fields
  const lessonTitleInput = $('#lessonTitle');
  const contentTypeSelect = $('#content_type'); // Select2
  const contentTextGroup = $('#contentTextGroup');
  const contentTextInput = $('#content_text');
  const contentFileGroup = $('#contentFileGroup');
  const lessonFileInput = $('#lesson_file');
  const currentFileDisplay = $('#currentLessonFileDisplay');
  const removeFileCheckArea = $('#removeFileCheckArea');
  const removeFileCheckbox = $('#remove_file');
  const generalErrorDiv = $('#general-error'); // General error display in form

  // Buttons
  const addLessonBtn = $('#addLessonBtn');

  // --- CSRF Setup ---
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } }); // Assumes csrfToken is global via Blade

  // --- Initialize Plugins ---

  // Select2 for Content Type in Offcanvas
  if (contentTypeSelect.length) {
    contentTypeSelect.select2({
      placeholder: 'Select Content Type...',
      dropdownParent: $(lessonOffcanvasElement).find('.offcanvas-body'), // Attach to offcanvas
      minimumResultsForSearch: Infinity // No search needed
    });
  }

  // --- Helper Functions ---

  // Show/Hide specific content input group based on selected type
  function toggleContentInputs(selectedType) {
    contentTextGroup.hide();
    contentFileGroup.hide();
    // Clear validation from potentially hidden fields
    contentTextInput.removeClass('is-invalid').siblings('.invalid-feedback').text('');
    lessonFileInput.removeClass('is-invalid').siblings('.invalid-feedback').text('');
    // Clear content of inactive fields
    // contentTextInput.val(''); // Decide if you want to clear text when switching type
    // lessonFileInput.val(''); // Cannot easily clear file input reliably, backend ignores if type changes

    if (!selectedType) return; // Do nothing if no type selected

    if (['text', 'video_embed', 'link'].includes(selectedType)) {
      contentTextGroup.show();
      // Update placeholder/label if needed based on type
      let placeholder = 'Enter text content...';
      let label = 'Content';
      if (selectedType === 'video_embed') { placeholder = 'Paste YouTube/Vimeo embed code...'; label = 'Embed Code';}
      else if (selectedType === 'link') { placeholder = 'Enter full URL starting with https://...'; label = 'External Link URL';}
      contentTextGroup.find('label').text(`${label} *`); // Update label
      contentTextInput.attr('placeholder', placeholder);

    } else if (['file', 'video_file'].includes(selectedType)) {
      contentFileGroup.show();
    }
    // Add handling for 'quiz' or other types later if needed
  }

  // Reset Validation
  function resetLessonFormValidation() {
    lessonForm.find('.is-invalid').removeClass('is-invalid');
    lessonForm.find('.invalid-feedback').text('').hide();
    lessonForm.find('.select2-container').removeClass('is-invalid');
    generalErrorDiv.text('').hide();
  }

  // Reset Form Content
  function resetLessonForm() {
    resetLessonFormValidation();
    lessonForm[0]?.reset();
    lessonIdInput.val('');
    lessonMethodInput.val('POST');
    contentTypeSelect.val(null).trigger('change'); // Reset select2
    toggleContentInputs(''); // Hide conditional inputs
    currentFileDisplay.text('').hide();
    removeFileCheckArea.hide();
    lessonOffcanvasLabel.text('Add Lesson');
    submitLessonBtn.text('Submit').prop('disabled', false);
  }

  // Set Button Loading State
  function setLessonButtonLoading(isLoading) {
    const buttonText = lessonIdInput.val() ? 'Update' : 'Submit';
    submitLessonBtn.prop('disabled', isLoading);
    submitLessonBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : buttonText);
  }

  // Display Validation Errors
  function displayLessonValidationErrors(errors) {
    resetLessonFormValidation();
    let firstErrorElement = null;
    console.log("Validation Errors:", errors);
    for (const fieldName in errors) {
      let inputName = fieldName;
      let errorMessage = errors[fieldName][0];
      // Adjust for file input name if backend uses 'lesson_file'
      if (inputName === 'lesson_file') inputName = 'lesson_file';

      const inputElement = lessonForm.find(`[name="${inputName}"]`);
      let targetElement = inputElement;
      let feedbackElement = targetElement.closest('.mb-3').find('.invalid-feedback');

      if (!inputElement.length) { generalErrorDiv.append(`<div>${escapeHtml(inputName)}: ${escapeHtml(errorMessage)}</div>`).show(); if (!firstErrorElement) firstErrorElement = generalErrorDiv; continue; }
      if (inputElement.hasClass('select2-hidden-accessible')) { targetElement = inputElement.siblings('.select2-container'); }
      else if (inputElement.attr('type') === 'file') { targetElement = inputElement; } // Target file input directly

      if (targetElement.length) {
        targetElement.addClass('is-invalid');
        if(feedbackElement.length) { feedbackElement.text(errorMessage).show(); }
        else { targetElement.after(`<div class="invalid-feedback d-block">${errorMessage}</div>`); }
        if (!firstErrorElement) firstErrorElement = targetElement;
      }
    }
    firstErrorElement?.focus();
    if (firstErrorElement?.hasClass('select2-container')) { firstErrorElement.prev('select').select2('open'); }
  }

  // Basic HTML escaping helper
  function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  // --- Initialize SortableJS for Lesson List ---
  function initLessonSortable() {
    if (sortableChecklistInstance) { sortableChecklistInstance.destroy(); } // Use the correct variable name
    if (lessonsListContainer.length && typeof Sortable !== 'undefined') {
      sortableChecklistInstance = new Sortable(lessonsListContainer[0], { // Target the main list container
        handle: '.lesson-handle', // Drag using the handle icon
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function (evt) {
          // Get the new order of lesson IDs
          const itemElements = lessonsListContainer.find('.lesson-item'); // Get ordered items
          const newOrderIds = itemElements.map(function() {
            return $(this).data('lesson-id');
          }).get(); // Convert to array

          // Send the new order to the backend
          saveLessonOrder(newOrderIds);
        }
      });
    } else if (typeof Sortable === 'undefined') {
      console.warn("SortableJS library not found. Lesson reordering disabled.");
    }
  }
  // Initial call for SortableJS on page load
  initLessonSortable();

  // --- Save Lesson Order ---
  function saveLessonOrder(orderedIds) {
    // Show loading indicator maybe?
    $.ajax({
      url: lessonsReorderUrl, // URL from Blade vars
      method: 'POST',
      data: {
        orderedLessonIds: orderedIds,
        // _token: csrfToken // Handled by setup
      },
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          showSuccessToast(response.message || 'Lesson order saved.');
          // No need to visually reorder, SortableJS already did it.
          // Optional: refetch data if backend modifies anything else on reorder
        } else {
          showErrorToast(response.message || 'Failed to save order.');
          // Revert visually? Reloading is safest fallback.
          location.reload(); // Reload on error to reset order
        }
      },
      error: function(jqXHR) {
        console.error('Error saving lesson order:', jqXHR);
        showErrorToast('Could not save new order.');
        location.reload(); // Reload on error
      }
    });
  }

  // --- Offcanvas Handling ---
  if(lessonOffcanvasElement.length) { lessonOffcanvasElement.on('hidden.bs.offcanvas', resetLessonForm); }

  // Add Lesson Button Click
  $('#addLessonBtn').on('click', function () {
    resetLessonForm();
    lessonOffcanvasLabel.text('Add New Lesson');
    contentTypeSelect.val('').trigger('change'); // Ensure initial type is selected & triggers hide/show
    toggleContentInputs(''); // Ensure fields are hidden initially
    lessonOffcanvas?.show();
  });

  // Edit Lesson Button Click (Event Delegation)
  lessonsListContainer.on('click', '.edit-lesson', function () {
    const lessonId = $(this).data('id');
    // Construct URL using base and ID
    const editUrl = `${lessonsBaseActionUrl}/${lessonId}/edit`; // Assumes /lms/lessons/{id}/edit route

    resetLessonForm();
    lessonOffcanvasLabel.text('Loading Lesson Data...');
    lessonOffcanvas?.show();

    $.ajax({
      url: editUrl, type: 'GET', dataType: 'json',
      success: function (response) {
        if (response.success && response.lesson) {
          const data = response.lesson;
          lessonOffcanvasLabel.text('Edit Lesson: ' + (data.title || `#${data.id}`));
          lessonIdInput.val(data.id);
          lessonMethodInput.val('PUT'); // Set method override for update

          lessonTitleInput.val(data.title || '');
          contentTypeSelect.val(data.content_type || '').trigger('change'); // Set and trigger change
          contentTextInput.val(data.content_text || '');
          // Display current file info (if applicable)
          if(data.current_file_name) {
            currentFileDisplay.text(`Current file: ${data.current_file_name}`).show();
            removeFileCheckArea.show(); // Show remove option
            removeFileCheckbox.prop('checked', false); // Uncheck remove by default
          } else {
            currentFileDisplay.text('').hide();
            removeFileCheckArea.hide();
          }
          // Important: Call toggleContentInputs AFTER setting content type value
          toggleContentInputs(data.content_type);

        } else { lessonOffcanvas?.hide(); showErrorToast(response.message || 'Failed to load lesson data.'); }
      },
      error: function (jqXHR) { console.error("Edit error:", jqXHR); lessonOffcanvas?.hide(); showErrorToast('Could not load lesson data.'); }
    });
  });

  // --- Conditional Input Display based on Content Type ---
  contentTypeSelect.on('change', function() {
    toggleContentInputs($(this).val());
  });

  // --- Form Submission (Add/Edit) ---
  if (lessonForm.length) {
    lessonForm.on('submit', function(e) {
      e.preventDefault();
      resetLessonFormValidation();

      const isUpdate = !!lessonIdInput.val();
      // Construct URL based on whether it's an update or store
      const url = isUpdate ? `${lessonsBaseActionUrl}/${lessonIdInput.val()}` : lessonsStoreUrl;
      const method = 'POST'; // Always POST
      let formData = new FormData(lessonForm[0]); // Use FormData for file uploads

      if (isUpdate) {
        formData.append('_method', 'PUT');
        // Ensure remove_file value is handled correctly
        if (!removeFileCheckbox.is(':checked')) { formData.delete('remove_file'); }
        else { formData.set('remove_file', '1'); }
      } else {
        formData.delete('remove_file'); // Don't send on create
      }

      // Clear unnecessary content field based on type before sending
      const selectedType = contentTypeSelect.val();
      if (['text', 'video_embed', 'link'].includes(selectedType)) {
        formData.delete('lesson_file'); // Remove file if text/link/embed type selected
      } else if (['file', 'video_file'].includes(selectedType)) {
        // Remove text content if file type selected (unless text is used for something else)
        if(formData.get('content_text') === '') formData.delete('content_text'); // Only remove if empty? Or always? Backend should handle based on type.
        // If no new file is chosen on update AND remove isn't checked, backend should ignore 'lesson_file'
        if (isUpdate && lessonFileInput[0].files.length === 0 && !removeFileCheckbox.is(':checked')) {
          formData.delete('lesson_file');
        }
      } else { // Quiz or other types might not need either
        formData.delete('content_text');
        formData.delete('lesson_file');
      }


      setLessonButtonLoading(true);

      $.ajax({
        url: url, method: method, data: formData,
        processData: false, contentType: false, dataType: 'json', // Needed for FormData
        success: function (response) {
          if (response.success) {
            lessonOffcanvas?.hide();
            showSuccessToast(response.message);
            location.reload(); // Reload the whole page to update the lesson list simply
            // TODO: Implement dynamic row add/update later for better UX
          } else { showErrorToast(response.message || 'Operation failed.'); }
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) { displayLessonValidationErrors(jqXHR.responseJSON.errors); showErrorToast('Validation failed.');}
          else { showErrorToast(jqXHR.responseJSON?.message || 'An error occurred.'); }
        },
        complete: function () { setLessonButtonLoading(false); }
      });
    });
  } // end if lessonForm

  // --- Delete Lesson Handling ---
  lessonsListContainer.on('click', '.delete-lesson', function () {
    const button = $(this);
    const lessonId = button.data('id');
    const deleteUrl = `${lessonsBaseActionUrl}/${lessonId}`; // Construct URL using base

    Swal.fire({
      title: 'Are you sure?', text: "Delete this lesson? This cannot be undone!", icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Yes, delete it!',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: deleteUrl, method: 'POST', data: { _method: 'DELETE' }, dataType: 'json',
          success: function(response) {
            if (response.success) {
              showSuccessToast(response.message);
              button.closest('.lesson-item').remove(); // Remove item from list visually
              if (lessonsListContainer.find('.lesson-item').length === 0) {
                // Show no lessons message if list is now empty
                lessonsListContainer.html('<div id="noLessonsMessage" class="text-center text-muted p-4">No lessons added yet.</div>');
              }
            } else { showErrorToast(response.message || 'Failed to delete.'); }
          },
          error: function(jqXHR) { showErrorToast(jqXHR.responseJSON?.message || 'Deletion failed.'); }
        });
      }
    });
  });


}); // End Document Ready
