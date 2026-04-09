$(function () {
  'use strict';

  // CSRF setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  let dt_warning_types_table = $('.datatables-warning-types');
  let dt_warning_types;

  // Initialize DataTable
  if (dt_warning_types_table.length) {
    dt_warning_types = dt_warning_types_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: pageData.urls.datatable
      },
      columns: [
        { data: 'name_with_code', name: 'name', searchable: true },
        { data: 'severity_badge', name: 'severity', searchable: true },
        { data: 'escalation_info', name: 'escalation_info', searchable: false },
        { data: 'requirements', name: 'requirements', searchable: false },
        { data: 'status', name: 'is_active', searchable: true },
        { data: 'actions', name: 'actions', orderable: false, searchable: false }
      ],
      order: [[0, 'asc']],
      dom: '<"row mx-2"<"col-md-2"<"me-3"l>><"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      displayLength: 10,
      lengthMenu: [10, 25, 50, 75, 100],
      buttons: [],
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Details of ' + data['name'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col, i) {
              return col.columnIndex !== 5
                ? '<tr data-dt-row="' +
                    col.rowIdx +
                    '" data-dt-column="' +
                    col.columnIndex +
                    '">' +
                    '<td>' +
                    col.title +
                    ':' +
                    '</td> ' +
                    '<td>' +
                    col.data +
                    '</td>' +
                    '</tr>'
                : '';
            }).join('');

            return data ? $('<table class="table"/><tbody />').append(data) : false;
          }
        }
      },
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search..'
      }
    });
  }

  // Initialize Select2
  $('.select2').select2({
    placeholder: function() {
      return $(this).data('placeholder') || 'Select...';
    },
    allowClear: true
  });

  // Initialize Quill editor
  let quill;
  if ($('#template_editor').length) {
    quill = new Quill('#template_editor', {
      theme: 'snow',
      placeholder: 'Enter warning letter template...',
      modules: {
        toolbar: [
          ['bold', 'italic', 'underline'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          ['clean']
        ]
      }
    });
  }

  // Load template variables
  loadTemplateVariables();
  
  // Load active warning types for next warning type dropdown
  loadActiveTypes();

  // Form submission
  $('#warningTypeForm').on('submit', function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const isEdit = $('#warning_type_id').val();
    
    // Get Quill content
    if (quill) {
      const templateContent = quill.root.innerHTML;
      formData.set('template_content', templateContent);
    }
    
    // Fix checkbox values
    const requiresAcknowledgment = $('#requires_acknowledgment').is(':checked');
    const allowsAppeal = $('#allows_appeal').is(':checked');
    const isActive = $('#is_active').is(':checked');
    
    formData.delete('requires_acknowledgment');
    formData.delete('allows_appeal');
    formData.delete('is_active');
    
    formData.append('requires_acknowledgment', requiresAcknowledgment ? '1' : '0');
    formData.append('allows_appeal', allowsAppeal ? '1' : '0');
    formData.append('is_active', isActive ? '1' : '0');

    // **ADD THIS: Handle conditional required fields**
    if (!requiresAcknowledgment) {
        formData.delete('acknowledgment_days'); // Remove if not required
    }
    if (!allowsAppeal) {
        formData.delete('appeal_days'); // Remove if not required
    }
    
    // **ADD THIS: Ensure empty numeric fields are handled**
    const numericFields = ['escalation_days', 'acknowledgment_days', 'appeal_days', 'validity_days', 'display_order'];
    numericFields.forEach(field => {
        const value = formData.get(field);
        if (value === '' || value === null) {
            formData.delete(field); // Remove empty numeric fields
        }
    });
    
    // Submit button handling
    const submitBtn = $('button[type="submit"]');
    const originalText = submitBtn.find('.submit-text').text();
    submitBtn.prop('disabled', true);
    submitBtn.find('.submit-text').text('Processing...');
    
    const url = isEdit ? pageData.urls.update.replace(':id', $('#warning_type_id').val()) : pageData.urls.store;
    const method = isEdit ? 'PUT' : 'POST';
    
    if (isEdit) {
      formData.append('_method', 'PUT');
    }
    
    $.ajax({
      url: url,
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
          });
          
          // Reset form and close offcanvas
          resetForm();
          $('#addWarningTypeOffcanvas').offcanvas('hide');
          
          // Refresh datatable
          dt_warning_types.draw(false);
          
          // Reload active types
          loadActiveTypes();
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

  // Edit warning type
  $(document).on('click', '.edit-warning-type', function (e) {
    e.preventDefault();
    const warningTypeId = $(this).data('id');
    
    $.ajax({
      url: pageData.urls.show.replace(':id', warningTypeId),
      method: 'GET',
      success: function (response) {
        if (response.status === 'success') {
          const warningType = response.data;
          
          // Populate form
          $('#warning_type_id').val(warningType.id);
          $('#name').val(warningType.name);
          $('#code').val(warningType.code);
          $('#description').val(warningType.description);
          $('#severity').val(warningType.severity);
          $('#display_order').val(warningType.display_order);
          $('#escalation_days').val(warningType.escalation_days);
          $('#next_warning_type_id').val(warningType.next_warning_type_id);
          $('#acknowledgment_days').val(warningType.acknowledgment_days);
          $('#appeal_days').val(warningType.appeal_days);
          $('#validity_days').val(warningType.validity_days);
          
          // Set checkboxes
          $('#requires_acknowledgment').prop('checked', warningType.requires_acknowledgment);
          $('#allows_appeal').prop('checked', warningType.allows_appeal);
          $('#is_active').prop('checked', warningType.is_active);
          
          // Set Quill content
          if (quill && warningType.template_content) {
            quill.root.innerHTML = warningType.template_content;
          }
          
          // Update form title and button
          $('#addWarningTypeOffcanvasLabel').text('Edit Warning Type');
          $('.submit-text').text('Update');
          
          // Show offcanvas
          $('#addWarningTypeOffcanvas').offcanvas('show');
        }
      }
    });
  });

  // Delete warning type
  $(document).on('click', '.delete-warning-type', function (e) {
    e.preventDefault();
    const warningTypeId = $(this).data('id');
    
    Swal.fire({
      title: 'Are you sure?',
      text: 'This action cannot be undone!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: pageData.urls.destroy.replace(':id', warningTypeId),
          method: 'DELETE',
          success: function (response) {
            if (response.status === 'success') {
              Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: response.data.message,
                timer: 2000,
                showConfirmButton: false
              });
              
              dt_warning_types.draw(false);
              loadActiveTypes();
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
            
            if (xhr.responseJSON && xhr.responseJSON.data) {
              errorMessage = xhr.responseJSON.data;
            }
            
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: errorMessage
            });
          }
        });
      }
    });
  });

  // Reset form when offcanvas is hidden
  $('#addWarningTypeOffcanvas').on('hidden.bs.offcanvas', function () {
    resetForm();
  });

  // Checkbox change handlers
  $('#requires_acknowledgment').on('change', function () {
    const container = $('#acknowledgment_days_container');
    if ($(this).is(':checked')) {
      container.show();
    } else {
      container.hide();
    }
  });

  $('#allows_appeal').on('change', function () {
    const container = $('#appeal_days_container');
    if ($(this).is(':checked')) {
      container.show();
    } else {
      container.hide();
    }
  });

  // Template variable click handler
  $(document).on('click', '.template-variable', function () {
    const variable = $(this).data('variable');
    if (quill) {
      const range = quill.getSelection();
      if (range) {
        quill.insertText(range.index, variable);
      } else {
        quill.insertText(0, variable);
      }
    }
  });

  // Load template variables
  function loadTemplateVariables() {
    $.ajax({
      url: pageData.urls.templateVariables,
      method: 'GET',
      success: function (response) {
        if (response.status === 'success') {
          const container = $('#template_variables');
          container.empty();
          
          response.data.forEach(function (variable) {
            container.append(`
              <span class="badge bg-primary template-variable" data-variable="${variable}" style="cursor: pointer;">
                ${variable}
              </span>
            `);
          });
        }
      }
    });
  }

  // Load active warning types
  function loadActiveTypes() {
    $.ajax({
      url: pageData.urls.activeTypes,
      method: 'GET',
      success: function (response) {
        if (response.status === 'success') {
          const $select = $('#next_warning_type_id');
          const currentValue = $select.val();
          
          $select.empty().append('<option value="">None</option>');
          
          response.data.forEach(function (type) {
            $select.append(`<option value="${type.id}">${type.name} (${type.severity})</option>`);
          });
          
          if (currentValue) {
            $select.val(currentValue);
          }
        }
      }
    });
  }

  // Reset form
  function resetForm() {
    $('#warningTypeForm')[0].reset();
    $('#warning_type_id').val('');
    
    if (quill) {
      quill.setContents([]);
    }
    
    $('#addWarningTypeOffcanvasLabel').text('Add Warning Type');
    $('.submit-text').text('Create');
    
    // Reset checkboxes to default state
    $('#requires_acknowledgment').prop('checked', true);
    $('#allows_appeal').prop('checked', true);
    $('#is_active').prop('checked', true);
    
    // Show/hide containers based on checkbox state
    $('#acknowledgment_days_container').show();
    $('#appeal_days_container').show();
    
    // Clear validation errors
    $('.is-invalid').removeClass('is-invalid');
    $('.invalid-feedback').remove();
  }
});