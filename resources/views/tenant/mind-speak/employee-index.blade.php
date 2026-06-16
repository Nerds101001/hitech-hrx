@extends('layouts.layoutMaster')

@section('title', 'Mind Speak')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('page-style')
<style>
  .char-counter {
    font-size: 0.75rem;
    color: #6b7280;
    text-align: right;
    margin-top: 0.25rem;
  }

  /* ===================== Alerts ===================== */
  .ms-alert {
    border-radius: 12px;
    border-left: 5px solid;
    padding: 0.9rem 1.2rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
  }
</style>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

  {{-- Flash Messages --}}
  @if(session('success'))
    <div class="ms-alert alert alert-success alert-dismissible fade show" role="alert" style="border-left-color: #10b981;">
      <i class="bx bx-check-circle me-2 fs-5 align-middle"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="ms-alert alert alert-danger alert-dismissible fade show" role="alert" style="border-left-color: #ef4444;">
      <i class="bx bx-error-circle me-2 fs-5 align-middle"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Hero Header --}}
  <div class="hitech-page-hero mb-6 animate__animated animate__fadeIn">
    <div class="hitech-page-hero-text">
      <h4 class="greeting">Innovation Hub Portal</h4>
      <p class="sub-text">Share your ideas, suggestions, complaints, sales pitches, or seminars. Make your voice heard, openly or anonymously.</p>
    </div>
  </div>

  <div class="row g-4">
    {{-- Left Side: Submission Form --}}
    <div class="col-12 col-lg-5">
      <div class="hitech-card mb-6 shadow-sm">
        <div class="hitech-card-header">
          <h5 class="title mb-0 d-flex align-items-center gap-2" style="color: #005a5a;">
            <i class="bx bx-pen fs-4" style="color: #0d9488;"></i>
            <span>Submit New Innovation</span>
          </h5>
        </div>
        <div class="card-body p-4">
          <form action="{{ route('mind-speak.store') }}" method="POST" id="mindSpeakForm" enctype="multipart/form-data">
            @csrf
            
            <div class="mb-4">
              <label class="hitech-label" for="category">Category</label>
              <select name="category" id="category" class="form-select-hitech form-select @error('category') is-invalid @enderror" required>
                <option value="" disabled selected>Select Category...</option>
                <option value="Idea" {{ old('category') == 'Idea' ? 'selected' : '' }}>💡 Idea</option>
                <option value="Complaint" {{ old('category') == 'Complaint' ? 'selected' : '' }}>⚠️ Complaint</option>
                <option value="Sales Pitch" {{ old('category') == 'Sales Pitch' ? 'selected' : '' }}>🎙️ Sales Pitch</option>
                <option value="Seminar" {{ old('category') == 'Seminar' ? 'selected' : '' }}>🎓 Seminar</option>
                <option value="Competitor Insights" {{ old('category') == 'Competitor Insights' ? 'selected' : '' }}>👁️ Competitor Insights</option>
              </select>
              @error('category')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-4">
              <label class="hitech-label" for="content" id="contentLabel">What is on your mind?</label>
              <textarea name="content" id="content" rows="6" class="form-control-hitech form-control @error('content') is-invalid @enderror" 
                        placeholder="Please describe your idea or complaint in detail..." required minlength="5" maxlength="2000">{{ old('content') }}</textarea>
              <div class="d-flex justify-content-between align-items-center mt-1">
                @error('content')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <span class="char-counter ms-auto" id="charCounter">0 / 2000 characters</span>
              </div>
            </div>

            <div class="mb-4" id="attachmentGroup">
              <label class="hitech-label" for="attachment" id="attachmentLabel">Attachment (Optional, PDF/Image only)</label>
              <input type="file" name="attachment" id="attachment" class="form-control-hitech form-control @error('attachment') is-invalid @enderror" accept=".pdf,.png,.jpg,.jpeg">
              
              {{-- Live Audio Recorder UI (Hidden by default) --}}
              <div id="audioRecorderUI" class="mt-2 d-none p-3 rounded-3" style="background: rgba(13, 115, 119, 0.05); border: 1px dashed #0d7377;">
                 <div class="d-flex align-items-center justify-content-between">
                    <div>
                      <span class="d-block fw-bold" style="color: #0d7377; font-size: 0.9rem;"><i class="bx bx-microphone"></i> Record Live Audio</span>
                      <small class="text-muted" id="recordingStatus">Ready to record.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <canvas id="audioVisualizer" width="60" height="24" class="d-none me-2" style="border-radius: 4px; background: transparent;"></canvas>
                      <span id="recordingTimer" class="d-none fw-bold text-danger small me-2" style="font-variant-numeric: tabular-nums;">00:00</span>
                      <button type="button" id="startRecordBtn" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="bx bx-radio-circle me-1"></i> Start</button>
                      <button type="button" id="stopRecordBtn" class="btn btn-sm btn-danger rounded-pill px-3 d-none"><i class="bx bx-stop-circle me-1"></i> Stop</button>
                    </div>
                 </div>
                 <div id="audioPlaybackContainer" class="mt-3 d-none">
                    <audio id="audioPlayback" controls class="w-100 mb-2" style="height: 36px;"></audio>
                    <div class="text-end">
                      <button type="button" id="clearAudioBtn" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bx bx-trash"></i> Clear Recording</button>
                    </div>
                 </div>
              </div>

              @error('attachment')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-4 p-4 d-flex align-items-center justify-content-between" id="anonymousGroup" style="background: rgba(18, 116, 100, 0.04); border: 1px solid rgba(18, 116, 100, 0.15); border-radius: 12px;">
              <div>
                <h6 class="mb-0 fw-bold" style="font-size: 0.88rem; color: #127464;"><i class="bx bx-hide me-1 fs-5 align-middle"></i> Post Anonymously</h6>
                <small class="text-muted" style="font-size: 0.75rem;">Only your employee ID will be visible to Admin/HR.</small>
              </div>
              <div class="hitech-toggle-wrapper">
                <input class="hitech-switch-input" type="checkbox" name="is_anonymous" id="is_anonymous" value="1" {{ old('is_anonymous') ? 'checked' : '' }}>
                <label class="hitech-switch-label" for="is_anonymous"></label>
              </div>
            </div>

            <button type="submit" class="btn btn-hitech w-100 mt-2 rounded-pill px-4">
              <i class="bx bx-send me-1"></i> Submit to Innovation Hub
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- Right Side: History --}}
    <div class="col-12 col-lg-7">
      <div class="hitech-card shadow-sm h-100">
        <div class="hitech-card-header d-flex justify-content-between align-items-center">
          <h5 class="title mb-0 d-flex align-items-center gap-2" style="color: #005a5a;">
            <i class="bx bx-history fs-4" style="color: #0d9488;"></i>
            <span>Your Last Submitted Innovations</span>
          </h5>
          <span class="badge rounded-pill fw-bold" style="background: rgba(0, 90, 90, 0.15); color: #005a5a; font-size: 0.8rem;">{{ $submissions->count() }} Submissions</span>
        </div>
        <div class="card-body p-0">
          @if($submissions->count() > 0)
            <div class="table-responsive">
              <table class="table mb-0 table-hover align-middle">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th>Idea / Content</th>
                    <th>Visibility</th>
                    <th>Submitted At</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($submissions as $sub)
                  <tr>
                    <td>
                      @php
                        $badgeClass = match($sub->category) {
                          'Idea' => 'bg-label-info',
                          'Complaint' => 'bg-label-danger',
                          'Sales Pitch' => 'bg-label-warning',
                          'Seminar' => 'bg-label-success',
                          'Competitor Insights' => 'bg-label-primary',
                          default => 'bg-label-secondary',
                        };
                      @endphp
                      <span class="badge {{ $badgeClass }}">{{ $sub->category }}</span>
                    </td>
                    <td>
                      <div class="text-wrap" style="max-width: 260px; min-width: 160px; font-size: 0.85rem; line-height: 1.5; color: #4b5563;">
                        {{ \Illuminate\Support\Str::limit($sub->content, 120) }}
                        @if(strlen($sub->content) > 120)
                          <a href="javascript:void(0);" class="text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#viewModal{{ $sub->id }}">Read More</a>
                        @endif
                      </div>

                      {{-- Read More Modal --}}
                      @if(strlen($sub->content) > 120)
                      <div class="modal fade" id="viewModal{{ $sub->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title">{{ $sub->category }} Detail</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4" style="font-size: 0.95rem; line-height: 1.7; color: #374151; white-space: pre-line;">
                              {{ $sub->content }}
                            </div>
                            <div class="modal-footer border-0 bg-light py-3">
                              <small class="text-muted me-auto"><i class="bx bx-calendar me-1"></i> {{ $sub->created_at->format('d M Y, h:i A') }}</small>
                              <button type="button" class="btn btn-label-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                            </div>
                          </div>
                        </div>
                      </div>
                      @endif
                      
                      @if($sub->attachment)
                        <div class="mt-2">
                           @php
                             $ext = pathinfo($sub->attachment, PATHINFO_EXTENSION);
                             $isAudio = in_array(strtolower($ext), ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'mp4']);
                           @endphp
                           @if($isAudio)
                             <audio controls class="w-100 mt-1" style="height: 36px;">
                               <source src="{{ Storage::url($sub->attachment) }}" type="audio/{{ $ext == 'webm' ? 'webm' : 'mpeg' }}">
                               Your browser does not support the audio element.
                             </audio>
                           @else
                             <a href="{{ Storage::url($sub->attachment) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 mt-1" style="font-size: 0.75rem;">
                               <i class="bx bx-link-external me-1"></i> View Attachment
                             </a>
                           @endif
                        </div>
                      @endif

                    </td>
                    <td>
                      @if($sub->is_anonymous)
                        <span class="text-warning fw-bold small"><i class="bx bx-hide align-middle"></i> Anonymous</span>
                      @else
                        <span class="text-success fw-bold small"><i class="bx bx-show align-middle"></i> Public</span>
                      @endif
                    </td>
                    <td>
                      <span class="text-muted small fw-semibold">{{ $sub->created_at->format('d M Y') }}</span><br>
                      <small class="text-muted" style="font-size: 0.75rem;">{{ $sub->created_at->format('h:i A') }}</small>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="text-center py-6 text-muted">
              <i class="bx bx-brain fs-1 d-block mb-2 text-light" style="font-size: 4rem !important;"></i>
              <p class="mb-0 fw-semibold">No Innovations submitted yet.</p>
              <small class="text-muted">Use the form on the left to submit your first innovation!</small>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
  // Character count tracker for content textarea
  const contentTextarea = document.getElementById('content');
  const charCounter = document.getElementById('charCounter');

  if (contentTextarea && charCounter) {
    const updateCounter = () => {
      const len = contentTextarea.value.length;
      charCounter.textContent = `${len} / 2000 characters`;
      if (len > 1900) {
        charCounter.style.color = '#ef4444';
      } else {
        charCounter.style.color = '#6b7280';
      }
    };
    
    contentTextarea.addEventListener('input', updateCounter);
    // run once initially in case of old values
    updateCounter();
  }

  // --- Category Change Logic ---
  const categorySelect = document.getElementById('category');
  const contentLabel = document.getElementById('contentLabel');
  const anonymousGroup = document.getElementById('anonymousGroup');
  const isAnonymousInput = document.getElementById('is_anonymous');
  const attachmentInput = document.getElementById('attachment');
  const attachmentLabel = document.getElementById('attachmentLabel');
  const audioRecorderUI = document.getElementById('audioRecorderUI');
  const contentInput = document.getElementById('content');

  function handleCategoryChange() {
    if (!categorySelect) return;
    const cat = categorySelect.value;
    
    if (cat === 'Sales Pitch') {
      contentLabel.textContent = 'Sales Pitch';
      contentInput.placeholder = 'Write down your sales pitch notes or script here...';
      anonymousGroup.classList.add('d-none');
      isAnonymousInput.checked = false;
      
      attachmentLabel.textContent = 'Upload or Record Audio Pitch';
      attachmentLabel.innerHTML += ' <span class="text-muted fw-normal">(Optional)</span>';
      attachmentInput.accept = 'audio/*';
      attachmentInput.required = false;
      audioRecorderUI.classList.remove('d-none');
    } else if (cat === 'Seminar') {
      contentLabel.textContent = 'Seminar Description / Summary';
      contentInput.placeholder = 'Briefly describe the seminar you conducted...';
      anonymousGroup.classList.add('d-none');
      isAnonymousInput.checked = false;
      
      attachmentLabel.innerHTML = 'Upload Seminar Presentation/Document <span class="text-danger fw-bold">*Required</span>';
      attachmentInput.accept = '.pdf,.png,.jpg,.jpeg,.ppt,.pptx,.doc,.docx';
      attachmentInput.required = true;
      audioRecorderUI.classList.add('d-none');
    } else if (cat === 'Competitor Insights') {
      contentLabel.textContent = 'Competitor Insights / Market Intel';
      contentInput.placeholder = 'Describe the competitor activity, pricing changes, or new products...';
      anonymousGroup.classList.add('d-none');
      isAnonymousInput.checked = false;
      
      attachmentLabel.innerHTML = 'Upload Competitor Docs/Screenshots <span class="text-muted fw-normal">(Optional)</span>';
      attachmentInput.accept = '.pdf,.png,.jpg,.jpeg,.doc,.docx';
      attachmentInput.required = false;
      audioRecorderUI.classList.add('d-none');
    } else {
      contentLabel.textContent = 'What is on your mind?';
      contentInput.placeholder = 'Please describe your idea or complaint in detail...';
      anonymousGroup.classList.remove('d-none');
      
      attachmentLabel.textContent = 'Attachment (Optional, PDF/Image only)';
      attachmentInput.accept = '.pdf,.png,.jpg,.jpeg';
      attachmentInput.required = false;
      audioRecorderUI.classList.add('d-none');
    }
  }

  if(categorySelect) {
    categorySelect.addEventListener('change', handleCategoryChange);
    handleCategoryChange(); // init
  }

  // --- Live Audio Recorder Logic ---
  let mediaRecorder;
  let audioChunks = [];
  let recordingInterval;
  let recordingTimeSeconds = 0;
  const MAX_RECORDING_SECONDS = 600; // 10 minutes
  
  let audioCtx, analyser, dataArray, animationId;
  
  const startBtn = document.getElementById('startRecordBtn');
  const stopBtn = document.getElementById('stopRecordBtn');
  const clearBtn = document.getElementById('clearAudioBtn');
  const statusText = document.getElementById('recordingStatus');
  const playbackContainer = document.getElementById('audioPlaybackContainer');
  const audioPlayback = document.getElementById('audioPlayback');
  const timerSpan = document.getElementById('recordingTimer');
  const visualizerCanvas = document.getElementById('audioVisualizer');
  const canvasCtx = visualizerCanvas ? visualizerCanvas.getContext('2d') : null;

  function formatTime(seconds) {
    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
    const s = (seconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
  }

  function drawVisualizer() {
    if (!analyser || !canvasCtx || !visualizerCanvas) return;
    
    animationId = requestAnimationFrame(drawVisualizer);
    analyser.getByteFrequencyData(dataArray);

    canvasCtx.clearRect(0, 0, visualizerCanvas.width, visualizerCanvas.height);
    const barWidth = 3;
    let x = 0;

    for(let i = 0; i < dataArray.length; i++) {
      const barHeight = (dataArray[i] / 255) * visualizerCanvas.height;
      // Draw red bars
      canvasCtx.fillStyle = `rgb(239, 68, 68)`; 
      canvasCtx.fillRect(x, visualizerCanvas.height - barHeight, barWidth, barHeight);
      x += barWidth + 1;
      if (x > visualizerCanvas.width) break;
    }
  }

  function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      mediaRecorder.stop();
      stopBtn.classList.add('d-none');
      startBtn.classList.remove('d-none');
      startBtn.innerHTML = '<i class="bx bx-radio-circle me-1"></i> Record Again';
      statusText.classList.remove('animate__animated', 'animate__flash', 'animate__infinite');
      
      timerSpan.classList.add('d-none');
      visualizerCanvas.classList.add('d-none');
      clearInterval(recordingInterval);
      if(animationId) cancelAnimationFrame(animationId);
      if(audioCtx && audioCtx.state !== 'closed') audioCtx.close();
    }
  }

  if(startBtn && stopBtn) {
    startBtn.addEventListener('click', async () => {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];

        // Setup Visualizer
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioCtx.createAnalyser();
        const source = audioCtx.createMediaStreamSource(stream);
        source.connect(analyser);
        analyser.fftSize = 64; // small resolution for 60px width
        const bufferLength = analyser.frequencyBinCount;
        dataArray = new Uint8Array(bufferLength);
        
        visualizerCanvas.classList.remove('d-none');
        drawVisualizer();

        mediaRecorder.ondataavailable = e => {
          if (e.data.size > 0) audioChunks.push(e.data);
        };

        mediaRecorder.onstop = () => {
          const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
          const audioUrl = URL.createObjectURL(audioBlob);
          audioPlayback.src = audioUrl;
          playbackContainer.classList.remove('d-none');
          
          // Attach blob to file input using DataTransfer
          const file = new File([audioBlob], `recorded_pitch_${Date.now()}.webm`, { type: 'audio/webm', lastModified: Date.now() });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(file);
          attachmentInput.files = dataTransfer.files;

          statusText.textContent = 'Recording complete. Attached to form.';
          statusText.style.color = '#10b981';
          
          // Stop all tracks to release mic
          stream.getTracks().forEach(track => track.stop());
        };

        mediaRecorder.start();
        startBtn.classList.add('d-none');
        stopBtn.classList.remove('d-none');
        statusText.textContent = 'Recording in progress...';
        statusText.style.color = '#ef4444';
        statusText.classList.add('animate__animated', 'animate__flash', 'animate__infinite');
        
        // Setup Timer & 10-minute limit
        recordingTimeSeconds = 0;
        timerSpan.textContent = '00:00';
        timerSpan.classList.remove('d-none');
        
        recordingInterval = setInterval(() => {
          recordingTimeSeconds++;
          timerSpan.textContent = formatTime(recordingTimeSeconds);
          
          if (recordingTimeSeconds >= MAX_RECORDING_SECONDS) {
            stopRecording();
            statusText.textContent = 'Time limit reached (10 min). Attached to form.';
          }
        }, 1000);

      } catch (err) {
        console.error('Error accessing mic:', err);
        alert('Microphone access denied or not available.');
      }
    });

    stopBtn.addEventListener('click', stopRecording);

    clearBtn.addEventListener('click', () => {
      audioPlayback.src = '';
      playbackContainer.classList.add('d-none');
      attachmentInput.value = ''; // clear file input
      statusText.textContent = 'Ready to record.';
      statusText.style.color = '#6b7280';
      startBtn.innerHTML = '<i class="bx bx-radio-circle me-1"></i> Start';
    });
  }

  // Auto-dismiss alerts after 4s
  document.querySelectorAll('.ms-alert').forEach(el => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(el);
      bsAlert.close();
    }, 4000);
  });
</script>
@endsection
