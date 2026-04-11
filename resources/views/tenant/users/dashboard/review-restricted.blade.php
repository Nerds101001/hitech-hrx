@extends('layouts/layoutMaster')

@section('title', 'Profile Under Review - Hitech HRX')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
@endsection

@section('page-style')
<style>
    .restricted-wrapper {
        min-height: 70vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    .restricted-card {
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(6, 237, 249, 0.2);
        border-radius: 24px;
        padding: 3rem;
        max-width: 600px;
        width: 100%;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .status-icon-wrap {
        width: 100px;
        height: 100px;
        background: rgba(6, 237, 249, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 2rem;
        position: relative;
    }
    .status-icon-wrap i {
        font-size: 3.5rem;
        color: #06edf9;
    }
    .pulse-ring {
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        border: 2px solid #06edf9;
        animation: pulse-ring 2s infinite;
    }
    @keyframes pulse-ring {
        0% { transform: scale(0.8); opacity: 0.5; }
        100% { transform: scale(1.4); opacity: 0; }
    }
    .restricted-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: #fff;
        font-weight: 800;
        font-size: 2rem;
        margin-bottom: 1rem;
        letter-spacing: -0.02em;
    }
    .restricted-subtitle {
        color: #94a3b8;
        font-size: 1.125rem;
        line-height: 1.6;
        margin-bottom: 2.5rem;
    }
    .action-steps {
        text-align: left;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2.5rem;
    }
    .step-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .step-item:last-child { margin-bottom: 0; }
    .step-icon {
        background: rgba(6, 237, 249, 0.2);
        color: #06edf9;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.875rem;
    }
    .step-text {
        color: #cbd5e1;
        font-size: 0.9375rem;
    }
    .btn-hitech-support {
        background: linear-gradient(135deg, #06edf9 0%, #04b3bd 100%);
        color: #0f172a;
        font-weight: 700;
        padding: 1rem 2rem;
        border-radius: 12px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
        border: none;
    }
    .btn-hitech-support:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(6, 237, 249, 0.4);
        color: #0f172a;
    }
</style>
@endsection

@section('content')
<div class="restricted-wrapper">
    <div class="restricted-card animate__animated animate__zoomIn">
        <div class="status-icon-wrap">
            <div class="pulse-ring"></div>
            <i class="bx bx-file-find"></i>
        </div>
        
        <h1 class="restricted-title">Your profile is under review</h1>
        <p class="restricted-subtitle">Great job completing your onboarding! Our HR team is currently verifying your documents and information. This typically takes 24-48 business hours.</p>

        <div class="action-steps">
            <div class="step-item">
                <div class="step-icon"><i class="bx bx-check"></i></div>
                <div class="step-text">Onboarding forms successfully submitted.</div>
            </div>
            <div class="step-item">
                <div class="step-icon"><i class="bx bx-loader-circle bx-spin"></i></div>
                <div class="step-text">Verification in progress by HR administrator.</div>
            </div>
            <div class="step-item">
                <div class="step-icon"><i class="bx bx-lock"></i></div>
                <div class="step-text">Full portal access will be granted once approved.</div>
            </div>
        </div>

        <div class="d-flex flex-column gap-3">
            <a href="mailto:hr@hitechgroup.in" class="btn-hitech-support">
                <i class="bx bx-envelope"></i> Send a mail to HR
            </a>
            <form action="{{ route('auth.logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-link text-muted" style="text-decoration: none;">
                    <i class="bx bx-log-out"></i> Log Out
                </button>
            </form>
        </div>

        <div class="mt-5" style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem;">
            <div style="color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700;">
                Protocol: SYSTEM_PENDING_REVIEW
            </div>
        </div>
    </div>
</div>
@endsection
