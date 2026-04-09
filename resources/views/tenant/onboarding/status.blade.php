@extends('layouts/layoutMaster')

@section('title', 'Onboarding Status')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y d-flex align-items-center justify-content-center" style="min-height: 70vh;">
  <div class="col-md-8 col-lg-6">
    <div class="hitech-card-white p-10 text-center animate__animated animate__zoomIn">
      <div class="mb-6">
        <div class="hitech-status-icon-wrapper mx-auto bg-label-warning" style="width: 100px; height: 100px;">
          <i class="bx bx-time-five fs-1 text-warning"></i>
        </div>
      </div>
      <h2 class="fw-bold text-heading">Submission Received!</h2>
      <p class="text-muted fs-5 mb-8">
        Hello <strong>{{ $user->first_name }}</strong>, your onboarding application has been submitted successfully and is currently <strong>Under Review</strong> by our HR department.
      </p>
      
      <div class="alert hitech-note-card text-start mb-8">
        <h6 class="alert-heading fw-bold d-flex align-items-center gap-2">
          <i class="bx bx-info-circle"></i> What happens next?
        </h6>
        <ul class="small mb-0 mt-3">
          <li class="mb-2">HR will verify your documents and bank details.</li>
          <li class="mb-2">This process usually takes 24-48 hours.</li>
          <li>You will receive an email once your application is approved or if any resubmission is required.</li>
        </ul>
      </div>

      <div class="d-flex flex-column gap-3">
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl">
          <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Current Protocol</p>
          <p class="text-xs font-bold text-slate-900">Awaiting Human Resources Validation</p>
        </div>
        <form action="{{ route('auth.logout') }}" method="POST">
          @csrf
          <button type="submit" class="btn btn-label-secondary w-100 py-3">
            <i class="bx bx-log-out me-2"></i> Logout
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.hitech-status-icon-wrapper {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
@endsection
