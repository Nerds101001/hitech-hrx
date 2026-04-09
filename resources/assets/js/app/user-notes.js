'use strict';

$(function () { // jQuery document ready

  // --- Global Variables & Selectors ---
  const notesGridContainer = $('#notesGridContainer'); // jQuery selector
  const notesLoadingIndicator = $('#notesLoadingIndicator');
  const noNotesMessage = $('#noNotesMessage');
  let sortableGridInstance = null; // For notes grid reorder
  let sortableChecklistInstance = null; // For checklist item reorder
  let notesDataCache = [];

  // Offcanvas & Form Elements
  const noteOffcanvasElement = $('#noteOffcanvas');
  const noteOffcanvas = noteOffcanvasElement.length ? new bootstrap.Offcanvas(noteOffcanvasElement[0]) : null;
  const noteForm = $('#noteForm'); // jQuery selector
  const noteModalLabel = $('#noteOffcanvasLabel');
  const submitNoteBtn = $('#submitNoteBtn');
  const noteIdInput = $('#note_id');
  const noteMethodInput = $('#noteMethod');

  // Form Fields (jQuery Selectors)
  const noteTitleInput = $('#noteTitle');
  const noteContentInput = $('#noteContent');
  // Color input is now radio group: Select by name $('input[name="color"]:checked')
  const isPinnedCheckbox = $('#is_pinned');
  const tagsSelect = $('#tags');
  const checklistRepeaterContainer = $('#checklistRepeaterContainer');
  const addChecklistItemBtn = $('#addChecklistItemBtn');
  const checklistItemTemplate = $('#checklistItemTemplate'); // jQuery selector for template

  // Filters
  const filterViewSelect = $('#filter_view');
  const filterTagSelect = $('#filter_tag_id');
  const noteSearchInput = $('#noteSearch');

  // --- CSRF Setup ---
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } }); // Assumes csrfToken is global

  // --- Initialize Plugins ---

  // Select2 for Filters
  $('#filter_view, #filter_tag_id').select2({ minimumResultsForSearch: Infinity });

  // Select2 for Tags in Modal
  if (tagsSelect.length) {
    const initialUserTags = typeof userTags !== 'undefined' ? userTags : [];
    tagsSelect.select2({
      placeholder: 'Select or type tags',
      dropdownParent: $('#select2-tags-wrapper'), // Use the wrapper div ID
      tags: true, // Allow creating new tags
      tokenSeparators: [',', ' '],
      data: initialUserTags // This might not work as expected, usually pre-select via .val()
    });
  }

  // --- Helper Functions ---

  function showLoading(show) {
    if (show) {
      notesLoadingIndicator?.show();
      noNotesMessage?.hide();
      notesGridContainer?.empty(); // Clear grid
    } else {
      notesLoadingIndicator?.hide();
    }
  }

  function showNoNotesMessage(show) {
    noNotesMessage?.toggle(show);
  }

  function resetNoteFormValidation() {
    noteForm.find('.is-invalid').removeClass('is-invalid');
    noteForm.find('.invalid-feedback').text('');
    noteForm.find('.select2-container').removeClass('is-invalid'); // Clear Select2 error state
    $('#general-error').text('');
  }

  function resetNoteForm() {
    resetNoteFormValidation();
    noteForm[0]?.reset(); // Reset native form elements
    noteIdInput.val('');
    noteMethodInput.val('POST');
    isPinnedCheckbox.prop('checked', false); // Uncheck pinned
    tagsSelect.val(null).trigger('change'); // Clear Select2 tags
    // Reset Color Picker to Default ('')
    noteForm.find('input[name="color"][value=""]').prop('checked', true);
    noteModalLabel.text('Add Note');
    submitNoteBtn.text('Submit').prop('disabled', false);
    // Clear and add one empty checklist item row
    checklistRepeaterContainer.empty();
    addChecklistItemRow();
  }

  function setNoteButtonLoading(isLoading) {
    const buttonText = noteIdInput.val() ? 'Update' : 'Submit';
    submitNoteBtn.prop('disabled', isLoading);
    submitNoteBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : buttonText);
  }

  function displayNoteValidationErrors(errors) {
    resetNoteFormValidation();
    let firstErrorElement = null;
    console.log("Validation Errors:", errors); // Debugging

    for (const fieldName in errors) {
      let inputName = fieldName;
      let errorMessage = errors[fieldName][0];

      // Handle indexed checklist errors (e.g., checklist_items.0.content)
      if (fieldName.startsWith('checklist_items.')) {
        try {
          const parts = fieldName.split('.');
          const index = parseInt(parts[1], 10);
          const itemField = parts[2]; // 'content' or 'is_completed'
          const itemRow = checklistRepeaterContainer.find('.checklist-repeater-item').eq(index);
          const inputElement = itemRow.find(`[name$="[${itemField}]"]`); // Find input ending with [content] or [is_completed]
          const feedbackElement = inputElement.closest('.checklist-repeater-item').find('.invalid-feedback'); // Add feedback div if needed

          if (inputElement.length) {
            inputElement.addClass('is-invalid');
            if (feedbackElement.length) feedbackElement.text(errorMessage).show();
            else inputElement.after(`<div class="invalid-feedback d-block">${errorMessage}</div>`); // Add feedback div dynamically if missing

            if (!firstErrorElement) firstErrorElement = inputElement;
          } else {
            // Show general error if specific item field not found
            $('#checklist-general-error').text(errorMessage).show();
            if (!firstErrorElement) firstErrorElement = $('#checklist-general-error');
          }

        } catch (e) {
          console.error("Error processing checklist validation:", fieldName, e);
          $('#general-error').text(errorMessage); // Show as general error
          if (!firstErrorElement) firstErrorElement = $('#general-error');
        }
      } else {
        // Handle regular fields
        const inputElement = noteForm.find(`[name="${inputName}"], [name="${inputName}[]"]`); // Handle array name for tags
        let targetElement = inputElement;
        let feedbackElement = targetElement.closest('.mb-3, .col-md-6, .col-12').find('.invalid-feedback');

        if (inputElement.hasClass('select2-hidden-accessible')) {
          targetElement = inputElement.siblings('.select2-container'); // Target select2 container
        } else if (inputElement.attr('type') === 'color' || inputElement.attr('type') === 'radio' || inputElement.attr('type') === 'checkbox') {
          targetElement = inputElement.closest('div'); // Target container for color/radio/checkbox
        }


        if (targetElement.length) {
          targetElement.addClass('is-invalid');
          if(feedbackElement.length) {
            feedbackElement.text(errorMessage).show();
          } else {
            // If no feedback div, add one (adjust structure if needed)
            targetElement.after(`<div class="invalid-feedback d-block">${errorMessage}</div>`);
          }
          if (!firstErrorElement) firstErrorElement = targetElement;
        } else {
          // Fallback to general error if field not found
          $('#general-error').text((fieldName === 'general' ? '' : fieldName + ': ') + errorMessage).show();
          if (!firstErrorElement) firstErrorElement = $('#general-error');
        }
      }
    }
    // Focus first error
    if (firstErrorElement) {
      if (firstErrorElement.hasClass('select2-container')) {
        firstErrorElement.prev('select').select2('open');
      } else if (firstErrorElement.is(':visible')){ // Check visibility before focusing
        firstErrorElement.focus();
      } else {
        // Fallback if element is hidden (like in checklist)
        checklistRepeaterContainer.find('.is-invalid').first().focus();
      }
    }
  }

  // Basic HTML escaping helper
  function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // --- Checklist Repeater Logic ---
  let checklistIndexCounter = 0;

  function addChecklistItemRow(itemData = { id: '', content: '', is_completed: false }) {
    if (!checklistItemTemplate.length || !checklistRepeaterContainer.length) return;

    const newIndex = Date.now() + checklistIndexCounter++; // Use timestamp + counter for uniqueness
    const templateHtml = checklistItemTemplate.html();

    // Replace placeholders with unique names
    const newItemHtml = templateHtml
      .replace(/CHECKLIST_ITEM_COMPLETED_NAME/g, `checklist_items[${newIndex}][is_completed]`)
      .replace(/CHECKLIST_ITEM_COMPLETED_NAME_HIDDEN/g, `checklist_items[${newIndex}][is_completed]`) // Keep same name for hidden input logic
      .replace(/CHECKLIST_ITEM_ID_NAME/g, `checklist_items[${newIndex}][id]`)
      .replace(/CHECKLIST_ITEM_CONTENT_NAME/g, `checklist_items[${newIndex}][content]`);

    const newRow = $(newItemHtml); // Create jQuery object from HTML

    // Populate data
    newRow.find('input[name$="[id]"]').val(itemData.id || '');
    newRow.find('.checklist-item-content').val(itemData.content || '');
    const checkbox = newRow.find('.checklist-item-complete');
    const hiddenCompleteInput = newRow.find('input[type="hidden"][name$="[is_completed]"]');
    checkbox.prop('checked', itemData.is_completed || false);

    // Logic for hidden input representing unchecked value
    hiddenCompleteInput.prop('disabled', checkbox.is(':checked'));
    checkbox.on('change', function() {
      hiddenCompleteInput.prop('disabled', this.checked);
      newRow.toggleClass('completed', this.checked); // Toggle style on change
    });

    // Apply initial completed style
    if(itemData.is_completed) newRow.addClass('completed');

    checklistRepeaterContainer.append(newRow);
    newRow.find('.checklist-item-content').focus(); // Focus the new input

    // Re-initialize sortable for checklist items
    initChecklistSortable();
  }

  if (addChecklistItemBtn.length) {
    addChecklistItemBtn.on('click', () => addChecklistItemRow());
  }

  // Remove Checklist Item (using event delegation)
  checklistRepeaterContainer.on('click', '.remove-checklist-item', function() {
    $(this).closest('.checklist-repeater-item').remove();
  });

  // --- Notes Grid Rendering ---
  /**
   * Renders note cards into the grid container.
   * @param {Array} notes - Array of note objects from the API.
   */
  function renderNotesGrid(notes) {
    // Ensure notesGridContainer is treated as a jQuery object if defined with $()
    // If defined with getElementById, use notesGridContainerElement.innerHTML = ''; etc.
    // Assuming notesGridContainer is likely used as a jQuery object elsewhere:
    const jqNotesGridContainer = $('#notesGridContainer'); // Use jQuery selector consistently here

    if (!jqNotesGridContainer.length) return; // Check if element exists

    jqNotesGridContainer.empty(); // Clear previous notes using jQuery
    showNoNotesMessage(notes.length === 0);

    if (notes.length === 0) return;

    notes.forEach(note => {
      const cardHtml = createNoteCardHtml(note);
      // --- FIX: Use jQuery's .append() method ---
      jqNotesGridContainer.append(cardHtml);
      // --- END FIX ---
    });

    // Initialize tooltips for new elements if needed
    const tooltipTriggerList = [].slice.call(jqNotesGridContainer[0].querySelectorAll('[data-bs-toggle="tooltip"]')); // Get raw element for querySelectorAll
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Re-initialize SortableJS after rendering
    initSortable();
  }

  /**
   * Creates HTML string for a single note card.
   * @param {Object} note - Note data object.
   * @returns {string} - HTML string for the card.
   */
  function createNoteCardHtml(note) {
    const bgColor = note.color || '#ffffff';
    const textColor = getColorContrast(bgColor);
    const contrastClass = textColor === '#ffffff' ? 'text-light' : 'text-dark';
    const btnTextClass = contrastClass === 'text-light' ? 'text-white-50' : 'text-secondary';
    const deleteBtnClass = contrastClass === 'text-light' ? 'text-white' : 'text-danger';

    const titleDisplay = note.title ? `<h5 class="card-title mb-1">${escapeHtml(note.title)}${note.isPinned ? ' <i class="bx bxs-pin text-warning pin-icon" title="Pinned"></i>' : ''}</h5>` : '<h5 class="card-title mb-1">&nbsp;</h5>';
    const contentDisplay = note.contentSnippet ? `<p class="card-text note-card-content">${escapeHtml(note.contentSnippet)}</p>` : '';

    // Checklist Summary
    let checklistHtml = '';
    if (note.checklistItems && note.checklistItems.length > 0) {
      checklistHtml += '<ul class="note-card-checklist mt-2 ps-1">'; // Start list
      note.checklistItems.forEach(item => {
        const isChecked = item.isCompleted ? 'checked' : '';
        const itemClass = item.isCompleted ? 'completed' : '';
        // Note: Checkboxes are disabled as interaction should happen in edit view/modal
        checklistHtml += `
                    <li class="${itemClass}">
                        <input class="form-check-input" type="checkbox" value="" ${isChecked} disabled>
                        <label class="form-check-label ${contrastClass}">${escapeHtml(item.content)}</label>
                    </li>`;
      });
      checklistHtml += '</ul>'; // End list
    }

    // Tags
    let tagsHtml = '';
    if (note.tags && note.tags.length > 0) {
      tagsHtml = note.tags.map(tag => {
        const tagColor = tag.color || '#6c757d';
        const tagTextColor = getColorContrast(tagColor); // Use contrast for tag text too
        return `<span class="badge note-tag me-1" style="background-color: ${tagColor}; color: ${tagTextColor};">${escapeHtml(tag.name)}</span>`;
      }).join(' ');
      tagsHtml = `<div class="mb-2">${tagsHtml}</div>`;
    }

    // Actions
    const pinTitle = note.isPinned ? 'Unpin' : 'Pin';
    const pinIcon = note.isPinned ? 'bxs-pin text-warning' : 'bx-pin'; // Keep pin warning color
    const archiveTitle = note.archivedAt ? 'Unarchive' : 'Archive';
    const archiveIcon = note.archivedAt ? 'bx-archive-out' : 'bx-archive-in';
    const deleteButtonHtml = note.archivedAt ? '' : `<button type="button" class="btn btn-xs btn-icon note-delete-link ${deleteBtnClass}" title="Delete" data-id="${note.id}"><i class="bx bx-trash"></i></button>`;
    return `
             <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 note-item-col" data-id="${note.id}">
                 <div class="card note-card ${note.isPinned ? 'is-pinned' : ''} ${contrastClass}" style="background-color: ${bgColor}; color: ${textColor};">
                     <div class="card-body d-flex flex-column">
                         <div style="flex-grow: 1;">
                             ${titleDisplay}
                             <small class="note-card-time mb-2 d-block ${contrastClass}">Updated: ${note.updatedAtDiff}</small>
                             ${contentDisplay}
                             ${checklistHtml}
                             ${tagsHtml}
                         </div>
                         <div class="note-actions d-flex justify-content-end pt-1 mt-auto">
                             <button type="button" class="btn btn-xs btn-icon ${btnTextClass} note-edit-link" title="Edit" data-id="${note.id}"><i class="bx bx-pencil"></i></button>
                             <button type="button" class="btn btn-xs btn-icon ${btnTextClass} note-toggle-pin" title="${pinTitle}" data-id="${note.id}"><i class="bx ${pinIcon}"></i></button>
                             <button type="button" class="btn btn-xs btn-icon ${btnTextClass} note-toggle-archive" title="${archiveTitle}" data-id="${note.id}"><i class="bx ${archiveIcon}"></i></button>
                             ${deleteButtonHtml}
                         </div>
                     </div>
                 </div>
             </div>`;
  }

  // --- Fetching Notes Data ---
    function fetchNotes() {
      showLoading(true);

      const filters = {
        filter_view: filterViewSelect.val(), // Includes active, pinned, archived
        filter_tag_id: filterTagSelect.val(),
        search: noteSearchInput.val()?.trim() || ''
      };

      // Use GET request for fetching data
      $.ajax({
        url: notesGetDataUrl, // The new endpoint returning JSON array
        type: 'GET',
        data: filters,
        dataType: 'json',
        success: function(data) {
          console.log(data);
          showLoading(false);
          notesDataCache = data || []; // Update cache
          renderNotesGrid(notesDataCache); // Render the grid
        },
        error: function(jqXHR, textStatus, errorThrown) {
          console.error("Error fetching notes:", textStatus, errorThrown);
          showLoading(false);
          showNoNotesMessage(true); // Show no notes message on error
          Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load notes. Please try again.' });
        }
      });
    }

  // --- Initial Data Load ---
  fetchNotes();

  // --- Filter & Search Event Handlers ---
  filterViewSelect.add(filterTagSelect).on('change', fetchNotes);
  let searchTimeout;
  noteSearchInput.on('input', function() { clearTimeout(searchTimeout); searchTimeout = setTimeout(fetchNotes, 500); });

  // --- SortableJS Initialization ---
  function initSortable() {
    // Destroy previous instance if it exists, to prevent duplicates on re-render
    if (sortableGridInstance) {
      sortableGridInstance.destroy();
      sortableGridInstance = null; // Clear reference
    }

    // Check if Sortable library is loaded and container exists
    if (notesGridContainer.length && typeof Sortable !== 'undefined') {
      sortableGridInstance = new Sortable(notesGridContainer[0], { // Pass the raw DOM element
        animation: 150, // Animation speed
        handle: '.note-card', // Specify elements to be used as drag handles (the whole card)
        ghostClass: 'sortable-ghost', // Class for the placeholder animation
        chosenClass: 'sortable-chosen', // Class for the item being dragged
        filter: '.no-drag', // Elements that should not trigger dragging (if any)
        preventOnFilter: true, // Prevent dragging if filter applies
        // Called when sorting action ends (item is dropped)
        onEnd: function (evt) {
          // evt.oldIndex - element's old index within parent
          // evt.newIndex - element's new index within parent

          // Get the new order of note IDs from the data-id attributes
          const itemElements = notesGridContainer.find('.note-item-col'); // Get columns in new order
          const newOrderIds = itemElements.map(function() {
            return $(this).data('id'); // Extract data-id from each column
          }).get(); // Convert jQuery map result to a standard array

          // Send the new order to the backend
          updateNoteOrder(newOrderIds);
        },
      });
    } else if (typeof Sortable === 'undefined') {
      console.warn("SortableJS library not found. Grid reordering disabled.");
    }
  }
  function updateNoteOrder(orderedIds) {
    // --- IMPORTANT: Define this route and controller method in Laravel ---
    const reorderUrl = `${notesBaseUrl}/reorder`;
    // --------------------------------------------------------------------

    console.log('Attempting to save new note order:', orderedIds); // For debugging

    // Show a subtle loading state maybe? (Optional)
    // Example: Add a temporary class to the grid container

    $.ajax({
      url: reorderUrl,
      method: 'POST', // Using POST as per user standard
      data: {
        orderedIds: orderedIds, // Send the array of IDs
        // _token: csrfToken // Handled by global $.ajaxSetup
      },
      dataType: 'json', // Expect JSON response
      success: function(response) {
        if (response.success) {
          showNotification('success', response.message || 'Note order saved.');
          // Optional: Update local cache order? Usually not needed if fetchNotes is called elsewhere
          // notesDataCache = reorderCache(notesDataCache, orderedIds); // Example client-side cache update
        } else {
          showNotification('error', response.message || 'Failed to save order.');
          // Revert visual order or simply refetch on failure? Refetch is safer.
          fetchNotes();
        }
      },
      error: function(jqXHR) {
        console.error('Error saving note order:', jqXHR);
        showNotification('error', 'Could not save new order. Please try again.');
        fetchNotes(); // Refetch to ensure consistency after error
      },
      complete: function() {
        // Hide loading state if shown
      }
    });
  }

  // --- Checklist Item Sortable ---
  function initChecklistSortable() {
    if (sortableChecklistInstance) { sortableChecklistInstance.destroy(); }
    if (checklistRepeaterContainer.length && typeof Sortable !== 'undefined') {
      sortableChecklistInstance = new Sortable(checklistRepeaterContainer[0], { // Pass raw DOM element
        handle: '.handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        // onEnd: function (evt) { /* TODO: Save checklist item order if needed */ }
      });
    }
  }

  // --- Offcanvas & Form Logic ---
  if(noteOffcanvasElement) { noteOffcanvasElement.on('hidden.bs.offcanvas', resetNoteForm); }
  $('#addNoteBtn').on('click', function () {
    resetNoteForm();
    noteModalLabel.textContent = 'Add New Note';
    $('#checklistInputArea').closest('.mb-3').show(); // Ensure checklist area is visible for add
    noteOffcanvas?.show();
  });

  // Edit Note Link Click
  $(document).on('click', '.note-edit-link', function () {
    const noteId = $(this).data('id');
    const editUrl = `${notesBaseUrl}/${noteId}/edit`;

    resetNoteForm(); // Resets checklist area too
    noteModalLabel.text('Loading Data...');
    noteOffcanvas?.show();

    $.ajax({
      url: editUrl, type: 'GET',
      success: function (response) {
        console.log(response);
        if (response.success && response.data) {
          const data = response.data;
          noteModalLabel.text('Edit Note #' + data.id);
          noteIdInput.val(data.id);
          noteMethodInput.val('PUT');
          noteTitleInput.val(data.title || '');
          noteContentInput.val(data.content || '');
          // Set Color Radio
          noteForm.find(`input[name="color"][value="${data.color || ''}"]`).prop('checked', true);
          isPinnedCheckbox.prop('checked', data.isPinned || false);
          tagsSelect.val(data.tagIds || []).trigger('change');

          // Populate Checklist Repeater
          checklistRepeaterContainer.empty(); // Clear default/previous items
          if (data.checklistItems && data.checklistItems.length > 0) {
            data.checklistItems.forEach(item => addChecklistItemRow(item));
          } else {
            // Optionally add one empty row if editing a note with no checklist
            // addChecklistItemRow();
          }
          initChecklistSortable(); // Re-init sortable

        }else {
          // Handle case where backend returned success:false or no data
          noteOffcanvas?.hide();
          Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to load note data for editing.' });
        }
      },
      error: function (jqXHR) {
        console.error("Error fetching note for edit:", jqXHR);
        noteModalLabel.text('Edit Note'); // Reset title
        noteOffcanvas?.hide();
        Swal.fire({ icon: 'error', title: 'Error', text: jqXHR.responseJSON?.message || 'Could not load note data.' });
      }
    });
  });

  // --- Form Submission (Add/Edit) ---
  if (noteForm) {
    noteForm.on('submit', function(e) { // Use jQuery event handler
      e.preventDefault();
      resetNoteFormValidation();

      const isUpdate = !!noteIdInput.val();
      const url = isUpdate ? `${notesBaseUrl}/${noteIdInput.val()}/update` : notesStoreUrl; // Correct update URL
      const method = 'POST';

      // --- Sending Data as JSON is easier with nested checklist ---
      let payload = {
        title: noteTitleInput.val()?.trim() || '',
        content: noteContentInput.val()?.trim() || '',
        color: noteForm.find('input[name="color"]:checked').val() || null,
        is_pinned: isPinnedCheckbox.is(':checked') ? 1 : 0,
        tags: tagsSelect.val() || [],
        checklist_items: []
      };

      // Collect checklist items
      checklistRepeaterContainer.find('.checklist-repeater-item').each(function(index) {
        const itemRow = $(this);
        const content = itemRow.find('.checklist-item-content').val()?.trim();
        if (content) { // Only add items with content
          payload.checklist_items.push({
            // Include ID only if it exists (for update identification later)
            id: itemRow.find('input[type="hidden"][name$="[id]"]').val() || null,
            content: content,
            is_completed: itemRow.find('.checklist-item-complete').is(':checked') ? 1 : 0,
            order_column: index // Optional: Add order column if needed
          });
        }
      });

      if (isUpdate) { payload._method = 'PUT'; }

      setNoteButtonLoading(true);

      $.ajax({
        url: url, method: method,
        data: JSON.stringify(payload), // Send data as JSON string
        contentType: 'application/json; charset=utf-8', // Set content type
        dataType: 'json', // Expect JSON response
        success: function (response) {
          if(response.success) {
            noteOffcanvas?.hide();
            Swal.fire({ icon: 'success', title: 'Success!', text: response.message, timer: 1500, showConfirmButton: false });
            fetchNotes(); // Refetch data to update grid
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response.message || 'Operation failed. Please check your input.' // Show backend message or default
            });
            // Optional: Display general error if available
            if(response.errors && response.errors.general) {
              $('#general-error').text(response.errors.general[0]).show();
            }
          }
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
            // Handle Validation Errors specifically
            displayNoteValidationErrors(jqXHR.responseJSON.errors);
            showNotification('error', jqXHR.responseJSON.message || 'Please correct the validation errors.'); // Show general validation message
          } else {
            // Handle other errors (500, network, etc.)
            console.error("Error saving note:", jqXHR.responseText); // Log full error
            const errorMsg = jqXHR.responseJSON?.message || 'An unexpected error occurred. Please try again later.';
            Swal.fire({
              icon: 'error',
              title: 'Request Failed',
              text: errorMsg
            });
          }
        },
        complete: function () { setNoteButtonLoading(false); }
      });
    });
  } // end if noteForm

  /**
   * Generic handler for performing AJAX actions on notes (Pin, Archive, Delete)
   * after showing a confirmation dialog.
   * Assumes backend routes are set up to handle POST requests, potentially with _method override.
   *
   * @param {string} url - The specific endpoint URL for the action.
   * @param {string} httpMethodOverride - The intended HTTP method ('POST', 'DELETE', etc.) to send via _method.
   * @param {string} confirmTitle - The title for the confirmation dialog.
   * @param {string} confirmText - The body text for the confirmation dialog.
   * @param {function} successCallback - Function to execute on successful AJAX response, receives response data.
   */
  function handleNoteAction(url, httpMethodOverride, confirmTitle, confirmText, successCallback) {
    // Default confirmation settings
    const confirmButtonText = httpMethodOverride === 'DELETE' ? 'Yes, delete it!' : 'Yes, proceed!';
    const confirmButtonClass = httpMethodOverride === 'DELETE' ? 'btn btn-danger me-3' : 'btn btn-primary me-3';

    Swal.fire({
      title: confirmTitle || 'Are you sure?',
      text: confirmText || "Do you want to proceed with this action?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: confirmButtonText,
      cancelButtonText: 'Cancel',
      customClass: {
        confirmButton: confirmButtonClass,
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false,
      focusCancel: true // Focus cancel button by default
    }).then(function (result) {
      if (result.isConfirmed) {
        // Prepare AJAX data - send _method for non-POST actions if needed by backend router on POST route
        let requestData = {};
        if (httpMethodOverride !== 'POST') {
          requestData._method = httpMethodOverride;
        }
        // Add CSRF token if not handled globally by $.ajaxSetup
        // requestData._token = csrfToken;

        $.ajax({
          url: url,
          method: 'POST', // Always use POST as per user standard
          data: requestData, // Send _method override in data payload
          dataType: 'json', // Expect JSON response
          beforeSend: function() {
            // Optional: Add a general loading indicator if desired
            // Example: Show a loading overlay
          },
          success: function(response) {
            if (response.success) {
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message || 'Action completed successfully.',
                timer: 1500,
                showConfirmButton: false,
                customClass: { confirmButton: 'btn btn-success' } // For styling consistency if button shown
              });
              // Execute the success callback function passed in
              if (typeof successCallback === 'function') {
                successCallback(response);
              }
            } else {
              // Handle cases where backend returns { success: false }
              Swal.fire({
                icon: 'error',
                title: 'Action Failed',
                text: response.message || 'The requested action could not be completed.',
                customClass: { confirmButton: 'btn btn-danger' }
              });
            }
          },
          error: function(jqXHR) {
            // Handle AJAX errors (4xx, 5xx)
            console.error(`Error performing action on ${url}:`, jqXHR.responseText);
            const errorMsg = jqXHR.responseJSON?.message || 'An unexpected error occurred. Please try again.';
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: errorMsg,
              customClass: { confirmButton: 'btn btn-danger' }
            });
          },
          complete: function() {
            // Optional: Hide general loading indicator if shown
          }
        });
      }
    });
  }
  // Toggle Pin
  $(document).on('click', '.note-toggle-pin', function () {
    const button = $(this);
    const noteId = button.data('id');
    const url = `${notesBaseUrl}/${noteId}/toggle-pin`;
    handleNoteAction(url, 'POST', 'Toggle Pin?', '', (response) => {
      fetchNotes(); // Simple refresh
      // Or: update pin icon dynamically on card
      // button.find('i').toggleClass('bx-pin bxs-pin text-warning');
      // button.closest('.note-card').toggleClass('is-pinned', response.isPinned);
    });
  });

  // Toggle Archive
  $(document).on('click', '.note-toggle-archive', function () {
    const button = $(this);
    const noteId = button.data('id');
    const isArchived = button.find('i').hasClass('bx-archive-out');
    const url = `${notesBaseUrl}/${noteId}/toggle-archive`;
    const confirmTitle = isArchived ? 'Restore Note?' : 'Archive Note?';
    handleNoteAction(url, 'POST', confirmTitle, '', (response) => {
      fetchNotes(); // Reload needed to reflect filter state
    });
  });

  // Delete Note
  $(document).on('click', '.note-delete-link', function () {
    const button = $(this);
    const noteId = button.data('id');
    const url = `${notesBaseUrl}/${noteId}/delete`; // POST route for delete action
    handleNoteAction(url, 'DELETE', 'Delete Note?', "Cannot be undone!", (response) => {
      // Optional: Optimistic UI update
      button.closest('.note-item-col').remove();
      // fetchNotes(); // Can still fetch to ensure consistency
    });
  });

  /**
   * Determines if white or black text should be used based on hex background color.
   * @param {string} hexColor - The background hex color (e.g., '#ffffff', '#2c3e50'). Defaults to white if invalid.
   * @returns {string} - Returns '#ffffff' (white) or '#333333' (dark grey).
   */
  function getColorContrast(hexColor) {
    if (!hexColor || hexColor === '') hexColor = '#ffffff'; // Default to white background if no color

    hexColor = hexColor.replace('#', '');
    let r, g, b;

    if (hexColor.length === 3) {
      r = parseInt(hexColor.substring(0, 1).repeat(2), 16);
      g = parseInt(hexColor.substring(1, 1).repeat(2), 16);
      b = parseInt(hexColor.substring(2, 1).repeat(2), 16);
    } else if (hexColor.length === 6) {
      r = parseInt(hexColor.substring(0, 2), 16);
      g = parseInt(hexColor.substring(2, 4), 16);
      b = parseInt(hexColor.substring(4, 6), 16);
    } else {
      return '#333333'; // Default dark text for invalid format
    }

    // Calculate luminance (simplified formula)
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    // Return white text for dark backgrounds, dark text for light backgrounds
    return luminance > 0.5 ? '#333333' : '#ffffff';
  }

  function showNotification(icon, title) { /* ... SweetAlert Toast ... */ }

}); // End Document Ready
