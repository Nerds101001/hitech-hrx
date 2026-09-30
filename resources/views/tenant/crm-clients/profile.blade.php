@extends('layouts.layoutMaster')

@section('title', $mapping->crm_company_name . ' — Customer Profile')

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss', 'resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection
@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js', 'resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@section('page-style')
<style>
    /* ── Customer Profile: modern, calm, alive ──────────────────────────────
       A banner header with the key figures in a glass strip, white cards with
       soft, low-contrast depth, tinted icon tiles, and a little motion.
       Buttons stay flat (no gradients, no glow, no lift). Everything is scoped to
       this page (#cpPage) and its dialogs (.cp-modal); the ID scope is what wins
       over the portal-wide !important button/card rules. */
    #cpPage, .cp-modal {
        --cp-ink: #0f172a; --cp-text: #334155; --cp-muted: #64748b; --cp-faint: #94a3b8;
        --cp-line: #e8edf3; --cp-line-strong: #d3dbe5; --cp-soft: #f6f8fb; --cp-surface: #fff;
        --cp-brand: #127464; --cp-brand-dark: #0e5f52; --cp-brand-soft: #eaf6f3; --cp-brand-line: #cfe8e1;
        --cp-ok: #067647; --cp-ok-soft: #ecfdf3; --cp-ok-line: #bfe9d2;
        --cp-warn: #b54708; --cp-warn-soft: #fffaeb; --cp-warn-line: #fedf89;
        --cp-bad: #b42318; --cp-bad-soft: #fef3f2; --cp-bad-line: #fbd0cc;
        --cp-shadow: 0 1px 2px rgba(16,24,40,.04), 0 6px 20px rgba(16,24,40,.05);
        --cp-shadow-hover: 0 2px 4px rgba(16,24,40,.05), 0 12px 28px rgba(16,24,40,.08);
    }
    #cpPage { color: var(--cp-text); }
    #cpPage .text-muted { color: var(--cp-muted) !important; }
    #cpPage .border { border-color: var(--cp-line) !important; }
    #cpPage a { transition: color .15s ease; }

    /* ── Header banner ─────────────────────────────────────────────────── */
    #cpPage .cp-hero {
        position: relative; isolation: isolate; margin-bottom: 1.25rem; border-radius: 22px; overflow: hidden; color: #fff;
        background: #0a2740; box-shadow: 0 14px 34px rgba(10,39,64,.20);
        animation: cpRise .5s ease backwards;
    }
    #cpPage .cp-hero-bg {
        position: absolute; inset: 0; z-index: -1;
        background:
            radial-gradient(900px 380px at 88% -12%, rgba(45,212,191,.40), transparent 62%),
            radial-gradient(720px 420px at 8% 118%, rgba(59,130,246,.44), transparent 62%),
            radial-gradient(520px 300px at 52% -8%, rgba(99,102,241,.30), transparent 66%),
            linear-gradient(115deg, #0a2740 0%, #0d4266 48%, #0f6b78 100%);
    }
    #cpPage .cp-hero-bg::before {          /* blueprint grid, fading out downwards */
        content: ''; position: absolute; inset: 0; opacity: .55;
        background-image: linear-gradient(rgba(255,255,255,.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.07) 1px, transparent 1px);
        background-size: 46px 46px;
        -webkit-mask-image: linear-gradient(180deg, #000 0%, transparent 82%); mask-image: linear-gradient(180deg, #000 0%, transparent 82%);
    }
    #cpPage .cp-hero-bg::after {           /* one slow, soft light */
        content: ''; position: absolute; right: -140px; top: -230px; width: 560px; height: 560px; border-radius: 50%;
        background: radial-gradient(circle, rgba(94,234,212,.30), transparent 66%); filter: blur(8px);
        animation: cpDrift 16s ease-in-out infinite alternate;
    }
    @keyframes cpDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(-70px,46px) scale(1.14); } }

    #cpPage .cp-hero-top { position: relative; display: flex; align-items: center; gap: 1.5rem; padding: 1.9rem 2.1rem 1.6rem; }
    #cpPage .cp-hero-logo {
        width: 92px; height: 92px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 22px;
        background: #fff; color: #0f4c75; font-size: 2rem; font-weight: 800; letter-spacing: .01em;
        box-shadow: 0 10px 28px rgba(0,0,0,.20);
    }
    #cpPage .cp-hero-info { min-width: 0; flex: 1; }
    #cpPage .cp-hero-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: .55rem; font-size: .8rem; color: rgba(255,255,255,.80); }
    #cpPage .cp-hero-since i { margin-right: 4px; opacity: .85; }
    #cpPage .cp-hero-title { margin: 0 0 .8rem; font-size: 2rem; font-weight: 700; line-height: 1.15; letter-spacing: -.025em; color: #fff !important; }
    #cpPage .cp-hero-tags { display: flex; flex-wrap: wrap; gap: .5rem; }
    #cpPage .cp-tag {
        display: inline-flex; align-items: center; gap: .4rem; padding: 5px 12px; border-radius: 999px; font-size: .78rem; font-weight: 500; color: #fff;
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
    }
    #cpPage .cp-tag i { font-size: .95rem; opacity: .85; }
    #cpPage .cp-tag b { padding: 1px 8px; border-radius: 999px; background: rgba(96,165,250,.34); border: 1px solid rgba(147,197,253,.42); color: #dbeafe; font-size: .68rem; font-weight: 600; }
    #cpPage .cp-hero .cp-pill {
        display: inline-flex; align-items: center; gap: 6px; padding: 3px 11px; border-radius: 999px; font-size: .75rem; font-weight: 600;
        background: rgba(34,197,94,.20); border: 1px solid rgba(74,222,128,.42); color: #bbf7d0;
    }
    #cpPage .cp-hero .cp-pill::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 0 3px rgba(74,222,128,.22); }
    #cpPage .cp-hero .cp-pill.cp-pill-dropped { background: rgba(239,68,68,.20); border-color: rgba(252,165,165,.42); color: #fecaca; }
    #cpPage .cp-hero .cp-pill.cp-pill-dropped::before { background: #f87171; box-shadow: 0 0 0 3px rgba(248,113,113,.22); }
    #cpPage .cp-hero-actions { position: absolute; top: 1.5rem; right: 2.1rem; display: flex; gap: .6rem; }
    #cpPage .cp-hero-contact { margin-top: .5rem; }
    #cpPage a.cp-tag { text-decoration: none; transition: background-color .15s ease; }
    #cpPage a.cp-tag:hover { background: rgba(255,255,255,.22); color: #fff; }
    #cpPage .cp-tag { min-width: 0; }
    #cpPage .cp-tag > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #cpPage .cp-tag-wide { max-width: 440px; }
    #cpPage .cp-tag-wide > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #cpPage .cp-hero-quote { position: absolute; right: 2.3rem; top: 5.6rem; width: min(330px, 28%); display: flex; align-items: flex-start; gap: .8rem; }
    #cpPage .cp-hero-quote > i { font-size: 2.1rem; line-height: 1; color: #5eead4; opacity: .95; }
    #cpPage .cp-hero-quote p { margin: 0 0 .4rem; font-size: 1.05rem; font-style: italic; font-weight: 500; line-height: 1.45; color: #fff; }
    #cpPage .cp-hero-quote span { font-size: .68rem; letter-spacing: .1em; color: rgba(255,255,255,.60); }
    @media (min-width: 1200px) { #cpPage .cp-hero-info { padding-right: 350px; } }
    @media (max-width: 1199px) { #cpPage .cp-hero-quote { display: none !important; } }
    @media (min-width: 992px) and (max-width: 1199px) { #cpPage .cp-hero-meta { padding-right: 310px; } }
    @media (max-width: 575px) { #cpPage .cp-mini-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    /* key figures: a glass strip along the bottom of the banner */
    #cpPage .cp-kpis {
        position: relative; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); margin: 0 1.6rem 1.6rem; padding: 1.15rem .3rem; border-radius: 18px;
        background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.16);
        -webkit-backdrop-filter: blur(14px) saturate(140%); backdrop-filter: blur(14px) saturate(140%);
    }
    #cpPage .cp-kpi { display: flex; align-items: center; gap: .9rem; min-width: 0; padding: .2rem 1.25rem; }
    #cpPage .cp-kpi + .cp-kpi { border-left: 1px solid rgba(255,255,255,.15); }
    #cpPage .cp-kpi-icon { width: 46px; height: 46px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 13px; font-size: 1.4rem; }
    #cpPage .tone-blue   { background: #e3edff; color: #2563eb; }
    #cpPage .tone-violet { background: #ece8ff; color: #7c3aed; }
    #cpPage .tone-green  { background: #dcfce9; color: #16a34a; }
    #cpPage .tone-cyan   { background: #d5f5fb; color: #0891b2; }
    #cpPage .tone-amber  { background: #ffead6; color: #ea580c; }
    #cpPage .cp-kpi-body { min-width: 0; }
    #cpPage .cp-kpi-value { display: flex; flex-wrap: wrap; align-items: baseline; gap: .1rem .5rem; font-size: 1.55rem; font-weight: 700; line-height: 1.15; letter-spacing: -.02em; color: #fff; font-variant-numeric: tabular-nums; }
    #cpPage .cp-kpi-value.is-text { font-size: 1.2rem; }
    #cpPage .cp-kpi-label { margin-top: 2px; font-size: .82rem; color: rgba(255,255,255,.88); }
    #cpPage .cp-kpi-sub { font-size: .72rem; line-height: 1.4; color: rgba(255,255,255,.64); }
    #cpPage .cp-trend { display: inline-flex; align-items: center; padding: 1px 8px 1px 3px; border-radius: 999px; font-size: .7rem; font-weight: 600; letter-spacing: 0; }
    #cpPage .cp-trend.up   { background: rgba(34,197,94,.22);  border: 1px solid rgba(134,239,172,.32); color: #bbf7d0; }
    #cpPage .cp-trend.down { background: rgba(239,68,68,.22); border: 1px solid rgba(252,165,165,.32); color: #fecaca; }
    @media (max-width: 1199px) {
        #cpPage .cp-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); row-gap: 1.1rem; }
        #cpPage .cp-kpi:nth-child(3n+1) { border-left: 0; }
    }
    @media (max-width: 991px) {
        #cpPage .cp-hero-top { flex-wrap: wrap; padding: 1.4rem 1.3rem 1.2rem; }
        #cpPage .cp-hero-actions { position: static; width: 100%; }
        #cpPage .cp-hero-title { font-size: 1.6rem; }
        #cpPage .cp-kpis { margin: 0 1rem 1rem; }
    }

    /* ── Tabs: plain text, green underline ─────────────────────────────── */
    #cpPage .cp-tabs { gap: .35rem; border-bottom: 1px solid var(--cp-line); flex-wrap: nowrap; overflow-x: auto; }
    #cpPage .cp-tabs .nav-item { flex-shrink: 0; }
    #cpPage .cp-tabs .nav-link {
        margin-bottom: -1px; padding: .8rem 1rem; border: 0; border-bottom: 2px solid transparent; border-radius: 0;
        background: transparent !important; box-shadow: none !important; transform: none !important;
        font-size: .88rem; font-weight: 500; color: var(--cp-muted); white-space: nowrap; transition: color .15s ease, border-color .15s ease;
    }
    #cpPage .cp-tabs .nav-link i { font-size: 1rem; vertical-align: -2px; opacity: .7; }
    #cpPage .cp-tabs .nav-link.active i, #cpPage .cp-tabs .nav-link:hover i { opacity: 1; }
    #cpPage .cp-tabs .nav-link:hover { color: var(--cp-ink); border-bottom-color: var(--cp-line-strong); }
    #cpPage .cp-tabs .nav-link.active { color: var(--cp-brand); font-weight: 600; border-bottom-color: var(--cp-brand); }

    /* ── Cards ─────────────────────────────────────────────────────────── */
    #cpPage .card, #cpPage .cp-card {
        background: var(--cp-surface); border: 1px solid #edf1f5 !important; border-radius: 16px !important;
        box-shadow: var(--cp-shadow) !important; transform: none !important;
        transition: box-shadow .25s ease, border-color .25s ease !important;
    }
    #cpPage .cp-card:hover { box-shadow: var(--cp-shadow-hover) !important; border-color: var(--cp-line) !important; }
    #cpPage .cp-card > .card-body { padding: 1.3rem 1.45rem; }
    #cpPage .cp-card { animation: cpRise .55s ease backwards; }
    #cpPage .col-lg-4:nth-child(2) .cp-card { animation-delay: .07s; }
    #cpPage .col-lg-4:nth-child(3) .cp-card { animation-delay: .14s; }
    #cpPage .col-lg-4 .cp-card:nth-child(2) { animation-delay: .12s; }
    #cpPage .col-lg-4 .cp-card:nth-child(3) { animation-delay: .2s; }
    @keyframes cpRise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    #cpPage .cp-card h6 { display: flex; align-items: center; gap: .65rem; font-size: .95rem; font-weight: 600 !important; letter-spacing: -.005em; color: var(--cp-ink); }
    #cpPage .cp-card h6 > i.bx {
        width: 30px; height: 30px; margin: 0 !important; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
        border-radius: 9px; font-size: 1.05rem; background: rgba(18,116,100,.10);
    }
    @supports (background: color-mix(in srgb, red 10%, transparent)) {
        #cpPage .cp-card h6 > i.bx { background: color-mix(in srgb, currentColor 12%, transparent); }
    }
    #cpPage .cp-stage { display: inline-block; padding: 2px 10px; border-radius: 999px; background: #eaf1ff; color: #2563eb; font-size: .74rem; font-weight: 600; }

    /* small figures under the sales chart */
    #cpPage .cp-mini-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; margin-top: 1.1rem; }
    #cpPage .cp-mini { padding: .8rem .35rem .7rem; text-align: center; border: 1px solid #edf1f5; border-radius: 12px; background: var(--cp-soft); transition: transform .2s ease, box-shadow .2s ease; }
    #cpPage .cp-mini:hover { transform: translateY(-1px); box-shadow: var(--cp-shadow); }
    #cpPage .cp-mini-icon { width: 34px; height: 34px; margin: 0 auto .45rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 1.05rem; }
    #cpPage .cp-mini-val { font-size: .98rem; font-weight: 700; color: var(--cp-ink); font-variant-numeric: tabular-nums; }
    #cpPage .cp-mini-lbl { font-size: .68rem; color: var(--cp-muted); }

    /* bottom call-to-action band */
    #cpPage .cp-cta {
        display: flex; align-items: center; gap: 1rem; margin-top: 1.6rem; padding: 1rem 1.3rem; border-radius: 16px;
        background: linear-gradient(90deg, #e9f7f2, #f2faf8); border: 1px solid #d3ede4; animation: cpRise .6s ease .2s backwards;
    }
    #cpPage .cp-cta-icon { width: 48px; height: 48px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 14px; background: var(--cp-brand); color: #fff; font-size: 1.5rem; }
    #cpPage .cp-cta-text strong { display: block; font-size: .95rem; font-weight: 600; color: var(--cp-ink); }
    #cpPage .cp-cta-text span { font-size: .82rem; color: var(--cp-muted); }
    #cpPage .cp-cta-actions { display: flex; gap: .6rem; margin-left: auto; }
    @media (max-width: 767px) { #cpPage .cp-cta { flex-wrap: wrap; } #cpPage .cp-cta-actions { width: 100%; margin-left: 0; } }

    /* ── Buttons: flat, no gradient / glow / lift ──────────────────────── */
    #cpPage .btn:not(.btn-close), .cp-modal.modal .btn:not(.btn-close) {
        min-height: 36px !important; padding: .45rem 1rem !important; gap: .4rem !important;
        border-width: 1px !important; border-style: solid !important; border-radius: 10px !important;
        font-size: .83rem !important; font-weight: 600 !important; letter-spacing: 0 !important;
        background-image: none !important; box-shadow: none !important; transform: none !important;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease !important;
    }
    #cpPage .btn-sm, .cp-modal.modal .btn-sm { min-height: 30px !important; padding: .3rem .75rem !important; font-size: .78rem !important; }
    #cpPage .btn:focus-visible, .cp-modal.modal .btn:focus-visible { outline: 0 !important; box-shadow: 0 0 0 3px rgba(18,116,100,.22) !important; }
    #cpPage .btn-primary, #cpPage .btn-success, .cp-modal.modal .btn-primary, .cp-modal.modal .btn-success {
        background-color: var(--cp-brand) !important; border-color: var(--cp-brand) !important; color: #fff !important;
    }
    #cpPage .btn-primary:hover, #cpPage .btn-success:hover, .cp-modal.modal .btn-primary:hover, .cp-modal.modal .btn-success:hover {
        background-color: var(--cp-brand-dark) !important; border-color: var(--cp-brand-dark) !important; color: #fff !important; box-shadow: 0 4px 12px rgba(18,116,100,.25) !important;
    }
    #cpPage .cp-hero .btn-primary { background-color: #10a37f !important; border-color: #10a37f !important; }
    #cpPage .cp-hero .btn-primary:hover { background-color: #0d8c6d !important; border-color: #0d8c6d !important; }
    #cpPage .btn-outline-secondary, #cpPage .btn-outline-primary, #cpPage .btn-secondary, #cpPage .btn-light,
    .cp-modal.modal .btn-outline-secondary, .cp-modal.modal .btn-secondary, .cp-modal.modal .btn-light {
        background-color: #fff !important; border-color: var(--cp-line-strong) !important; color: var(--cp-text) !important;
    }
    #cpPage .btn-outline-secondary:hover, #cpPage .btn-outline-primary:hover, #cpPage .btn-secondary:hover, #cpPage .btn-light:hover,
    .cp-modal.modal .btn-outline-secondary:hover, .cp-modal.modal .btn-secondary:hover, .cp-modal.modal .btn-light:hover {
        background-color: var(--cp-soft) !important; border-color: #c2ccd8 !important; color: var(--cp-ink) !important;
    }
    #cpPage .cp-hero .btn-light { border-color: transparent !important; color: var(--cp-ink) !important; }
    #cpPage .btn-outline-success { background-color: #fff !important; border-color: var(--cp-ok-line) !important; color: var(--cp-ok) !important; }
    #cpPage .btn-outline-success:hover { background-color: var(--cp-ok-soft) !important; border-color: #9fdcbd !important; color: var(--cp-ok) !important; }
    #cpPage .btn-outline-danger, .cp-modal.modal .btn-outline-danger { background-color: #fff !important; border-color: var(--cp-bad-line) !important; color: var(--cp-bad) !important; }
    #cpPage .btn-outline-danger:hover, .cp-modal.modal .btn-outline-danger:hover { background-color: var(--cp-bad-soft) !important; border-color: #f6b4ae !important; color: var(--cp-bad) !important; }
    #cpPage .btn.btn-icon { width: 28px; height: 28px; min-height: 0 !important; padding: 0 !important; border-radius: 50% !important; }
    #cpPage .btn.p-0, #cpPage .btn.border-0 { min-height: 0 !important; padding: 0 !important; background-color: transparent !important; border-color: transparent !important; }
    #cpPage .btn.cp-add-btn { width: 32px; height: 32px; min-height: 32px !important; padding: 0 !important; background-color: var(--cp-brand-soft) !important; border-color: var(--cp-brand-line) !important; color: var(--cp-brand) !important; }
    #cpPage .btn.cp-add-btn:hover { background-color: var(--cp-brand) !important; border-color: var(--cp-brand) !important; color: #fff !important; box-shadow: none !important; }
    /* chart range: a segmented control (the script swaps btn-primary / btn-outline-secondary on click) */
    #cpPage .btn-group { padding: 3px; gap: 2px; background: var(--cp-soft); border: 1px solid var(--cp-line); border-radius: 11px; }
    #cpPage .btn-group .btn { min-height: 26px !important; padding: .1rem .7rem !important; border-color: transparent !important; border-radius: 8px !important; background-color: transparent !important; color: var(--cp-muted) !important; font-size: .74rem !important; }
    #cpPage .btn-group .btn:hover { color: var(--cp-ink) !important; background-color: transparent !important; box-shadow: none !important; }
    #cpPage .btn-group .btn.btn-primary { background-color: #fff !important; border-color: var(--cp-line) !important; color: var(--cp-brand) !important; box-shadow: 0 1px 2px rgba(16,24,40,.06) !important; }

    /* ── Lists and rows ────────────────────────────────────────────────── */
    #cpPage .table-borderless td { padding: .65rem .25rem; border: 0; font-size: .86rem; }
    #cpPage .table-borderless tr + tr td { border-top: 1px solid #f1f4f8; }
    #cpPage .table td, #cpPage .table th { padding: .65rem .85rem; }
    #cpPage .table thead th { background: var(--cp-soft); font-size: .68rem; font-weight: 600; letter-spacing: .05em; color: var(--cp-muted); border-bottom: 1px solid var(--cp-line); }
    #cpPage .table:not(.table-borderless) tbody td { font-size: .84rem; color: var(--cp-text); border-bottom: 1px solid #f1f4f8; }
    #cpPage .table:not(.table-borderless) tbody tr:last-child td { border-bottom: 0; }
    #cpPage .table:not(.table-borderless) tbody tr { transition: background-color .15s ease; }
    #cpPage .table:not(.table-borderless) tbody tr:hover { background: #f8fafc; }
    #cpPage .cp-health-ring { width: 108px; height: 108px; }
    #cpPage .cp-quick-btn {
        display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; padding: .8rem .4rem;
        border: 1px solid var(--cp-line); border-radius: 12px; background: #fff; color: var(--cp-text);
        font-size: .72rem; font-weight: 600; text-decoration: none; transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
    }
    #cpPage .cp-quick-btn:hover { background: var(--cp-soft); border-color: var(--cp-line-strong); color: var(--cp-ink); transform: translateY(-1px); }
    #cpPage .cp-quick-btn i { font-size: 1.3rem; }
    #cpPage .cp-quick-btn.cp-quick-call { background: var(--cp-brand); border-color: var(--cp-brand); color: #fff; }
    #cpPage .cp-quick-btn.cp-quick-call:hover { background: var(--cp-brand-dark); border-color: var(--cp-brand-dark); color: #fff; }
    #cpPage .cp-quick-btn.cp-quick-whatsapp i { color: #16a34a; }
    #cpPage .cp-quick-btn.cp-quick-email i { color: #2563eb; }
    #cpPage .cp-followup-empty { padding: 1.2rem 0; text-align: center; font-size: .8rem; color: var(--cp-muted); }
    #cpPage .cp-contact-row, #cpPage .cp-doc-row { margin-bottom: 8px; padding: 13px 14px; border: 1px solid var(--cp-line); border-radius: 13px; transition: border-color .15s ease, background-color .15s ease; }
    #cpPage .cp-contact-row:hover, #cpPage .cp-doc-row:hover { border-color: var(--cp-line-strong); background: #fbfcfd; }
    #cpPage .cp-avatar { width: 40px; height: 40px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #e3edff; color: #2563eb; font-size: .8rem; font-weight: 600; }
    #cpPage .cp-team-slot { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f4f8; }
    #cpPage .cp-team-slot:last-child { border-bottom: 0; }
    #cpPage .cp-team-avatar { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .66rem; font-weight: 600; box-shadow: 0 0 0 3px #fff, 0 0 0 4px #edf1f5; }
    #cpPage .cp-icon-btn { width: 28px; height: 28px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--cp-line); border-radius: 50%; background: var(--cp-soft); color: var(--cp-muted); font-size: .72rem; }
    #cpPage .cp-icon-btn:hover { background: #eef2f6; }
    #cpPage .cp-icon-btn.cp-icon-call { background: var(--cp-ok-soft); border-color: var(--cp-ok-line); color: var(--cp-ok); }
    #cpPage .cp-call-list-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f4f8; }
    #cpPage .cp-call-list-row:last-child { border-bottom: 0; }
    #cpPage .cp-call-icon { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .95rem; }
    #cpPage .cp-status-chip { padding: 3px 10px; border-radius: 999px; font-size: .68rem; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
    #cpPage .cp-status-logged  { background: var(--cp-ok-soft);   color: var(--cp-ok); }
    #cpPage .cp-status-pending { background: var(--cp-warn-soft); color: var(--cp-warn); }
    /* feed: one line per entry (click a row for the full text) */
    #cpPage .activity-item { display: flex; align-items: center; gap: .7rem; padding: .5rem .35rem; border-bottom: 1px solid #f1f4f8; border-radius: 8px; cursor: pointer; transition: background-color .15s ease; }
    #cpPage .activity-item:hover { background: #f8fafc; }
    #cpPage .activity-item:last-child { border-bottom: 0; }
    #cpPage .activity-icon { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .8rem; color: #fff; }
    #cpPage .activity-main { flex: 1; min-width: 0; display: flex; align-items: center; gap: .6rem; }
    #cpPage .activity-who { flex-shrink: 0; font-size: .83rem; font-weight: 600; color: var(--cp-ink); }
    #cpPage .activity-text { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .83rem; color: var(--cp-text); }
    #cpPage .activity-full { display: none; font-size: .83rem; white-space: pre-line; color: var(--cp-text); }
    #cpPage .activity-time { flex-shrink: 0; font-size: .72rem; color: var(--cp-muted); white-space: nowrap; }
    #cpPage .activity-time .t-short { display: none; }
    #cpPage .activity-item .follow-up-chip { flex-shrink: 0; white-space: nowrap; }
    #cpPage .follow-up-chip .fu-date::before { content: ' — '; }
    #cpPage .follow-up-chip .fu-short { display: none; }
    #cpPage .activity-item.is-open { align-items: flex-start; }
    #cpPage .activity-item.is-open .activity-main { flex-wrap: wrap; row-gap: .15rem; }
    #cpPage .activity-item.is-open .activity-text { display: none; }
    #cpPage .activity-item.is-open .activity-full { display: block; flex-basis: 100%; }
    @media (max-width: 575px) { #cpPage .activity-main { flex-wrap: wrap; row-gap: .1rem; } #cpPage .activity-text { flex-basis: 100%; } }
    /* calls tab: both sides of every call */
    #cpPage .cp-call-table td { padding: .55rem .85rem; vertical-align: middle; }
    #cpPage .cp-clamp { max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #cpPage .cp-party-name { font-size: .84rem; font-weight: 600; line-height: 1.25; color: var(--cp-ink); }
    #cpPage .cp-party-role { margin-left: .4rem; padding: 0 7px; border-radius: 999px; background: #f3edff; color: #6d28d9; font-size: .66rem; font-weight: 600; }
    #cpPage .cp-party-meta { display: flex; flex-wrap: nowrap; gap: .1rem .8rem; font-size: .74rem; }
    #cpPage .cp-party-meta a { color: var(--cp-muted); text-decoration: none; white-space: nowrap; }
    #cpPage .cp-party-meta a:hover { color: var(--cp-brand); }
    #cpPage .cp-party-meta i { margin-right: 3px; opacity: .8; }
    @media (max-width: 1199px) {   /* below full desktop width: each call becomes a small card instead of a wide table */
        #cpPage .cp-call-table thead { display: none; }
        #cpPage .cp-party-meta { flex-wrap: wrap; }
        #cpPage .cp-call-table, #cpPage .cp-call-table tbody, #cpPage .cp-call-table tr, #cpPage .cp-call-table td { display: block; width: 100%; }
        #cpPage .cp-call-table tr { position: relative; padding: .7rem 0; border-bottom: 1px solid #f1f4f8; }
        #cpPage .cp-call-table td { min-width: 0 !important; padding: .2rem 0 !important; border: 0 !important; }
        #cpPage .cp-call-table td[data-label]:not([data-label="Call"])::before { content: attr(data-label); display: block; font-size: .64rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--cp-faint); }
        #cpPage .cp-call-table .cp-td-status { position: absolute; top: .7rem; right: 0; width: auto; }
        #cpPage .cp-call-table td[data-label="Call"] { padding-right: 6.5rem !important; }
        #cpPage .cp-clamp { max-width: 100%; }
    }
    #cpPage .activity-icon.type-call    { background: #16a34a; }
    #cpPage .activity-icon.type-comment { background: #3b82f6; }
    #cpPage .activity-icon.type-system  { background: #94a3b8; }
    #cpPage .follow-up-chip { padding: 2px 9px; border-radius: 999px; background: var(--cp-warn-soft); color: var(--cp-warn); font-size: .72rem; font-weight: 600; }
    #cpPage .role-purchase { background: #f3edff !important; color: #6d28d9; }
    #cpPage .role-finance  { background: #fdf2f8 !important; color: #be185d; }
    #cpPage .role-accounts { background: var(--cp-ok-soft) !important; color: var(--cp-ok); }
    #cpPage .role-admin    { background: #eff6ff !important; color: #1d4ed8; }
    #cpPage .role-other    { background: #f1f5f9 !important; color: var(--cp-muted); }
    #cpPage .alert { border-radius: 10px; border: 1px solid; box-shadow: none; }
    #cpPage .alert-info { background: #f0f9ff; border-color: #cfe9fb; color: #075985; }
    #cpPage .badge { font-weight: 600; }

    /* ── Forms and dialogs ─────────────────────────────────────────────── */
    #cpPage .form-control, #cpPage .form-select, .cp-modal .form-control, .cp-modal .form-select {
        padding: .55rem .85rem !important; border: 1px solid #d5dde6 !important; border-radius: 10px !important;
        background-color: #fff !important; box-shadow: none !important; font-size: .85rem; font-weight: 400; color: var(--cp-ink);
    }
    #cpPage .form-control:focus, #cpPage .form-select:focus, .cp-modal .form-control:focus, .cp-modal .form-select:focus {
        border-color: var(--cp-brand) !important; box-shadow: 0 0 0 3px rgba(18,116,100,.14) !important;
    }
    .cp-modal .modal-content { border: 1px solid var(--cp-line) !important; border-radius: 18px !important; box-shadow: 0 18px 44px rgba(15,23,42,.16) !important; }
    .cp-modal .modal-header { min-height: 0 !important; padding: 1.05rem 1.4rem !important; background: #fff !important; border-bottom: 1px solid var(--cp-line) !important; }
    .cp-modal .modal-header .modal-title { color: var(--cp-ink) !important; font-size: .98rem !important; font-weight: 600 !important; letter-spacing: 0 !important; text-transform: none !important; }
    .cp-modal .modal-header .btn-close { padding: .55rem !important; border-radius: 8px !important; background-color: transparent !important; filter: none !important; opacity: .55 !important; }
    .cp-modal .modal-header .btn-close:hover { background-color: var(--cp-soft) !important; opacity: 1 !important; }
    .cp-modal .modal-body { padding: 1.3rem 1.4rem !important; }
    .cp-modal .modal-footer { padding: .95rem 1.4rem; border-top: 1px solid var(--cp-line); background: #fff; }
    .cp-modal .form-label { font-size: .78rem; font-weight: 600; color: var(--cp-text); margin-bottom: .35rem; }

    /* Overview: cards flow into three balanced columns instead of three stacks of unequal height (which left a big empty area under the short ones) */
    @media (min-width: 768px) {
        #cpPage #cpOverview > .row { display: block; column-count: 2; column-gap: 1.5rem; margin: 0; }
        #cpPage #cpOverview > .row > .col-lg-4 { display: contents; }
        #cpPage #cpOverview > .row .cp-card { break-inside: avoid; margin: 0 0 1.5rem !important; }
    }
    #cpPage .cp-status-manual { background: #eff6ff; color: #2563eb; }
    /* the feed sits in a narrow column card on the Overview: name + flag on the first line, the text under it */
    #cpPage .cp-feed { container-type: inline-size; container-name: cpfeed; }
    @container cpfeed (max-width: 560px) {
        #cpPage .activity-item { align-items: flex-start; }
        #cpPage .activity-main { flex-wrap: wrap; row-gap: .1rem; }
        #cpPage .activity-who { flex: 1 1 0; min-width: 4.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        #cpPage .activity-text { order: 3; flex: 1 1 100%; }
        #cpPage .activity-item .follow-up-chip { order: 2; }
        #cpPage .activity-item .activity-time { padding-top: .1rem; }
        #cpPage .activity-time .t-long { display: none; }
        #cpPage .activity-time .t-short { display: inline; }
    }
    @container cpfeed (max-width: 400px) {   /* very narrow: the follow-up flag shrinks to a bell and a date */
        #cpPage .follow-up-chip.has-date .fu-label { display: none; }
        #cpPage .follow-up-chip.has-date .fu-date::before { content: ''; }
        #cpPage .follow-up-chip.is-done .fu-label { display: none; }
        #cpPage .follow-up-chip.is-done .fu-short { display: inline; }
    }
    #cpOverview .cp-card .d-flex.justify-content-between { flex-wrap: wrap; gap: .5rem; }
    /* note boxes: the field and its button share one line */
    #cpPage .cp-composer { margin-bottom: .75rem; padding: .5rem; background: #f8fafc; border: 1px solid #edf1f5; border-radius: 12px; }
    #cpPage .cp-composer-row { display: flex; align-items: flex-end; gap: .5rem; }
    #cpPage .cp-composer textarea { flex: 1 1 auto; min-width: 0; min-height: 38px; max-height: 120px; padding: .5rem .8rem !important; resize: none; overflow-y: auto; line-height: 1.35; }
    #cpPage .cp-composer .btn { flex-shrink: 0; min-height: 38px !important; padding-left: .85rem !important; padding-right: .85rem !important; }
    #cpPage .cp-composer-extra { display: flex; align-items: center; gap: .3rem; margin: .5rem 0 0; padding-left: 1.7rem; }
    #cpPage .cp-viewall { display: flex; align-items: center; justify-content: center; gap: .3rem; margin-top: .6rem; padding: .5rem; border-radius: 10px; background: var(--cp-brand-soft); color: var(--cp-brand); font-size: .8rem; font-weight: 600; text-decoration: none; transition: background-color .15s ease; }
    #cpPage .cp-viewall:hover { background: #d8eee8; color: var(--cp-brand-dark); }
    #cpPage .cp-overdue { color: var(--cp-bad) !important; font-weight: 600; }

    /* ══ Tablets and phones ═════════════════════════════════════════════════
       992+  three columns; 768-991 two columns; under 768 one column with the most-used cards first;
       under 576 a compact header and a swipeable key-figure row. Tap targets grow on touch screens. */
    @media (min-width: 992px) { #cpPage #cpOverview > .row { column-count: 3; } }
    @media (max-width: 991px) {
        #cpPage .cp-hero-actions { display: flex; gap: .6rem; }
        #cpPage .cp-hero-actions .btn { flex: 1 1 0; min-width: 0; white-space: nowrap; }
        #cpPage .cp-tabs { scroll-snap-type: x proximity; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        #cpPage .cp-tabs::-webkit-scrollbar { display: none; }
        #cpPage .cp-tabs .nav-item { scroll-snap-align: center; }
    }
    @media (max-width: 767px) {
        #cpPage .cp-hero-top { padding: 1.2rem 1.2rem 1rem; }
        #cpPage .cp-hero-logo { width: 72px; height: 72px; border-radius: 18px; font-size: 1.6rem; }
        #cpPage .cp-kpis { display: flex; gap: .6rem; margin: 0 1rem 1rem; padding: .6rem; overflow-x: auto; scroll-snap-type: x proximity; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        #cpPage .cp-kpis::-webkit-scrollbar { display: none; }
        #cpPage .cp-kpi, #cpPage .cp-kpi:nth-child(n) { flex: 0 0 auto; width: min(46%, 260px); gap: .7rem; padding: .6rem .8rem; border: 1px solid rgba(255,255,255,.14); border-radius: 13px; background: rgba(255,255,255,.07); scroll-snap-align: start; }
        #cpPage .cp-kpi-icon { width: 40px; height: 40px; border-radius: 11px; font-size: 1.2rem; }
        #cpPage #cpOverview > .row { display: flex; flex-direction: column; flex-wrap: nowrap; align-items: stretch !important; margin: 0; }
        #cpPage #cpOverview > .row > .col-lg-4 { display: contents; }
        #cpPage #cpOverview > .row .cp-card { margin: 0 0 1rem !important; }
        #cpPage .cp-c-quick { order: 1; } #cpPage .cp-c-activity { order: 2; } #cpPage .cp-c-tasks { order: 3; }
        #cpPage .cp-c-contacts { order: 4; } #cpPage .cp-c-trend { order: 5; } #cpPage .cp-c-calls { order: 6; }
        #cpPage .cp-c-followup { order: 7; } #cpPage .cp-c-health { order: 8; } #cpPage .cp-c-team { order: 9; }
        #cpPage .cp-card > .card-body { padding: 1rem 1.1rem; }
        #cpPage .cp-contact-row .text-muted, #cpPage .cp-party-name, #cpPage .activity-full, #cpPage .activity-text { overflow-wrap: anywhere; }
        .cp-modal .modal-dialog { margin: .75rem; }
        .cp-modal .modal-footer .btn { flex: 1 1 0; }
    }
    @media (max-width: 575px) {
        /* header: logo beside the name, chips and buttons across the full width */
        #cpPage .cp-hero { margin-bottom: .9rem; border-radius: 18px; }
        #cpPage .cp-hero-top { display: grid; grid-template-columns: 56px minmax(0, 1fr); align-items: center; gap: .55rem .8rem; padding: 1rem 1rem .8rem; }
        #cpPage .cp-hero-info { display: contents; }
        #cpPage .cp-hero-logo { grid-row: 1 / span 2; width: 56px; height: 56px; border-radius: 15px; font-size: 1.2rem; }
        #cpPage .cp-hero-meta { grid-column: 2; gap: .5rem; margin: 0; font-size: .74rem; }
        #cpPage .cp-hero-title { grid-column: 2; margin: 0; font-size: 1.25rem; line-height: 1.2; overflow-wrap: anywhere; }
        #cpPage .cp-hero-tags { grid-column: 1 / -1; gap: .4rem; }
        #cpPage .cp-hero-contact { margin-top: 0; }
        #cpPage .cp-tag { max-width: 100%; padding: 4px 10px; font-size: .75rem; }
        #cpPage .cp-hero-actions { grid-column: 1 / -1; }
        /* key figures: one swipeable row instead of three stacked rows */
        #cpPage .cp-kpis { display: flex; gap: .6rem; margin: 0 .75rem .8rem; padding: .6rem; overflow-x: auto; scroll-snap-type: x proximity; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        #cpPage .cp-kpis::-webkit-scrollbar { display: none; }
        #cpPage .cp-kpi, #cpPage .cp-kpi:nth-child(n) { flex: 0 0 auto; width: min(78%, 250px); gap: .7rem; padding: .6rem .8rem; border: 1px solid rgba(255,255,255,.14); border-radius: 13px; background: rgba(255,255,255,.07); scroll-snap-align: start; }
        #cpPage .cp-kpi-icon { width: 38px; height: 38px; border-radius: 11px; font-size: 1.15rem; }
        #cpPage .cp-kpi-value { font-size: 1.3rem; }
        #cpPage .cp-kpi-value.is-text { font-size: 1.05rem; }
        #cpPage .cp-cta-icon { width: 40px; height: 40px; font-size: 1.25rem; }
        #cpPage .table td, #cpPage .table th { padding: .5rem .45rem; }
        #cpPage .table thead th { font-size: .66rem; letter-spacing: .03em; }
        #cpPage .cp-team-avatar { font-size: .72rem; }
        /* inputs at 16px so iOS doesn't zoom the page when one is focused */
        #cpPage .form-control, #cpPage .form-select, .cp-modal .form-control, .cp-modal .form-select { font-size: 16px !important; }
    }
    @media (max-width: 399px) { #cpPage .cp-xs-hide { display: none; } }
    @media (max-width: 767px), (pointer: coarse) {
        /* legible small print */
        #cpPage .small, #cpPage .cp-kpi-sub, #cpPage .cp-mini-lbl, #cpPage .activity-time, #cpPage .cp-party-meta, #cpPage .cp-party-role,
        #cpPage .cp-status-chip, #cpPage .follow-up-chip, #cpPage .cp-stage, #cpPage .cp-hero-quote span { font-size: .78rem; }
        #cpPage [style*="font-size:.6"], #cpPage [style*="font-size:0.6"], #cpPage [style*="font-size:.7"], #cpPage [style*="font-size:0.7"] { font-size: .78rem !important; }
        /* finger-sized targets */
        #cpPage .btn:not(.btn-close), .cp-modal.modal .btn:not(.btn-close) { min-height: 40px !important; }
        #cpPage .btn-sm, .cp-modal.modal .btn-sm { min-height: 38px !important; }
        #cpPage .btn-group .btn { min-height: 34px !important; padding-left: .85rem !important; padding-right: .85rem !important; }
        #cpPage .btn.btn-icon { width: 38px !important; height: 38px !important; min-height: 0 !important; }
        #cpPage .btn.cp-add-btn { width: 40px; height: 40px; min-height: 40px !important; }
        #cpPage .btn.p-0, #cpPage .btn.border-0 { display: inline-flex; align-items: center; justify-content: center; min-width: 38px; min-height: 38px !important; }
        #cpPage .cp-card a.small.fw-semibold { display: inline-block; padding: .6rem .4rem; margin: -.6rem -.4rem; }
        #cpPage .cp-party-meta a { padding: .3rem 0; }
        #cpPage .form-check-input { width: 1.3rem; height: 1.3rem; margin-top: 0; }
        #cpPage .cp-composer-extra { padding-left: 1.9rem; }
        #cpPage .cp-tabs .nav-link { padding: .85rem 1rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        #cpPage *, #cpPage *::before, #cpPage *::after { animation: none !important; transition: none !important; }
    }
</style>
@endsection

@section('content')
<div id="cpPage" class="cp-page animate__animated animate__fadeIn">

    <a href="{{ route('crm-clients.profile', $mapping->id) }}" onclick="event.preventDefault(); history.back();" class="text-decoration-none small text-muted mb-2 d-inline-block">
        <i class="bx bx-arrow-back"></i> Back to Client Mappings
    </a>

    @php
        $__isDropped = $mapping->crm_stage && stripos($mapping->crm_stage, 'drop') !== false;
    @endphp

    {{-- ── Header banner, with the key figures in a glass strip ────────────── --}}
    @php
        $tallyOnly = $co && $totalSalesFy <= 0 && $tallyFy > 0;
        $salesFigure = $tallyOnly ? $tallyFy : $totalSalesFy;
        $tallyFrom = $co ? \Carbon\Carbon::parse($co['coverage']['from'])->format('j M') : null;
        $tallyTo = $co ? \Carbon\Carbon::parse($co['coverage']['to'])->format('j M') : null;
        // Contact details for the header. The account's own fields win; when one is empty the primary contact's stands in (and says so).
        $heroContact = $contacts->firstWhere('is_primary', true) ?? $contacts->first();
        $hdrEmail = $mapping->email ?: optional($heroContact)->email;
        $hdrPhone = $mapping->phone ?: optional($heroContact)->phone;
        $hdrPerson = $mapping->contact_person ?: optional($heroContact)->name;
    @endphp
    <section class="cp-hero">
        <div class="cp-hero-bg" aria-hidden="true"></div>

        <div class="cp-hero-top">
            <div class="cp-hero-logo">{{ strtoupper(substr($mapping->crm_company_name, 0, 2)) }}</div>
            <div class="cp-hero-info">
                <div class="cp-hero-meta">
                    <span class="cp-pill {{ $__isDropped ? 'cp-pill-dropped' : 'cp-pill-active' }}">{{ $__isDropped ? 'Dropped' : 'Active' }}</span>
                    <span class="cp-hero-since"><i class="bx bx-calendar"></i>Customer Since {{ $mapping->created_at->format('M Y') }}</span>
                </div>
                <h1 class="cp-hero-title">{{ $mapping->crm_company_name }}</h1>
                <div class="cp-hero-tags">
                    <span class="cp-tag"><i class="bx bx-hash"></i>{{ $mapping->crm_company_code }}</span>
                    @if($mapping->crm_city)<span class="cp-tag"><i class="bx bx-map-pin"></i>{{ $mapping->crm_city }}{{ $mapping->crm_state ? ', '.$mapping->crm_state : '' }}</span>@endif
                    @if($mapping->crm_stage)<span class="cp-tag"><i class="bx bx-flag"></i>{{ $mapping->crm_stage }} <b>Current Stage</b></span>@endif
                </div>
                @if($hdrEmail || $hdrPhone || $hdrPerson || $mapping->address)
                    <div class="cp-hero-tags cp-hero-contact">
                        @if($hdrEmail)<a class="cp-tag" href="mailto:{{ $hdrEmail }}" title="{{ $mapping->email ? 'Customer email' : 'Email of the primary contact' }}"><i class="bx bx-envelope"></i><span>{{ $hdrEmail }}</span></a>@endif
                        @if($hdrPhone)<a class="cp-tag" href="tel:{{ $hdrPhone }}" title="{{ $mapping->phone ? 'Customer phone' : 'Phone of the primary contact' }}"><i class="bx bx-phone"></i><span>{{ $hdrPhone }}</span></a>@endif
                        @if($hdrPerson)<span class="cp-tag"><i class="bx bx-user"></i>{{ $hdrPerson }} <b>Primary Contact</b></span>@endif
                        @if($mapping->address)<span class="cp-tag cp-tag-wide" title="{{ $mapping->address }}"><i class="bx bx-buildings"></i><span>{{ $mapping->address }}</span></span>@endif
                    </div>
                @endif
            </div>
            <div class="cp-hero-actions">
                <button class="btn btn-primary" onclick="LogCall.open()"><i class="bx bx-plus"></i> Add Activity</button>
                <a href="{{ route('crm-clients.show', ['id' => $mapping->id, 'classic' => 1]) }}" class="btn btn-light"><i class="bx bx-grid-alt"></i> Classic View</a>
            </div>
            <div class="cp-hero-quote d-none d-lg-flex">
                <i class="bx bxs-quote-alt-left"></i>
                <div><p>Every call logged<br>is a relationship built.</p><span>— HITECH GROUP</span></div>
            </div>
        </div>

        <div class="cp-kpis">
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-blue"><i class="bx bx-bar-chart-alt-2"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value">
                        <span data-count="{{ round($salesFigure, 2) }}" data-decimals="2" data-prefix="₹" data-suffix="L">₹{{ number_format($salesFigure, 2) }}L</span>
                        @if($salesTrendPct !== null)
                            <span class="cp-trend {{ $salesTrendPct >= 0 ? 'up' : 'down' }}"><i class="bx {{ $salesTrendPct >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>{{ abs($salesTrendPct) }}%</span>
                        @endif
                    </div>
                    <div class="cp-kpi-label">{{ $tallyOnly ? 'Sales (Tally)' : 'Total Sales (FY)' }}</div>
                    @if($salesTrendPct !== null)<div class="cp-kpi-sub">vs same period last FY</div>@endif
                    @if($tallyOnly)
                        <div class="cp-kpi-sub" title="Tally data covers this period only; nothing is recorded in the Sales Report">{{ $tallyFyInvoices }} invoice{{ $tallyFyInvoices == 1 ? '' : 's' }} &middot; Tally, {{ $tallyFrom }}&ndash;{{ $tallyTo }}</div>
                    @elseif($co && $tallyFy > 0)
                        <div class="cp-kpi-sub">Tally: ₹{{ number_format($tallyFy, 2) }}L &middot; {{ $tallyFyInvoices }} invoice{{ $tallyFyInvoices == 1 ? '' : 's' }}</div>
                    @endif
                </div>
            </div>
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-violet"><i class="bx bx-target-lock"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value"><span data-count="{{ $openOpportunityCount }}" data-decimals="0">{{ $openOpportunityCount }}</span></div>
                    <div class="cp-kpi-label">Open Opportunities</div>
                    <div class="cp-kpi-sub">₹{{ number_format($openOpportunityValue, 2) }}L forecast</div>
                </div>
            </div>
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-green"><i class="bx bx-group"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value"><span data-count="{{ $contacts->count() }}" data-decimals="0">{{ $contacts->count() }}</span></div>
                    <div class="cp-kpi-label">Total Contacts</div>
                </div>
            </div>
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-cyan"><i class="bx bx-phone-call"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value"><span data-count="{{ $callsThisMonthTotal }}" data-decimals="0">{{ $callsThisMonthTotal }}</span></div>
                    <div class="cp-kpi-label">Calls (This Month)</div>
                    <div class="cp-kpi-sub">@if($callsLoggedThisMonth){{ $callsAgentThisMonth }} via Smart Agent &middot; {{ $callsLoggedThisMonth }} logged @else via Smart Agent @endif</div>
                </div>
            </div>
            <div class="cp-kpi">
                <div class="cp-kpi-icon tone-amber"><i class="bx bx-time-five"></i></div>
                <div class="cp-kpi-body">
                    <div class="cp-kpi-value is-text">{{ $lastActivity ? (abs($lastActivity->created_at->diffInSeconds(now())) < 60 ? 'Just now' : $lastActivity->created_at->diffForHumans()) : 'None yet' }}</div>
                    <div class="cp-kpi-label">Last Activity</div>
                </div>
            </div>
        </div>
    </section>
    {{-- ── Tabs ─────────────────────────────────────────────────────────── --}}
    <ul class="nav nav-tabs cp-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cpOverview" type="button"><i class="bx bx-home-alt me-1"></i>Overview</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpContacts" type="button"><i class="bx bx-id-card me-1"></i>Contacts</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpSales" type="button"><i class="bx bx-line-chart me-1"></i>Sales &amp; Revenue</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpCalls" type="button"><i class="bx bx-phone-call me-1"></i>Calls</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpTasks" type="button"><i class="bx bx-task me-1"></i>Tasks @if($openFollowUps->count())<span class="badge bg-label-warning rounded-pill">{{ $openFollowUps->count() }}</span>@endif</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpActivities" type="button"><i class="bx bx-time-five me-1"></i>Activities</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpNotes" type="button"><i class="bx bx-note me-1"></i>Notes</button></li>
    </ul>

    <div class="tab-content">
        {{-- ══════════════════════════════ OVERVIEW ══════════════════════════════ --}}
        <div class="tab-pane fade show active" id="cpOverview">
            @php
                $__primaryContact = $contacts->firstWhere('is_primary', true) ?? $contacts->first();
                $__quickPhone = $__primaryContact->phone ?? $mapping->phone;
                $__quickEmail = $__primaryContact->email ?? $mapping->email;
                $__quickWhatsapp = $__quickPhone ? preg_replace('/\D+/', '', $__quickPhone) : null;
                $__nextFollowUp = $openFollowUps->first();
                $__ctcAgentForCalls = app(\App\Services\ClickToCallService::class)->agentFor(auth()->user());
            @endphp

            {{-- Cards flow into balanced columns (3 on desktop, 2 on tablets) or one stack on phones, where the cp-c-* names set a most-used-first order.
                 Reading order: Activity & Calls, Quick Actions, Customer Health, Sales Trend, Key Contacts, Open Tasks, Team, Recent Calls, Follow-up. --}}
            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="card cp-card mb-4 cp-c-activity">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-time-five me-1 text-primary"></i>Activity &amp; Calls</h6>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-success rounded-pill" onclick="LogCall.open()"><i class="bx bx-phone-call"></i> Log Call</button>
                                    <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="document.querySelector('[data-bs-target=\'#cpActivities\']').click()">View All</a>
                                </div>
                            </div>
                            <div class="cp-composer">
                                <div class="cp-composer-row">
                                    <textarea id="cpOverviewCommentBody" class="form-control" rows="1" placeholder="Add a note…"></textarea>
                                    <button class="btn btn-sm btn-primary rounded-pill" id="cpOverviewPostBtn" onclick="postOverviewComment()"><i class="bx bx-send"></i> Post</button>
                                </div>
                            </div>
                            <div id="cpOverviewActivityFeed" class="cp-feed">
                                @forelse($activities->take(5) as $activity)
                                    @include('tenant.crm-clients.partials.profile-activity-row', ['activity' => $activity])
                                @empty
                                    <p class="text-muted small text-center py-3 mb-0">No activity yet.</p>
                                @endforelse
                            </div>
                            @if($activities->total() > 5)
                                <a href="javascript:void(0)" class="cp-viewall" onclick="document.querySelector('[data-bs-target=\'#cpActivities\']').click()">Showing the latest 5 &middot; View all {{ $activities->total() }} <i class="bx bx-right-arrow-alt"></i></a>
                            @endif
                        </div>
                    </div>

                    <div class="card cp-card mb-4 cp-c-quick">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2"><i class="bx bxs-bolt me-1 text-warning"></i>Quick Actions</h6>
                            <div class="d-flex gap-2">
                                @if($__quickPhone)
                                    <button type="button" class="cp-quick-btn cp-quick-call js-ctc-call" data-phone="{{ $__quickPhone }}" onclick="callContact('{{ $__quickPhone }}')">
                                        <i class="bx bx-phone-call"></i> Call
                                    </button>
                                @endif
                                @if($__quickEmail)
                                    <a href="mailto:{{ $__quickEmail }}" class="cp-quick-btn cp-quick-email"><i class="bx bx-envelope"></i> Email</a>
                                @endif
                                @if($__quickWhatsapp)
                                    <a href="https://wa.me/{{ $__quickWhatsapp }}" target="_blank" class="cp-quick-btn cp-quick-whatsapp"><i class="bx bxl-whatsapp"></i> WhatsApp</a>
                                @endif
                                <button type="button" class="cp-quick-btn" onclick="LogCall.open()"><i class="bx bx-dots-horizontal-rounded"></i> More</button>
                            </div>
                            @if(!$__quickPhone && !$__quickEmail)
                                <p class="text-muted small mb-0 mt-2">Add a contact with a phone or email to enable Call/Email/WhatsApp.</p>
                            @endif
                        </div>
                    </div>

                    <div class="card cp-card mb-4 cp-c-health">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2"><i class="bx bx-heart me-1 text-danger"></i>Customer Health</h6>
                            <div class="d-flex align-items-center gap-3">
                                <div id="cpHealthRing" class="cp-health-ring"></div>
                                <div>
                                    <div class="fw-bold" style="font-size:1rem;">{{ $health['label'] }}</div>
                                    @foreach($health['reasons'] as $reason)
                                        <div class="small text-muted"><i class="bx bx-check text-success"></i> {{ $reason }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card cp-card mb-4 cp-c-trend">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-line-chart me-1 text-primary"></i>Sales Trend</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary cp-range-btn" data-range="6M">6M</button>
                                    <button type="button" class="btn btn-primary cp-range-btn" data-range="1Y">1Y</button>
                                    <button type="button" class="btn btn-outline-secondary cp-range-btn" data-range="2Y">2Y</button>
                                </div>
                            </div>
                            <div id="cpSalesTrendChart"></div>
                            @if($co)
                                @php
                                    $__outLakh = ((float) ($co['outstanding']->total ?? 0)) / 100000;
                                    $__ordN = (int) ($co['orders']->n ?? 0);
                                @endphp
                                <div class="cp-mini-grid">
                                    <div class="cp-mini" title="Tally sales, {{ $tallyFrom }}–{{ $tallyTo }}"><div class="cp-mini-icon tone-blue"><i class="bx bx-receipt"></i></div><div class="cp-mini-val">₹{{ number_format($tallyFy, 2) }}L</div><div class="cp-mini-lbl">Tally sales</div></div>
                                    <div class="cp-mini" title="Receivable in Tally"><div class="cp-mini-icon tone-amber"><i class="bx bx-wallet"></i></div><div class="cp-mini-val">₹{{ number_format($__outLakh, 2) }}L</div><div class="cp-mini-lbl">Outstanding</div></div>
                                    <div class="cp-mini" title="Sale orders in Tally"><div class="cp-mini-icon tone-violet"><i class="bx bx-cart"></i></div><div class="cp-mini-val">{{ $__ordN }}</div><div class="cp-mini-lbl">Sale orders</div></div>
                                    <div class="cp-mini" title="Latest customer invoice in Tally"><div class="cp-mini-icon tone-green"><i class="bx bx-calendar-check"></i></div><div class="cp-mini-val">{{ $co['lastDate'] ? \Carbon\Carbon::parse($co['lastDate'])->format('j M') : '—' }}</div><div class="cp-mini-lbl">Last invoice</div></div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card cp-card mb-4 cp-c-contacts">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-id-card me-1 text-success"></i>Key Contacts</h6>
                                <button class="btn btn-sm btn-primary cp-add-btn" onclick="openContactModal()" title="Add contact"><i class="bx bx-plus"></i></button>
                            </div>
                            <div id="cpContactsList">
                                @include('tenant.crm-clients.partials.profile-contacts-list', ['contacts' => $contacts])
                            </div>
                        </div>
                    </div>

                    <div class="card cp-card mb-4 cp-c-tasks">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-task me-1 text-warning"></i>Open Tasks</h6>
                                <a href="javascript:void(0)" class="small fw-semibold" style="color:#127464;" onclick="document.querySelector('[data-bs-target=\'#cpTasks\']').click()">View All</a>
                            </div>
                            @forelse($openFollowUps->take(4) as $fu)
                                <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom">
                                    <div style="min-width:0;">
                                        <div class="small text-truncate" style="max-width:220px;">{{ \Illuminate\Support\Str::limit($fu->body, 60) }}</div>
                                        <div class="text-muted small {{ $fu->follow_up_due_at && $fu->follow_up_due_at->isPast() ? 'cp-overdue' : '' }}">{{ $fu->follow_up_due_at ? 'Due '.$fu->follow_up_due_at->format('d M') : 'No date' }}{{ $fu->follow_up_due_at && $fu->follow_up_due_at->isPast() ? ' · overdue' : '' }}</div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-success rounded-pill flex-shrink-0" onclick="completeFollowUp({{ $fu->id }}, this)"><i class="bx bx-check"></i></button>
                                </div>
                            @empty
                                <p class="text-muted small text-center py-3 mb-0">No open tasks.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card cp-card mb-4 cp-c-team">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-group me-1 text-primary"></i>Our Team (HRX)</h6>
                                <button class="btn btn-sm btn-primary cp-add-btn" onclick="TeamCard.openAdd()" title="Add team member"><i class="bx bx-plus"></i></button>
                            </div>
                            @include('tenant.crm-clients.partials.profile-team-list', ['teamByRole' => $teamByRole, 'teamPickable' => $teamPickable])
                        </div>
                    </div>

                    <div class="card cp-card mb-4 cp-c-calls">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-phone-call me-1 text-primary"></i>Recent Calls</h6>
                                <a href="javascript:void(0)" class="small fw-semibold" style="color:#127464;" onclick="document.querySelector('[data-bs-target=\'#cpCalls\']').click()">View All</a>
                            </div>
                            @forelse($callLog->take(6) as $entry)
                                @include('tenant.crm-clients.partials.profile-call-row', ['entry' => $entry, 'detailed' => false])
                            @empty
                                <p class="text-muted small text-center py-3 mb-0">No calls placed through Smart Agent or logged for this account yet.</p>
                            @endforelse
                            @if($__ctcAgentForCalls)
                                <button type="button" class="btn btn-sm btn-primary w-100 mt-2 js-ctc-call" data-phone="{{ $__quickPhone ?? $mapping->phone }}">
                                    <i class="bx bx-phone-call"></i> Make a Call
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="card cp-card mb-4 cp-c-followup">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2"><i class="bx bx-calendar-check me-1 text-primary"></i>Upcoming Follow-up</h6>
                            @if($__nextFollowUp)
                                <div class="fw-bold">{{ $__nextFollowUp->follow_up_due_at ? $__nextFollowUp->follow_up_due_at->format('d M Y') : 'No date set' }}</div>
                                <div class="text-muted small mb-2">{{ \Illuminate\Support\Str::limit($__nextFollowUp->body, 70) }}</div>
                                <a href="javascript:void(0)" class="small fw-semibold" style="color:#127464;" onclick="document.querySelector('[data-bs-target=\'#cpTasks\']').click()">View all tasks</a>
                            @else
                                <p class="cp-followup-empty mb-0">No upcoming follow-ups.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

                    
        </div>

        {{-- ══════════════════════════════ CONTACTS ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpContacts">
            <div class="card cp-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">All Contacts</h6>
                        <button class="btn btn-sm btn-primary rounded-pill" onclick="openContactModal()"><i class="bx bx-plus"></i> Add Contact</button>
                    </div>
                    <div id="cpContactsListFull">
                        @include('tenant.crm-clients.partials.profile-contacts-list', ['contacts' => $contacts])
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ SALES & REVENUE ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpSales">
            <div class="card cp-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">Sales Trend</h6>
                    <div id="cpSalesTrendChartBig"></div>
                </div>
            </div>
            @if($co)
                <div class="card cp-card mb-4">
                    <div class="card-body">
                        @if($co['hasData'])
                            @if($co['name'])<div class="small text-muted mb-3">Matched by name to the Tally customer &ldquo;{{ $co['name'] }}&rdquo;{{ $co['codes'] ? ' (code ' . $co['codes'] . ')' : '' }}.</div>@endif
                            @include('tenant.sales-sources.partials.company-sales', ['co' => $co, 'billUrl' => route('crm-clients.profile.invoice', $mapping->id) . '?invoice_no='])
                        @else
                            <h6 class="fw-bold mb-2">Sales from Tally &amp; Setu</h6>
                            <p class="text-muted small mb-0">
                                @if($sharedName > 1)
                                    {{ $sharedName }} CRM customers share the name &ldquo;{{ $mapping->crm_company_name }}&rdquo;, so Tally sales can't be matched to just this one.
                                @else
                                    No Tally, Setu or Sales Report figures were found under this customer's name. Names are matched exactly, ignoring Pvt/Ltd and punctuation.
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            @endif
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Linked Sales Report Rows</h6>
                    @if($pipelines->isEmpty())
                        <p class="text-muted small mb-0">No Sales Report rows are reconciled to this CRM account yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Party Name</th><th>Salesperson</th><th class="text-end">This Month Sale</th><th class="text-end">Total on File</th></tr></thead>
                                <tbody>
                                    @foreach($pipelines as $p)
                                        @php $thisMonth = $p->months->firstWhere('month_year', now()->format('Y-m')); @endphp
                                        <tr>
                                            <td>{{ $p->party_name }}</td>
                                            <td>{{ optional($p->salesperson)->getFullName() ?: '—' }}</td>
                                            <td class="text-end">₹{{ number_format($thisMonth->sale_amount ?? 0, 2) }}L</td>
                                            <td class="text-end">₹{{ number_format($p->months->sum('sale_amount'), 2) }}L</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ CALLS ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpCalls">
            <div class="card cp-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Calls</h6>
                        <button class="btn btn-sm btn-primary rounded-pill" onclick="LogCall.open()"><i class="bx bx-plus"></i> Log Call</button>
                    </div>
                    @if($callLog->isEmpty())
                        <p class="text-muted small mb-0">No calls placed through Smart Agent or logged for this account yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table cp-call-table mb-0">
                                <thead><tr><th>Call</th><th>Our team</th><th>Customer contact</th><th>When</th><th class="text-end">Status</th></tr></thead>
                                <tbody>
                                    @foreach($callLog as $entry)
                                        @include('tenant.crm-clients.partials.profile-call-tr', ['entry' => $entry])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ TASKS ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpTasks">
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Open Follow-ups</h6>
                    @forelse($openFollowUps as $fu)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="small">{{ \Illuminate\Support\Str::limit($fu->body, 100) }}</div>
                                <div class="text-muted small">
                                    @if($fu->follow_up_due_at)<span class="{{ $fu->follow_up_due_at->isPast() ? 'cp-overdue' : '' }}">Due {{ $fu->follow_up_due_at->format('d M Y') }}{{ $fu->follow_up_due_at->isPast() ? ' · overdue' : '' }}</span>@endif
                                    @if($fu->followUpAssignedTo) · {{ $fu->followUpAssignedTo->first_name }}@endif
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-success rounded-pill" onclick="completeFollowUp({{ $fu->id }}, this)"><i class="bx bx-check"></i> Done</button>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No open follow-ups.</p>
                    @endforelse

                    @if($completedFollowUps->isNotEmpty())
                        <h6 class="fw-bold mt-4 mb-3"><i class="bx bx-check-circle text-success"></i>Completed</h6>
                        @foreach($completedFollowUps as $fu)
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div style="min-width:0;">
                                    <div class="small text-muted text-decoration-line-through">{{ \Illuminate\Support\Str::limit($fu->body, 100) }}</div>
                                    <div class="text-muted small">
                                        Done {{ $fu->follow_up_completed_at->format('d M Y, h:i A') }}
                                        @if($fu->followUpAssignedTo) &middot; {{ $fu->followUpAssignedTo->first_name }}@endif
                                    </div>
                                </div>
                                <span class="cp-status-chip cp-status-logged flex-shrink-0">Done</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ ACTIVITIES ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpActivities">
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Activity Feed</h6>
                    <div class="cp-composer">
                        <div class="cp-composer-row">
                            <textarea id="cpNewCommentBody" class="form-control" rows="1" placeholder="Add a note about this customer..."></textarea>
                            <button class="btn btn-sm btn-primary rounded-pill" id="cpPostCommentBtn" onclick="postComment()"><i class="bx bx-send"></i> Post</button>
                        </div>
                        <div class="cp-composer-extra form-check">
                            <input class="form-check-input" type="checkbox" id="cpNewCommentFollowUp">
                            <label class="form-check-label small" for="cpNewCommentFollowUp">Needs follow-up</label>
                            <input type="date" id="cpNewCommentDueAt" class="form-control form-control-sm d-inline-block ms-2 d-none" style="width:150px;">
                        </div>
                    </div>
                    <div id="cpActivityFeed">
                        @forelse($activities as $activity)
                            @include('tenant.crm-clients.partials.profile-activity-row', ['activity' => $activity])
                        @empty
                            <p class="text-muted small text-center py-3">No activity yet.</p>
                        @endforelse
                    </div>
                    {{ $activities->links() }}
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ NOTES ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpNotes">
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Notes</h6>
                    <div class="cp-composer">
                        <div class="cp-composer-row">
                            <textarea id="cpNewNoteBody" class="form-control" rows="1" placeholder="Write a note..."></textarea>
                            <button class="btn btn-sm btn-primary rounded-pill" onclick="postNote()"><i class="bx bx-send"></i> Save Note</button>
                        </div>
                    </div>
                    <div id="cpNotesList">
                        @forelse($activities->filter(fn($a) => $a->type?->value === 'comment') as $note)
                            @include('tenant.crm-clients.partials.profile-activity-row', ['activity' => $note])
                        @empty
                            <p class="text-muted small text-center py-3">No notes yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="cp-cta">
        <div class="cp-cta-icon"><i class="bx bx-leaf"></i></div>
        <div class="cp-cta-text">
            <strong>Build stronger relationships, drive more opportunities.</strong>
            <span>Keep your customer data, conversations and activities in one place.</span>
        </div>
        <div class="cp-cta-actions">
            <button type="button" class="btn btn-primary" onclick="cpFocusNote()"><i class="bx bx-plus"></i> Add Note</button>
            <button type="button" class="btn btn-outline-secondary" onclick="LogCall.open()"><i class="bx bx-phone-call"></i> Log a Call</button>
        </div>
    </div>
</div>

{{-- ── Modals (contact, log-call) ──────────────────────────────────────── --}}
<div class="modal fade cp-modal" id="cpContactModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" id="cpContactModalTitle">Add Contact</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="cp_contact_id">
                <div class="mb-3"><label class="form-label fw-bold">Name *</label><input type="text" id="cp_contact_name" class="form-control"></div>
                <div class="mb-3"><label class="form-label fw-bold">Role *</label>
                    <select id="cp_contact_role" class="form-select">
                        <option value="purchase">Purchase</option><option value="finance">Finance</option>
                        <option value="accounts">Accounts</option><option value="admin">Admin</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="cp_contact_is_primary"><label class="form-check-label">Primary contact</label></div>
                <div class="mb-3"><label class="form-label fw-bold">Email</label><input type="email" id="cp_contact_email" class="form-control"></div>
                <div class="mb-3"><label class="form-label fw-bold">Phone</label><input type="text" id="cp_contact_phone" class="form-control"></div>
                <div class="mb-1"><label class="form-label fw-bold">Notes</label><textarea id="cp_contact_notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4" onclick="saveContact()" id="cpSaveContactBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade cp-modal" id="cpLogCallModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title"><i class="bx bx-phone-call me-1"></i>Log Call &amp; Add Notes</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label fw-bold small">Our Team Member</label>
                        <select id="cpCallOurUser" class="form-select form-select-sm">
                            @foreach($ourPeople as $p)<option value="{{ $p->id }}" @selected($p->id === auth()->id())>{{ trim($p->first_name.' '.$p->last_name) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label fw-bold small">Customer Contact</label>
                        <select id="cpCallContact" class="form-select form-select-sm">
                            <option value="">— Not listed —</option>
                            @foreach($contacts as $c)<option value="{{ $c->id }}" @selected($c->is_primary)>{{ $c->name }} ({{ $c->role->label() }})</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label fw-bold small">Call Outcome *</label>
                        <select id="cpCallOutcome" class="form-select form-select-sm">
                            <option>Discussion</option><option>Order Placed</option><option>Follow-up Needed</option>
                            <option>No Response</option><option>Complaint</option><option>Other</option>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label fw-bold small">Call Type *</label>
                        <select id="cpCallType" class="form-select form-select-sm">
                            <option>Outbound</option><option>Inbound</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2"><label class="form-label fw-bold small">Call Notes *</label><textarea id="cpCallNotes" class="form-control" rows="3"></textarea></div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label fw-bold small">Next Follow-up</label><input type="date" id="cpCallNextFollowUp" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label fw-bold small">Follow-up Type</label>
                        <select id="cpCallFollowUpType" class="form-select form-select-sm">
                            <option value="">— None —</option><option>Call</option><option>Meeting</option><option>Email</option>
                        </select>
                    </div>
                </div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="cpCallSetReminder" checked><label class="form-check-label small">Set Reminder</label></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4" id="cpSaveCallLogBtn" onclick="LogCall.save()">Save Call Log</button>
            </div>
        </div>
    </div>
</div>

<div class="d-none">
    @include('tenant.users.dashboard._click-to-call')
</div>
@if($co)
    @include('tenant.sales-sources.partials.invoice-modal')
@endif
@endsection

@section('page-script')
<script>
const CP_MAPPING_ID = {{ $mapping->id }};
const CP_CSRF = '{{ csrf_token() }}';
const CP_ROUTES = {
    storeContact:     '{{ route('crm-clients.contacts.store', $mapping->id) }}',
    updateContact:    (id) => `{{ url('crm-clients/contacts') }}/${id}`,
    destroyContact:   (id) => `{{ url('crm-clients/contacts') }}/${id}`,
    storeActivity:    '{{ route('crm-clients.activities.store', $mapping->id) }}',
    completeFollowUp: (id) => `{{ url('crm-clients/activities') }}/${id}/complete-follow-up`,
    addTeamMember:    '{{ route('crm-clients.team.store', $mapping->id) }}',
    removeTeamMember: (userId, role) => `{{ url('crm-clients') }}/{{ $mapping->id }}/team/${userId}/${role}`,
    salesTrend:       '{{ route('crm-clients.profile.sales-trend', $mapping->id) }}',
    logCall:          '{{ route('crm-clients.profile.log-call', $mapping->id) }}',
};

function swalOk(m) { if (window.showSuccessSwal) window.showSuccessSwal(m); else if (window.Swal) Swal.fire({icon:'success', title:'Done', text:m}); }
function swalErr(m) { if (window.showErrorSwal) window.showErrorSwal(m); else if (window.Swal) Swal.fire({icon:'error', title:'Error', text:m}); else alert(m); }

// ── Contacts (shares the same backend routes as the classic Customer 360) ──
function openContactModal(contact = null) {
    document.getElementById('cpContactModalTitle').textContent = contact ? 'Edit Contact' : 'Add Contact';
    document.getElementById('cp_contact_id').value = contact ? contact.id : '';
    document.getElementById('cp_contact_name').value = contact ? contact.name : '';
    document.getElementById('cp_contact_role').value = contact ? (contact.role.value || contact.role) : 'purchase';
    document.getElementById('cp_contact_email').value = contact ? (contact.email || '') : '';
    document.getElementById('cp_contact_phone').value = contact ? (contact.phone || '') : '';
    document.getElementById('cp_contact_notes').value = contact ? (contact.notes || '') : '';
    document.getElementById('cp_contact_is_primary').checked = !!(contact && contact.is_primary);
    new bootstrap.Modal(document.getElementById('cpContactModal')).show();
}
function saveContact() {
    const id = document.getElementById('cp_contact_id').value;
    const payload = {
        name: document.getElementById('cp_contact_name').value,
        role: document.getElementById('cp_contact_role').value,
        email: document.getElementById('cp_contact_email').value,
        phone: document.getElementById('cp_contact_phone').value,
        notes: document.getElementById('cp_contact_notes').value,
        is_primary: document.getElementById('cp_contact_is_primary').checked,
    };
    if (!payload.name) { alert('Name is required.'); return; }
    const btn = document.getElementById('cpSaveContactBtn');
    btn.disabled = true;
    fetch(id ? CP_ROUTES.updateContact(id) : CP_ROUTES.storeContact, {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    }).then(r => r.json()).then(data => {
        if (data.success) { location.reload(); } else { alert(data.message || 'Save failed.'); }
    }).finally(() => { btn.disabled = false; });
}
function deleteContact(id) {
    if (!confirm('Remove this contact?')) return;
    fetch(CP_ROUTES.destroyContact(id), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' } })
        .then(r => r.json()).then(data => { if (data.success) location.reload(); });
}
function callContact(phone) {
    const modalEl = document.getElementById('clickToCallModal');
    if (!modalEl) return;
    document.getElementById('ctcPhone').value = phone;
    document.getElementById('ctcResult').classList.add('d-none');
    document.getElementById('ctcLive').classList.add('d-none');
    new bootstrap.Modal(modalEl).show();
}
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-ctc-call');
    if (btn) callContact(btn.dataset.phone);
});

// ── Team (shares the same backend routes) ──
const TeamCard = {
    openAdd() { document.getElementById('cpTeamAddRow').classList.remove('d-none'); },
    closeAdd() { document.getElementById('cpTeamAddRow').classList.add('d-none'); },
    add() {
        const userId = document.getElementById('cpTeamAddUser').value;
        const role = document.getElementById('cpTeamAddRole').value;
        if (!userId) { swalErr('Pick a person first'); return; }
        fetch(CP_ROUTES.addTeamMember, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF },
            body: JSON.stringify({ user_id: userId, role: role }),
        }).then(r => r.json()).then(d => { if (!d.success) { swalErr('Could not add'); return; } location.reload(); });
    },
    remove(userId, role) {
        if (!confirm('Remove this person from the team?')) return;
        fetch(CP_ROUTES.removeTeamMember(userId, role), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CP_CSRF } })
            .then(r => r.json()).then(d => { if (!d.success) { swalErr('Could not remove'); return; } location.reload(); });
    },
};

// ── Activities / Notes ──
function postComment() {
    const body = document.getElementById('cpNewCommentBody').value.trim();
    if (!body) return;
    const needsFollowUp = document.getElementById('cpNewCommentFollowUp').checked;
    const btn = document.getElementById('cpPostCommentBtn');
    btn.disabled = true;
    fetch(CP_ROUTES.storeActivity, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ body, needs_follow_up: needsFollowUp, follow_up_due_at: needsFollowUp ? document.getElementById('cpNewCommentDueAt').value : null }),
    }).then(r => r.json()).then(data => { if (data.success) location.reload(); else alert(data.message || 'Could not post.'); })
      .finally(() => { btn.disabled = false; });
}
function postNote() {
    const body = document.getElementById('cpNewNoteBody').value.trim();
    if (!body) return;
    fetch(CP_ROUTES.storeActivity, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ body, needs_follow_up: false }),
    }).then(r => r.json()).then(data => { if (data.success) location.reload(); else alert(data.message || 'Could not post.'); });
}
function postOverviewComment() {
    const body = document.getElementById('cpOverviewCommentBody').value.trim();
    if (!body) return;
    const btn = document.getElementById('cpOverviewPostBtn');
    btn.disabled = true;
    fetch(CP_ROUTES.storeActivity, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ body, needs_follow_up: false }),
    }).then(r => r.json()).then(data => { if (data.success) location.reload(); else alert(data.message || 'Could not post.'); })
      .finally(() => { btn.disabled = false; });
}
document.getElementById('cpNewCommentFollowUp')?.addEventListener('change', function () {
    document.getElementById('cpNewCommentDueAt').classList.toggle('d-none', !this.checked);
});
function completeFollowUp(id, btn) {
    fetch(CP_ROUTES.completeFollowUp(id), { method: 'POST', headers: { 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' } })
        .then(r => r.json()).then(d => { if (d.success) { location.reload(); } });
}

// ── Manual Call Log ──
const LogCall = {
    open() { new bootstrap.Modal(document.getElementById('cpLogCallModal')).show(); },
    save() {
        const notes = document.getElementById('cpCallNotes').value.trim();
        if (!notes) { swalErr('Call notes are required.'); return; }
        const btn = document.getElementById('cpSaveCallLogBtn');
        btn.disabled = true;
        fetch(CP_ROUTES.logCall, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({
                call_outcome: document.getElementById('cpCallOutcome').value,
                call_type: document.getElementById('cpCallType').value,
                notes: notes,
                next_follow_up: document.getElementById('cpCallNextFollowUp').value || null,
                follow_up_type: document.getElementById('cpCallFollowUpType').value || null,
                set_reminder: document.getElementById('cpCallSetReminder').checked,
                our_user_id: document.getElementById('cpCallOurUser').value || null,
                customer_contact_id: document.getElementById('cpCallContact').value || null,
            }),
        }).then(r => r.json()).then(d => {
            if (!d.success) { swalErr(d.message || 'Could not save.'); return; }
            swalOk('Call logged.');
            location.reload();
        }).finally(() => { btn.disabled = false; });
    },
};

// ── The note boxes are one line until you type more (they grow to about five lines) ──
document.querySelectorAll('#cpPage .cp-composer textarea').forEach(function (t) {
    t.addEventListener('input', function () {
        if (!t.value) { t.style.height = ''; return; }   // empty: back to one line (the placeholder must not size it)
        t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 120) + 'px';
    });
});

// ── A feed entry is one line; click it to read the whole text ──
document.addEventListener('click', function (e) {
    const row = e.target.closest('#cpPage .activity-item');
    if (row && !e.target.closest('a, button')) row.classList.toggle('is-open');
});

// ── After a reload (logging a call, ticking a task, posting a note ...) come back to the tab you were on ──
(function () {
    const KEY = 'cpTab:{{ $mapping->id }}';
    document.querySelectorAll('.cp-tabs [data-bs-toggle="tab"]').forEach(function (b) {
        b.addEventListener('shown.bs.tab', function () {
            try { sessionStorage.setItem(KEY, b.dataset.bsTarget); } catch (e) {}
            b.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });   // keep the chosen tab visible in the scrolling bar
        });
    });
    try {
        const nav = performance.getEntriesByType('navigation')[0], saved = sessionStorage.getItem(KEY);
        const btn = saved && nav && nav.type === 'reload' ? document.querySelector('.cp-tabs [data-bs-target="' + saved + '"]') : null;
        // bootstrap loads as a deferred module, so it may not exist yet when this runs
        if (btn) (function show(tries) {
            if (window.bootstrap && window.bootstrap.Tab) { new window.bootstrap.Tab(btn).show(); }
            else if (tries < 200) { setTimeout(function () { show(tries + 1); }, 25); }
        })(0);
    } catch (e) {}
})();

// ── Key figures count up once on load (skipped for reduced motion; final text is always the server-rendered value) ──
(function () {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('#cpPage [data-count]').forEach(function (el) {
        const end = parseFloat(el.dataset.count), d = parseInt(el.dataset.decimals || '0', 10);
        if (!isFinite(end) || end === 0) return;
        const pre = el.dataset.prefix || '', suf = el.dataset.suffix || '', final = el.textContent;
        const fmt = function (v) { return pre + v.toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d }) + suf; };
        const t0 = Date.now(), dur = 900;
        (function step() {
            const p = Math.min(1, (Date.now() - t0) / dur), eased = 1 - Math.pow(1 - p, 3);
            el.textContent = p < 1 ? fmt(end * eased) : final;
            if (p < 1) setTimeout(step, 30);
        })();
    });
})();

function cpFocusNote() {
    const tab = document.querySelector('[data-bs-target="#cpOverview"]');
    if (tab) tab.click();
    setTimeout(function () {
        const box = document.getElementById('cpOverviewCommentBody');
        if (box) { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); box.focus({ preventScroll: true }); }
    }, 250);
}

// ── Sales trend chart (ApexCharts, deferred-loaded like the rest of HRX) ──
(async function () {
    for (let i = 0; i < 200 && !window.ApexCharts; i++) { await new Promise(r => setTimeout(r, 25)); }
    if (!window.ApexCharts) return;

    let chart = null, chartBig = null, bigOpts = null;
    const isPhone = () => window.matchMedia('(max-width: 575px)').matches;
    function render(labels, values, tally) {
        const series = [{ name: 'Sales Report (₹L)', data: values }];
        if (tally) series.push({ name: 'Tally (₹L)', data: tally });
        const opts = {
            chart: { type: 'area', height: isPhone() ? 200 : 220, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
            series: series,
            xaxis: { categories: labels, tickAmount: isPhone() ? 5 : undefined, axisBorder: { show: false }, axisTicks: { show: false }, labels: { rotate: 0, hideOverlappingLabels: true, style: { colors: '#94a3b8', fontSize: '11px' } } },
            yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' }, formatter: (v) => '₹' + v + 'L' } },
            grid: { borderColor: '#eef2f6', strokeDashArray: 3, padding: { left: 4, right: 4 } },
            colors: ['#127464', '#2563eb'], stroke: { curve: 'smooth', width: 2 },
            markers: { size: 0, strokeColors: '#fff', strokeWidth: 2, hover: { size: 5 } },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.16, opacityTo: 0, stops: [0, 95] } },
            legend: { show: series.length > 1, position: 'top', horizontalAlign: 'right', fontSize: '12px', labels: { colors: '#64748b' }, markers: { width: 8, height: 8, radius: 8 } },
            dataLabels: { enabled: false },
            tooltip: { y: { formatter: (v) => v === null ? 'no data' : '₹' + v + 'L' } },
        };
        if (chart) chart.destroy();
        chart = new ApexCharts(document.querySelector('#cpSalesTrendChart'), opts);
        chart.render();
        bigOpts = { ...opts, chart: { ...opts.chart, height: isPhone() ? 230 : 300 } };
        if (chartBig) chartBig.destroy();
        chartBig = new ApexCharts(document.querySelector('#cpSalesTrendChartBig'), bigOpts);
        chartBig.render();
    }
    // The big chart sits in a tab that is hidden when the page loads, so it is drawn at zero width; draw it again when the tab opens.
    const salesTabBtn = document.querySelector('[data-bs-target="#cpSales"]');
    if (salesTabBtn) salesTabBtn.addEventListener('shown.bs.tab', function () {
        if (!bigOpts) return;
        if (chartBig) chartBig.destroy();
        chartBig = new ApexCharts(document.querySelector('#cpSalesTrendChartBig'), bigOpts);
        chartBig.render();
    });
    function load(range) {
        fetch(CP_ROUTES.salesTrend + '?range=' + range).then(r => r.json()).then(d => render(d.labels, d.values, d.tally));
    }
    document.querySelectorAll('.cp-range-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cp-range-btn').forEach(b => b.classList.replace('btn-primary', 'btn-outline-secondary'));
            this.classList.replace('btn-outline-secondary', 'btn-primary');
            load(this.dataset.range);
        });
    });
    load('1Y');
    // turning the phone (or resizing the window) across the phone breakpoint redraws the charts at the right height
    const phoneMq = window.matchMedia('(max-width: 575px)');
    const onPhoneChange = function () { const on = document.querySelector('.cp-range-btn.btn-primary'); load(on ? on.dataset.range : '1Y'); };
    if (phoneMq.addEventListener) phoneMq.addEventListener('change', onPhoneChange); else phoneMq.addListener(onPhoneChange);

    // Health score radial gauge
    const healthChart = new ApexCharts(document.querySelector('#cpHealthRing'), {
        chart: { type: 'radialBar', height: 96, width: 96, sparkline: { enabled: true } },
        series: [{{ $health['score'] }}],
        plotOptions: { radialBar: { hollow: { size: '58%' }, track: { background: '#eef2f6', strokeWidth: '100%' }, dataLabels: { name: { show: false }, value: { offsetY: 6, fontSize: '16px', fontWeight: 700, formatter: (v) => v + '%' } } } },
        stroke: { lineCap: 'round' },
        colors: ['{{ $health['score'] >= 75 ? '#22c55e' : ($health['score'] >= 50 ? '#f59e0b' : '#ef4444') }}'],
    });
    healthChart.render();
})();
</script>
@endsection
