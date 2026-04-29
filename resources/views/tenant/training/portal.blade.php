@extends('layouts.layoutMaster')

@section('title', 'Training Portal - The Journey')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
@endsection

@section('page-style')
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #005f6b 0%, #008c99 100%);
        --glass-bg: rgba(255, 255, 255, 0.1);
        --glass-border: rgba(255, 255, 255, 0.2);
    }

    .training-header {
        background: var(--primary-gradient);
        border-radius: 15px;
        padding: 40px;
        color: white;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(118, 75, 162, 0.3);
    }

    .training-header::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .journey-map {
        position: relative;
        padding: 40px 0;
    }

    .phase-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        margin-bottom: 30px;
        overflow: hidden;
    }

    .phase-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    }

    .phase-badge {
        background: #e0f2f1;
        color: #00796b;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        display: inline-block;
        margin-bottom: 10px;
    }

    .module-item {
        display: flex;
        align-items: center;
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 10px;
        background: #fafbfc;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }

    .module-item:hover {
        background: #f0f4ff;
        transform: scale(1.02);
    }

    .module-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        background: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    .status-badge {
        margin-left: auto;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-completed { background: #e6fffa; color: #2d3748; }
    .status-in_progress { background: #fffaf0; color: #744210; }
    .status-locked { opacity: 0.5; filter: grayscale(1); cursor: not-allowed; }

    .progress-track {
        height: 8px;
        background: #edf2f7;
        border-radius: 10px;
        margin-top: 10px;
    }

    .progress-bar-fill {
        height: 100%;
        background: var(--primary-gradient);
        border-radius: 10px;
        transition: width 0.5s ease;
    }

    .confetti-canvas {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 9999;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="training-header animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="text-white fw-bold mb-2">Welcome to Your Journey, {{ $user->first_name }}! 🚀</h2>
                <p class="text-white opacity-75 mb-0">Complete your training phases to unlock your full potential and become an active member of our team.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-inline-block text-center">
                    <h3 class="text-white mb-0">{{ $progressPercentage }}%</h3>
                    <small class="text-white opacity-75">Overall Completion</small>
                </div>
            </div>
        </div>
        <div class="progress-track mt-4" style="background: rgba(255,255,255,0.2);">
            <div class="progress-bar-fill" style="width: {{ $progressPercentage }}%; background: white;"></div>
        </div>
    </div>

    <div class="journey-map">
        <div class="row">
            @foreach($phases as $phase)
            <div class="col-12 col-lg-4">
                <div class="phase-card animate__animated animate__fadeInUp" style="animation-delay: {{ $loop->index * 0.1 }}s">
                    <div class="card-body">
                        <span class="phase-badge">Phase {{ $phase->order }}</span>
                        <h4 class="fw-bold mb-3">{{ $phase->title }}</h4>
                        <p class="text-muted small mb-4">{{ $phase->description }}</p>

                        <div class="module-list">
                            @foreach($phase->modules as $module)
                                @php
                                    $progress = $module->userProgress->first();
                                    $status = $progress ? $progress->status : 'locked';
                                    $isLocked = ($status === 'locked');
                                @endphp
                                
                                <a href="{{ $isLocked ? 'javascript:void(0)' : route('training.module.show', $module->id) }}" 
                                   class="module-item {{ $isLocked ? 'status-locked' : '' }}">
                                    <div class="module-icon">
                                        @if($module->content_type === 'video')
                                            <i class="ti ti-player-play text-danger"></i>
                                        @elseif($module->content_type === 'policy')
                                            <i class="ti ti-file-text text-primary"></i>
                                        @else
                                            <i class="ti ti-book text-success"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-bold small">{{ $module->title }}</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">
                                            <i class="ti ti-clock"></i> {{ $module->estimated_time_minutes }} mins
                                        </div>
                                    </div>
                                    @if($status === 'completed')
                                        <span class="status-badge status-completed">
                                            <i class="ti ti-check"></i>
                                        </span>
                                    @elseif($status === 'in_progress')
                                        <span class="status-badge status-in_progress">
                                            <i class="ti ti-rotate"></i>
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
