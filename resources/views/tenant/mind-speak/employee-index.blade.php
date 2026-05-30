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
      <h4 class="greeting">Mind Speak Portal</h4>
      <p class="sub-text">Share your ideas, suggestions, complaints, or improvement plans. Make your voice heard, openly or anonymously.</p>
    </div>
  </div>

  <div class="row g-4">
    {{-- Left Side: Submission Form --}}
    <div class="col-12 col-lg-5">
      <div class="hitech-card mb-6 shadow-sm">
        <div class="hitech-card-header">
          <h5 class="title mb-0 d-flex align-items-center gap-2" style="color: #005a5a;">
            <i class="bx bx-pen fs-4" style="color: #0d9488;"></i>
            <span>Submit New Idea / Suggestion</span>
          </h5>
        </div>
        <div class="card-body p-4">
          <form action="{{ route('mind-speak.store') }}" method="POST" id="mindSpeakForm">
            @csrf
            
            <div class="mb-4">
              <label class="hitech-label" for="category">Category</label>
              <select name="category" id="category" class="form-select-hitech form-select @error('category') is-invalid @enderror" required>
                <option value="" disabled selected>Select Category...</option>
                <option value="Suggestion" {{ old('category') == 'Suggestion' ? 'selected' : '' }}>💡 Suggestion</option>
                <option value="Complaint" {{ old('category') == 'Complaint' ? 'selected' : '' }}>⚠️ Complaint</option>
                <option value="Improvement" {{ old('category') == 'Improvement' ? 'selected' : '' }}>🚀 Improvement</option>
                <option value="Feedback" {{ old('category') == 'Feedback' ? 'selected' : '' }}>💬 Feedback</option>
                <option value="Other" {{ old('category') == 'Other' ? 'selected' : '' }}>📋 Other</option>
              </select>
              @error('category')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-4">
              <label class="hitech-label" for="content">What is on your mind?</label>
              <textarea name="content" id="content" rows="6" class="form-control-hitech form-control @error('content') is-invalid @enderror" 
                        placeholder="Please describe your idea or complaint in detail..." required minlength="5" maxlength="2000">{{ old('content') }}</textarea>
              <div class="d-flex justify-content-between align-items-center mt-1">
                @error('content')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <span class="char-counter ms-auto" id="charCounter">0 / 2000 characters</span>
              </div>
            </div>

            <div class="mb-4 p-4 d-flex align-items-center justify-content-between" style="background: rgba(18, 116, 100, 0.04); border: 1px solid rgba(18, 116, 100, 0.15); border-radius: 12px;">
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
              <i class="bx bx-send me-1"></i> Submit Mind Speak
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
            <span>Your Last Submitted Ideas</span>
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
                          'Suggestion' => 'bg-label-info',
                          'Complaint' => 'bg-label-danger',
                          'Improvement' => 'bg-label-success',
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
                              <h5 class="modal-title">{{ $sub->category }} Idea Detail</h5>
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
              <p class="mb-0 fw-semibold">No Mind Speak submissions yet.</p>
              <small class="text-muted">Use the form on the left to submit your first suggestion!</small>
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

  // Auto-dismiss alerts after 4s
  document.querySelectorAll('.ms-alert').forEach(el => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(el);
      bsAlert.close();
    }, 4000);
  });
</script>
@endsection
