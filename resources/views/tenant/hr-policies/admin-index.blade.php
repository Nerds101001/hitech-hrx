@extends('layouts/layoutMaster')

@section('title', 'Policy Management (Admin)')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Management /</span> HR Policies
</h4>

<div class="row">
    <!-- Upload Section -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Upload New Policy</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('hr-policies.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Policy Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Code of Conduct" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="General">General</option>
                            <option value="Security">Security</option>
                            <option value="Attendance">Attendance</option>
                            <option value="Finance">Finance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Policy PDF</label>
                        <input type="file" name="file" class="form-control" accept="application/pdf" required>
                        <div class="form-text text-muted small">Max 10MB. PDF only.</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_mandatory" value="1" checked>
                            <label class="form-check-label">Mandatory for all staff</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="show_as_popup" value="1" checked>
                            <label class="form-check-label">Show as popup to all</label>
                        </div>
                        <div class="form-text small">If disabled, it will only show in 'My Policies' list.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bx bx-upload me-1"></i> Upload & Publish
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Policy List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Active Policies</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Policy</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Signatures</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($policies as $policy)
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold">{{ $policy->title }}</span>
                                    <small class="text-muted">{{ $policy->created_at->format('d M Y') }}</small>
                                </div>
                            </td>
                            <td><span class="badge bg-label-info">{{ $policy->category }}</span></td>
                            <td>
                                @if($policy->is_mandatory)
                                    <span class="badge bg-label-danger">Mandatory</span>
                                @else
                                    <span class="badge bg-label-secondary">Optional</span>
                                @endif
                                
                                @if($policy->show_as_popup)
                                    <span class="badge bg-label-warning ms-1" title="Forces a popup on login"><i class="bx bx-window-open small"></i> Popup</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('hr-policies.acknowledgments', $policy->id) }}" class="text-decoration-none">
                                    <div class="d-flex align-items-center">
                                        <span class="me-2 fw-bold text-primary">{{ $policy->acknowledgments_count }}</span>
                                        <small class="text-muted text-underline-hover">view list</small>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="{{ route('hr-policies.view', $policy->id) }}" target="_blank"><i class="bx bx-show me-1"></i> View PDF</a>
                                        <a class="dropdown-item" href="{{ route('hr-policies.acknowledgments', $policy->id) }}"><i class="bx bx-list-check me-1"></i> View Acknowledgment List</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-policy" href="javascript:void(0);" data-id="{{ $policy->id }}" data-title="{{ $policy->title }}"><i class="bx bx-trash me-1"></i> Delete</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No policies uploaded yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $(function() {
        $('.delete-policy').on('click', function() {
        const id = $(this).data('id');
        const title = $(this).data('title');

        Swal.fire({
            title: 'Delete Policy?',
            text: `Are you sure you want to delete "${title}"? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff3e1d',
            cancelButtonColor: '#8592a3',
            confirmButtonText: 'Yes, delete it!',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/hr-policies/${id}`,
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.success,
                            customClass: {
                                confirmButton: 'btn btn-success'
                            }
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to delete policy.'
                        });
                    }
                });
            }
        });
        });
    });
});
</script>
@endsection
