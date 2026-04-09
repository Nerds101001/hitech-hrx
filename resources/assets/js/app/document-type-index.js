'use strict';

$(function () {
  var dt_table = $('.datatables-proofTypes');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // ProofTypes datatable
  if (dt_table.length) {
    var dt_proofType = dt_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'documentTypes/getListAjax',
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
                <div class="d-flex justify-content-left">
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
              `<a href="javascript:;" class="icon-sophisticated text-hitech edit-record" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateDocumentType" title="Edit"><i class="bx bx-edit"></i></a>` +
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
        searchPlaceholder: 'Search Document Type',
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
        url: baseUrl + 'documentTypes/getListAjax',
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
      if (e.key === 'Enter') dt_proofType.draw();
    });

    $('#customSearchBtn').on('click', function () {
      dt_proofType.draw();
    });

    $('input[name="statusFilter"]').on('change', function () {
      dt_proofType.draw();
    });

    $('#customLengthMenu').on('change', function () {
      dt_proofType.page.len($(this).val()).draw();
    });

    $('#btnExportDocumentTypes').on('click', function () {
      dt_proofType.button('.buttons-excel').trigger();
    });

    // To remove default btn-secondary in export buttons
    $('.dt-buttons').addClass('d-none');
  }

  var offCanvasForm = $('#modalAddOrUpdateDocumentType');

  $(document).on('click', '.add-new', function () {
    $('#id').val('');
    $('#notes').val('');
    $('#modalDocumentTypeLabel').html('Add Document Type');
    fv.resetForm(true);
    setCode();
  });

  function setCode() {
    $.get(`${baseUrl}documentTypes\/getCodeAjax`, function (data) {
      $('#code').val(data);
    });
  }

  $('#isRequiredToggle').on('change', function () {
    $('#isRequired').val(this.checked ? 1 : 0);
  });

  const addProofTypeForm = document.getElementById('proofTypeForm');

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id'),
      dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // changing the title
    $('#modalDocumentTypeLabel').html('Edit Document Type');

    // get data
    $.get(`${baseUrl}documentTypes\/getByIdAjax\/${id}`, function (response) {
      var data = response.data;
      $('#id').val(data.id);
      $('#name').val(data.name);
      $('#code').val(data.code);
      $('#notes').val(data.notes);
      $('#isRequiredToggle').prop('checked', !!data.isRequired);
      $('#isRequired').val(data.isRequired ? 1 : 0);
    });
  });

  const fv = FormValidation.formValidation(addProofTypeForm, {
    fields: {
      name: {
        validators: {
          notEmpty: {
            message: 'The name is required'
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
          return '.mb-6';
        }
      }),
      submitButton: new FormValidation.plugins.SubmitButton(),
      autoFocus: new FormValidation.plugins.AutoFocus()
    }
  }).on('core.form.valid', function () {
    // adding or updating user when form successfully validate
    $.ajax({
      data: $('#proofTypeForm').serialize(),
      url: `${baseUrl}documentTypes/addOrUpdateAjax`,
      type: 'POST',
      success: function (response) {
        console.log(response);
        if (response.status === 'success') {
          offCanvasForm.modal('hide');
          // sweetalert
          Swal.fire({
            icon: 'success',
            title: `Successfully ${response.data}!`,
            text: `Proof Type ${response.data} Successfully.`,
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });

          dt_proofType.draw();
        }
      },
      error: function (err) {
        var responseTemp = JSON.parse(err.responseText);
        console.log('Error Response: ' + JSON.stringify(responseTemp));
        if (responseTemp.status === 'failed') {
          Swal.fire({
            title: 'Unable to create Proof Type',
            text: `${responseTemp.data}`,
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });
        } else {
          Swal.fire({
            title: 'Unable to create Proof Type',
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
          url: `${baseUrl}documentTypes/deleteAjax/${id}`,
          success: function () {
            // success sweetalert
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The Proof Type has been deleted!',
              customClass: {
                confirmButton: 'btn btn-hitech'
              }
            });

            dt_proofType.draw();
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
      url: `${baseUrl}documentTypes/changeStatusAjax/${id}`,
      type: 'POST',
      data: {
        status: status,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function (response) {
        if (response.status === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Status Updated!',
            text: `The status has been changed to ${status}.`,
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });
        }
        dt_proofType.draw();
      },
      error: function (err) {
        var responseJson = JSON.parse(err.responseText);
        console.log('Error Response: ' + JSON.stringify(responseJson));
        if (responseJson.status === 'failed') {
          Swal.fire({
            title: 'Unable to update status',
            text: `${responseTemp.data}`,
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });
        } else {
          Swal.fire({
            title: 'Unable to update status',
            text: 'Please try again',
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });
        }
      }
    });
  });
});
