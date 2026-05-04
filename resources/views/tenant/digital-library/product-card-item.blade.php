@php 
    $file = $files->first();
    $tds = $files->firstWhere('category', 'TDS');
    $sds = $files->firstWhere('category', 'SDS');
    $comp = $files->firstWhere('category', 'COMP') ?? $files->firstWhere('category', 'MOM');
    $totalSize = 0;
    foreach($files as $f) $totalSize += $f->size;
    $sizeFormatted = number_format($totalSize / 1024 / 1024, 2) . ' MB';
@endphp

<div class="col-md-6 col-xl-4 product-item">
    <div class="product-card" draggable="true" ondragstart="handleDragStart(event, '{{ addslashes($productName) }}', '{{ $brand }}', '{{ $category }}')">
        <div class="card-top-badge">{{ $file->category }}</div>
        <div class="card-main-content">
            <div class="product-header">
                <div class="product-icon-box">
                    <img src="{{ asset('assets/img/pdf icon.png') }}" width="22" alt="PDF">
                </div>
                <div class="product-title-area overflow-hidden">
                    <div class="product-title text-truncate" title="{{ $productName }}">{{ $productName }}</div>
                    <div class="product-subtitle">{{ $brand }} · {{ $category }}</div>
                </div>
            </div>
            <div class="card-summary">{{ trim(str_replace('**', '', $file->summary ?? 'AI technical overview for ' . $productName)) }}</div>
            <div class="action-row">
                <a href="{{ $tds ? route('library.access', $tds->id) : 'javascript:void(0)' }}" target="{{ $tds ? '_blank' : '' }}" class="action-btn {{ !$tds ? 'disabled' : '' }}">
                    <span>TDS</span>
                    <span>{{ $tds ? 'View PDF' : 'N/A' }}</span>
                </a>
                <a href="{{ $sds ? route('library.access', $sds->id) : 'javascript:void(0)' }}" target="{{ $sds ? '_blank' : '' }}" class="action-btn {{ !$sds ? 'disabled' : '' }}">
                    <span>SDS</span>
                    <span>{{ $sds ? 'View PDF' : 'N/A' }}</span>
                </a>
                <a href="{{ $comp ? route('library.access', $comp->id) : 'javascript:void(0)' }}" target="{{ $comp ? '_blank' : '' }}" class="action-btn {{ !$comp ? 'disabled' : '' }}">
                    <span>COMP</span>
                    <span>{{ $comp ? 'Access' : 'N/A' }}</span>
                </a>
            </div>
        </div>
        <div class="card-footer">
            <div class="file-size">{{ $sizeFormatted }}</div>
            <div class="d-flex align-items-center gap-3">
                <a href="javascript:void(0)" onclick="openMoveModal('{{ addslashes($productName) }}', '{{ $brand }}', '{{ $category }}')" class="text-muted" title="Manage Archive">
                    <i class="ti ti-settings fs-5"></i>
                </a>
                <a href="{{ route('library.access', $file->id) }}" target="_blank" class="view-details">View Details <i class="ti ti-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>
