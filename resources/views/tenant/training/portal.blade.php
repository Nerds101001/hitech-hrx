@extends('layouts/blankLayout')

@section('title', 'Training Academy — HiTech HRX')

@section('vendor-style')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
@endsection

@section('page-style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #f0f4f8; }

/* ══════════════════════════════════════════
   TOP NAV
══════════════════════════════════════════ */
.lms-nav {
    position: sticky; top: 0; z-index: 1000;
    background: #0a0f1e;
    height: 56px;
    display: flex; align-items: center;
    padding: 0 24px;
    gap: 16px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.3);
}
.lms-nav-brand {
    display: flex; align-items: center; gap: 10px;
    text-decoration: none;
}
.lms-nav-brand-meta { display: flex; flex-direction: column; justify-content: center; line-height: 1.1; }
.lms-nav-title { font-size: 0.88rem; font-weight: 700; color: white; letter-spacing: 0.2px; display: block; }
.lms-nav-slogan { font-size: 0.6rem; font-weight: 600; color: rgba(255,255,255,0.45); letter-spacing: 1px; text-transform: uppercase; display: block; }
.lms-nav-sep { width: 1px; height: 20px; background: rgba(255,255,255,0.12); }
.lms-nav-course { font-size: 0.82rem; color: rgba(255,255,255,0.55); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 300px; }
.lms-nav-right { margin-left: auto; display: flex; align-items: center; gap: 14px; }
.lms-nav-pct {
    display: flex; align-items: center; gap: 7px;
    font-size: 0.78rem; color: rgba(255,255,255,0.7);
}
.lms-nav-pct-bar {
    width: 80px; height: 4px; background: rgba(255,255,255,0.15); border-radius: 10px; overflow: hidden;
}
.lms-nav-pct-fill { height: 100%; background: #06b6d4; border-radius: 10px; transition: width 1s ease; }
.lms-nav-back {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.78rem; color: rgba(255,255,255,0.55);
    text-decoration: none;
    padding: 5px 10px; border-radius: 8px;
    transition: all 0.15s;
    border: 1px solid rgba(255,255,255,0.1);
}
.lms-nav-back:hover { color: white; background: rgba(255,255,255,0.08); }
.nav-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, #06b6d4, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; font-weight: 700; color: white;
    flex-shrink: 0;
}

/* ══════════════════════════════════════════
   HERO
══════════════════════════════════════════ */
.course-hero {
    background: linear-gradient(135deg, #0a0f1e 0%, #0f2040 40%, #0e3461 70%, #1a4b7c 100%);
    padding: 48px 0 0;
    position: relative;
    overflow: hidden;
}
.hero-deco-1 {
    position: absolute; top: -80px; right: -60px;
    width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(6,182,212,0.12) 0%, transparent 70%);
    pointer-events: none;
}
.hero-deco-2 {
    position: absolute; bottom: 40px; left: -40px;
    width: 280px; height: 280px; border-radius: 50%;
    background: radial-gradient(circle, rgba(139,92,246,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.hero-inner { max-width: 1200px; margin: 0 auto; padding: 0 32px 40px; }
.hero-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(6,182,212,0.15);
    border: 1px solid rgba(6,182,212,0.3);
    color: #67e8f9; font-size: 0.72rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    padding: 5px 12px; border-radius: 20px;
    margin-bottom: 14px;
}
.hero-title { font-size: 2rem; font-weight: 900; color: white; line-height: 1.2; margin-bottom: 8px; }
.hero-sub { font-size: 0.95rem; color: rgba(255,255,255,0.55); margin-bottom: 24px; max-width: 480px; }
.hero-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
.hero-meta-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.8rem; color: rgba(255,255,255,0.65);
}
.hero-meta-item i { color: #06b6d4; font-size: 0.9rem; }
.hero-progress-label {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: rgba(255,255,255,0.45);
    margin-bottom: 7px;
}
.hero-progress-track {
    height: 6px; background: rgba(255,255,255,0.12);
    border-radius: 10px; overflow: hidden; max-width: 400px;
    margin-bottom: 6px;
}
.hero-progress-fill {
    height: 100%; border-radius: 10px;
    background: linear-gradient(90deg, #06b6d4, #8b5cf6);
    transition: width 1s ease;
}
.hero-progress-text { font-size: 0.78rem; color: rgba(255,255,255,0.5); margin-bottom: 24px; }
.hero-cta {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #06b6d4, #0284c7);
    color: white; font-size: 0.9rem; font-weight: 700;
    padding: 12px 28px; border-radius: 50px;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(6,182,212,0.35);
    transition: all 0.2s ease;
}
.hero-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(6,182,212,0.45);
    color: white;
}
.hero-ring-wrap {
    display: flex; flex-direction: column; align-items: center; gap: 12px;
    flex-shrink: 0;
}
.ring-container { position: relative; width: 120px; height: 120px; }
.ring-container svg { transform: rotate(-90deg); }
.ring-center {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.ring-pct { font-size: 1.8rem; font-weight: 900; color: white; line-height: 1; }
.ring-label { font-size: 0.6rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; }
.ring-subtext { font-size: 0.78rem; color: rgba(255,255,255,0.55); text-align: center; }

/* Hero wave SVG separator */
.hero-wave { display: block; margin-top: -2px; }

/* ══════════════════════════════════════════
   MAIN CONTENT AREA
══════════════════════════════════════════ */
.lms-body {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px 24px 80px;
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 28px;
    align-items: start;
}
@media (max-width: 991px) {
    .lms-body { grid-template-columns: 1fr; }
    .progress-sidebar { order: -1; }
}

/* ══════════════════════════════════════════
   PHASE / CURRICULUM CARDS
══════════════════════════════════════════ */
.curriculum-section { display: flex; flex-direction: column; gap: 16px; }

.phase-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid #e8edf2;
    transition: box-shadow 0.2s;
}
.phase-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.09); }
.phase-card.locked-card {
    background: #fafbfc;
    border-color: #dde3ea;
    opacity: 0.8;
}

.phase-header {
    display: flex; align-items: center; gap: 16px;
    padding: 18px 22px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}
.phase-header:hover { background: rgba(0,0,0,0.015); }

.phase-num {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 1.05rem; color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.pn-1 { background: linear-gradient(135deg, #0891b2, #06b6d4); }
.pn-2 { background: linear-gradient(135deg, #2563eb, #3b82f6); }
.pn-3 { background: linear-gradient(135deg, #7c3aed, #a855f7); }
.pn-4 { background: linear-gradient(135deg, #ea580c, #f97316); }
.pn-5 { background: linear-gradient(135deg, #16a34a, #22c55e); }
.pn-locked { background: linear-gradient(135deg, #94a3b8, #cbd5e1); }

.phase-info { flex: 1; min-width: 0; }
.phase-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 2px; }
.phase-name { font-size: 1rem; font-weight: 800; color: #0f172a; line-height: 1.25; }
.phase-desc { font-size: 0.78rem; color: #64748b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.phase-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.phase-pill {
    padding: 4px 12px; border-radius: 20px;
    font-size: 0.68rem; font-weight: 700;
    white-space: nowrap;
}
.pill-done     { background: #d1fae5; color: #065f46; }
.pill-partial  { background: #fef3c7; color: #92400e; }
.pill-empty    { background: #f1f5f9; color: #64748b; }
.pill-locked   { background: #f1f5f9; color: #94a3b8; }
.pill-complete { background: #d1fae5; color: #065f46; }

.phase-chevron {
    color: #94a3b8; font-size: 1rem;
    transition: transform 0.25s ease;
}
.phase-chevron.open { transform: rotate(180deg); }

.phase-progress-bar {
    height: 3px;
    background: #f1f5f9;
    overflow: hidden;
}
.phase-progress-fill-bar {
    height: 100%;
    transition: width 0.7s ease;
}
.pb-1 { background: linear-gradient(90deg, #0891b2, #06b6d4); }
.pb-2 { background: linear-gradient(90deg, #2563eb, #3b82f6); }
.pb-3 { background: linear-gradient(90deg, #7c3aed, #a855f7); }
.pb-4 { background: linear-gradient(90deg, #ea580c, #f97316); }
.pb-5 { background: linear-gradient(90deg, #16a34a, #22c55e); }
.pb-locked { background: #e2e8f0; }

/* Locked phase overlay message */
.phase-locked-msg {
    padding: 20px 22px;
    display: flex; align-items: center; gap: 14px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
}
.locked-icon-wrap {
    width: 42px; height: 42px; border-radius: 12px;
    background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.phase-locked-msg p { font-size: 0.82rem; color: #64748b; margin: 0; line-height: 1.5; }
.phase-locked-msg strong { color: #0f172a; }

/* Module items */
.phase-modules { border-top: 1px solid #f1f5f9; }

.module-item {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 22px;
    border-bottom: 1px solid #f8fafc;
    text-decoration: none;
    color: #0f172a;
    transition: background 0.15s;
    position: relative;
}
.module-item:last-child { border-bottom: none; }
.module-item:hover:not(.mod-locked) { background: #f8fcff; }
.module-item.mod-locked { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
.module-item.mod-active {
    background: linear-gradient(90deg, #f0fdff, #faffff);
    border-left: 3px solid #06b6d4;
    padding-left: 19px;
}
.module-item.mod-completed { background: #fafffe; }
.module-item.mod-failed { background: #fff8f8; border-left: 3px solid #ef4444; padding-left: 19px; }

/* Module number bubble */
.mod-num {
    width: 28px; height: 28px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; font-weight: 800;
    flex-shrink: 0;
    background: #f1f5f9; color: #64748b;
}
.mod-completed .mod-num { background: #d1fae5; color: #059669; }
.mod-active .mod-num    { background: rgba(6,182,212,0.15); color: #0891b2; }
.mod-failed .mod-num    { background: #fee2e2; color: #dc2626; }

/* Module type icon box */
.mod-type-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 1rem;
}
.mti-video  { background: #fffbeb; }
.mti-catalog{ background: #eff6ff; }
.mti-policy { background: #f0fdf4; }

/* Module text */
.mod-text { flex: 1; min-width: 0; }
.mod-title { font-size: 0.88rem; font-weight: 700; color: #1e293b; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mod-meta  { display: flex; align-items: center; gap: 10px; margin-top: 3px; flex-wrap: wrap; }
.mod-meta-chip { display: inline-flex; align-items: center; gap: 3px; font-size: 0.67rem; color: #94a3b8; }

/* Module status right side */
.mod-status-area { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.mod-score-badge { font-size: 0.7rem; font-weight: 700; color: #059669; }
.mod-status-icon {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; flex-shrink: 0;
}
.msi-done  { background: #d1fae5; color: #059669; }
.msi-prog  { background: #fef3c7; color: #d97706; }
.msi-lock  { background: #f1f5f9; color: #94a3b8; }
.msi-fail  { background: #fee2e2; color: #dc2626; }
.msi-avail { background: rgba(6,182,212,0.15); color: #0891b2; }

.start-badge {
    display: inline-block;
    background: linear-gradient(135deg, #06b6d4, #0284c7);
    color: white;
    font-size: 0.6rem; font-weight: 800;
    letter-spacing: 0.8px; text-transform: uppercase;
    padding: 3px 8px; border-radius: 20px;
    animation: pulse-glow 1.8s ease-in-out infinite;
}
@keyframes pulse-glow {
    0%,100% { box-shadow: 0 0 0 0 rgba(6,182,212,0.4); }
    50%      { box-shadow: 0 0 0 6px rgba(6,182,212,0); }
}
.retry-badge { background: linear-gradient(135deg, #ef4444, #dc2626); }
.resume-badge { background: linear-gradient(135deg, #f59e0b, #d97706); }

/* ══════════════════════════════════════════
   PROGRESS SIDEBAR
══════════════════════════════════════════ */
.progress-sidebar { position: sticky; top: 72px; display: flex; flex-direction: column; gap: 16px; }

.progress-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    border: 1px solid #e8edf2;
}
.progress-card-header {
    background: linear-gradient(135deg, #0a0f1e, #0f2040);
    padding: 24px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.progress-card-header::before {
    content: '';
    position: absolute; top: -30px; right: -30px;
    width: 120px; height: 120px; border-radius: 50%;
    background: rgba(6,182,212,0.1);
    pointer-events: none;
}
.pc-ring { position: relative; display: inline-flex; margin-bottom: 10px; }
.pc-ring svg { transform: rotate(-90deg); }
.pc-ring-text {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.pc-pct { font-size: 1.6rem; font-weight: 900; color: white; line-height: 1; }
.pc-pct-label { font-size: 0.55rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; }
.pc-title { font-size: 0.95rem; font-weight: 800; color: white; }
.pc-subtitle { font-size: 0.75rem; color: rgba(255,255,255,0.5); }

.progress-card-body { padding: 20px; }

.pc-stat-row { display: flex; gap: 8px; margin-bottom: 16px; }
.pc-stat {
    flex: 1;
    background: #f8fafc;
    border-radius: 10px;
    padding: 10px 12px;
    text-align: center;
}
.pc-stat-val { font-size: 1.1rem; font-weight: 800; color: #0f172a; }
.pc-stat-lbl { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }

.pc-cta {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    background: linear-gradient(135deg, #06b6d4, #0284c7);
    color: white; font-size: 0.88rem; font-weight: 700;
    padding: 12px; border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s;
    margin-bottom: 16px;
    box-shadow: 0 4px 14px rgba(6,182,212,0.3);
}
.pc-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(6,182,212,0.4); color: white; }

.pc-phase-list { display: flex; flex-direction: column; gap: 8px; }
.pc-phase-row {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
}
.pc-phase-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.pc-phase-info { flex: 1; min-width: 0; }
.pc-phase-name { font-size: 0.75rem; font-weight: 700; color: #1e293b; }
.pc-phase-mini-bar { height: 3px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin-top: 3px; }
.pc-phase-mini-fill { height: 100%; border-radius: 10px; }
.pc-phase-count { font-size: 0.68rem; font-weight: 700; color: #94a3b8; flex-shrink: 0; }

/* Completed banner */
.complete-banner {
    background: linear-gradient(135deg, #064e3b, #065f46, #047857);
    border-radius: 16px;
    padding: 28px;
    text-align: center;
    color: white;
    box-shadow: 0 8px 24px rgba(4,120,87,0.25);
}

/* Alert */
.lms-alert {
    max-width: 1200px; margin: 12px auto 0; padding: 0 24px;
}
.alert-inner {
    background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444;
    border-radius: 10px; padding: 12px 16px;
    display: flex; align-items: center; gap: 10px;
    font-size: 0.85rem; color: #991b1b;
}

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */
@media (max-width: 767px) {
    .hero-inner { padding: 0 16px 32px; }
    .hero-title { font-size: 1.4rem; }
    .hero-ring-wrap { display: none; }
    .lms-body { padding: 20px 16px 60px; }
    .phase-header { padding: 14px 16px; }
    .module-item { padding: 12px 16px; }
    .lms-nav-course { display: none; }
}
</style>
@endsection

@section('content')

{{-- ════════════════════════════════════════════
     TOP NAV
════════════════════════════════════════════ --}}
<nav class="lms-nav">
    <a href="{{ route('training.portal') }}" class="lms-nav-brand">
        <img src="{{ asset('assets/img/logo.png') }}" alt="HRX Logo" style="height:34px;width:auto;object-fit:contain;flex-shrink:0;">
        <div class="lms-nav-brand-meta d-none d-md-block">
            <span class="lms-nav-title">Hi Tech <span style="color:#06b6d4;font-weight:800;">HRX</span></span>
            <span class="lms-nav-slogan">NEXT-GEN HRMS</span>
        </div>
    </a>
    <div class="lms-nav-sep d-none d-md-block"></div>
    <span class="lms-nav-course d-none d-md-block">Employee Training Program</span>
    <div class="lms-nav-right">
        <div class="lms-nav-pct d-none d-sm-flex">
            <span class="fw-bold" style="color:white;">{{ $progressPercentage }}%</span>
            <div class="lms-nav-pct-bar">
                <div class="lms-nav-pct-fill" style="width:{{ $progressPercentage }}%"></div>
            </div>
        </div>
        @if(!auth()->user()->hasRole(['admin','hr','manager','accounts']) || request()->has('user_id'))
        <a href="{{ url('/') }}" class="lms-nav-back">
            <i class="ti ti-layout-dashboard" style="font-size:0.8rem;"></i>
            <span class="d-none d-sm-inline">Dashboard</span>
        </a>
        @else
        <a href="{{ route('training.activity') }}" class="lms-nav-back">
            <i class="ti ti-chart-bar" style="font-size:0.8rem;"></i>
            <span class="d-none d-sm-inline">Activity</span>
        </a>
        @endif
        <div class="dropdown">
            <div class="nav-avatar" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor:pointer;" title="Account options">
                {{ strtoupper(substr($user->first_name ?? $user->name, 0, 2)) }}
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius:12px; margin-top:8px; min-width:160px;">
                <li><span class="dropdown-item-text text-muted small px-3 pt-2 pb-1 d-block">{{ $user->first_name ?? $user->name }}</span></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item text-danger fw-semibold d-flex align-items-center gap-2" href="{{ route('auth.logout') }}"
                       onclick="event.preventDefault(); document.getElementById('training-logout-form').submit();">
                        <i class="ti ti-logout"></i> Logout
                    </a>
                </li>
            </ul>
            <form id="training-logout-form" method="POST" action="{{ route('auth.logout') }}" style="display:none;">@csrf</form>
        </div>
    </div>
</nav>

{{-- Flash error --}}
@if(session('error'))
<div class="lms-alert">
    <div class="alert-inner">
        <i class="ti ti-alert-circle" style="font-size:1.1rem;flex-shrink:0;"></i>
        {{ session('error') }}
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════
     COMPUTE DATA
════════════════════════════════════════════ --}}
@php
    $totalMods    = $phases->sum(fn($p) => $p->modules->count());
    $doneMods     = 0;
    $sumScore     = 0;
    $scoreCount   = 0;
    $hasAnyProgress = false;
    foreach ($phases as $ph) {
        foreach ($ph->modules as $mod) {
            $prog = $mod->userProgress->first();
            if ($prog && $prog->status === 'completed') {
                $doneMods++;
                if ($prog->assessment_score > 0) { $sumScore += $prog->assessment_score; $scoreCount++; }
            }
            if ($prog) $hasAnyProgress = true;
        }
    }
    $avgScore = $scoreCount > 0 ? round($sumScore / $scoreCount) : 0;
    $estTotalMins = $phases->sum(fn($p) => $p->modules->sum('estimated_time_minutes'));

    // Find next module
    $nextModId   = null;
    $nextModName = null;
    $outerBreak  = false;
    foreach ($phases as $phaseIdx => $ph) {
        if ($outerBreak) break;

        $isPhaseAccessible = true;
        if ($phaseIdx > 0) {
            $prevPhase = $phases[$phaseIdx - 1];
            $prevDone  = $prevPhase->modules->filter(fn($m) => optional($m->userProgress->first())->status === 'completed')->count();
            if ($prevPhase->modules->count() > 0 && $prevDone < $prevPhase->modules->count()) {
                $isPhaseAccessible = false;
            }
        }

        foreach ($ph->modules as $mod) {
            $prog = $mod->userProgress->first();
            $st   = $prog ? $prog->status : 'locked';

            if ($st === 'locked' && $isPhaseAccessible) {
                $hasIncompleteLower = $ph->modules->filter(function($m) use ($mod) {
                    return $m->order < $mod->order && optional($m->userProgress->first())->status !== 'completed';
                })->isNotEmpty();
                if (!$hasIncompleteLower) {
                    $st = 'available';
                }
            }

            if (in_array($st, ['available','in_progress','failed'])) {
                $nextModId   = $mod->id;
                $nextModName = $mod->title;
                $outerBreak  = true;
                break;
            }
        }
    }
    if (!$hasAnyProgress && $phases->isNotEmpty() && $phases->first()->modules->isNotEmpty()) {
        $nextModId   = $phases->first()->modules->first()->id;
        $nextModName = $phases->first()->modules->first()->title;
    }
@endphp

{{-- ════════════════════════════════════════════
     HERO
════════════════════════════════════════════ --}}
<div class="course-hero">
    <div class="hero-deco-1"></div>
    <div class="hero-deco-2"></div>
    <div class="hero-inner">
        <div class="d-flex align-items-start justify-content-between gap-4">
            <div style="flex:1;min-width:0;">
                <div class="hero-badge animate__animated animate__fadeInDown">
                    <i class="ti ti-certificate" style="font-size:0.8rem;"></i>
                    Employee Training Program
                </div>
                <h1 class="hero-title animate__animated animate__fadeInUp">
                    @if($user->id === auth()->id())
                        Your Learning Journey
                    @else
                        {{ $user->name }}'s Training
                    @endif
                </h1>
                <p class="hero-sub">Master your role through structured phases — each building on the last.</p>
                <div class="hero-meta">
                    <span class="hero-meta-item"><i class="ti ti-layers"></i> {{ $phases->count() }} Phases</span>
                    <span class="hero-meta-item"><i class="ti ti-books"></i> {{ $totalMods }} Modules</span>
                    <span class="hero-meta-item"><i class="ti ti-clock"></i> ~{{ round($estTotalMins/60, 1) }}h total</span>
                    @if($avgScore > 0)
                    <span class="hero-meta-item"><i class="ti ti-star"></i> {{ $avgScore }}% avg score</span>
                    @endif
                </div>
                <div class="hero-progress-label">Overall Progress</div>
                <div class="hero-progress-track">
                    <div class="hero-progress-fill" style="width:{{ $progressPercentage }}%"></div>
                </div>
                <div class="hero-progress-text">{{ $doneMods }} of {{ $totalMods }} modules complete</div>
                @if($nextModId && $user->training_status !== 'completed' && $user->id === auth()->id())
                <a href="{{ route('training.module.show', $nextModId) }}" class="hero-cta animate__animated animate__fadeInUp" style="animation-delay:0.15s;">
                    {{ $hasAnyProgress ? 'Continue Learning' : 'Start Learning' }}
                    <i class="ti ti-arrow-right"></i>
                </a>
                @elseif($user->training_status === 'completed')
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;padding:10px 20px;border-radius:50px;font-size:0.88rem;font-weight:700;">
                    <i class="ti ti-trophy"></i> Training Complete!
                </div>
                @endif
            </div>

            {{-- Progress ring --}}
            <div class="hero-ring-wrap animate__animated animate__fadeIn d-none d-lg-flex">
                @php $c = round(2 * M_PI * 48, 2); @endphp
                <div class="ring-container">
                    <svg width="120" height="120" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="48" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="8"/>
                        <circle cx="60" cy="60" r="48" fill="none"
                            stroke="url(#heroGrad)" stroke-width="8"
                            stroke-dasharray="{{ $c }}"
                            stroke-dashoffset="{{ round($c * (1 - $progressPercentage/100), 2) }}"
                            stroke-linecap="round"
                            style="transition:stroke-dashoffset 1.5s ease;"/>
                        <defs>
                            <linearGradient id="heroGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#06b6d4"/>
                                <stop offset="100%" stop-color="#8b5cf6"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="ring-center">
                        <span class="ring-pct">{{ $progressPercentage }}%</span>
                        <span class="ring-label">Done</span>
                    </div>
                </div>
                <div class="ring-subtext">{{ $doneMods }}/{{ $totalMods }}<br>Modules</div>
            </div>
        </div>
    </div>

    {{-- Wave separator --}}
    <svg class="hero-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 48" preserveAspectRatio="none" style="height:48px;width:100%;">
        <path fill="#f0f4f8" d="M0,32 C360,0 1080,64 1440,32 L1440,48 L0,48 Z"/>
    </svg>
</div>

{{-- ════════════════════════════════════════════
     MAIN BODY
════════════════════════════════════════════ --}}
<div class="lms-body">

    {{-- ── LEFT: Curriculum ──────────────────── --}}
    <div class="curriculum-section">

        @if($user->training_status === 'completed')
        <div class="complete-banner animate__animated animate__zoomIn">
            <div style="font-size:3rem;margin-bottom:12px;">🏆</div>
            <h3 class="fw-bold mb-2">Training Complete!</h3>
            <p style="color:rgba(255,255,255,0.75);font-size:0.88rem;margin:0;">You've mastered all phases. Welcome to the team!</p>
        </div>
        @endif

        @php
            $phaseColors = ['1','2','3','4','5'];
        @endphp

        @foreach($phases as $phaseIdx => $phase)
        @php
            $pTotalMods = $phase->modules->count();
            $pDoneMods  = $phase->modules->filter(fn($m) => optional($m->userProgress->first())->status === 'completed')->count();
            $pPct       = $pTotalMods > 0 ? round($pDoneMods / $pTotalMods * 100) : 0;
            $ci         = (($phase->order - 1) % 5) + 1;

            // Phase accessibility: all previous phases must be 100%
            $isAccessible = true;
            if ($phaseIdx > 0) {
                $prevPhase = $phases[$phaseIdx - 1];
                $prevDone  = $prevPhase->modules->filter(fn($m) => optional($m->userProgress->first())->status === 'completed')->count();
                if ($prevPhase->modules->count() > 0 && $prevDone < $prevPhase->modules->count()) {
                    $isAccessible = false;
                }
            }

            // Should this phase start expanded?
            $hasActiveModule = false;
            foreach ($phase->modules as $mod) {
                $st = optional($mod->userProgress->first())->status ?? 'locked';
                if (in_array($st, ['in_progress','available','failed'])) { $hasActiveModule = true; break; }
            }
            // Also expand if user has no progress and this is first phase
            if (!$hasAnyProgress && $phaseIdx === 0) $hasActiveModule = true;
            // Expand completed phases by default (collapsed)
            $startOpen = $hasActiveModule || ($pPct > 0 && $pPct < 100);

            $pillClass = $pPct === 100 ? 'pill-done' : ($pPct > 0 ? 'pill-partial' : (!$isAccessible ? 'pill-locked' : 'pill-empty'));
        @endphp

        <div class="phase-card {{ !$isAccessible ? 'locked-card' : '' }} animate__animated animate__fadeInUp" style="animation-delay:{{ $loop->index * 0.07 }}s;">

            {{-- Phase Header --}}
            <div class="phase-header" onclick="togglePhase('phase-{{ $phase->id }}')" id="ph-header-{{ $phase->id }}">
                <div class="phase-num pn-{{ $isAccessible ? $ci : 'locked' }}">
                    @if($pPct === 100) <i class="ti ti-check" style="font-size:1rem;"></i>
                    @elseif(!$isAccessible) <i class="ti ti-lock" style="font-size:0.9rem;"></i>
                    @else {{ str_pad($phase->order, 2, '0', STR_PAD_LEFT) }}
                    @endif
                </div>
                <div class="phase-info">
                    <div class="phase-label">Phase {{ $phase->order }}</div>
                    <div class="phase-name">{{ $phase->title }}</div>
                    @if($phase->description)
                    <div class="phase-desc">{{ $phase->description }}</div>
                    @endif
                </div>
                <div class="phase-right">
                    <span class="phase-pill {{ $pillClass }}">
                        @if($pPct === 100) <i class="ti ti-check me-1" style="font-size:0.65rem;"></i>Complete
                        @elseif(!$isAccessible) <i class="ti ti-lock me-1" style="font-size:0.65rem;"></i>Locked
                        @else {{ $pDoneMods }}/{{ $pTotalMods }}
                        @endif
                    </span>
                    <i class="ti ti-chevron-down phase-chevron {{ $startOpen ? 'open' : '' }}" id="chevron-{{ $phase->id }}"></i>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="phase-progress-bar">
                <div class="phase-progress-fill-bar pb-{{ $isAccessible ? $ci : 'locked' }}" style="width:{{ $pPct }}%;"></div>
            </div>

            {{-- Phase Body --}}
            <div class="phase-modules" id="phase-{{ $phase->id }}" style="{{ $startOpen ? '' : 'display:none;' }}">

                @if(!$isAccessible)
                <div class="phase-locked-msg">
                    <div class="locked-icon-wrap">
                        <i class="ti ti-lock" style="color:#94a3b8;font-size:1.1rem;"></i>
                    </div>
                    <p>Complete <strong>Phase {{ $phase->order - 1 }}</strong> to unlock this phase.</p>
                </div>
                @else
                @forelse($phase->modules as $module)
                @php
                    $prog   = $module->userProgress->first();
                    $status = $prog ? $prog->status : 'locked';

                    // Auto-unlock first module if user has no progress
                    if (!$hasAnyProgress && $phaseIdx === 0 && $loop->first && $status === 'locked') {
                        $status = 'available';
                    }

                    if ($status === 'locked' && $isAccessible) {
                        $hasIncompleteLower = $phase->modules->filter(function($m) use ($module) {
                            return $m->order < $module->order && optional($m->userProgress->first())->status !== 'completed';
                        })->isNotEmpty();
                        if (!$hasIncompleteLower) {
                            $status = 'available';
                        }
                    }

                    $isClickable = $status !== 'locked';
                    $href = $isClickable ? route('training.module.show', $module->id) : 'javascript:void(0)';
                    $rowClass = match($status) {
                        'completed'   => 'mod-completed',
                        'in_progress' => 'mod-active',
                        'available'   => 'mod-active',
                        'failed'      => 'mod-failed',
                        default       => 'mod-locked',
                    };
                    $iconClass = match($module->content_type) {
                        'video'   => 'mti-video',
                        'catalog' => 'mti-catalog',
                        default   => 'mti-policy',
                    };
                    $iconName = match($module->content_type) {
                        'video'   => 'ti-player-play',
                        'catalog' => 'ti-file-text',
                        default   => 'ti-book',
                    };
                    $iconColor = match($module->content_type) {
                        'video'   => '#f59e0b',
                        'catalog' => '#3b82f6',
                        default   => '#10b981',
                    };
                @endphp

                <a href="{{ $href }}" class="module-item {{ $rowClass }}" @if(!$isClickable)tabindex="-1"@endif>
                    <div class="mod-num">
                        @if($status === 'completed')
                            <i class="ti ti-check" style="font-size:0.75rem;"></i>
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </div>
                    <div class="mod-type-icon {{ $iconClass }}">
                        <i class="ti {{ $iconName }}" style="color:{{ $iconColor }};font-size:1rem;"></i>
                    </div>
                    <div class="mod-text">
                        <div class="mod-title">{{ $module->title }}</div>
                        <div class="mod-meta">
                            <span class="mod-meta-chip"><i class="ti ti-clock" style="font-size:0.72rem;"></i> {{ $module->estimated_time_minutes }}m</span>
                            <span class="mod-meta-chip" style="text-transform:capitalize;">
                                {{ ucfirst($module->content_type ?? 'reading') }}
                            </span>
                            @if($module->is_assessment_required ?? true)
                            <span class="mod-meta-chip"><i class="ti ti-clipboard-check" style="font-size:0.72rem;"></i> Quiz</span>
                            @endif
                            @if($prog && $prog->attempts > 0)
                            <span class="mod-meta-chip"><i class="ti ti-repeat" style="font-size:0.72rem;"></i> {{ $prog->attempts }} try</span>
                            @endif
                        </div>
                    </div>
                    <div class="mod-status-area">
                        @if($prog && $prog->assessment_score > 0)
                            <span class="mod-score-badge">{{ round($prog->assessment_score) }}%</span>
                        @endif
                        @if($status === 'available')
                            <span class="start-badge">Start</span>
                        @elseif($status === 'in_progress')
                            <span class="start-badge resume-badge">Resume</span>
                        @elseif($status === 'failed')
                            <span class="start-badge retry-badge">Retry</span>
                        @endif
                        <div class="mod-status-icon
                            @if($status==='completed') msi-done
                            @elseif($status==='in_progress') msi-prog
                            @elseif($status==='available') msi-avail
                            @elseif($status==='failed') msi-fail
                            @else msi-lock @endif">
                            @if($status==='completed') <i class="ti ti-check"></i>
                            @elseif($status==='in_progress') <i class="ti ti-player-play"></i>
                            @elseif($status==='available') <i class="ti ti-arrow-right"></i>
                            @elseif($status==='failed') <i class="ti ti-refresh"></i>
                            @else <i class="ti ti-lock"></i>
                            @endif
                        </div>
                    </div>
                </a>

                @empty
                <div style="padding:24px;text-align:center;color:#94a3b8;font-size:0.82rem;">
                    <i class="ti ti-inbox" style="font-size:1.8rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                    No modules yet.
                </div>
                @endforelse
                @endif
            </div>
        </div>
        @endforeach

        @if($phases->isEmpty())
        <div style="text-align:center;padding:60px 20px;background:white;border-radius:16px;">
            <div style="font-size:3rem;opacity:0.15;margin-bottom:16px;">📚</div>
            <h5 style="color:#64748b;">No training content yet</h5>
            <p style="color:#94a3b8;font-size:0.85rem;">Training phases will appear here once added by your HR team.</p>
        </div>
        @endif

    </div>

    {{-- ── RIGHT: Progress Sidebar ───────────── --}}
    <aside class="progress-sidebar">

        {{-- Progress Card --}}
        <div class="progress-card">
            <div class="progress-card-header">
                @php $c2 = round(2 * M_PI * 38, 2); @endphp
                <div class="pc-ring">
                    <svg width="100" height="100" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="38" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="7"/>
                        <circle cx="50" cy="50" r="38" fill="none"
                            stroke="#06b6d4" stroke-width="7"
                            stroke-dasharray="{{ $c2 }}"
                            stroke-dashoffset="{{ round($c2 * (1 - $progressPercentage/100), 2) }}"
                            stroke-linecap="round"
                            style="transition:stroke-dashoffset 1.5s ease;"/>
                    </svg>
                    <div class="pc-ring-text">
                        <span class="pc-pct">{{ $progressPercentage }}%</span>
                        <span class="pc-pct-label">Done</span>
                    </div>
                </div>
                <div class="pc-title">Your Progress</div>
                <div class="pc-subtitle">{{ $doneMods }}/{{ $totalMods }} modules complete</div>
            </div>
            <div class="progress-card-body">
                <div class="pc-stat-row">
                    <div class="pc-stat">
                        <div class="pc-stat-val">{{ $phases->count() }}</div>
                        <div class="pc-stat-lbl">Phases</div>
                    </div>
                    <div class="pc-stat">
                        <div class="pc-stat-val">{{ $avgScore > 0 ? $avgScore.'%' : '—' }}</div>
                        <div class="pc-stat-lbl">Avg Score</div>
                    </div>
                    <div class="pc-stat">
                        <div class="pc-stat-val">{{ $estTotalMins }}m</div>
                        <div class="pc-stat-lbl">Est. Time</div>
                    </div>
                </div>

                @if($nextModId && $user->training_status !== 'completed')
                <a href="{{ route('training.module.show', $nextModId) }}" class="pc-cta">
                    {{ $hasAnyProgress ? 'Continue Learning' : 'Begin Training' }}
                    <i class="ti ti-arrow-right"></i>
                </a>
                @endif

                {{-- Per-phase mini progress --}}
                <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;margin-bottom:10px;">Phase Breakdown</div>
                <div class="pc-phase-list">
                    @foreach($phases as $pi => $ph)
                    @php
                        $phD = $ph->modules->filter(fn($m) => optional($m->userProgress->first())->status==='completed')->count();
                        $phT = $ph->modules->count();
                        $phP = $phT > 0 ? round($phD/$phT*100) : 0;
                        $phColors = ['#06b6d4','#3b82f6','#a855f7','#f97316','#22c55e'];
                        $phCol = $phColors[$pi % 5];
                    @endphp
                    <div class="pc-phase-row">
                        <div class="pc-phase-dot" style="background:{{ $phCol }};"></div>
                        <div class="pc-phase-info">
                            <div class="pc-phase-name">Phase {{ $ph->order }}: {{ \Illuminate\Support\Str::limit($ph->title, 22) }}</div>
                            <div class="pc-phase-mini-bar">
                                <div class="pc-phase-mini-fill" style="width:{{ $phP }}%;background:{{ $phCol }};"></div>
                            </div>
                        </div>
                        <div class="pc-phase-count">{{ $phD }}/{{ $phT }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- HR View info --}}
        @if($user->id !== auth()->id())
        <div style="background:white;border-radius:14px;padding:16px;border:1px solid #e2e8f0;font-size:0.8rem;color:#64748b;text-align:center;">
            <i class="ti ti-eye" style="font-size:1.2rem;display:block;margin-bottom:6px;color:#94a3b8;"></i>
            Viewing <strong style="color:#0f172a;">{{ $user->name }}</strong>'s training progress
        </div>
        @endif

    </aside>

</div>

@endsection

@section('page-script')
<script>
function togglePhase(id) {
    const body = document.getElementById(id);
    if (!body) return;
    // Chevron ID is "chevron-{phaseId}" where id is "phase-{phaseId}"
    const chevron = document.getElementById('chevron-' + id.replace('phase-', ''));
    const isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : 'block';
    if (chevron) chevron.classList.toggle('open', !isOpen);
}
</script>
@endsection
