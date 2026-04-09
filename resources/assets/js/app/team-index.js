'use strict';



$(function () {

  var dt_table = $('.datatables-teams');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // team datatable
  if (dt_table.length) {
    var dt_team = dt_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'teams/getTeamsListAjax',
        data: function (d) {
          d.searchTerm = $('#customSearchInput').val();
        }
      },
      columns: [
        { data: '' },
        { data: 'id' },
        { data: 'name' },
        { data: 'code' },
        { data: 'notes' },
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
          render: function (data) {
            return `<span class="text-muted small">${data || 'N/A'}</span>`;
          }
        },
        {
          targets: 5,
          render: function (data, type, full) {
            var checked = data === 'active' ? 'checked' : '';
            return `
              <div class="form-check form-switch mb-0">
                <input type="checkbox" class="form-check-input status-toggle" 
                  id="statusToggle${full['id']}" data-id="${full['id']}" ${checked}>
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
                <a href="javascript:;" class="icon-sophisticated text-hitech edit-record" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateTeam" title="Edit"><i class="bx bx-edit"></i></a>
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
          exportOptions: { columns: [1, 2, 3, 4] }
        },
        {
          extend: 'csv',
          className: 'd-none',
          exportOptions: { columns: [1, 2, 3, 4] }
        },
        {
          extend: 'pdf',
          className: 'd-none',
          exportOptions: { columns: [1, 2, 3, 4] }
        }
      ]
    });

    // Custom Search & Length
    $('#customSearchBtn').on('click', function () { dt_team.draw(); });
    $('#customSearchInput').on('keyup', function (e) { if (e.key === 'Enter') dt_team.draw(); });
    $('#customLengthMenu').on('change', function () { dt_team.page.len($(this).val()).draw(); });
    
    $('#btnExportTeams').on('click', function () {
      dt_team.button('.buttons-excel').trigger();
    });

    $('.dt-buttons').addClass('d-none');
  }

  var teamModal = $('#modalAddOrUpdateTeam');
  var managerSelect = $('#manager_ids');
  const addTeamForm = document.getElementById('teamForm');
  let fv;

  if (addTeamForm) {
    fv = FormValidation.formValidation(addTeamForm, {
      fields: {
        name: { validators: { notEmpty: { message: 'The name is required' } } },
        code: {
          validators: {
            notEmpty: { message: 'The code is required' },
            remote: {
              url: `${baseUrl}teams/checkCodeValidationAjax`,
              message: 'The code is already taken',
              method: 'GET',
              data: function () { return { id: addTeamForm.querySelector('[name="id"]').value }; }
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: function (field, ele) { return '.mb-5'; }
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    }).on('core.form.valid', function () {
      $.ajax({
        data: $('#teamForm').serialize(),
        url: `${baseUrl}teams/addOrUpdateTeamAjax`,
        type: 'POST',
        success: function (response) {
          if (response.code === 200) {
            teamModal.modal('hide');
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: `Team ${response.message} Successfully.`,
              showConfirmButton: true,
              confirmButtonText: 'OK',
              customClass: { confirmButton: 'btn btn-hitech' }
            });
            dt_team.draw();
          }
        },
        error: function (err) {
          Swal.fire({
            title: 'Error',
            text: 'Operation failed. Please try again.',
            icon: 'error',
            customClass: { confirmButton: 'btn btn-hitech' }
          });
        }
      });
    });
  }

  if (managerSelect.length) {
    managerSelect.select2({
      placeholder: 'Select Managers',
      dropdownParent: teamModal,
      allowClear: true
    });
  }

  $(document).on('click', '.add-new', function () {
    $('#id').val('');
    $('#teamForm')[0].reset();
    $('#modalTeamLabel').html('Create Team');
    $('.submit-text').html('Create Team');
    managerSelect.val(null).trigger('change');
    if (fv) fv.resetForm(true);
    setCode();
  });

  function setCode() {
    $.get(`${baseUrl}teams/getCodeAjax`, function (data) { $('#code').val(data); });
  }

  $('#isChatEnabledToggle').on('change', function () {
    $('#isChatEnabled').val(this.checked ? 1 : 0);
  });

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id');
    $('#modalTeamLabel').html('Edit Team');
    $('.submit-text').html('Update Team');

    $.get(`${baseUrl}teams/getTeamAjax/${id}`, function (data) {
      $('#id').val(data.id);
      $('#name').val(data.name);
      $('#code').val(data.code);
      $('#notes').val(data.notes);
      $('#isChatEnabled').val(data.isChatEnabled ? 1 : 0);
      $('#isChatEnabledToggle').prop('checked', !!data.isChatEnabled);
      if (data.manager_ids) {
        managerSelect.val(data.manager_ids).trigger('change');
      } else {
        managerSelect.val(null).trigger('change');
      }
    });
  });

  teamModal.on('hidden.bs.modal', function () {
    fv.resetForm(true);
    $('#teamForm')[0].reset();
  });

  $(document).on('click', '.delete-record', function () {
    var id = $(this).data('id');
    Swal.fire({
      title: 'Are you sure?',
      text: "This action cannot be undone!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-hitech me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        $.ajax({
          type: 'DELETE',
          url: `${baseUrl}teams/deleteTeamAjax/${id}`,
          success: function () {
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The Team has been deleted!',
              customClass: { confirmButton: 'btn btn-hitech' }
            });
            dt_team.draw();
          }
        });
      }
    });
  });

  $(document).on('change', '.status-toggle', function () {
    var id = $(this).data('id');
    var status = $(this).is(':checked') ? 'Active' : 'Inactive';
    $.post(`${baseUrl}teams/changeStatus/${id}`, { status: status, _token: $('meta[name="csrf-token"]').attr('content') }, function () {
      dt_team.draw();
    });
  });
});
