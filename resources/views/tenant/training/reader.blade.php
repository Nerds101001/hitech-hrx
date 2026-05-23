@extends('layouts.layoutMaster')

@section('title', $module->title)

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
@endsection

@section('page-style')
<style>
    :root {
        --primary-teal: #005f6b;
        --secondary-teal: #008c99;
        --accent-cyan: #00acc1;
        --book-bg: #fdfdfd;
    }

    body {
        background-color: #f0f2f5;
    }

    .immersive-wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .reader-navbar {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
    }

    .reader-main {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: stretch;
        padding: 2rem;
        overflow: hidden;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* AI Chat Sidebar styles */
    .ai-chat-sidebar {
        height: 75vh;
        display: flex;
        flex-direction: column;
    }
    .chat-messages {
        scrollbar-width: thin;
    }
    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }
    .chat-messages::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 3px;
    }
    .max-w-85 {
        max-width: 85%;
    }
    .chat-message {
        animation: fadeIn 0.3s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .page-citation-link {
        cursor: pointer;
        text-decoration: underline;
    }
    .page-citation-link:hover {
        color: var(--accent-cyan) !important;
    }
    
    /* Video chapter list styling */
    .video-chapters-sidebar {
        height: 100%;
        min-height: 350px;
    }
    .video-chapters-sidebar button:hover {
        background-color: #f1f5f9;
        color: var(--primary-teal) !important;
    }

    /* Milestone active button */
    .milestone-opt-btn.active {
        background-color: var(--accent-cyan) !important;
        border-color: var(--accent-cyan) !important;
        color: white !important;
    }

    /* --- PREMIUM BOOK FLIP STYLES --- */
    #book-container {
        position: relative;
        width: 100%;
        max-width: 1000px;
        height: 75vh;
        perspective: 2000px;
        display: none;
    }

    .book {
        position: relative;
        width: 100%;
        height: 100%;
        transform-style: preserve-3d;
        transition: transform 0.5s;
    }

    .page {
        position: absolute;
        width: 50%;
        height: 100%;
        top: 0;
        right: 0;
        transform-origin: left;
        transform-style: preserve-3d;
        transition: transform 0.8s cubic-bezier(0.645, 0.045, 0.355, 1);
        z-index: 1;
        cursor: pointer;
    }

    .page-front, .page-back {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        backface-visibility: hidden;
        background: var(--book-bg);
        box-shadow: inset 3px 0 10px rgba(0,0,0,0.1), 5px 5px 15px rgba(0,0,0,0.05);
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
        overflow: hidden;
    }

    .page-back {
        transform: rotateY(180deg);
        border-right: 2px solid #eee;
    }

    .page.flipped {
        transform: rotateY(-180deg);
    }

    canvas {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    /* --- VIDEO STYLES --- */
    .video-hero {
        width: 100%;
        max-width: 1100px;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        position: relative;
    }

    .video-hero video, .video-hero iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    .video-overlay-msg {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 8px 20px;
        border-radius: 30px;
        font-size: 0.9rem;
        backdrop-filter: blur(5px);
        opacity: 0.8;
    }

    /* --- ASSESSMENT OVERLAY --- */
    #assessment-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.98);
        z-index: 2000;
        display: none;
        padding: 4rem;
        overflow-y: auto;
    }

    .quiz-card {
        max-width: 700px;
        margin: 0 auto;
    }

    .quiz-option {
        padding: 1.25rem;
        border: 2px solid #e2e8f0;
        border-radius: 15px;
        margin-bottom: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        font-weight: 500;
    }

    .quiz-option:hover {
        border-color: var(--accent-cyan);
        background: #f0fdff;
    }

    .quiz-option.selected {
        border-color: var(--primary-teal);
        background: #e0f7f9;
        transform: scale(1.02);
    }

    .quiz-indicator {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid #cbd5e0;
        margin-right: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .selected .quiz-indicator {
        background: var(--primary-teal);
        border-color: var(--primary-teal);
    }

    .selected .quiz-indicator::after {
        content: '';
        width: 8px;
        height: 8px;
        background: white;
        border-radius: 50%;
    }

    /* --- ANIMATIONS --- */
    @keyframes pageTurn {
        0% { transform: rotateY(0deg); }
        100% { transform: rotateY(-180deg); }
    }
</style>
@endsection

@section('content')
<div class="immersive-wrapper">
    {{-- Top Bar --}}
    <nav class="reader-navbar">
        <div class="d-flex align-items-center">
            <a href="{{ route('training.portal') }}" class="btn btn-icon btn-label-secondary me-3">
                <i class="ti ti-arrow-left"></i>
            </a>
            <div>
                <h5 class="mb-0 fw-bold">{{ $module->title }}</h5>
                <small class="text-muted" id="status-text">
                    @if($module->content_type == 'catalog')
                        Interactive Catalog • Click right side to flip pages
                    @elseif($module->content_type == 'video')
                        Video Presentation • Watch full to unlock assessment
                    @else
                        Standard Policy Module
                    @endif
                </small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="progress-info text-end d-none d-md-block">
                <div class="text-xs fw-bold text-uppercase text-muted">Reading Progress</div>
                <div class="progress" style="height: 6px; width: 150px;">
                    <div class="progress-bar bg-primary" id="main-progress" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
            <button class="btn btn-primary shadow-sm" id="assessment-trigger" style="display: none;">
                <i class="ti ti-checklist me-2"></i> Take Assessment
            </button>
        </div>
    </nav>

    {{-- Main Reader Area --}}
    <main class="reader-main">
        {{-- Catalog / PDF Book --}}
        @if($module->content_type === 'catalog' && $module->content_url)
            <div class="d-flex w-100 flex-column flex-lg-row gap-4 align-items-stretch" style="max-height: 80vh;">
                <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center">
                    <div id="book-container" class="animate__animated animate__zoomIn" style="display: block; width: 100%;">
                        <div class="book" id="flipbook">
                            {{-- Pages will be injected here by PDF.js --}}
                        </div>
                        <div class="mt-4 text-center">
                            <button class="btn btn-sm btn-outline-secondary me-2" id="prev-page"><i class="ti ti-chevron-left"></i> Previous</button>
                            <span id="page-num-display" class="fw-bold mx-2">Loading pages...</span>
                            <button class="btn btn-sm btn-outline-secondary ms-2" id="next-page">Next <i class="ti ti-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                
                <!-- AI Chat Sidebar Drawer -->
                <div class="ai-chat-sidebar shadow-sm d-flex flex-column bg-white rounded-3 overflow-hidden border" style="width: 350px; min-width: 300px;">
                    <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ti ti-cpu text-primary fs-4"></i>
                            <div>
                                <h6 class="fw-bold mb-0">AI Study Assistant</h6>
                                <small class="text-muted">Ask anything about this catalog</small>
                            </div>
                        </div>
                    </div>
                    <div class="chat-messages flex-grow-1 p-3 overflow-y-auto d-flex flex-column gap-3" id="chat-messages-container" style="height: 350px;">
                        <div class="chat-message bot-message bg-light p-3 rounded-3 align-self-start max-w-85 text-sm">
                            Hello! I am your study assistant for this catalog. Ask me any question, and I will search the document and cite pages for you.
                        </div>
                    </div>
                    <form id="ai-chat-form" class="p-3 border-top bg-light">
                        <div class="input-group">
                            <input type="text" id="ai-chat-input" class="form-control" placeholder="Ask a question..." required>
                            <button type="submit" class="btn btn-primary" id="ai-chat-send-btn">
                                <i class="ti ti-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        {{-- Video Player --}}
        @elseif($module->content_type === 'video')
            <div class="d-flex w-100 flex-column flex-lg-row gap-4 align-items-stretch" style="max-height: 80vh;">
                <!-- Left: Video Player -->
                <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center">
                    <div class="video-hero animate__animated animate__fadeInUp w-100" style="max-width: 100%; position: relative; aspect-ratio: 16/9; background: #000; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
                        @if(str_contains($module->content_url, 'youtube.com') || str_contains($module->content_url, 'youtu.be'))
                            <div id="player" class="w-100 h-100"></div> {{-- YT API uses this --}}
                        @else
                            <video id="local-video" class="w-100 h-100" controls controlsList="nodownload">
                                <source src="{{ $module->content_url }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        @endif
                        <div class="video-overlay-msg" id="video-timer">00:00 / --:--</div>
                        
                        <!-- Milestone Quiz Overlay -->
                        <div id="milestone-quiz-overlay" class="d-none align-items-center justify-content-center text-white" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1050; padding: 2rem;">
                            <div class="w-100 text-center" style="max-width: 500px;">
                                <span class="badge bg-warning mb-2"><i class="ti ti-bulb me-1"></i> Milestone Quiz Checkpoint</span>
                                <h4 class="fw-bold mb-4 text-white" id="milestone-question">Question text goes here?</h4>
                                <div id="milestone-options" class="d-flex flex-column gap-2 mb-4">
                                    <!-- Options injected dynamically -->
                                </div>
                                <button class="btn btn-warning rounded-pill px-4 shadow" id="milestone-submit-btn" disabled>Submit Answer</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Chapters & Milestones Sidebar -->
                <div class="video-chapters-sidebar shadow-sm bg-white rounded-3 overflow-hidden border d-flex flex-column" style="width: 320px; min-width: 280px;">
                    <div class="p-3 bg-light border-bottom">
                        <h6 class="fw-bold mb-0">Video Chapters</h6>
                        <small class="text-muted">Click to jump to a topic</small>
                    </div>
                    <div class="flex-grow-1 p-3 overflow-y-auto d-flex flex-column gap-2" id="video-chapters-list">
                        <!-- Chapters list will be populated here -->
                        <p class="text-muted small text-center my-auto">No chapters defined.</p>
                    </div>
                </div>
            </div>

        {{-- Policy / Text Content --}}
        @else
            <div class="card border-0 shadow-lg p-5 mx-auto align-self-center" style="max-width: 800px;">
                <div class="prose">
                    {!! nl2br(e($module->content_body)) !!}
                </div>
                <hr class="my-4">
                <div class="text-center">
                    <button class="btn btn-primary btn-lg px-5" onclick="showAssessment()">Done Reading - Start Quiz</button>
                </div>
            </div>
        @endif
    </main>
</div>

{{-- Assessment Fullscreen Overlay --}}
<div id="assessment-overlay">
    <div class="quiz-card animate__animated animate__fadeIn">
        <div class="text-center mb-5">
            <div class="badge bg-label-primary p-2 px-3 mb-3">Module Assessment</div>
            <h2 class="fw-bold" id="quiz-question-title">Preparing your assessment...</h2>
            <p class="text-muted" id="quiz-pass-threshold">You need 80% to pass. Good luck!</p>
        </div>

        <div id="quiz-body">
            {{-- Questions injected here --}}
        </div>

        <div class="d-flex justify-content-between mt-5" id="quiz-nav-btns">
            <button class="btn btn-label-secondary" id="quiz-prev" style="visibility: hidden;">Previous</button>
            <button class="btn btn-primary px-5" id="quiz-next">Next Question</button>
            <button class="btn btn-success px-5" id="quiz-submit" style="display: none;">Submit Final Answers</button>
        </div>
    </div>

    {{-- Result State --}}
    <div id="quiz-result-state" class="text-center" style="display: none;">
        <div id="result-icon-area" class="mb-4"></div>
        <h1 class="fw-bold" id="result-headline"></h1>
        <p class="fs-5 text-muted mb-5" id="result-subline"></p>
        <div class="d-flex justify-content-center gap-3">
            <button class="btn btn-lg btn-label-secondary" onclick="location.reload()">Review & Retake</button>
            <button class="btn btn-lg btn-primary px-5" onclick="location.href='{{ route('training.portal') }}'">Return to Portal</button>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
@endsection

@section('page-script')
<script>
    // Configuration
    const moduleId = "{{ $module->id }}";
    const contentType = "{{ $module->content_type }}";
    const pdfUrl = "{{ $module->content_url }}";
    const videoChapters = {!! json_encode($module->video_chapters ?? []) !!};
    const videoMilestones = {!! json_encode($module->video_milestones ?? []) !!};
    
    // PDF State
    let pdfDoc = null,
        pageNum = 1,
        pageRendering = false,
        pageNumPending = null,
        totalPages = 0;

    // Quiz State
    let questions = [];
    let currentQIdx = 0;
    let answers = {};
    let showAllAtOnce = false;
    let passingPercentage = 80;

    // Video Tracking State
    let ytPlayer = null;
    let timeTrackingInterval = null;
    let triggeredMilestones = new Set();
    let currentMilestone = null;

    document.addEventListener('DOMContentLoaded', function() {
        if (contentType === 'catalog' && pdfUrl) {
            initPdfViewer();
            initAiChat();
        } else if (contentType === 'video') {
            renderChapters();
            if (pdfUrl.includes('youtube.com') || pdfUrl.includes('youtu.be')) {
                // Dynamically load the YouTube IFrame Player API
                var tag = document.createElement('script');
                tag.src = "https://www.youtube.com/iframe_api";
                var firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            } else {
                initVideoTracker();
            }
        } else {
            document.getElementById('assessment-trigger').style.display = 'block';
        }

        // Assessment Trigger
        document.getElementById('assessment-trigger').onclick = showAssessment;
    });

    /* --- PDF VIEWER & BOOK FLIP LOGIC --- */
    function initPdfViewer() {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        document.getElementById('book-container').style.display = 'block';

        pdfjsLib.getDocument(pdfUrl).promise.then(doc => {
            pdfDoc = doc;
            totalPages = doc.numPages;
            document.getElementById('page-num-display').textContent = `Page 1 of ${totalPages}`;
            renderPage(pageNum);
        }).catch(err => {
            console.error("Error loading PDF document:", err);
            document.getElementById('page-num-display').textContent = "Failed to load catalog PDF.";
        });

        document.getElementById('next-page').onclick = () => {
            if (pageNum >= totalPages) return;
            pageNum++;
            renderPage(pageNum, 'forward');
        };

        document.getElementById('prev-page').onclick = () => {
            if (pageNum <= 1) return;
            pageNum--;
            renderPage(pageNum, 'backward');
        };
    }

    async function renderPage(num, direction) {
        pageRendering = true;
        const page = await pdfDoc.getPage(num);
        const viewport = page.getViewport({ scale: 1.5 });
        
        const flipbook = document.getElementById('flipbook');
        
        if (direction) {
            flipbook.classList.add('animate__animated', direction === 'forward' ? 'animate__fadeOutLeft' : 'animate__fadeOutRight');
            await new Promise(r => setTimeout(r, 300));
        }

        flipbook.innerHTML = '';
        const canvas = document.createElement('canvas');
        flipbook.appendChild(canvas);
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = { canvasContext: context, viewport: viewport };
        await page.render(renderContext).promise;
        
        if (direction) {
            flipbook.classList.remove('animate__fadeOutLeft', 'animate__fadeOutRight');
            flipbook.classList.add(direction === 'forward' ? 'animate__fadeInRight' : 'animate__fadeInLeft');
        }

        document.getElementById('page-num-display').textContent = `Page ${num} of ${totalPages}`;
        const progress = (num / totalPages) * 100;
        document.getElementById('main-progress').style.width = progress + '%';

        if (num === totalPages) {
            document.getElementById('assessment-trigger').style.display = 'block';
        }
        
        pageRendering = false;
    }

    function goToPage(pNum) {
        pNum = parseInt(pNum);
        if (pNum >= 1 && pNum <= totalPages) {
            pageNum = pNum;
            renderPage(pageNum);
        }
    }

    /* --- AI STUDY ASSISTANT CHAT LOGIC --- */
    function initAiChat() {
        const chatForm = document.getElementById('ai-chat-form');
        if (chatForm) {
            chatForm.onsubmit = async (e) => {
                e.preventDefault();
                const input = document.getElementById('ai-chat-input');
                const userMsgText = input.value.trim();
                if (!userMsgText) return;
                
                input.value = '';
                
                // Add user message to container
                appendChatMessage(userMsgText, 'user');
                
                // Add bot typing indicator
                const typingId = appendChatTypingIndicator();
                
                try {
                    const response = await fetch(`{{ url('/training/module') }}/${moduleId}/chat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ message: userMsgText })
                    });
                    const data = await response.json();
                    
                    removeChatTypingIndicator(typingId);
                    
                    if (data.success) {
                        appendChatMessage(data.message, 'bot');
                    } else {
                        appendChatMessage(data.message || 'Error occurred while talking to AI.', 'bot-error');
                    }
                } catch (err) {
                    removeChatTypingIndicator(typingId);
                    appendChatMessage('Could not reach AI study assistant. Please try again.', 'bot-error');
                }
            };
        }
    }

    function appendChatMessage(text, sender) {
        const container = document.getElementById('chat-messages-container');
        if (!container) return;
        
        const div = document.createElement('div');
        if (sender === 'user') {
            div.className = 'chat-message user-message text-white p-3 rounded-3 align-self-end max-w-85 text-sm';
            div.style.backgroundColor = 'var(--primary-teal)';
            div.textContent = text;
        } else if (sender === 'bot') {
            div.className = 'chat-message bot-message bg-light p-3 rounded-3 align-self-start max-w-85 text-sm';
            div.innerHTML = formatMessage(text);
        } else {
            div.className = 'chat-message bot-message bg-label-danger text-danger p-3 rounded-3 align-self-start max-w-85 text-sm';
            div.textContent = text;
        }
        
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function appendChatTypingIndicator() {
        const container = document.getElementById('chat-messages-container');
        if (!container) return null;
        
        const id = 'typing-' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.className = 'chat-message bot-message bg-light p-3 rounded-3 align-self-start max-w-85 text-sm d-flex gap-1 align-items-center';
        div.innerHTML = `
            <span class="spinner-grow spinner-grow-sm text-primary" role="status"></span>
            <span class="text-muted small">Thinking...</span>
        `;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
        return id;
    }

    function removeChatTypingIndicator(id) {
        if (!id) return;
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function formatMessage(text) {
        let formatted = text.replace(/Page\s+(\d+)/gi, (match, pNum) => {
            return `<a href="javascript:void(0)" class="fw-bold text-primary page-citation-link" onclick="goToPage(${pNum})">Page ${pNum}</a>`;
        });
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formatted = formatted.replace(/^\s*-\s+(.*?)$/gm, '<li>$1</li>');
        formatted = formatted.replace(/\n/g, '<br>');
        return formatted;
    }

    /* --- VIDEO TRACKER LOGIC --- */
    function formatTime(sec) {
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }

    function seekVideo(sec) {
        if (ytPlayer && typeof ytPlayer.seekTo === 'function') {
            ytPlayer.seekTo(sec, true);
            ytPlayer.playVideo();
        } else {
            const video = document.getElementById('local-video');
            if (video) {
                video.currentTime = sec;
                video.play();
            }
        }
    }

    function pauseVideo() {
        if (ytPlayer && typeof ytPlayer.pauseVideo === 'function') {
            ytPlayer.pauseVideo();
        } else {
            const video = document.getElementById('local-video');
            if (video) {
                video.pause();
            }
        }
    }

    function playVideo() {
        if (ytPlayer && typeof ytPlayer.playVideo === 'function') {
            ytPlayer.playVideo();
        } else {
            const video = document.getElementById('local-video');
            if (video) {
                video.play();
            }
        }
    }

    function renderChapters() {
        const list = document.getElementById('video-chapters-list');
        if (!list) return;
        if (!videoChapters || videoChapters.length === 0) {
            list.innerHTML = '<p class="text-muted small text-center my-auto">No chapters defined.</p>';
            return;
        }
        list.innerHTML = '';
        videoChapters.forEach(chap => {
            const btn = document.createElement('button');
            btn.className = 'btn btn-outline-teal text-start w-100 py-2 px-3 border-0 d-flex justify-content-between align-items-center mb-1';
            btn.style.borderRadius = '8px';
            btn.innerHTML = `
                <span class="text-truncate fw-medium text-dark">${chap.title}</span>
                <span class="badge bg-label-secondary font-monospace">${formatTime(chap.time)}</span>
            `;
            btn.onclick = () => seekVideo(chap.time);
            list.appendChild(btn);
        });
    }

    function checkMilestones(currentTime) {
        if (!videoMilestones || videoMilestones.length === 0) return;
        videoMilestones.forEach(m => {
            if (currentTime >= m.time && !triggeredMilestones.has(m.time)) {
                triggeredMilestones.add(m.time);
                triggerMilestone(m);
            }
        });
    }

    function triggerMilestone(m) {
        pauseVideo();
        currentMilestone = m;
        
        document.getElementById('milestone-question').textContent = m.question;
        const optContainer = document.getElementById('milestone-options');
        optContainer.innerHTML = '';
        
        let selectedIdx = null;
        const submitBtn = document.getElementById('milestone-submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submit Answer';
        submitBtn.className = 'btn btn-warning rounded-pill px-4 shadow';
        
        m.options.forEach((opt, idx) => {
            const btn = document.createElement('button');
            btn.className = 'btn btn-outline-light text-start py-2 px-3 border w-100 milestone-opt-btn';
            btn.style.borderRadius = '8px';
            btn.textContent = opt;
            btn.onclick = () => {
                document.querySelectorAll('.milestone-opt-btn').forEach(b => b.classList.remove('active', 'btn-warning', 'text-dark'));
                btn.classList.add('active', 'btn-warning', 'text-dark');
                selectedIdx = idx;
                submitBtn.disabled = false;
            };
            optContainer.appendChild(btn);
        });
        
        submitBtn.onclick = () => {
            if (selectedIdx === m.correct) {
                confetti({
                    particleCount: 50,
                    spread: 60,
                    origin: { y: 0.8 }
                });
                submitBtn.textContent = 'Correct! Resuming...';
                submitBtn.className = 'btn btn-success rounded-pill px-4 shadow';
                submitBtn.disabled = true;
                setTimeout(() => {
                    document.getElementById('milestone-quiz-overlay').classList.add('d-none');
                    playVideo();
                }, 1500);
            } else {
                Swal.fire({
                    title: 'Incorrect Answer',
                    text: 'Please try again to resume the video training.',
                    icon: 'error'
                });
            }
        };
        
        document.getElementById('milestone-quiz-overlay').classList.remove('d-none');
    }

    // YouTube Iframe API Global Callback
    window.onYouTubeIframeAPIReady = function() {
        const videoId = getYoutubeId(pdfUrl || "");
        if (!videoId) return;

        ytPlayer = new YT.Player('player', {
            height: '100%',
            width: '100%',
            videoId: videoId,
            playerVars: {
                'playsinline': 1,
                'controls': 1,
                'rel': 0
            },
            events: {
                'onReady': onPlayerReady,
                'onStateChange': onPlayerStateChange
            }
        });
    }

    function getYoutubeId(url) {
        const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
        const match = url.match(regExp);
        return (match && match[2].length === 11) ? match[2] : null;
    }

    function onPlayerReady(event) {
        document.getElementById('video-timer').textContent = `00:00 / ${formatTime(ytPlayer.getDuration())}`;
    }

    function onPlayerStateChange(event) {
        if (event.data == YT.PlayerState.PLAYING) {
            startTimeTracking();
        } else {
            stopTimeTracking();
        }
        
        if (event.data == YT.PlayerState.ENDED) {
            document.getElementById('assessment-trigger').style.display = 'block';
        }
    }

    function startTimeTracking() {
        stopTimeTracking();
        timeTrackingInterval = setInterval(() => {
            if (ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
                const currentTime = ytPlayer.getCurrentTime();
                const duration = ytPlayer.getDuration();
                document.getElementById('video-timer').textContent = `${formatTime(currentTime)} / ${formatTime(duration)}`;
                
                const progress = (currentTime / duration) * 100;
                document.getElementById('main-progress').style.width = progress + '%';
                
                checkMilestones(currentTime);
            }
        }, 500);
    }

    function stopTimeTracking() {
        if (timeTrackingInterval) {
            clearInterval(timeTrackingInterval);
            timeTrackingInterval = null;
        }
    }

    function initVideoTracker() {
        const video = document.getElementById('local-video');
        if (video) {
            video.addEventListener('timeupdate', () => {
                const currentTime = video.currentTime;
                const duration = video.duration || 0;
                document.getElementById('video-timer').textContent = `${formatTime(currentTime)} / ${formatTime(duration)}`;
                
                const progress = duration > 0 ? (currentTime / duration) * 100 : 0;
                document.getElementById('main-progress').style.width = progress + '%';
                
                checkMilestones(currentTime);
            });

            video.onended = () => {
                document.getElementById('assessment-trigger').style.display = 'block';
            };
        }
    }

    /* --- ASSESSMENT ENGINE --- */
    async function showAssessment() {
        document.getElementById('assessment-overlay').style.display = 'block';
        
        try {
            const res = await fetch(`{{ route('training.assessment.get', $module->id) }}`);
            const data = await res.json();
            
            if (data.success && data.questions.length > 0) {
                questions = data.questions;
                showAllAtOnce = data.show_all_at_once;
                passingPercentage = data.passing_percentage;
                
                document.getElementById('quiz-pass-threshold').textContent = `You need ${passingPercentage}% to pass. Good luck!`;
                renderQuiz();
            }
        } catch (e) { console.error(e); }
    }

    function renderQuiz() {
        const body = document.getElementById('quiz-body');
        body.innerHTML = '';
        
        if (showAllAtOnce) {
            document.getElementById('quiz-question-title').textContent = "Full Assessment";
            questions.forEach((q, qIdx) => {
                const qDiv = document.createElement('div');
                qDiv.className = 'mb-5 p-4 border rounded bg-white shadow-sm';
                qDiv.innerHTML = `<h5 class="fw-bold mb-3">${qIdx + 1}. ${q.question} <small class="text-muted">(${q.marks} pts)</small></h5>`;
                
                const optionsList = document.createElement('div');
                q.options.forEach((opt, idx) => {
                    const optDiv = document.createElement('div');
                    optDiv.className = `quiz-option ${answers[q.id] == idx ? 'selected' : ''}`;
                    optDiv.innerHTML = `<div class="quiz-indicator"></div><span>${opt}</span>`;
                    optDiv.onclick = () => { answers[q.id] = idx; renderQuiz(); };
                    optionsList.appendChild(optDiv);
                });
                qDiv.appendChild(optionsList);
                body.appendChild(qDiv);
            });
            document.getElementById('quiz-nav-btns').style.display = 'none';
            const submitBtn = document.getElementById('quiz-submit');
            submitBtn.style.display = 'block';
            submitBtn.className = 'btn btn-success btn-lg w-100 py-3 mt-4';
            body.appendChild(submitBtn);
            
        } else {
            const q = questions[currentQIdx];
            document.getElementById('quiz-question-title').textContent = `Question ${currentQIdx + 1} of ${questions.length}`;
            
            const qHeader = document.createElement('h3');
            qHeader.className = 'fw-bold mb-4 text-center';
            qHeader.textContent = q.question;
            body.appendChild(qHeader);

            q.options.forEach((opt, idx) => {
                const div = document.createElement('div');
                div.className = `quiz-option ${answers[q.id] == idx ? 'selected' : ''}`;
                div.innerHTML = `<div class="quiz-indicator"></div><span>${opt}</span>`;
                div.onclick = () => { answers[q.id] = idx; renderQuiz(); };
                body.appendChild(div);
            });

            document.getElementById('quiz-prev').style.visibility = currentQIdx === 0 ? 'hidden' : 'visible';
            if (currentQIdx === questions.length - 1) {
                document.getElementById('quiz-next').style.display = 'none';
                document.getElementById('quiz-submit').style.display = 'block';
            } else {
                document.getElementById('quiz-next').style.display = 'block';
                document.getElementById('quiz-submit').style.display = 'none';
            }
        }
    }

    document.getElementById('quiz-next').onclick = () => {
        if (answers[questions[currentQIdx].id] === undefined) return Swal.fire('Wait', 'Select an answer!', 'warning');
        currentQIdx++; renderQuiz();
    };

    document.getElementById('quiz-prev').onclick = () => { currentQIdx--; renderQuiz(); };

    document.getElementById('quiz-submit').onclick = async () => {
        if (Object.keys(answers).length < questions.length) return Swal.fire('Wait', 'Answer all questions!', 'warning');

        Swal.fire({ title: 'Submitting...', didOpen: () => Swal.showLoading() });

        const res = await fetch(`{{ route('training.assessment.submit', $module->id) }}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ answers })
        });
        
        const data = await res.json();
        Swal.close();

        if (data.success) {
            document.querySelector('.quiz-card').style.display = 'none';
            document.getElementById('quiz-result-state').style.display = 'block';
            
            const iconArea = document.getElementById('result-icon-area');
            if (data.passed) {
                confetti({
                    particleCount: 150,
                    spread: 80,
                    origin: { y: 0.6 }
                });
                iconArea.innerHTML = '<i class="ti ti-trophy text-success" style="font-size: 6rem;"></i>';
                document.getElementById('result-headline').textContent = 'Congratulations! 🎓';
                document.getElementById('result-subline').innerHTML = `Score: <b>${data.score}%</b>. Points: ${data.earned_marks}/${data.total_marks}.`;
            } else {
                iconArea.innerHTML = '<i class="ti ti-circle-x text-danger" style="font-size: 6rem;"></i>';
                document.getElementById('result-headline').textContent = 'Try Again';
                document.getElementById('result-subline').innerHTML = `Score: <b>${data.score}%</b>. (Needs ${data.passing_percentage}%).`;
            }
        }
    };
</script>
@endsection
