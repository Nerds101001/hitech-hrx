@props([
    'id' => null,
    'title' => 'Stat Title',
    'value' => '0',
    'icon' => 'bx-chart',
    'color' => 'primary',
    'trend' => null,
    'trendValue' => null,
    'trendLabel' => 'vs last month',
    'link' => null,
    'animationDelay' => '0.1s'
])

@php
    $colors = [
        'primary' => ['bg' => 'primary', 'icon' => 'primary', 'text' => 'primary'],
        'success' => ['bg' => 'teal', 'icon' => 'teal', 'text' => 'teal'],
        'info' => ['bg' => 'blue', 'icon' => 'blue', 'text' => 'blue'],
        'warning' => ['bg' => 'amber', 'icon' => 'amber', 'text' => 'amber'],
        'danger' => ['bg' => 'red', 'icon' => 'red', 'text' => 'red'],
        'teal' => ['bg' => 'teal', 'icon' => 'teal', 'text' => 'teal'],
        'blue' => ['bg' => 'blue', 'icon' => 'blue', 'text' => 'blue'],
        'amber' => ['bg' => 'amber', 'icon' => 'amber', 'text' => 'amber'],
        'red' => ['bg' => 'red', 'icon' => 'red', 'text' => 'red']
    ];
    
    $colorConfig = $colors[$color] ?? $colors['primary'];
    $iconClass = 'icon-' . $colorConfig['icon'];
@endphp

<div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: {{ $animationDelay }}">
    <div class="hitech-stat-card dashboard-variant h-100 {{ $link ? 'cursor-pointer' : '' }}" {{ $link ? 'onclick="window.location.href=\'' . $link . '\'"' : '' }}>
        <div class="stat-card-header mb-3">
            <div class="stat-icon-wrap {{ $iconClass }} mb-0">
                <i class="bx {{ $icon }}"></i>
            </div>
            @if($link)
                <div class="stat-card-link-arrow">
                    <i class="bx bx-right-arrow-alt fs-4"></i>
                </div>
            @endif
        </div>
        
        <div class="stat-card-body">
            <div class="stat-card-label text-uppercase mb-1" {{ $id ? 'id='.$id.'-title' : '' }} style="font-size: 0.72rem; font-weight: 700; color: #94a3b8; letter-spacing: 0.05em;">
                {{ $title }}
            </div>
            <h3 class="stat-card-value mb-1" {{ $id ? 'id='.$id : '' }} style="font-size: 1.8rem; font-weight: 800; color: #1e293b; line-height: 1;">
                {{ $value }}
            </h3>
            
            @if($trendValue)
                <div class="stat-card-trend d-flex align-items-center gap-1" style="font-size: 0.75rem; font-weight: 600;">
                    <span class="{{ $trend === 'up' ? 'text-success' : ($trend === 'down' ? 'text-danger' : 'text-muted') }}">
                        @if($trend === 'up') + @endif{{ $trendValue }}
                    </span>
                    <span class="text-muted fw-normal">{{ $trendLabel }}</span>
                </div>
            @else
                <div style="height: 18px;"></div> {{-- Spacer --}}
            @endif
        </div>
    </div>
</div>

