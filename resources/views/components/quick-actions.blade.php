@props([
    'title' => 'Quick Actions'
])

<div class="hitech-card">
    <div class="hitech-card-header">
        <h5 class="title mb-0">{{ $title }}</h5>
    </div>
    <div class="card-body p-sm-5 p-4">
        <div class="row g-3">
            {{ $slot }}
        </div>
    </div>
</div>
