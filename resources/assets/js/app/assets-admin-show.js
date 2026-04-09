
'use strict';

$(function () { // jQuery document ready

  // --- Selectors (Using jQuery) ---
  // Edit Asset Offcanvas
  const assetOffcanvasElement = $('#assetOffcanvas');
  const assetOffcanvas = assetOffcanvasElement.length ? new bootstrap.Offcanvas(assetOffcanvasElement[0]) : null;
  const assetForm = $('#assetForm');
  const assetOffcanvasLabel = $('#assetOffcanvasLabel');
  const submitAssetBtn = $('#submitAssetBtn');
  const assetIdInput = $('#asset_id'); // Hidden input in edit form
  const assetMethodInput = $('#assetMethod'); // Hidden input for _method
  const categorySelect = $('#asset_category_id');
  const statusSelect = $('#status');
  const conditionSelect = $('#condition');
  const purchaseDateInput = $('#purchase_date'); // Use jQuery selector
  const warrantyExpiryInput = $('#warranty_expiry_date'); // Use jQuery selector
  const purchaseCostInput = $('#purchase_cost');

  // Assign Asset Modal
  const assignAssetModalElement = $('#assignAssetModal');
  const assignAssetModal = assignAssetModalElement.length ? new bootstrap.Modal(assignAssetModalElement[0]) : null;
  const assignAssetForm = $('#assignAssetForm');
  const assignSubmitBtn = $('#submitAssignBtn');
  const assignAssetIdInput = $('#assign_asset_id');
  const assignAssetNameTag = $('#assignAssetNameTag');
  const assignUserIdSelect = $('#assignUserId');
  const assignConditionOutSelect = $('#conditionOut');
  const assignAssignedAtInput = $('#assignedAt');
  const assignExpectedReturnInput = $('#expectedReturnDate');
  const assignGeneralError = $('#assign-general-error'); // Specific error display

  // Return Asset Modal
  const returnAssetModalElement = $('#returnAssetModal');
  const returnAssetModal = returnAssetModalElement.length ? new bootstrap.Modal(returnAssetModalElement[0]) : null;
  const returnAssetForm = $('#returnAssetForm');
  const returnSubmitBtn = $('#submitReturnBtn');
  const returnAssetIdInput = $('#return_asset_id');
  const returnAssetNameTag = $('#returnAssetNameTag');
  const returnCurrentAssignee = $('#returnCurrentAssignee');
  const returnConditionInSelect = $('#conditionIn');
  const returnReturnedAtInput = $('#returnedAt');
  const returnGeneralError = $('#return-general-error'); // Specific error display

  // === NEW: Maintenance Modal Selectors ===
  const maintenanceLogModalElement = document.getElementById('maintenanceLogModal');
  const maintenanceLogModal = maintenanceLogModalElement ? new bootstrap.Modal(maintenanceLogModalElement) : null;
  const maintenanceLogForm = document.getElementById('maintenanceLogForm'); // Native form element
  const submitMaintenanceBtn = document.getElementById('submitMaintenanceBtn');
  const maintenanceTypeSelect = $('#maintenance_type'); // jQuery for Select2
  const performedAtInput = document.getElementById('performed_at'); // Flatpickr
  const costInput = document.getElementById('cost'); // CleaveJS
  const providerInput = document.getElementById('provider');
  const detailsInput = document.getElementById('details');
  const nextDueDateInput = document.getElementById('next_due_date'); // Flatpickr
  const updateAssetStatusCheckbox = document.getElementById('update_asset_status');
  const newStatusArea = document.getElementById('new_status_area');
  const newAssetStatusSelect = $('#new_asset_status'); // jQuery for Select2
  const maintenanceGeneralError = $(maintenanceLogForm).find('.general-error-message'); // Assumes class added


  // Buttons on this page triggering modals
  const editAssetBtnDetailsPage = $('#editAssetBtnDetailsPage');
  const assignAssetBtnDetailsPage = $('#assignAssetBtnDetailsPage');
  const returnAssetBtnDetailsPage = $('#returnAssetBtnDetailsPage');

  // --- CSRF Setup ---
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

  // --- Initialize Plugins for Modals/Offcanvas ---
  function initializeModalPlugins() {
    // Edit Asset Offcanvas Plugins
    if (assetOffcanvasElement.length) {
      const offcanvasBody = assetOffcanvasElement.find('.offcanvas-body');
      $('#asset_category_id').select2({ placeholder: 'Select Category', dropdownParent: offcanvasBody, allowClear: true });
      $('#status').select2({ placeholder: 'Select Status', dropdownParent: offcanvasBody, minimumResultsForSearch: Infinity });
      $('#condition').select2({ placeholder: 'Select Condition', dropdownParent: offcanvasBody, allowClear: true, minimumResultsForSearch: Infinity });
      if (purchaseDateInput.length) flatpickr(purchaseDateInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });
      if (warrantyExpiryInput.length) flatpickr(warrantyExpiryInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });
      if (purchaseCostInput.length && typeof Cleave !== 'undefined') { new Cleave(purchaseCostInput[0], { numeral: true, numeralThousandsGroupStyle: 'thousand' }); }
    }
    // Assign Asset Modal Plugins
    if (assignAssetModalElement.length) {
      $('#assignUserId').select2({ placeholder: 'Select Employee', dropdownParent: assignAssetModalElement });
      $('#conditionOut').select2({ placeholder: 'Select Condition (Optional)', dropdownParent: assignAssetModalElement, allowClear: true, minimumResultsForSearch: Infinity });
      if (assignAssignedAtInput.length) flatpickr(assignAssignedAtInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', defaultDate: 'today' });
      if (assignExpectedReturnInput.length) flatpickr(assignExpectedReturnInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', minDate: 'today' });
    }
    // Return Asset Modal Plugins
    if (returnAssetModalElement.length) {
      $('#conditionIn').select2({ placeholder: 'Select Condition', dropdownParent: returnAssetModalElement, minimumResultsForSearch: Infinity });
      if (returnReturnedAtInput.length) flatpickr(returnReturnedAtInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', defaultDate: 'today', maxDate: 'today' });
    }

    // Maintenance Log Modal Plugins
    if (maintenanceLogModalElement) {
      $('#maintenance_type').select2({ placeholder: 'Select Type', dropdownParent: $(maintenanceLogModalElement), minimumResultsForSearch: Infinity });
      $('#new_asset_status').select2({ placeholder: 'Select New Status', dropdownParent: $(maintenanceLogModalElement), minimumResultsForSearch: Infinity });
      if (performedAtInput) flatpickr(performedAtInput, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', defaultDate: 'today', maxDate: 'today' });
      if (nextDueDateInput) flatpickr(nextDueDateInput, { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', minDate: 'today' });
      if (costInput && typeof Cleave !== 'undefined') { new Cleave(costInput, { numeral: true, numeralDecimalScale: 2, numeralThousandsGroupStyle: 'thousand' }); }
    }
  }
  initializeModalPlugins(); // Initialize on page load

  // --- Helper Functions ---

  // Show SweetAlert Toast Notification (ensure these exist globally or define here)
  function showNotification(icon, title) {
    if(typeof showSuccessToast !== 'undefined' && icon === 'success') {
      showSuccessToast(title); // Assuming global function
    } else if (typeof showErrorToast !== 'undefined' && icon === 'error') {
      showErrorToast(title); // Assuming global function
    } else { // Fallback to SweetAlert
      const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true, didOpen: (toast) => { toast.addEventListener('mouseenter', Swal.stopTimer); toast.addEventListener('mouseleave', Swal.resumeTimer); } });
      Toast.fire({ icon: icon, title: title });
    }
  }

  // Reset Validation (Generic)
  function resetModalValidation(formEl) {
    const jqForm = $(formEl);
    if (!jqForm.length) return; // Check if form exists

    jqForm.find('.is-invalid').removeClass('is-invalid');
    // Also remove from parent containers of special inputs
    jqForm.find('.select2-container.is-invalid').removeClass('is-invalid');
    jqForm.find('.flatpickr-input.is-invalid').removeClass('is-invalid');
    // Clear and hide feedback text
    jqForm.find('.invalid-feedback').text('').hide();
    // Clear specific general error divs within the form (if they exist)
    jqForm.find('.general-error-message').text('').hide();
  }

  // Display Validation Errors (Generic)
  function displayModalValidationErrors(formEl, errors) {
    const jqForm = $(formEl); // Ensure we have a jQuery object
    if (!jqForm.length || !errors) return;

    resetModalValidation(jqForm); // Clear previous errors first
    let firstErrorElement = null;
    const generalErrorDiv = jqForm.find('.general-error-message'); // Find general error div within form

    console.log("Validation Errors:", errors); // Debugging

    for (const fieldName in errors) {
      let inputName = fieldName;
      let errorMessage = errors[key][0]; // Typo corrected: errors[fieldName][0];
      // Corrected line:
      // let errorMessage = errors[fieldName][0];

      const inputElement = jqForm.find(`[name="${inputName}"], [name="${inputName}[]"]`);
      let targetElement = inputElement;
      // Find feedback within closest standard form group structure (e.g., .mb-3)
      let feedbackElement = targetElement.closest('.mb-3, .col-md-6, .col-12').find('.invalid-feedback');

      if (!inputElement.length) { // Field not found in form
        if(generalErrorDiv.length) {
          generalErrorDiv.append(`<div>${escapeHtml(inputName)}: ${escapeHtml(errorMessage)}</div>`).show(); // Append to general error display
          if (!firstErrorElement) firstErrorElement = generalErrorDiv;
        }
        continue; // Skip to next error
      }

      // Target specific containers for complex inputs
      if (inputElement.hasClass('select2-hidden-accessible')) {
        targetElement = inputElement.siblings('.select2-container');
      } else if (inputElement.hasClass('flatpickr-input')) {
        targetElement = inputElement; // Target the input itself
      } else if (inputElement.is(':checkbox') || inputElement.is(':radio')) {
        targetElement = inputElement.closest('div'); // Adjust container selector if needed
      }
      // Add more conditions for other custom inputs if necessary

      if (targetElement.length) {
        targetElement.addClass('is-invalid');
        if(feedbackElement.length) {
          feedbackElement.text(errorMessage).show();
        } else {
          // If no feedback div exists nearby, append one (might need style adjustments)
          targetElement.after(`<div class="invalid-feedback d-block">${errorMessage}</div>`);
          // Fetch the newly added element to set as first error if needed
          if (!firstErrorElement) firstErrorElement = targetElement.next('.invalid-feedback');
        }
        if (!firstErrorElement) firstErrorElement = targetElement;
      }
    }

    // Focus first error element
    if (firstErrorElement) {
      if (firstErrorElement.hasClass('select2-container')) {
        firstErrorElement.prev('select').select2('open');
      } else if (firstErrorElement.is(':visible')){
        firstErrorElement.focus();
      } else {
        // Fallback focus if specific element isn't focusable
        jqForm.find('.is-invalid').first().focus();
      }
    }
  }

  // --- Asset Edit Offcanvas Helpers ---
  function resetAssetFormValidation() { resetModalValidation(assetForm); }
  function resetAssetForm() {
    resetAssetFormValidation(); assetForm[0]?.reset(); assetIdInput.val(''); assetMethodInput.val('POST');
    categorySelect.val(null).trigger('change'); statusSelect.val(null).trigger('change'); conditionSelect.val(null).trigger('change');
    purchaseDateInput[0]?._flatpickr?.clear(); warrantyExpiryInput[0]?._flatpickr?.clear();
    purchaseCostInput.val(''); assetOffcanvasLabel.text('Add Asset'); submitAssetBtn.text('Submit').prop('disabled', false);
  }
  function setAssetButtonLoading(isLoading) {
    const buttonText = assetIdInput.val() ? 'Update' : 'Submit'; submitAssetBtn.prop('disabled', isLoading);
    submitAssetBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : buttonText);
  }

  // --- Assign Asset Modal Helpers ---
  function resetAssignFormValidation() { resetModalValidation(assignAssetForm); }
  function resetAssignForm() {
    resetAssignFormValidation(); assignAssetForm[0]?.reset(); assignAssetIdInput.val('');
    assignAssetNameTag.text('Asset Name [Tag]'); assignUserIdSelect.val(null).trigger('change');
    assignConditionOutSelect.val(null).trigger('change'); assignAssignedAtInput[0]?._flatpickr?.setDate('today', true);
    assignExpectedReturnInput[0]?._flatpickr?.clear(); assignGeneralError.text('').hide();
    assignSubmitBtn.text('Assign Asset').prop('disabled', false);
  }
  function setAssignButtonLoading(isLoading) {
    assignSubmitBtn.prop('disabled', isLoading);
    assignSubmitBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Assigning...' : 'Assign Asset');
  }

  // --- Return Asset Modal Helpers ---
  function resetReturnFormValidation() { resetModalValidation(returnAssetForm); }
  function resetReturnForm() {
    resetReturnFormValidation(); returnAssetForm[0]?.reset(); returnAssetIdInput.val(''); returnAssetIdInput.val('');
    returnAssetNameTag.text('Asset Name [Tag]'); returnCurrentAssignee.text('Employee Name');
    returnConditionInSelect.val(null).trigger('change'); returnReturnedAtInput[0]?._flatpickr?.setDate('today', true);
    returnGeneralError.text('').hide(); returnSubmitBtn.text('Confirm Return').prop('disabled', false);
  }
  function setReturnButtonLoading(isLoading) {
    returnSubmitBtn.prop('disabled', isLoading);
    returnSubmitBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : 'Confirm Return');
  }

  // === NEW: Maintenance Modal Helpers ===
  function resetMaintenanceFormValidation() { resetModalValidation(maintenanceLogForm); }
  function resetMaintenanceForm() {
    resetMaintenanceFormValidation();
    maintenanceLogForm?.reset();
    maintenanceTypeSelect.val(null).trigger('change'); // Reset Select2
    performedAtInput?._flatpickr?.setDate('today', true); // Reset date
    nextDueDateInput?._flatpickr?.clear();
    $('#cost').val(''); // Clear CleaveJS input
    $('#update_asset_status').prop('checked', false); // Uncheck status update
    $(newStatusArea).hide(); // Hide status dropdown
    newAssetStatusSelect.val(null).trigger('change'); // Reset status dropdown
    maintenanceGeneralError.text('').hide();
    $(submitMaintenanceBtn).text('Save Log').prop('disabled', false);
  }
  function setMaintenanceButtonLoading(isLoading) {
    $(submitMaintenanceBtn).prop('disabled', isLoading);
    $(submitMaintenanceBtn).html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Saving...' : 'Save Log');
  }

  // --- Event Listeners ---

  // Reset forms when modals/offcanvas are hidden
  if(assetOffcanvasElement.length) { assetOffcanvasElement.on('hidden.bs.offcanvas', resetAssetForm); }
  if(assignAssetModalElement.length) { assignAssetModalElement.on('hidden.bs.modal', resetAssignForm); }
  if(returnAssetModalElement.length) { returnAssetModalElement.on('hidden.bs.modal', resetReturnForm); }

  // Edit Asset Button Click
  if(editAssetBtnDetailsPage.length) {
    editAssetBtnDetailsPage.on('click', function() {
      const assetId = $(this).data('asset-id');
      resetAssetForm();
      assetOffcanvasLabel.text('Loading Data...'); // Use jQuery .text()
      assetOffcanvas?.show();
      $.ajax({ url: assetEditUrl, type: 'GET', dataType: 'json', // Use URL from script var
        success: function (response) {
          if (response.success && response.asset) {
            const data = response.asset;
            assetOffcanvasLabel.text('Edit Asset: ' + (data.name || `#${data.id}`)); // Use jQuery .text()
            assetIdInput.val(data.id);
            assetMethodInput.val('PUT');
            // Populate using jQuery .val() and .trigger()
            $('#assetName').val(data.name || '');
            $('#assetTag').val(data.assetTag || '');
            categorySelect.val(data.assetCategoryId || '').trigger('change');
            $('#manufacturer').val(data.manufacturer || '');
            $('#model').val(data.model || '');
            $('#serialNumber').val(data.serialNumber || '');
            purchaseDateInput[0]?._flatpickr?.setDate(data.purchaseDate || '', true);
            purchaseCostInput.val(data.purchaseCost || ''); // For Cleave, setting val might be enough
            $('#supplier').val(data.supplier || '');
            warrantyExpiryInput[0]?._flatpickr?.setDate(data.warrantyExpiryDate || '', true);
            statusSelect.val(data.status || '').trigger('change');
            conditionSelect.val(data.condition || '').trigger('change');
            $('#location').val(data.location || '');
            $('#notes').val(data.notes || '');
          } else { showNotification('error', response.message || 'Failed data load.'); assetOffcanvas?.hide(); }
        },
        error: function (jqXHR) { showNotification('error', 'Could not load asset data.'); assetOffcanvas?.hide(); }
      });
    });
  }

  // Assign Asset Button Click
  if(assignAssetBtnDetailsPage.length) {
    assignAssetBtnDetailsPage.on('click', function() {
      const assetId = $(this).data('asset-id');
      const assetName = $(this).data('asset-name');
      const assetTag = $(this).data('asset-tag');
      resetAssignForm();
      assignAssetIdInput.val(assetId);
      assignAssetNameTag.text(`${assetName} [${assetTag}]`);
      $('#assignAssetModalLabel').text(`Assign Asset: ${assetTag}`);
      assignAssetModal?.show();
    });
  }

  // Return Asset Button Click
  if(returnAssetBtnDetailsPage.length) {
    returnAssetBtnDetailsPage.on('click', function() {
      const assetId = $(this).data('asset-id');
      const assetName = $(this).data('asset-name');
      const assetTag = $(this).data('asset-tag');
      const assigneeName = $(this).data('assignee-name');
      resetReturnForm();
      returnAssetIdInput.val(assetId);
      returnAssetNameTag.text(`${assetName} [${assetTag}]`);
      returnCurrentAssignee.text(assigneeName || 'N/A');
      $('#returnAssetModalLabel').text(`Return Asset: ${assetTag}`);
      returnAssetModal?.show();
    });
  }

  // === NEW: Maintenance Modal Logic ===
  // Show/hide new status dropdown based on checkbox
  if (updateAssetStatusCheckbox) {
    updateAssetStatusCheckbox.addEventListener('change', function() {
      $(newStatusArea).toggle(this.checked); // Use jQuery toggle based on checkbox state
      // Make status required only if checkbox is checked (handled by backend 'required_if')
    });
  }

  // Maintenance Form Submission
  if (maintenanceLogForm) {
    maintenanceLogForm.addEventListener('submit', function(e) {
      e.preventDefault();
      resetMaintenanceFormValidation();

      const url = assetMaintenanceStoreUrl; // URL from Blade script vars
      const formData = new FormData(maintenanceLogForm);

      // Ensure boolean value for checkbox is sent correctly
      if (!$(updateAssetStatusCheckbox).is(':checked')) {
        formData.set('update_asset_status', '0');
        formData.delete('new_asset_status'); // Don't send status if box unchecked
      } else {
        formData.set('update_asset_status', '1');
        // Ensure new_asset_status is required if checked (client-side optional check)
        if (!newAssetStatusSelect.val()) {
          displayModalValidationErrors(maintenanceLogForm, { 'new_asset_status': ['New Status is required when checkbox is checked.']});
          return; // Prevent submission
        }
      }

      setMaintenanceButtonLoading(true);

      $.ajax({
        url: url, method: 'POST', data: formData,
        processData: false, contentType: false, dataType: 'json',
        success: function (response) {
          if(response.success) {
            maintenanceLogModal?.hide();
            showNotification('success', response.message);
            location.reload(); // Reload page to see updated history & asset status
          } else { showNotification('error', response.message || 'Failed to save log.'); }
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) { displayModalValidationErrors(maintenanceLogForm, jqXHR.responseJSON.errors); showNotification('error', 'Validation failed.');}
          else { showNotification('error', jqXHR.responseJSON?.message || 'An error occurred.'); }
        },
        complete: function () { setMaintenanceButtonLoading(false); }
      });
    });
  }


  // --- Form Submissions ---

  // Asset Form (Edit)
  if (assetForm.length) {
    assetForm.on('submit', function(e) {
      e.preventDefault(); resetModalValidation(assetForm);
      //URL with id
      const url = assetsBaseUrl + '/' + assetIdInput.val(); // PUT URL
      const method = 'POST';
      let formData = new FormData(assetForm[0]);
      formData.append('_method', 'PUT'); // Method override

      setAssetButtonLoading(true);
      $.ajax({ url: url, method: method, data: formData, processData: false, contentType: false, dataType: 'json',
        success: function (response) { if(response.success) { assetOffcanvas?.hide(); showNotification('success', response.message); location.reload(); } else { showNotification('error', response.message || 'Update failed.'); }},
        error: function (jqXHR) { if (jqXHR.status === 422) { displayModalValidationErrors(assetForm[0], jqXHR.responseJSON.errors); showNotification('error', 'Validation failed.');} else { showNotification('error', jqXHR.responseJSON?.message || 'An error occurred.'); }},
        complete: function () { setAssetButtonLoading(false); }
      });
    });
  }

  // Assign Asset Form
  if (assignAssetForm.length) {
    assignAssetForm.on('submit', function(e) {
      e.preventDefault(); resetModalValidation(assignAssetForm[0]);
      const url = assetAssignUrl; // POST URL
      const formData = new FormData(assignAssetForm[0]);
      setAssignButtonLoading(true);
      $.ajax({ url: url, method: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
        success: function(response) { if(response.success) { assignAssetModal?.hide(); showNotification('success', response.message); location.reload(); } else { $('#assign-general-error').text(response.message || 'Assignment failed.').show(); showNotification('error', response.message || 'Assignment failed.'); }},
        error: function(jqXHR) { if (jqXHR.status === 422) { displayModalValidationErrors(assignAssetForm[0], jqXHR.responseJSON.errors); showNotification('error', 'Validation failed.');} else if (jqXHR.status === 409) { showNotification('error', jqXHR.responseJSON?.message || 'Asset cannot be assigned.'); } else { showNotification('error', jqXHR.responseJSON?.message || 'An error occurred.'); }},
        complete: function() { setAssignButtonLoading(false); }
      });
    });
  }

  // Return Asset Form
  if (returnAssetForm.length) {
    returnAssetForm.on('submit', function(e) {
      e.preventDefault(); resetModalValidation(returnAssetForm[0]);
      const url = assetReturnUrl; // POST URL
      const formData = new FormData(returnAssetForm[0]);
      setReturnButtonLoading(true);
      $.ajax({ url: url, method: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
        success: function (response) { if(response.success) { returnAssetModal?.hide(); showNotification('success', response.message); location.reload(); } else { $('#return-general-error').text(response.message || 'Return failed.').show(); showNotification('error', response.message || 'Return failed.'); }},
        error: function (jqXHR) { if (jqXHR.status === 422) { displayModalValidationErrors(returnAssetForm[0], jqXHR.responseJSON.errors); showNotification('error', 'Validation failed.');} else if (jqXHR.status === 409 || jqXHR.status === 404) { showNotification('error', jqXHR.responseJSON?.message || 'Cannot process return.'); } else { showNotification('error', jqXHR.responseJSON?.message || 'An error occurred.'); }},
        complete: function () { setReturnButtonLoading(false); }
      });
    });
  }

}); // End DOMContentLoaded / jQuery Ready
