'use strict';

$(function () { // jQuery document ready

  // --- Global Variables & Selectors ---
  const categoriesDataTableElement = $('.datatables-lms-categories');
  const categoryOffcanvasElement = $('#lmsCategoryOffcanvas');
  const categoryOffcanvas = categoryOffcanvasElement.length ? new bootstrap.Offcanvas(categoryOffcanvasElement[0]) : null;
  const categoryForm = $('#lmsCategoryForm');
  const categoryOffcanvasLabel = $('#lmsCategoryOffcanvasLabel');
  const submitCategoryBtn = $('#submitLmsCategoryBtn');
  const categoryIdInput = $('#category_id');
  const categoryMethodInput = $('#categoryMethod');
  const categoryNameInput = $('#categoryName'); // Added for potential focus
  const isActiveCheckbox = $('#is_active');
  const generalErrorDiv = $('#general-error'); // General error display in form
  const categoryDescriptionInput = $('#categoryDescription'); // Added for potential focus

  // --- CSRF Setup ---
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

  // --- Initialize Plugins ---
  // Initialize Select2 for any filters if they exist
  $('.select2-basic').each(function () {
    $(this).select2({ minimumResultsForSearch: Infinity, dropdownParent: $(this).parent() });
  });

  // --- Helper Functions ---

  // Reset Offcanvas Form Validation
  function resetCategoryFormValidation() {
    categoryForm.find('.is-invalid').removeClass('is-invalid');
    categoryForm.find('.invalid-feedback').text('');
    generalErrorDiv.text('').hide();
  }

  // Reset Offcanvas Form Content
  function resetCategoryForm() {
    resetCategoryFormValidation();
    categoryForm[0]?.reset();
    categoryIdInput.val('');
    categoryMethodInput.val('POST');
    isActiveCheckbox.prop('checked', true);
    categoryOffcanvasLabel.text('Add Category');
    submitCategoryBtn.text('Submit').prop('disabled', false);
  }

  // Set Submit Button Loading State
  function setCategoryButtonLoading(isLoading) {
    const buttonText = categoryIdInput.val() ? 'Update' : 'Submit';
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
        let feedbackElement = inputElement.closest('.mb-3, .mb-4').find('.invalid-feedback');
        if (feedbackElement.length) { feedbackElement.text(errors[fieldName][0]).show(); }
        else { inputElement.after(`<div class="invalid-feedback d-block">${errors[fieldName][0]}</div>`); }
        if (!firstErrorElement) firstErrorElement = inputElement;
      } else { generalErrorDiv.append(`<div>${fieldName}: ${errors[fieldName][0]}</div>`).show(); if (!firstErrorElement) firstErrorElement = generalErrorDiv; }
    }
    firstErrorElement?.focus();
  }
  // NOTE: No need for showNotification helper anymore if using global toasts

  // --- DataTable Initialization ---
  let dtCategories;
  if (categoriesDataTableElement.length) {
    dtCategories = categoriesDataTableElement.DataTable({
      processing: true, serverSide: true,
      ajax: { url: lmsCategoriesListAjaxUrl, type: 'GET' },
      columns: [
        { data: 'id', name: 'id' },
        { data: 'name', name: 'name' },
        { data: 'description', name: 'description', orderable: false },
        { data: 'courses_count_display', name: 'courses_count', className: 'text-center', searchable: false },
        { data: 'status', name: 'is_active', className: 'text-center', orderable: true, searchable: false },
        { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
      ],
      columnDefs: [ { targets: [3, 4, 5], className: 'text-center' } ], // Combined targets
      order: [[1, 'asc']],
      dom: '<"row me-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: { sLengthMenu: '_MENU_', search: '', searchPlaceholder: 'Search Categories...' },
      buttons: [],
    });
  }

  // --- Offcanvas Handling ---
  if(categoryOffcanvasElement.length) { categoryOffcanvasElement.on('hidden.bs.offcanvas', resetCategoryForm); }

  $('#addLmsCategoryBtn').on('click', function () {
    resetCategoryForm();
    categoryOffcanvasLabel.text('Add New Category');
    categoryOffcanvas?.show();
  });

  categoriesDataTableElement.on('click', '.edit-category', function () {
    const categoryId = $(this).data('id');
    const editUrl = $(this).data('url');
    resetCategoryForm();
    categoryOffcanvasLabel.text('Loading Data...');
    categoryOffcanvas?.show();
    $.ajax({ url: editUrl, type: 'GET', dataType: 'json',
      success: function (response) {
        if (response.success && response.category) {
          const data = response.category;
          categoryOffcanvasLabel.text('Edit Category: ' + data.name);
          categoryIdInput.val(data.id); categoryMethodInput.val('PUT');
          categoryNameInput.val(data.name || ''); categoryDescriptionInput.val(data.description || '');
          isActiveCheckbox.prop('checked', data.is_active);
        } else { categoryOffcanvas?.hide(); showErrorToast(response.message || 'Failed to load category data.'); }
      },
      error: function (jqXHR) { categoryOffcanvas?.hide(); showErrorToast(jqXHR.responseJSON?.message || 'Could not load category data.'); }
    });
  });

  // --- Form Submission (Add/Edit) ---
  if (categoryForm.length) {
    categoryForm.on('submit', function(e) {
      e.preventDefault(); resetCategoryFormValidation();
      const isUpdate = !!categoryIdInput.val();
      const url = isUpdate ? `${lmsCategoriesBaseUrl}/${categoryIdInput.val()}` : lmsCategoriesStoreUrl;
      const method = 'POST'; let formData = new FormData(categoryForm[0]);
      if (isUpdate) { formData.append('_method', 'PUT'); }
      // Ensure is_active sends 0 or 1
      formData.set('is_active', isActiveCheckbox.is(':checked') ? '1' : '0');

      setCategoryButtonLoading(true);
      $.ajax({ url: url, method: method, data: formData, processData: false, contentType: false, dataType: 'json',
        success: function (response) {
          if(response.success) {
            categoryOffcanvas?.hide();
            showSuccessToast(response.message); // Use specific success toast
            dtCategories?.ajax.reload(null, false);
          } else { showErrorToast(response.message || 'Operation failed.'); } // Use specific error toast
        },
        error: function (jqXHR) {
          if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) { displayCategoryValidationErrors(jqXHR.responseJSON.errors); showErrorToast('Validation failed. Please check the form.'); } // Use specific error toast
          else { showErrorToast(jqXHR.responseJSON?.message || 'An error occurred.'); } // Use specific error toast
        },
        complete: function () { setCategoryButtonLoading(false); }
      });
    });
  }

  // --- Toggle Status Handling ---
  categoriesDataTableElement.on('change', '.category-status-toggle', function () {
    var checkbox = $(this); var url = checkbox.data('url');
    var currentStateIsActive = !checkbox.is(':checked');
    checkbox.prop('disabled', true);
    $.ajax({ url: url, method: 'POST', data: { _method: 'PUT' }, dataType: 'json',
      success: function(response) {
        if (response.success) {
          showSuccessToast(response.message || 'Status updated.'); // Use specific success toast
          dtCategories?.row(checkbox.closest('tr')).invalidate('data').draw(false);
        } else {
          showErrorToast(response.message || 'Failed to update status.'); // Use specific error toast
          checkbox.prop('checked', currentStateIsActive); // Revert UI
        }
      },
      error: function(jqXHR) {
        showErrorToast('An error occurred while toggling status.'); // Use specific error toast
        checkbox.prop('checked', currentStateIsActive); // Revert UI
      },
      complete: function() { checkbox.prop('disabled', false); }
    });
  });

  // --- Delete Category Handling ---
  categoriesDataTableElement.on('click', '.delete-category', function () {
    const button = $(this); const deleteUrl = button.data('url');
    const courseCount = parseInt(button.data('count') || 0, 10);
    if (courseCount > 0) { Swal.fire({ icon: 'error', title: 'Cannot Delete', text: `Category has ${courseCount} course(s). Please reassign first.` }); return; }
    Swal.fire({ title: 'Are you sure?', text: "You won't be able to revert this!", icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete it!', customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed) {
        $.ajax({ url: deleteUrl, method: 'POST', data: { _method: 'DELETE' }, dataType: 'json',
          success: function(response) {
            if (response.success) { showSuccessToast(response.message); dtCategories?.row(button.closest('tr')).remove().draw(false); } // Use specific success toast
            else { showErrorToast(response.message || 'Failed to delete.'); } // Use specific error toast
          },
          error: function(jqXHR) { showErrorToast(jqXHR.responseJSON?.message || 'Deletion failed.'); } // Use specific error toast
        });
      }
    });
  });

}); // End Document Ready
