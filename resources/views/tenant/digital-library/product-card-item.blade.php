@php
    $file = $files->first();
    $tds = $files->firstWhere('category', 'TDS');
    $sds = $files->firstWhere('category', 'SDS');
    $pres = $files->firstWhere('category', 'Presentation') ?? $files->firstWhere('category', 'COMP');
    $testReport = $files->firstWhere('category', 'Test Report');
    $totalSize = 0;
    foreach($files as $f) $totalSize += $f->size;
    $sizeFormatted = number_format($totalSize / 1024 / 1024, 2) . ' MB';
    $tier = config("digital_library.document_types.{$file->category}.level", 'product');
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
                    <div class="product-subtitle">
                        @if($tier === 'brand'){{ $brand }}
                        @elseif($tier === 'video'){{ $category }}
                        @else{{ $brand }} · {{ $category }}
                        @endif
                    </div>
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
                        {{-- Bootstrap's .ratio > * forces every direct child to
                             width:100%;height:100% for the aspect-ratio hack —
                             that fights translate-middle's centering math (it
                             ends up translating by -50% of the full thumbnail
                             size, not the button's own size, landing it in the
                             top-left corner). w-auto/h-auto opt this one back
                             out of the forced sizing. --}}
                        <div class="position-absolute top-50 start-50 translate-middle w-auto h-auto">
                            {{-- Play is a neutral, positive action, not a warning —
                                 bg-danger (red) reads as "stop/threat" to anyone
                                 who's used a video player before. Theme teal. --}}
                            <div class="rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 48px; height: 48px; background: var(--hitech-primary);">
                                <i class="bx bx-play text-white fs-4" style="margin-left: 3px;"></i>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card-summary mb-2">{{ trim(str_replace('**', '', $file->summary ?? 'AI technical overview for ' . $productName)) }}</div>
                @endif
                
                @if($file->presenter_id)
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge bg-light text-dark border"><i class="bx bx-user text-primary"></i> {{ $file->presenter->first_name ?? 'Unknown' }} {{ $file->presenter->last_name ?? '' }}</span>
                        @if($file->session_date)
                            <span class="badge bg-light text-dark border"><i class="bx bx-calendar text-primary"></i> {{ \Carbon\Carbon::parse($file->session_date)->format('M d, Y') }}</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="action-row {{ in_array($tier, ['video', 'brand']) ? 'action-row-wide' : '' }}">
                @if($tier === 'video')
                    @php
                        $videoSummary = trim(str_replace('**', '', $file->summary ?? ''));
                    @endphp
                    @if($file->youtube_url)
                        @php
                            $pName = $file->presenter ? trim($file->presenter->first_name . ' ' . $file->presenter->last_name) : '';
                            $sDate = $file->session_date ? \Carbon\Carbon::parse($file->session_date)->format('M d, Y') : '';
                        @endphp
                        <a href="javascript:void(0)" onclick="playVideo('{{ route('library.previewFrame', $file->id) }}', '{{ addslashes($productName) }}', '{{ addslashes($pName) }}', '{{ addslashes($sDate) }}')" class="action-btn action-btn-wide action-btn-primary" style="grid-column: span 2;">
                            <span><i class="bx bx-play fs-5"></i> Play</span>
                        </a>
                    @else
                        <a href="{{ route('library.access', $file->id) }}" target="_blank" class="action-btn action-btn-wide action-btn-primary" style="grid-column: span 2;">
                            <span><i class="bx bx-video fs-5"></i> Play</span>
                        </a>
                    @endif
                    <button type="button" onclick="openVideoSummaryModal('{{ addslashes($productName) }}', '{{ addslashes($videoSummary) }}')" class="action-btn action-btn-wide" style="grid-column: span 2;">
                        <span><i class="bx bx-file fs-5"></i> Summary</span>
                    </button>
                @elseif($tier === 'brand')
                    {{-- One document, one action — a Presentation or Comparison
                         Report doesn't have sibling TDS/SDS/Test Report docs to
                         switch between, so the 4-slot grid doesn't apply. --}}
                    <a href="{{ route('library.access', $file->id) }}" target="_blank" class="action-btn action-btn-wide">
                        <span>{{ $file->category === 'Comparison Report' ? 'View Report' : 'View Presentation' }}</span>
                    </a>
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
                    <i class="bx bx-cog fs-5"></i>
                </a>
                @if(auth()->user()->hasRole(['admin', 'Admin', 'super_admin']))
                <a href="javascript:void(0)" onclick="deleteDocument('{{ addslashes($productName) }}')" class="text-danger ms-2" title="Delete Document">
                    <i class="bx bx-trash fs-5"></i>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
