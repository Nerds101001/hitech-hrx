'use strict';

$(function () {
  var dt_table = $('.datatables-expenseTypes');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // expenseTypes datatable
  if (dt_table.length) {
    var dt_expenseType = dt_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'expenseTypes/getExpenseTypesListAjax',
        error: function (xhr, error, code) {
          console.log('Error: ' + error);
          console.log('Code: ' + code);
          console.log('Response: ' + xhr.responseText);
        }
      },
      columns: [
        // columns according to JSON
        { data: '' },
        { data: 'id' },
        { data: 'name' },
        { data: 'code' },
        { data: 'notes' },
        { data: 'status' },
        { data: 'action' }
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          searchable: false,
          orderable: false,
          responsivePriority: 2,
          targets: 0,
          render: function (data, type, full, meta) {
            return '';
          }
        },
        {
          // id
          targets: 1,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $id = full['id'];

            return '<span class="id">' + $id + '</span>';
          }
        },
        {
          // name
          targets: 2,
          className: 'text-start',
          responsivePriority: 4,
          render: function (data, type, full, meta) {
            var $name = full['name'];

            return '<span class="user-name">' + $name + '</span>';
          }
        },
        {
          // code
          targets: 3,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $code = full['code'];

            return '<span class="user-code">' + $code + '</span>';
          }
        },
        {
          // notes
          targets: 4,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $notes = full['notes'] ?? 'N/A';

            return '<span class="user-notes">' + $notes + '</span>';
          }
        },

        {
          // status
          targets: 5,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $status = full['status'];

            var checked = $status === 'active' ? 'checked' : '';

            return `
                <div class= "d-flex justify-content-left">
                  <div class="form-check form-switch mb-0">
                    <input type="checkbox" class="form-check-input status-toggle" 
                      id="statusToggle${full['id']}" data-id="${full['id']}" ${checked}>
                  </div>
                </div>
            `;
          }
        },

        {
          // Actions
          targets: 6,
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center justify-content-center gap-2">' +
              `<a href="javascript:;" class="icon-sophisticated text-hitech edit-record" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateExpenseType" title="Edit"><i class="bx bx-edit"></i></a>` +
              `<a href="javascript:;" class="icon-sophisticated text-danger delete-record" data-id="${full['id']}" title="Delete"><i class="bx bx-trash"></i></a>` +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'asc']],
      dom: 'rt<"d-flex justify-content-between align-items-center mx-3 mt-4 mb-2" <"small text-muted" i> <"pagination-wrapper" p>>',
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search Expense Type',
        info: 'Displaying _START_ to _END_ of _TOTAL_ entries',
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
      ],
      ajax: {
        url: baseUrl + 'expenseTypes/getExpenseTypesListAjax',
        type: 'GET',
        data: function (d) {
          d.statusFilter = $('input[name="statusFilter"]:checked').val();
          d.searchTerm = $('#customSearchInput').val();
        }
      },
      // For responsive popup
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
              return col.title !== '' // ? Do not show row in modal popup if title is blank (for check box)
                ? '<tr data-dt-row="' +
                col.rowIndex +
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
      }
    });

    // --- Custom Search & Filters ---
    $('#customSearchInput').on('keyup', function (e) {
      if (e.key === 'Enter') dt_expenseType.draw();
    });

    $('#customSearchBtn').on('click', function () {
      dt_expenseType.draw();
    });

    $('input[name="statusFilter"]').on('change', function () {
      dt_expenseType.draw();
    });

    $('#customLengthMenu').on('change', function () {
      dt_expenseType.page.len($(this).val()).draw();
    });

    $('#btnExportExpenseTypes').on('click', function () {
      dt_expenseType.button('.buttons-excel').trigger();
    });

    // To remove default btn-secondary in export buttons
    $('.dt-buttons').addClass('d-none');
  }

  var offCanvasForm = $('#modalAddOrUpdateExpenseType');

  $(document).on('click', '.add-new', function () {
    $('#id').val('');
    $('#notes').val('');
    $('#isProofRequired').val('0');
    $('#isProofRequiredToggle').prop('checked', false);
    $('#modalExpenseTypeLabel').html('Add Expense Type');
    fv.resetForm(true);

  });



  $('#isProofRequiredToggle').on('change', function () {
    $('#isProofRequired').val(this.checked ? 1 : 0);
  });

  const addExpenseTypeForm = document.getElementById('expenseTypeForm');

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id'),
      dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // changing the title
    $('#modalExpenseTypeLabel').html('Edit Expense Type');

    // get data
    $.get(`${baseUrl}expenseTypes\/getExpenseTypeAjax\/${id}`, function (data) {
      $('#id').val(data.id);
      $('#name').val(data.name);
      $('#code').val(data.code);
      $('#notes').val(data.notes);

      // Set the isProofRequired toggle - ensure proper boolean conversion
      var isProofRequired = parseInt(data.isProofRequired) === 1;
      $('#isProofRequired').val(isProofRequired ? 1 : 0);
      $('#isProofRequiredToggle').prop('checked', isProofRequired);
    });
  });

  const fv = FormValidation.formValidation(addExpenseTypeForm, {
    fields: {
      name: {
        validators: {
          notEmpty: {
            message: 'The name is required'
          }
        }
      },
      code: {
        validators: {
          notEmpty: {
            message: 'The code is required'
          },
          remote: {
            url: `${baseUrl}expenseTypes/checkCodeValidationAjax`,
            message: 'The code is already taken',
            method: 'GET',
            data: function () {
              return {
                id: addExpenseTypeForm.querySelector('[name="id"]').value
              };
            }
          }
        }
      }
    },
    plugins: {
      trigger: new FormValidation.plugins.Trigger(),
      bootstrap5: new FormValidation.plugins.Bootstrap5({
        // Use this for enabling/changing valid/invalid class
        eleValidClass: '',
        rowSelector: function (field, ele) {
          // Both individual field wrappers and rows must be selectable
          return '.col-md-6, .col-12, .mb-6, .mb-5, .mb-4, .mb-3';
        }
      }),
      submitButton: new FormValidation.plugins.SubmitButton(),
      autoFocus: new FormValidation.plugins.AutoFocus()
    }
  }).on('core.form.valid', function () {
    // adding or updating user when form successfully validate
    $.ajax({
      data: $('#expenseTypeForm').serialize(),
      url: `${baseUrl}expenseTypes/addOrUpdateExpenseTypeAjax`,
      type: 'POST',
      success: function (response) {
        if (response.code === 200) {
          offCanvasForm.modal('hide');
          // sweetalert
          Swal.fire({
            icon: 'success',
            title: `Successfully ${response.message}!`,
            text: `Expense Type ${response.message} Successfully.`,
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });

          dt_expenseType.draw();
        }
      },
      error: function (err) {
        var responseJson = JSON.parse(err.responseText);
        console.log('Error Response: ' + JSON.stringify(responseJson));
        if (err.code === 400) {
          Swal.fire({
            title: 'Unable to create Leave Type',
            text: `${responseJson.data}`,
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });
        } else {
          Swal.fire({
            title: 'Unable to create Expense Type',
            text: 'Please try again',
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });
        }
      }
    });
  });

  // clearing form data when modal hidden
  offCanvasForm.on('hidden.bs.modal', function () {
    fv.resetForm(true);
    $('#notes').val('');
    $('#isProofRequired').val('0');
    $('#isProofRequiredToggle').prop('checked', false);
  });

  $(document).on('click', '.delete-record', function () {
    var id = $(this).data('id');
    var dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // sweetalert for confirmation of delete
    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: {
        confirmButton: 'btn btn-hitech me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        // delete the data
        $.ajax({
          type: 'DELETE',
          url: `${baseUrl}expenseTypes/deleteExpenseTypeAjax/${id}`,
          success: function () {
            // success sweetalert
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The Expense Type has been deleted!',
              customClass: {
                confirmButton: 'btn btn-hitech'
              }
            });

            setTimeout(() => {
              location.reload();
            }, 1000);
          },
          error: function (error) {
            console.log(error);
          }
        });
      }
    });
  });

  $(document).on('change', '.status-toggle', function () {
    var id = $(this).data('id');
    var status = $(this).is(':checked') ? 'Active' : 'Inactive';

    $.ajax({
      url: `${baseUrl}expenseTypes/changeStatus/${id}`,
      type: 'POST',
      data: {
        status: status,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function (response) {
        console.log(response);

        dt_expenseType.draw();
      },
      error: function (response) {
        console.log(response);
      }
    });
  });
});
