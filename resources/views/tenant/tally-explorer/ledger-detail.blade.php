@extends('layouts/layoutMaster')

@section('title', 'Ledger · ' . \Illuminate\Support\Str::limit($ledger->name, 40))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss', 'resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@section('page-style')
<style>
    /* Same visual language as the CRM Customer Profile page (resources/views/tenant/crm-clients/profile.blade.php)
       -- the two pages are meant to feel like one app, so the hero banner, glass key-figure strip, card
       style and table style are the identical recipe, just re-scoped to #lgPage instead of #cpPage. */
    #lgPage {
        --cp-ink: #0f172a; --cp-text: #334155; --cp-muted: #64748b; --cp-faint: #94a3b8;
        --cp-line: #e8edf3; --cp-line-strong: #d3dbe5; --cp-soft: #f6f8fb; --cp-surface: #fff;
        --cp-brand: #127464; --cp-brand-dark: #0e5f52; --cp-brand-soft: #eaf6f3; --cp-brand-line: #cfe8e1;
        --cp-ok: #067647; --cp-ok-soft: #ecfdf3; --cp-ok-line: #bfe9d2;
        --cp-warn: #b54708; --cp-warn-soft: #fffaeb; --cp-warn-line: #fedf89;
        --cp-bad: #b42318; --cp-bad-soft: #fef3f2; --cp-bad-line: #fbd0cc;
        --cp-shadow: 0 1px 2px rgba(16,24,40,.04), 0 6px 20px rgba(16,24,40,.05);
        --cp-shadow-hover: 0 2px 4px rgba(16,24,40,.05), 0 12px 28px rgba(16,24,40,.08);
        color: var(--cp-text);
    }
    #lgPage .text-muted { color: var(--cp-muted) !important; }

    #lgPage .lg-inner { padding: .5rem 1rem 2rem; }
    @media (min-width: 768px) { #lgPage .lg-inner { padding: .5rem 1.5rem 2rem; } }
    #lgPage .lg-back { display: inline-flex; align-items: center; gap: .2rem; margin: .25rem 0 .9rem; font-size: .84rem; font-weight: 600; color: #475569; text-decoration: none; }
    #lgPage .lg-back:hover { color: var(--cp-brand); }

    /* ── Header banner (identical recipe to .cp-hero) ───────────────────── */
    #lgPage .cp-hero {
        position: relative; isolation: isolate; margin-bottom: 1.25rem; border-radius: 22px; overflow: hidden; color: #fff;
        background: #0a2740; box-shadow: 0 14px 34px rgba(10,39,64,.20);
        animation: lgRise .5s ease backwards;
    }
    #lgPage .cp-hero-bg {
        position: absolute; inset: 0; z-index: -1;
        background:
            radial-gradient(900px 380px at 88% -12%, rgba(45,212,191,.40), transparent 62%),
            radial-gradient(720px 420px at 8% 118%, rgba(59,130,246,.44), transparent 62%),
            radial-gradient(520px 300px at 52% -8%, rgba(99,102,241,.30), transparent 66%),
            linear-gradient(115deg, #0a2740 0%, #0d4266 48%, #0f6b78 100%);
    }
    #lgPage .cp-hero-bg::before {
        content: ''; position: absolute; inset: 0; opacity: .55;
        background-image: linear-gradient(rgba(255,255,255,.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.07) 1px, transparent 1px);
        background-size: 46px 46px;
        -webkit-mask-image: linear-gradient(180deg, #000 0%, transparent 82%); mask-image: linear-gradient(180deg, #000 0%, transparent 82%);
    }
    #lgPage .cp-hero-bg::after {
        content: ''; position: absolute; right: -140px; top: -230px; width: 560px; height: 560px; border-radius: 50%;
        background: radial-gradient(circle, rgba(94,234,212,.30), transparent 66%); filter: blur(8px);
        animation: lgDrift 16s ease-in-out infinite alternate;
    }
    @keyframes lgDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(-70px,46px) scale(1.14); } }
    @keyframes lgRise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

    #lgPage .cp-hero-top { position: relative; display: flex; align-items: center; gap: 1.5rem; padding: 1.9rem 2.1rem 1.6rem; }
    #lgPage .cp-hero-logo {
        width: 92px; height: 92px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 22px;
        background: #fff; color: #0f4c75; font-size: 2rem; font-weight: 800; letter-spacing: .01em;
        box-shadow: 0 10px 28px rgba(0,0,0,.20);
    }
    #lgPage .cp-hero-info { min-width: 0; flex: 1; }
    #lgPage .cp-hero-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: .55rem; font-size: .8rem; color: rgba(255,255,255,.80); }
    #lgPage .cp-hero-since i { margin-right: 4px; opacity: .85; }
    #lgPage .cp-hero-title { margin: 0 0 .8rem; font-size: 2rem; font-weight: 700; line-height: 1.15; letter-spacing: -.025em; color: #fff !important; }
    #lgPage .cp-hero-tags { display: flex; flex-wrap: wrap; gap: .5rem; }
    #lgPage .cp-tag {
        display: inline-flex; align-items: center; gap: .4rem; padding: 5px 12px; border-radius: 999px; font-size: .78rem; font-weight: 500; color: #fff;
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
        min-width: 0;
    }
    #lgPage .cp-tag > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #lgPage .cp-tag i { font-size: .95rem; opacity: .85; }
    #lgPage a.cp-tag { text-decoration: none; transition: background-color .15s ease; }
    #lgPage a.cp-tag:hover { background: rgba(255,255,255,.22); color: #fff; }
    #lgPage .cp-pill {
        display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 999px; font-size: .75rem; font-weight: 600;
        background: rgba(34,197,94,.20); border: 1px solid rgba(74,222,128,.42); color: #bbf7d0;
    }
    #lgPage .cp-pill::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 0 3px rgba(74,222,128,.22); }
    #lgPage .cp-pill.cp-pill-warn { background: rgba(234,179,8,.20); border-color: rgba(253,224,71,.42); color: #fef08a; }
    #lgPage .cp-pill.cp-pill-warn::before { background: #facc15; box-shadow: 0 0 0 3px rgba(250,204,21,.22); }
    #lgPage .cp-hero-actions { position: absolute; top: 1.9rem; right: 2.1rem; display: flex; gap: .6rem; }

    /* key figures: a glass strip along the bottom of the banner */
    #lgPage .cp-kpis {
        position: relative; display: grid; grid-template-columns: repeat(var(--lg-kpi-cols, 5), minmax(0, 1fr)); margin: 0 1.6rem 1.6rem; padding: 1.15rem .3rem; border-radius: 18px;
        background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.16);
        -webkit-backdrop-filter: blur(14px) saturate(140%); backdrop-filter: blur(14px) saturate(140%);
    }
    #lgPage .cp-kpi { display: flex; align-items: center; gap: .9rem; min-width: 0; padding: .2rem 1.25rem; }
    #lgPage .cp-kpi + .cp-kpi { border-left: 1px solid rgba(255,255,255,.15); }
    #lgPage .cp-kpi-icon { width: 46px; height: 46px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 13px; font-size: 1.4rem; }
    #lgPage .tone-blue   { background: #e3edff; color: #2563eb; }
    #lgPage .tone-violet { background: #ece8ff; color: #7c3aed; }
    #lgPage .tone-green  { background: #dcfce9; color: #16a34a; }
    #lgPage .tone-cyan   { background: #d5f5fb; color: #0891b2; }
    #lgPage .tone-amber  { background: #ffead6; color: #ea580c; }
    #lgPage .cp-kpi-body { min-width: 0; }
    #lgPage .cp-kpi-value { display: flex; flex-wrap: wrap; align-items: baseline; gap: .1rem .5rem; font-size: 1.55rem; font-weight: 700; line-height: 1.15; letter-spacing: -.02em; color: #fff; font-variant-numeric: tabular-nums; }
    #lgPage .cp-kpi-value.is-text { font-size: 1.2rem; }
    #lgPage .cp-kpi-label { margin-top: 2px; font-size: .82rem; color: rgba(255,255,255,.88); }
    #lgPage .cp-kpi-sub { font-size: .72rem; line-height: 1.4; color: rgba(255,255,255,.64); }
    @media (max-width: 1199px) {
        #lgPage .cp-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); row-gap: 1.1rem; }
        #lgPage .cp-kpi:nth-child(3n+1) { border-left: 0; }
    }
    @media (max-width: 991px) {
        #lgPage .cp-hero-top { flex-wrap: wrap; padding: 1.4rem 1.3rem 1.2rem; }
        #lgPage .cp-hero-actions { position: static; width: 100%; }
        #lgPage .cp-hero-title { font-size: 1.6rem; }
        #lgPage .cp-kpis { margin: 0 1rem 1rem; }
    }
    @media (max-width: 767px) {
        #lgPage .cp-hero-top { padding: 1.2rem 1.2rem 1rem; }
        #lgPage .cp-hero-logo { width: 72px; height: 72px; border-radius: 18px; font-size: 1.6rem; }
        #lgPage .cp-kpis { display: flex; gap: .6rem; margin: 0 1rem 1rem; padding: .6rem; overflow-x: auto; scroll-snap-type: x proximity; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        #lgPage .cp-kpis::-webkit-scrollbar { display: none; }
        #lgPage .cp-kpi, #lgPage .cp-kpi:nth-child(n) { flex: 0 0 auto; width: min(46%, 260px); gap: .7rem; padding: .6rem .8rem; border: 1px solid rgba(255,255,255,.14); border-radius: 13px; background: rgba(255,255,255,.07); scroll-snap-align: start; }
        #lgPage .cp-kpi-icon { width: 40px; height: 40px; border-radius: 11px; font-size: 1.2rem; }
    }
    @media (max-width: 575px) {
        #lgPage .cp-hero { margin-bottom: .9rem; border-radius: 18px; }
        #lgPage .cp-hero-top { display: grid; grid-template-columns: 56px minmax(0, 1fr); align-items: center; gap: .55rem .8rem; padding: 1rem 1rem .8rem; }
        #lgPage .cp-hero-info { display: contents; }
        #lgPage .cp-hero-logo { grid-row: 1 / span 2; width: 56px; height: 56px; border-radius: 15px; font-size: 1.2rem; }
        #lgPage .cp-hero-meta { grid-column: 2; gap: .5rem; margin: 0; font-size: .74rem; }
        #lgPage .cp-hero-title { grid-column: 2; margin: 0; font-size: 1.25rem; line-height: 1.2; overflow-wrap: anywhere; }
        #lgPage .cp-hero-tags { grid-column: 1 / -1; gap: .4rem; }
        #lgPage .cp-tag { max-width: 100%; padding: 4px 10px; font-size: .75rem; }
        #lgPage .cp-kpis { margin: 0 .75rem .8rem; }
        #lgPage .cp-kpi, #lgPage .cp-kpi:nth-child(n) { width: min(78%, 250px); }
        #lgPage .cp-kpi-value { font-size: 1.3rem; }
        #lgPage .cp-kpi-value.is-text { font-size: 1.05rem; }
    }

    /* ── Cards (identical recipe to .cp-card) ───────────────────────────── */
    #lgPage .cp-card {
        background: var(--cp-surface); border: 1px solid #edf1f5 !important; border-radius: 16px !important;
        box-shadow: var(--cp-shadow) !important; transition: box-shadow .25s ease, border-color .25s ease !important;
        animation: lgRise .55s ease backwards; overflow: hidden;
    }
    #lgPage .cp-card:hover { box-shadow: var(--cp-shadow-hover) !important; border-color: var(--cp-line) !important; }
    #lgPage .cp-card-body { padding: 1.3rem 1.45rem; }
    #lgPage .cp-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; padding: 1.3rem 1.45rem .4rem; flex-wrap: wrap; }
    #lgPage .cp-card h6, #lgPage .cp-card-head h6 { display: flex; align-items: center; gap: .65rem; margin: 0; font-size: .95rem; font-weight: 600 !important; letter-spacing: -.005em; color: var(--cp-ink); }
    #lgPage .cp-card h6 > i.bx, #lgPage .cp-card-head h6 > i.bx {
        width: 30px; height: 30px; margin: 0 !important; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
        border-radius: 9px; font-size: 1.05rem; background: rgba(18,116,100,.10); color: var(--cp-brand);
    }
    #lgPage .cp-sub { font-size: .72rem; color: var(--cp-faint); }

    /* terms list (inside a cp-card) */
    #lgPage .lg-terms > div { display: flex; justify-content: space-between; gap: .5rem; padding: .42rem 0; border-bottom: 1px dashed #eef2f6; }
    #lgPage .lg-terms > div:last-child { border-bottom: 0; }
    #lgPage .lg-t-lbl { font-size: .78rem; color: var(--cp-muted); }
    #lgPage .lg-t-val { font-size: .9rem; font-weight: 700; color: var(--cp-ink); text-align: right; }
    #lgPage .lg-t-val.sm { font-size: .8rem; font-weight: 600; }
    #lgPage .lg-t-val.warn { color: var(--cp-warn); }
    #lgPage .lg-t-sub { font-size: .72rem; font-weight: 500; color: var(--cp-faint); }
    #lgPage .lg-util-bar { height: 8px; border-radius: 20px; background: var(--cp-soft); overflow: hidden; }
    #lgPage .lg-util-bar span { display: block; height: 100%; border-radius: 20px; }
    #lgPage .lg-age { display: inline-block; margin-left: .4rem; font-size: .68rem; background: var(--cp-soft); color: var(--cp-muted); padding: 1px 8px; border-radius: 20px; }

    /* ── Tables (identical recipe to CRM's) ─────────────────────────────── */
    #lgPage .table td, #lgPage .table th { padding: .65rem .85rem; }
    #lgPage .table thead th { background: var(--cp-soft); font-size: .68rem; font-weight: 600; letter-spacing: .05em; color: var(--cp-muted); border-bottom: 1px solid var(--cp-line); text-transform: uppercase; white-space: nowrap; }
    #lgPage .table tbody td { font-size: .84rem; color: var(--cp-text); border-bottom: 1px solid #f1f4f8; }
    #lgPage .table tbody tr:last-child td { border-bottom: 0; }
    #lgPage .table tbody tr { transition: background-color .15s ease; }
    #lgPage .table tbody tr:hover { background: #f8fafc; }
    #lgPage .lg-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* recent-activity feed row */
    #lgPage .lg-feed { display: flex; align-items: center; gap: .7rem; padding: .55rem .1rem; border-bottom: 1px solid #f1f4f8; border-radius: 8px; transition: background-color .15s ease; }
    #lgPage .lg-feed:hover { background: #f8fafc; }
    #lgPage .lg-feed:last-child { border-bottom: 0; }
    #lgPage .lg-feed-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    #lgPage .lg-feed-dot.in { background: var(--cp-ok); } #lgPage .lg-feed-dot.out { background: #6366f1; }
    #lgPage .lg-feed-main { flex: 1; min-width: 0; }
    #lgPage .lg-feed-top { display: flex; align-items: baseline; gap: .4rem; }
    #lgPage .lg-feed-type { font-size: .8rem; font-weight: 700; color: var(--cp-ink); }
    #lgPage .lg-feed-ref { font-size: .74rem; color: var(--cp-muted); }
    #lgPage .lg-feed-amt { font-weight: 700; font-size: .86rem; font-variant-numeric: tabular-nums; }
    #lgPage .lg-feed-amt.in { color: var(--cp-ok); } #lgPage .lg-feed-amt.out { color: #4338ca; }

    /* ── Ledger detail fields (full Tally master record) ───────────────── */
    #lgPage .lg-fields { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .9rem 1.4rem; }
    #lgPage .lg-field-lbl { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; color: var(--cp-faint); font-weight: 700; }
    #lgPage .lg-field-val { font-size: .88rem; font-weight: 600; color: var(--cp-ink); word-break: break-word; }
    #lgPage .lg-field-val.empty { color: var(--cp-faint); font-weight: 400; }

    /* ── CRM linkage: contacts / team / activity feed / follow-ups (same recipe as the CRM profile) ── */
    #lgPage .lg-crm-banner {
        display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; padding: 1rem 1.3rem; border-radius: 16px;
        background: linear-gradient(90deg, #e9f7f2, #f2faf8); border: 1px solid #d3ede4; animation: lgRise .6s ease .1s backwards;
    }
    #lgPage .lg-crm-banner-icon { width: 46px; height: 46px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 13px; background: var(--cp-brand); color: #fff; font-size: 1.3rem; }
    #lgPage .lg-crm-banner-text strong { display: block; font-size: .92rem; font-weight: 700; color: var(--cp-ink); }
    #lgPage .lg-crm-banner-text span { font-size: .78rem; color: var(--cp-muted); }
    #lgPage .lg-crm-banner .btn { margin-left: auto; flex-shrink: 0; }
    #lgPage .lg-crm-unlinked { display: flex; align-items: center; gap: .7rem; margin-bottom: 1.25rem; padding: .85rem 1.1rem; border-radius: 14px; border: 1px dashed var(--cp-line-strong); color: var(--cp-muted); font-size: .82rem; }
    #lgPage .lg-crm-unlinked i { font-size: 1.1rem; }

    #lgPage .cp-contact-row { margin-bottom: 8px; padding: 11px 13px; border: 1px solid var(--cp-line); border-radius: 13px; transition: border-color .15s ease, background-color .15s ease; }
    #lgPage .cp-contact-row:last-child { margin-bottom: 0; }
    #lgPage .cp-contact-row:hover { border-color: var(--cp-line-strong); background: #fbfcfd; }
    #lgPage .cp-avatar { width: 36px; height: 36px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #e3edff; color: #2563eb; font-size: .76rem; font-weight: 600; }
    #lgPage .cp-party-name { font-size: .84rem; font-weight: 600; line-height: 1.25; color: var(--cp-ink); }
    #lgPage .cp-party-role { margin-left: .4rem; padding: 0 7px; border-radius: 999px; background: #f3edff; color: #6d28d9; font-size: .66rem; font-weight: 600; }
    #lgPage .cp-party-meta { display: flex; flex-wrap: wrap; gap: .1rem .8rem; font-size: .74rem; }
    #lgPage .cp-party-meta a { color: var(--cp-muted); text-decoration: none; }
    #lgPage .cp-party-meta a:hover { color: var(--cp-brand); }
    #lgPage .cp-party-meta i { margin-right: 3px; opacity: .8; }

    #lgPage .cp-team-slot { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f4f8; }
    #lgPage .cp-team-slot:last-child { border-bottom: 0; }
    #lgPage .cp-team-avatar { width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .64rem; font-weight: 600; box-shadow: 0 0 0 3px #fff, 0 0 0 4px #edf1f5; }
    #lgPage .lg-team-role { font-size: .66rem; text-transform: uppercase; letter-spacing: .04em; color: var(--cp-faint); font-weight: 700; }

    #lgPage .activity-item { display: flex; align-items: center; gap: .7rem; padding: .5rem .35rem; border-bottom: 1px solid #f1f4f8; border-radius: 8px; }
    #lgPage .activity-item:last-child { border-bottom: 0; }
    #lgPage .activity-icon { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .8rem; color: #fff; }
    #lgPage .activity-icon.type-call { background: var(--cp-ok); }
    #lgPage .activity-icon.type-comment { background: #3b82f6; }
    #lgPage .activity-icon.type-system { background: #94a3b8; }
    #lgPage .activity-main { flex: 1; min-width: 0; display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
    #lgPage .activity-who { flex-shrink: 0; font-size: .83rem; font-weight: 600; color: var(--cp-ink); }
    #lgPage .activity-text { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .83rem; color: var(--cp-text); }
    #lgPage .activity-full { display: none; }
    #lgPage .activity-time { flex-shrink: 0; font-size: .72rem; color: var(--cp-muted); white-space: nowrap; }
    #lgPage .activity-time .t-short { display: none; }
    #lgPage .follow-up-chip { flex-shrink: 0; padding: 2px 9px; border-radius: 999px; background: var(--cp-warn-soft); color: var(--cp-warn); font-size: .72rem; font-weight: 600; white-space: nowrap; }
    #lgPage .follow-up-chip .fu-short, #lgPage .follow-up-chip .fu-date::before { display: none; }
    #lgPage .cp-overdue { color: var(--cp-bad) !important; }
    #lgPage .min-width-0 { min-width: 0; }
    #lgPage .role-purchase { background: #f3edff !important; color: #6d28d9; }
    #lgPage .role-finance  { background: #fdf2f8 !important; color: #be185d; }
    #lgPage .role-accounts { background: var(--cp-ok-soft) !important; color: var(--cp-ok); }
    #lgPage .role-admin    { background: #eff6ff !important; color: #1d4ed8; }
    #lgPage .role-other    { background: #f1f5f9 !important; color: var(--cp-muted); }

    /* ── Buttons (identical recipe) ─────────────────────────────────────── */
    #lgPage .btn:not(.btn-close) {
        min-height: 36px !important; padding: .45rem 1rem !important; gap: .4rem !important;
        border-width: 1px !important; border-style: solid !important; border-radius: 10px !important;
        font-size: .83rem !important; font-weight: 600 !important; letter-spacing: 0 !important;
        background-image: none !important; box-shadow: none !important; transform: none !important;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease !important;
    }
    #lgPage .cp-hero .btn-light { background-color: #fff !important; border-color: transparent !important; color: var(--cp-ink) !important; }
    #lgPage .cp-hero .btn-light:hover { background-color: var(--cp-soft) !important; }
    @media (prefers-reduced-motion: reduce) { #lgPage *, #lgPage *::before, #lgPage *::after { animation: none !important; transition: none !important; } }
</style>
@endsection

@section('content')
@php
  $c = fn ($v, $d = 0) => '₹' . number_format((float) $v, $d);
  $__kpiCount = ($isCustomer ? 1 : 0) + ($isSupplier ? 1 : 0);
  $__kpiCols = 3 + max(1, $__kpiCount);
@endphp
<div id="lgPage" class="layout-full-width">
  <div class="lg-inner">
    <a href="{{ route('tally-explorer.ledgers') }}" class="lg-back"><i class="bx bx-chevron-left"></i> Back to Ledgers</a>

    {{-- ── Header banner, with the key figures in a glass strip (same recipe as the CRM Customer Profile) ── --}}
    <section class="cp-hero">
        <div class="cp-hero-bg" aria-hidden="true"></div>

        <div class="cp-hero-top">
            <div class="cp-hero-logo">{{ strtoupper(substr($ledger->name, 0, 2)) }}</div>
            <div class="cp-hero-info">
                <div class="cp-hero-meta">
                    @if($isCustomer || $isSupplier)
                        <span class="cp-pill {{ (float)$ledger->closing_balance < 0 ? 'cp-pill-warn' : '' }}">{{ $isCustomer && $isSupplier ? 'Customer & Supplier' : ($isCustomer ? 'Customer' : 'Supplier') }}</span>
                    @endif
                    <span class="cp-hero-since"><i class="bx bx-folder"></i>{{ $ledger->primary_group ?: 'Ledger' }}@if($ledger->ledger_group && $ledger->ledger_group !== $ledger->primary_group) &middot; {{ $ledger->ledger_group }}@endif</span>
                </div>
                <h1 class="cp-hero-title">{{ $ledger->name }}</h1>
                <div class="cp-hero-tags">
                    @if($ledger->gstin)<span class="cp-tag"><i class="bx bx-id-card"></i><span>{{ $ledger->gstin }}</span></span>@endif
                    @if($ledger->pan)<span class="cp-tag"><i class="bx bx-credit-card-front"></i><span>PAN {{ $ledger->pan }}</span></span>@endif
                    @if($ledger->contact_person)<span class="cp-tag"><i class="bx bx-user"></i><span>{{ $ledger->contact_person }}</span></span>@endif
                    @if($ledger->contact_mobile)<a class="cp-tag" href="tel:{{ $ledger->contact_mobile }}"><i class="bx bx-phone"></i><span>{{ $ledger->contact_mobile }}</span></a>@endif
                </div>
            </div>
        </div>

        <div class="cp-kpis" style="--lg-kpi-cols: {{ $__kpiCols }};">
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-blue"><i class="bx bx-wallet"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value">{{ $ledger->closing_balance !== null ? $c($ledger->closing_balance) : '—' }}</div>
                    <div class="cp-kpi-label">Closing Balance</div>
                </div>
            </div>
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-amber"><i class="bx bx-credit-card"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value">{{ $c($kpi['outstanding']) }}</div>
                    <div class="cp-kpi-label">Outstanding</div>
                    @if($terms['credit_limit'])<div class="cp-kpi-sub">of {{ $c($terms['credit_limit']) }} limit ({{ $terms['utilisation'] }}%)</div>@endif
                </div>
            </div>
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-violet"><i class="bx bx-time-five"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value">{{ number_format($kpi['bills_open']) }}</div>
                    <div class="cp-kpi-label">Open Bills</div>
                </div>
            </div>
            @if($isCustomer)
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-green"><i class="bx bx-receipt"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value">{{ $c($kpi['sales_total']) }}</div>
                    <div class="cp-kpi-label">Total Sales</div>
                    <div class="cp-kpi-sub">{{ number_format($kpi['sales_count']) }} invoices &middot; {{ $c($kpi['received']) }} received</div>
                </div>
            </div>
            @endif
            @if($isSupplier)
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-cyan"><i class="bx bx-cart"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value">{{ $c($kpi['purchase_total']) }}</div>
                    <div class="cp-kpi-label">Total Purchases</div>
                    <div class="cp-kpi-sub">{{ number_format($kpi['purchase_count']) }} bills &middot; {{ $c($kpi['paid']) }} paid</div>
                </div>
            </div>
            @endif
            @unless($isCustomer || $isSupplier)
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-green"><i class="bx bx-info-circle"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value is-text">No activity</div>
                    <div class="cp-kpi-label">Sales &amp; Purchases</div>
                </div>
            </div>
            @endunless
        </div>
    </section>

    {{-- ── CRM linkage: this ledger's matching crm_client_mappings record, if any (same normalized-name
         match the CRM Customer Profile page itself uses to find Tally sales for an account, just run in
         the opposite direction) ──────────────────────────────────────────────────────────────────────── --}}
    @if($crmMapping)
      <div class="lg-crm-banner">
        <div class="lg-crm-banner-icon"><i class="bx bx-link"></i></div>
        <div class="lg-crm-banner-text">
          <strong>Linked CRM account: {{ $crmMapping->crm_company_name }}</strong>
          <span>{{ $crmMapping->crm_stage ?: 'No stage set' }}@if($crmMapping->crm_city) &middot; {{ $crmMapping->crm_city }}{{ $crmMapping->crm_state ? ', '.$crmMapping->crm_state : '' }}@endif &middot; customer since {{ $crmMapping->created_at->format('M Y') }}</span>
        </div>
        <a href="{{ route('crm-clients.profile', $crmMapping->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-id-card"></i> Open full CRM profile</a>
      </div>
    @else
      <div class="lg-crm-unlinked"><i class="bx bx-unlink"></i> No matching CRM client record found for this ledger name &mdash; contacts, calls, tasks and notes live in the CRM module once one is mapped.</div>
    @endif

    {{-- Full Tally master record -- every field synced for this ledger, not just the handful used above --}}
    <div class="cp-card mb-4">
      <div class="cp-card-head"><h6><i class="bx bx-id-card"></i> Ledger details</h6><span class="cp-sub">full Tally master record</span></div>
      <div class="cp-card-body">
        <div class="lg-fields">
          @php
            $__fields = [
              'Name' => $ledger->name, 'Mailing name' => $ledger->mailing_name,
              'Alias' => $ledger->alias ?? null, 'Company (Tally)' => $ledger->company,
              'Primary group' => $ledger->primary_group, 'Ledger group' => $ledger->ledger_group,
              'GSTIN' => $ledger->gstin, 'PAN' => $ledger->pan,
              'Contact person' => $ledger->contact_person, 'Contact mobile' => $ledger->contact_mobile,
              'Email' => $ledger->email, 'Address' => $ledger->address,
              'State' => $ledger->state, 'Country' => $ledger->country, 'Pincode' => $ledger->pincode,
              'Closing balance' => $ledger->closing_balance !== null ? $c($ledger->closing_balance) : null,
              'Credit limit' => $ledger->credit_limit !== null ? $c($ledger->credit_limit) : null,
              'Last synced from Tally' => $ledger->synced_at ? \Illuminate\Support\Carbon::parse($ledger->synced_at)->format('d M Y, h:i A') : null,
            ];
          @endphp
          @foreach($__fields as $__lbl => $__val)
            <div><div class="lg-field-lbl">{{ $__lbl }}</div><div class="lg-field-val {{ $__val ? '' : 'empty' }}">{{ $__val ?: 'Not set in Tally' }}</div></div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Terms & credit detail --}}
    <div class="row g-4 mb-4">
      <div class="col-12 col-lg-5">
        <div class="cp-card h-100"><div class="cp-card-body">
          <h6 class="mb-3"><i class="bx bx-receipt"></i> Terms &amp; credit</h6>
          <div class="lg-terms">
            <div><span class="lg-t-lbl">Credit limit</span><span class="lg-t-val">{{ $terms['credit_limit'] !== null ? $c($terms['credit_limit']) : 'not set' }}</span></div>
            <div><span class="lg-t-lbl">Outstanding now</span><span class="lg-t-val {{ $kpi['outstanding'] > 0 ? 'warn' : '' }}">{{ $c($kpi['outstanding']) }}</span></div>
            <div><span class="lg-t-lbl">Credit used</span><span class="lg-t-val">{{ $terms['utilisation'] !== null ? $terms['utilisation'].'%' : '—' }}</span></div>
            <div><span class="lg-t-lbl">Payment terms (typical)</span><span class="lg-t-val">{{ $terms['credit_days_typical'] !== null ? $terms['credit_days_typical'].' days' : '—' }}@if($terms['credit_days_max'] !== null && $terms['credit_days_max'] !== $terms['credit_days_typical'])<span class="lg-t-sub"> (up to {{ $terms['credit_days_max'] }})</span>@endif</span></div>
            <div><span class="lg-t-lbl">Open bills</span><span class="lg-t-val">{{ number_format($kpi['bills_open']) }}</span></div>
            <div><span class="lg-t-lbl">Group</span><span class="lg-t-val sm">{{ $ledger->ledger_group ?: '—' }}</span></div>
          </div>
          @if($terms['utilisation'] !== null)
            <div class="mt-3"><div class="lg-util-bar"><span style="width:{{ min(100, $terms['utilisation']) }}%; background:{{ $terms['utilisation'] > 100 ? 'var(--cp-bad)' : ($terms['utilisation'] > 80 ? 'var(--cp-warn)' : 'var(--cp-brand)') }};"></span></div>
            <div class="lg-t-sub mt-1">{{ $c($kpi['outstanding']) }} of {{ $c($terms['credit_limit']) }} credit limit used</div></div>
          @endif
        </div></div>
      </div>
      <div class="col-12 col-lg-7">
        @if($trend->isNotEmpty())
        <div class="cp-card h-100">
          <div class="cp-card-head"><h6><i class="bx bx-bar-chart-alt-2"></i> Monthly activity</h6><span class="cp-sub">sales &amp; purchases by month</span></div>
          <div class="cp-card-body"><div id="lgTrend" style="min-height:260px;"></div></div>
        </div>
        @endif
      </div>
    </div>

    @if($crmMapping)
    <div class="row g-4 mb-4">
      {{-- CRM contacts --}}
      <div class="col-12 col-lg-4">
        <div class="cp-card h-100">
          <div class="cp-card-head"><h6><i class="bx bx-id-card"></i> Contacts</h6><span class="cp-sub">{{ $crmContacts->count() }}</span></div>
          <div class="cp-card-body">
            @forelse($crmContacts as $ct)
              <div class="cp-contact-row d-flex align-items-start gap-2">
                <div class="cp-avatar">{{ strtoupper(substr($ct->name, 0, 1)) }}</div>
                <div class="flex-grow-1 min-width-0">
                  <div class="cp-party-name">{{ $ct->name }}@if($ct->is_primary)<span class="cp-party-role" style="background:var(--cp-brand-soft);color:var(--cp-brand);">Primary</span>@endif@if($ct->role)<span class="cp-party-role role-{{ $ct->role->value }}">{{ $ct->role->label() }}</span>@endif</div>
                  <div class="cp-party-meta">
                    @if($ct->email)<a href="mailto:{{ $ct->email }}"><i class="bx bx-envelope"></i>{{ $ct->email }}</a>@endif
                    @if($ct->phone)<a href="tel:{{ $ct->phone }}"><i class="bx bx-phone"></i>{{ $ct->phone }}</a>@endif
                  </div>
                </div>
              </div>
            @empty
              <div class="text-center text-muted py-3 small">No contacts on this CRM account yet.</div>
            @endforelse
          </div>
        </div>
      </div>

      {{-- CRM internal team --}}
      <div class="col-12 col-lg-4">
        <div class="cp-card h-100">
          <div class="cp-card-head"><h6><i class="bx bx-group"></i> Our Team (HRX)</h6></div>
          <div class="cp-card-body">
            @forelse(\App\Models\CrmClientMappingTeamMember::ROLE_LABELS as $__role => $__label)
              @php $__slot = $crmTeam->get($__role, collect())->first(); @endphp
              <div class="cp-team-slot">
                <div class="cp-team-avatar" style="background:{{ $__slot ? '#127464' : '#cbd5e1' }};">{{ $__slot ? strtoupper(substr($__slot->user->first_name ?? '?', 0, 1) . substr($__slot->user->last_name ?? '', 0, 1)) : '—' }}</div>
                <div class="flex-grow-1 min-width-0">
                  <div class="cp-party-name" style="font-size:.8rem;">{{ $__slot ? trim(($__slot->user->first_name ?? '').' '.($__slot->user->last_name ?? '')) : 'Not assigned' }}</div>
                  <div class="lg-team-role">{{ $__label }}</div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      {{-- CRM open follow-ups --}}
      <div class="col-12 col-lg-4">
        <div class="cp-card h-100">
          <div class="cp-card-head"><h6><i class="bx bx-task"></i> Open Tasks</h6><span class="cp-sub">{{ $crmFollowUps->count() }}</span></div>
          <div class="cp-card-body">
            @forelse($crmFollowUps as $fu)
              <div class="cp-contact-row">
                <div class="small {{ $fu->follow_up_due_at && $fu->follow_up_due_at->isPast() ? 'cp-overdue' : '' }}" style="font-weight:600;">
                  {{ $fu->follow_up_due_at ? $fu->follow_up_due_at->format('d M Y') : 'No due date' }}
                </div>
                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($fu->body, 60) }}</div>
              </div>
            @empty
              <div class="text-center text-muted py-3 small">No open follow-ups.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>

    {{-- CRM activity & notes feed --}}
    <div class="cp-card mb-4">
      <div class="cp-card-head"><h6><i class="bx bx-history"></i> CRM Activity &amp; Notes</h6><span class="cp-sub">calls and notes logged on the CRM account</span></div>
      <div class="cp-card-body">
        @forelse($crmActivities as $activity)
          @include('tenant.crm-clients.partials.profile-activity-row', ['activity' => $activity])
        @empty
          <div class="text-center text-muted py-3 small">No activity logged on this CRM account yet.</div>
        @endforelse
      </div>
    </div>
    @endif

    <div class="row g-4 mb-4">
      {{-- Outstanding bills + ageing --}}
      @if($outstanding->isNotEmpty())
      <div class="col-12 col-xl-7">
        <div class="cp-card h-100">
          <div class="cp-card-head"><h6><i class="bx bx-time-five"></i> Outstanding ({{ $outstanding->count() }} bill{{ $outstanding->count() === 1 ? '' : 's' }})</h6>
            <span class="cp-sub">
              @foreach($ageing as $b => $amt)@if($amt != 0)<span class="lg-age">{{ $b }}d: {{ $c($amt) }}</span>@endif @endforeach
            </span>
          </div>
          <div class="table-responsive" style="max-height:360px;overflow:auto;">
            <table class="table table-sm align-middle mb-0">
              <thead style="position:sticky;top:0;"><tr><th>Bill</th><th>Date</th><th class="lg-num">Age (d)</th><th class="lg-num">Terms</th><th class="lg-num">Amount (₹)</th></tr></thead>
              <tbody>
              @foreach($outstanding as $b)
                @php $age = $b->bill_date ? (int) \Illuminate\Support\Carbon::parse($b->bill_date)->startOfDay()->diffInDays(now()->startOfDay()) : null; @endphp
                <tr>
                  <td class="fw-semibold">{{ $b->bill_ref ?: '—' }}</td>
                  <td class="cp-sub">{{ $b->bill_date ? \Illuminate\Support\Carbon::parse($b->bill_date)->format('d M Y') : '—' }}</td>
                  <td class="lg-num {{ $age !== null && $age > 90 ? 'text-danger fw-semibold' : '' }}">{{ $age ?? '—' }}</td>
                  <td class="lg-num cp-sub">{{ $b->credit_days !== null ? $b->credit_days : '—' }}</td>
                  <td class="lg-num fw-semibold">{{ number_format((float)$b->amount, 0) }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      @endif

      {{-- Recent activity feed --}}
      <div class="col-12 {{ $outstanding->isNotEmpty() ? 'col-xl-5' : '' }}">
        <div class="cp-card h-100">
          <div class="cp-card-head"><h6><i class="bx bx-history"></i> Recent activity</h6></div>
          <div class="cp-card-body">
            @forelse($feed as $f)
              <div class="lg-feed">
                <span class="lg-feed-dot {{ $f['dir'] }}"></span>
                <div class="lg-feed-main">
                  <div class="lg-feed-top"><span class="lg-feed-type">{{ $f['type'] }}</span> <span class="lg-feed-ref">{{ $f['ref'] }}</span></div>
                  <div class="cp-sub">{{ $f['date'] ? \Illuminate\Support\Carbon::parse($f['date'])->format('d M Y') : '—' }}@if(!empty($f['note'])) · {{ \Illuminate\Support\Str::limit($f['note'], 24) }}@endif</div>
                </div>
                <div class="lg-feed-amt {{ $f['dir'] }}">{{ $f['dir'] === 'in' ? '+' : '−' }}{{ number_format($f['amount'], 0) }}</div>
              </div>
            @empty
              <div class="text-center text-muted py-4">No transactions on record.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>

    {{-- Delivery activity --}}
    @if($deliveries->isNotEmpty())
    <div class="cp-card mb-4">
      <div class="cp-card-head"><h6><i class="bx bx-package"></i> Recent deliveries</h6><span class="cp-sub">how goods shipped</span></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Date</th><th>Delivery No</th><th>Item</th><th class="lg-num">Qty</th><th>Transporter</th><th>Vehicle</th><th>Consignee</th></tr></thead>
          <tbody>
          @foreach($deliveries as $d)
            <tr>
              <td class="cp-sub">{{ $d->delivery_date ? \Illuminate\Support\Carbon::parse($d->delivery_date)->format('d M Y') : '—' }}</td>
              <td class="fw-semibold">{{ $d->delivery_no }}</td>
              <td>{{ \Illuminate\Support\Str::limit($d->item_name, 30) ?: '—' }}</td>
              <td class="lg-num">{{ $d->quantity !== null ? number_format((float)$d->quantity, 2) : '—' }} <span class="cp-sub">{{ $d->unit }}</span></td>
              <td class="cp-sub">{{ $d->transporter_name ?: '—' }}</td>
              <td class="cp-sub">{{ $d->vehicle_number ?: '—' }}</td>
              <td class="cp-sub">{{ \Illuminate\Support\Str::limit($d->consignee_name, 20) ?: '—' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

  </div>
</div>

<script>
  window.LG_TREND = @json($trend->values());
</script>
<script>
(function () {
  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  const inr = v => '₹' + Number(v||0).toLocaleString('en-IN', {maximumFractionDigits:0});
  const monthLabel = ym => { const [y,m] = ym.split('-'); return new Date(y, m-1, 1).toLocaleDateString('en-GB',{month:'short',year:'2-digit'}); };
  ready(function () {
    if (typeof ApexCharts === 'undefined') return;
    const t = window.LG_TREND || [];
    const el = document.querySelector('#lgTrend');
    if (!el || !t.length) return;
    const hasSales = t.some(r => r.sales), hasPurch = t.some(r => r.purchases);
    const series = [];
    if (hasSales) series.push({ name: 'Sales', data: t.map(r => Math.round(r.sales)) });
    if (hasPurch) series.push({ name: 'Purchases', data: t.map(r => Math.round(r.purchases)) });
    new ApexCharts(el, {
      chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
      series: series,
      xaxis: { categories: t.map(r => monthLabel(r.ym)), labels: { rotate: -45, style: { fontSize: '11px' } } },
      yaxis: { labels: { formatter: v => inr(v) } },
      colors: ['#127464', '#6366f1'],
      plotOptions: { bar: { borderRadius: 4, columnWidth: '58%' } },
      dataLabels: { enabled: false },
      legend: { position: 'top' },
      tooltip: { y: { formatter: v => inr(v) } },
      grid: { borderColor: '#eef2f6' },
    }).render();
  });
})();
</script>
@endsection
