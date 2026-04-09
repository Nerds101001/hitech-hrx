$(function () {
    'use strict';

    let categoriesTable, addOrUpdateForm, categoriesOffcanvas;

    // Initialize components
    function initializeComponents() {
        // Initialize DataTable
        initializeDataTable();
        
        // Initialize offcanvas
        initializeOffcanvas();
        
        // Initialize forms
        initializeForms();
        
        // Bind events
        bindEvents();
    }

    // Initialize DataTable
    function initializeDataTable() {
        if ($.fn.DataTable.isDataTable('.datatables-policy-categories')) {
            $('.datatables-policy-categories').DataTable().destroy();
        }

        categoriesTable = $('.datatables-policy-categories').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: pageData.urls.datatable,
                type: 'GET'
            },
            columns: [
                { data: 'id', name: 'id', width: '5%' },
                { data: 'name', name: 'name' },
                { data: 'description', name: 'description' },
                { data: 'color_display', name: 'color_display', width: '8%' },
                { data: 'icon_display', name: 'icon_display', width: '8%' },
                { data: 'sort_order', name: 'sort_order', width: '10%' },
                { data: 'status', name: 'status', width: '10%' },
                { data: 'policies_count', name: 'policies_count', width: '10%' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, width: '12%' }
            ],
            order: [[5, 'asc'], [0, 'desc']], // Sort by sort_order first, then by id
            dom: '<"card-header d-flex border-top rounded-0 flex-wrap py-md-0"<"me-5 ms-n2"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center mb-3 mb-sm-0"lB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
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
            buttons: [
                {
                    text: '<i class="bx bx-plus me-0 me-sm-1"></i><span class="d-none d-sm-inline-block">' + pageData.labels.addCategory + '</span>',
                    className: 'add-new btn btn-primary mx-3',
                    action: function () {
                        openAddCategoryForm();
                    }
                }
            ],
            responsive: {
                details: {
                    display: $.fn.dataTable.Responsive.display.modal({
                        header: function (row) {
                            var data = row.data();
                            return 'Details for ' + data.name;
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

        // Show/hide add button based on permissions
        if (!pageData.permissions.create) {
            $('.add-new').hide();
        }
    }

    // Initialize offcanvas
    function initializeOffcanvas() {
        const addOrUpdateOffcanvasEl = document.getElementById('addOrUpdateCategoryOffcanvas');

        if (addOrUpdateOffcanvasEl) {
            categoriesOffcanvas = new bootstrap.Offcanvas(addOrUpdateOffcanvasEl);
        }
    }

    // Initialize forms
    function initializeForms() {
        addOrUpdateForm = $('#addOrUpdateCategoryForm');
        
        // Color picker sync
        $('#color').on('input', function() {
            $('#color_text').val($(this).val());
        });

        $('#color_text').on('input', function() {
            const colorValue = $(this).val();
            if (/^#[0-9A-F]{6}$/i.test(colorValue)) {
                $('#color').val(colorValue);
            }
        });

        // Icon preview
        $('#icon').on('input', function() {
            const iconClass = $(this).val() || 'bx-category';
            $('#icon-preview').attr('class', 'bx ' + iconClass);
        });
    }

    // Bind events
    function bindEvents() {
        // Add category button
        $(document).on('click', '#addCategoryBtn, .add-new', function() {
            openAddCategoryForm();
        });

        // Form submission
        addOrUpdateForm.on('submit', function(e) {
            e.preventDefault();
            handleFormSubmission();
        });

        // Edit category
        $(document).on('click', '.edit-category', function() {
            const id = $(this).data('id');
            editCategory(id);
        });

        // Delete category
        $(document).on('click', '.delete-category', function() {
            const id = $(this).data('id');
            deleteCategory(id);
        });

        // Toggle status
        $(document).on('click', '.toggle-status', function() {
            const id = $(this).data('id');
            const status = $(this).data('status');
            toggleStatus(id, status);
        });
    }

    // Open add category form
    function openAddCategoryForm() {
        resetForm();
        $('#addOrUpdateCategoryOffcanvasLabel').text(pageData.labels.addCategory);
        $('#submitBtn').text(pageData.labels.create);
        $('#method').val('POST');
        categoriesOffcanvas.show();
    }

    // Edit category
    function editCategory(id) {
        const url = pageData.urls.edit.replace(':id', id);
        
        $.get(url)
            .done(function(response) {
                if (response.status === 'success') {
                    const category = response.data;
                    populateForm(category);
                    $('#addOrUpdateCategoryOffcanvasLabel').text(pageData.labels.editCategory);
                    $('#submitBtn').text(pageData.labels.update);
                    $('#method').val('PUT');
                    $('#category_id').val(id);
                    categoriesOffcanvas.show();
                } else {
                    showNotification('error', response.message || pageData.labels.error);
                }
            })
            .fail(function() {
                showNotification('error', pageData.labels.error);
            });
    }

    // Delete category
    function deleteCategory(id) {
        Swal.fire({
            title: pageData.labels.confirmDelete,
            text: pageData.labels.wontRevert,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: pageData.labels.yesDeleteIt,
            cancelButtonText: pageData.labels.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                const url = pageData.urls.destroy.replace(':id', id);
                
                $.ajax({
                    url: url,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                })
                .done(function(response) {
                    if (response.status === 'success') {
                        showNotification('success', pageData.labels.deleteSuccess);
                        categoriesTable.ajax.reload();
                    } else {
                        showNotification('error', response.message || pageData.labels.error);
                    }
                })
                .fail(function() {
                    showNotification('error', pageData.labels.error);
                });
            }
        });
    }

    // Toggle status
    function toggleStatus(id, currentStatus) {
        const action = currentStatus === 'active' ? 'deactivate' : 'activate';
        const actionText = currentStatus === 'active' ? pageData.labels.deactivate : pageData.labels.activate;
        
        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to ${action} this category?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, ${action} it!`,
            cancelButtonText: pageData.labels.cancel
        }).then((result) => {
            if (result.isConfirmed) {
                const url = pageData.urls.toggleStatus.replace(':id', id);
                
                $.ajax({
                    url: url,
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                })
                .done(function(response) {
                    if (response.status === 'success') {
                        showNotification('success', pageData.labels.statusSuccess);
                        categoriesTable.ajax.reload();
                    } else {
                        showNotification('error', response.message || pageData.labels.error);
                    }
                })
                .fail(function() {
                    showNotification('error', pageData.labels.error);
                });
            }
        });
    }

    // Handle form submission
    function handleFormSubmission() {
        const formData = new FormData(addOrUpdateForm[0]);
        const method = $('#method').val();
        const id = $('#category_id').val();
        
        // Handle checkbox values
        const isActive = $('#status').is(':checked');
        formData.delete('status');
        formData.append('status', isActive ? 'active' : 'inactive');

        let url = pageData.urls.store;
        if (method === 'PUT') {
            url = pageData.urls.update.replace(':id', id);
        }

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#submitBtn').prop('disabled', true).text(pageData.labels.processing);
            }
        })
        .done(function(response) {
            if (response.status === 'success') {
                const message = method === 'PUT' ? pageData.labels.updateSuccess : pageData.labels.createSuccess;
                showNotification('success', message);
                categoriesOffcanvas.hide();
                categoriesTable.ajax.reload();
                resetForm();
            } else {
                showNotification('error', response.message || pageData.labels.error);
                handleValidationErrors(response.errors);
            }
        })
        .fail(function(xhr) {
            showNotification('error', pageData.labels.error);
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                handleValidationErrors(xhr.responseJSON.errors);
            }
        })
        .always(function() {
            const btnText = method === 'PUT' ? pageData.labels.update : pageData.labels.create;
            $('#submitBtn').prop('disabled', false).text(btnText);
        });
    }

    // Populate form with category data
    function populateForm(category) {
        $('#name').val(category.name);
        $('#description').val(category.description);
        $('#color').val(category.color);
        $('#color_text').val(category.color);
        $('#icon').val(category.icon);
        $('#sort_order').val(category.sort_order);
        $('#status').prop('checked', category.status === 'active');
        
        // Update icon preview
        $('#icon-preview').attr('class', 'bx ' + (category.icon || 'bx-category'));
    }

    // Reset form
    function resetForm() {
        addOrUpdateForm[0].reset();
        $('#category_id').val('');
        $('#method').val('POST');
        $('#color').val('#007bff');
        $('#color_text').val('#007bff');
        $('#icon').val('bx-category');
        $('#icon-preview').attr('class', 'bx bx-category');
        $('#status').prop('checked', true);
        clearValidationErrors();
    }

    // Handle validation errors
    function handleValidationErrors(errors) {
        clearValidationErrors();
        
        if (errors) {
            Object.keys(errors).forEach(function(field) {
                const input = $(`[name="${field}"]`);
                input.addClass('is-invalid');
                input.siblings('.invalid-feedback').text(errors[field][0]);
            });
        }
    }

    // Clear validation errors
    function clearValidationErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    }

    // Show notification
    function showNotification(type, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type,
                title: type === 'success' ? pageData.labels.success : pageData.labels.error,
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        }
    }

    // Initialize when document is ready
    initializeComponents();
});