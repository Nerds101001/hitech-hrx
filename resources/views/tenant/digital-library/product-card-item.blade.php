@php 
    $file = $files->first();
    $tds = $files->firstWhere('category', 'TDS');
    $sds = $files->firstWhere('category', 'SDS');
    $pres = $files->firstWhere('category', 'Presentation') ?? $files->firstWhere('category', 'COMP');
    $testReport = $files->firstWhere('category', 'Test Report');
    $totalSize = 0;
    foreach($files as $f) $totalSize += $f->size;
    $sizeFormatted = number_format($totalSize / 1024 / 1024, 2) . ' MB';
@endphp

<div class="col-md-6 col-xl-4 product-item">
    <div class="product-card border shadow-sm" draggable="true" ondragstart="handleDragStart(event, '{{ addslashes($productName) }}', '{{ $brand }}', '{{ $category }}')">
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
            <div class="product-summary">
                @if($file->youtube_url)
                    @php
                        $pName = $file->presenter ? trim($file->presenter->first_name . ' ' . $file->presenter->last_name) : '';
                        $sDate = $file->session_date ? \Carbon\Carbon::parse($file->session_date)->format('M d, Y') : '';
                        
                        $isDrive = false;
                        $thumbnailUrl = null;
                        if (str_contains($file->youtube_url, 'drive.google.com/file/d/')) {
                            preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $file->youtube_url, $matches);
                            if (!empty($matches[1])) {
                                $thumbnailUrl = "https://drive.google.com/thumbnail?id={$matches[1]}&sz=w800";
                            }
                        } elseif (str_contains($file->youtube_url, 'youtube.com/watch?v=')) {
                            $ytParts = parse_url($file->youtube_url);
                            if(isset($ytParts['query'])) {
                                parse_str($ytParts['query'], $ytParams);
                                if(isset($ytParams['v'])) $thumbnailUrl = "https://img.youtube.com/vi/{$ytParams['v']}/hqdefault.jpg";
                            }
                        } elseif (str_contains($file->youtube_url, 'youtu.be/')) {
                            $ytId = explode('/', parse_url($file->youtube_url, PHP_URL_PATH))[1] ?? null;
                            if ($ytId) $thumbnailUrl = "https://img.youtube.com/vi/{$ytId}/hqdefault.jpg";
                        }
                    @endphp
                    <div class="ratio ratio-16x9 mb-2 rounded overflow-hidden shadow-sm position-relative bg-dark" style="cursor:pointer;" onclick="playVideo('{{ $file->youtube_url }}', '{{ addslashes($productName) }}', '{{ addslashes($pName) }}', '{{ addslashes($sDate) }}')">
                        @if($thumbnailUrl)
                            <img src="{{ $thumbnailUrl }}" style="object-fit: cover; width: 100%; height: 100%; opacity: 0.8;" alt="Video Thumbnail" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div class="w-100 h-100" style="display: none; background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);"></div>
                        @else
                            <div class="w-100 h-100" style="background: linear-gradient(135deg, #232526, #414345);"></div>
                        @endif
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <div class="bg-danger rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 48px; height: 48px;">
                                <i class="ti ti-player-play-filled text-white fs-4" style="margin-left: 3px;"></i>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card-summary mb-2">{{ trim(str_replace('**', '', $file->summary ?? 'AI technical overview for ' . $productName)) }}</div>
                @endif
                
                @if($file->category === 'LEARN' && $file->presenter_id)
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge bg-light text-dark border"><i class="ti ti-user text-primary"></i> {{ $file->presenter->first_name ?? 'Unknown' }} {{ $file->presenter->last_name ?? '' }}</span>
                        @if($file->session_date)
                            <span class="badge bg-light text-dark border"><i class="ti ti-calendar text-primary"></i> {{ \Carbon\Carbon::parse($file->session_date)->format('M d, Y') }}</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="action-row">
                @if($file->category === 'Video' || $file->category === 'LEARN')
                    @if($file->youtube_url)
                        @php
                            $pName = $file->presenter ? trim($file->presenter->first_name . ' ' . $file->presenter->last_name) : '';
                            $sDate = $file->session_date ? \Carbon\Carbon::parse($file->session_date)->format('M d, Y') : '';
                        @endphp
                        <a href="javascript:void(0)" onclick="playVideo('{{ route('library.previewFrame', $file->id) }}', '{{ addslashes($productName) }}', '{{ addslashes($pName) }}', '{{ addslashes($sDate) }}')" class="action-btn w-100" style="grid-column: span 4; background: #fff5f5; border-color: #ffcccc;">
                            <span class="text-danger"><i class="ti ti-player-play fs-5"></i> Play Video</span>
                        </a>
                    @else
                        <a href="{{ route('library.access', $file->id) }}" target="_blank" class="action-btn w-100" style="grid-column: span 4;">
                            <span class="text-primary"><i class="ti ti-video fs-5"></i> Access Media</span>
                        </a>
                    @endif
                @else
                    <a href="{{ $tds ? route('library.access', $tds->id) : 'javascript:void(0)' }}" target="{{ $tds ? '_blank' : '' }}" class="action-btn {{ !$tds ? 'disabled' : '' }}">
                        <span>TDS</span>
                        <span>{{ $tds ? 'View PDF' : 'N/A' }}</span>
                    </a>
                    <a href="{{ $sds ? route('library.access', $sds->id) : 'javascript:void(0)' }}" target="{{ $sds ? '_blank' : '' }}" class="action-btn {{ !$sds ? 'disabled' : '' }}">
                        <span>SDS</span>
                        <span>{{ $sds ? 'View PDF' : 'N/A' }}</span>
                    </a>
                    <a href="{{ $pres ? route('library.access', $pres->id) : 'javascript:void(0)' }}" target="{{ $pres ? '_blank' : '' }}" class="action-btn {{ !$pres ? 'disabled' : '' }}">
                        <span>PRES</span>
                        <span>{{ $pres ? 'Access' : 'N/A' }}</span>
                    </a>
                    <a href="{{ $testReport ? route('library.access', $testReport->id) : 'javascript:void(0)' }}" target="{{ $testReport ? '_blank' : '' }}" class="action-btn {{ !$testReport ? 'disabled' : '' }}">
                        <span>TEST</span>
                        <span>{{ $testReport ? 'Report' : 'N/A' }}</span>
                    </a>
                @endif
            </div>
        </div>
        <div class="card-footer">
            <div class="file-size">{{ $sizeFormatted }}</div>
            <div class="d-flex align-items-center gap-3">
                <a href="javascript:void(0)" onclick="openMoveModal('{{ addslashes($productName) }}', '{{ $brand }}', '{{ $category }}')" class="text-muted" title="Manage Archive">
                    <i class="ti ti-settings fs-5"></i>
                </a>
                @if(auth()->user()->hasRole(['admin', 'Admin', 'super_admin']))
                <a href="javascript:void(0)" onclick="deleteDocument('{{ addslashes($productName) }}')" class="text-danger ms-2" title="Delete Document">
                    <i class="ti ti-trash fs-5"></i>
                </a>
                @endif
                <a href="{{ route('library.access', $file->id) }}" target="_blank" class="view-details">View Details <i class="ti ti-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>
