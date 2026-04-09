$(function () {
    var dt_table = $('.datatables-retailers');
  
    // ajax setup
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    if (dt_table.length) {
        var dt_retailers = dt_table.DataTable({
          processing: true,
          serverSide: true,
          ajax: {
            url: baseUrl + 'retailers/getListAjax',
             error: function (xhr, error, code) {
              // console.log('Error: ' + error);
              // console.log('Code: ' + code);
              // console.log('Response: ' + xhr.responseText);
            }
          },
          columns: [
            // columns according to JSON
        { data: '' },
            {data: 'id'},
            {data: 'name'},
            {data: 'phone_number'},
            {data: 'email'},
            {data: 'status'},
            {data: 'action'}
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
                var $id= full['id'];
    
                return '<span class="id">' + $id + '</span>';
              }
            },
            {
              // Name with avatar
              targets: 2,
              className: 'text-start',
              responsivePriority: 4,
              render: function (data, type, full, meta) {
            
                var $name = full['name'];
            
                // For Avatar badge
                var stateNum = Math.floor(Math.random() * 6);
                var states = ['success', 'danger', 'warning', 'info', 'dark', 'primary', 'secondary'];
                var $state = states[stateNum],
                  $initials = $name.match(/\b\w/g) || [],
                  $output;
                $initials = (($initials.shift() || '') + ($initials.pop() || '')).toUpperCase();
                $output = '<span class="avatar-initial rounded-circle bg-label-' + $state + '">' + $initials + '</span>';
            
                // Creates full output for row
                var $row_output =
                  '<div class="d-flex justify-content-start align-items-center name-with-avatar">' +
                  '<div class="avatar-wrapper">' +
                  '<div class="avatar avatar-sm me-4">' +
                  $output +
                  '</div>' +
                  '</div>' +
                  '<div class="d-flex flex-column">' +
                  '<span class="fw-medium">' + $name + '</span>' +
                  '</div>' +
                  '</div>';
                  
                return $row_output;
              }
            },
            
            {
              // code
              targets: 3,
              className: 'text-start',
              render: function (data, type, full, meta) {
                var $code = full['phone_number'];
    
                return '<span class="phone_number">' + $code + '</span>';
              }
            },
            {
              // email
              targets: 4,
              className: 'text-start',
              render: function (data, type, full, meta) {
                var $notes = full['email'] ;
    
                return '<span class="email">' + (full['email'] ? full['email'] : 'N/A') + '</span>';
              }
            },
    
            {
              // status
              targets: 5,
              className: 'text-start',
              render: function(data, type, full, meta) {
                var $status = full['status'];
    
                var checked = $status === 'active' ? 'checked' : '';
    
                return `
                    <div class= d-flex justify-content-left">
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

              targets: 6,
              searchable: false,
              orderable: false,
              render: function (data, type, full, meta) {
                return (
                  '<div class="d-flex align-items-left gap-50">' +
                  
                  `<a href="retailers/show/${full['id']}" class="btn btn-sm btn-icon info-record">
                     <i class="bx bx-show"></i>
                  </a>` +
                  
                  `<a href="retailers/edit/${full['id']}" class="btn btn-sm btn-icon edit-record">
                     <i class="bx bx-edit"></i>
                  </a>` +
                  
                  `<button class="btn btn-sm btn-icon delete-record" data-id="${full['id']}">
                     <i class="bx bx-trash"></i>
                  </button>` +
                
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
            searchPlaceholder: 'Search Retailers',
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
                  title: 'Retailers',
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
                  title: 'Retailers',
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
                  title: 'Retailers',
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
                  title: 'Retailers',
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

    $(document).on('click', '.delete-record', function () {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        customClass: {
            confirmButton: 'btn btn-primary me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(function (result) {
        if (result.value) {
           deleteRetailer(id);
        }
    });
    });


    function deleteRetailer(id) {
        $.ajax({
            url: `retailers/deleteAjax/${id}`, 
            type: 'DELETE',
            success: function (response) {
               
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Retailer has been deleted.',
                    customClass: {
                        confirmButton: 'btn btn-success'
                      }
                });
                 // Redraw DataTable or reload the data
                 dt_retailers.draw();
            },
            error: function (error) {
                // console.log(error);
                Swal.fire({
                  title: 'Error',
                  text: 'Something went wrong',
                  icon: 'error',
                  customClass: {
                    confirmButton: 'btn btn-success'
                  }
                });
            }
        });
    }

    $(document).on('change', '.status-toggle', function () {
      var id = $(this).data('id');
      var status = $(this).is(':checked') ? 'Active' : 'Inactive';
  
      $.ajax({
        url: `${baseUrl}retailers/changeStatusAjax/${id}`,
        type: 'POST',
        data: {
          status: status,
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
          console.log('Server response:', response);
          if (response.code === 200) {
            Swal.fire({
              icon: 'success',
              title: 'Status Updated!',
              text: `The status has been changed to ${status}.`,
              customClass: {
                confirmButton: 'btn btn-success'
              }
            });
          } else {
            Swal.fire({
              title: 'Error',
              text: response.message || 'Something went wrong',
              icon: 'error',
              customClass: {
                confirmButton: 'btn btn-danger'
              }
            });
          }
        },
        error: function (err) {
          console.log('AJAX error:', err);
          Swal.fire({
            title: 'Unable to update status',
            text: 'Please try again',
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-danger'
            }
          });
        }
      });
    });
    
});