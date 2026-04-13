@extends('layouts/layoutMaster')

@section('title', 'Evaluation Submitted')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 text-center">
            <div class="card p-5 shadow-lg border-0 rounded-4">
                <div class="mb-4">
                    <div class="avatar avatar-xl bg-label-success mx-auto rounded-circle d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="bx bx-check-circle fs-1" style="font-size: 4rem !important;"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-2">Evaluation Submitted Successfully!</h3>
                <p class="text-muted mb-5">
                    Thank you for evaluating <strong>{{ $employee->name }}</strong>. 
                    Your feedback has been sent to the HR department for final approval.
                </p>
                <div class="d-grid">
                    <a href="{{ route('tenant.dashboard') }}" class="btn btn-primary rounded-pill py-3 fw-bold">Return to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
