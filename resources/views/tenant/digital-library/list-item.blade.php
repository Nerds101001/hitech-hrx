@php
    $file = $files->first();
    $tds = $files->firstWhere('category', 'TDS');
    $sds = $files->firstWhere('category', 'SDS');
    $pres = $files->firstWhere('category', 'Presentation') ?? $files->firstWhere('category', 'COMP');
    $testReport = $files->firstWhere('category', 'Test Report');
    $tier = config("digital_library.document_types.{$file->category}.level", 'product');
@endphp

<div class="list-view-item product-item">
    <div class="list-item-main">
        <div class="product-icon-box sm shadow-none bg-light border-0" style="width: 36px; height: 36px;">
            <img src="{{ asset('assets/img/pdf icon.png') }}" width="18" alt="PDF">
        </div>
        <div class="list-item-info">
            <div class="list-item-title">{{ $productName }}</div>
            <div class="list-item-meta">
                @if($tier === 'brand'){{ $brand }}
                @elseif($tier === 'video'){{ $category }}
                @else{{ $brand }} · {{ $category }}
                @endif
                @if($file->presenter_id)
                    <span class="ms-2 border-start ps-2">
                        <i class="bx bx-user text-primary"></i> {{ $file->presenter->first_name ?? 'Unknown' }}
                        @if($file->session_date)
                            <i class="bx bx-calendar ms-1 text-primary"></i> {{ \Carbon\Carbon::parse($file->session_date)->format('M d') }}
                        @endif
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div class="list-item-actions">
        @if($tier === 'video')
            @if($file->youtube_url)
                @php
                    $pName = $file->presenter ? trim($file->presenter->first_name . ' ' . $file->presenter->last_name) : '';
                    $sDate = $file->session_date ? \Carbon\Carbon::parse($file->session_date)->format('M d, Y') : '';
                @endphp
                <a href="javascript:void(0)" onclick="playVideo('{{ $file->youtube_url }}', '{{ addslashes($productName) }}', '{{ addslashes($pName) }}', '{{ addslashes($sDate) }}')" class="action-pill border-0" style="background: var(--hitech-primary); color: #fff;"><i class="bx bx-play"></i> PLAY</a>
            @endif
        @elseif($tier === 'brand')
            <a href="{{ route('library.access', $file->id) }}" target="_blank" class="action-pill">{{ $file->category === 'Comparison Report' ? 'VIEW REPORT' : 'VIEW' }}</a>
        @else
            <a href="{{ $tds ? route('library.access', $tds->id) : 'javascript:void(0)' }}" target="{{ $tds ? '_blank' : '' }}" class="action-pill {{ !$tds ? 'disabled' : '' }}">TDS</a>
            <a href="{{ $sds ? route('library.access', $sds->id) : 'javascript:void(0)' }}" target="{{ $sds ? '_blank' : '' }}" class="action-pill {{ !$sds ? 'disabled' : '' }}">SDS</a>
            <a href="{{ $pres ? route('library.access', $pres->id) : 'javascript:void(0)' }}" target="{{ $pres ? '_blank' : '' }}" class="action-pill {{ !$pres ? 'disabled' : '' }}">PRES</a>
            <a href="{{ $testReport ? route('library.access', $testReport->id) : 'javascript:void(0)' }}" target="{{ $testReport ? '_blank' : '' }}" class="action-pill {{ !$testReport ? 'disabled' : '' }}">TEST</a>
        @endif
        @if(auth()->user()->hasRole(['admin', 'Admin', 'super_admin']))
        <a href="javascript:void(0)" onclick="deleteDocument('{{ addslashes($productName) }}')" class="action-pill bg-danger text-white ms-3 border-0" title="Delete Document">
            <i class="bx bx-trash"></i> DEL
        </a>
        @endif
    </div>
</div>
