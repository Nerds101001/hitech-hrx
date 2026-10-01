@extends('layouts/blankLayout')

@section('title', $file->title . ' — Training')

@section('vendor-style')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
@endsection

@section('page-style')
<style>
  body { background: #0d1117; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
  .lv-wrap { max-width: 1100px; margin: 0 auto; padding: 18px 16px 40px; color: #e6edf3; }
  .lv-top { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
  .lv-back { color: #8b949e; text-decoration: none; font-size: .85rem; display: inline-flex; align-items: center; gap: 4px; }
  .lv-back:hover { color: #58a6ff; }
  .lv-title { font-size: 1.15rem; font-weight: 700; margin: 0; flex: 1; }
  .lv-player { position: relative; width: 100%; aspect-ratio: 16/9; background: #000; border-radius: 12px; overflow: hidden; border: 1px solid #30363d; }
  .lv-player iframe, .lv-player video { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
  .lv-missing { display: flex; align-items: center; justify-content: center; height: 100%; color: #8b949e; font-size: .9rem; text-align: center; padding: 20px; }
  .lv-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 14px; padding: 14px 16px; background: #161b22; border: 1px solid #30363d; border-radius: 12px; }
  .lv-timer { font-variant-numeric: tabular-nums; font-weight: 700; color: #58a6ff; }
  .lv-note { color: #8b949e; font-size: .82rem; flex: 1; min-width: 220px; }
  .lv-btn { border: 0; border-radius: 999px; padding: 9px 20px; font-weight: 700; font-size: .85rem; background: #238636; color: #fff; cursor: pointer; }
  .lv-btn:disabled { background: #30363d; color: #8b949e; cursor: not-allowed; }
  .lv-done { color: #3fb950; font-weight: 700; }
  .lv-progress { font-size: .8rem; color: #8b949e; }
  .lv-msg { margin-top: 10px; font-size: .85rem; }
  .lv-shield { position: absolute; top: 0; right: 0; width: 96px; height: 72px; z-index: 3; background: transparent; cursor: default; }
  .lv-player .sv-frame { position: absolute; inset: 0; height: 100%; aspect-ratio: auto; }
</style>
@endsection

@section('content')
<div class="lv-wrap">
  <div class="lv-top">
    <a class="lv-back" href="{{ route('training.portal') }}"><i class="ti ti-arrow-left"></i> Training</a>
    <h1 class="lv-title">{{ $file->title }}</h1>
  </div>

  @php
    // YouTube plays through the protected player (only the id reaches the page).
    $lvYtId = (!$isFile && $src && preg_match('~youtube(?:-nocookie)?\.com/embed/([a-zA-Z0-9_-]{6,})~', $src, $lvm)) ? $lvm[1] : null;
  @endphp
  {{-- No right-click anywhere on the player: "Copy video URL" lives there. --}}
  <div class="lv-player" id="lvPlayer" oncontextmenu="return false;">
    @if($src && $isFile)
      <video src="{{ $src }}" controls controlsList="nodownload noremoteplayback" disablePictureInPicture oncontextmenu="return false;"></video>
    @elseif($lvYtId)
      <div id="lvYt"></div>
    @elseif($src)
      <iframe src="{{ $src }}" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>
      {{-- Google Drive's preview has an "open in new window" button in its top
           corner that leads to the file's own page and link. Nothing here can
           switch it off inside Drive's frame; this shield swallows the click. --}}
      <div class="lv-shield" title=""></div>
    @else
      <div class="lv-missing">This session's recording link is missing or not playable. Please tell HR.</div>
    @endif
  </div>

  <div class="lv-bar">
    @if($view->completed_at)
      <span class="lv-done"><i class="ti ti-check"></i> Watched</span>
      <span class="lv-note">Already counted towards the Learn &#64; Hi Tech phase.</span>
    @else
      <span class="lv-timer" id="lvTimer">--:--</span>
      <span class="lv-note">Watch for at least {{ $minMins }} minutes, then mark it as watched.</span>
      <button type="button" class="lv-btn" id="lvMark" disabled>Mark as watched</button>
    @endif
    <span class="lv-progress" id="lvProgress">{{ min($watched, $required) }} of {{ $required }} watched for this phase</span>
  </div>
  <div class="lv-msg" id="lvMsg"></div>
</div>

@if($lvYtId)
  @include('tenant.training.partials.secure-youtube')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      HrxSecureYT.mount(document.getElementById('lvYt'), @json($lvYtId), { fsTarget: document.getElementById('lvPlayer') });
    });
  </script>
@endif

@unless($view->completed_at)
<script>
(function () {
  // The server enforces the same minimum; this countdown only shows it.
  let left = {{ (int) $secsLeft }};
  const timer = document.getElementById('lvTimer');
  const btn = document.getElementById('lvMark');
  const msg = document.getElementById('lvMsg');
  const fmt = s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
  function tick() {
    if (left <= 0) { timer.textContent = 'Done'; btn.disabled = false; return; }
    timer.textContent = fmt(left); left--; setTimeout(tick, 1000);
  }
  tick();
  btn.addEventListener('click', async function () {
    btn.disabled = true;
    try {
      const r = await fetch(@json(route('training.library-video.watched', $file->id)), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
      });
      const d = await r.json();
      msg.textContent = d.message || (d.success ? 'Watched.' : 'Could not save - please try again.');
      msg.style.color = d.success ? '#3fb950' : '#f0883e';
      if (d.success) {
        timer.textContent = 'Done';
        btn.textContent = 'Watched';
        document.getElementById('lvProgress').textContent = Math.min(d.watched, d.required) + ' of ' + d.required + ' watched for this phase';
      } else {
        if (d.seconds_left) { left = d.seconds_left; tick(); }
        btn.disabled = left > 0;
      }
    } catch (e) {
      msg.textContent = 'Network error - please try again.'; msg.style.color = '#f0883e'; btn.disabled = false;
    }
  });
})();
</script>
@endunless
@endsection
