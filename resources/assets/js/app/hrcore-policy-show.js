$(function () {
    'use strict';

    // Set up AJAX defaults
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize components
    function initializeComponents() {
        bindEvents();
    }

    // Bind events
    function bindEvents() {
        // Handle action buttons
        $('.action-btn').on('click', function() {
            const action = $(this).data('action');
            const policyId = $(this).data('id');
            
            switch(action) {
                case 'edit':
                    window.location.href = pageData.urls.edit.replace(':id', policyId);
                    break;
                case 'download':
                    window.location.href = pageData.urls.download.replace(':id', policyId);
                    break;
                case 'acknowledge':
                    acknowledgePolicy(policyId);
                    break;
            }
        });

        // Handle publish button
        $('.publish-btn').on('click', function(e) {
            e.preventDefault();
            const url = $(this).attr('href');
            
            Swal.fire({
                title: pageData.labels.confirmPublish,
                text: pageData.labels.publishWarning,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: pageData.labels.yesPublish,
                cancelButtonText: pageData.labels.cancel
            }).then((result) => {
                if (result.isConfirmed) {
                    publishPolicy(url);
                }
            });
        });

        // Handle archive button
        $('.archive-btn').on('click', function(e) {
            e.preventDefault();
            const url = $(this).attr('href');
            
            Swal.fire({
                title: pageData.labels.confirmArchive,
                text: pageData.labels.archiveWarning,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: pageData.labels.yesArchive,
                cancelButtonText: pageData.labels.cancel
            }).then((result) => {
                if (result.isConfirmed) {
                    archivePolicy(url);
                }
            });
        });

        // Handle assign button
        $('.assign-btn').on('click', function(e) {
            e.preventDefault();
            const policyId = $(this).data('id');
            openAssignModal(policyId);
        });
    }

    // Acknowledge policy
    function acknowledgePolicy(id) {
        Swal.fire({
            title: pageData.labels.acknowledgePolicy,
            html: `
                <p>${pageData.labels.acknowledgeConfirmation}</p>
                <div class="mt-3">
                    <label class="form-label">${pageData.labels.commentsOptional}</label>
                    <textarea id="acknowledgment-comments" class="form-control" rows="3" placeholder="${pageData.labels.addComments}"></textarea>
                </div>
            `,
            icon: 'question',
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
                submitAcknowledgment(id, result.value.comments);
            }
        });
    }

    // Submit acknowledgment
    function submitAcknowledgment(id, comments) {
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
                    }).then(() => {
                        // Reload page to update acknowledgment status
                        window.location.reload();
                    });
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

    // Publish policy
    function publishPolicy(url) {
        $.ajax({
            url: url,
            type: 'POST',
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
                        title: pageData.labels.published,
                        text: response.data.message || pageData.labels.publishSuccess,
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: pageData.labels.error,
                        text: response.data || pageData.labels.publishFailed
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

    // Archive policy
    function archivePolicy(url) {
        $.ajax({
            url: url,
            type: 'POST',
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
                        title: pageData.labels.archived,
                        text: response.data.message || pageData.labels.archiveSuccess,
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: pageData.labels.error,
                        text: response.data || pageData.labels.archiveFailed
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

    // Open assign modal
    function openAssignModal(policyId) {
        // Redirect to policies index page with assign parameter
        window.location.href = pageData.urls.policies + '?assign=' + policyId;
    }

    // Initialize when document is ready
    initializeComponents();
});