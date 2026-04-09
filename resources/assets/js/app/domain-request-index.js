$(function () {
  var dtTable = $('.datatables-domainRequests');

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  if (dtTable.length) {
    var employeeView = baseUrl + 'employees/view/';

    var dtdomainRequestsdtdomainRequests = dtTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'domainRequests/indexAjax',

        error: function (xhr, error, code) {
          console.log('Error: ' + error);
          console.log('Code: ' + code);
          console.log('Response: ' + xhr.responseText);
        }
      },
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
          // Name with avatar
          targets: 2,
          className: 'text-start',
          responsivePriority: 4,
          render: function (data, type, full, meta) {
            var $name = full['user_name'],
              email = full['user_email'],
              initials = full['user_initials'],
              profileOutput,
              rowOutput;

            if (full['user_profile_image']) {
              profileOutput =
                '<img src="' + full['user_profile_image'] + '" alt="Avatar" class="avatar rounded-circle " />';
            } else {
              initials = full['user_initials'];
              profileOutput = '<span class="avatar-initial rounded-circle bg-label-info">' + initials + '</span>';
            }

            // Creates full output for row
            rowOutput =
              '<div class="d-flex justify-content-start align-items-center user-name">' +
              '<div class="avatar-wrapper">' +
              '<div class="avatar avatar-sm me-4">' +
              profileOutput +
              '</div>' +
              '</div>' +
              '<div class="d-flex flex-column">' +
              '<a href="' +
              employeeView +
              full['user_id'] +
              '" class="text-heading text-truncate"><span class="fw-medium">' +
              $name +
              '</span></a>' +
              '<small>' +
              email +
              '</small>' +
              '</div>' +
              '</div>';

            return rowOutput;
          }
        },

        {
          // Domain Name
          targets: 3,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return full['name'];
          }
        },

        {
          // Date
          targets: 4,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return full['created_at'];
          }
        },

        {
          //status
          targets: 5,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $status = full['status'];
            if ($status == 'approved') {
              return '<span class="badge bg-label-success">Approved</span>';
            } else if ($status == 'rejected') {
              return '<span class="badge bg-label-danger">Rejected</span>';
            } else if ($status == 'cancelled') {
              return '<span class="badge bg-label-danger">Cancelled</span>';
            } else {
              return '<span class="badge bg-label-warning">Pending</span>';
            }
          }
        },
        {
          // Actions

          targets: 6,
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-left gap-50">' +
              `<button class="btn btn-sm btn-icon domain-request-details" data-id="${full['id']}" data-bs-toggle="offcanvas" data-bs-target="#offcanvasDomainRequestDetails"><i class="bx bx-show"></i></button>` +
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
        searchPlaceholder: 'Search Domain Requests',
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
              title: 'Domain Requests',
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
              title: 'Domain Requests',
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
              title: 'Domain Requests',
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
              title: 'Domain Requests',
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

  // domain request details
  $(document).on('click', '.domain-request-details', function () {
    var id = $(this).data('id');
    //get data
    $.get(`${baseUrl}domainRequests/getByIdAjax/${id}`, function (response) {
      if (response.status === 'success') {
        var data = response.data;

        var statusQS = $('#status');
        var statusDiv = $('#statusDiv');
        var form = $('#domainRequestForm');

        $('#id').val(data.id);
        $('#userName').text(data.userName);
        $('#userEmail').text(data.userEmail);
        $('#name').text(data.name);
        $('#createdAt').text(data.createdAt);

        $('#admiNotes').val('');

        if (data.status === 'approved') {
          statusDiv.html('<span class="badge bg-label-success">Approved</span>');
        } else if (data.status === 'rejected') {
          statusDiv.html('<span class="badge bg-label-danger">Rejected</span>');
        } else if (data.status === 'cancelled') {
          statusDiv.html('<span class="badge bg-label-danger">Cancelled</span>');
        } else {
          statusDiv.html('<span class="badge bg-label-warning">Pending</span>');
          statusQS.empty();
          $('#statusDDDiv').show();
          statusQS.append(`<option value="approved">Approve</option>`);
          statusQS.append(`<option value="rejected">Reject</option>`);

          var actionBtn = $('#actionButton');
          actionBtn.text('Submit');
          form.show();

          actionBtn.on('click', function () {
            showSwalLoader();
          });
        }
      }
    });
  });

  function showSwalLoader() {
    //Show swal loader
    Swal.fire({
      title: 'Please Wait',
      html: 'Setting up the account for the tenant please wait. This may take a while, do not close the browser or refresh the page.',
      customClass: {},
      didOpen: () => {
        Swal.showLoading();
      }
    });
  }
});
