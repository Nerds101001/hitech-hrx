@extends('layouts/layoutMaster')

@section('title', __('Workplace Celebrations'))

@section('vendor-style')
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap">
@vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
<style>
    :root {
        --celeb-teal: #004D4D;
        --celeb-teal-light: #e6f0f0;
    }
    .celebration-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 2rem;
        margin-top: 2rem;
    }
    .celeb-card {
        background: #ffffff;
        border: 1px solid #eef2f7;
        border-radius: 20px;
        position: relative;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .celeb-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 77, 77, 0.1);
        border-color: var(--celeb-teal);
    }
    
    .celeb-header-accent {
        height: 80px;
        width: 100%;
        position: absolute;
        top: 0;
        left: 0;
        opacity: 0.05;
        z-index: 0;
    }
    .is-birthday .celeb-header-accent { background: linear-gradient(to bottom, #ff4d4f, transparent); }
    .is-anniversary .celeb-header-accent { background: linear-gradient(to bottom, #1890ff, transparent); }

    .celeb-body {
        padding: 2.5rem 1.5rem 1.5rem;
        position: relative;
        z-index: 1;
        text-align: center;
    }
    .avatar-container {
        position: relative;
        display: inline-block;
        margin-bottom: 1.5rem;
    }
    .avatar-main {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #ffffff;
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }
    .celeb-name {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 0.25rem;
    }
    .celeb-role {
        font-size: 0.875rem;
        color: #718096;
        margin-bottom: 1.5rem;
    }
    .celeb-date-pill {
        background: var(--celeb-teal);
        color: #ffffff;
        padding: 0.6rem 1.2rem;
        border-radius: 100px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .is-anniversary .celeb-date-pill { background: #1890ff; }

    .today-marker {
        position: absolute;
        top: 15px;
        right: 15px;
        background: #ff4d4f;
        color: white;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        box-shadow: 0 4px 10px rgba(255, 77, 79, 0.3);
    }

    .action-row {
        margin-top: 1.5rem;
        display: flex;
        gap: 0.75rem;
        justify-content: center;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.3s ease;
    }
    .celeb-card:hover .action-row {
        opacity: 1;
        transform: translateY(0);
    }
    .btn-celeb {
        border-radius: 12px;
        padding: 8px 16px;
        font-size: 0.8rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .festive-icon {
        position: absolute;
        bottom: -10px;
        right: -10px;
        font-size: 4rem;
        opacity: 0.03;
        transform: rotate(-15deg);
    }
</style>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const festiveColors = ['#004D4D', '#ffc107', '#17a2b8', '#e83e8c', '#6f42c1', '#fd7e14'];
        
        // Diagonal burst from bottom-left corner
        confetti({
            particleCount: 200,
            angle: 45,
            spread: 70,
            origin: { x: 0, y: 1 },
            colors: festiveColors,
            zIndex: 9999,
            gravity: 1.2
        });
        
        // Diagonal burst from bottom-right corner
        confetti({
            particleCount: 200,
            angle: 135,
            spread: 70,
            origin: { x: 1, y: 1 },
            colors: festiveColors,
            zIndex: 9999,
            gravity: 1.2
        });
    });

    function fireCardConfetti(event) {
        const rect = event.target.closest('.celeb-card').getBoundingClientRect();
        const festiveColors = ['#004D4D', '#ffc107', '#17a2b8', '#e83e8c'];
        
        // Burst from both card corners
        confetti({
            particleCount: 30,
            angle: 60,
            spread: 55,
            origin: { x: rect.left / window.innerWidth, y: rect.bottom / window.innerHeight },
            colors: festiveColors,
            zIndex: 9999
        });
        confetti({
            particleCount: 30,
            angle: 120,
            spread: 55,
            origin: { x: rect.right / window.innerWidth, y: rect.bottom / window.innerHeight },
            colors: festiveColors,
            zIndex: 9999
        });
    }
</script>
@endsection

@section('content')
<div class="content-wrapper">
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-hero-banner 
            title="Team Celebrations"
            subtitle="Honoring the moments that make our team special"
            icon="bx-party"
            gradient="teal"
        />

        {{-- BIRTHDAYS --}}
        <div class="mt-5 pt-3">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h3 class="mb-0 fw-bold d-flex align-items-center">
                    <span class="avatar avatar-sm bg-label-primary me-2"><i class="bx bx-cake"></i></span>
                    Upcoming Birthdays
                </h3>
                <span class="badge bg-label-primary rounded-pill">{{ $birthdays->count() }} This Month</span>
            </div>
            
            <div class="row g-4">
                @forelse($birthdays as $u)
                    @php
                        $isToday = Carbon\Carbon::parse($u->dob)->format('md') === now()->format('md');
                    @endphp
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="celeb-card animate__animated animate__fadeInUp" onmouseenter="fireCardConfetti(event)">
                            <div class="celeb-body">
                                <div class="avatar-container">
                                    <img src="{{ $u->getProfilePicture() ?: 'https://ui-avatars.com/api/?name='.urlencode($u->name).'&background=f1f5f9&color=004D4D&bold=true&size=200' }}" 
                                         class="avatar-main" alt="{{ $u->name }}">
                                </div>
                                
                                <div class="celeb-name text-dark">{{ $u->name }}</div>
                                <div class="celeb-role text-muted">{{ $u->designation->name ?? 'Team Member' }}</div>
                                
                                <div class="celeb-date-pill">
                                    <i class="bx bx-calendar-check"></i>
                                    {{ Carbon\Carbon::parse($u->dob)->format('M d') }}
                                </div>

                                @if($isToday)
                                    <div class="action-row">
                                        <a href="mailto:{{ $u->email }}?subject=Happy Birthday!" class="btn btn-celeb btn-primary">
                                            <i class="bx bx-envelope"></i> Email
                                        </a>
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $u->phone) }}?text=Happy%20Birthday%20{{ urlencode($u->name) }}!" 
                                           target="_blank" class="btn btn-celeb btn-success">
                                            <i class="bx bxl-whatsapp"></i> Wish
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><div class="card p-5 text-center text-muted">No birthdays recorded for the next 30 days.</div></div>
                @endforelse
            </div>
        </div>

        {{-- ANNIVERSARIES --}}
        <div class="mt-5 pt-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h3 class="mb-0 fw-bold d-flex align-items-center">
                    <span class="avatar avatar-sm bg-label-primary me-2"><i class="bx bx-medal"></i></span>
                    Work Anniversaries
                </h3>
            </div>

            <div class="row g-4">
                @forelse($anniversaries as $u)
                    @php
                        $isToday = Carbon\Carbon::parse($u->date_of_joining)->format('md') === now()->format('md');
                        $milestone = (int)Carbon\Carbon::parse($u->date_of_joining)->diffInYears(now()) + 1;
                    @endphp
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="celeb-card animate__animated animate__fadeInUp" onmouseenter="fireCardConfetti(event)">
                            <div class="celeb-body">
                                <div class="avatar-container">
                                    <img src="{{ $u->getProfilePicture() ?: 'https://ui-avatars.com/api/?name='.urlencode($u->name).'&background=f0f7ff&color=004D4D&bold=true&size=200' }}" 
                                         class="avatar-main" alt="{{ $u->name }}">
                                </div>
                                
                                <div class="celeb-name text-dark">{{ $u->name }}</div>
                                <div class="celeb-role text-muted">{{ $milestone }} Year Milestone</div>
                                
                                <div class="celeb-date-pill">
                                    <i class="bx bx-award"></i>
                                    {{ Carbon\Carbon::parse($u->date_of_joining)->format('M d') }}
                                </div>

                                @if($isToday)
                                    <div class="action-row">
                                        <a href="mailto:{{ $u->email }}?subject=Happy Work Anniversary!" class="btn btn-celeb btn-primary">
                                            <i class="bx bx-envelope"></i> Email
                                        </a>
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $u->phone) }}?text=Congratulations%20on%20your%20Work%20Anniversary%20{{ urlencode($u->name) }}!" 
                                           target="_blank" class="btn btn-celeb btn-success">
                                            <i class="bx bxl-whatsapp"></i> Wish
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><div class="card p-5 text-center text-muted">No work anniversaries recorded for the next 30 days.</div></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
