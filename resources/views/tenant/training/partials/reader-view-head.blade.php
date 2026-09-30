{{-- The viewer's header: what this module is and what to do with it. Title,
     reading timer, notes and the quiz sit here - not in a row of their own
     above the viewer - so the page itself gets that height. $isBook adds the
     PDF's page count, size, zoom and fullscreen. --}}
<div class="rd-view-head">
    <span class="rd-type rd-type-sm {{ $typeClass }}"><i class="bx {{ $typeIcon }}"></i></span>
    <div class="rd-head-text">
        <h1 class="rd-view-title" title="{{ $module->title }}">{{ $module->title }}</h1>
        <div class="rd-meta">
            <span><i class="bx bx-time-five"></i> {{ $module->estimated_time_minutes ?? 5 }} min</span>
            <span><i class="bx bx-file"></i> {{ $typeLabel }}</span>
            @if($needsQuiz)<span><i class="bx bx-clipboard"></i> Quiz</span>@endif
            @if($attempts > 0)<span><i class="bx bx-trophy"></i> {{ $attempts }} {{ $attempts == 1 ? 'Try' : 'Tries' }}</span>@endif
            @if($progress->status === 'completed')
                <span class="ok"><i class="bx bx-check-circle"></i> Passed{{ $progress->assessment_score > 0 ? ' · ' . round($progress->assessment_score) . '%' : '' }}</span>
            @endif
            @if(!empty($isBook))
                <span><i class="bx bx-book-open"></i> <span id="book-page-sub">Opening…</span><span id="book-size"></span></span>
            @endif
        </div>
    </div>
    <div class="rd-actions">
        {{-- Timer (not for video) --}}
        @if($module->content_type !== 'video')
            <div class="timer-chip" id="timer-chip" title="Minimum reading time">
                <i class="bx bx-time-five" id="timer-icon"></i>
                <span id="timer-display">{{ str_pad($module->estimated_time_minutes ?? 5, 2, '0', STR_PAD_LEFT) }}:00</span>
            </div>
        @endif
        <button type="button" class="rd-btn-outline" id="notes-toggle" title="My notes"><i class="bx bx-notepad"></i><span class="d-none d-sm-inline">Notes</span></button>
        <button type="button" class="assess-btn" id="assess-btn" title="{{ $module->content_type === 'video' ? 'Opens once the video has been watched' : 'Opens after the minimum reading time' }}">
            <i class="bx {{ $needsQuiz ? 'bx-list-check' : 'bx-check-circle' }}"></i>
            <span>{{ $needsQuiz ? 'Take Quiz' : 'Mark as Complete' }}</span>
        </button>
    </div>
    @if(!empty($isBook))
        <div class="rd-tools" id="book-tools" style="display:none;">
            <div class="book-zoom-group">
                <button type="button" class="book-zoom-btn" id="book-zoom-out" title="Zoom out"><i class="bx bx-minus"></i></button>
                <span class="book-zoom-label" id="book-zoom-label">100%</span>
                <button type="button" class="book-zoom-btn" id="book-zoom-in" title="Zoom in"><i class="bx bx-plus"></i></button>
            </div>
            <button type="button" class="rd-tool" id="book-fs-btn" title="Fullscreen (F)" onclick="toggleFullscreen()"><i class="bx bx-fullscreen" id="fs-btn-icon"></i></button>
        </div>
    @endif
</div>
