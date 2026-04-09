'use strict';

$(function () { // jQuery document ready

  // --- Global Variables & Selectors ---
  const assetsDataTableElement = $('.datatables-assets');
  const assetOffcanvasElement = $('#assetOffcanvas');
  const assetOffcanvas = assetOffcanvasElement.length ? new bootstrap.Offcanvas(assetOffcanvasElement[0]) : null;
  const assetForm = $('#assetForm');
  const assetOffcanvasLabel = $('#assetOffcanvasLabel');
  const submitAssetBtn = $('#submitAssetBtn');
  const assetIdInput = $('#asset_id');
  const assetMethodInput = $('#assetMethod');

  // Form Fields (jQuery Selectors)
  const assetNameInput = $('#assetName');
  const assetTagInput = $('#assetTag');
  const categorySelect = $('#asset_category_id'); // Select2
  const serialNumberInput = $('#serialNumber');
  const manufacturerInput = $('#manufacturer');
  const modelInput = $('#model');
  const statusSelect = $('#status'); // Select2
  const conditionSelect = $('#condition'); // Select2
  const purchaseDateInput = $('#purchase_date'); // Flatpickr
  const purchaseCostInput = $('#purchase_cost'); // CleaveJS
  const supplierInput = $('#supplier');
  const warrantyExpiryInput = $('#warranty_expiry_date'); // Flatpickr
  const locationInput = $('#location');
  const notesInput = $('#notes');

  //Assign
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

  //Return
  const returnAssetModalElement = $('#returnAssetModal');
  const returnAssetModal = returnAssetModalElement.length ? new bootstrap.Modal(returnAssetModalElement[0]) : null;
  const returnAssetForm = $('#returnAssetForm');
  const returnSubmitBtn = $('#submitReturnBtn');
  const returnAssetIdInput = $('#return_asset_id');
  const returnAssignmentIdInput = $('#return_assignment_id'); // Added optional assignment ID
  const returnAssetNameTag = $('#returnAssetNameTag');
  const returnCurrentAssignee = $('#returnCurrentAssignee');
  const returnConditionInSelect = $('#conditionIn'); // Select2
  const returnReturnedAtInput = $('#returnedAt'); // Flatpickr

  // Filters
  const filterCategorySelect = $('#filter_category_id');
  const filterStatusSelect = $('#filter_status');
  const filterUserSelect = $('#filter_user_id');
  // Assuming a general search input exists within the DataTables structure (f in DOM)

  // --- CSRF Setup ---
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } }); // Assumes csrfToken is global via Blade

  // --- Initialize Plugins ---

  // Select2 for Filters (allow clear)
  $('#filter_category_id, #filter_status, #filter_user_id').each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent(),
      allowClear: true
    });
  });

  // Initialize Plugins for Assign Modal
  if (assignAssetModalElement.length) {
    assignUserIdSelect.select2({ placeholder: 'Select Employee', dropdownParent: assignAssetModalElement });
    assignConditionOutSelect.select2({ placeholder: 'Select Condition (Optional)', dropdownParent: assignAssetModalElement, allowClear: true, minimumResultsForSearch: Infinity });
    flatpickr(assignAssignedAtInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', defaultDate: 'today' });
    flatpickr(assignExpectedReturnInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y', minDate: 'today' });
  }

  // Initialize Plugins for Return Modal
  if (returnAssetModalElement.length) {
    returnConditionInSelect.select2({
      placeholder: 'Select Condition',
      dropdownParent: returnAssetModalElement, // Attach to modal
      minimumResultsForSearch: Infinity
    });
    flatpickr(returnReturnedAtInput[0], {
      dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y',
      defaultDate: 'today', // Default to today
      maxDate: 'today' // Cannot return in future
    });
  }



  // Helper to reset Assign modal validation
  function resetAssignFormValidation() {
    assignAssetForm.find('.is-invalid').removeClass('is-invalid');
    assignAssetForm.find('.invalid-feedback').text('');
    assignAssetForm.find('.select2-container').removeClass('is-invalid');
    $('#assign-general-error').text('');
  }
  // Helper to reset Assign modal form content
  function resetAssignForm() {
    resetAssignFormValidation();
    assignAssetForm[0]?.reset();
    assignAssetIdInput.val('');
    assignAssetNameTag.text('Asset Name [Tag]'); // Reset placeholder text
    assignUserIdSelect.val(null).trigger('change');
    assignConditionOutSelect.val(null).trigger('change');
    assignAssignedAtInput[0]?._flatpickr?.setDate('today', true); // Reset date to today
    assignExpectedReturnInput[0]?._flatpickr?.clear(); // Clear return date
  }
  // Helper for Assign submit button state
  function setAssignButtonLoading(isLoading) {
    assignSubmitBtn.prop('disabled', isLoading);
    assignSubmitBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Assigning...' : 'Assign Asset');
  }

  // Select2 for Modal/Offcanvas Dropdowns
  function initOffcanvasPlugins() {
    const offcanvasBody = assetOffcanvasElement.find('.offcanvas-body'); // Ensure correct parent

    categorySelect.select2({ placeholder: 'Select Category', dropdownParent: offcanvasBody, allowClear: true });
    statusSelect.select2({ placeholder: 'Select Status', dropdownParent: offcanvasBody, minimumResultsForSearch: Infinity });
    conditionSelect.select2({ placeholder: 'Select Condition', dropdownParent: offcanvasBody, allowClear: true, minimumResultsForSearch: Infinity });

    // Flatpickr for Dates
    if (purchaseDateInput.length) flatpickr(purchaseDateInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });
    if (warrantyExpiryInput.length) flatpickr(warrantyExpiryInput[0], { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });

    // CleaveJS for Currency/Number Input
    if (purchaseCostInput.length && typeof Cleave !== 'undefined') {
      new Cleave(purchaseCostInput[0], {
        numeral: true,
        numeralThousandsGroupStyle: 'thousand'
      });
    }
  }
  // Initialize plugins when the offcanvas is shown the first time or if elements exist on load
  if (assetOffcanvasElement.length) {
    initOffcanvasPlugins();
  }


  // --- Helper Functions ---

  function resetAssetFormValidation() {
    assetForm.find('.is-invalid').removeClass('is-invalid');
    assetForm.find('.invalid-feedback').text('');
    assetForm.find('.select2-container').removeClass('is-invalid');
    $('#general-error').text('');
  }

  function resetAssetForm() {
    resetAssetFormValidation();
    assetForm[0]?.reset();
    assetIdInput.val('');
    assetMethodInput.val('POST');
    // Reset Select2 fields
    categorySelect.val(null).trigger('change');
    statusSelect.val(null).trigger('change');
    conditionSelect.val(null).trigger('change');
    // Reset Flatpickr fields
    purchaseDateInput[0]?._flatpickr?.clear();
    warrantyExpiryInput[0]?._flatpickr?.clear();
    // Reset labels/buttons
    assetOffcanvasLabel.text('Add New Asset');
    submitAssetBtn.text('Submit').prop('disabled', false);
  }

  function setAssetButtonLoading(isLoading) {
    const buttonText = assetIdInput.val() ? 'Update' : 'Submit';
    submitAssetBtn.prop('disabled', isLoading);
    submitAssetBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : buttonText);
  }

  // Helper to reset Return modal validation
  function resetReturnFormValidation() {
    returnAssetForm.find('.is-invalid').removeClass('is-invalid');
    returnAssetForm.find('.invalid-feedback').text('');
    returnAssetForm.find('.select2-container').removeClass('is-invalid');
    $('#return-general-error').text('');
  }
  // Helper to reset Return modal form content
  function resetReturnForm() {
    resetReturnFormValidation();
    returnAssetForm[0]?.reset();
    returnAssetIdInput.val('');
    returnAssignmentIdInput.val(''); // Clear assignment ID
    returnAssetNameTag.text('Asset Name [Tag]');
    returnCurrentAssignee.text('Employee Name');
    returnConditionInSelect.val(null).trigger('change'); // Reset Select2
    returnReturnedAtInput[0]?._flatpickr?.setDate('today', true); // Reset date to today
  }
  // Helper for Return submit button state
  function setReturnButtonLoading(isLoading) {
    returnSubmitBtn.prop('disabled', isLoading);
    returnSubmitBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : 'Confirm Return');
  }

  function displayAssetValidationErrors(errors) {
    resetAssetFormValidation();
    let firstErrorElement = null;
    console.log("Validation Errors:", errors);

    for (const fieldName in errors) {
      // Map snake_case from backend to camelCase/form name if needed (likely not needed if name attributes match DB)
      let inputName = fieldName;
      let errorMessage = errors[fieldName][0];

      const inputElement = assetForm.find(`[name="${inputName}"]`);
      let targetElement = inputElement;
      let feedbackElement = targetElement.closest('.mb-3, .col-md-6, .col-12').find('.invalid-feedback');

      if (inputElement.hasClass('select2-hidden-accessible')) {
        targetElement = inputElement.siblings('.select2-container');
      } else if (inputElement.hasClass('flatpickr-input')) {
        targetElement = inputElement; // Target the input itself for flatpickr
      }

      if (targetElement.length) {
        targetElement.addClass('is-invalid');
        if(feedbackElement.length) { feedbackElement.text(errorMessage).show(); }
        else { targetElement.after(`<div class="invalid-feedback d-block">${errorMessage}</div>`); } // Add if missing
        if (!firstErrorElement) firstErrorElement = targetElement;
      } else { $('#general-error').text(`${fieldName}: ${errorMessage}`).show(); if (!firstErrorElement) firstErrorElement = $('#general-error'); }
    }
    // Focus first error
    if (firstErrorElement) { /* ... focus logic including select2 ... */ }
  }

  function showNotification(icon, title) {  showSuccessToast(title);}

  // --- DataTable Initialization ---
  let dtAssets;
  if (assetsDataTableElement.length) {
    dtAssets = assetsDataTableElement.DataTable({
      processing: true, serverSide: true,
      ajax: {
        url: assetsListAjaxUrl, type: 'GET',
        data: function (d) {
          // Add filter data
          d.filter_category_id = filterCategorySelect.val();
          d.filter_status = filterStatusSelect.val();
          d.filter_user_id = filterUserSelect.val();
          // Add search value directly if server-side search logic uses 'search[value]'
          // d.search = d.search.value; // Already handled by DT usually
        }
      },
      columns: [
        { data: 'id', name: 'id' },
        { data: 'name', name: 'name' },
        { data: 'asset_tag', name: 'asset_tag' },
        { data: 'category_name', name: 'category.name', orderable: false }, // Order/Search by relation needs care
        { data: 'serial_number', name: 'serial_number' },
        { data: 'status', name: 'status' }, // Rendered as badge server-side
        { data: 'condition', name: 'condition', orderable: false }, // Rendered as badge server-side
        { data: 'current_assignee', name: 'currentAssignment.user.first_name', orderable: false, searchable: false }, // Rendered as link server-side
        { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
      ],
      order: [[1, 'asc']], // Order by name ascending initially
      dom: '<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: { sLengthMenu: '_MENU_', search: '', searchPlaceholder: 'Search Assets...' },
      buttons: [ /* Add export buttons etc. if needed */ ],
      // responsive: true, // Enable if needed
    });
  }

  assetsDataTableElement.on('click', '.assign-asset', function () {
    const button = $(this);
    const assetId = button.data('id');
    // Get asset name/tag from the DataTable row for display in modal
    const rowData = dtAssets.row(button.closest('tr')).data();
    const assetName = rowData?.name || 'Unknown';
    const assetTag = rowData?.asset_tag || 'N/A';

    resetAssignForm(); // Reset the assign form first
    assignAssetIdInput.val(assetId); // Set the hidden asset ID
    assignAssetNameTag.text(`${assetName} [${assetTag}]`); // Display asset info
    $('#assignAssetModalLabel').text(`Assign Asset: ${assetTag}`); // Update modal title

    assignAssetModal?.show();
  });

  // --- Filter Changes ---
  filterCategorySelect.add(filterStatusSelect).add(filterUserSelect).on('change', function () {
    dtAssets?.ajax.reload(); // Reload DataTable when filters change
  });

  // --- Offcanvas Handling ---
  if(assetOffcanvasElement.length) { assetOffcanvasElement.on('hidden.bs.offcanvas', resetAssetForm); }

  $('#addAssetBtn').on('click', function () {
    resetAssetForm();
    assetOffcanvasLabel.text('Add New Asset');
    assetOffcanvas?.show();
  });

  // Edit Asset Button Click (Event Delegation)
  assetsDataTableElement.on('click', '.edit-asset', function () {
    const assetId = $(this).data('id');
    const editUrl = $(this).data('url');

    resetAssetForm();
    assetOffcanvasLabel.text('Loading Data...');
    assetOffcanvas?.show();

    $.ajax({
      url: editUrl, type: 'GET', dataType: 'json',
      success: function (response) {
        if (response.success && response.asset) {
          const data = response.asset;
          assetOffcanvasLabel.text('Edit Asset: ' + (data.name || `#${data.id}`));
          assetIdInput.val(data.id);
          assetMethodInput.val('PUT'); // Set method override

          // Populate fields (use form names matching DB columns)
          $('#assetName').val(data.name || '');
          $('#assetTag').val(data.assetTag || ''); // Expecting camelCase key from controller
          categorySelect.val(data.assetCategoryId || '').trigger('change');
          $('#manufacturer').val(data.manufacturer || '');
          $('#model').val(data.model || '');
          $('#serialNumber').val(data.serialNumber || ''); // Expecting camelCase
          purchaseDateInput[0]?._flatpickr?.setDate(data.purchaseDate || '', true);
          $('#purchase_cost').val(data.purchaseCost || ''); // Use CleaveJS instance if needed: cleaveInstance.setRawValue(data.purchaseCost);
          $('#supplier').val(data.supplier || '');
          warrantyExpiryInput[0]?._flatpickr?.setDate(data.warrantyExpiryDate || '', true);
          statusSelect.val(data.status || '').trigger('change');
          conditionSelect.val(data.condition || '').trigger('change');
          $('#location').val(data.location || '');
          $('#notes').val(data.notes || '');

        } else {
          console.error("Error fetching category for edit:", jqXHR);
          assetOffcanvasLabel.text('Edit Category'); // Reset title
          assetOffcanvas?.hide();
          showNotification('error', jqXHR.responseJSON?.message || 'Could not load category data.');

        }
      },
      error: function (jqXHR) {
        console.error("Error fetching category for edit:", jqXHR);
        assetOffcanvasLabel.text('Edit Category'); // Reset title
        assetOffcanvas?.hide();
        showNotification('error', jqXHR.responseJSON?.message || 'Could not load category data.');

      }
    });
  });

  // --- Form Submission (Add/Edit) ---
  if (assetForm.length) {
    assetForm.on('submit', function(e) {
      e.preventDefault();
      resetAssetFormValidation();

      const isUpdate = !!assetIdInput.val();
      // IMPORTANT: Ensure route definition uses PUT for update if using {asset} binding
      // If routes use POST for update like /assets/{id}/update, construct URL manually
      const url = isUpdate ? `assetsManagement/${assetIdInput.val()}` : assetsStoreUrl;
      const method = isUpdate ? 'PUT' : 'POST'; // Use correct method

      let formData = new FormData(assetForm[0]);

      // Important: If route uses PUT/DELETE, append _method for Laravel form handling
      if (isUpdate) { formData.append('_method', 'PUT'); }
      // If using POST route for update: Adjust URL and keep method POST

      setAssetButtonLoading(true);

      $.ajax({
        url: url,
        method: 'POST', // Always use POST if using _method override for PUT/DELETE
        data: formData,
        processData: false, contentType: false, dataType: 'json',
        success: function (response) {
          if(response.success) {
            assetOffcanvas?.hide();
            showNotification('success', response.message);
            dtAssets?.ajax.reload(null, false); // Refresh DataTable
          } else { /* ... handle success:false ... */ }
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
            displayAssetValidationErrors(jqXHR.responseJSON.errors);
            showNotification('error', jqXHR.responseJSON.message || 'Validation failed.');
          } else {
            console.error("Error saving category:", jqXHR.responseText);
            showNotification('error', jqXHR.responseJSON?.message || 'An error occurred.');
          }
        },
        complete: function () { setAssetButtonLoading(false); }
      });
    });
  }

  // --- Delete Asset Handling ---
  assetsDataTableElement.on('click', '.delete-asset', function () {
    const button = $(this);
    const assetId = button.data('id');
    const deleteUrl = button.data('url'); // Get URL from button data
    const isAssigned = button.data('assigned') === true || button.data('assigned') === 'true';

    // Prevent deletion if assigned
    if (isAssigned) {
      Swal.fire({ icon: 'error', title: 'Cannot Delete', text: 'This asset is currently assigned to an employee. Please return it first.' });
      return;
    }

    Swal.fire({
      title: 'Are you sure?', text: "Delete this asset? This action cannot be undone.", icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Yes, delete it!',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: deleteUrl, // Use specific delete URL
          method: 'POST', // Use POST for form submission standard
          data: { _method: 'DELETE' }, // Tell Laravel to treat as DELETE
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showNotification('success', response.message);
              dtAssets?.row(button.closest('tr')).remove().draw(false); // Remove row
            } else { showNotification('error', response.message || 'Failed to delete.'); }
          },
          error: function(jqXHR) { showNotification('error', jqXHR.responseJSON?.message || 'Deletion failed.'); }
        });
      }
    });
  });

  // --- Placeholder Listeners for Assign/Return ---
  if (assignAssetForm.length) {
    assignAssetForm.on('submit', function(e) {
      e.preventDefault();
      resetAssignFormValidation();

      const assetId = assignAssetIdInput.val();
      if (!assetId) {
        showNotification('error', 'Asset ID missing. Please try again.');
        return;
      }

      const url = `${assetsAssignUrl}/${assetId}/assign`; // Adjust URL for assignment
      const formData = new FormData(assignAssetForm[0]);
      // FormData automatically includes user_id, assigned_at etc from form inputs

      setAssignButtonLoading(true);

      $.ajax({
        url: url,
        method: 'POST', // As defined in route
        data: formData,
        processData: false, contentType: false, dataType: 'json',
        success: function(response) {
          if(response.success) {
            assignAssetModal?.hide();
            showNotification('success', response.message);
            dtAssets?.ajax.reload(null, false); // Refresh table to show new status/assignee
          } else {
            // Display general error from backend if not validation
            $('#assign-general-error').text(response.message || 'Assignment failed.').show();
            showNotification('error', response.message || 'Assignment failed.');
          }
        },
        error: function(jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
            // Display validation errors inline in the modal
            let firstAssignError = null;
            Object.keys(jqXHR.responseJSON.errors).forEach(key => {
              const inputElement = assignAssetForm.find(`[name="${key}"]`);
              let targetElement = inputElement;
              let feedbackElement = targetElement.closest('.mb-3').find('.invalid-feedback');
              if (inputElement.hasClass('select2-hidden-accessible')) { targetElement = inputElement.siblings('.select2-container');}
              if (targetElement.length) {
                targetElement.addClass('is-invalid');
                if(feedbackElement.length) { feedbackElement.text(jqXHR.responseJSON.errors[key][0]).show(); }
                else { targetElement.after(`<div class="invalid-feedback d-block">${jqXHR.responseJSON.errors[key][0]}</div>`); }
                if (!firstAssignError) firstAssignError = targetElement;
              } else {$('#assign-general-error').text(jqXHR.responseJSON.errors[key][0]).show(); if (!firstAssignError) firstAssignError = $('#assign-general-error');}
            });
            if (firstAssignError) { firstAssignError.hasClass('select2-container') ? firstAssignError.prev('select').select2('open') : firstAssignError.focus(); }
            showNotification('error', 'Please check the form for errors.');

          } else if (jqXHR.status === 409) { // Handle conflict (e.g., asset not available)
            $('#assign-general-error').text(jqXHR.responseJSON?.message || 'Asset cannot be assigned.').show();
            showNotification('error', jqXHR.responseJSON?.message || 'Asset cannot be assigned.');
          }
          else {
            console.error("Error assigning asset:", jqXHR.responseText);
            showNotification('error', jqXHR.responseJSON?.message || 'An unexpected error occurred.');
          }
        },
        complete: function() {
          setAssignButtonLoading(false);
        }
      });
    });
  }

  assetsDataTableElement.on('click', '.return-asset', function () {
    const button = $(this);
    const assetId = button.data('id');
    // Fetch asset details from the DataTable row
    const rowData = dtAssets.row(button.closest('tr')).data();
    console.log(rowData);

    if (!rowData) {
      showNotification('error', 'Could not get asset details.');
      return;
    }

    resetReturnForm(); // Reset the return form first
    returnAssetIdInput.val(assetId); // Set the hidden asset ID
    // Optional: Store assignment ID if needed for specific return endpoint
    // returnAssignmentIdInput.val(rowData.current_assignment_id || '');

    // Display asset & user info in the modal
    var assetName = rowData?.name || 'Unknown';
    returnAssetNameTag.html(`${assetName} <span class="text-muted">[${rowData.asset_tag || 'N/A'}]</span>`); // Display asset name and tag
    // Assuming 'current_assignee' column data contains the HTML link, extract text or use specific data point if available
    let assigneeName = 'Unknown';
    if (rowData.current_assignee) {
      // Try to extract name from link text, or use a dedicated field if controller sends it
      assigneeName = $(rowData.current_assignee).text() || rowData.current_assignee; // Simple text extraction
    }
    returnCurrentAssignee.text(assigneeName);
    $('#returnAssetModalLabel').text(`Return Asset: ${rowData.asset_tag || ''}`); // Update modal title

    returnAssetModal?.show();
  });

  if (returnAssetForm.length) {
    returnAssetForm.on('submit', function(e) {
      e.preventDefault();
      resetReturnFormValidation();

      const assetId = returnAssetIdInput.val();
      if (!assetId) {
        showNotification('error', 'Asset ID missing. Please try again.');
        return;
      }

      // Route uses POST /assets/{asset}/return
      const url = baseUrl+assetsAssignUrl+`${assetId}/return`; // Adjust URL for return
      const formData = new FormData(returnAssetForm[0]);
      // No _method needed as route is POST

      setReturnButtonLoading(true);

      $.ajax({
        url: url,
        method: 'POST',
        data: formData,
        processData: false, contentType: false, dataType: 'json',
        success: function(response) {
          if(response.success) {
            returnAssetModal?.hide();
            showNotification('success', response.message);
            dtAssets?.ajax.reload(null, false); // Refresh table
          } else {
            $('#return-general-error').text(response.message || 'Return failed.').show();
            showNotification('error', response.message || 'Return failed.');
          }
        },
        error: function(jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
            // Display validation errors inline in the modal
            let firstReturnError = null;
            Object.keys(jqXHR.responseJSON.errors).forEach(key => {
              const inputElement = returnAssetForm.find(`[name="${key}"]`);
              let targetElement = inputElement;
              let feedbackElement = targetElement.closest('.mb-3').find('.invalid-feedback');
              if (inputElement.hasClass('select2-hidden-accessible')) { targetElement = inputElement.siblings('.select2-container');}
              if (targetElement.length) {
                targetElement.addClass('is-invalid');
                if(feedbackElement.length) { feedbackElement.text(jqXHR.responseJSON.errors[key][0]).show(); }
                else { targetElement.after(`<div class="invalid-feedback d-block">${jqXHR.responseJSON.errors[key][0]}</div>`); }
                if (!firstReturnError) firstReturnError = targetElement;
              } else {$('#return-general-error').text(jqXHR.responseJSON.errors[key][0]).show(); if (!firstReturnError) firstReturnError = $('#return-general-error');}
            });
            if (firstReturnError) { /* ... focus logic ... */ }
            showNotification('error', 'Please check the form for errors.');

          } else if (jqXHR.status === 409 || jqXHR.status === 404 ) { // Handle specific errors like "not assigned" or "assignment not found"
            $('#return-general-error').text(jqXHR.responseJSON?.message || 'Cannot process return.').show();
            showNotification('error', jqXHR.responseJSON?.message || 'Cannot process return.');
          } else {
            console.error("Error returning asset:", jqXHR.responseText);
            showNotification('error', jqXHR.responseJSON?.message || 'An unexpected error occurred.');
          }
        },
        complete: function() {
          setReturnButtonLoading(false);
        }
      });
    });
  }


}); // End Document Ready
