'use strict';

$(function () {
  var dt_table = $('.datatables-plans');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Plans datatable
  if (dt_table.length) {
    var dtPlan = dt_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'plans/indexAjax',
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
        { data: 'durationType' },
        { data: 'basePrice' },
        { data: 'includedUsers' },
        { data: 'perUserPrice' },
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
            return '<span class="id">' + full['id'] + '</span>';
          }
        },
        {
          // name
          targets: 2,
          className: 'text-start',
          responsivePriority: 4,
          render: function (data, type, full, meta) {
            return '<span class="user-name">' + full['name'] + '</span>';
          }
        },

        {
          // duration type
          targets: 3,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $durationType = full['duration_type'] ?? 'N/A';

            $durationType = $durationType.charAt(0).toUpperCase() + $durationType.slice(1);

            return '<span class="user-notes">' + $durationType + '</span>';
          }
        },
        {
          // included users
          targets: 4,
          className: 'text-start',
          responsivePriority: 4,
          render: function (data, type, full, meta) {
            return '<span class="user-name">' + full['included_users'] + '</span>';
          }
        },
        {
          // base price
          targets: 5,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return '<span class="user-code">' + currencySymbol + full['base_price'] + '</span>';
          }
        },
        {
          // per user price
          targets: 6,
          className: 'text-start',
          responsivePriority: 4,
          render: function (data, type, full, meta) {
            return '<span class="user-name">' + currencySymbol + full['per_user_price'] + '</span>';
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
                <div class="d-flex justify-content-left">
                <label class="switch mb-0">
                <input
                type="checkbox"
                class="switch-input status-toggle "
                id="statusToggle${full['id']}"
                data-id="${full['id']}"${checked} />
              <span class="switch-toggle-slider">
              <span class="switch-on"><i class="bx bx-check"></i></span>
              <span class="switch-off"><i class="bx bx-x"></i></span>
              </span>
              </label>
              </div>

            `;
          }
        },

        {
          // Actions
          targets: 8,
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            var $href = `${baseUrl}plans\/edit\/${full['id']}`;
            return (
              '<div class="d-flex align-items-left gap-50">' +
              `<a class="btn btn-sm btn-icon" href="${$href}"><i class="bx bx-edit"></i></a>` +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'desc']],
      dom:
        '<"row"' +
        '<"col-md-2"<"ms-n2"l>>' +
        '<"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-6 mb-md-0 mt-n6 mt-md-0"fB>>' +
        '>t' +
        '<"row"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      lengthMenu: [7, 10, 20, 50, 70, 100], //for length of menu
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search Plan',
        info: 'Displaying _START_ to _END_ of _TOTAL_ entries',
        paginate: {
          next: '<i class="bx bx-chevron-right bx-sm"></i>',
          previous: '<i class="bx bx-chevron-left bx-sm"></i>'
        }
      },
      // Buttons with Dropdown
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-label-secondary dropdown-toggle mx-4',
          text: '<i class="bx bx-export me-2 bx-sm"></i>Export',
          buttons: [
            {
              extend: 'print',
              title: 'Leave Type',
              text: '<i class="bx bx-printer me-2" ></i>Print',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                // prevent avatar to be print
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              },
              customize: function (win) {
                //customize print view for dark
                $(win.document.body)
                  .css('color', config.colors.headingColor)
                  .css('border-color', config.colors.borderColor)
                  .css('background-color', config.colors.body);
                $(win.document.body)
                  .find('table')
                  .addClass('compact')
                  .css('color', 'inherit')
                  .css('border-color', 'inherit')
                  .css('background-color', 'inherit');
              }
            },
            {
              extend: 'csv',
              title: 'Plan',
              text: '<i class="bx bx-file me-2" ></i>Csv',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                // prevent avatar to be print
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              }
            },
            {
              extend: 'excel',
              text: '<i class="bx bxs-file-export me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                // prevent avatar to be display
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              }
            },
            {
              extend: 'pdf',
              title: 'Plan',
              text: '<i class="bx bxs-file-pdf me-2"></i>Pdf',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                // prevent avatar to be display
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              }
            },
            {
              extend: 'copy',
              title: 'Plan',
              text: '<i class="bx bx-copy me-2" ></i>Copy',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                // prevent avatar to be copy
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              }
            }
          ]
        }
      ],
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
    // To remove default btn-secondary in export buttons
    $('.dt-buttons > .btn-group > button').removeClass('btn-secondary');
  }

  var offCanvasForm = $('#offcanvasAddOrUpdatePlan');

  $(document).on('click', '.add-new', function () {
    $('#id').val('');
    $('#name').val('');
    $('#duration').val('');
    $('#durationType').val('');
    $('#description').val('');
    $('#includedUsers').val('');
    $('#basePrice').val('');
    $('#perUserPrice').val('');
    $('#offcanvasPlanLabel').html('Add Plan');
    fv.resetForm(true);
  });

  const addPlanForm = document.getElementById('planForm');

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id'),
      dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // changing the title of offcanvas
    $('#offcanvasPlanLabel').html('Edit Plan');

    // get data
    $.get(`${baseUrl}plans\/getPlanAjax\/${id}`, function (data) {
      $('#id').val(data.id);
      $('#name').val(data.name);
      $('#duration').val(data.duration);
      $('#durationType').val(data.durationType);
      $('#description').val(data.description);
      $('#includedUsers').val(data.includedUsers);
      $('#basePrice').val(data.basePrice);
      $('#perUserPrice').val(data.perUserPrice);
    });
  });

  const fv = FormValidation.formValidation(addPlanForm, {
    fields: {
      name: {
        validators: {
          notEmpty: {
            message: 'The name is required'
          }
        }
      },

      duration: {
        validators: {
          notEmpty: {
            message: 'The duration is required'
          }
        }
      },

      durationType: {
        validators: {
          notEmpty: {
            message: 'The duration type is required'
          }
        }
      },
      includedUsers: {
        validators: {
          regexp: {
            regexp: /^(0|[1-9]\d*)(\.\d+)?$/,
            message: 'The included users can not be negative'
          }
        }
      },
      basePrice: {
        validators: {
          regexp: {
            regexp: /^(0|[1-9]\d*)(\.\d+)?$/,
            message: 'The base price can not be negative'
          }
        }
      },
      perUserPrice: {
        validators: {
          regexp: {
            regexp: /^(0|[1-9]\d*)(\.\d+)?$/,
            message: 'The per user price can not be negative'
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
      data: $('#planForm').serialize(),
      url: `${baseUrl}plans/addOrUpdatePlanAjax`,
      type: 'POST',
      success: function (response) {
        if (response.status === 'success') {
          offCanvasForm.offcanvas('hide');
          // sweetalert
          Swal.fire({
            icon: 'success',
            title: `Successfully ${response.data}!`,
            text: `Plan ${response.data} Successfully.`,
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });

          dtPlan.draw();
        }
      },
      error: function (err) {
        var responseJson = JSON.parse(err.responseText);
        console.log('Error Response: ' + JSON.stringify(responseJson));
        if (responseJson.status === 'failed') {
          Swal.fire({
            title: 'Unable to create Plan',
            text: `${responseJson.data}`,
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-success'
            }
          });
        } else {
          Swal.fire({
            title: 'Unable to create Leave Type',
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

  // clearing form data when offcanvas hidden
  offCanvasForm.on('hidden.bs.offcanvas', function () {
    fv.resetForm(true);
  });

  $(document).on('change', '.status-toggle', function () {
    var id = $(this).data('id');
    var status = $(this).is(':checked') ? 'Active' : 'Inactive';

    $.ajax({
      url: `${baseUrl}plans/changeStatusAjax/${id}`,
      type: 'POST',
      data: {
        status: status,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function (response) {
        console.log('Response: ' + JSON.stringify(response));
        if (response.status === 'success') {
        }
        dtPlan.draw();
      },
      error: function (err) {
        console.log('Error Response: ' + JSON.stringify(err));
      }
    });
  });
});
