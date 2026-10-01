@extends('layouts/blankLayout')

@section('title', $module->title . ' — HiTech Academy')

@section('vendor-style')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('page-style')
<style>
html, body { height: 100%; overflow: hidden; }
body { display: flex; flex-direction: column; background: #f4f7f9; }

/* Page-wide, not on .rd-root: the quiz layer sits outside .rd-root, and with
   the colours undefined there its Next and Submit buttons drew white on white. */
:root {
    --rd-primary: #0b5f68; --rd-primary-dark: #07464d; --rd-ink: #1e293b; --rd-navy: #13235b; --rd-muted: #64748b;
    --rd-border: #e3ebee; --rd-soft: #f4f8f9; --rd-green: #22c55e; --rd-orange: #f59e0b; --rd-red: #ef4444;
}
.rd-root { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; color: var(--rd-ink); }

/* ── Header ─────────────────────────────────────── */
.rd-top { height: 64px; flex-shrink: 0; background: #fff; border-bottom: 1px solid var(--rd-border); display: flex; align-items: center; gap: 28px; padding: 0 28px; z-index: 300; }
.rd-logo img { height: 38px; width: auto; display: block; }
.rd-nav { display: flex; align-items: stretch; gap: 26px; height: 100%; }
.rd-nav a { display: flex; align-items: center; font-size: .88rem; font-weight: 500; color: #475569; text-decoration: none; border-bottom: 3px solid transparent; padding-top: 3px; }
.rd-nav a:hover { color: var(--rd-primary); }
.rd-nav a.active { color: var(--rd-primary); font-weight: 700; border-bottom-color: var(--rd-primary); }
.rd-user { margin-left: auto; }
.rd-user-btn { display: flex; align-items: center; gap: 10px; background: transparent; border: 0; padding: 4px; cursor: pointer; color: var(--rd-ink); }
.rd-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--rd-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; flex-shrink: 0; }
.rd-user-text { flex-direction: column; align-items: flex-start; line-height: 1.2; }
.rd-user-text b { font-size: .86rem; }
.rd-user-text small { font-size: .74rem; color: var(--rd-muted); }
.rd-progress { height: 3px; background: #e8eef0; flex-shrink: 0; }
.rd-progress > div { height: 100%; background: linear-gradient(90deg, #0b7c86, #22c55e); transition: width .5s ease; }

/* ── Page ───────────────────────────────────────── */
.rd-page { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 12px; padding: 14px 28px 18px; }
.rd-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: .82rem; color: var(--rd-muted); }
.rd-crumbs a { color: #4a6fa5; text-decoration: none; }
.rd-crumbs a:hover { color: var(--rd-primary); text-decoration: underline; }
.rd-crumbs i { font-size: 1rem; color: #94a3b8; }
.rd-crumbs b { color: var(--rd-navy); font-weight: 600; }
.rd-head { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.rd-type { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; color: #fff; flex-shrink: 0; }
.ic-pdf { background: #e5262d; } .ic-video { background: #f59e0b; } .ic-text { background: #0b7c86; } .ic-quiz { background: #e5262d; }
.rd-head-text { flex: 1; min-width: 240px; }
.rd-head h1 { font-size: 1.7rem; font-weight: 800; color: var(--rd-navy); margin: 0 0 4px; line-height: 1.2; }
.rd-meta { display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: .84rem; color: var(--rd-muted); }
.rd-meta span { display: inline-flex; align-items: center; gap: 6px; }
.rd-meta i { font-size: 1.1rem; }
.rd-meta .ok { color: #15803d; font-weight: 600; }
.rd-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.rd-btn-outline { display: inline-flex; align-items: center; gap: 8px; height: 46px; padding: 0 18px; border-radius: 10px; border: 1px solid #d5dfe3; background: #fff; color: var(--rd-navy); font-weight: 600; font-size: .9rem; cursor: pointer; }
.rd-btn-outline:hover, .rd-btn-outline.active { border-color: var(--rd-primary); color: var(--rd-primary); }
.rd-btn-outline i { font-size: 1.2rem; }
.assess-btn { display: inline-flex; align-items: center; gap: 8px; height: 46px; padding: 0 22px; border-radius: 10px; border: 0; background: #94a3b8; color: #fff; font-weight: 700; font-size: .9rem; cursor: pointer; white-space: nowrap; transition: background .2s; }
.assess-btn i { font-size: 1.25rem; }
.assess-btn.ready { background: var(--rd-primary); box-shadow: 0 6px 16px rgba(11, 95, 104, .25); }
.assess-btn.ready:hover { background: var(--rd-primary-dark); }

/* Timer */
.timer-chip { display: inline-flex; align-items: center; gap: 6px; height: 46px; padding: 0 16px; border-radius: 10px; font-size: .88rem; font-weight: 700; border: 1px solid #d5dfe3; color: var(--rd-muted); background: #fff; font-variant-numeric: tabular-nums; white-space: nowrap; transition: all .3s; }
.timer-chip i { font-size: 1.15rem; }
.timer-chip.active  { border-color: #7dd3fc; color: #0369a1; background: #f0f9ff; }
.timer-chip.warning { border-color: #fdba74; color: #c2410c; background: #fff7ed; }
.timer-chip.done    { border-color: #86efac; color: #15803d; background: #f0fdf4; }

/* ── Grid: modules | viewer ─────────────────────── */
.rd-grid { flex: 1; min-height: 0; display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 16px; }
.rd-card { background: #fff; border: 1px solid var(--rd-border); border-radius: 14px; box-shadow: 0 2px 10px rgba(15, 23, 42, .04); }

.course-nav { overflow-y: auto; min-height: 0; }
.course-nav::-webkit-scrollbar { width: 5px; }
.course-nav::-webkit-scrollbar-thumb { background: #cfd8dc; border-radius: 4px; }
.cn-mobile-head { display: none; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--rd-border); }
.cn-mobile-head button { background: transparent; border: 0; font-size: 1.4rem; color: var(--rd-muted); }
.cnav-phase { border-bottom: 1px solid #eef3f4; }
.cnav-phase:last-child { border-bottom: 0; }
.cnav-phase-header { display: flex; align-items: flex-start; gap: 10px; padding: 16px 18px 12px; cursor: pointer; user-select: none; }
.cnav-phase-header:hover { background: #fafcfc; }
.cnav-phase-info { flex: 1; min-width: 0; }
.cnav-phase-label { font-size: 1rem; font-weight: 800; color: var(--rd-navy); }
.cnav-phase-sub { font-size: .8rem; color: #4a6fa5; margin-top: 1px; }
.cnav-phase-count { font-size: .8rem; color: var(--rd-ink); margin-top: 10px; }
.cnav-chevron { font-size: 1.3rem; color: #475569; transition: transform .2s; margin-top: 2px; }
.cnav-chevron.open { transform: rotate(180deg); }
.cnav-module-list { display: none; border-top: 1px solid #eef3f4; }
.cnav-module-list.open { display: block; }
.cnav-mod { display: flex; align-items: center; gap: 12px; padding: 13px 18px; border-bottom: 1px solid #f1f5f6; border-left: 3px solid transparent; text-decoration: none; color: var(--rd-navy); font-size: .86rem; transition: background .15s; }
.cnav-mod:last-child { border-bottom: 0; }
.cnav-mod:hover:not(.cnav-locked) { background: #f6fafb; color: var(--rd-navy); }
.cnav-mod.cnav-current { background: #e8f5f6; border-left-color: var(--rd-primary); font-weight: 700; color: var(--rd-primary-dark); }
.cnav-mod.cnav-locked { cursor: not-allowed; pointer-events: none; }
.cnav-mod.cnav-locked .cnav-mod-title { color: #64748b; }
.cnav-num { width: 18px; font-size: .84rem; color: #475569; flex-shrink: 0; }
.cnav-mod-title { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cnav-state { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.cs-done { background: var(--rd-green); color: #fff; }
.cs-current { background: var(--rd-primary); color: #fff; }
.cs-progress { border: 6px solid var(--rd-orange); background: #fff; }
.cs-failed { background: var(--rd-red); color: #fff; }
.cs-open { border: 2px solid #94a3b8; background: #fff; }
.cs-locked { color: #94a3b8; font-size: 1.35rem; }

/* ── Viewer ─────────────────────────────────────── */
.rd-view { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.rd-view-head { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 14px; padding: 10px 16px; border-bottom: 1px solid var(--rd-border); flex-shrink: 0; }
.rd-type-sm { width: 40px; height: 40px; border-radius: 9px; font-size: 1.35rem; }
.rd-view-title { font-size: 1.2rem; font-weight: 800; color: var(--rd-navy); margin: 0 0 2px; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rd-view-head .rd-head-text { min-width: 200px; }
.rd-view-head .rd-meta { font-size: .78rem; gap: 2px 16px; }
.rd-view-head .rd-meta i { font-size: 1rem; }
.rd-view-head .timer-chip, .rd-view-head .rd-btn-outline, .rd-view-head .assess-btn { height: 40px; }
.rd-view-head .rd-btn-outline { padding: 0 14px; }
.rd-view-head .assess-btn { padding: 0 18px; }
.rd-file-ic { width: 40px; height: 40px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
.rd-file-ic.ic-pdf { background: #fdecec; color: #e5262d; } .rd-file-ic.ic-video { background: #fff5e0; color: #d97706; } .rd-file-ic.ic-text { background: #e0f2f1; color: #0b6b73; }
.rd-file-text { flex: 1; min-width: 0; line-height: 1.3; }
.rd-file-text b { display: block; font-size: .95rem; color: var(--rd-navy); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rd-file-text small { font-size: .8rem; color: #4a6fa5; }
.rd-tools { display: flex; align-items: center; gap: 14px; }
.book-zoom-group { display: flex; align-items: center; border: 1px solid #d5dfe3; border-radius: 10px; overflow: hidden; }
.book-zoom-btn { width: 38px; height: 38px; background: #fff; border: 0; font-size: 1.2rem; color: var(--rd-navy); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.book-zoom-btn:hover { background: #f1f5f6; }
.book-zoom-label { min-width: 58px; text-align: center; font-size: .86rem; font-weight: 600; color: var(--rd-navy); border-left: 1px solid #e3ebee; border-right: 1px solid #e3ebee; line-height: 38px; }
.rd-tool { width: 38px; height: 38px; border: 0; background: transparent; border-radius: 8px; color: var(--rd-navy); font-size: 1.35rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.rd-tool:hover { background: #f1f5f6; }
.rd-view-body { flex: 1; min-height: 0; position: relative; }
.rd-view-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; border-top: 1px solid var(--rd-border); flex-shrink: 0; background: #fff; }
.rd-foot-btn { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border-radius: 9px; font-size: .86rem; font-weight: 600; text-decoration: none; border: 1px solid #d5dfe3; background: #fff; color: var(--rd-navy); max-width: 260px; white-space: nowrap; flex-shrink: 0; }
.rd-foot-btn span { overflow: hidden; text-overflow: ellipsis; }
.rd-foot-btn:hover { border-color: var(--rd-primary); color: var(--rd-primary); }
.rd-foot-btn.primary { background: var(--rd-primary); border-color: var(--rd-primary); color: #fff; }
.rd-foot-btn.primary:hover { background: var(--rd-primary-dark); color: #fff; }
.rd-foot-btn i { font-size: 1.2rem; }
.rd-foot-spacer { flex: 1; }
.course-nav-toggle { display: none; }

/* PDF / flip book */
.rd-book-body { background: #4a5160; display: flex; flex-direction: column; overflow: hidden; }
.book-loading-screen { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; color: #e2e8f0; }
.book-loading-emoji { font-size: 3rem; color: #fff; animation: bookFloat 1.4s ease-in-out infinite; }
@keyframes bookFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.book-loading-label { font-size: .9rem; font-weight: 600; }
.book-loading-track { width: 220px; height: 5px; background: rgba(255,255,255,.15); border-radius: 3px; overflow: hidden; }
.book-loading-bar { height: 100%; width: 0; background: #5eead4; border-radius: 3px; transition: width .3s ease; }
/* Zoom makes room: the zoomer box takes the zoomed size, so the stage can scroll
   to every part of the page instead of clipping it (margin:auto centres it
   while it fits, and never pushes it out of reach once it does not). */
.book-stage { flex: 1; display: flex; min-height: 0; padding: 8px 16px; position: relative; overflow: auto; }
.book-stage::-webkit-scrollbar { width: 10px; height: 10px; }
.book-stage::-webkit-scrollbar-thumb { background: rgba(255,255,255,.35); border-radius: 6px; }
.book-zoomer { position: relative; margin: auto; flex-shrink: 0; }
#book-container { transform-origin: 0 0; }
/* Zoomed in: drag to move around the page (turn pages with the arrows). */
.book-pan { position: absolute; inset: 0; z-index: 30; display: none; cursor: grab; touch-action: none; }
.book-stage.is-zoomed .book-pan { display: block; }
.book-pan.grabbing { cursor: grabbing; }
.book-page { background: #fff; overflow: hidden; }
.book-page img { display: block; width: 100%; height: 100%; object-fit: contain; user-select: none; -webkit-user-drag: none; pointer-events: none; }
.book-controls { display: flex; align-items: center; gap: 10px; }
.rd-pg-btn { width: 36px; height: 36px; border-radius: 8px; border: 1px solid #d5dfe3; background: #fff; color: var(--rd-navy); font-size: 1.3rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.rd-pg-btn:hover:not(:disabled) { border-color: var(--rd-primary); color: var(--rd-primary); }
.rd-pg-btn:disabled { opacity: .35; cursor: not-allowed; }
.book-page-info { font-size: .88rem; font-weight: 600; color: var(--rd-navy); min-width: 96px; text-align: center; font-variant-numeric: tabular-nums; }
.book-reader:fullscreen { border-radius: 0; border: 0; width: 100vw; height: 100vh; }
.book-reader:-webkit-full-screen { border-radius: 0; border: 0; width: 100vw; height: 100vh; }

/* Video */
.rd-video-body { background: #eef2f5; overflow-y: auto; display: flex; justify-content: center; align-items: flex-start; padding: 22px; }
.video-wrap { width: 100%; max-width: 1000px; }
.video-player { width: 100%; aspect-ratio: 16 / 9; background: #000; border-radius: 12px; overflow: hidden; position: relative; box-shadow: 0 16px 40px rgba(15, 23, 42, .22); }
.video-player > video { width: 100%; height: 100%; border: 0; display: block; }
.video-time-overlay { position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,.6); color: #fff; padding: 3px 11px; border-radius: 20px; font-size: .76rem; font-weight: 600; display: none; pointer-events: none; z-index: 3; }
#milestone-overlay { position: absolute; inset: 0; background: rgba(8, 15, 20, .93); display: none; flex-direction: column; align-items: center; justify-content: center; padding: 24px; text-align: center; color: #fff; z-index: 50; }
.ms-badge { display: inline-block; background: #d97706; color: #fff; font-size: .7rem; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: .5px; }
.ms-q { font-size: 1.05rem; font-weight: 700; max-width: 460px; margin-bottom: 20px; line-height: 1.4; }
.ms-opt { display: block; width: 100%; max-width: 380px; margin: 0 auto 8px; padding: 10px 16px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.06); color: #fff; border-radius: 8px; font-size: .85rem; cursor: pointer; text-align: left; }
.ms-opt:hover { background: rgba(255,255,255,.12); }
.ms-opt.sel { background: #0b7c86; border-color: #2dd4bf; }
.ms-submit { margin-top: 14px; padding: 10px 28px; border-radius: 20px; background: #d97706; border: 0; color: #fff; font-size: .88rem; font-weight: 700; cursor: pointer; }
.ms-submit:disabled { opacity: .45; cursor: not-allowed; }
.video-chapters-bar { margin-top: 16px; background: #fff; border: 1px solid var(--rd-border); border-radius: 12px; overflow: hidden; }
.chap-head { padding: 10px 16px; border-bottom: 1px solid var(--rd-border); font-size: .84rem; font-weight: 700; color: var(--rd-navy); }
.chap-list { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 16px; }
.chap-chip { display: inline-flex; align-items: center; gap: 6px; background: #f4f8f9; border: 1px solid var(--rd-border); color: var(--rd-ink); font-size: .76rem; font-weight: 600; padding: 5px 12px; border-radius: 20px; cursor: pointer; }
.chap-chip:hover, .chap-chip.active { color: var(--rd-primary); border-color: var(--rd-primary); background: #e8f5f6; }

/* Text */
.rd-text-body { background: #eef2f5; overflow-y: auto; padding: 24px 20px 40px; }
.text-content-wrap { max-width: 780px; margin: 0 auto; }
.text-module-card { background: #fff; border-radius: 12px; padding: 40px 48px; box-shadow: 0 8px 28px rgba(15, 23, 42, .08); color: var(--rd-ink); }
.text-module-card h2 { font-size: 1.35rem; font-weight: 800; color: var(--rd-navy); margin: 1.6rem 0 .6rem; }
.text-module-card h3 { font-size: 1.1rem; font-weight: 700; color: #334155; margin: 1.3rem 0 .5rem; }
.text-module-card h4 { font-size: .95rem; font-weight: 700; color: #475569; margin: 1.1rem 0 .4rem; }
.text-module-card p { font-size: 1rem; line-height: 1.85; color: #475569; margin-bottom: 1rem; }
.text-module-card ul, .text-module-card ol { padding-left: 1.5rem; margin-bottom: 1rem; }
.text-module-card li { font-size: 1rem; line-height: 1.8; color: #475569; margin-bottom: .35rem; }
.text-module-card blockquote { border-left: 4px solid #0b7c86; padding: 12px 20px; margin: 1.5rem 0; background: #f0fbfb; border-radius: 0 8px 8px 0; font-style: italic; color: #0b5f68; }
.text-module-card hr { border: 0; border-top: 1px solid #e2e8f0; margin: 2rem 0; }
.read-complete-msg { margin-top: 32px; padding: 24px; border-radius: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; text-align: center; display: none; }
.read-complete-msg h4 { color: #065f46; margin-bottom: 6px; }
.read-complete-msg p { color: #047857; font-size: .88rem; margin-bottom: 16px; }
.rcm-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--rd-primary); color: #fff; font-size: .9rem; font-weight: 700; padding: 12px 28px; border-radius: 10px; border: 0; cursor: pointer; }
.rcm-btn:hover { background: var(--rd-primary-dark); }

/* ── Notes (slide-over) ─────────────────────────── */
.notes-panel { position: fixed; right: 0; bottom: 0; width: 330px; max-width: 100vw; background: #fff; border-left: 1px solid var(--rd-border); box-shadow: -12px 0 30px rgba(15, 23, 42, .1); display: flex; flex-direction: column; transform: translateX(105%); transition: transform .25s ease; z-index: 400; }
.notes-panel.open { transform: translateX(0); }
.notes-header { padding: 14px 16px; border-bottom: 1px solid var(--rd-border); display: flex; align-items: center; justify-content: space-between; }
.notes-header span { font-size: .9rem; font-weight: 700; color: var(--rd-navy); }
.notes-header button { background: transparent; border: 0; font-size: 1.3rem; color: var(--rd-muted); cursor: pointer; }
.notes-body { flex: 1; padding: 12px; display: flex; flex-direction: column; gap: 10px; min-height: 0; }
#notes-ta { flex: 1; width: 100%; min-height: 200px; border: 1px solid #d5dfe3; border-radius: 10px; padding: 12px; font-size: .86rem; line-height: 1.7; font-family: inherit; resize: none; outline: none; color: var(--rd-ink); }
#notes-ta:focus { border-color: var(--rd-primary); }
.notes-footer { display: flex; gap: 8px; }
.notes-btn { flex: 1; padding: 8px; border-radius: 8px; border: 1px solid #d5dfe3; background: #fff; color: var(--rd-ink); font-size: .78rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; }
.notes-btn:hover { border-color: var(--rd-primary); color: var(--rd-primary); }
.notes-btn.danger:hover { border-color: var(--rd-red); color: var(--rd-red); }
.notes-save-status { font-size: .72rem; color: var(--rd-muted); padding: 4px 12px 12px; text-align: center; }

/* ── Checkpoint ─────────────────────────────────── */
.pdf-checkpoint { position: fixed; inset: 0; z-index: 8000; background: rgba(15, 23, 42, .55); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; padding: 24px; }
.pdf-checkpoint.open { display: flex; animation: rdFade .25s ease; }
@keyframes rdFade { from { opacity: 0; transform: scale(.97); } to { opacity: 1; transform: scale(1); } }
.checkpoint-card { background: #fff; border-radius: 16px; padding: 30px 28px; max-width: 480px; width: 100%; box-shadow: 0 30px 70px rgba(15, 23, 42, .3); }
.checkpoint-badge { display: inline-flex; align-items: center; gap: 6px; background: #e8f5f6; color: var(--rd-primary); font-size: .7rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; padding: 4px 12px; border-radius: 20px; margin-bottom: 14px; }
.checkpoint-title { font-size: 1.05rem; font-weight: 800; color: var(--rd-navy); margin-bottom: 8px; }
.checkpoint-body { font-size: .88rem; color: #475569; line-height: 1.65; margin-bottom: 20px; }
.checkpoint-opts { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
.checkpoint-continue { width: 100%; padding: 12px; border-radius: 10px; background: var(--rd-primary); border: 0; color: #fff; font-size: .9rem; font-weight: 700; cursor: pointer; display: none; }
.checkpoint-continue:hover { background: var(--rd-primary-dark); }

/* ══ QUIZ ═══════════════════════════════════════ */
#quiz-overlay { position: fixed; left: 0; right: 0; bottom: 0; top: 67px; z-index: 9000; background: #f4f7f9; display: none; overflow-y: auto; }
/* Pop-ups (Submitting..., "answer all questions", the server's reason for
   refusing a submission) must show above the quiz: at SweetAlert's own
   z-index (1060) they drew behind it, and a refused Submit looked dead. */
.swal2-container { z-index: 20000 !important; }
.qz-page { max-width: 1440px; margin: 0 auto; padding: 16px 28px 40px; display: flex; flex-direction: column; gap: 14px; }
.qz-badge { display: inline-block; vertical-align: middle; margin-left: 10px; font-size: .85rem; font-weight: 700; color: #4f46e5; background: #e0e7ff; padding: 4px 12px; border-radius: 8px; }
.qz-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 16px; align-items: start; }
.qz-main { padding: 26px 30px 24px; }
.qz-progress-row { display: flex; align-items: center; gap: 22px; padding-bottom: 20px; border-bottom: 1px solid #edf2f4; margin-bottom: 24px; }
.qz-progress-row b { font-size: 1.05rem; color: var(--rd-navy); white-space: nowrap; }
.qz-track { flex: 1; height: 10px; background: #e8eef2; border-radius: 10px; overflow: hidden; }
.qz-track > span { display: block; height: 100%; width: 0; border-radius: 10px; background: linear-gradient(90deg, #0b7c86, #22b8c9); transition: width .3s; }
.qz-pct { font-size: 1rem; color: var(--rd-navy); white-space: nowrap; }
.qz-question { display: flex; gap: 22px; align-items: flex-start; background: #f2f7fb; border-radius: 12px; padding: 20px 22px; margin-bottom: 20px; }
.qz-qno { width: 54px; height: 54px; border-radius: 10px; background: #0b8f9c; color: #fff; font-size: 1.2rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.quiz-q-text { font-size: 1.3rem; font-weight: 800; color: var(--rd-navy); line-height: 1.35; }
.qz-hint { font-size: .95rem; color: #475569; margin-top: 6px; }
.quiz-opts-wrap { display: flex; flex-direction: column; gap: 14px; }
.quiz-opt { display: flex; align-items: center; gap: 20px; padding: 18px 24px; border: 1px solid #dfe7eb; border-radius: 10px; background: #fff; cursor: pointer; transition: all .15s; }
.quiz-opt:hover { border-color: #7dd3d8; }
.quiz-opt.sel { border-color: #7dd3d8; background: #e9f9fa; }
.opt-radio { width: 24px; height: 24px; border-radius: 50%; border: 2px solid #475569; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.quiz-opt.sel .opt-radio { border-color: #0b8f9c; }
.quiz-opt.sel .opt-radio::after { content: ''; width: 12px; height: 12px; border-radius: 50%; background: #0b8f9c; }
.opt-text { font-size: 1.02rem; color: var(--rd-navy); flex: 1; }
.quiz-card-foot { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-top: 30px; }
.qz-exit small { display: block; font-size: .8rem; color: var(--rd-muted); margin-top: 6px; }
.qfoot-btn { display: inline-flex; align-items: center; gap: 10px; height: 54px; padding: 0 26px; border-radius: 10px; border: 1px solid #d5dfe3; background: #fff; color: var(--rd-navy); font-size: 1rem; font-weight: 600; cursor: pointer; }
.qfoot-btn i { font-size: 1.3rem; }
.qfoot-btn:hover { border-color: var(--rd-primary); color: var(--rd-primary); }
.qfoot-btn.primary, .qfoot-btn.success { background: var(--rd-primary); border-color: var(--rd-primary); color: #fff; padding: 0 40px; }
.qfoot-btn.primary:hover, .qfoot-btn.success:hover { background: var(--rd-primary-dark); color: #fff; }
.qfoot-btn.flagged { border-color: #fca5a5; color: #b91c1c; background: #fef2f2; }
.q-dots { display: none !important; }
.quiz-all-block { display: flex; flex-direction: column; gap: 18px; }
.qall-q { border: 1px solid #e6edf0; border-radius: 12px; padding: 18px; }
.qall-q-text { font-size: 1.02rem; font-weight: 700; color: var(--rd-navy); margin-bottom: 12px; line-height: 1.4; display: flex; gap: 10px; }
.qall-q-num { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; height: 30px; background: #0b8f9c; color: #fff; border-radius: 8px; font-size: .78rem; font-weight: 800; }
.qall-q .quiz-opt { margin-bottom: 10px; padding: 14px 18px; }
.qz-all-count { font-size: .9rem; color: var(--rd-muted); align-self: center; }

.qz-side { display: flex; flex-direction: column; gap: 14px; }
.qz-side .rd-card { padding: 16px; }
.qz-timer { display: flex; align-items: center; gap: 16px; background: #f2f7fb; border-radius: 10px; padding: 14px 16px; }
.qz-timer i { font-size: 2rem; color: #2563eb; }
.qz-timer small { display: block; font-size: .86rem; color: var(--rd-navy); }
.qz-timer b { font-size: 1.7rem; font-weight: 800; color: var(--rd-navy); font-variant-numeric: tabular-nums; line-height: 1.1; }
.qz-side h6 { font-size: 1rem; font-weight: 700; color: var(--rd-navy); margin: 4px 0 14px; }
.qz-nav { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.qz-nav button { width: 44px; height: 44px; border-radius: 50%; border: 0; background: #eef2f5; color: var(--rd-navy); font-size: .95rem; cursor: pointer; }
.qz-nav button.ans { background: #22c55e; color: #fff; }
.qz-nav button.flag { background: #ef4444; color: #fff; }
.qz-nav button.cur { background: #0b8f9c; color: #fff; box-shadow: 0 0 0 3px #b8ecef; }
.qz-legend { list-style: none; padding: 0; margin: 0; font-size: .86rem; color: #475569; }
.qz-legend li { display: flex; align-items: center; gap: 12px; padding: 3px 0; }
.qz-legend .dot { width: 11px; height: 11px; border-radius: 50%; }
.qz-info { list-style: none; padding: 0; margin: 0 0 16px; font-size: .86rem; }
.qz-info li { display: flex; align-items: center; gap: 12px; padding: 6px 0; color: #475569; }
.qz-info i { font-size: 1.2rem; color: #2563eb; }
.qz-info b { margin-left: auto; color: var(--rd-navy); font-weight: 600; text-align: right; }
.qz-instr { border-top: 1px solid #edf2f4; padding-top: 14px; }
.qz-instr h6 { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.qz-instr h6 i { font-size: 1.4rem; color: #2563eb; }
.qz-instr ul { margin: 0; padding-left: 28px; font-size: .84rem; color: #475569; }
.qz-instr li { padding: 2px 0; }

.result-screen { display: none; text-align: center; padding: 40px 28px; }
.result-ring { position: relative; display: inline-flex; margin-bottom: 20px; }
.result-ring-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.result-pct { font-size: 1.8rem; font-weight: 900; line-height: 1; }
.result-label { font-size: .62rem; color: var(--rd-muted); text-transform: uppercase; letter-spacing: .5px; }
.result-title { font-size: 1.6rem; font-weight: 900; color: var(--rd-navy); margin-bottom: 6px; }
.result-msg { font-size: .95rem; color: #475569; margin-bottom: 28px; line-height: 1.6; }
.result-btns { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.res-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; border-radius: 10px; font-size: .9rem; font-weight: 700; cursor: pointer; border: 1px solid #d5dfe3; background: #fff; color: var(--rd-navy); text-decoration: none; }
.res-btn:hover { border-color: var(--rd-primary); color: var(--rd-primary); }
.res-btn.primary { background: var(--rd-primary); border-color: var(--rd-primary); color: #fff; }
.res-btn.primary:hover { background: var(--rd-primary-dark); color: #fff; }

/* ── Responsive ─────────────────────────────────── */
@media (max-width: 1199.98px) {
    .qz-grid { grid-template-columns: minmax(0, 1fr); }
    .rd-nav { gap: 18px; }
}
@media (max-width: 991.98px) {
    .rd-page { overflow-y: auto; }
    .rd-grid { grid-template-columns: minmax(0, 1fr); flex: none; }
    .rd-view { min-height: 78vh; }
    .course-nav { position: fixed; top: 0; bottom: 0; left: 0; width: 300px; max-width: 88vw; z-index: 1200; border-radius: 0; transform: translateX(-105%); transition: transform .25s ease; }
    .course-nav.open { transform: translateX(0); box-shadow: 12px 0 30px rgba(15, 23, 42, .18); }
    .cn-mobile-head { display: flex; }
    .course-nav-toggle { display: inline-flex; }
}
@media (max-width: 767.98px) {
    .rd-top { padding: 0 14px; gap: 14px; }
    .rd-nav a:not(.active) { display: none; }
    .rd-page, .qz-page { padding: 12px 14px 16px; }
    .rd-head h1 { font-size: 1.3rem; }
    .text-module-card { padding: 24px 20px; }
    .qz-main { padding: 18px 16px; }
    .quiz-q-text { font-size: 1.1rem; }
    .qz-progress-row { gap: 12px; flex-wrap: wrap; }
    .rd-foot-btn { padding: 0 12px; }
    .rd-foot-btn span { display: none; }
    .book-zoom-label { min-width: 48px; }
}
</style>
@endsection

@section('content')

@php
    // Build prev/next module navigation
    $allModules = \App\Models\TrainingModule::whereHas('phase')
        ->with('phase')
        ->orderByRaw('(SELECT `order` FROM training_phases WHERE id = phase_id)')
        ->orderBy('order')
        ->get();

    $currentIdx = $allModules->search(fn($m) => $m->id == $module->id);
    $prevModule  = $currentIdx > 0 ? $allModules[$currentIdx - 1] : null;
    $nextModule  = $currentIdx !== false && $currentIdx < $allModules->count() - 1 ? $allModules[$currentIdx + 1] : null;

    // All phases with progress for course nav sidebar
    $user = auth()->user();
    $phases = \App\Models\TrainingPhase::with(['modules.userProgress' => function($q) use ($user) {
        $q->where('user_id', $user->id);
    }])->orderBy('order')->get();

    // ── Display only ──
    $needsQuiz = \App\Http\Controllers\tenant\TrainingController::needsQuiz($module);
    [$typeLabel, $typeIcon, $typeClass] = match($module->content_type) {
        'catalog' => ['PDF Document', 'bxs-file-pdf', 'ic-pdf'],
        'video'   => ['Video', 'bxs-videos', 'ic-video'],
        default   => ['Reading', 'bx-book-content', 'ic-text'],
    };
    // YouTube modules play through the protected player, which needs only the
    // video id - the page never prints the YouTube address itself.
    $ytId = null;
    if ($module->content_type === 'video' && !empty($module->content_url)
        && preg_match('/(?:youtu\.be\/|v=|embed\/|shorts\/)([a-zA-Z0-9_-]{11})/', $module->content_url, $ytm)) {
        $ytId = $ytm[1];
    }
    // Uploads are stored under random names, so name the file after the module.
    $fileName = ($module->content_type === 'catalog' && $module->content_url)
        ? preg_replace('/[^\w\-]+/u', '_', trim($module->title)) . '.pdf' : null;
    $splitPhase = function ($p) {
        if ($p && preg_match('/^\s*(Phase\s*\d+)\s*[:\-–]\s*(.+)$/iu', (string) $p->title, $m)) return [$m[1], trim($m[2])];
        return ['Phase ' . ($p->order ?? ''), (string) ($p->title ?? '')];
    };
    $attempts = (int) ($progress->attempts ?? 0);
    $passPct  = (int) ($module->passing_percentage ?? 80);
    $maxFails = max(1, (int) config('training.max_failed_attempts', 3));
    $cooldown = max(0, (int) config('training.retry_cooldown_minutes', 30));
    $initials = strtoupper(substr($user->first_name ?? $user->name ?? '?', 0, 1) . substr($user->last_name ?? '', 0, 1));
    $roleLine = optional($user->designation)->name ?? ucfirst((string) ($user->getRoleNames()->first() ?? ''));
@endphp

<div class="rd-root">

    {{-- ════ HEADER ════ --}}
    <header class="rd-top" id="rdTop">
        <a href="{{ route('training.portal') }}" class="rd-logo"><img src="{{ asset('assets/img/logo.png') }}" alt="HRX"></a>
        <nav class="rd-nav">
            <a href="{{ route('training.portal') }}" class="active">My Training</a>
            <a href="{{ route('training.report-card', $user->id) }}">My Results</a>
        </nav>
        <div class="rd-user dropdown">
            <button type="button" class="rd-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="rd-avatar">{{ $initials }}</span>
                <span class="rd-user-text d-none d-md-flex"><b>{{ $user->name }}</b>@if($roleLine)<small>{{ $roleLine }}</small>@endif</span>
                <i class="bx bx-chevron-down fs-4"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <li><a class="dropdown-item" href="{{ route('training.portal') }}"><i class="bx bx-book-reader me-2"></i>My Training</a></li>
                <li><a class="dropdown-item" href="{{ route('training.report-card', $user->id) }}"><i class="bx bx-certification me-2"></i>My Results</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="{{ route('auth.logout') }}" onclick="event.preventDefault(); document.getElementById('reader-logout-form').submit();"><i class="bx bx-log-out me-2"></i>Logout</a>
                    <form id="reader-logout-form" method="POST" action="{{ route('auth.logout') }}" class="d-none">@csrf</form>
                </li>
            </ul>
        </div>
    </header>
    <div class="rd-progress"><div id="top-progress" style="width:0%"></div></div>

    <div class="rd-page">
        <nav class="rd-crumbs" aria-label="Breadcrumb">
            <a href="{{ route('training.portal') }}">My Training</a>
            <i class="bx bx-chevron-right"></i>
            <a href="{{ route('training.portal') }}">{{ $module->phase->title ?? 'Training' }}</a>
            <i class="bx bx-chevron-right"></i>
            <b>{{ $module->title }}</b>
        </nav>

        <div class="rd-grid">

            {{-- ── Modules, phase by phase ── --}}
            <aside class="course-nav rd-card" id="course-nav">
                <div class="cn-mobile-head"><b>Course Contents</b><button type="button" onclick="closeCourseNav()" aria-label="Close"><i class="bx bx-x"></i></button></div>
                @foreach($phases as $pi => $phase)
                @php
                    $isCurrentPhase = $phase->id === $module->phase_id;
                    [$phLabel, $phSub] = $splitPhase($phase);
                    $phDone = $phase->modules->filter(fn($m) => optional($m->userProgress->first())->status === 'completed')->count();
                @endphp
                <div class="cnav-phase">
                    <div class="cnav-phase-header" onclick="toggleCnavPhase('cnav-phase-{{ $phase->id }}','cnav-chev-{{ $phase->id }}')">
                        <div class="cnav-phase-info">
                            <div class="cnav-phase-label">{{ $phLabel }}</div>
                            <div class="cnav-phase-sub">{{ $phSub }}</div>
                            <div class="cnav-phase-count">{{ $phDone }} of {{ $phase->modules->count() }} modules</div>
                        </div>
                        <i class="bx bx-chevron-down cnav-chevron {{ $isCurrentPhase ? 'open' : '' }}" id="cnav-chev-{{ $phase->id }}"></i>
                    </div>
                    <div class="cnav-module-list {{ $isCurrentPhase ? 'open' : '' }}" id="cnav-phase-{{ $phase->id }}">
                        @foreach($phase->modules as $mod)
                        @php
                            $mp     = $mod->userProgress->first();
                            $mst    = $mp ? $mp->status : 'locked';
                            $isCur  = $mod->id == $module->id;
                            $isDone = $mst === 'completed';
                            $isLock = $mst === 'locked' && !$isCur;
                            $cnavClass = $isCur ? 'cnav-current' : ($isDone ? 'cnav-completed' : ($isLock ? 'cnav-locked' : ''));
                            $cnavHref  = ($isLock && !$isCur) ? 'javascript:void(0)' : route('training.module.show', $mod->id);
                            [$csClass, $csIcon] = $isDone ? ['cs-done', 'bx-check']
                                : ($isCur ? ['cs-current', 'bx-play']
                                : ($isLock ? ['cs-locked', 'bxs-lock-alt']
                                : ($mst === 'failed' ? ['cs-failed', 'bx-x']
                                : ($mst === 'in_progress' ? ['cs-progress', null] : ['cs-open', null]))));
                        @endphp
                        <a href="{{ $cnavHref }}" class="cnav-mod {{ $cnavClass }}" title="{{ $mod->title }} · {{ $mod->estimated_time_minutes }} min{{ $mp && $mp->assessment_score > 0 ? ' · ' . round($mp->assessment_score) . '%' : '' }}" @if($isLock) tabindex="-1" aria-disabled="true" @endif>
                            <span class="cnav-num">{{ $loop->iteration }}</span>
                            <span class="cnav-mod-title">{{ $mod->title }}</span>
                            <span class="cnav-state {{ $csClass }}">@if($csIcon)<i class="bx {{ $csIcon }}"></i>@endif</span>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </aside>

            {{-- ── Viewer ── --}}
            @if($module->content_type === 'catalog' && $module->content_url)
            {{-- ══ CATALOG / PDF — page-flip book ══ --}}
            <section class="rd-view rd-card book-reader" id="book-reader">
                @include('tenant.training.partials.reader-view-head', ['isBook' => true])
                <div class="rd-view-body rd-book-body">
                    <div class="book-loading-screen" id="book-loading">
                        <div class="book-loading-emoji"><i class="bx bx-book-open"></i></div>
                        <div class="book-loading-label" id="book-loading-text">Opening catalog…</div>
                        <div class="book-loading-track"><div class="book-loading-bar" id="book-loading-fill"></div></div>
                    </div>
                    <div class="book-stage" id="book-stage" style="display:none;">
                        <div class="book-zoomer" id="book-zoomer">
                            <div id="book-container"></div>
                            <div class="book-pan" id="book-pan" title="Drag to move around the page · the arrows turn pages · double-click for 100%"></div>
                        </div>
                    </div>
                </div>
                <div class="rd-view-foot">
                    @include('tenant.training.partials.reader-foot-prev')
                    <div class="book-controls" id="book-controls" style="display:none;">
                        <button type="button" class="rd-pg-btn" id="book-prev" disabled title="Previous page"><i class="bx bx-chevron-left"></i></button>
                        <span class="book-page-info" id="book-page-info">Page 1 of ?</span>
                        <button type="button" class="rd-pg-btn" id="book-next" title="Next page"><i class="bx bx-chevron-right"></i></button>
                    </div>
                    @include('tenant.training.partials.reader-foot-next')
                </div>
            </section>

            @elseif($module->content_type === 'video')
            {{-- ══ VIDEO ══ --}}
            <section class="rd-view rd-card" id="rd-view">
                @include('tenant.training.partials.reader-view-head')
                <div class="rd-view-body rd-video-body">
                    {{-- No right-click on the player: "Copy video URL" and "Save
                         video as" live there. The YouTube player adds its own
                         shield and controls on top of this. --}}
                    <div class="video-wrap" oncontextmenu="return false;">
                        <div class="video-player" id="video-player">
                            @if($ytId)
                                <div id="yt-player"></div>
                            @elseif(!empty($module->content_url))
                                <video id="local-video" controls controlsList="nodownload noremoteplayback" disablePictureInPicture oncontextmenu="return false;"
                                       data-src="{{ $module->content_url }}"></video>
                                <div class="video-time-overlay" id="vid-time"></div>
                            @else
                                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;flex-direction:column;gap:8px;">
                                    <i class="bx bx-video-off" style="font-size:2.5rem;opacity:.5;"></i>
                                    <span style="font-size:.85rem;">No video URL configured</span>
                                </div>
                            @endif
                            <div id="milestone-overlay">
                                <span class="ms-badge">⚡ Checkpoint Quiz</span>
                                <div class="ms-q" id="ms-q"></div>
                                <div id="ms-opts"></div>
                                <button type="button" class="ms-submit" id="ms-submit" disabled>Submit Answer</button>
                            </div>
                        </div>

                        @if(!empty($module->video_chapters))
                        <div class="video-chapters-bar">
                            <div class="chap-head"><i class="bx bx-list-ol me-1"></i> Chapters</div>
                            <div class="chap-list" id="chap-list"></div>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="rd-view-foot">
                    @include('tenant.training.partials.reader-foot-prev')
                    <span class="rd-foot-spacer"></span>
                    @include('tenant.training.partials.reader-foot-next')
                </div>
            </section>

            @else
            {{-- ══ TEXT / POLICY ══ --}}
            <section class="rd-view rd-card" id="rd-view">
                @include('tenant.training.partials.reader-view-head')
                <div class="rd-view-body rd-text-body" id="content-pane">
                    <div class="text-content-wrap">
                        <div class="text-module-card" id="text-card">
                            <div id="text-body">
                                {!! \Illuminate\Support\Str::of($module->content_body ?? '<p><em>No content yet.</em></p>')
                                    ->replace("\r\n", "\n")->replace("\r", "\n")
                                    ->pipe(fn($s) => preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $s))
                                    ->pipe(fn($s) => preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $s))
                                    ->pipe(fn($s) => preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $s))
                                    ->pipe(fn($s) => preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s))
                                    ->pipe(fn($s) => preg_replace('/\*(.+?)\*/', '<em>$1</em>', $s))
                                    ->pipe(fn($s) => preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $s))
                                    ->pipe(fn($s) => preg_replace('/^---$/m', '<hr>', $s))
                                    ->pipe(fn($s) => preg_replace('/^- (.+)$/m', '<li>$1</li>', $s))
                                    ->pipe(fn($s) => preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $s))
                                    ->pipe(fn($s) => nl2br($s))
                                !!}
                            </div>
                            <div class="read-complete-msg" id="read-complete">
                                <div style="font-size:2.5rem;margin-bottom:8px;">✅</div>
                                <h4>Reading Complete!</h4>
                                <p>You've completed the minimum reading time. Take the quiz when ready.</p>
                                <button type="button" class="rcm-btn" onclick="openQuiz()"><i class="bx bx-list-check"></i> Start Quiz</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rd-view-foot">
                    @include('tenant.training.partials.reader-foot-prev')
                    <span class="rd-foot-spacer"></span>
                    @include('tenant.training.partials.reader-foot-next')
                </div>
            </section>
            @endif
        </div>
    </div>

    {{-- ── Notes (slide-over) ── --}}
    <div class="notes-panel" id="notes-panel">
        <div class="notes-header">
            <span><i class="bx bx-notepad me-1" style="color:#0b7c86;"></i>My Notes</span>
            <button type="button" onclick="closeNotes()" aria-label="Close notes"><i class="bx bx-x"></i></button>
        </div>
        <div class="notes-body">
            <textarea id="notes-ta" placeholder="Jot down key points, definitions, or anything worth remembering...

Auto-saved as you type."></textarea>
            <div class="notes-footer">
                <button type="button" class="notes-btn" onclick="copyNotes()"><i class="bx bx-copy"></i> Copy</button>
                <button type="button" class="notes-btn danger" onclick="clearNotes()"><i class="bx bx-trash"></i></button>
            </div>
        </div>
        <div class="notes-save-status" id="notes-status"><i class="bx bx-check-circle me-1" style="color:#16a34a;"></i>Auto-saved</div>
    </div>

    {{-- Checkpoint popup (PDF) --}}
    <div class="pdf-checkpoint" id="pdf-checkpoint">
        <div class="checkpoint-card">
            <div class="checkpoint-badge"><i class="bx bx-bolt-circle"></i> Page Checkpoint</div>
            <div class="checkpoint-title" id="cp-title">Quick Check</div>
            <div class="checkpoint-body" id="cp-body"></div>
            <div class="checkpoint-opts" id="cp-opts"></div>
            <button type="button" class="checkpoint-continue" id="cp-continue" onclick="closeCheckpoint()"><i class="bx bx-right-arrow-alt me-1"></i> Continue Reading</button>
        </div>
    </div>
</div>

{{-- ════ QUIZ ════ --}}
<div id="quiz-overlay">
    <div class="qz-page">
        <nav class="rd-crumbs" aria-label="Breadcrumb">
            <a href="{{ route('training.portal') }}">My Training</a>
            <i class="bx bx-chevron-right"></i>
            <a href="{{ route('training.portal') }}">{{ $module->phase->title ?? 'Training' }}</a>
            <i class="bx bx-chevron-right"></i>
            <a href="javascript:void(0)" onclick="closeQuiz()">{{ $module->title }}</a>
            <i class="bx bx-chevron-right"></i>
            <b>Quiz</b>
        </nav>
        <div class="rd-head">
            <span class="rd-type ic-quiz"><i class="bx bxs-edit"></i></span>
            <div class="rd-head-text">
                <h1>{{ $module->title }}<span class="qz-badge">Quiz</span></h1>
                <div class="rd-meta">
                    <span><i class="bx bx-book-open"></i> <span id="qz-meta-count">–</span>&nbsp;Questions</span>
                    <span><i class="bx bx-check-circle"></i> Passing Score: <span id="qz-meta-pass">{{ $passPct }}</span>%</span>
                    <span><i class="bx bx-file"></i> Attempt <span class="qz-attempt-no">{{ $attempts + 1 }}</span></span>
                    <span><i class="bx bx-time-five"></i> No time limit</span>
                </div>
            </div>
            <div class="rd-actions"><span class="text-muted small" id="quiz-sub">Loading...</span></div>
        </div>

        <div class="qz-grid">
            <div class="rd-card qz-main">
                <div id="quiz-card">
                    <div id="quiz-card-top">
                        <div class="qz-progress-row">
                            <b id="q-num">Question 1 of ?</b>
                            <div class="qz-track"><span id="qz-fill"></span></div>
                            <span class="qz-pct" id="qz-pct">0% Complete</span>
                        </div>
                        <div class="q-dots" id="q-dots"></div>
                    </div>
                    <div id="quiz-single-body">
                        <div class="qz-question">
                            <span class="qz-qno" id="qz-qno">Q1</span>
                            <div>
                                <div class="quiz-q-text" id="q-text">Loading questions...</div>
                                <div class="qz-hint">Select the correct answer from the options below.</div>
                            </div>
                        </div>
                        <div class="quiz-opts-wrap" id="q-opts"></div>
                        <div class="quiz-card-foot">
                            <div class="qz-exit">
                                <button type="button" class="qfoot-btn" onclick="closeQuiz()"><i class="bx bx-log-out"></i> Exit Quiz</button>
                                <small>Leaving draws a fresh set of questions next time.</small>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="qfoot-btn" id="q-prev" style="visibility:hidden;"><i class="bx bx-left-arrow-alt"></i> Previous</button>
                                <button type="button" class="qfoot-btn" id="q-flag" title="Mark this question to come back to"><i class="bx bx-flag"></i> <span>Review later</span></button>
                                <button type="button" class="qfoot-btn primary" id="q-next">Next Question <i class="bx bx-right-arrow-alt"></i></button>
                                <button type="button" class="qfoot-btn success" id="q-submit" style="display:none;"><i class="bx bx-check"></i> Submit Quiz</button>
                            </div>
                        </div>
                    </div>
                    <div id="quiz-all-body" style="display:none;">
                        <div class="quiz-all-block" id="q-all-content"></div>
                        <div class="quiz-card-foot">
                            <div class="qz-exit">
                                <button type="button" class="qfoot-btn" onclick="closeQuiz()"><i class="bx bx-log-out"></i> Exit Quiz</button>
                                <small>Leaving draws a fresh set of questions next time.</small>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="qz-all-count" id="q-all-count">0/0 answered</span>
                                <button type="button" class="qfoot-btn success" id="q-all-submit"><i class="bx bx-check"></i> Submit Quiz</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="result-screen" id="quiz-result">
                    <div class="result-ring" id="result-ring"></div>
                    <div id="result-grade-badge" style="margin-bottom:12px;"></div>
                    <div class="result-title" id="result-title"></div>
                    <div class="result-msg" id="result-msg"></div>
                    <div class="result-btns">
                        <a href="{{ route('training.portal') }}" class="res-btn" id="result-portal-btn"><i class="bx bx-grid-alt"></i> Portal</a>
                        <button type="button" class="res-btn" id="result-retry" style="display:none;" onclick="retakeQuiz()"><i class="bx bx-revision"></i> Retake</button>
                        <a href="#" class="res-btn primary" id="result-next-btn" style="display:none;"><i class="bx bx-right-arrow-alt"></i> Next Module</a>
                    </div>
                </div>
            </div>

            <aside class="qz-side">
                <div class="rd-card">
                    <div class="qz-timer"><i class="bx bx-time-five"></i><div><small>Time on this quiz</small><b id="qz-clock">00:00</b></div></div>
                </div>
                <div class="rd-card">
                    <h6>Question Navigation</h6>
                    <div class="qz-nav" id="qz-nav"></div>
                    <ul class="qz-legend">
                        <li><span class="dot" style="background:#0b8f9c"></span>Current Question</li>
                        <li><span class="dot" style="background:#e2e8f0"></span>Not Answered</li>
                        <li><span class="dot" style="background:#22c55e"></span>Answered</li>
                        <li><span class="dot" style="background:#ef4444"></span>Review Later</li>
                    </ul>
                </div>
                <div class="rd-card">
                    <h6>Quiz Information</h6>
                    <ul class="qz-info">
                        <li><i class="bx bx-message-square-detail"></i> Total Questions <b id="qz-info-count">–</b></li>
                        <li><i class="bx bx-time-five"></i> Time Limit <b>None</b></li>
                        <li><i class="bx bx-check-circle"></i> Passing Score <b><span id="qz-info-pass">{{ $passPct }}</span>%</b></li>
                        <li><i class="bx bx-trophy"></i> Retakes <b>{{ $cooldown > 0 ? $cooldown . ' min wait after ' . $maxFails . ' fails' : 'Unlimited' }}</b></li>
                        <li><i class="bx bx-user-check"></i> Current Attempt <b class="qz-attempt-no">{{ $attempts + 1 }}</b></li>
                    </ul>
                    <div class="qz-instr">
                        <h6><i class="bx bx-info-circle"></i> Instructions</h6>
                        <ul>
                            <li>Read each question carefully.</li>
                            <li>Select the best answer.</li>
                            <li>Move on with <strong>Next Question</strong>, or jump using the numbers above.</li>
                            <li>Every question must be answered before you submit.</li>
                            <li>You need {{ $passPct }}% to pass. Leaving draws a fresh set of questions next time.</li>
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

@if($ytId)
    @include('tenant.training.partials.secure-youtube')
@endif

@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/page-flip@2.0.7/dist/js/page-flip.browser.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
@endsection

@section('page-script')
<script>
/* ══════════════════════════════════════════
   CONFIG
══════════════════════════════════════════ */
const MOD_ID       = {{ $module->id }};
const CTYPE        = "{{ $module->content_type }}";
const CURL         = @json($ytId ? '' : ($module->content_url ?? ''));
const YT_ID        = @json($ytId);
const EST_MINS     = {{ $module->estimated_time_minutes ?? 5 }};
const VID_CHAPTERS = {!! json_encode($module->video_chapters ?? []) !!};
const VID_MILES    = {!! json_encode($module->video_milestones ?? []) !!};
const CSRF         = "{{ csrf_token() }}";
const ASSESS_URL        = "{{ route('training.assessment.get', $module->id) }}";
const SUBMIT_URL        = "{{ route('training.assessment.submit', $module->id) }}";
const NOTES_KEY         = "lms_notes_" + MOD_ID;
const NEXT_MODULE_URL   = @json($nextModule ? route('training.module.show', $nextModule->id) : null);
const NEXT_MODULE_TITLE = @json($nextModule ? $nextModule->title : null);
const IS_COMPLETED      = {{ $progress->status === 'completed' ? 'true' : 'false' }};
const ELAPSED_SECS      = {{ $progress->started_at ? max(0, now()->timestamp - $progress->started_at->timestamp) : 0 }};
const SW = { confirmButtonColor: '#0b5f68' };   // pop-up look

/* ══════════════════════════════════════════
   TIMER
══════════════════════════════════════════ */
let timerSecs = EST_MINS * 60, timerElapsed = Math.min(ELAPSED_SECS, timerSecs), timerDone = false, timerInterval = null;
let assessReady = false;

function markCompletedUI() {
    timerDone = true;
    const chip = document.getElementById('timer-chip');
    const disp = document.getElementById('timer-display');
    const icon = document.getElementById('timer-icon');
    if (chip) { chip.classList.remove('active','warning'); chip.classList.add('done'); }
    if (icon) icon.className = 'bx bx-badge-check';
    if (disp) disp.textContent = 'Completed';
    showAssessBtn();
    // Change "Take Quiz" to "Review Quiz"
    const btn = document.getElementById('assess-btn');
    if (btn) { btn.querySelector('span') && (btn.querySelector('span').textContent = 'Review Quiz'); }
}
function startTimer() {
    if (CTYPE === 'video') return;
    if (IS_COMPLETED) { markCompletedUI(); return; } // already done — skip timer
    const chip = document.getElementById('timer-chip');
    const disp = document.getElementById('timer-display');
    if (!chip) return;
    chip.classList.add('active');
    timerInterval = setInterval(() => {
        timerElapsed++;
        const rem = Math.max(0, timerSecs - timerElapsed);
        if (disp) disp.textContent = ft(rem);
        const pct = Math.min((timerElapsed / timerSecs) * 100, 100);
        const pb = document.getElementById('top-progress');
        if (pb) pb.style.width = pct + '%';
        if (rem / timerSecs <= 0.25) { chip.classList.remove('active'); chip.classList.add('warning'); }
        if (rem <= 0) { clearInterval(timerInterval); onTimerDone(); }
    }, 1000);
}
function ft(s) { return String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0'); }
function onTimerDone() {
    timerDone = true;
    const chip = document.getElementById('timer-chip');
    const disp = document.getElementById('timer-display');
    const icon = document.getElementById('timer-icon');
    if (chip) { chip.classList.remove('active','warning'); chip.classList.add('done'); }
    if (disp) disp.textContent = 'Done!';
    if (icon) icon.className = 'bx bx-check';
    // catalog WITH a PDF → wait for pdfReached; catalog without URL OR text → treat like text
    if (CTYPE === 'catalog' && CURL) { checkCatalogReady(); }
    else { showAssessBtn(); showReadComplete(); if (!IS_COMPLETED) setTimeout(() => openQuiz(), 1200); }
}
function showAssessBtn() {
    assessReady = true;
    const btn = document.getElementById('assess-btn');
    if (btn) { btn.classList.add('ready'); btn.removeAttribute('title'); }
}
function showReadComplete() {
    const bar = document.getElementById('read-complete');
    if (bar) { bar.style.display = 'block'; setTimeout(() => bar.scrollIntoView({ behavior:'smooth', block:'nearest' }), 100); }
}

/* ══════════════════════════════════════════
   NOTES
══════════════════════════════════════════ */
let notesSaveTO = null, notesOpen = false;

function initNotes() {
    const ta = document.getElementById('notes-ta');
    if (ta) {
        ta.value = localStorage.getItem(NOTES_KEY) || '';
        ta.addEventListener('input', () => {
            const st = document.getElementById('notes-status');
            if (st) st.innerHTML = '<i class="bx bx-cloud me-1"></i>Saving...';
            clearTimeout(notesSaveTO);
            notesSaveTO = setTimeout(() => {
                localStorage.setItem(NOTES_KEY, ta.value);
                if (st) st.innerHTML = '<i class="bx bx-check-circle me-1" style="color:#16a34a;"></i>Saved';
            }, 1200);
        });
    }
}
// The quiz and notes open outside the PDF viewer: leave its fullscreen first.
function leaveFullscreen() {
    if (document.fullscreenElement || document.webkitFullscreenElement) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
}
function placeNotes() {
    const p = document.getElementById('notes-panel'), top = document.getElementById('rdTop');
    if (p && top) p.style.top = Math.max(0, top.getBoundingClientRect().bottom) + 'px';
}
function toggleNotes() {
    notesOpen = !notesOpen;
    if (notesOpen) leaveFullscreen();
    placeNotes();
    const p = document.getElementById('notes-panel');
    const btn = document.getElementById('notes-toggle');
    if (p)   p.classList.toggle('open', notesOpen);
    if (btn) btn.classList.toggle('active', notesOpen);
}
function closeNotes() { notesOpen = false; const p = document.getElementById('notes-panel'); if (p) p.classList.remove('open'); const btn = document.getElementById('notes-toggle'); if (btn) btn.classList.remove('active'); }
function copyNotes()  { const ta = document.getElementById('notes-ta'); if (!ta?.value.trim()) return Swal.fire({toast:true,position:'top-end',icon:'info',title:'No notes yet',showConfirmButton:false,timer:2000}); navigator.clipboard.writeText(ta.value).then(() => Swal.fire({toast:true,position:'top-end',icon:'success',title:'Copied!',showConfirmButton:false,timer:1800})); }
function clearNotes() { Swal.fire({title:'Clear notes?',text:'Cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Clear',confirmButtonColor:'#ef4444'}).then(r => { if (r.isConfirmed) { const ta = document.getElementById('notes-ta'); if (ta) { ta.value=''; localStorage.setItem(NOTES_KEY,''); } } }); }

/* ══════════════════════════════════════════
   COURSE NAV SIDEBAR
══════════════════════════════════════════ */
let cnavOpen = false;
function toggleCnavPhase(bodyId, chevId) {
    const body = document.getElementById(bodyId);
    const chev = document.getElementById(chevId);
    if (!body) return;
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    if (chev) chev.classList.toggle('open', !isOpen);
}
function openCourseNav() {
    cnavOpen = true;
    const nav = document.getElementById('course-nav');
    if (nav) nav.classList.add('open');
}
function closeCourseNav() {
    cnavOpen = false;
    const nav = document.getElementById('course-nav');
    if (nav) nav.classList.remove('open');
}
function toggleCourseNav() { cnavOpen ? closeCourseNav() : openCourseNav(); }

/* ══════════════════════════════════════════
   BOOK READER — Realistic Page Flip
══════════════════════════════════════════ */
let pdfPage = 1, pdfTotal = 0, pdfReached = false, pdfFlipBook = null;
let bookZoom = 1.0, BOOK_W = 0, BOOK_H = 0;
const CHECKPOINT_PAGES = new Set();

async function initPdf() {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const loadingText = document.getElementById('book-loading-text');
    const loadingFill = document.getElementById('book-loading-fill');
    const loadingEl   = document.getElementById('book-loading');

    function setProgress(pct) {
        if (loadingFill) loadingFill.style.width = Math.min(pct, 100) + '%';
        if (loadingText) loadingText.textContent = 'Loading catalog… ' + Math.round(pct) + '%';
    }

    const timeoutId = setTimeout(() => {
        if (loadingText) loadingText.textContent = 'Connection is slow — please wait or refresh.';
    }, 60000);

    try {
        // ── 1. Load PDF document ─────────────────────────
        setProgress(2);
        const loadTask = pdfjsLib.getDocument({
            url: CURL,
            withCredentials: false,
            cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
            cMapPacked: true,
        });
        loadTask.onProgress = d => { if (d.total) setProgress(d.loaded / d.total * 35); };
        const doc = await loadTask.promise;
        pdfTotal = doc.numPages;
        for (let p = 3; p < pdfTotal; p += 3) CHECKPOINT_PAGES.add(p);
        setProgress(35);
        doc.getDownloadInfo().then(info => {
            const el = document.getElementById('book-size');
            if (el && info && info.length) el.textContent = '  |  ' + (info.length >= 1048576 ? (info.length / 1048576).toFixed(1) + ' MB' : Math.round(info.length / 1024) + ' KB');
        }).catch(() => {});

        // ── 2. Compute render scale ──────────────────────
        // The stage is still hidden here, so measure the viewer body it will fill.
        const stage    = document.getElementById('book-stage');
        const box      = stage.parentElement;
        const stageW   = Math.max((stage.clientWidth  || box.clientWidth  || window.innerWidth)  - 48, 300);
        const stageH   = Math.max((stage.clientHeight || box.clientHeight || window.innerHeight) - 20, 300);
        const portrait = (box.clientWidth || window.innerWidth) < 700;
        const pg1      = await doc.getPage(1);
        const nv       = pg1.getViewport({scale: 1});
        // Each page takes half the spread width on desktop, full width on mobile
        const availW   = portrait ? stageW : (stageW - 10) / 2;
        const rScale   = Math.min(availW / nv.width, stageH / nv.height, 1.8); // cap at 1.8×
        const PW = Math.round(nv.width  * rScale);
        const PH = Math.round(nv.height * rScale);
        // PW x PH is the on-screen (CSS pixel) size. Pages used to be drawn at
        // exactly that size, so on any scaled display (125-200%, most laptops
        // and phones) the browser stretched them and the text blurred - and the
        // zoom buttons / fullscreen stretched them further. Draw them sharper:
        // the screen's pixel density with headroom for zoom, 2x-3x, capped at
        // ~6 megapixels a page so long catalogues stay light enough to load.
        const DPR   = window.devicePixelRatio || 1;
        let   SHARP = Math.min(3, Math.max(2, DPR * 1.5));
        SHARP       = Math.min(SHARP, Math.sqrt(6e6 / (PW * PH)));
        const RW = Math.round(PW * SHARP), RH = Math.round(PH * SHARP);
        const canvasToUrl = (cv, q) => new Promise(res => cv.toBlob(b => res(URL.createObjectURL(b)), 'image/jpeg', q));

        // ── 3. Render all pages → JPEG ──────────────────
        // For proper book layout: total images must be EVEN (cover + inner pairs + back cover)
        // If pdfTotal is odd, insert a blank page before the back cover
        const needBlank = (pdfTotal > 2) && (pdfTotal % 2 === 1);
        const images = [];

        for (let i = 1; i <= pdfTotal; i++) {
            // Insert blank before last page if needed (makes last page a proper back cover)
            if (needBlank && i === pdfTotal) {
                const bc = document.createElement('canvas');
                bc.width = PW; bc.height = PH;
                const bx = bc.getContext('2d');
                bx.fillStyle = '#f5f0e8';
                bx.fillRect(0, 0, PW, PH);
                images.push(await canvasToUrl(bc, 0.5));
                bc.width = 0;
            }
            const page = await doc.getPage(i);
            const vp   = page.getViewport({scale: rScale * SHARP});
            const cv   = document.createElement('canvas');
            cv.width = RW; cv.height = RH;
            const cx = cv.getContext('2d');
            cx.fillStyle = '#ffffff';            // JPEG has no transparency: white, not black, behind the page
            cx.fillRect(0, 0, RW, RH);
            await page.render({canvasContext: cx, viewport: vp}).promise;
            images.push(await canvasToUrl(cv, 0.92));
            cv.width = 0; cv.height = 0;
            setProgress(35 + (i / pdfTotal) * 63);
        }
        clearTimeout(timeoutId);
        setProgress(100);

        // ── 4. Show stage + init PageFlip ───────────────
        await new Promise(r => setTimeout(r, 80)); // let progress bar animate to 100%
        loadingEl.style.display = 'none';
        stage.style.display     = 'flex';
        const ctrlEl = document.getElementById('book-controls');
        if (ctrlEl) ctrlEl.style.display = 'flex';
        const toolsEl = document.getElementById('book-tools');
        if (toolsEl) toolsEl.style.display = 'flex';

        BOOK_W = portrait ? PW : PW * 2; BOOK_H = PH;
        const bookBox = document.getElementById('book-container');
        bookBox.style.width = BOOK_W + 'px'; bookBox.style.height = BOOK_H + 'px';
        applyBookZoom();
        initBookPan();

        const flipBook = new St.PageFlip(bookBox, {
            width:               PW,
            height:              PH,
            size:                'fixed',
            drawShadow:          true,
            flippingTime:        850,
            usePortrait:         portrait,
            showCover:           true,    // page 0 = front cover alone, last = back cover alone
            mobileScrollSupport: false,
            swipeDistance:       25,
            clickEventForward:   true,
            startZIndex:         0,
            maxShadowOpacity:    0.5,
        });
        // Pages as real <img> elements (HTML mode). loadFromImages() paints them
        // onto a canvas the size of the book in CSS pixels, with no allowance for
        // the screen's pixel density - blurry on any scaled display however
        // sharp the images are. An <img> is drawn by the browser at full detail,
        // zoomed or not.
        const pageEls = images.map(src => {
            const el = document.createElement('div');
            el.className = 'book-page';
            const img = document.createElement('img');
            img.src = src; img.alt = ''; img.draggable = false;
            el.appendChild(img);
            return el;
        });
        flipBook.loadFromHTML(pageEls);

        // ── 5. Events ────────────────────────────────────
        function syncControls(idx) {
            pdfPage = Math.min(idx + 1, pdfTotal);
            document.getElementById('book-page-info').textContent = 'Page ' + pdfPage + ' of ' + pdfTotal;
            const sub = document.getElementById('book-page-sub');
            if (sub) sub.textContent = 'Page ' + pdfPage + ' of ' + pdfTotal;
            const pb = document.getElementById('top-progress');
            if (pb) pb.style.width = (pdfPage / pdfTotal * 100) + '%';
            document.getElementById('book-prev').disabled = idx <= 0;
            document.getElementById('book-next').disabled = idx >= images.length - 1;
            if (pdfPage >= pdfTotal) { pdfReached = true; checkCatalogReady(); }
            if (CHECKPOINT_PAGES.has(pdfPage)) maybeShowCheckpoint(pdfPage);
        }

        flipBook.on('flip',       e => syncControls(e.data));
        flipBook.on('changeState',e => { /* handle mid-flip states */ });

        document.getElementById('book-prev').onclick = () => flipBook.flipPrev();
        document.getElementById('book-next').onclick = () => flipBook.flipNext();
        syncControls(0);

        // Zoom buttons
        document.getElementById('book-zoom-out').onclick = () => adjustBookZoom(-0.15);
        document.getElementById('book-zoom-in').onclick  = () => adjustBookZoom(+0.15);

        pdfFlipBook = flipBook;

    } catch(err) {
        clearTimeout(timeoutId);
        if (loadingText) {
            loadingText.textContent = 'Failed to load catalog.';
            loadingText.style.color = '#fca5a5';
        }
        if (loadingFill) loadingFill.style.background = '#ef4444';
        console.error('Book init error:', err);
    }
}

/* ── Zoom ─────────────────────────────────── */
function adjustBookZoom(delta) {
    const stage = document.getElementById('book-stage');
    // Keep whatever is in the middle of the view in the middle after zooming.
    const cx = stage && stage.scrollWidth  > stage.clientWidth  ? (stage.scrollLeft + stage.clientWidth  / 2) / stage.scrollWidth  : 0.5;
    const cy = stage && stage.scrollHeight > stage.clientHeight ? (stage.scrollTop  + stage.clientHeight / 2) / stage.scrollHeight : 0.5;
    bookZoom = Math.min(Math.max(bookZoom + delta, 0.4), 2.5);
    applyBookZoom();
    if (stage) { stage.scrollLeft = cx * stage.scrollWidth - stage.clientWidth / 2; stage.scrollTop = cy * stage.scrollHeight - stage.clientHeight / 2; }
}
function applyBookZoom() {
    const book = document.getElementById('book-container'), zoomer = document.getElementById('book-zoomer'), stage = document.getElementById('book-stage');
    if (book) book.style.transform = `scale(${bookZoom})`;
    if (zoomer && BOOK_W) { zoomer.style.width = Math.round(BOOK_W * bookZoom) + 'px'; zoomer.style.height = Math.round(BOOK_H * bookZoom) + 'px'; }
    if (stage) stage.classList.toggle('is-zoomed', bookZoom > 1.001);
    const lbl = document.getElementById('book-zoom-label');
    if (lbl) lbl.textContent = Math.round(bookZoom * 100) + '%';
}
// Zoomed in, a drag moves around the page (mouse, pen or finger).
function initBookPan() {
    const pan = document.getElementById('book-pan'), stage = document.getElementById('book-stage');
    if (!pan || !stage || pan.dataset.ready) return;
    pan.dataset.ready = '1';
    let drag = null;
    pan.addEventListener('pointerdown', e => {
        drag = { x: e.clientX, y: e.clientY, l: stage.scrollLeft, t: stage.scrollTop };
        pan.setPointerCapture(e.pointerId); pan.classList.add('grabbing'); e.preventDefault();
    });
    pan.addEventListener('pointermove', e => {
        if (!drag) return;
        stage.scrollLeft = drag.l - (e.clientX - drag.x);
        stage.scrollTop  = drag.t - (e.clientY - drag.y);
    });
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(ev => pan.addEventListener(ev, () => { drag = null; pan.classList.remove('grabbing'); }));
    pan.addEventListener('dblclick', () => { bookZoom = 1.0; applyBookZoom(); });
}

/* ── Fullscreen ───────────────────────────── */
function toggleFullscreen() {
    const el = document.getElementById('book-reader');
    if (!el) return;
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        (el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen).call(el);
    } else {
        (document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen).call(document);
    }
}
['fullscreenchange','webkitfullscreenchange'].forEach(ev => {
    document.addEventListener(ev, () => {
        const book = document.getElementById('book-reader');
        if (!book) return;
        const icon = document.getElementById('fs-btn-icon');
        const isFs = (document.fullscreenElement || document.webkitFullscreenElement) === book;
        if (icon) icon.className = isFs ? 'bx bx-exit-fullscreen' : 'bx bx-fullscreen';
        // Auto-boost zoom when entering fullscreen, restore when exiting
        if (isFs)  adjustBookZoom( 0.35);
        else       { bookZoom = 1.0; adjustBookZoom(0); }
    });
});

// Reaching the last page no longer ends the reading time: jumping straight to
// it skipped the whole catalogue. The test opens when the timer runs out (the
// server enforces the same rule).
function checkCatalogReady() {
    if (IS_COMPLETED || timerDone) showAssessBtn();
}

/* ── Checkpoint Popup ── */
const cpPool = [
    { title: '💡 Key Insight',   body: "Take a moment — what's the most important idea on the pages you just read? Make sure you understand it before moving on." },
    { title: '🔍 Stay Sharp',    body: "You're making great progress! Think about how the content on this page connects to what you already know." },
    { title: '📌 Remember This', body: "Important details are often tested. Re-read any highlighted points before continuing." },
    { title: '⚡ Quick Think',   body: "Can you summarize the last 2-3 pages in one sentence? If yes, you're ready to continue!" },
    { title: '🎯 Focus Check',   body: "Before you move on — are there any terms or concepts on these pages that are unclear to you?" },
];
let cpShownPages = new Set();

function maybeShowCheckpoint(n) {
    if (cpShownPages.has(n)) return;
    cpShownPages.add(n);
    const cp = cpPool[Math.floor(Math.random() * cpPool.length)];
    document.getElementById('cp-title').textContent = cp.title;
    document.getElementById('cp-body').textContent  = cp.body;
    document.getElementById('cp-opts').innerHTML    = '';
    document.getElementById('cp-continue').style.display = 'block';
    document.getElementById('pdf-checkpoint').classList.add('open');
}
function closeCheckpoint() {
    document.getElementById('pdf-checkpoint').classList.remove('open');
}

/* ══════════════════════════════════════════
   VIDEO
══════════════════════════════════════════ */
let ytP = null, ytInt = null, milestoneSet = new Set(), curMilestone = null, selMsIdx = null;

function fvt(s) { return String(Math.floor(s/60)).padStart(2,'0') + ':' + String(Math.floor(s%60)).padStart(2,'0'); }
function pauseV() { if (ytP) ytP.pauseVideo(); else { const v=document.getElementById('local-video'); if(v) v.pause(); } }
function playV()  { if (ytP) ytP.playVideo();  else { const v=document.getElementById('local-video'); if(v) v.play(); } }
function seekV(s) { if (ytP) { ytP.seekTo(s,true); ytP.playVideo(); } else { const v=document.getElementById('local-video'); if(v) { v.currentTime=s; v.play(); } } }

function initChapters() {
    const list = document.getElementById('chap-list'); if (!list) return;
    if (!VID_CHAPTERS?.length) { list.innerHTML = '<span style="color:#94a3b8;font-size:0.78rem;">No chapters.</span>'; return; }
    VID_CHAPTERS.forEach(ch => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'chap-chip';
        const ic = document.createElement('i'); ic.className = 'bx bx-skip-next';
        const tm = document.createElement('span'); tm.style.cssText = 'font-family:monospace;opacity:0.6;'; tm.textContent = fvt(ch.time);
        btn.append(ic, document.createTextNode(ch.title + ' '), tm);
        btn.onclick = () => {
            document.querySelectorAll('.chap-chip').forEach(b => b.classList.remove('active'));
            btn.classList.add('active'); seekV(ch.time);
        };
        list.appendChild(btn);
    });
}
function checkMilestones(t) {
    if (!VID_MILES?.length) return;
    VID_MILES.forEach(m => { if (t >= m.time && !milestoneSet.has(m.time)) { milestoneSet.add(m.time); triggerMilestone(m); } });
}
function triggerMilestone(m) {
    pauseV(); curMilestone = m; selMsIdx = null;
    document.getElementById('ms-q').textContent = m.question;
    const opts = document.getElementById('ms-opts'); opts.innerHTML = '';
    const sub = document.getElementById('ms-submit'); sub.disabled = true;
    sub.textContent = 'Submit Answer'; sub.style.background = '#d97706';
    m.options.forEach((opt, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ms-opt'; btn.textContent = opt;
        btn.onclick = () => { document.querySelectorAll('.ms-opt').forEach(b=>b.classList.remove('sel')); btn.classList.add('sel'); selMsIdx = i; sub.disabled = false; };
        opts.appendChild(btn);
    });
    document.getElementById('milestone-overlay').style.display = 'flex';
}

function startYT() { if (ytInt) clearInterval(ytInt); ytInt = setInterval(() => { if (!ytP) return; const ct = ytP.getCurrentTime(), dur = ytP.getDuration(); const pb = document.getElementById('top-progress'); if (pb && dur) pb.style.width = (ct/dur*100)+'%'; checkMilestones(ct); }, 800); }
function stopYT() { clearInterval(ytInt); }

// YouTube plays through the protected player: its title strip, copy-link
// and "Watch on YouTube" are cropped out of view, a shield keeps every click
// (and right-click) inside HRX, and the controls are ours.
function initYouTube() {
    const el = document.getElementById('yt-player');
    if (!el || !YT_ID || !window.HrxSecureYT) return;
    HrxSecureYT.mount(el, YT_ID, {
        fsTarget: document.getElementById('video-player'),
        onPlayer(p) { ytP = p; },
        onStateChange(e) {
            if (e.data === YT.PlayerState.PLAYING) startYT(); else stopYT();
            if (e.data === YT.PlayerState.ENDED) { stopYT(); showAssessBtn(); const pb = document.getElementById('top-progress'); if (pb) pb.style.width = '100%'; }
        },
    });
}
function initLocalVideo() {
    const v = document.getElementById('local-video'); if (!v) return;
    // Set src from data attribute
    const src = v.getAttribute('data-src');
    if (src) v.src = src;
    v.addEventListener('timeupdate', () => {
        const to = document.getElementById('vid-time');
        if (to) { to.style.display='block'; to.textContent = fvt(v.currentTime)+' / '+fvt(v.duration||0); }
        const pb = document.getElementById('top-progress'); if (pb && v.duration) pb.style.width = (v.currentTime/v.duration*100)+'%';
        checkMilestones(v.currentTime);
    });
    v.onended = () => { showAssessBtn(); const pb=document.getElementById('top-progress'); if(pb) pb.style.width='100%'; };
}

/* ══════════════════════════════════════════
   QUIZ / ASSESSMENT
══════════════════════════════════════════ */
let qList=[], qIdx=0, qAnswers={}, qFlags={}, showAll=false, passPct=80;
let quizClock = null, quizSecs = 0;

function startQuizClock() {
    stopQuizClock(); quizSecs = 0;
    const el = document.getElementById('qz-clock'); if (el) el.textContent = '00:00';
    quizClock = setInterval(() => { quizSecs++; if (el) el.textContent = ft(quizSecs); }, 1000);
}
function stopQuizClock() { clearInterval(quizClock); }

function openQuiz() {
    if (CTYPE !== 'video' && !timerDone && !IS_COMPLETED) {
        Swal.fire({title:'Not yet!',text:`Complete the minimum read time (${EST_MINS} min) first.`,icon:'info',...SW});
        return;
    }
    if (CTYPE === 'video' && !assessReady && !IS_COMPLETED) {
        Swal.fire({title:'Not yet!',text:'Please watch the video to the end first.',icon:'info',...SW});
        return;
    }
    closeNotes();
    leaveFullscreen();
    const ov = document.getElementById('quiz-overlay');
    const top = document.getElementById('rdTop');
    if (top) ov.style.top = Math.max(0, top.getBoundingClientRect().bottom + 3) + 'px';
    ov.style.display = 'block'; ov.scrollTop = 0;
    loadQuiz();
}
function closeQuiz() {
    stopQuizClock();
    document.getElementById('quiz-overlay').style.display = 'none';
}
async function loadQuiz() {
    const sub = document.getElementById('quiz-sub'); if (sub) sub.textContent = 'Loading...';
    try {
        const r = await fetch(ASSESS_URL, {headers: {'Accept': 'application/json'}});
        if (r.status === 401 || r.status === 419) {
            closeQuiz();
            Swal.fire({title:'Your session has expired',text:'Please reload the page (sign in again if asked) and open the quiz again.',icon:'warning',confirmButtonText:'Reload page',...SW})
                .then(x => { if (x.isConfirmed) location.reload(); });
            return;
        }
        const d = await r.json();
        if (!d.success && d.message) {
            Swal.fire({title:'Not yet!',text:d.message,icon:'info',...SW}); closeQuiz(); return;
        }
        // No quiz for this module: watching/reading it is what completes it.
        if (d.no_quiz) {
            closeQuiz();
            const r2 = await fetch(SUBMIT_URL, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:JSON.stringify({answers:{}})});
            const d2 = await r2.json();
            if (d2.success) {
                Swal.fire({title:'Completed!',text:'This module is marked as complete.',icon:'success',confirmButtonColor:'#16a34a'})
                    .then(() => location.reload());
            } else {
                Swal.fire({title:'Not yet!',text:d2.message || 'Could not mark it complete.',icon:'info',...SW});
            }
            return;
        }
        if (!d.success || !d.questions.length) {
            Swal.fire({title:'No questions',text:'No assessment questions found.',icon:'info',...SW}); closeQuiz(); return;
        }
        qList = d.questions; showAll = d.show_all_at_once; passPct = d.passing_percentage;
        qIdx = 0; qAnswers = {}; qFlags = {};
        if (sub) sub.textContent = `Pass: ${passPct}% · ${qList.length} questions`;
        ['qz-meta-count','qz-info-count'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = qList.length; });
        ['qz-meta-pass','qz-info-pass'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = passPct; });
        buildDots();
        document.getElementById('quiz-result').style.display = 'none';
        document.getElementById('quiz-card').style.display = 'block';
        document.getElementById('q-flag').style.display = showAll ? 'none' : '';
        if (showAll) { document.getElementById('quiz-single-body').style.display = 'none'; document.getElementById('quiz-all-body').style.display = 'block'; renderAll(); }
        else { document.getElementById('quiz-all-body').style.display = 'none'; document.getElementById('quiz-single-body').style.display = 'block'; renderQ(); }
        startQuizClock();
    } catch(e) { Swal.fire({title:'Error',text:'Could not load quiz.',icon:'error',...SW}); closeQuiz(); }
}
// Question navigation: numbered circles (current / answered / review later).
function buildDots() {
    const nav = document.getElementById('qz-nav'); if (!nav) return; nav.innerHTML = '';
    qList.forEach((_, i) => {
        const b = document.createElement('button');
        b.type = 'button'; b.id = 'qd-' + i; b.textContent = i + 1;
        b.onclick = () => {
            if (showAll) { document.getElementById('qall-' + i)?.scrollIntoView({behavior:'smooth', block:'center'}); return; }
            qIdx = i; renderQ();
        };
        nav.appendChild(b);
    });
    updateDots();
}
function updateDots() {
    qList.forEach((q, i) => {
        const d = document.getElementById('qd-' + i); if (!d) return;
        d.className = (qAnswers[q.id] !== undefined ? 'ans' : '') + (qFlags[q.id] ? ' flag' : '') + (!showAll && i === qIdx ? ' cur' : '');
    });
    const answered = Object.keys(qAnswers).length, total = qList.length || 1;
    const pct = Math.round(answered / total * 100);
    const fill = document.getElementById('qz-fill'); if (fill) fill.style.width = pct + '%';
    const lbl = document.getElementById('qz-pct'); if (lbl) lbl.textContent = pct + '% Complete';
    if (showAll) { const qn = document.getElementById('q-num'); if (qn) qn.textContent = answered + ' of ' + qList.length + ' answered'; }
}
function optionEl(q, opt, i, onPick) {
    const d = document.createElement('div');
    d.className = 'quiz-opt' + (qAnswers[q.id] === i ? ' sel' : '');
    d.setAttribute('role', 'radio');
    d.setAttribute('aria-checked', qAnswers[q.id] === i ? 'true' : 'false');
    d.tabIndex = 0;
    const radio = document.createElement('span'); radio.className = 'opt-radio';
    const text = document.createElement('span'); text.className = 'opt-text'; text.textContent = opt;
    d.append(radio, text);
    d.onclick = onPick;
    d.onkeydown = e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onPick(); } };
    return d;
}
function renderQ() {
    const q = qList[qIdx];
    const qnum = document.getElementById('q-num'); if (qnum) qnum.textContent = `Question ${qIdx+1} of ${qList.length}`;
    const qno = document.getElementById('qz-qno'); if (qno) qno.textContent = 'Q' + (qIdx + 1);
    const qt = document.getElementById('q-text'); if (qt) qt.textContent = q.question;
    const opts = document.getElementById('q-opts'); if (!opts) return; opts.innerHTML = '';
    q.options.forEach((opt, i) => opts.appendChild(optionEl(q, opt, i, () => { qAnswers[q.id] = i; renderQ(); })));
    const prev = document.getElementById('q-prev'), next = document.getElementById('q-next'), sub = document.getElementById('q-submit');
    prev.style.visibility = qIdx === 0 ? 'hidden' : 'visible';
    const last = qIdx === qList.length - 1;
    next.style.display = last ? 'none' : 'inline-flex';
    sub.style.display  = last ? 'inline-flex' : 'none';
    const flag = document.getElementById('q-flag');
    if (flag) { flag.classList.toggle('flagged', !!qFlags[q.id]); flag.querySelector('span').textContent = qFlags[q.id] ? 'Marked for review' : 'Review later'; }
    updateDots();
}
function renderAll() {
    const body = document.getElementById('q-all-content'); if (!body) return; body.innerHTML = '';
    qList.forEach((q, qi) => {
        const block = document.createElement('div'); block.className = 'qall-q'; block.id = 'qall-' + qi;
        const head = document.createElement('div'); head.className = 'qall-q-text';
        const num = document.createElement('span'); num.className = 'qall-q-num'; num.textContent = qi + 1;
        const txt = document.createElement('span'); txt.textContent = q.question + ' ';
        const pts = document.createElement('small'); pts.style.cssText = 'color:#94a3b8;font-weight:400;'; pts.textContent = `(${q.marks} pt${q.marks > 1 ? 's' : ''})`;
        txt.appendChild(pts); head.append(num, txt); block.appendChild(head);
        q.options.forEach((opt, i) => block.appendChild(optionEl(q, opt, i, () => { qAnswers[q.id] = i; renderAll(); updateAllCount(); })));
        body.appendChild(block);
    });
    updateAllCount();
}
function updateAllCount() {
    const el = document.getElementById('q-all-count');
    if (el) el.textContent = Object.keys(qAnswers).length + '/' + qList.length + ' answered';
    updateDots();
}
async function submitQuiz() {
    if (Object.keys(qAnswers).length < qList.length) {
        // Take them to the first unanswered question, and say so.
        const miss = qList.findIndex(q => qAnswers[q.id] === undefined);
        if (miss >= 0) {
            if (showAll) document.getElementById('qall-' + miss)?.scrollIntoView({behavior:'smooth', block:'center'});
            else { qIdx = miss; renderQ(); }
        }
        Swal.fire({icon:'warning',title:'One more to go',text:(miss >= 0 ? `Question ${miss + 1} has no answer yet.` : 'Some questions have no answer yet.') + ' Choose an answer, then press Submit Quiz.',...SW}); return;
    }
    Swal.fire({title:'Submitting...',didOpen:()=>Swal.showLoading(),allowOutsideClick:false});
    let r, d = null;
    try {
        r = await fetch(SUBMIT_URL, {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({answers:qAnswers})});
        try { d = await r.json(); } catch (e) { d = null; }
    } catch { Swal.close(); Swal.fire({title:'Network error',text:'Please check your connection and press Submit again.',icon:'error',...SW}); return; }
    Swal.close();
    if (d && d.success) { showResult(d); return; }
    // Signed out (the session ran out while the quiz was open): the answers
    // cannot be accepted, and the questions served with it are gone too.
    if (r.status === 419 || r.status === 401 || !d) {
        Swal.fire({title:'Your session has expired',text:'Please reload the page (sign in again if asked) and take the quiz again - a fresh set of questions will be drawn.',icon:'warning',confirmButtonText:'Reload page',...SW})
            .then(x => { if (x.isConfirmed) location.reload(); });
        return;
    }
    // The server no longer holds this set (opened again elsewhere, or already
    // submitted): offer a fresh one rather than leave the button dead.
    if (r.status === 409 || r.status === 422) {
        Swal.fire({title:'Please start the test again',text:d.message,icon:'warning',showCancelButton:true,confirmButtonText:'Load a fresh set',cancelButtonText:'Close',...SW})
            .then(x => { if (x.isConfirmed) { qAnswers = {}; qFlags = {}; qIdx = 0; loadQuiz(); } });
        return;
    }
    Swal.fire({title: d.message ? 'Not yet!' : 'Error',text: d.message || 'Submission failed.',icon: d.message ? 'info' : 'error',...SW});
}
function showResult(d) {
    stopQuizClock();
    document.querySelectorAll('.qz-attempt-no').forEach(el => { el.textContent = (parseInt(el.textContent, 10) || 1) + 1; });
    document.getElementById('quiz-card').style.display = 'none';
    const res = document.getElementById('quiz-result'); res.style.display = 'block';
    const ov = document.getElementById('quiz-overlay'); if (ov) ov.scrollTop = 0;
    const sc = Math.round(d.score), col = d.passed ? '#16a34a' : '#dc2626';
    const c = 2 * Math.PI * 46;
    document.getElementById('result-ring').innerHTML = `
        <svg width="120" height="120" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="46" fill="none" stroke="#e8eef2" stroke-width="8"/>
            <circle cx="60" cy="60" r="46" fill="none" stroke="${col}" stroke-width="8"
                stroke-dasharray="${c.toFixed(2)}" stroke-dashoffset="${(c*(1-sc/100)).toFixed(2)}"
                stroke-linecap="round" transform="rotate(-90,60,60)"
                style="transition:stroke-dashoffset 1.2s ease;"/>
        </svg>
        <div class="result-ring-text">
            <span class="result-pct" style="color:${col};">${sc}%</span>
            <span class="result-label">${d.earned_marks}/${d.total_marks}pts</span>
        </div>`;
    const gb = document.getElementById('result-grade-badge');
    if (gb) gb.innerHTML = d.passed
        ? '<span style="display:inline-block;background:#dcfce7;color:#15803d;font-size:0.72rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:5px 14px;border-radius:20px;">🏆 PASSED</span>'
        : '<span style="display:inline-block;background:#fee2e2;color:#b91c1c;font-size:0.72rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:5px 14px;border-radius:20px;">✗ NOT PASSED</span>';
    document.getElementById('result-title').textContent = d.passed ? 'Excellent Work!' : 'Keep Going!';
    document.getElementById('result-msg').innerHTML = d.passed
        ? `You scored <strong style="color:#16a34a;">${sc}%</strong> — well past the ${d.passing_percentage}% pass mark. Module complete! 🚀`
        : `You scored <strong style="color:#dc2626;">${sc}%</strong>. You need <strong>${d.passing_percentage}%</strong> to pass. Review the material and try again.`;
    const retry = document.getElementById('result-retry');
    if (retry) retry.style.display = d.passed ? 'none' : 'inline-flex';
    const nextBtn   = document.getElementById('result-next-btn');
    const portalBtn = document.getElementById('result-portal-btn');
    if (d.passed) {
        confetti({particleCount:180,spread:90,origin:{y:0.55},colors:['#16a34a','#0b7c86','#f59e0b']});
        if (NEXT_MODULE_URL && nextBtn) {
            nextBtn.href = NEXT_MODULE_URL;
            nextBtn.innerHTML = '<i class="bx bx-right-arrow-alt" style="font-size:1.1rem;"></i> ';
            nextBtn.appendChild(document.createTextNode(NEXT_MODULE_TITLE || 'Next Module'));
            nextBtn.style.display = 'inline-flex';
            if (portalBtn) portalBtn.classList.remove('primary'); // demote portal btn
            // Auto-navigate after 4 seconds
            let countdown = 4;
            const countEl = document.createElement('div');
            countEl.style.cssText = 'font-size:0.78rem;color:#64748b;margin-top:10px;';
            countEl.textContent = `Auto-opening next module in ${countdown}s…`;
            nextBtn.parentElement.after(countEl);
            const autoNav = setInterval(() => {
                countdown--;
                countEl.textContent = countdown > 0 ? `Auto-opening next module in ${countdown}s…` : 'Opening…';
                if (countdown <= 0) { clearInterval(autoNav); window.location.href = NEXT_MODULE_URL; }
            }, 1000);
            // Cancel auto-nav if user clicks portal btn
            if (portalBtn) portalBtn.addEventListener('click', () => clearInterval(autoNav));
        }
    }
}
// A retake asks the server for a fresh set: each served set can be submitted
// once, and the server applies the retry limit here.
function retakeQuiz() { qAnswers={}; qIdx=0; document.getElementById('quiz-result').style.display='none'; loadQuiz(); }

/* ══════════════════════════════════════════
   BOOT
══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {

    // Course nav: sync JS state with CSS (auto-open on desktop)
    const cn = document.getElementById('course-nav');
    if (cn && window.innerWidth >= 992) { cn.classList.add('open'); cnavOpen = true; }

    // Notes
    initNotes();
    document.getElementById('notes-toggle')?.addEventListener('click', toggleNotes);
    window.addEventListener('resize', placeNotes);

    // Mobile course nav toggles
    document.getElementById('cnav-toggle-bottom')?.addEventListener('click', toggleCourseNav);

    // Assessment btn
    document.getElementById('assess-btn')?.addEventListener('click', openQuiz);

    // Quiz nav
    document.getElementById('q-prev')?.addEventListener('click', () => { qIdx--; renderQ(); });
    document.getElementById('q-next')?.addEventListener('click', () => {
        if (qAnswers[qList[qIdx].id] === undefined) { Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Select an answer',showConfirmButton:false,timer:2000}); return; }
        qIdx++; renderQ();
    });
    document.getElementById('q-flag')?.addEventListener('click', () => {
        const q = qList[qIdx]; if (!q) return;
        qFlags[q.id] = !qFlags[q.id]; renderQ();
    });
    document.getElementById('q-submit')?.addEventListener('click', submitQuiz);
    document.getElementById('q-all-submit')?.addEventListener('click', submitQuiz);

    // Milestone submit
    document.getElementById('ms-submit')?.addEventListener('click', () => {
        if (selMsIdx === curMilestone.correct) {
            confetti({particleCount:60,spread:60,origin:{y:0.7}});
            const s = document.getElementById('ms-submit');
            s.textContent = '✓ Correct!'; s.style.background = '#16a34a'; s.disabled = true;
            setTimeout(() => { document.getElementById('milestone-overlay').style.display='none'; playV(); }, 1400);
        } else {
            Swal.fire({title:'Not quite!',text:'Try again.',icon:'error',confirmButtonColor:'#ef4444'});
        }
    });

    // Keyboard navigation for book reader
    document.addEventListener('keydown', e => {
        if (CTYPE === 'catalog' && pdfFlipBook
            && document.getElementById('quiz-overlay').style.display !== 'block'
            && !document.getElementById('pdf-checkpoint')?.classList.contains('open')
            && !['TEXTAREA', 'INPUT'].includes(e.target.tagName)) {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); pdfFlipBook.flipNext(); }
            if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   { e.preventDefault(); pdfFlipBook.flipPrev(); }
            if (e.key === '+' || e.key === '=')  adjustBookZoom(+0.15);
            if (e.key === '-')                   adjustBookZoom(-0.15);
            if (e.key === 'f' || e.key === 'F')  toggleFullscreen();
        }
    });

    // Mouse wheel on the book scrolls, never zooms - rolling the wheel to read
    // zoomed the page by surprise. Ctrl+wheel still zooms (as in a browser's own
    // PDF viewer), and so do the - / + buttons.
    document.getElementById('book-stage')?.addEventListener('wheel', e => {
        if (CTYPE !== 'catalog' || !(e.ctrlKey || e.metaKey)) return;   // plain wheel: native scroll
        e.preventDefault();
        adjustBookZoom(e.deltaY > 0 ? -0.1 : 0.1);
    }, { passive: false });

    // Init by type
    if (CTYPE === 'catalog' && CURL) {
        initPdf();
        if (IS_COMPLETED) { markCompletedUI(); } else { startTimer(); }
    }
    else if (CTYPE === 'video') {
        initChapters();
        if (IS_COMPLETED) { markCompletedUI(); showAssessBtn(); }
        if (YT_ID) { initYouTube(); } else { initLocalVideo(); }
    } else {
        // text modules AND catalog-without-URL both use startTimer()
        // startTimer() already handles IS_COMPLETED → markCompletedUI()
        startTimer();
    }
});
</script>
@endsection
