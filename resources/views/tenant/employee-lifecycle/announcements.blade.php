@extends('layouts/layoutMaster')

@section('title', __('Announcements'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  @include('components.enhanced-css')
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.full.min.js',
    'resources/assets/vendor/libs/apex-charts/apex-charts.min.js',
    'resources/assets/vendor/js/bootstrap.js',
  ])
@endsection

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <x-hero-banner 
      title="Company Announcements"
      subtitle="Broadcast news, events, and important updates to the whole team"
      icon="ti ti-megaphone"
      :show-stats="false"
    />

    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announcementModal">
          <i class="ti ti-plus me-2"></i>Create Announcement
        </button>
      </div>
    </div>

    <div class="row">
      @forelse($announcements as $announcement)
        <div class="col-md-6 col-lg-4 mb-4">
          <div class="card h-100 hitech-card-white border-0 shadow-sm rounded-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <span class="badge {{ $announcement->type == 'urgent' ? 'bg-label-danger' : 'bg-label-primary' }} text-uppercase">
                  {{ $announcement->type }}
                </span>
                <div class="dropdown">
                  <button class="btn p-0" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i> Edit</a>
                    <a class="dropdown-item text-danger" href="javascript:void(0);"><i class="ti ti-trash me-1"></i> Delete</a>
                  </div>
                </div>
              </div>
              <h5 class="card-title fw-bold mb-2">{{ $announcement->title }}</h5>
              <p class="card-text text-muted mb-4 announcement-content">{{ Str::limit($announcement->content, 150) }}</p>
              
              <div class="d-flex align-items-center justify-content-between mt-auto pt-3 border-top">
                <div class="d-flex align-items-center">
                  <div class="avatar avatar-xs me-2">
                    <img src="{{ $announcement->createdBy->getProfilePicture() }}" alt="Avatar" class="rounded-circle">
                  </div>
                  <small class="text-muted">{{ $announcement->createdBy->first_name }}</small>
                </div>
                <small class="text-muted">{{ $announcement->start_date->format('M d, Y') }}</small>
              </div>
            </div>
          </div>
        </div>
      @empty
        <div class="col-12">
          <div class="card hitech-card-white border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="ti ti-bell-off fs-1 text-muted mb-3"></i>
            <h5 class="text-muted">No announcements found</h5>
            <p class="text-muted mb-0">Be the first to share something with the team!</p>
          </div>
        </div>
      @endforelse
    </div>
    
    <div class="d-flex justify-content-center mt-4">
        {{ $announcements->links() }}
    </div>
  </div>
</div>

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title">Create Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.announcements.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" placeholder="Annual General Meeting" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Type</label>
              <select name="type" class="form-select" required>
                <option value="general">General</option>
                <option value="urgent">Urgent</option>
                <option value="policy">Policy Update</option>
                <option value="holiday">Holiday Notice</option>
                <option value="event">Company Event</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Priority</label>
              <select name="priority" class="form-select" required>
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">End Date (Optional)</label>
              <input type="date" name="end_date" class="form-control">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Content</label>
            <textarea name="content" class="form-control" rows="5" placeholder="Share the details here..." required></textarea>
          </div>
          <div class="mb-0">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
              <label class="form-check-label">Keep this announcement active</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Post Announcement</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
