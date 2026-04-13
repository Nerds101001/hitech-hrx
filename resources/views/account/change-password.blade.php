@extends('layouts.layoutMaster')

@section('title', 'Change Password')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/libs/animate-css/animate.scss',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
        'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

@section('page-style')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    body {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
    }

    .change-password-page {
        background: radial-gradient(circle at 10% 20%, rgba(0, 77, 77, 0.05) 0%, rgba(255, 255, 255, 0) 100%);
        min-height: calc(100vh - 200px);
    }

    .change-password-card {
        border-radius: 28px;
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 20px 50px rgba(0, 77, 77, 0.1);
        overflow: hidden;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }

    .card-header-premium {
        background: linear-gradient(135deg, #004d4d 0%, #007a7a 60%, #00a3a3 100%);
        padding: 3rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .card-header-premium::before {
        content: '';
        position: absolute;
        width: 150px;
        height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        top: -50px;
        right: -30px;
    }

    .card-header-premium i.header-icon {
        font-size: 3.5rem;
        color: white;
        margin-bottom: 1.5rem;
        display: inline-block;
        background: rgba(255, 255, 255, 0.1);
        width: 80px;
        height: 80px;
        line-height: 80px;
        border-radius: 20px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .card-header-premium h4 {
        color: white;
        margin-bottom: 0.5rem;
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    .card-header-premium p {
        color: rgba(255, 255, 255, 0.82);
        margin-bottom: 0;
        font-size: 0.95rem;
        font-weight: 500;
    }

    .form-label-premium {
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.6rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .input-group-premium {
        border-radius: 16px !important;
        overflow: hidden;
        border: 2px solid #f1f5f9 !important;
        transition: all 0.3s ease;
        background: #f8fafc;
        display: flex;
        align-items: center;
    }

    .input-group-premium:focus-within {
        border-color: #007a7a !important;
        box-shadow: 0 0 0 4px rgba(0, 122, 122, 0.1) !important;
        background: white;
    }

    .input-group-premium .input-group-text {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding-left: 1.25rem;
        color: #64748b;
    }

    .input-group-premium .form-control {
        border: none !important;
        box-shadow: none !important;
        padding: 0.85rem 1rem !important;
        background: transparent !important;
        font-weight: 500;
        border-radius: 0 !important;
        outline: none !important;
    }

    .password-toggle {
        cursor: pointer;
        padding-right: 1.25rem !important;
        color: #94a3b8;
        transition: color 0.2s ease;
    }

    .password-toggle:hover {
        color: #007a7a;
    }

    .btn-premium {
        background: linear-gradient(135deg, #007a7a 0%, #004d4d 100%);
        border: none;
        color: white;
        font-weight: 700;
        padding: 1rem 2rem;
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 10px 20px rgba(0, 77, 77, 0.2);
    }

    .btn-premium:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(0, 77, 77, 0.3);
        color: white;
        filter: brightness(1.1);
    }

    .btn-premium:active {
        transform: translateY(-1px);
    }

    .requirement-box {
        background: #f1f5f9;
        padding: 1rem;
        border-radius: 14px;
        margin-top: 1rem;
    }

    .password-requirement {
        font-size: 0.8rem;
        color: #64748b;
        display: flex;
        align-items: center;
        margin-bottom: 0.35rem;
        font-weight: 500;
    }

    .password-requirement:last-child {
        margin-bottom: 0;
    }

    .password-requirement i {
        font-size: 1.1rem;
        margin-right: 0.6rem;
    }

    .requirement-met {
        color: #059669;
    }

    .requirement-met i {
        color: #10b981;
    }

    .card-footer-premium {
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        padding: 1.5rem;
    }
</style>
@endsection

@section('vendor-script')
    @vite([
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y change-password-page">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card change-password-card animate__animated animate__zoomIn">
                <div class="card-header-premium">
                    <i class="bx bx-shield-alt-2 header-icon"></i>
                    <h4>Security Settings</h4>
                    <p>Protect your account with a strong password</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    @if(session('success'))
                        <div class="alert alert-success d-flex align-items-center rounded-3 mb-4" role="alert">
                            <i class="bx bx-check-double fs-4 me-2"></i>
                            <div class="fw-semibold">{{ session('success') }}</div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger d-flex align-items-center rounded-3 mb-4" role="alert">
                            <i class="bx bx-error-circle fs-4 me-2"></i>
                            <div class="fw-semibold">{{ session('error') }}</div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger rounded-3 mb-4">
                            <ul class="mb-0 small fw-medium">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('account.change-password.post') }}" method="POST" id="passwordForm">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label-premium" for="oldPassword">Current Password</label>
                            <div class="input-group input-group-premium">
                                <span class="input-group-text"><i class="bx bx-lock-open-alt"></i></span>
                                <input type="password" name="oldPassword" class="form-control" id="oldPassword" placeholder="Your current password" required />
                                <span class="input-group-text password-toggle" onclick="togglePassword('oldPassword', this)">
                                    <i class="bx bx-hide"></i>
                                </span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-premium" for="newPassword">New Password</label>
                            <div class="input-group input-group-premium">
                                <span class="input-group-text"><i class="bx bx-key"></i></span>
                                <input type="password" name="newPassword" class="form-control" id="newPassword" placeholder="Create new password" required />
                                <span class="input-group-text password-toggle" onclick="togglePassword('newPassword', this)">
                                    <i class="bx bx-hide"></i>
                                </span>
                            </div>
                            <div class="requirement-box">
                                <div class="password-requirement" id="req-length">
                                    <i class="bx bx-check-circle"></i> Minimum 8 characters
                                </div>
                                <div class="password-requirement" id="req-complex">
                                    <i class="bx bx-check-circle"></i> Letters, numbers & symbols
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-premium" for="confirmPassword">Confirm New Password</label>
                            <div class="input-group input-group-premium">
                                <span class="input-group-text"><i class="bx bx-check-shield"></i></span>
                                <input type="password" name="confirmPassword" class="form-control" id="confirmPassword" placeholder="Repeat new password" required />
                                <span class="input-group-text password-toggle" onclick="togglePassword('confirmPassword', this)">
                                    <i class="bx bx-hide"></i>
                                </span>
                            </div>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-premium btn-lg">
                                <i class="bx bx-lock-alt me-2"></i> Update Security Credentials
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer-premium text-center">
                    <p class="mb-0 text-muted small fw-medium">
                        Forgotten your password? 
                        <a href="mailto:csenerds@gmail.com" class="text-primary fw-bold text-decoration-none">
                            <i class="bx bx-support me-1"></i>Contact Admin
                        </a>
                    </p>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="{{ auth()->user()->hasRole(['admin', 'hr', 'manager']) ? url('/') : (auth()->user()->hasRole('employee') ? route('user.dashboard.index') : url('/')) }}" class="btn btn-link text-muted fw-bold text-decoration-none">
                    <i class="bx bx-arrow-back me-1"></i> @lang('Back to Dashboard')
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword(id, el) {
        const input = document.getElementById(id);
        const icon = el.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bx-hide', 'bx-show');
        } else {
            input.type = 'password';
            icon.classList.replace('bx-show', 'bx-hide');
        }
    }

    // Dynamic requirement checking
    document.getElementById('newPassword').addEventListener('input', function() {
        const val = this.value;
        const reqLength = document.getElementById('req-length');
        const reqComplex = document.getElementById('req-complex');
        
        // Length check
        if (val.length >= 8) {
            reqLength.classList.add('requirement-met');
            reqLength.querySelector('i').classList.replace('bx-check-circle', 'bx-check-double');
        } else {
            reqLength.classList.remove('requirement-met');
            reqLength.querySelector('i').classList.replace('bx-check-double', 'bx-check-circle');
        }
        
        // Complexity check (simplified)
        const hasLetter = /[a-zA-Z]/.test(val);
        const hasNumber = /[0-9]/.test(val);
        const hasSymbol = /[^a-zA-Z0-9]/.test(val);
        
        if (hasLetter && hasNumber && hasSymbol) {
            reqComplex.classList.add('requirement-met');
            reqComplex.querySelector('i').classList.replace('bx-check-circle', 'bx-check-double');
        } else {
            reqComplex.classList.remove('requirement-met');
            reqComplex.querySelector('i').classList.replace('bx-check-double', 'bx-check-circle');
        }
    });
</script>
@endsection
