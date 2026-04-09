$(function () {
  'use strict';

  var dtExpenseTable = $('.datatables-expenseRequests');
  var dtExpenseRequests;

  // Initialize Select2 filters
  if ($('.select2').length) {
    $('.select2').each(function () {
      var $this = $(this);
      $this.select2({
        dropdownParent: $this.parent()
      });
    });
  }

  // Setup AJAX
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  if (dtExpenseTable.length) {
    var employeeView = baseUrl + 'employees/view/';

    dtExpenseRequests = dtExpenseTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'expenseRequests/indexAjax',
        data: function (d) {
          d.searchTerm = $('#customSearchInput').val();
          d.employeeFilter = $('#employeeFilter').val();
          d.expenseTypeFilter = $('#expenseTypeFilter').val();
          d.dateFilter = $('#dateFilter').val();
          d.statusFilter = $('.status-toggle-btn.active').data('status');
        }
      },
      columns: [
        { data: '' },
        { data: 'user_name' },
        { data: 'expense_type_name' },
        { data: 'for_date' },
        { data: 'amount' },
        { data: 'document_url_formatted' },
        { data: 'status' },
        { data: '' }, // Actions
        { data: 'approved_by_name' },
        { data: 'approved_at_formatted' }
      ],
      columnDefs: [
        {
          // Checkboxes
          targets: 0,
          orderable: false,
          searchable: false,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return '<input type="checkbox" class="form-check-input dt-checkboxes ms-2" value="' + full['id'] + '">';
          }
        },
        {
          // Employee
          targets: 1,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $name = full['user_name'],
              $code = full['user_code'],
              $image = full['user_profile_image'],
              $initials = full['user_initial'];

            var profileOutput = $image
              ? '<img src="' + $image + '" alt="Avatar" class="avatar rounded-circle" />'
              : '<span class="avatar-initial rounded-circle bg-label-info">' + $initials + '</span>';

            return '<div class="d-flex justify-content-start align-items-center user-name">' +
              '<div class="avatar-wrapper">' +
              '<div class="avatar avatar-sm me-3">' + profileOutput + '</div>' +
              '</div>' +
              '<div class="d-flex flex-column">' +
              '<a href="' + employeeView + full['user_id'] + '" class="text-heading text-truncate"><span class="fw-medium">' + $name + '</span></a>' +
              '<small class="text-muted">' + $code + '</small>' +
              '</div>' +
              '</div>';
          }
        },
        {
          // Expense Type
          targets: 2,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return '<span class="text-heading">' + (full['expense_type_name'] || 'N/A') + '</span>';
          }
        },
        {
          // Date
          targets: 3,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return '<span class="text-muted">' + (full['for_date'] || 'N/A') + '</span>';
          }
        },
        {
          // Amount
          targets: 4,
          className: 'text-start',
          render: function (data, type, full, meta) {
            var $amount = full['amount'];
            var $approved = full['approved_amount'];
            var $status = full['status'];

            if ($status === 'approved' && $approved && $approved != $amount) {
              return '<div class="d-flex flex-column">' +
                '<span class="fw-bold text-dark">' + currencySymbol + Number($approved).toLocaleString() + '</span>' +
                '<small class="text-muted text-decoration-line-through">' + currencySymbol + Number($amount).toLocaleString() + '</small>' +
                '</div>';
            }
            return '<span class="fw-bold text-dark">' + currencySymbol + Number($amount).toLocaleString() + '</span>';
          }
        },
        {
          // Receipt
          targets: 5,
          className: 'text-start',
          render: function (data, type, full, meta) {
            if (full['document_url_formatted']) {
              var docUrl = full['document_url_formatted'];
              // Ensure docUrl is relative if it's a local domain
              if (docUrl.includes('http')) {
                docUrl = '/' + docUrl.replace(/^https?:\/\/[^\/]+\//, '');
              }
              
              var isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(docUrl);
              if (isImg) {
                return '<a href="' + docUrl + '" class="glightbox"><img src="' + docUrl + '" alt="Proof" height="35" class="rounded border shadow-sm" /></a>';
              } else {
                return `<a href="${docUrl}" target="_blank" class="btn btn-sm btn-icon btn-hitech shadow-sm" title="View Document">
                  <i class="bx bxs-file-pdf fs-5 text-danger"></i>
                </a>`;
              }
            }
            return '<span class="text-muted">N/A</span>';
          }
        },
        {
          // Status
          targets: 6,
          className: 'text-start status-col',
          render: function (data, type, full, meta) {
            var $status = full['status'];
            if ($status === 'approved') {
              return '<span class="badge badge-hitech bg-label-success"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Approved</span>';
            } else if ($status === 'rejected') {
              return '<span class="badge badge-hitech bg-label-danger"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Rejected</span>';
            } else {
              return '<span class="badge badge-hitech bg-label-warning"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Pending</span>';
            }
          }
        },
        {
          // Actions
          targets: 7,
          searchable: false,
          orderable: false,
          className: 'text-center action-col',
          render: function (data, type, full, meta) {
            var actionsHtml = '<div class="d-flex align-items-center gap-2">';
            actionsHtml += `<button class="btn btn-sm btn-icon expense-request-details hitech-action-icon" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#modalExpenseRequestDetails" title="View Details"><i class="bx bx-show fs-5"></i></button>`;

            if (full['status'] === 'pending') {
              actionsHtml += `<button class="btn btn-sm btn-icon hitech-action-icon text-success quick-expense-approve" data-id="${full['id']}" title="Quick Approve"><i class="bx bx-check-circle fs-5"></i></button>`;
              actionsHtml += `<button class="btn btn-sm btn-icon hitech-action-icon text-danger quick-expense-reject" data-id="${full['id']}" title="Quick Reject"><i class="bx bx-x-circle fs-5"></i></button>`;
            }

            actionsHtml += '</div>';
            return actionsHtml;
          }
        },
        {
          // Approved By
          targets: 8,
          visible: false,
          className: 'text-start approved-by-col',
          render: function (data, type, full, meta) {
            return '<span class="fw-bold text-dark">' + (full['approved_by_name'] || 'N/A') + '</span>';
          }
        },
        {
          // Approved At
          targets: 9,
          visible: false,
          className: 'text-start approved-at-col',
          render: function (data, type, full, meta) {
            return '<span class="text-muted">' + (full['approved_at_formatted'] || 'N/A') + '</span>';
          }
        }
      ],
      order: [[1, 'asc']],
      dom: 't<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      lengthMenu: [10, 25, 50, 70, 100],
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search Expenses',
        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
        paginate: {
          next: '<i class="bx bx-chevron-right bx-sm"></i>',
          previous: '<i class="bx bx-chevron-left bx-sm"></i>'
        }
      }
    });

    // Handle Custom Length
    $('#customLengthMenu').on('change', function () {
      dtExpenseRequests.page.len($(this).val()).draw();
    });

    // Handle Custom Search
    $('#btnSearch').on('click', function () {
      dtExpenseRequests.ajax.reload();
    });
    $('#customSearchInput').on('keyup', function (e) {
      if (e.key === 'Enter') dtExpenseRequests.ajax.reload();
    });

    // Handle Filters
    $('#employeeFilter, #expenseTypeFilter, #dateFilter').on('change', function () {
      dtExpenseRequests.ajax.reload();
    });

    // Handle Status Toggle
    $('.status-toggle-btn').on('click', function () {
      $('.status-toggle-btn').removeClass('active');
      $(this).addClass('active');
      const status = $(this).data('status');

      if (status === 'approved') {
        dtExpenseRequests.column('.status-col').visible(false);
        dtExpenseRequests.column('.action-col').visible(false);
        dtExpenseRequests.column('.approved-by-col').visible(true);
        dtExpenseRequests.column('.approved-at-col').visible(true);
      } else {
        dtExpenseRequests.column('.status-col').visible(true);
        dtExpenseRequests.column('.action-col').visible(true);
        dtExpenseRequests.column('.approved-by-col').visible(false);
        dtExpenseRequests.column('.approved-at-col').visible(false);
      }

      dtExpenseRequests.ajax.reload();
    });
  }

  // Details Modal Logic
  $(document).on('click', '.expense-request-details', function () {
    var id = $(this).data('id');

    // UI Reset
    $('#statusInput').val('');
    $('#adminRemarks').val('');
    $('#expenseRequestForm').hide();
    $('#alreadyRespondedNotice').hide();
    $('#documentHide').hide();

    $.get(`${baseUrl}expenseRequests/getByIdAjax/${id}`, function (response) {
      if (response.status === 'success') {
        var data = response.data;
        var statusDiv = $('#statusDiv');

        $('#id').val(data.id);
        $('#userName, #userNameLabel').text(data.userName);
        $('#userCode').text(data.userCode);

        if (data.user_profile_image) {
          $('#userAvatarContainer').html(`<img src="${data.user_profile_image}" class="avatar avatar-md rounded-circle border" />`);
        } else {
          $('#userAvatarContainer').html(`<div class="avatar avatar-md"><span class="avatar-initial rounded-circle bg-label-primary shadow-sm">${data.userInitials || ''}</span></div>`);
        }

        $('#expenseType').text(data.expenseType);
        $('#forDate').text(data.forDate);
        $('#amountDisplay, #amountInBadge').text(currencySymbol + data.amount.toLocaleString());
        $('#approvedAmount').val(data.approvedAmount);
        $('#createdAt').text(data.createdAt);
        $('#userNotes').text(data.userNotes || 'N/A');

        if (data.status === 'approved') {
          statusDiv.html('<span class="badge bg-label-success px-3 py-2"><i class="bx bx-check-circle me-1"></i>Approved</span>');
          $('#finalStatusMsg').html(`Finalized at <strong>${currencySymbol}${data.approvedAmount}</strong>.`);
          $('#alreadyRespondedNotice').show();
        } else if (data.status === 'rejected') {
          statusDiv.html('<span class="badge bg-label-danger px-3 py-2"><i class="bx bx-x-circle me-1"></i>Rejected</span>');
          $('#finalStatusMsg').text('This request was declined.');
          $('#alreadyRespondedNotice').show();
        } else {
          statusDiv.html('<span class="badge bg-label-warning px-3 py-2"><i class="bx bx-time me-1"></i>Pending Review</span>');
          $('#expenseRequestForm').show();
        }

        if (data.document) {
          $('#document').attr('src', data.document);
          $('#documentHide').show();
        }
      }
    });
  });

  // Submit Decision
  window.submitExpenseDecision = function (status) {
    if (status === 'rejected' && !$('#adminRemarks').val().trim()) {
      Swal.fire({ title: 'Remarks Required', text: 'Please provide a reason for rejection.', icon: 'warning', confirmButtonColor: '#005a5a' });
      $('#adminRemarks').focus(); return;
    }

    $('#statusInput').val(status);
    $('#expenseRequestForm').submit();
  };

  // Form Submission via AJAX
  $('#expenseRequestForm').on('submit', function (e) {
    e.preventDefault();
    var form = $(this);
    var status = $('#statusInput').val();
    var btn = status === 'approved' ? $('#btnApprove') : $('#btnReject');
    var original = btn.html();

    btn.addClass('disabled').html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

    $.ajax({
      url: form.attr('action') || `${baseUrl}expenseRequests/actionAjax`,
      type: 'POST',
      data: form.serialize(),
      success: function (response) {
        $('#modalExpenseRequestDetails').modal('hide');
        Swal.fire({ title: 'Success!', text: `Expense updated successfully.`, icon: 'success', timer: 2000, showConfirmButton: false });
        if (dtExpenseRequests) dtExpenseRequests.ajax.reload();
        setTimeout(() => { location.reload(); }, 2000);
      },
      error: function () {
        btn.removeClass('disabled').html(original);
        Swal.fire('Error', 'Failed to process request.', 'error');
      }
    });
  });

  // Quick Actions (Table-based)
  $(document).on('click', '.quick-expense-approve', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Quick Approve Expense?',
      text: 'Are you sure you want to approve this expense for the full amount?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Approve',
      confirmButtonColor: '#005a5a'
    }).then((result) => {
      if (result.isConfirmed) performQuickAction(id, 'approved', 'Quick approved via table action.');
    });
  });

  $(document).on('click', '.quick-expense-reject', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Reject Expense?',
      text: 'Provide a reason for rejection:',
      input: 'textarea',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Submit Rejection',
      confirmButtonColor: '#ff4d4d',
      preConfirm: (val) => { if (!val) Swal.showValidationMessage('Remarks are required!'); return val; }
    }).then((result) => {
      if (result.isConfirmed) performQuickAction(id, 'rejected', result.value);
    });
  });

  function performQuickAction(id, status, notes) {
    $.ajax({
      url: `${baseUrl}expenseRequests/actionAjax`,
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        id: id,
        status: status,
        adminRemarks: notes,
        approvedAmount: status === 'approved' ? -1 : 0 // -1 flag to server to use original amount if needed, or I'll just handle it
      },
      success: function (response) {
        Swal.fire({ title: 'Success!', text: `Expense has been ${status}.`, icon: 'success', timer: 2000, showConfirmButton: false });
        if (dtExpenseRequests) dtExpenseRequests.ajax.reload();
        setTimeout(() => { location.reload(); }, 2000);
      },
      error: function () { Swal.fire('Error', 'Failed to process.', 'error'); }
    });
  }

  // Lightbox init
  $('#modalExpenseRequestDetails').on('shown.bs.modal', function () {
    if (typeof lightbox !== 'undefined') lightbox.reload();
  });

  // Expense Trend Chart
  if ($('#expenseTrendChart').length) {
    const options = {
      series: [{
        name: 'Reimbursements',
        data: [44, 55, 41, 67, 22, 43]
      }],
      chart: {
        height: 350,
        type: 'bar',
        toolbar: { show: false },
        fontFamily: 'Outfit, sans-serif'
      },
      plotOptions: {
        bar: {
          borderRadius: 10,
          columnWidth: '40%',
          distributed: true,
        }
      },
      dataLabels: { enabled: false },
      legend: { show: false },
      colors: ['#004d4d', '#007a7a', '#00a3a3', '#0d9488', '#0f766e', '#115e59'],
      xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        labels: { style: { colors: '#64748b', fontWeight: 600 } }
      },
      tooltip: {
        y: {
          formatter: function (val) {
            return currencySymbol + val.toLocaleString();
          }
        }
      },
      grid: { borderColor: 'rgba(0,0,0,0.05)', strokeDashArray: 4 }
    };

    const chart = new ApexCharts(document.querySelector("#expenseTrendChart"), options);
    chart.render();
  }
});
