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
        align-items: center;
        padding: 2rem;
        overflow: hidden;
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
            <div id="book-container" class="animate__animated animate__zoomIn">
                <div class="book" id="flipbook">
                    {{-- Pages will be injected here by PDF.js --}}
                </div>
                <div class="mt-4 text-center">
                    <button class="btn btn-sm btn-outline-secondary me-2" id="prev-page"><i class="ti ti-chevron-left"></i> Previous</button>
                    <span id="page-num-display" class="fw-bold mx-2">Loading pages...</span>
                    <button class="btn btn-sm btn-outline-secondary ms-2" id="next-page">Next <i class="ti ti-chevron-right"></i></button>
                </div>
            </div>

        {{-- Video Player --}}
        @elseif($module->content_type === 'video')
            <div class="video-hero animate__animated animate__fadeInUp">
                @if(str_contains($module->content_url, 'youtube.com') || str_contains($module->content_url, 'youtu.be'))
                    <div id="player"></div> {{-- YT API uses this --}}
                @else
                    <video id="local-video" controls controlsList="nodownload">
                        <source src="{{ $module->content_url }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                @endif
                <div class="video-overlay-msg" id="video-timer">00:00 / --:--</div>
            </div>

        {{-- Policy / Text Content --}}
        @else
            <div class="card border-0 shadow-lg p-5" style="max-width: 800px;">
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
@endsection

@section('page-script')
<script>
    // Configuration
    const moduleId = "{{ $module->id }}";
    const contentType = "{{ $module->content_type }}";
    const pdfUrl = "{{ $module->content_url }}";
    
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

    document.addEventListener('DOMContentLoaded', function() {
        if (contentType === 'catalog' && pdfUrl) {
            initPdfViewer();
        } else if (contentType === 'video') {
            initVideoTracker();
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

    /* --- VIDEO TRACKER LOGIC --- */
    function initVideoTracker() {
        const video = document.getElementById('local-video');
        if (video) {
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
