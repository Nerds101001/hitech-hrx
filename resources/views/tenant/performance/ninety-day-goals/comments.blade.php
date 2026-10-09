@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Comments')

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">Comments written with a weekly status and comments added afterwards by the salesperson or an admin, newest first.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <form method="GET" action="{{ route('performance.ninety-day-goals.comments') }}" class="row g-3 align-items-end">
        @if($seesMany)
        <div class="col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salesperson</label>
          <select name="user_id" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">Everyone</option>
            @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
          </select>
        </div>
        @endif
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
          <select name="status" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">Any</option>
            @foreach($statuses as $s)<option value="{{ $s }}" {{ $filters['status'] === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-center pb-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="late" value="1" id="lateOnly" {{ $filters['late'] ? 'checked' : '' }} onchange="this.form.submit()">
            <label class="form-check-label small fw-semibold" for="lateOnly">Only statuses sent late</label>
          </div>
        </div>
        <div class="col-md-2"><a href="{{ route('performance.ninety-day-goals.comments') }}" class="btn btn-hitech-cancel w-100 rounded-pill">Clear</a></div>
      </form>
    </div>
  </div>

  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom fw-bold text-dark">{{ $comments->count() }} {{ \Illuminate\Support\Str::plural('comment', $comments->count()) }}</div>
    @forelse($comments as $c)
      @php
        $g = $c->goal;
        [$bg, $fg] = $G::STATUS_COLORS[$c->status] ?? ['#e2e8f0', '#475569'];
      @endphp
      <div class="ndg-cm">
        <div class="ndg-cm-head">
          @if($c->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $c->status }}</span>@endif
          <span class="fw-bold text-dark">{{ $g->client_name }}</span>
          <span class="text-muted small">{{ $g->product }}@if($g->brand) · {{ $g->brand }}@endif</span>
          <span class="ms-auto small text-muted">Week {{ $c->week_no + 1 }}@if($c->due_on) · due {{ $c->due_on->format('D d M') }}@endif</span>
        </div>
        <div class="ndg-cm-body">{{ $c->text }}</div>
        <div class="ndg-cm-foot small text-muted">
          <b class="text-dark">{{ $name($c->by) }}</b>
          @if($c->kind === 'note') added a comment @else wrote this with the status @endif
          · {{ optional($c->at)->format('d M Y, h:i A') }}
          @if($seesMany) · {{ $name($g->user) }}'s customer, {{ optional(optional($g->user)->department)->name }}@endif
          @if($c->kind === 'status' && $c->is_late)<span class="text-danger fw-bold">· sent late</span>@endif
        </div>
      </div>
    @empty
      <div class="card-body p-5 text-center text-muted">
        No comments yet. A comment is written whenever someone gives a weekly status, and is required when a Monday is answered late.
      </div>
    @endforelse
  </div>

  </div>
</div>

<style>
  .ndg-cm { padding: 16px 24px; border-bottom: 1px solid #eef2f5; }
  .ndg-cm:last-child { border-bottom: 0; }
  .ndg-cm-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 8px; }
  .ndg-cm-body { color: #1e293b; line-height: 1.55; white-space: pre-line; max-width: 900px; }
  .ndg-cm-foot { margin-top: 8px; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .72rem; font-weight: 700; }
</style>
@endsection
