'use strict';

$(function () {
  var dt_table = $('.datatables-departments');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // department datatable
  if (dt_table.length) {
    var dt_department = dt_table.DataTable({
      initComplete: function () {
        $('#loader').attr('style', 'display:none');
        $('.card-datatable').show();
      },
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'departments/indexAjax',
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
        { data: 'parent_id' },
        { data: 'managers' },
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
          render: function (data) {
            return `<span>${data}</span>`;
          }
        },
        {
          targets: 2,
          responsivePriority: 4,
          render: function (data) {
            return `<span class="text-body">${data}</span>`;
          }
        },
        {
          targets: 3,
          render: function (data) {
            return `<span class="badge badge-code-hitech">${data}</span>`;
          }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            var $parentName = full.parent_department ? full.parent_department : 'No Parent';
            return `<span class="text-muted small">${$parentName}</span>`;
          }
        },
        {
          targets: 5,
          render: function (data) {
            return `<span class="text-body small">${data || 'No Managers'}</span>`;
          }
        },
        {
          targets: 6,
          render: function (data, type, full) {
            var checked = data === 'active' ? 'checked' : '';
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
                <a href="javascript:;" class="icon-sophisticated text-hitech edit-department" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateDepartment" title="Edit"><i class="bx bx-edit"></i></a>
                <a href="javascript:;" class="icon-sophisticated text-danger delete-department" data-id="${full['id']}" title="Delete"><i class="bx bx-trash"></i></a>
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
          exportOptions: { columns: [1, 2, 3, 4, 5] }
        },
        {
          extend: 'csv',
          className: 'd-none',
          exportOptions: { columns: [1, 2, 3, 4, 5] }
        },
        {
          extend: 'pdf',
          className: 'd-none',
          exportOptions: { columns: [1, 2, 3, 4, 5] }
        }
      ]
    });

    // Custom Filters & Search
    $('#customSearchBtn').on('click', function () { dt_department.draw(); });
    $('#customSearchInput').on('keyup', function (e) { if (e.key === 'Enter') dt_department.draw(); });
    $('input[name="statusFilter"]').on('change', function () { dt_department.draw(); });
    $('#customLengthMenu').on('change', function () { dt_department.page.len($(this).val()).draw(); });
    
    $('#btnExportDepartments').on('click', function () {
      dt_department.button('.buttons-excel').trigger();
    });
  }

  var deptModal = $('#modalAddOrUpdateDepartment');
  const addNewDepartmentForm = document.getElementById('addNewDepartmentForm');
  let fv;

  if (addNewDepartmentForm) {
    fv = FormValidation.formValidation(addNewDepartmentForm, {
      fields: {
        name: { validators: { notEmpty: { message: 'The name is required' } } },
        code: {
          validators: {
            notEmpty: { message: 'The Department code is required' },
            stringLength: { min: 3, max: 10, message: 'The code must be 3 to 10 characters' }
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
      addOrUpdateDepartment();
    });
  }

  $(document).on('click', '.add-new-department', function () {
    $('#departmentId').val('');
    $('#addNewDepartmentForm')[0].reset();
    $('#modalDepartmentLabel').html('Create Department');
    $('.submit-text').html('Create Department');
    loadDepartmentList();
    $('#manager_ids').empty().append('<option value="">Loading...</option>').trigger('change');
    loadFilteredUsers(null); 
    $('#parent_department').val('').trigger('change');
    $('#manager_ids').val(null).trigger('change');
    if (fv) fv.resetForm(true);
  });

  $(document).on('click', '.edit-department', function () {
    var departmentId = $(this).data('id');
    $('#modalDepartmentLabel').html('Edit Department');
    $('.submit-text').html('Update Department');
    loadDepartmentList();
    $('#manager_ids').empty().append('<option value="">Loading...</option>').trigger('change');
    setDepartmentData(departmentId);
  });

  if ($('.select2-modal-department').length) {
    $('.select2-modal-department').each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select Managers',
        dropdownParent: $this.parent()
      });
    });
  }

  deptModal.on('hidden.bs.modal', function () {
    if (fv) fv.resetForm(true);
    $('#addNewDepartmentForm')[0].reset();
  });

  $(document).on('click', '.delete-department', function () {
    var departmentId = $(this).data('id');
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
        deleteDepartment(departmentId);
      }
    });
  });

  function loadFilteredUsers(deptId) {
    let url = deptId ? `${baseUrl}departments/getDepartmentUsersAjax/${deptId}` : `${baseUrl}departments/getDepartmentUsersAjax`;
    return $.get(url, function (response) {
      let managerDropdown = $('#manager_ids');
      managerDropdown.empty();
      if (response.status === 'success') {
        response.data.forEach(function (user) {
          managerDropdown.append(`<option value="${user.id}">${user.first_name} ${user.last_name}</option>`);
        });
        managerDropdown.trigger('change');
      }
    });
  }

  $(document).on('change', '.status-toggle', function () {
    var id = $(this).data('id');
    var status = $(this).is(':checked') ? 'Active' : 'Inactive';
    $.post(`${baseUrl}departments/changeStatus/${id}`, { status: status, _token: $('meta[name="csrf-token"]').attr('content') }, function () {
      dt_department.draw();
    });
  });

  function loadDepartmentList() {
    $.get(baseUrl + 'departments/getParentDepartments', function (response) {
      let parentDropdown = $('#parent_department');
      parentDropdown.empty();
      parentDropdown.append('<option value="">Select parent department</option>');
      response.forEach(function (department) {
        parentDropdown.append(`<option value="${department.id}">${department.name}</option>`);
      });
    });
  }

  function addOrUpdateDepartment() {
    let formData = new FormData($('#addNewDepartmentForm')[0]);
    $.ajax({
      data: formData,
      url: `${baseUrl}departments/addOrUpdateDepartmentAjax`,
      type: 'POST',
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.status === 'success') {
          deptModal.modal('hide');
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: `Department ${response.data} Successfully.`,
            customClass: { confirmButton: 'btn btn-hitech' }
          });
          dt_department.draw();
        }
      },
      error: function () {
        Swal.fire({ title: 'Error', text: 'Operation failed. Code must be unique.', icon: 'error', customClass: { confirmButton: 'btn btn-hitech' } });
      }
    });
  }

  function deleteDepartment(departmentId) {
    $.ajax({
      type: 'DELETE',
      url: `${baseUrl}departments/deleteAjax/${departmentId}`,
      success: function () {
        Swal.fire({ icon: 'success', title: 'Deleted!', text: 'The Department has been deleted!', customClass: { confirmButton: 'btn btn-hitech' } });
        dt_department.draw();
      }
    });
  }

  function setDepartmentData(departmentId) {
    $.get(`${baseUrl}departments/getDepartmentAjax/${departmentId}`, function (response) {
      if (response.status === 'success') {
        let data = response.data;
        $('#departmentId').val(data.id);
        $('#name').val(data.name);
        $('#code').val(data.code);
        $('#notes').val(data.notes);
        
        // Populating managers specifically for this department
        let managerDropdown = $('#manager_ids');
        managerDropdown.empty();
        if (data.available_managers) {
          data.available_managers.forEach(function (user) {
            managerDropdown.append(`<option value="${user.id}">${user.first_name} ${user.last_name}</option>`);
          });
        }
        
        if (data.parent_id) {
          $('#parent_department').val(data.parent_id).trigger('change');
        } else {
          $('#parent_department').val('').trigger('change');
        }
        
        if (data.manager_ids) {
          managerDropdown.val(data.manager_ids).trigger('change');
        } else {
          managerDropdown.val(null).trigger('change');
        }
      }
    });
  }
});
