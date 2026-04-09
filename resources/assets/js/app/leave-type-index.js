'use strict';



$(function () {
  var dt_table = $('.datatables-leaveTypes');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // LeaveTypes datatable
  if (dt_table.length) {
    var dt_leaveType = dt_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'leaveTypes/getLeaveTypesAjax',
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
          // site_name
          targets: 4,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $site_name = full['site_name'] ?? 'All Units';

            return '<span class="site-name">' + $site_name + '</span>';
          }
        },
        {
          // notes
          targets: 5,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $notes = full['notes'] ?? 'N/A';

            return '<span class="user-notes">' + $notes + '</span>';
          }
        },

        {
          // status
          targets: 6,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $status = full['status'];

            var checked = $status === 'active' ? 'checked' : '';

            return `
                <div class="d-flex justify-content-center">
                  <div class="hitech-toggle-wrapper">
                    <input type="checkbox" class="hitech-switch-input status-toggle" 
                      id="statusToggle${full['id']}" data-id="${full['id']}" ${checked}>
                    <label for="statusToggle${full['id']}" class="hitech-switch-label"></label>
                  </div>
                </div>
            `;
          }
        },

        {
          // Actions
          targets: 7,
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center justify-content-center gap-2">' +
              `<a href="javascript:;" class="icon-sophisticated text-hitech edit-record" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateLeaveType" title="Edit"><i class="bx bx-edit"></i></a>` +
              `<a href="javascript:;" class="icon-sophisticated text-danger delete-record" data-id="${full['id']}" title="Delete"><i class="bx bx-trash"></i></a>` +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'asc']],
      dom:
        '<"row"' +
        '<"col-md-2"<"ms-n2"l>>' +
        '<"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-6 mb-md-0 mt-n6 mt-md-0"f>>' +
        '>t' +
        '<"row"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      lengthMenu: [7, 10, 20, 50, 70, 100], //for length of menu
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search Leave Type',
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
              title: 'Leave Type',
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
              title: 'Leave Type',
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
              title: 'Leave Type',
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
    $('.dt-buttons').addClass('d-none');

    // --- Custom Search & Filters ---
    $('#customSearchInput').on('keyup', function () {
      dt_leaveType.search($(this).val()).draw();
    });

    $('#customSearchBtn').on('click', function () {
      dt_leaveType.search($('#customSearchInput').val()).draw();
    });

    $('#customLengthMenu').on('change', function () {
      dt_leaveType.page.len($(this).val()).draw();
    });

    // Status Filter Listener
    $('input[name="statusFilter"]').on('change', function () {
      dt_leaveType.draw();
    });

    // To remove default btn-secondary in export buttons
    $('.dt-buttons').addClass('d-none');
  }

  // Add status filter to AJAX
  if (dt_leaveType) {
    dt_leaveType.on('preXhr.dt', function (e, settings, data) {
      data.statusFilter = $('input[name="statusFilter"]:checked').val();
    });
  }

  var offCanvasForm = $('#modalAddOrUpdateLeaveType');

  $(document).on('click', '.add-new', function () {
    $('#id').val('');
    $('#name').val('');
    $('#code').val('');
    $('#site_id').val('').trigger('change');
    $('#notes').val('');
    
    // Master Toggles Global Reset
    $('#isProofRequired').val('0');
    $('#isProofRequiredToggle').prop('checked', false);
    $('#isShortLeave').val('0');
    $('#isShortLeaveToggle').prop('checked', false);
    $('#isPaid').val('1');
    $('#isPaidToggle').prop('checked', true);
    $('#isCarryForward').val('1');
    $('#isCarryForwardToggle').prop('checked', true);
    $('#isSplitEntitlement').val('0');
    $('#isSplitEntitlementToggle').prop('checked', false);
    $('#isConsecutiveAllowed').val('0');
    $('#isConsecutiveAllowedToggle').prop('checked', false);
    $('#isStrictRules').val('0');
    $('#isStrictRulesToggle').prop('checked', false);

    $('#modalLeaveTypeLabel').html('Add Leave Type');
    fv.resetForm(true);

  });

  $('#isProofRequiredToggle').on('change', function () {
    $('#isProofRequired').val(this.checked ? 1 : 0);
  });

  $('#isShortLeaveToggle').on('change', function () {
    $('#isShortLeave').val(this.checked ? 1 : 0);
  });

  $('#isPaidToggle').on('change', function () {
    $('#isPaid').val(this.checked ? 1 : 0);
  });

  $('#isCarryForwardToggle').on('change', function () {
    $('#isCarryForward').val(this.checked ? 1 : 0);
  });

  $('#isSplitEntitlementToggle').on('change', function () {
    $('#isSplitEntitlement').val(this.checked ? 1 : 0);
  });

  $('#isConsecutiveAllowedToggle').on('change', function () {
    $('#isConsecutiveAllowed').val(this.checked ? 1 : 0);
  });

  $('#isStrictRulesToggle').on('change', function () {
    $('#isStrictRules').val(this.checked ? 1 : 0);
  });

  const addLeaveTypeForm = document.getElementById('leaveTypeForm');

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id'),
      dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // changing the title
    $('#modalLeaveTypeLabel').html('Edit Leave Type');

    // get data
    $.get(`${baseUrl}leaveTypes\/getLeaveTypeAjax\/${id}`, function (data) {
      console.log(data);
      $('#id').val(data.id);
      $('#name').val(data.name);
      $('#code').val(data.code);
      if (data.site_id) {
          $('#site_id').val(data.site_id).trigger('change');
      } else {
          $('#site_id').val('').trigger('change');
      }
      $('#notes').val(data.notes);
      // Set the Master Toggles
      $('#isProofRequired').val(data.isProofRequired == 1 ? 1 : 0);
      $('#isProofRequiredToggle').prop('checked', data.isProofRequired == 1);

      $('#isShortLeave').val(data.isShortLeave == 1 ? 1 : 0);
      $('#isShortLeaveToggle').prop('checked', data.isShortLeave == 1);

      $('#isPaid').val(data.isPaid == 1 ? 1 : 0);
      $('#isPaidToggle').prop('checked', data.isPaid == 1);

      $('#isCarryForward').val(data.isCarryForward == 1 ? 1 : 0);
      $('#isCarryForwardToggle').prop('checked', data.isCarryForward == 1);

      $('#isSplitEntitlement').val(data.isSplitEntitlement == 1 ? 1 : 0);
      $('#isSplitEntitlementToggle').prop('checked', data.isSplitEntitlement == 1);

      $('#isConsecutiveAllowed').val(data.isConsecutiveAllowed == 1 ? 1 : 0);
      $('#isConsecutiveAllowedToggle').prop('checked', data.isConsecutiveAllowed == 1);

      $('#isStrictRules').val(data.isStrictRules == 1 ? 1 : 0);
      $('#isStrictRulesToggle').prop('checked', data.isStrictRules == 1);
    });
  });

  const fv = FormValidation.formValidation(addLeaveTypeForm, {
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
            url: `${baseUrl}leaveTypes/checkCodeValidationAjax`,
            message: 'The code is already taken',
            method: 'GET',
            data: function () {
              return {
                id: addLeaveTypeForm.querySelector('[name="id"]').value
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
          return '.col-md-6, .col-12, .col-md-12';
        }
      }),
      submitButton: new FormValidation.plugins.SubmitButton(),
      autoFocus: new FormValidation.plugins.AutoFocus()
    }
  }).on('core.form.valid', function () {
    // adding or updating user when form successfully validate
    $.ajax({
      data: $('#leaveTypeForm').serialize(),
      url: `${baseUrl}leaveTypes/addOrUpdateLeaveTypeAjax`,
      type: 'POST',
      success: function (response) {
        if (response.code === 200) {
          offCanvasForm.modal('hide');
          // sweetalert
          Swal.fire({
            icon: 'success',
            title: `Successfully ${response.message}!`,
            text: `Leave Type ${response.message} Successfully.`,
            customClass: {
              confirmButton: 'btn btn-hitech'
            }
          });

          dt_leaveType.draw();
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
            title: 'Unable to create Leave Type',
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
    $('#site_id').val('').trigger('change');
    $('#notes').val('');
    $('#wfh_days_entitlement').val('');
    $('#off_days_entitlement').val('');
    $('#isProofRequired').val('0');
    $('#isProofRequiredToggle').prop('checked', false);
    $('#isShortLeave').val('0');
    $('#isShortLeaveToggle').prop('checked', false);
    $('#isPaid').val('1');
    $('#isPaidToggle').prop('checked', true);
    $('#isCarryForward').val('1');
    $('#isCarryForwardToggle').prop('checked', true);
    $('#isSplitEntitlement').val('0');
    $('#isSplitEntitlementToggle').prop('checked', false);
    $('#isConsecutiveAllowed').val('0');
    $('#isConsecutiveAllowedToggle').prop('checked', false);
    $('#isStrictRules').val('0');
    $('#isStrictRulesToggle').prop('checked', false);
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
          url: `${baseUrl}leaveTypes/deleteLeaveTypeAjax/${id}`,
          success: function () {
            // success sweetalert
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The Leave Type has been deleted!',
              customClass: {
                confirmButton: 'btn btn-hitech'
              }
            });

            dt_leaveType.draw();
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
    var toggleElement = $(this);
    var originalState = !toggleElement.is(':checked');

    $.ajax({
      url: `${baseUrl}leaveTypes/changeStatus/${id}`,
      type: 'POST',
      data: {
        status: status,
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function (response) {
        console.log(response);

        // Show success message
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: `Leave Type status changed to ${status} successfully.`,
          customClass: {
            confirmButton: 'btn btn-hitech'
          },
          timer: 2000,
          showConfirmButton: false
        });

        dt_leaveType.draw();
      },
      error: function (response) {
        console.log(response);

        // Revert the toggle to original state
        toggleElement.prop('checked', originalState);

        // Show error message
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'Failed to update status. Please try again.',
          customClass: {
            confirmButton: 'btn btn-hitech'
          }
        });
      }
    });
  });
});
