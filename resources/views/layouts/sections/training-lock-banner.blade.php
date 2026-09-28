{{-- Top banner for new joiners whose account is held until they pass their
     gating training (App\Support\TrainingLock). $lock = ['passed', 'total']. --}}
@php
  $pct = $lock['total'] > 0 ? (int) round($lock['passed'] * 100 / $lock['total']) : 0;
  $left = max(0, $lock['total'] - $lock['passed']);
@endphp
<div class="alert alert-warning hitech-note-card animate__animated animate__fadeIn {{ $bannerClass ?? 'm-4' }}" role="alert">
  <div class="d-flex align-items-center flex-wrap gap-3">
    <i class="bx bx-lock-alt fs-3"></i>
    <div class="flex-grow-1" style="min-width: 220px;">
      <strong>Complete your training and assessments to unlock your account.</strong>
      <div class="small mt-1">
        Attendance, leave and the rest of HRX open automatically once all
        {{ $lock['total'] }} required modules are passed &mdash;
        <strong>{{ $lock['passed'] }} of {{ $lock['total'] }} done</strong>, {{ $left }} to go.
      </div>
      <div class="progress mt-2" style="height: 6px; max-width: 420px;">
        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $pct }}%"
             aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
      </div>
    </div>
    @unless(request()->routeIs('training.portal'))
      <a href="{{ route('training.portal') }}" class="btn btn-sm btn-warning ms-auto">Continue training</a>
    @endunless
  </div>
</div>
