'use strict';

$(function () { // jQuery document ready

  // --- Global Variables & Selectors ---
  const categoriesDataTable = $('.datatables-asset-categories');
  const categoryOffcanvasElement = $('#categoryOffcanvas');
  const categoryOffcanvas = categoryOffcanvasElement.length ? new bootstrap.Offcanvas(categoryOffcanvasElement[0]) : null;
  const categoryForm = $('#categoryForm');
  const categoryOffcanvasLabel = $('#categoryOffcanvasLabel');
  const submitCategoryBtn = $('#submitCategoryBtn');
  const categoryIdInput = $('#category_id');
  const categoryMethodInput = $('#categoryMethod');
  const isActiveCheckbox = $('#is_active');

  // Form Fields
  const categoryNameInput = $('#categoryName');
  const categoryDescriptionInput = $('#categoryDescription');

  // CSRF Token Setup
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } }); // Assumes csrfToken is global via Blade

  // --- Initialize Plugins ---
  // Initialize Select2 for any filters if they exist (none in this view currently)
  $('.select2-basic').each(function () {
    var $this = $(this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select value',
      dropdownParent: $this.parent()
    });
  });

  // --- Helper Functions ---

  // Reset Offcanvas Form Validation
  function resetCategoryFormValidation() {
    categoryForm.find('.is-invalid').removeClass('is-invalid');
    categoryForm.find('.invalid-feedback').text('');
    $('#general-error').text(''); // Clear general error message
  }

  // Reset Offcanvas Form Content
  function resetCategoryForm() {
    resetCategoryFormValidation();
    categoryForm[0]?.reset(); // Reset native form elements
    categoryIdInput.val('');
    isActiveCheckbox.prop('checked', true);
    categoryMethodInput.val('POST'); // Default to POST for create
    categoryOffcanvasLabel.text('Add Category');
    submitCategoryBtn.text('Submit').prop('disabled', false);
  }

  // Set Button Loading State
  function setCategoryButtonLoading(isLoading) {
    const buttonText = categoryIdInput.val() ? 'Update' : 'Submit'; // Dynamic button text
    submitCategoryBtn.prop('disabled', isLoading);
    submitCategoryBtn.html(isLoading ? '<span class="spinner-border spinner-border-sm"></span> Processing...' : buttonText);
  }

  // Display Validation Errors in Form
  function displayCategoryValidationErrors(errors) {
    resetCategoryFormValidation();
    let firstErrorElement = null;
    console.log("Validation Errors:", errors);

    for (const fieldName in errors) {
      const inputElement = categoryForm.find(`[name="${fieldName}"]`);
      if (inputElement.length) {
        inputElement.addClass('is-invalid');
        // Find the specific feedback div for this input
        let feedbackElement = inputElement.siblings('.invalid-feedback');
        // If not sibling, check closest parent container
        if (!feedbackElement.length) {
          feedbackElement = inputElement.closest('.mb-3, .col-md-6, .col-12').find('.invalid-feedback');
        }
        if (feedbackElement.length) {
          feedbackElement.text(errors[fieldName][0]).show(); // Show feedback message
        } else {
          // Fallback if no feedback div found near input
          inputElement.after(`<div class="invalid-feedback d-block">${errors[fieldName][0]}</div>`);
        }
        if (!firstErrorElement) firstErrorElement = inputElement;
      } else {
        // Fallback for general errors not tied to a field
        $('#general-error').text(errors[fieldName][0]).show();
        if (!firstErrorElement) firstErrorElement = $('#general-error');
      }
    }
    // Focus first error
    firstErrorElement?.focus();
  }

  // Show SweetAlert Toast Notification
  function showNotification(icon, title) {
    showSuccessToast(title);
  }

  // --- DataTable Initialization ---
  let dtCategories; // Variable to hold DataTable instance
  if (categoriesDataTable.length) {
    dtCategories = categoriesDataTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: categoriesListAjaxUrl, // URL from Blade <script> block
        type: 'GET' // Method for fetching data
      },
      columns: [
        // Define columns based on thead and controller response
        { data: 'id', name: 'id' },
        { data: 'name', name: 'name' },
        { data: 'description', name: 'description', orderable: false },
        { data: 'assets_count', name: 'assets_count', className: 'text-center', searchable: false },
        { data: 'status', name: 'is_active', className: 'text-center', orderable: true, searchable: false },
        { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
      ],
      order: [[1, 'asc']], // Order by name ascending initially
      columnDefs: [ // Adjust indices if needed
        { targets: 3, className: 'text-center' }, // assets_count
        { targets: 4, className: 'text-center', orderable: true }, // status (is_active)
        { targets: 5, className: 'text-center' }, // actions
      ],
      dom: '<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: { sLengthMenu: '_MENU_', search: '', searchPlaceholder: 'Search Categories...' },
      buttons: [], // Add export buttons etc. if needed
      // responsive: true, // Add responsive extension if needed
    });
  }

  // --- Offcanvas Handling ---

  // Reset form when offcanvas is hidden
  if (categoryOffcanvasElement) { // Use correct offcanvas element variable
    categoryOffcanvasElement.on('hidden.bs.offcanvas', resetCategoryForm);
  }

  // Add Category Button Click
  $('#addCategoryBtn').on('click', function () {
    resetCategoryForm(); // Ensure form is clean
    categoryOffcanvasLabel.text('Add New Category');
    categoryOffcanvas?.show();
  });

  // Edit Category Button Click (Event Delegation)
  categoriesDataTable.on('click', '.edit-category', function () {
    const categoryId = $(this).data('id');
    const editUrl = $(this).data('url'); // Get URL from button's data-url attribute

    resetCategoryForm();
    categoryOffcanvasLabel.text('Loading Data...');
    categoryOffcanvas?.show();

    $.ajax({
      url: editUrl, // Use the specific edit URL
      type: 'GET',
      dataType: 'json',
      success: function (response) {
        if (response.success && response.category) {
          const data = response.category;
          categoryOffcanvasLabel.text('Edit Category: ' + data.name);
          categoryIdInput.val(data.id);
          categoryMethodInput.val('PUT'); // Set method override for update
          categoryNameInput.val(data.name || '');
          categoryDescriptionInput.val(data.description || '');
          isActiveCheckbox.prop('checked', data.is_active);
        } else {
          categoryOffcanvas?.hide();
          showNotification('error', response.message || 'Failed to load category data.');
        }
      },
      error: function (jqXHR) {
        console.error("Error fetching category for edit:", jqXHR);
        categoryOffcanvasLabel.text('Edit Category'); // Reset title
        categoryOffcanvas?.hide();
        showNotification('error', jqXHR.responseJSON?.message || 'Could not load category data.');
      }
    });
  });

  // --- Form Submission (Add/Edit) ---
  if (categoryForm.length) {
    categoryForm.on('submit', function (e) {
      e.preventDefault();
      resetCategoryFormValidation();

      const isUpdate = !!categoryIdInput.val();
      const url = isUpdate ? `${categoriesBaseUrl}/${categoryIdInput.val()}` : categoriesStoreUrl;
      const method = 'POST'; // Always POST, use _method for PUT

      let formData = new FormData(categoryForm[0]); // Use FormData for easy handling

      if (isUpdate) {
        formData.append('_method', 'PUT');
      }
      if (!isActiveCheckbox.is(':checked')) { formData.set('is_active', '0'); }
      else { formData.set('is_active', '1'); }

      setCategoryButtonLoading(true);

      $.ajax({
        url: url,
        method: method,
        data: formData,
        processData: false, // Needed for FormData
        contentType: false, // Needed for FormData
        dataType: 'json',
        success: function (response) {
          if (response.success) {
            categoryOffcanvas?.hide();
            showNotification('success', response.message);
            dtCategories?.ajax.reload(null, false); // Refresh DataTable without resetting page
          } else {
            // Handle potential success:false response from controller
            showNotification('error', response.message || 'Operation failed.');
            // Display general errors if provided
            if (response.errors && response.errors.general) {
              $('#general-error').text(response.errors.general[0]).show();
            }
          }
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) {
            displayCategoryValidationErrors(jqXHR.responseJSON.errors);
            showNotification('error', jqXHR.responseJSON.message || 'Please correct the validation errors.');
          } else {
            console.error("Error saving category:", jqXHR.responseText);
            showNotification('error', jqXHR.responseJSON?.message || 'An error occurred.');
          }
        },
        complete: function () {
          setCategoryButtonLoading(false);
        }
      });
    });
  }

  categoriesDataTable.on('change', '.category-status-toggle', function () { // Target specific class
    var checkbox = $(this);
    var url = checkbox.data('url'); // Get URL from data attribute
    var categoryId = checkbox.data('id');
    var currentStateIsActive = !checkbox.is(':checked'); // Status *before* successful toggle

    checkbox.prop('disabled', true); // Briefly disable during AJAX

    $.ajax({
      url: url,
      method: 'POST', // Using POST for the action route
      data: { _method: 'PUT' }, // If toggleStatus route uses PUT (adjust if route is POST)
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          showNotification('success', response.message || 'Status updated.');
          // No UI change needed here, rely on DataTable reload for consistency
          dtCategories.row(checkbox.closest('tr')).invalidate('data').draw(false); // Invalidate row data and redraw
        } else {
          showNotification('error', response.message || 'Failed to update status.');
          checkbox.prop('checked', currentStateIsActive); // Revert UI on failure
        }
      },
      error: function (jqXHR) {
        showNotification('error', 'An error occurred.');
        checkbox.prop('checked', currentStateIsActive); // Revert UI on failure
      },
      complete: function () {
        checkbox.prop('disabled', false); // Re-enable checkbox
      }
    });
  });

  // --- Delete Category Handling ---
  categoriesDataTable.on('click', '.delete-category', function () {
    const button = $(this);
    const categoryId = button.data('id');
    const deleteUrl = button.data('url');
    const assetCount = parseInt(button.data('count') || 0, 10); // Get asset count

    // Prevent deletion if assets are assigned
    if (assetCount > 0) {
      Swal.fire({
        icon: 'error',
        title: 'Cannot Delete',
        text: `This category is assigned to ${assetCount} asset(s). Please reassign them before deleting the category.`,
        customClass: { confirmButton: 'btn btn-hitech' }
      });
      return; // Stop execution
    }

    // Confirmation dialog
    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({
          url: deleteUrl,
          method: 'POST', // Use POST
          data: {
            _method: 'DELETE' // Override method
            // _token: csrfToken // Handled by setup
          },
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              showNotification('success', response.message);
              dtCategories?.row(button.closest('tr')).remove().draw(false); // Remove row from table
            } else {
              showNotification('error', response.message || 'Failed to delete category.');
            }
          },
          error: function (jqXHR) {
            console.error("Error deleting category:", jqXHR.responseText);
            showNotification('error', jqXHR.responseJSON?.message || 'An error occurred during deletion.');
          }
        });
      }
    });
  });

}); // End Document Ready
