@php 
    $file = $files->first();
    $tds = $files->firstWhere('category', 'TDS');
    $sds = $files->firstWhere('category', 'SDS');
    $comp = $files->firstWhere('category', 'COMP');
    $testReport = $files->firstWhere('category', 'Test Report');
@endphp

<div class="list-view-item product-item">
    <div class="list-item-main">
        <div class="product-icon-box sm shadow-none bg-light border-0" style="width: 36px; height: 36px;">
            <img src="{{ asset('assets/img/pdf icon.png') }}" width="18" alt="PDF">
        </div>
        <div class="list-item-info">
            <div class="list-item-title">{{ $productName }}</div>
            <div class="list-item-meta">
                {{ $brand }} · {{ $category }}
                @if($file->category === 'LEARN' && $file->presenter_id)
                    <span class="ms-2 border-start ps-2">
                        <i class="ti ti-user text-primary"></i> {{ $file->presenter->first_name ?? 'Unknown' }}
                        @if($file->session_date)
                            <i class="ti ti-calendar ms-1 text-primary"></i> {{ \Carbon\Carbon::parse($file->session_date)->format('M d') }}
                        @endif
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div class="list-item-actions">
        @if($file->category === 'Video' || $file->category === 'LEARN')
            @if($file->youtube_url)
                @php
                    $pName = $file->presenter ? trim($file->presenter->first_name . ' ' . $file->presenter->last_name) : '';
                    $sDate = $file->session_date ? \Carbon\Carbon::parse($file->session_date)->format('M d, Y') : '';
                @endphp
                <a href="javascript:void(0)" onclick="playVideo('{{ $file->youtube_url }}', '{{ addslashes($productName) }}', '{{ addslashes($pName) }}', '{{ addslashes($sDate) }}')" class="action-pill bg-danger text-white border-0"><i class="ti ti-player-play"></i> PLAY</a>
            @endif
        @else
            <a href="{{ $tds ? route('library.access', $tds->id) : 'javascript:void(0)' }}" target="{{ $tds ? '_blank' : '' }}" class="action-pill {{ !$tds ? 'disabled' : '' }}">TDS</a>
            <a href="{{ $sds ? route('library.access', $sds->id) : 'javascript:void(0)' }}" target="{{ $sds ? '_blank' : '' }}" class="action-pill {{ !$sds ? 'disabled' : '' }}">SDS</a>
            <a href="{{ $comp ? route('library.access', $comp->id) : 'javascript:void(0)' }}" target="{{ $comp ? '_blank' : '' }}" class="action-pill {{ !$comp ? 'disabled' : '' }}">COMP</a>
            <a href="{{ $testReport ? route('library.access', $testReport->id) : 'javascript:void(0)' }}" target="{{ $testReport ? '_blank' : '' }}" class="action-pill {{ !$testReport ? 'disabled' : '' }}">TEST</a>
        @endif
        @if(auth()->user()->hasRole(['admin', 'Admin', 'super_admin']))
        <a href="javascript:void(0)" onclick="deleteDocument('{{ addslashes($productName) }}')" class="action-pill bg-danger text-white ms-3 border-0" title="Delete Document">
            <i class="ti ti-trash"></i> DEL
        </a>
        @endif
        <a href="{{ route('library.access', $file->id) }}" target="_blank" class="action-pill bg-primary text-white ms-1 border-0" title="View Details">
            <i class="ti ti-external-link"></i> OPEN
        </a>
    </div>
</div>
