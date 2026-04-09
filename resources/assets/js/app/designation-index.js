'use strict';

$(function () {
  var dt_table = $('.datatables-designations');
  loadDepartments();

  // Ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  if (dt_table.length) {
    var dt_designation = dt_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'designations/indexAjax',
        data: function (d) {
          d.searchTerm = $('#customSearchInput').val();
          d.statusFilter = $('input[name="statusFilter"]:checked').val();
        }
      },
      columns: [
        { data: '' },
        { data: 'id' },
        { data: 'name' },
        { data: 'code' },
        { data: 'department_name' },
        { data: 'notes' },
        { data: 'is_approver_text' },
        { data: 'status' },
        { data: '' }
      ],
      columnDefs: [
        {
          className: 'control',
          searchable: false,
          orderable: false,
          responsivePriority: 2,
          targets: 0,
          render: function () { return ''; }
        },
        {
          targets: 1,
          render: function (data) { return `<span>${data}</span>`; }
        },
        {
          targets: 2,
          responsivePriority: 4,
          render: function (data) { return `<span class="text-body">${data}</span>`; }
        },
        {
          targets: 3,
          render: function (data) { return `<span class="badge badge-code-hitech">${data}</span>`; }
        },
        {
          targets: 4,
          render: function (data) { return `<span class="text-muted small">${data || 'N/A'}</span>`; }
        },
        {
          targets: 5,
          render: function (data) { return `<span class="text-muted small text-truncate d-inline-block" style="max-width: 150px;">${data || 'N/A'}</span>`; }
        },
        {
          targets: 6,
          render: function (data) {
            var badgeClass = data === 'Yes' ? 'badge-approver-yes' : 'badge-approver-no';
            return `<span class="badge ${badgeClass}">${data}</span>`;
          }
        },
        {
          targets: 7,
          render: function (data, type, full) {
            var checked = data.toLowerCase() === 'active' ? 'checked' : '';
            return `
              <div class="hitech-toggle-wrapper ms-2">
                <input class="hitech-switch-input status-toggle" type="checkbox" 
                  id="statusToggle${full['id']}" data-id="${full['id']}" ${checked}>
                <label class="hitech-switch-label" for="statusToggle${full['id']}"></label>
              </div>`;
          }
        },
        {
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full) {
            return `
              <div class="d-flex align-items-center justify-content-center gap-2">
                <a href="javascript:;" class="icon-sophisticated text-hitech edit-record" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateDesignation" title="Edit"><i class="bx bx-edit"></i></a>
                <a href="javascript:;" class="icon-sophisticated text-danger delete-record" data-id="${full['id']}" title="Delete"><i class="bx bx-trash"></i></a>
              </div>`;
          }
        }
      ],
      order: [[1, 'asc']],
      dom: 'rt<"d-flex justify-content-between align-items-center mx-3 mt-4 mb-2" <"small text-muted" i> <"pagination-wrapper" p>>',
      lengthMenu: [7, 10, 25, 50, 100],
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
        paginate: {
          next: 'Next',
          previous: 'Previous'
        }
      },
      buttons: [
        {
          extend: 'excel',
          className: 'd-none',
          exportOptions: { columns: [1, 2, 3, 4, 5, 6] }
        },
        {
          extend: 'csv',
          className: 'd-none',
          exportOptions: { columns: [1, 2, 3, 4, 5, 6] }
        },
        {
          extend: 'pdf',
          className: 'd-none',
          exportOptions: { columns: [1, 2, 3, 4, 5, 6] }
        }
      ]
    });

    // Custom Filters & Search
    $('#customSearchBtn').on('click', function () { dt_designation.draw(); });
    $('#customSearchInput').on('keyup', function (e) { if (e.key === 'Enter') dt_designation.draw(); });
    $('input[name="statusFilter"]').on('change', function () { dt_designation.draw(); });
    $('#customLengthMenu').on('change', function () { dt_designation.page.len($(this).val()).draw(); });
    
    $('#btnExportDesignations').on('click', function () {
      dt_designation.button('.buttons-excel').trigger();
    });

    // To remove default btn-secondary in export buttons
    $('.dt-buttons').addClass('d-none');
  }

  var designationModal = $('#modalAddOrUpdateDesignation');
  const addDesignationForm = document.getElementById('designationForm');
  let fv;

  if (addDesignationForm) {
    fv = FormValidation.formValidation(addDesignationForm, {
      fields: {
        name: { validators: { notEmpty: { message: 'The name is required' } } },
        code: {
          validators: {
            notEmpty: { message: 'The code is required' },
            remote: {
              url: `${baseUrl}designations/checkCodeValidationAjax`,
              message: 'The code is already taken',
              method: 'GET',
              data: function () { return { id: addDesignationForm.querySelector('[name="id"]').value }; }
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: function (field, ele) {
            return '.col-md-6, .col-12, .mb-5, .mb-4, .mb-3';
          }
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    }).on('core.form.valid', function () {
      $.ajax({
        data: $('#designationForm').serialize(),
        url: `${baseUrl}designations/addOrUpdateAjax`,
        type: 'POST',
        success: function (response) {
          if (response.status === 'success') {
            designationModal.modal('hide');
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: `Designation ${response.data} Successfully.`,
              customClass: { confirmButton: 'btn btn-hitech' }
            });
            dt_designation.draw();
          }
        },
        error: function (err) {
          var responseJson = JSON.parse(err.responseText);
          Swal.fire({ title: 'Error', text: responseJson.data || 'Operation failed', icon: 'error', customClass: { confirmButton: 'btn btn-hitech' } });
        }
      });
    });
  }

  $(document).on('click', '.add-new-designation', function () {
    $('#id').val('');
    $('#designationForm')[0].reset();
    $('#modalDesignationLabel').html('Create Designation');
    $('.submit-text').html('Create Designation');
    loadDepartments();
    if (fv) fv.resetForm(true);
  });

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id');
    $('#modalDesignationLabel').html('Edit Designation');
    $('.submit-text').html('Update Designation');

    $.get(`${baseUrl}designations/getByIdAjax/${id}`, function (data) {
      $('#id').val(data.id);
      $('#name').val(data.name);
      $('#code').val(data.code);
      $('#notes').val(data.notes);
      $('#department_id').val(data.department_id).trigger('change');
      $('#is_approver').prop('checked', !!data.is_approver);
    });
  });

  designationModal.on('hidden.bs.modal', function () {
    fv.resetForm(true);
    $('#designationForm')[0].reset();
  });

  $(document).on('click', '.delete-record', function () {
    var id = $(this).data('id');
    Swal.fire({
      title: 'Are you sure?',
      text: "This action cannot be undone!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: { confirmButton: 'btn btn-hitech me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        $.ajax({
          type: 'DELETE',
          url: `${baseUrl}designations/deleteAjax/${id}`,
          success: function () {
            Swal.fire({ icon: 'success', title: 'Deleted!', text: 'The Designation has been deleted!', customClass: { confirmButton: 'btn btn-hitech' } });
            dt_designation.draw();
          }
        });
      }
    });
  });

  $(document).on('change', '.status-toggle', function () {
    var id = $(this).data('id');
    var status = $(this).is(':checked') ? 'Active' : 'Inactive';
    $.post(`${baseUrl}designations/changeStatus/${id}`, { status: status, _token: $('meta[name="csrf-token"]').attr('content') }, function () {
      dt_designation.draw();
    });
  });

  function loadDepartments() {
    $.get(`${baseUrl}departments/getListAjax`, function (response) {
      if (response && response.status === 'success') {
        $('#department_id').empty();
        $('#department_id').append('<option value="">Select department</option>');
        response.data.forEach(function (department) {
          $('#department_id').append(`<option value="${department.id}">${department.name}</option>`);
        });
      }
    });
  }
});
