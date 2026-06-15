$(function () {
  // GLightbox instance — declared at outer scope so all handlers can access it
  var lightbox = null;

  function initLightbox() {
    if (typeof GLightbox === 'undefined') return;
    if (lightbox) { try { lightbox.destroy(); } catch(e) {} }
    lightbox = GLightbox({ selector: '.glightbox' });
  }
  var date = $('#dateFilter').val();
  var initialStatus = $('#statusFilter').val() || 'pending';

  var dtTable = $('.datatables-leaveRequests');


  $('#employeeFilter').select2();

  $('#employeeFilter').on('change', function () {
    dtLeaveRequests.draw();
  });

  $('#leaveTypeFilter, #monthFilter, #yearFilter').select2();

  $('#employeeFilter, #leaveTypeFilter, #monthFilter, #yearFilter').on('change', function () {
    if (typeof dtLeaveRequests !== 'undefined') dtLeaveRequests.draw();
  });

  // Status Toggle Logic
  $('.status-toggle-btn').on('click', function () {
    $('.status-toggle-btn').removeClass('active btn-white').addClass('btn-transparent text-muted');
    $(this).addClass('active btn-white text-dark').removeClass('btn-transparent text-muted');

    var status = $(this).data('status');
    $('#statusFilter').val(status);

    // Dynamic Column Visibility based on status
    if (status === 'approved') {
      dtLeaveRequests.column(7).visible(false);  // Status
      dtLeaveRequests.column(9).visible(false);  // Actions
      dtLeaveRequests.column(10).visible(true);  // Approved By
      dtLeaveRequests.column(11).visible(true);  // Approved At

      $('.status-col, .action-col').hide();
      $('.approved-by-col, .approved-at-col').show();
    } else {
      dtLeaveRequests.column(7).visible(true);
      dtLeaveRequests.column(9).visible(true);
      dtLeaveRequests.column(10).visible(false);
      dtLeaveRequests.column(11).visible(false);

      $('.status-col, .action-col').show();
      $('.approved-by-col, .approved-at-col').hide();
    }

    dtLeaveRequests.draw();
  });

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  if (dtTable.length) {
    var employeeView = baseUrl + 'employees/view/';

    var dtLeaveRequests = dtTable.DataTable({
      processing: true,
      serverSide: true,
      dom: 'rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>', // Custom DOM for Hitech layout
      ajax: {
        url: baseUrl + 'leaveRequests/getListAjax',
        data: function (d) {
          d.dateFilter = $('#dateFilter').val();
          d.employeeFilter = $('#employeeFilter').val();
          d.leaveTypeFilter = $('#leaveTypeFilter').val();
          d.statusFilter = $('#statusFilter').val();
          d.monthFilter = $('#monthFilter').val();
          d.yearFilter = $('#yearFilter').val();
          d.searchTerm = $('#customSearchInput').val(); // Integrated Search
        },
        error: function (xhr, error, code) {
          console.log('Error: ' + error);
          console.log('Code: ' + code);
          console.log('Response: ' + xhr.responseText);
        }
      },
      columns: [
        { data: 'unified_id' },                 // 0: Checkbox
        { data: 'user_name' },                  // 1: Employee
        { data: 'department' },                 // 2: Department
        { data: 'request_type' },               // 3: Request Type
        { data: 'dates' },                      // 4: Dates
        { data: 'duration' },                   // 5: Duration
        { data: 'reason' },                     // 6: Reason
        { data: 'status' },                     // 7: Status
        { data: 'attachment' },                 // 8: Attachment
        { data: null, defaultContent: '' },     // 9: Actions
        { data: 'approved_by_name', defaultContent: 'N/A' }, // 10: Approved By
        { data: 'created_at', defaultContent: 'N/A' } // 11: Applied At
      ],
      columnDefs: [
        {
          // Checkboxes
          targets: 0,
          orderable: false,
          searchable: false,
          checkboxes: true,
          render: function (data, type, full, meta) {
            return '<input type="checkbox" class="dt-checkboxes form-check-input" value="' + full['unified_id'] + '">';
          }
        },
        {
          // Employee Name (no avatar - clean name + code)
          targets: 1,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $name = full['user_name'],
              code = full['user_code'];

            return '<div class="d-flex flex-column">' +
              '<a href="' + employeeView + full['user_id'] + '" class="text-heading fw-medium text-truncate">' + $name + '</a>' +
              '<small class="text-muted">' + code + '</small>' +
              '</div>';
          }
        },
        {
          // Department
          targets: 2,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return '<span class="text-heading">' + (full['department'] || 'N/A') + '</span>';
          }
        },
        {
          // Leave type
          targets: 3,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return full['leave_type'] || 'N/A';
          }
        },
        {
          // From Date
          targets: 4,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return full['from_date'] || '';
          }
        },
        {
          // Days
          targets: 5,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var days = full['days'] ? full['days'] : 1;
            var suffix = days > 1 ? ' Days' : ' Day';
            return '<span class="badge badge-hitech-success">' + days + suffix + '</span>';
          }
        },
        {
          // Reason
          targets: 6,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var reason = full['reason'] ? full['reason'] : 'N/A';
            var short_reason = reason.length > 25 ? reason.substring(0, 25) + '...' : reason;
            return '<span class="text-muted" title="' + reason + '">' + short_reason + '</span>';
          }
        },
        {
          // Status
          targets: 7,
          visible: (initialStatus !== 'approved'),
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $status = full['status'];
            var lwpBadge = full['is_lwp'] ? ' <span class="badge bg-label-danger ms-1" style="font-size:0.6rem;" title="' + (full['lwp_days'] || 0) + ' day(s) Leave Without Pay">⚠️ LWP</span>' : '';
            if ($status === 'approved') {
              return '<span class="badge badge-hitech bg-label-success"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Approved</span>' + lwpBadge;
            } else if ($status === 'rejected') {
              return '<span class="badge badge-hitech bg-label-danger"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Rejected</span>' + lwpBadge;
            } else if ($status === 'cancelled') {
              return '<span class="badge badge-hitech bg-label-secondary"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Cancelled</span>' + lwpBadge;
            } else {
              return '<span class="badge badge-hitech bg-label-warning"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Pending</span>' + lwpBadge;
            }
          }
        },
        {
          // Attachment
          targets: 8,
          className: 'text-start',
          render: function (data, type, full, meta) {
            if (full['document'] && full['document'] !== 'N/A') {
              var docUrl = full['document'];
              // Ensure docUrl is relative if it's a local domain to avoid mismatches
              if (docUrl.includes('http')) {
                docUrl = '/' + docUrl.replace(/^https?:\/\/[^\/]+\//, '');
              }
              
              var isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(docUrl);
              
              if (isImg) {
                return `<a href="${docUrl}" class="glightbox" data-gallery="gallery1"> <img src="${docUrl}" alt="Proof" height="35" class="rounded border shadow-sm"/> </a>`;
              } else {
                // Return a premium PDF icon/button (slightly smaller fs-5 instead of fs-4)
                return `<a href="${docUrl}" target="_blank" class="btn btn-sm btn-icon btn-hitech shadow-sm" title="View Document">
                  <i class="bx bxs-file-pdf fs-5 text-danger"></i>
                </a>`;
              }
            }
            return '<span class="text-muted">N/A</span>';
          }
        },
        {
          // Actions
          targets: 9,
          visible: (initialStatus !== 'approved'),
          searchable: false,
          orderable: false,
          className: 'text-center',
          render: function (data, type, full, meta) {
            var actionsHtml = '<div class="d-flex align-items-center justify-content-center gap-2">';
            // Medium icons for Hitech feel
            actionsHtml += `<button class="btn btn-icon btn-hitech leave-request-details shadow-sm" data-id="${full['unified_id']}" data-bs-toggle="modal" data-bs-target="#modalLeaveRequestDetails" title="Review Request"><i class="bx bx-show fs-5"></i></button>`;

            if (full['status'] === 'pending' && !window.isAccountsRole) {
              actionsHtml += `<button class="btn btn-icon btn-hitech-success quick-leave-approve shadow-sm" data-id="${full['unified_id']}" title="Approve"><i class="bx bx-check fs-5"></i></button>`;
              actionsHtml += `<button class="btn btn-icon btn-hitech-danger quick-leave-reject shadow-sm" data-id="${full['unified_id']}" title="Reject"><i class="bx bx-x fs-5"></i></button>`;
            }

            actionsHtml += '</div>';
            return actionsHtml;
          }
        },
        {
          // Approved By
          targets: 10,
          visible: (initialStatus === 'approved'),
          className: 'text-start',
          render: function (data, type, full, meta) {
            return full['approved_by_name'] || 'N/A';
          }
        },
        {
          // Approved At
          targets: 11,
          visible: (initialStatus === 'approved'),
          className: 'text-start',
          render: function (data, type, full, meta) {
            return full['approved_at_formatted'] || 'N/A';
          }
        }
      ],
      order: [[1, 'asc']],
      pageLength: 25,
      lengthMenu: [25, 50, 100],
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search Leave Requests',
        info: 'Displaying _START_ to _END_ of _TOTAL_ entries',
        paginate: {
          next: '<i class="bx bx-chevron-right bx-sm"></i>',
          previous: '<i class="bx bx-chevron-left bx-sm"></i>'
        }
      },
      responsive: false
    });

    // Re-init GLightbox after every DataTable draw (handles AJAX-rendered rows)
    dtLeaveRequests.on('draw', function () {
      initLightbox();
    });

    // Integrated Filter Listeners
    $('#employeeFilter, #leaveTypeFilter, #statusFilter, #dateFilter, #monthFilter, #yearFilter').on('change', function () {
      dtLeaveRequests.draw();
      refreshLeaveChart();
    });

    $('#customSearchBtn').on('click', function () {
      dtLeaveRequests.draw();
      refreshLeaveChart();
    });

    $('#customSearchInput').on('keyup', function (e) {
      if (e.key === 'Enter') {
        dtLeaveRequests.draw();
        refreshLeaveChart();
      }
    });

    $('#customLengthMenu').on('change', function () {
      dtLeaveRequests.page.len($(this).val()).draw();
    });

    // Dynamic Chart Refresh (Simulated)
    window.refreshLeaveChart = function () {
      if (window.leaveChart) {
        const newVal1 = Array.from({ length: 7 }, () => Math.floor(Math.random() * 15) + 5);
        const newVal2 = Array.from({ length: 7 }, () => Math.floor(Math.random() * 8) + 1);

        window.leaveChart.updateSeries([{
          name: 'Entitled/Available',
          data: newVal1
        }, {
          name: 'Used To Date',
          data: newVal2
        }]);
      }
    };


    // Initial lightbox setup (rows may not exist yet; draw event handles the rest)
    initLightbox();

    // To remove default btn-secondary in export buttons
    $('.dt-buttons > .btn-group > button').removeClass('btn-secondary');
  }

  // Handle date changes for standard var update
  $('#dateFilter').on('change', function () {
    date = this.value;
  });

  // leave request details
  window.openLeaveDetails = function (id) {
    // Reset modal state
    $('#statusInput').val('');
    $('#adminNotes').val('');
    $('#remarksRequired').hide();
    $('#adminNotes').css('border-color', '');

    // Show loading state in modal if already open or just open it
    $('#modalLeaveRequestDetails').modal('show');
    $('#userAvatarContainer').html('<div class="spinner-border text-primary" role="status"></div>');

    // get data
    $.get(`${baseUrl}leaveRequests/getByIdAjax/${id}`, function (response) {
      if (response.status === 'success') {
        var data = response.data;
        var statusDiv = $('#statusDiv');

        $('#id').val(data.id);
        $('#userName, #userNameLabel, #userNameHeader').text(data.userName);
        $('#userCode').text(data.userCode);

        // Handle avatar/initials
        if (data.user_profile_image) {
          $('#userAvatarContainer').html(`<img src="${data.user_profile_image}" class="avatar avatar-md rounded-circle border" />`);
        } else {
          $('#userAvatarContainer').html(`<div class="avatar avatar-md"><span class="avatar-initial rounded-circle bg-label-primary shadow-sm">${data.userInitials || ''}</span></div>`);
        }

        $('#leaveType').html(data.leaveType);
        $('#fromDate').text(data.fromDate);
        $('#toDate').text(data.toDate);
        $('#totalDays').text(data.days);
        $('#dayLabel').text(data.days == 1 ? 'Day' : 'Days');
        $('#createdAt').text(data.createdAt);
        $('#userNotes').text(data.userNotes || 'N/A');

        if (data.is_half_day) {
            $('#halfDaySessionBlock').removeClass('d-none');
            var sessionText = data.half_day_session === 'first_half' ? 'First Half (Morning)' : 'Second Half (Afternoon)';
            $('#halfDaySessionLabel').text(sessionText);
        } else {
            $('#halfDaySessionBlock').addClass('d-none');
        }

        $('#leaveRequestForm').hide();
        $('#alreadyRespondedNotice').hide();

        if (data.status === 'approved') {
          statusDiv.html('<span class="badge bg-label-success px-3 py-2"><i class="bx bx-check-circle me-1"></i>Approved</span>');
          if (!window.isAccountsRole) {
              $('#leaveRequestForm').show();
              $('#btnApprove').hide();
              $('#btnReject').html('REVOKE').show();
          } else {
              $('#leaveRequestForm').hide();
          }
        } else if (data.status === 'rejected') {
          statusDiv.html('<span class="badge bg-label-danger px-3 py-2"><i class="bx bx-x-circle me-1"></i>Rejected</span>');
          $('#alreadyRespondedNotice').show();
          $('#leaveRequestForm').hide();
        } else if (data.status === 'cancelled') {
          statusDiv.html('<span class="badge bg-label-secondary px-3 py-2"><i class="bx bx-minus-circle me-1"></i>Cancelled</span>');
          $('#alreadyRespondedNotice').show();
          $('#leaveRequestForm').hide();
        } else {
          statusDiv.html('<span class="badge bg-label-warning px-3 py-2"><i class="bx bx-time me-1"></i>Pending Review</span>');
          if (!window.isAccountsRole) {
              $('#btnApprove').show();
              $('#btnReject').html('REJECT').show();
              $('#leaveRequestForm').show();
          } else {
              $('#btnApprove').hide();
              $('#btnReject').hide();
              $('#leaveRequestForm').hide();
          }
        }

        if (data.document !== null && data.document !== 'N/A') {
          var docUrl = data.document;
          // Ensure docUrl is relative if it's a local domain to avoid mismatches
          if (docUrl.includes('http')) {
            docUrl = '/' + docUrl.replace(/^https?:\/\/[^\/]+\//, '');
          }
          
          var isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(docUrl);
          
          if (isImg) {
            $('#document').attr('src', docUrl);
            $('#document').parent().attr('href', docUrl).show();
            $('#document').show();
            $('#pdfPreview').hide();
          } else {
            $('#document').hide();
            $('#document').parent().hide();
            $('#pdfPreview').attr('href', docUrl).show().html(`<div class="d-flex align-items-center gap-2 p-2 border rounded"><i class="bx bxs-file-pdf fs-3 text-danger"></i> <span class="fw-bold">View Document</span></div>`);
          }
          $('#documentHide').show();
        } else {
          $('#documentHide').hide();
        }
      }
    });
  };

  $(document).on('click', '.leave-request-details', function () {
    var id = $(this).data('id');
    window.openLeaveDetails(id);
  });

  // Deep Link Handling
  const urlParams = new URLSearchParams(window.location.search);
  const leaveId = urlParams.get('id');
  if (leaveId) {
    setTimeout(() => {
      window.openLeaveDetails(leaveId);
    }, 800);
  }

  // Global submitDecision function
  window.submitDecision = function (status) {
    if (status === 'rejected' && !$('#adminNotes').val().trim()) {
      Swal.fire({
        title: 'Remarks Required',
        text: 'Please provide a reason for rejection.',
        icon: 'warning',
        confirmButtonColor: '#007a7a'
      });
      $('#adminNotes').focus();
      return;
    }

    $('#statusInput').val(status);
    $('#leaveRequestForm').submit();
  };

  // Handle form submission with interactive feedback
  $('#leaveRequestForm').on('submit', function (e) {
    e.preventDefault();
    var form = $(this);
    var status = $('#statusInput').val();
    var btn = status === 'approved' ? $('#btnApprove') : $('#btnReject');
    var originalContent = btn.html();

    btn.addClass('disabled').html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...');

    $.ajax({
      url: form.attr('action') || `${baseUrl}leaveRequests/actionAjax`,
      type: 'POST',
      data: form.serialize(),
      success: function (response) {
        $('#modalLeaveRequestDetails').modal('hide');
        Swal.fire({
          title: 'Success!',
          text: `Leave request has been updated.`,
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });

        if (dtLeaveRequests) dtLeaveRequests.ajax.reload();

        // Refresh stats cards manually or by reloading page - let's try to fetch fresh counts if possible
        // For now, a quick reload of stats or page works best to ensure accuracy
        setTimeout(() => { location.reload(); }, 2000);
      },
      error: function () {
        btn.removeClass('disabled').html(originalContent);
        Swal.fire('Error', 'Failed to update request.', 'error');
      }
    });
  });

  // Quick Approve Logic
  $(document).on('click', '.quick-leave-approve', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Approve Leave?',
      text: 'Are you sure you want to quickly approve this request?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Approve',
      confirmButtonColor: '#00695c',
      cancelButtonColor: '#94a3b8'
    }).then((result) => {
      if (result.isConfirmed) {
        performQuickAction(id, 'approved', 'Quick approved from table.');
      }
    });
  });

  // Quick Reject Logic
  $(document).on('click', '.quick-leave-reject', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Reject Leave?',
      text: 'Please provide a reason for rejection:',
      input: 'textarea',
      inputPlaceholder: 'Enter remarks here...',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Submit Rejection',
      confirmButtonColor: '#ff4d4d',
      preConfirm: (value) => {
        if (!value) {
          Swal.showValidationMessage('Remarks are required for rejection!');
        }
        return value;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        performQuickAction(id, 'rejected', result.value);
      }
    });
  });

  // Core AJAX function for quick actions
  function performQuickAction(id, status, notes) {
    $.ajax({
      url: `${baseUrl}leaveRequests/actionAjax`,
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        id: id,
        status: status,
        adminNotes: notes
      },
      success: function (response) {
        Swal.fire({
          title: 'Success!',
          text: `Leave request has been ${status}.`,
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
        if (dtLeaveRequests) dtLeaveRequests.ajax.reload();

        // Refresh stats cards
        setTimeout(() => { location.reload(); }, 2000);
      },
      error: function (xhr) {
        Swal.fire('Error', 'Failed to process request. Please try again.', 'error');
      }
    });
  }

  // Re-initialize lightbox when the details modal opens (attachment inside modal)
  $('#modalLeaveRequestDetails').on('shown.bs.modal', function () {
    initLightbox();
  });
});
