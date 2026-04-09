$(function () {
    var dt_table = $('.datatables-asset-categories');

    // Ajax setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    if (dt_table.length) {
        var dt_category = dt_table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + 'asset-categories/list-ajax',
                type: 'GET',
                data: function (d) {
                    d.searchTerm = $('#customSearchInput').val();
                },
                error: function (xhr, error, code) {
                    console.log('Error: ' + error);
                }
            },
            columns: [
                { data: '' },
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'description', name: 'description' },
                { data: 'assets_count', name: 'assets_count', searchable: false, orderable: false },
                { data: 'status_badge', name: 'status_badge', searchable: false, orderable: false },
                { data: 'action', name: 'action', searchable: false, orderable: false }
            ],
            columnDefs: [
                {
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
                    targets: 1,
                    render: function (data, type, full, meta) {
                        return '<span>' + full.DT_RowIndex + '</span>';
                    }
                },
                {
                    targets: 3,
                    render: function (data, type, full, meta) {
                        return data ? '<span>' + data + '</span>' : '<span class="text-muted">No description</span>';
                    }
                }
            ],
            order: [[2, 'asc']],
            dom: '<"row"<"col-sm-12"tr>><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Search Category',
                info: 'Displaying _START_ to _END_ of _TOTAL_ entries',
                paginate: {
                    next: '<i class="bx bx-chevron-right bx-sm"></i>',
                    previous: '<i class="bx bx-chevron-left bx-sm"></i>'
                }
            },
            buttons: []
        });

        // --- Custom Search ---
        $('#customSearchInput').on('keyup', function () {
            dt_category.draw();
        });

        $('#customSearchBtn').on('click', function () {
            dt_category.draw();
        });
    }

    var modalForm = $('#offcanvasAddOrUpdateCategory');
    const addCategoryForm = document.getElementById('assetCategoryForm');

    $(document).on('click', '.add-new-category', function () {
        $('#id').val('');
        $('#name').val('');
        $('#description').val('');
        $('#status').val('active');
        $('#parameters').val('');
        $('#offcanvasCategoryLabel').html('Add Category');
        if (fv) fv.resetForm(true);
    });

    $(document).on('click', '.edit-category', function () {
        var id = $(this).data('id');

        $('#offcanvasCategoryLabel').html('Edit Category');

        $.get(`${baseUrl}asset-categories/${id}/edit`, function (response) {
            if (response.success) {
                var data = response.data;
                $('#id').val(data.id);
                $('#name').val(data.name);
                $('#description').val(data.description);
                $('#status').val(data.status);
                $('#parameters').val(data.parameters ? data.parameters.join(', ') : '');
                modalForm.modal('show');
            }
        });
    });

    if (addCategoryForm) {
        const fv = FormValidation.formValidation(addCategoryForm, {
        fields: {
            name: {
                validators: {
                    notEmpty: {
                        message: 'Category name is required'
                    }
                }
            }
        },
        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap5: new FormValidation.plugins.Bootstrap5({
                eleValidClass: '',
                rowSelector: function (field, ele) {
                    return '.mb-3';
                }
            }),
            submitButton: new FormValidation.plugins.SubmitButton(),
            autoFocus: new FormValidation.plugins.AutoFocus()
        }
    }).on('core.form.valid', function () {
        var id = $('#id').val();
        var url = id ? `${baseUrl}asset-categories/${id}` : `${baseUrl}asset-categories`;
        var method = id ? 'PUT' : 'POST';

        $.ajax({
            data: $('#assetCategoryForm').serialize(),
            url: url,
            type: method,
            success: function (response) {
                if (response.success) {
                    modalForm.modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        customClass: {
                            confirmButton: 'btn btn-success'
                        }
                    });
                    dt_category.draw();
                }
            },
            error: function (err) {
                Swal.fire({
                    title: 'Error',
                    text: err.responseJSON && err.responseJSON.message ? err.responseJSON.message : 'Please check your inputs',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-success'
                    }
                });
            }
        });
    });
    modalForm.on('hidden.bs.modal', function () {
        if (fv) fv.resetForm(true);
    });
    }

    $(document).on('click', '.delete-category', function () {
        var id = $(this).data('id');

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
                $.ajax({
                    type: 'DELETE',
                    url: `${baseUrl}asset-categories/${id}`,
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: response.message,
                                customClass: {
                                    confirmButton: 'btn btn-success'
                                }
                            });
                            dt_category.draw();
                        }
                    },
                    error: function (error) {
                        Swal.fire({
                            title: 'Error',
                            text: error.responseJSON && error.responseJSON.message ? error.responseJSON.message : 'Something went wrong',
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            }
                        });
                    }
                });
            }
        });
    });

});
