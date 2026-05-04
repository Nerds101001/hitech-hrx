@php 
    $file = $files->first();
    $tds = $files->firstWhere('category', 'TDS');
    $sds = $files->firstWhere('category', 'SDS');
    $comp = $files->firstWhere('category', 'COMP');
@endphp

<div class="list-view-item product-item">
    <div class="list-item-main">
        <div class="product-icon-box sm shadow-none bg-light border-0" style="width: 36px; height: 36px;">
            <img src="{{ asset('assets/img/pdf icon.png') }}" width="18" alt="PDF">
        </div>
        <div class="list-item-info">
            <div class="list-item-title">{{ $productName }}</div>
            <div class="list-item-meta">{{ $brand }} · {{ $category }}</div>
        </div>
    </div>
    <div class="list-item-actions">
        <a href="{{ $tds ? route('library.access', $tds->id) : 'javascript:void(0)' }}" target="{{ $tds ? '_blank' : '' }}" class="action-pill {{ !$tds ? 'disabled' : '' }}">TDS</a>
        <a href="{{ $sds ? route('library.access', $sds->id) : 'javascript:void(0)' }}" target="{{ $sds ? '_blank' : '' }}" class="action-pill {{ !$sds ? 'disabled' : '' }}">SDS</a>
        <a href="{{ $comp ? route('library.access', $comp->id) : 'javascript:void(0)' }}" target="{{ $comp ? '_blank' : '' }}" class="action-pill {{ !$comp ? 'disabled' : '' }}">COMP</a>
        <a href="{{ route('library.access', $file->id) }}" target="_blank" class="btn btn-icon btn-label-primary rounded-circle ms-3" style="width: 32px; height: 32px;"><i class="ti ti-external-link"></i></a>
    </div>
</div>
