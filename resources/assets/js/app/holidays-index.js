'use strict';

$(function () {
  var dtTable = $('.datatables-holidays');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // holidays datatable
  if (dtTable.length) {
    var dtHoliday = dtTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'holidays/indexAjax',
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
        { data: 'date' },
        { data: 'site_name' },
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
          // date
          targets: 4,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $date = full['date'];

            return '<span class="user-notes">' + $date + '</span>';
          }
        },
        {
           // Unit
           targets: 5,
           className: 'text-start',
           render: function (data, type, full, meta) {
             var $site_name = full['site_name'] || 'All Units';
             return '<span class="badge bg-label-hitech">' + $site_name + '</span>';
           }
        },
        {
          // notes
          targets: 6,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $notes = full['notes'] ?? 'N/A';

            return '<span class="user-notes">' + $notes + '</span>';
          }
        },

        {
          // status
          targets: 7,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $status = full['status'];

            var checked = $status === 'active' ? 'checked' : '';

            return `
              <div class="hitech-toggle-wrapper ms-2">
                <input class="hitech-switch-input status-toggle" type="checkbox" 
                  id="statusToggle${full['id']}" data-id="${full['id']}" ${checked}>
                <label class="hitech-switch-label" for="statusToggle${full['id']}"></label>
              </div>`;
          }
        },

        {
          // Actions
          targets: 8,
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center justify-content-center gap-2">' +
              `<a href="javascript:;" class="icon-sophisticated text-hitech edit-record" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateHoliday" title="Edit"><i class="bx bx-edit"></i></a>` +
              `<a href="javascript:;" class="icon-sophisticated text-danger delete-record" data-id="${full['id']}" title="Delete"><i class="bx bx-trash"></i></a>` +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'asc']],
      buttons: [],
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
    $('#customSearchInput').on('keyup', function () {
      dtHoliday.draw();
    });

    $('#customSearchBtn').on('click', function () {
      dtHoliday.draw();
    });

    $('input[name="statusFilter"]').on('change', function () {
      dtHoliday.draw();
    });

    $('#customLengthMenu').on('change', function () {
      dtHoliday.page.len($(this).val()).draw();
    });

    $('#btnExportHolidays').on('click', function () {
      dtHoliday.button('.buttons-excel').trigger();
    });

    // To remove default btn-secondary in export buttons
    $('.dt-buttons').addClass('d-none');
  }
  var offCanvasForm = $('#modalAddOrUpdateHoliday');

  $(document).on('click', '.add-new', function () {
    $('#id').val('');
    $('#name').val('');
    $('#code').val('');
    $('#notes').val('');
    $('#site_id').val('');
    $('#modalHolidayLabel').html('Add Holiday');
    fv.resetForm(true);

  });

  const addHolidayForm = document.getElementById('holidayForm');

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id'),
      dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // changing the title
    $('#modalHolidayLabel').html('Edit Holiday');

    // get data
    $.get(`${baseUrl}holidays\/getByIdAjax\/${id}`, function (response) {
      var data = response.data;
      $('#id').val(data.id);
      $('#name').val(data.name);
      $('#code').val(data.code);
      $('#notes').val(data.notes);
      $('#date').val(data.date);
      $('#site_id').val(data.site_id || '');
    });
  });

  const fv = FormValidation.formValidation(addHolidayForm, {
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
          }
        }
      },
      date: {
        validators: {
          notEmpty: {
            message: 'The date is required'
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
          return '.col-md-6, .col-12, .mb-5, .mb-4, .mb-3';
        }
      }),
      submitButton: new FormValidation.plugins.SubmitButton(),
      autoFocus: new FormValidation.plugins.AutoFocus()
    }
  }).on('core.form.valid', function () {
    // adding or updating user when form successfully validate
    $.ajax({
      data: $('#holidayForm').serialize(),
      url: `${baseUrl}holidays/addOrUpdateHolidayAjax`,
      type: 'POST',
      success: function (response) {
        console.log(response);
        if (response.status === 'success') {
          offCanvasForm.modal('hide');
          // sweetalert
          Swal.fire({
            icon: 'success',
            title: `Successfully ${response.data}!`,
            text: `Holiday ${response.data} Successfully.`,
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });

          dtHoliday.draw();
        }
      },
      error: function (err) {
        var responseTemp = JSON.parse(err.responseText);
        console.log('Error Response: ' + JSON.stringify(responseTemp));
        if (responseTemp.status === 'failed') {
          Swal.fire({
            title: 'Unable to create Holiday',
            text: `${responseTemp.data}`,
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });
        } else {
          Swal.fire({
            title: 'Unable to create Holiday',
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
          url: `${baseUrl}holidays/deleteAjax/${id}`,
          success: function () {
            // success sweetalert
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The holiday has been deleted!',
              customClass: {
                confirmButton: 'btn btn-hitech'
              }
            });

            dtHoliday.draw();
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
      url: `${baseUrl}holidays/changeStatusAjax/${id}`,
      type: 'POST',
      data: {
        status: status,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function (response) {
        console.log(response);

        dtHoliday.draw();
      },
      error: function (response) {
        console.log(response);
      }
    });
  });
});

