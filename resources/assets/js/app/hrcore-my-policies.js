$(function () {
    'use strict';

    // Set up AJAX defaults
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    let myPoliciesTable;

    // Initialize components
    function initializeComponents() {
        // Initialize DataTable
        initializeDataTable();
        
        // Load statistics
        loadStatistics();
        
        // Bind events
        bindEvents();
    }

    // Initialize DataTable
    function initializeDataTable() {
        if ($.fn.DataTable.isDataTable('.datatables-my-policies')) {
            $('.datatables-my-policies').DataTable().destroy();
        }

        myPoliciesTable = $('.datatables-my-policies').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: pageData.urls.datatable,
                type: 'GET'
            },
            columns: [
                { data: 'policy.title', name: 'policy.title' },
                { data: 'policy.category.name', name: 'policy.category.name' },
                { data: 'policy.version', name: 'policy_version', width: '8%' },
                { data: 'assigned_date', name: 'assigned_date', width: '12%' },
                { data: 'deadline_date', name: 'deadline_date', width: '12%' },
                { data: 'status', name: 'status', width: '10%' },
                { data: 'acknowledged_date', name: 'acknowledged_date', width: '12%' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, width: '10%' }
            ],
            order: [[3, 'desc']],
            dom: '<"card-header d-flex border-top rounded-0 flex-wrap py-md-0"<"me-5 ms-n2"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center mb-3 mb-sm-0"l>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: pageData.labels.search,
                processing: pageData.labels.processing,
                lengthMenu: pageData.labels.lengthMenu,
                info: pageData.labels.info,
                infoEmpty: pageData.labels.infoEmpty,
                emptyTable: pageData.labels.emptyTable,
                paginate: pageData.labels.paginate
            },
            responsive: {
                details: {
                    display: $.fn.dataTable.Responsive.display.modal({
                        header: function (row) {
                            var data = row.data();
                            return pageData.labels.detailsFor + ' ' + data['policy.title'];
                        }
                    }),
                    type: 'column',
                    renderer: function (api, rowIdx, columns) {
                        var data = $.map(columns, function (col, i) {
                            return col.title !== ''
                                ? '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                                    '<td>' + col.title + '</td> ' +
                                    '<td>' + col.data + '</td>' +
                                  '</tr>'
                                : '';
                        }).join('');
                        return data ? $('<table class="table"/><tbody />').append(data) : false;
                    }
                }
            }
        });
    }

    // Load statistics
    function loadStatistics() {
        $.ajax({
            url: pageData.urls.stats,
            type: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    $('#total-assigned').text(response.data.total || 0);
                    $('#pending-count').text(response.data.pending || 0);
                    $('#acknowledged-count').text(response.data.acknowledged || 0);
                    $('#overdue-count').text(response.data.overdue || 0);
                }
            }
        });
    }

    // Bind events
    function bindEvents() {
        // Handle acknowledge button click
        $(document).on('click', '.acknowledge-policy', function() {
            const id = $(this).data('id');
            const policyTitle = $(this).data('title');
            
            Swal.fire({
                title: pageData.labels.acknowledgePolicy,
                html: `
                    <p>${pageData.labels.acknowledgeConfirmation}</p>
                    <h6 class="text-primary">${policyTitle}</h6>
                    <div class="mt-3">
                        <label class="form-label">${pageData.labels.commentsOptional}</label>
                        <textarea id="acknowledgment-comments" class="form-control" rows="3" placeholder="${pageData.labels.addComments}"></textarea>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: pageData.labels.yesAcknowledge,
                cancelButtonText: pageData.labels.cancel,
                preConfirm: () => {
                    return {
                        comments: document.getElementById('acknowledgment-comments').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    acknowledgePolicy(id, result.value.comments);
                }
            });
        });

        // Handle view policy button click
        $(document).on('click', '.view-policy', function() {
            const policyId = $(this).data('policy-id');
            window.location.href = pageData.urls.viewPolicy.replace(':id', policyId);
        });
    }

    // Acknowledge policy
    function acknowledgePolicy(id, comments) {
        $.ajax({
            url: pageData.urls.acknowledge.replace(':id', id),
            type: 'POST',
            data: {
                comments: comments
            },
            beforeSend: function() {
                Swal.fire({
                    title: pageData.labels.processing,
                    text: pageData.labels.pleaseWait,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: pageData.labels.acknowledged,
                        text: response.data.message || pageData.labels.acknowledgeSuccess,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    myPoliciesTable.ajax.reload();
                    loadStatistics();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: pageData.labels.error,
                        text: response.data || pageData.labels.acknowledgeFailed
                    });
                }
            },
            error: function(xhr) {
                let errorMessage = pageData.labels.errorOccurred;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                Swal.fire({
                    icon: 'error',
                    title: pageData.labels.error,
                    text: errorMessage
                });
            }
        });
    }

    // Initialize when document is ready
    initializeComponents();
});