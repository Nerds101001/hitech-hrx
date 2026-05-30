@extends('layouts/layoutMaster')

@section('title', 'Policy Management (Admin)')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
        <h3 class="fw-extrabold mb-0 text-dark">Policy Management (Admin)</h3>
        <div class="d-flex gap-3 flex-wrap">
            <button type="button" class="btn btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadPolicyModal">
                <i class="bx bx-upload fs-5"></i>
                <span class="fw-bold">Upload New Policy</span>
            </button>
        </div>
    </div>

    <div class="row g-6 mb-6 mx-0 px-4">
        <div class="col-xl-4 col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
            <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
                <div class="stat-card-header mb-2">
                    <div class="stat-icon-wrap icon-teal" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-folder"></i></div>
                    <div class="trend-indicator text-success" style="font-size:0.65rem; padding: 2px 6px;">Live Policies</div>
                </div>
                <div>
                    <h3 class="stat-value mb-0">{{ $policies->count() }}</h3>
                    <span class="stat-label">Active Policies</span>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
            <div class="hitech-stat-card dashboard-variant card-red uniform-card">
                <div class="stat-card-header mb-2">
                    <div class="stat-icon-wrap icon-red" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-error-circle"></i></div>
                    <div class="trend-indicator text-danger" style="font-size:0.65rem; padding: 2px 6px;">Required</div>
                </div>
                <div>
                    <h3 class="stat-value mb-0">{{ $policies->where('is_mandatory', 1)->count() }}</h3>
                    <span class="stat-label">Mandatory Policies</span>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
            <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
                <div class="stat-card-header mb-2">
                    <div class="stat-icon-wrap icon-blue" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-pen"></i></div>
                    <div class="trend-indicator text-primary" style="font-size:0.65rem; padding: 2px 6px;">Total Engagement</div>
                </div>
                <div>
                    <h3 class="stat-value mb-0">{{ $policies->sum('acknowledgments_count') }}</h3>
                    <span class="stat-label">Total Signatures</span>
                </div>
            </div>
        </div>
    </div>

    <div class="animate__animated animate__fadeIn px-4 mt-6">
        <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
            <div class="table-responsive">
                <table class="table m-0 shadow-none border-0 align-middle">
                    <thead class="text-dark">
                        <tr>
                            <th class="ps-4">Policy</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Signatures</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($policies as $policy)
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td class="ps-4">
                                <div class="d-flex justify-content-start align-items-center user-name">
                                    <div class="avatar-wrapper">
                                        <div class="avatar avatar-sm me-3">
                                            <span class="avatar-initial-hitech" style="background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 100%; height: 100%;">
                                                <i class="bx bxs-file-pdf text-danger fs-5"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <span class="fw-medium mb-0" style="font-size: 0.875rem; color: #1e293b;">{{ $policy->title }}</span>
                                        <small class="text-muted" style="font-size: 0.75rem;">{{ $policy->created_at->format('d M Y') }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-code-hitech" style="background-color: rgba(18, 116, 100, 0.1); color: #127464; border-radius: 4px; padding: 4px 8px; font-weight: 600;">{{ $policy->category }}</span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    @if($policy->is_mandatory)
                                        <span class="badge bg-danger-light text-danger rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">Mandatory</span>
                                    @else
                                        <span class="badge bg-label-secondary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;">Optional</span>
                                    @endif
                                    
                                    @if($policy->show_as_popup)
                                        <span class="badge bg-warning-light text-warning rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;"><i class="bx bx-window-open small me-1"></i>Popup</span>
                                    @endif
                                    
                                    @if(empty($policy->target_departments))
                                        <span class="badge bg-success-light text-success rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;"><i class="bx bx-buildings small me-1"></i>All Depts</span>
                                    @else
                                        <span class="badge bg-info-light text-info rounded-pill px-3 py-1 fw-bold" style="font-size: 0.7rem;"><i class="bx bx-target-lock small me-1"></i>Targeted ({{ count($policy->target_departments) }})</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('hr-policies.acknowledgments', $policy->id) }}" class="text-decoration-none d-flex align-items-center justify-content-center" style="width: fit-content; background-color: rgba(18, 116, 100, 0.08); border: 1px solid rgba(18, 116, 100, 0.2); padding: 4px 12px; border-radius: 50px; transition: all 0.2s ease;">
                                    <span class="fw-bold me-2" style="color: #127464;">{{ $policy->acknowledgments_count }}</span>
                                    <small class="fw-medium" style="color: #127464; font-size: 0.75rem;">view list</small>
                                </a>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-icon btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bx bx-dots-vertical-rounded fs-5 text-muted"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-hitech shadow-lg">
                                        <li><a class="dropdown-item dropdown-item-hitech py-2 d-flex align-items-center" href="{{ route('hr-policies.view', $policy->id) }}" target="_blank"><i class="bx bx-show me-2 text-info"></i> View PDF</a></li>
                                        <li><a class="dropdown-item dropdown-item-hitech py-2 d-flex align-items-center" href="{{ route('hr-policies.acknowledgments', $policy->id) }}"><i class="bx bx-list-check me-2 text-primary"></i> Acknowledgments</a></li>
                                        <li><hr class="dropdown-divider mx-3"></li>
                                        <li><a class="dropdown-item dropdown-item-hitech text-danger py-2 d-flex align-items-center delete-policy" href="javascript:void(0);" data-id="{{ $policy->id }}" data-title="{{ $policy->title }}"><i class="bx bx-trash me-2 text-danger"></i> Delete</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-10">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <i class="bx bx-folder-open text-muted mb-2" style="font-size: 2.5rem;"></i>
                                    <p class="mb-0 text-muted">No policies uploaded yet. Click "Upload New Policy" to get started.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="py-4"></div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Policy Modal -->
<div class="modal fade" id="uploadPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-hitech border-0 shadow-lg">
            <div class="modal-header modal-header-hitech">
                <h5 class="modal-title modal-title-hitech d-flex align-items-center gap-3">
                    <i class="bx bx-upload fs-4"></i>
                    Upload New Policy
                </h5>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <div class="modal-body p-6">
                <form action="{{ route('hr-policies.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-hitech">Policy Title</label>
                            <input type="text" name="title" class="form-control form-control-hitech" placeholder="e.g. Code of Conduct" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech">Category</label>
                            <select name="category" class="form-select form-control-hitech">
                                <option value="General">General</option>
                                <option value="Security">Security</option>
                                <option value="Attendance">Attendance</option>
                                <option value="Finance">Finance</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-hitech">Target Departments</label>
                            <select name="target_departments[]" class="form-select select2 form-control-hitech" multiple="multiple" data-placeholder="All Departments (Default)">
                                <option value="all">All Departments (Default)</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text small mt-1 text-muted">Leave blank or select "All Departments" to apply to everyone.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label-hitech">Policy PDF</label>
                            <input type="file" name="file" class="form-control form-control-hitech" accept="application/pdf" required>
                            <div class="form-text small text-muted mt-1">Max 10MB. PDF only.</div>
                        </div>
                        <div class="col-12">
                            <div class="p-4 rounded-3" style="background-color: #F8FAFC;">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_mandatory" value="1" id="is_mandatory" checked>
                                    <label class="form-check-label fw-bold text-dark" for="is_mandatory">Mandatory for all staff</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_as_popup" value="1" id="show_as_popup" checked>
                                    <label class="form-check-label fw-bold text-dark" for="show_as_popup">Show as popup to all</label>
                                    <div class="form-text small mt-1">If disabled, it will only show in the staff's 'My Policies' list.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label-hitech">Short Description</label>
                            <textarea name="description" class="form-control form-control-hitech" rows="3" placeholder="Brief summary of the policy..."></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-3 mt-5">
                        <button type="button" class="btn btn-label-danger px-6 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-hitech px-8 rounded-pill fw-bold shadow-sm">
                            <i class="bx bx-upload me-2"></i> Upload & Publish
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page-script')
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
                confirmButtonText: 'Yes, delete it!',
                customClass: {
                    confirmButton: 'btn btn-danger me-3 rounded-pill fw-bold px-4',
                    cancelButton: 'btn btn-label-secondary rounded-pill fw-bold px-4'
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
                                    confirmButton: 'btn btn-success rounded-pill fw-bold px-4'
                                }
                            }).then(() => {
                                location.reload();
                            });
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to delete policy.',
                                customClass: {
                                    confirmButton: 'btn btn-primary rounded-pill fw-bold px-4'
                                }
                            });
                        }
                    });
                }
            });
        });
        
        // Initialize select2
        if ($('.select2').length) {
            $('.select2').select2({
                dropdownParent: $('#uploadPolicyModal')
            });
        }
    });
});
</script>
@endsection
