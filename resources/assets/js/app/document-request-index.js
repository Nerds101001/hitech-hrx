$(function () {
    'use strict';

    var dtDocumentTable = $('.datatables-documentRequests');
    var dtDocumentRequests;

    // Setup AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    if (dtDocumentTable.length) {
        var employeeView = baseUrl + 'employees/view/';

        dtDocumentRequests = dtDocumentTable.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + 'documentmanagement/getListAjax',
                data: function (d) {
                    d.searchTerm = $('#customSearchInput').val();
                    d.statusFilter = $('#statusFilter').val();
                }
            },
            columns: [
                { data: 'user_name' },
                { data: 'document_type' },
                { data: 'status' },
                { data: 'created_at' },
                { data: '' } // Actions
            ],
            columnDefs: [
                {
                    // Employee with Avatar
                    targets: 0,
                    responsivePriority: 4,
                    render: function (data, type, full, meta) {
                        var $name = full['user_name'],
                            code = full['user_code'],
                            initials = full['user_initial'],
                            profileOutput,
                            rowOutput;

                        if (full['user_profile_image']) {
                            profileOutput = '<img src="' + full['user_profile_image'] + '" alt="Avatar" class="avatar rounded-circle " />';
                        } else {
                            initials = full['user_initial'] || '';
                            profileOutput = '<span class="avatar-initial rounded-circle bg-label-info">' + initials + '</span>';
                        }

                        rowOutput =
                            '<div class="d-flex justify-content-start align-items-center user-name">' +
                            '<div class="avatar-wrapper">' +
                            '<div class="avatar avatar-sm me-4">' +
                            profileOutput +
                            '</div>' +
                            '</div>' +
                            '<div class="d-flex flex-column">' +
                            '<a href="' +
                            baseUrl + 'employees/view/' + full['user_id'] + '" class="text-heading text-truncate"><span class="fw-medium">' +
                            $name +
                            '</span></a>' +
                            '<small class="text-muted">' +
                            code +
                            '</small>' +
                            '</div>' +
                            '</div>';
                        return rowOutput;
                    }
                },
                {
                    // Document Type
                    targets: 1,
                    render: function (data, type, full, meta) {
                        return '<span class="badge badge-hitech-success">' + data + '</span>';
                    }
                },
                {
                    // Status
                    targets: 2,
                    render: function (data, type, full, meta) {
                        var $status = data;
                        if ($status === 'Approved') {
                            return '<span class="badge badge-hitech bg-label-success"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Approved</span>';
                        } else if ($status === 'Rejected') {
                            return '<span class="badge badge-hitech bg-label-danger"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Rejected</span>';
                        } else {
                            return '<span class="badge badge-hitech bg-label-warning"><i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>Pending</span>';
                        }
                    }
                },
                {
                    // Created At
                    targets: 3,
                    render: function (data, type, full, meta) {
                        return '<span class="text-muted">' + data + '</span>';
                    }
                },
                {
                    // Actions
                    targets: 4,
                    searchable: false,
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, full, meta) {
                        var actionsHtml = '<div class="d-flex align-items-center justify-content-center gap-2">';
                        actionsHtml += `<button class="btn btn-sm btn-icon hitech-action-icon edit-record shadow-sm" data-id="${full['id']}" data-bs-toggle="modal" data-bs-target="#actionModal" title="Review" style="width:32px; height:32px;"><i class="bx bx-show fs-5"></i></button>`;

                        if (full['status'] === 'Pending') {
                            actionsHtml += `<button class="btn btn-sm btn-icon hitech-action-icon text-success quick-approve shadow-sm" data-id="${full['id']}" title="Quick Approve" style="width:32px; height:32px;"><i class="bx bx-check fs-5"></i></button>`;
                            actionsHtml += `<button class="btn btn-sm btn-icon hitech-action-icon text-danger quick-reject shadow-sm" data-id="${full['id']}" title="Quick Reject" style="width:32px; height:32px;"><i class="bx bx-x fs-5"></i></button>`;
                        }

                        actionsHtml += '</div>';
                        return actionsHtml;
                    }
                }
            ],
            order: [[0, 'desc']],
            dom: 'rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                paginate: {
                    next: '<i class="bx bx-chevron-right bx-sm"></i>',
                    previous: '<i class="bx bx-chevron-left bx-sm"></i>'
                }
            }
        });

        // Handle Custom Search
        $('#customSearchInput').on('keyup', function (e) {
            dtDocumentRequests.ajax.reload();
        });

        $('.status-toggle-btn').on('click', function () {
            $('.status-toggle-btn').removeClass('active');
            $(this).addClass('active');

            var status = $(this).data('status');
            $('#statusFilter').val(status);
            dtDocumentRequests.ajax.reload();
        });
    }

    // Modal Logic
    $(document).on('click', '.edit-record', function () {
        var id = $(this).data('id');
        $.get(baseUrl + 'documentmanagement/getByIdAjax/' + id, function (response) {
            if (response.status === 'success') {
                var data = response.data;
                $('#requestId').val(data.id);

                // Reset and set status radios
                $('input[name="status"]').prop('checked', false);
                if (data.status === 'Approved') $('#statusApproved').prop('checked', true);
                else if (data.status === 'Rejected') $('#statusRejected').prop('checked', true);
                else $('#statusPending').prop('checked', true);

                $('#adminRemarks').val(data.admin_remarks || '');
                $('#userRemarks').text(data.remarks || 'No remarks provided');
                $('#employeeName').text(data.user ? data.user.first_name + ' ' + data.user.last_name : 'N/A');
                $('#docTypeName').text(data.document_type ? data.document_type.name : 'N/A');
            }
        });
    });

    // Quick Actions
    $(document).on('click', '.quick-approve', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Approve Request?',
            text: 'Are you sure you want to approve this document request?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve',
            confirmButtonColor: '#005a5a'
        }).then((result) => {
            if (result.isConfirmed) performAction(id, 'Approved', 'Quick approved.');
        });
    });

    $(document).on('click', '.quick-reject', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Reject Request?',
            text: 'Provide a reason for rejection:',
            input: 'textarea',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Submit Rejection',
            confirmButtonColor: '#ff4d4d',
            preConfirm: (val) => { if (!val) Swal.showValidationMessage('Remarks are required!'); return val; }
        }).then((result) => {
            if (result.isConfirmed) performAction(id, 'Rejected', result.value);
        });
    });

    function performAction(id, status, remarks) {
        $.ajax({
            url: baseUrl + 'documentmanagement/actionAjax',
            method: "POST",
            data: {
                id: id,
                status: status,
                admin_remarks: remarks,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                Swal.fire({ icon: 'success', title: 'Success', text: response.message, timer: 1500, showConfirmButton: false });
                if (dtDocumentRequests) dtDocumentRequests.ajax.reload();
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong' });
            }
        });
    }

    // Global scope for modal button
    window.submitAction = function () {
        const formData = $('#actionForm').serialize();
        $.ajax({
            url: baseUrl + 'documentmanagement/actionAjax',
            method: "POST",
            data: formData + "&_token=" + $('meta[name="csrf-token"]').attr('content'),
            success: function (response) {
                Swal.fire({ icon: 'success', title: 'Success', text: response.message, timer: 1500, showConfirmButton: false });
                $('#actionModal').modal('hide');
                if (dtDocumentRequests) dtDocumentRequests.ajax.reload();
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON ? xhr.responseJSON.message : 'Something went wrong' });
            }
        });
    };
});
