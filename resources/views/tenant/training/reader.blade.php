@extends('layouts/blankLayout')

@section('title', $module->title . ' — HiTech Academy')

@section('vendor-style')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endsection

@section('page-style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #0d1117; }

/* ══════════════════════════════════════════
   LAYOUT
══════════════════════════════════════════ */
.reader-root { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

/* Top bar */
.reader-topbar {
    background: #161b22;
    border-bottom: 1px solid #30363d;
    height: 52px;
    display: flex; align-items: center;
    padding: 0 16px;
    gap: 12px;
    flex-shrink: 0;
    z-index: 200;
}
.topbar-back {
    display: flex; align-items: center; gap: 6px;
    color: #8b949e; font-size: 0.78rem; font-weight: 600;
    text-decoration: none; padding: 5px 10px; border-radius: 6px;
    border: 1px solid #30363d; transition: all 0.15s; white-space: nowrap;
    flex-shrink: 0;
}
.topbar-back:hover { color: white; border-color: #58a6ff; background: rgba(88,166,255,0.08); }
.topbar-sep { width: 1px; height: 18px; background: #30363d; flex-shrink: 0; }
.topbar-breadcrumb {
    display: flex; flex-direction: column; justify-content: center;
    min-width: 0; flex: 1;
}
.topbar-phase { font-size: 0.62rem; color: #8b949e; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; }
.topbar-title { font-size: 0.85rem; font-weight: 700; color: #e6edf3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.topbar-right { display: flex; align-items: center; gap: 10px; margin-left: auto; flex-shrink: 0; }

/* Timer */
.timer-chip {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px;
    font-size: 0.78rem; font-weight: 700;
    border: 1px solid #30363d; color: #8b949e; background: #21262d;
    transition: all 0.4s; white-space: nowrap;
}
.timer-chip.active  { border-color: #58a6ff; color: #58a6ff; background: rgba(88,166,255,0.08); }
.timer-chip.warning { border-color: #f0883e; color: #f0883e; background: rgba(240,136,62,0.08); }
.timer-chip.done    { border-color: #3fb950; color: #3fb950; background: rgba(63,185,80,0.08); }

/* Notes btn */
.topbar-icon-btn {
    width: 32px; height: 32px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    background: transparent; border: 1px solid #30363d;
    color: #8b949e; cursor: pointer; font-size: 0.9rem;
    transition: all 0.15s; flex-shrink: 0;
}
.topbar-icon-btn:hover { color: white; border-color: #58a6ff; background: rgba(88,166,255,0.08); }
.topbar-icon-btn.active { color: #58a6ff; border-color: #58a6ff; background: rgba(88,166,255,0.1); }

/* Assessment button */
.assess-btn {
    display: none; align-items: center; gap: 6px;
    background: linear-gradient(135deg, #1f883d, #2ea043);
    color: white; font-size: 0.8rem; font-weight: 700;
    padding: 7px 16px; border-radius: 20px;
    border: none; cursor: pointer;
    box-shadow: 0 0 12px rgba(63,185,80,0.3);
    transition: all 0.2s; white-space: nowrap;
    animation: glow-pulse 2s ease-in-out infinite;
}
.assess-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 18px rgba(63,185,80,0.45); }
@keyframes glow-pulse {
    0%,100% { box-shadow: 0 0 8px rgba(63,185,80,0.3); }
    50%      { box-shadow: 0 0 18px rgba(63,185,80,0.5); }
}

/* Progress bar under topbar */
.topbar-progress { height: 3px; background: #21262d; }
.topbar-progress-fill { height: 100%; background: linear-gradient(90deg, #58a6ff, #3fb950); transition: width 0.5s ease; }

/* ── Main reading area ── */
.reader-main { flex: 1; display: flex; overflow: hidden; }

/* Content pane */
.content-pane {
    flex: 1; overflow-y: auto; min-width: 0;
    background: #0d1117;
    display: flex; flex-direction: column; align-items: center;
    padding: 28px 20px 80px;
    scroll-behavior: smooth;
}
.content-pane::-webkit-scrollbar { width: 6px; }
.content-pane::-webkit-scrollbar-track { background: #161b22; }
.content-pane::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }

/* ── Notes sidebar ── */
.notes-panel {
    width: 0; overflow: hidden; opacity: 0;
    background: #161b22;
    border-left: 1px solid #30363d;
    display: flex; flex-direction: column;
    transition: width 0.25s ease, opacity 0.25s ease;
    flex-shrink: 0;
}
.notes-panel.open { width: 300px; opacity: 1; }
.notes-header {
    padding: 14px 16px; border-bottom: 1px solid #30363d;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.notes-header span { font-size: 0.82rem; font-weight: 700; color: #e6edf3; }
.notes-body { flex: 1; padding: 12px; display: flex; flex-direction: column; gap: 10px; overflow: hidden; }
#notes-ta {
    flex: 1; width: 100%; min-height: 200px;
    background: #0d1117; border: 1px solid #30363d;
    border-radius: 8px; padding: 12px;
    color: #e6edf3; font-size: 0.82rem; line-height: 1.7;
    font-family: inherit; resize: none; outline: none;
    transition: border-color 0.2s;
}
#notes-ta:focus { border-color: #58a6ff; }
#notes-ta::placeholder { color: #484f58; }
.notes-footer {
    padding: 10px 12px; border-top: 1px solid #30363d;
    display: flex; gap: 8px; flex-shrink: 0;
}
.notes-btn {
    flex: 1; padding: 7px; border-radius: 6px; border: 1px solid #30363d;
    background: #21262d; color: #8b949e;
    font-size: 0.72rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 4px;
    transition: all 0.15s;
}
.notes-btn:hover { color: white; border-color: #58a6ff; background: rgba(88,166,255,0.08); }
.notes-btn.danger:hover { border-color: #f85149; color: #f85149; background: rgba(248,81,73,0.08); }
.notes-save-status { font-size: 0.65rem; color: #484f58; padding: 2px 12px; text-align: center; }

/* ── Course nav sidebar ── */
.course-nav {
    width: 0; overflow: hidden; opacity: 0;
    background: #161b22;
    border-left: 1px solid #30363d;
    display: flex; flex-direction: column;
    transition: width 0.25s ease, opacity 0.25s ease;
    flex-shrink: 0;
}
.course-nav.open { width: 300px; opacity: 1; }
@media (max-width: 1199px) { .course-nav.open { width: 260px; } }

.course-nav-header {
    padding: 14px 16px; border-bottom: 1px solid #30363d;
    display: flex; align-items: center; justify-content: space-between;
    flex-shrink: 0;
}
.course-nav-header span { font-size: 0.82rem; font-weight: 700; color: #e6edf3; }
.course-nav-body { flex: 1; overflow-y: auto; }
.course-nav-body::-webkit-scrollbar { width: 4px; }
.course-nav-body::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }

.cnav-phase { border-bottom: 1px solid #21262d; }
.cnav-phase-header {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px; cursor: pointer;
    background: #1c2128; user-select: none;
    transition: background 0.15s;
}
.cnav-phase-header:hover { background: #21262d; }
.cnav-phase-num {
    width: 22px; height: 22px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.65rem; font-weight: 800; color: white;
    flex-shrink: 0;
}
.cnav-phase-title { flex: 1; font-size: 0.78rem; font-weight: 700; color: #c9d1d9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cnav-phase-count { font-size: 0.65rem; color: #484f58; flex-shrink: 0; }
.cnav-chevron { color: #484f58; font-size: 0.7rem; flex-shrink: 0; transition: transform 0.2s; }
.cnav-chevron.open { transform: rotate(180deg); }

.cnav-module-list { display: none; }
.cnav-module-list.open { display: block; }

.cnav-mod {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px 10px 20px;
    text-decoration: none;
    border-left: 2px solid transparent;
    transition: all 0.15s;
}
.cnav-mod:hover:not(.cnav-locked) { background: rgba(88,166,255,0.04); }
.cnav-mod.cnav-current {
    background: rgba(88,166,255,0.08);
    border-left-color: #58a6ff;
}
.cnav-mod.cnav-completed { border-left-color: #3fb950; }
.cnav-mod.cnav-locked { opacity: 0.4; pointer-events: none; cursor: not-allowed; }

.cnav-mod-icon {
    width: 24px; height: 24px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 0.72rem;
    background: #21262d;
}
.cnav-mod-info { flex: 1; min-width: 0; }
.cnav-mod-title { font-size: 0.75rem; font-weight: 600; color: #8b949e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cnav-mod.cnav-current .cnav-mod-title  { color: #e6edf3; font-weight: 700; }
.cnav-mod.cnav-completed .cnav-mod-title{ color: #7ee787; }
.cnav-mod-dur { font-size: 0.62rem; color: #484f58; margin-top: 1px; }
.cnav-mod-status { flex-shrink: 0; font-size: 0.72rem; color: #484f58; }
.cnav-mod.cnav-completed .cnav-mod-status { color: #3fb950; }

/* ══════════════════════════════════════════
   PDF VIEWER
══════════════════════════════════════════ */
.pdf-wrap { width: 100%; max-width: 860px; }
.pdf-canvas-card {
    background: white; border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    min-height: 480px;
}
#pdf-canvas { max-width: 100%; height: auto !important; display: block; }
.pdf-controls {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; margin-top: 14px;
    background: #161b22; border-radius: 10px; border: 1px solid #30363d;
    gap: 12px;
}
.pdf-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 6px;
    background: #21262d; border: 1px solid #30363d;
    color: #8b949e; font-size: 0.78rem; font-weight: 600; cursor: pointer;
    transition: all 0.15s;
}
.pdf-btn:hover:not(:disabled) { color: white; border-color: #58a6ff; background: rgba(88,166,255,0.1); }
.pdf-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pdf-page-info { color: #8b949e; font-size: 0.82rem; font-weight: 600; }
.pdf-page-input {
    width: 44px; text-align: center; padding: 4px 6px;
    background: #21262d; border: 1px solid #30363d; border-radius: 6px;
    color: #e6edf3; font-size: 0.8rem; outline: none;
}
.pdf-page-input:focus { border-color: #58a6ff; }

/* AI Chat under PDF */
.ai-chat-card {
    width: 100%; max-width: 860px;
    margin-top: 16px;
    background: #161b22; border: 1px solid #30363d;
    border-radius: 10px; overflow: hidden;
}
.ai-chat-head {
    padding: 12px 16px; border-bottom: 1px solid #30363d;
    display: flex; align-items: center; gap: 10px;
}
.ai-dot { width: 8px; height: 8px; border-radius: 50%; background: #3fb950; flex-shrink: 0; animation: blink 2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.3;} }
.ai-chat-head-title { font-size: 0.82rem; font-weight: 700; color: #e6edf3; }
.ai-chat-head-sub   { font-size: 0.68rem; color: #484f58; }
.ai-msgs { padding: 14px; min-height: 120px; max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
.ai-msgs::-webkit-scrollbar { width: 4px; }
.ai-msgs::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
.ai-msg { padding: 10px 14px; border-radius: 8px; font-size: 0.8rem; line-height: 1.65; max-width: 85%; }
.ai-msg.bot  { background: #21262d; color: #c9d1d9; align-self: flex-start; }
.ai-msg.user { background: #0d419d; color: #e6edf3; align-self: flex-end; }
.ai-msg.err  { background: rgba(248,81,73,0.1); color: #f85149; align-self: flex-start; }
.ai-input-row { padding: 12px; border-top: 1px solid #30363d; display: flex; gap: 8px; }
#ai-input {
    flex: 1; padding: 8px 12px;
    background: #21262d; border: 1px solid #30363d; border-radius: 6px;
    color: #e6edf3; font-size: 0.82rem; outline: none;
}
#ai-input:focus { border-color: #58a6ff; }
#ai-input::placeholder { color: #484f58; }
.ai-send-btn {
    padding: 8px 14px; border-radius: 6px;
    background: #1f6feb; border: none; color: white;
    font-size: 0.82rem; cursor: pointer; transition: background 0.15s;
    display: flex; align-items: center; gap: 4px;
}
.ai-send-btn:hover { background: #388bfd; }

/* ══════════════════════════════════════════
   VIDEO
══════════════════════════════════════════ */
.video-wrap { width: 100%; max-width: 960px; }
.video-player {
    width: 100%; aspect-ratio: 16/9;
    background: #000; border-radius: 12px; overflow: hidden;
    box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    position: relative;
}
.video-player video, .video-player iframe, .video-player > div { width: 100%; height: 100%; border: none; }
.video-time-overlay {
    position: absolute; bottom: 16px; right: 16px;
    background: rgba(0,0,0,0.6); color: white;
    padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
    backdrop-filter: blur(4px); display: none; pointer-events: none;
}
/* Milestone overlay */
#milestone-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.92); display: none;
    flex-direction: column; align-items: center; justify-content: center;
    padding: 24px; text-align: center; color: white; z-index: 50;
}
.ms-badge { display: inline-block; background: #d29922; color: white; font-size: 0.7rem; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
.ms-q { font-size: 1.05rem; font-weight: 700; max-width: 460px; margin-bottom: 20px; line-height: 1.4; }
.ms-opt {
    display: block; width: 100%; max-width: 380px; margin: 0 auto 8px;
    padding: 10px 16px; border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.06); color: white;
    border-radius: 8px; font-size: 0.85rem; cursor: pointer; text-align: left;
    transition: all 0.15s;
}
.ms-opt:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.4); }
.ms-opt.sel   { background: #1f6feb; border-color: #388bfd; }
.ms-submit {
    margin-top: 14px; padding: 10px 28px; border-radius: 20px;
    background: #d29922; border: none; color: white;
    font-size: 0.88rem; font-weight: 700; cursor: pointer;
    transition: all 0.2s;
}
.ms-submit:disabled { opacity: 0.45; cursor: not-allowed; }
.ms-submit:not(:disabled):hover { background: #e3b341; }

.video-chapters-bar {
    margin-top: 14px;
    background: #161b22; border: 1px solid #30363d;
    border-radius: 10px; overflow: hidden;
}
.chap-head { padding: 10px 16px; border-bottom: 1px solid #30363d; font-size: 0.78rem; font-weight: 700; color: #e6edf3; }
.chap-list { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 16px; }
.chap-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: #21262d; border: 1px solid #30363d;
    color: #8b949e; font-size: 0.72rem; font-weight: 600;
    padding: 5px 12px; border-radius: 20px; cursor: pointer;
    transition: all 0.15s;
}
.chap-chip:hover { color: white; border-color: #58a6ff; background: rgba(88,166,255,0.1); }
.chap-chip.active { color: #58a6ff; border-color: #58a6ff; background: rgba(88,166,255,0.1); }

/* ══════════════════════════════════════════
   TEXT/POLICY READER
══════════════════════════════════════════ */
.text-content-wrap { width: 100%; max-width: 760px; }
.text-module-card {
    background: #ffffff; border-radius: 12px;
    padding: 44px 52px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    color: #1e293b;
}
.text-module-card h1 { font-size: 1.9rem; font-weight: 900; color: #0f172a; margin-bottom: 8px; line-height: 1.2; }
.text-module-card h2 { font-size: 1.35rem; font-weight: 800; color: #1e293b; margin: 1.75rem 0 0.6rem; }
.text-module-card h3 { font-size: 1.1rem; font-weight: 700; color: #334155; margin: 1.4rem 0 0.5rem; }
.text-module-card h4 { font-size: 0.95rem; font-weight: 700; color: #475569; margin: 1.2rem 0 0.4rem; }
.text-module-card p  { font-size: 1rem; line-height: 1.85; color: #475569; margin-bottom: 1rem; }
.text-module-card ul, .text-module-card ol { padding-left: 1.5rem; margin-bottom: 1rem; }
.text-module-card li { font-size: 1rem; line-height: 1.8; color: #475569; margin-bottom: 0.35rem; }
.text-module-card strong { color: #1e293b; }
.text-module-card em { color: #475569; }
.text-module-card blockquote {
    border-left: 4px solid #06b6d4; padding: 12px 20px;
    margin: 1.5rem 0; background: #f0fdff; border-radius: 0 8px 8px 0;
    font-style: italic; color: #0e7490;
}
.text-module-card hr { border: none; border-top: 1px solid #e2e8f0; margin: 2rem 0; }
.text-meta {
    display: flex; flex-wrap: wrap; gap: 10px;
    padding-bottom: 20px; margin-bottom: 24px;
    border-bottom: 1px solid #f1f5f9;
}
.text-meta-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: #f1f5f9; color: #64748b;
    font-size: 0.72rem; font-weight: 600;
    padding: 5px 10px; border-radius: 20px;
}
.read-complete-msg {
    margin-top: 32px; padding: 24px; border-radius: 12px;
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1px solid #bbf7d0; text-align: center;
    display: none;
}
.read-complete-msg h4 { color: #065f46; margin-bottom: 6px; }
.read-complete-msg p  { color: #047857; font-size: 0.85rem; margin-bottom: 16px; }
.rcm-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white; font-size: 0.9rem; font-weight: 700;
    padding: 12px 28px; border-radius: 50px; border: none; cursor: pointer;
    box-shadow: 0 6px 20px rgba(16,185,129,0.3);
    transition: all 0.2s;
}
.rcm-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(16,185,129,0.4); }

/* ══════════════════════════════════════════
   BOTTOM NAV BAR
══════════════════════════════════════════ */
.bottom-nav {
    background: #161b22; border-top: 1px solid #30363d;
    height: 52px; display: flex; align-items: center;
    padding: 0 20px; gap: 16px; flex-shrink: 0;
    z-index: 200;
}
.bnav-course { flex: 1; font-size: 0.75rem; font-weight: 600; color: #484f58; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bnav-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: 6px;
    background: #21262d; border: 1px solid #30363d;
    color: #8b949e; font-size: 0.78rem; font-weight: 600; cursor: pointer;
    text-decoration: none; transition: all 0.15s; flex-shrink: 0;
}
.bnav-btn:hover { color: white; border-color: #58a6ff; background: rgba(88,166,255,0.08); }
.bnav-btn.primary { background: #1f6feb; border-color: #1f6feb; color: white; }
.bnav-btn.primary:hover { background: #388bfd; color: white; }
.course-nav-toggle {
    display: none;
    align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 6px;
    background: #21262d; border: 1px solid #30363d;
    color: #8b949e; font-size: 0.78rem; font-weight: 600; cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s;
}
.course-nav-toggle:hover { color: white; border-color: #58a6ff; }
@media (max-width: 991px) { .course-nav-toggle { display: inline-flex; } }

/* ══════════════════════════════════════════
   ASSESSMENT FULLSCREEN
══════════════════════════════════════════ */
#quiz-overlay {
    position: fixed; inset: 0; z-index: 9000;
    background: #0d1117;
    display: none; overflow-y: auto;
    padding: 28px 20px 60px;
}
.quiz-wrap { max-width: 760px; margin: 0 auto; }
.quiz-topbar {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 28px;
}
.quiz-close-btn {
    display: flex; align-items: center; gap: 6px;
    background: #21262d; border: 1px solid #30363d;
    color: #8b949e; font-size: 0.78rem; font-weight: 600;
    padding: 7px 14px; border-radius: 6px; cursor: pointer;
    transition: all 0.15s;
}
.quiz-close-btn:hover { color: white; border-color: #f85149; }
.quiz-title { font-size: 0.88rem; font-weight: 700; color: #e6edf3; }
.quiz-sub { font-size: 0.75rem; color: #484f58; margin-left: auto; }
.quiz-card {
    background: #161b22; border: 1px solid #30363d;
    border-radius: 14px; overflow: hidden;
}
.quiz-card-top {
    padding: 24px 28px 0;
    background: linear-gradient(180deg, #161b22, #161b22);
}
.quiz-q-badge {
    display: inline-block;
    background: rgba(88,166,255,0.1); border: 1px solid rgba(88,166,255,0.2);
    color: #58a6ff; font-size: 0.7rem; font-weight: 700;
    letter-spacing: 0.8px; text-transform: uppercase;
    padding: 4px 12px; border-radius: 20px; margin-bottom: 14px;
}
.quiz-q-num { font-size: 0.72rem; color: #484f58; font-weight: 600; margin-bottom: 8px; }
.quiz-q-text { font-size: 1.05rem; font-weight: 700; color: #e6edf3; line-height: 1.45; margin-bottom: 20px; }
.quiz-opts-wrap { padding: 0 28px 6px; }
.quiz-opt {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 16px; border-radius: 10px;
    border: 1px solid #30363d;
    margin-bottom: 9px; cursor: pointer;
    transition: all 0.15s; background: #21262d;
}
.quiz-opt:hover { border-color: #58a6ff; background: rgba(88,166,255,0.05); }
.quiz-opt.sel { border-color: #388bfd; background: rgba(31,111,235,0.12); }
.opt-dot {
    width: 20px; height: 20px; border-radius: 50%;
    border: 2px solid #484f58; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.quiz-opt.sel .opt-dot { background: #388bfd; border-color: #388bfd; }
.quiz-opt.sel .opt-dot::after { content:''; width:6px; height:6px; background:white; border-radius:50%; display:block; }
.opt-ltr { font-size: 0.7rem; font-weight: 800; color: #484f58; width:14px; flex-shrink:0; }
.quiz-opt.sel .opt-ltr { color: #58a6ff; }
.opt-text { font-size: 0.88rem; color: #c9d1d9; flex: 1; }
.quiz-opt.sel .opt-text { color: #e6edf3; }

.quiz-card-foot {
    padding: 16px 28px;
    border-top: 1px solid #30363d;
    background: #1c2128;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.qfoot-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 9px 20px; border-radius: 8px; border: 1px solid #30363d;
    background: #21262d; color: #8b949e; font-size: 0.82rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s; flex-shrink: 0;
}
.qfoot-btn:hover { color: white; border-color: #58a6ff; }
.qfoot-btn.primary { background: #1f6feb; border-color: #1f6feb; color: white; }
.qfoot-btn.primary:hover { background: #388bfd; }
.qfoot-btn.success { background: #1f883d; border-color: #238636; color: white; }
.qfoot-btn.success:hover { background: #2ea043; }

/* Progress dots */
.q-dots { display: flex; gap: 5px; }
.q-dot { width: 8px; height: 8px; border-radius: 50%; background: #30363d; transition: all 0.2s; }
.q-dot.ans  { background: #388bfd; }
.q-dot.curr { background: #58a6ff; transform: scale(1.4); }

/* All-at-once questions */
.quiz-all-block { padding: 0 28px 16px; }
.qall-q { background: #21262d; border-radius: 10px; padding: 18px; margin-bottom: 14px; }
.qall-q-text { font-size: 0.92rem; font-weight: 700; color: #e6edf3; margin-bottom: 14px; line-height: 1.4; }
.qall-q-num { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: #388bfd; color: white; border-radius: 6px; font-size: 0.7rem; font-weight: 800; margin-right: 8px; vertical-align: middle; }

/* Result screen */
.result-screen {
    display: none; text-align: center; padding: 40px 28px;
}
.result-ring { position: relative; display: inline-flex; margin-bottom: 20px; }
.result-ring-text {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.result-pct   { font-size: 1.8rem; font-weight: 900; line-height: 1; }
.result-label { font-size: 0.55rem; color: #484f58; text-transform: uppercase; letter-spacing: 0.5px; }
.result-title { font-size: 1.6rem; font-weight: 900; color: #e6edf3; margin-bottom: 6px; }
.result-msg   { font-size: 0.9rem; color: #8b949e; margin-bottom: 28px; line-height: 1.6; }
.result-btns  { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.res-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 24px; border-radius: 8px; font-size: 0.85rem; font-weight: 700;
    cursor: pointer; border: 1px solid #30363d; background: #21262d; color: #c9d1d9;
    transition: all 0.15s; text-decoration: none;
}
.res-btn:hover { color: white; border-color: #58a6ff; }
.res-btn.primary { background: #1f6feb; border-color: #1f6feb; color: white; }
.res-btn.primary:hover { background: #388bfd; color: white; }
.res-btn.success { background: #1f883d; border-color: #238636; color: white; }
.res-btn.success:hover { background: #2ea043; color: white; }

/* ══════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════ */

/* Desktop: course nav always open (JS also adds .open on load) */
@media (min-width: 992px) {
    .course-nav { width: 300px; opacity: 1; }
}

@media (max-width: 991px) {
    /* On mobile/tablet, course nav is hidden unless .open is toggled by JS */
    .course-nav { width: 0; opacity: 0; }
    .course-nav.open { width: 260px; opacity: 1; }
}

@media (max-width: 767px) {
    .content-pane { padding: 16px 12px 80px; }
    .text-module-card { padding: 24px 20px; }
    .text-module-card h1 { font-size: 1.4rem; }
    .course-nav.open { width: 100%; position: fixed; inset: 52px 0 52px; z-index: 150; }
    .topbar-phase { display: none; }
}
</style>
@endsection

@section('content')

@php
    // Build prev/next module navigation
    use App\Models\TrainingModule;
    use App\Models\TrainingPhase;

    $allModules = TrainingModule::whereHas('phase')
        ->with('phase')
        ->orderByRaw('(SELECT `order` FROM training_phases WHERE id = phase_id)')
        ->orderBy('order')
        ->get();

    $currentIdx = $allModules->search(fn($m) => $m->id == $module->id);
    $prevModule  = $currentIdx > 0 ? $allModules[$currentIdx - 1] : null;
    $nextModule  = $currentIdx !== false && $currentIdx < $allModules->count() - 1 ? $allModules[$currentIdx + 1] : null;

    // All phases with progress for course nav sidebar
    $user = auth()->user();
    $phases = TrainingPhase::with(['modules.userProgress' => function($q) use ($user) {
        $q->where('user_id', $user->id);
    }])->orderBy('order')->get();

    $phaseNavColors = ['#06b6d4','#3b82f6','#a855f7','#f97316','#22c55e'];
@endphp

<div class="reader-root">

    {{-- ════ TOP BAR ════ --}}
    <div class="reader-topbar">
        <a href="{{ route('training.portal') }}" class="topbar-back">
            <i class="ti ti-arrow-left" style="font-size:0.8rem;"></i>
            <span class="d-none d-sm-inline">Portal</span>
        </a>
        <div class="topbar-sep d-none d-md-block"></div>
        <div class="d-none d-md-flex align-items-center gap-2" style="flex-shrink:0;">
            <img src="{{ asset('assets/img/logo.png') }}" alt="HRX Logo" style="height:28px;width:auto;object-fit:contain;">
            <div style="display:flex;flex-direction:column;line-height:1.1;">
                <span style="font-size:0.78rem;font-weight:700;color:white;">Hi Tech <span style="color:#06b6d4;">HRX</span></span>
                <span style="font-size:0.55rem;font-weight:600;color:rgba(255,255,255,0.4);letter-spacing:1px;text-transform:uppercase;">NEXT-GEN HRMS</span>
            </div>
        </div>
        <div class="topbar-sep d-none d-md-block"></div>
        <div class="topbar-breadcrumb">
            <div class="topbar-phase d-none d-md-block">Phase {{ $module->phase->order ?? '?' }}: {{ $module->phase->title ?? '' }}</div>
            <div class="topbar-title">{{ $module->title }}</div>
        </div>
        <div class="topbar-right">
            {{-- Timer (not for video) --}}
            @if($module->content_type !== 'video')
            <div class="timer-chip" id="timer-chip">
                <i class="ti ti-clock" id="timer-icon" style="font-size:0.8rem;"></i>
                <span id="timer-display">{{ str_pad($module->estimated_time_minutes ?? 5, 2, '0', STR_PAD_LEFT) }}:00</span>
            </div>
            @endif

            {{-- Notes toggle --}}
            <button class="topbar-icon-btn" id="notes-toggle" title="Notes">
                <i class="ti ti-notebook" style="font-size:0.88rem;"></i>
            </button>

            {{-- Course nav toggle (mobile) --}}
            <button class="topbar-icon-btn d-lg-none" id="cnav-toggle" title="Course contents">
                <i class="ti ti-list" style="font-size:0.88rem;"></i>
            </button>

            {{-- Assessment button --}}
            <button class="assess-btn" id="assess-btn" onclick="openQuiz()">
                <i class="ti ti-clipboard-check" style="font-size:0.88rem;"></i>
                <span class="d-none d-sm-inline">Take Quiz</span>
            </button>
        </div>
    </div>

    {{-- Progress track --}}
    <div class="topbar-progress">
        <div class="topbar-progress-fill" id="top-progress" style="width:0%"></div>
    </div>

    {{-- ════ MAIN AREA ════ --}}
    <div class="reader-main">

        {{-- ── CONTENT ── --}}
        <div class="content-pane" id="content-pane">

            {{-- ══ CATALOG / PDF ══ --}}
            @if($module->content_type === 'catalog' && $module->content_url)
            <div class="pdf-wrap">
                <div class="pdf-canvas-card" id="pdf-box">
                    <div id="pdf-loading" style="padding:60px;text-align:center;color:#64748b;">
                        <div style="width:36px;height:36px;border:3px solid #e2e8f0;border-top-color:#06b6d4;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 14px;"></div>
                        Loading catalog...
                    </div>
                    <canvas id="pdf-canvas" style="display:none;"></canvas>
                </div>
                <div class="pdf-controls">
                    <button class="pdf-btn" id="pdf-prev" disabled><i class="ti ti-chevron-left"></i> Prev</button>
                    <div class="pdf-page-info">
                        Page <input type="number" id="pdf-pi" class="pdf-page-input" value="1" min="1"> of <span id="pdf-total">?</span>
                    </div>
                    <button class="pdf-btn" id="pdf-next" disabled>Next <i class="ti ti-chevron-right"></i></button>
                </div>
            </div>

            {{-- AI Chat --}}
            <div class="ai-chat-card">
                <div class="ai-chat-head">
                    <div class="ai-dot"></div>
                    <div>
                        <div class="ai-chat-head-title">AI Study Assistant</div>
                        <div class="ai-chat-head-sub">Ask anything — I'll cite the page for you</div>
                    </div>
                    <button style="margin-left:auto;background:transparent;border:none;color:#484f58;cursor:pointer;font-size:0.72rem;" id="ai-toggle">Hide</button>
                </div>
                <div id="ai-chat-container">
                    <div class="ai-msgs" id="ai-msgs">
                        <div class="ai-msg bot">Hello! I'm your AI study assistant for this catalog. Ask me anything and I'll search the document and cite pages.</div>
                    </div>
                    <div class="ai-input-row">
                        <input type="text" id="ai-input" placeholder="Ask a question about this catalog...">
                        <button class="ai-send-btn" id="ai-send"><i class="ti ti-send"></i></button>
                    </div>
                </div>
            </div>

            {{-- ══ VIDEO ══ --}}
            @elseif($module->content_type === 'video')
            <div class="video-wrap">
                <div class="video-player" id="video-player">
                    @if(!empty($module->content_url) && (str_contains($module->content_url, 'youtube.com') || str_contains($module->content_url, 'youtu.be')))
                        <div id="yt-player"></div>
                    @elseif(!empty($module->content_url))
                        <video id="local-video" controls controlsList="nodownload" oncontextmenu="return false;"
                               data-src="{{ $module->content_url }}"></video>
                    @else
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#484f58;flex-direction:column;gap:8px;">
                            <i class="ti ti-video-off" style="font-size:2.5rem;opacity:0.4;"></i>
                            <span style="font-size:0.85rem;">No video URL configured</span>
                        </div>
                    @endif
                    <div class="video-time-overlay" id="vid-time"></div>
                    <div id="milestone-overlay">
                        <span class="ms-badge">⚡ Checkpoint Quiz</span>
                        <div class="ms-q" id="ms-q"></div>
                        <div id="ms-opts"></div>
                        <button class="ms-submit" id="ms-submit" disabled>Submit Answer</button>
                    </div>
                </div>

                @if(!empty($module->video_chapters))
                <div class="video-chapters-bar">
                    <div class="chap-head"><i class="ti ti-list-numbers" style="margin-right:6px;"></i> Chapters</div>
                    <div class="chap-list" id="chap-list"></div>
                </div>
                @endif
            </div>

            {{-- ══ TEXT / POLICY ══ --}}
            @else
            <div class="text-content-wrap">
                <div class="text-module-card" id="text-card">
                    <h1>{{ $module->title }}</h1>
                    <div class="text-meta">
                        <span class="text-meta-chip"><i class="ti ti-clock" style="font-size:0.75rem;"></i> {{ $module->estimated_time_minutes }} min read</span>
                        <span class="text-meta-chip"><i class="ti ti-book" style="font-size:0.75rem;"></i> Reading Module</span>
                        @if($module->phase)<span class="text-meta-chip"><i class="ti ti-layers" style="font-size:0.75rem;"></i> Phase {{ $module->phase->order }}</span>@endif
                    </div>
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
                        <button class="rcm-btn" onclick="openQuiz()">
                            <i class="ti ti-clipboard-check"></i> Start Quiz
                        </button>
                    </div>
                </div>
            </div>
            @endif

        </div>

        {{-- ── NOTES PANEL ── --}}
        <div class="notes-panel" id="notes-panel">
            <div class="notes-header">
                <span><i class="ti ti-notebook" style="margin-right:6px;color:#58a6ff;"></i>My Notes</span>
                <button style="background:transparent;border:none;color:#484f58;cursor:pointer;font-size:0.85rem;" onclick="closeNotes()"><i class="ti ti-x"></i></button>
            </div>
            <div class="notes-body">
                <textarea id="notes-ta" placeholder="📝 Jot down key points, definitions, or anything worth remembering...

Auto-saved as you type."></textarea>
                <div class="notes-footer">
                    <button class="notes-btn" onclick="copyNotes()"><i class="ti ti-copy"></i> Copy</button>
                    <button class="notes-btn danger" onclick="clearNotes()"><i class="ti ti-trash"></i></button>
                </div>
            </div>
            <div class="notes-save-status" id="notes-status"><i class="ti ti-cloud-check" style="margin-right:4px;color:#3fb950;"></i>Auto-saved</div>
        </div>

        {{-- ── COURSE NAV SIDEBAR (desktop always visible on right, mobile toggle) ── --}}
        <div class="course-nav" id="course-nav">
            <div class="course-nav-header">
                <span><i class="ti ti-list" style="margin-right:6px;"></i>Course Contents</span>
                <button class="d-lg-none" style="background:transparent;border:none;color:#484f58;cursor:pointer;" onclick="closeCourseNav()"><i class="ti ti-x"></i></button>
            </div>
            <div class="course-nav-body" id="cnav-body">
                @foreach($phases as $pi => $phase)
                @php
                    $isCurrentPhase = $phase->id === $module->phase_id;
                    $phaseHasActive = $phase->modules->contains(fn($m) => $m->id == $module->id);
                    $cnColor = $phaseNavColors[$pi % 5];
                @endphp
                <div class="cnav-phase">
                    <div class="cnav-phase-header" onclick="toggleCnavPhase('cnav-phase-{{ $phase->id }}','cnav-chev-{{ $phase->id }}')">
                        <div class="cnav-phase-num" style="background:{{ $cnColor }};">{{ $phase->order }}</div>
                        <div class="cnav-phase-title">{{ $phase->title }}</div>
                        <div class="cnav-phase-count">{{ $phase->modules->count() }}m</div>
                        <i class="ti ti-chevron-down cnav-chevron {{ $isCurrentPhase ? 'open' : '' }}" id="cnav-chev-{{ $phase->id }}"></i>
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
                            $mIconCol  = match($mod->content_type) { 'video' => '#f59e0b', 'catalog' => '#58a6ff', default => '#3fb950' };
                            $mIconName = match($mod->content_type) { 'video' => 'ti-player-play', 'catalog' => 'ti-file-text', default => 'ti-book' };
                        @endphp
                        <a href="{{ $cnavHref }}" class="cnav-mod {{ $cnavClass }}">
                            <div class="cnav-mod-icon">
                                @if($isDone)
                                    <i class="ti ti-check" style="color:#3fb950;font-size:0.75rem;"></i>
                                @elseif($isLock)
                                    <i class="ti ti-lock" style="color:#484f58;font-size:0.75rem;"></i>
                                @else
                                    <i class="ti {{ $mIconName }}" style="color:{{ $mIconCol }};font-size:0.75rem;"></i>
                                @endif
                            </div>
                            <div class="cnav-mod-info">
                                <div class="cnav-mod-title">{{ $mod->title }}</div>
                                <div class="cnav-mod-dur">{{ $mod->estimated_time_minutes }}m
                                    @if($mp && $mp->assessment_score > 0) · {{ round($mp->assessment_score) }}%@endif
                                </div>
                            </div>
                            <div class="cnav-mod-status">
                                @if($isDone) <i class="ti ti-check"></i>
                                @elseif($isCur) <i class="ti ti-player-play" style="color:#58a6ff;font-size:0.75rem;"></i>
                                @elseif($isLock) <i class="ti ti-lock" style="font-size:0.72rem;"></i>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>{{-- end reader-main --}}

    {{-- ════ BOTTOM NAV ════ --}}
    <div class="bottom-nav">
        @if($prevModule)
        <a href="{{ route('training.module.show', $prevModule->id) }}" class="bnav-btn">
            <i class="ti ti-chevron-left" style="font-size:0.8rem;"></i>
            <span class="d-none d-sm-inline">{{ \Illuminate\Support\Str::limit($prevModule->title, 22) }}</span>
        </a>
        @endif
        <div class="bnav-course">{{ $module->title }}</div>
        <button class="course-nav-toggle" id="cnav-toggle-bottom">
            <i class="ti ti-list" style="font-size:0.8rem;"></i>
            Contents
        </button>
        @if($nextModule)
        <a href="{{ route('training.module.show', $nextModule->id) }}" class="bnav-btn primary">
            <span class="d-none d-sm-inline">{{ \Illuminate\Support\Str::limit($nextModule->title, 22) }}</span>
            <i class="ti ti-chevron-right" style="font-size:0.8rem;"></i>
        </a>
        @endif
    </div>

</div>{{-- end reader-root --}}

{{-- ════ QUIZ OVERLAY ════ --}}
<div id="quiz-overlay">
    <div class="quiz-wrap">
        <div class="quiz-topbar">
            <button class="quiz-close-btn" onclick="closeQuiz()"><i class="ti ti-arrow-left" style="font-size:0.8rem;"></i> Exit Quiz</button>
            <div class="quiz-title">{{ $module->title }}</div>
            <div class="quiz-sub" id="quiz-sub">Loading...</div>
        </div>
        <div class="quiz-card" id="quiz-card">
            <div class="quiz-card-top" id="quiz-card-top">
                <div class="quiz-q-badge">Module Assessment</div>
                <div class="quiz-q-num" id="q-num">Question 1 of ?</div>
                <div class="quiz-q-text" id="q-text">Loading questions...</div>
                <div class="q-dots" id="q-dots" style="margin-bottom:16px;flex-wrap:wrap;gap:5px;"></div>
            </div>
            <div id="quiz-single-body">
                <div class="quiz-opts-wrap" id="q-opts"></div>
                <div class="quiz-card-foot">
                    <button class="qfoot-btn" id="q-prev" style="visibility:hidden;"><i class="ti ti-chevron-left"></i> Prev</button>
                    <span></span>
                    <button class="qfoot-btn primary" id="q-next">Next <i class="ti ti-chevron-right"></i></button>
                    <button class="qfoot-btn success" id="q-submit" style="display:none;"><i class="ti ti-check"></i> Submit</button>
                </div>
            </div>
            <div id="quiz-all-body" style="display:none;">
                <div class="quiz-all-block" id="q-all-content"></div>
                <div class="quiz-card-foot">
                    <span class="text-muted" style="font-size:0.78rem;color:#484f58;" id="q-all-count">0/0 answered</span>
                    <span></span>
                    <button class="qfoot-btn success" id="q-all-submit"><i class="ti ti-check"></i> Submit All</button>
                </div>
            </div>
        </div>
        <div class="result-screen" id="quiz-result">
            <div class="result-ring" id="result-ring"></div>
            <div id="result-grade-badge" style="margin-bottom:12px;"></div>
            <div class="result-title" id="result-title"></div>
            <div class="result-msg" id="result-msg"></div>
            <div class="result-btns">
                <a href="{{ route('training.portal') }}" class="res-btn" id="result-portal-btn"><i class="ti ti-layout-dashboard"></i> Portal</a>
                <button class="res-btn" id="result-retry" style="display:none;" onclick="retakeQuiz()"><i class="ti ti-refresh"></i> Retake</button>
                <a href="#" class="res-btn primary" id="result-next-btn" style="display:none;"><i class="ti ti-arrow-right"></i> Next Module</a>
            </div>
        </div>
    </div>
</div>

@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
@endsection

@section('page-script')
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
/* ══════════════════════════════════════════
   CONFIG
══════════════════════════════════════════ */
const MOD_ID       = {{ $module->id }};
const CTYPE        = "{{ $module->content_type }}";
const CURL         = @json($module->content_url ?? '');
const EST_MINS     = {{ $module->estimated_time_minutes ?? 5 }};
const VID_CHAPTERS = {!! json_encode($module->video_chapters ?? []) !!};
const VID_MILES    = {!! json_encode($module->video_milestones ?? []) !!};
const CSRF         = "{{ csrf_token() }}";
const ASSESS_URL        = "{{ route('training.assessment.get', $module->id) }}";
const SUBMIT_URL        = "{{ route('training.assessment.submit', $module->id) }}";
const AI_URL            = "{{ url('/training/module') }}/" + MOD_ID + "/chat";
const NOTES_KEY         = "lms_notes_" + MOD_ID;
const NEXT_MODULE_URL   = @json($nextModule ? route('training.module.show', $nextModule->id) : null);
const NEXT_MODULE_TITLE = @json($nextModule ? $nextModule->title : null);
const IS_COMPLETED      = {{ $progress->status === 'completed' ? 'true' : 'false' }};

/* ══════════════════════════════════════════
   TIMER
══════════════════════════════════════════ */
let timerSecs = EST_MINS * 60, timerElapsed = 0, timerDone = false, timerInterval = null;

function markCompletedUI() {
    timerDone = true;
    const chip = document.getElementById('timer-chip');
    const disp = document.getElementById('timer-display');
    const icon = document.getElementById('timer-icon');
    if (chip) { chip.classList.remove('active','warning'); chip.classList.add('done'); }
    if (icon) icon.className = 'ti ti-rosette-discount-check';
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
    const icon = document.getElementById('timer-icon');
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
    if (icon) icon.className = 'ti ti-check';
    // catalog WITH a PDF → wait for pdfReached; catalog without URL OR text → treat like text
    if (CTYPE === 'catalog' && CURL) { checkCatalogReady(); }
    else { showAssessBtn(); showReadComplete(); if (!IS_COMPLETED) setTimeout(() => openQuiz(), 1200); }
}
function showAssessBtn() {
    const btn = document.getElementById('assess-btn');
    if (btn) btn.style.display = 'inline-flex';
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
            if (st) st.innerHTML = '<i class="ti ti-cloud" style="margin-right:4px;color:#484f58;"></i>Saving...';
            clearTimeout(notesSaveTO);
            notesSaveTO = setTimeout(() => {
                localStorage.setItem(NOTES_KEY, ta.value);
                if (st) st.innerHTML = '<i class="ti ti-cloud-check" style="margin-right:4px;color:#3fb950;"></i>Saved';
            }, 1200);
        });
    }
}
function toggleNotes() {
    notesOpen = !notesOpen;
    const p = document.getElementById('notes-panel');
    const btn = document.getElementById('notes-toggle');
    if (p)   p.classList.toggle('open', notesOpen);
    if (btn) btn.classList.toggle('active', notesOpen);
}
function closeNotes() { notesOpen = false; const p = document.getElementById('notes-panel'); if (p) p.classList.remove('open'); const btn = document.getElementById('notes-toggle'); if (btn) btn.classList.remove('active'); }
function copyNotes()  { const ta = document.getElementById('notes-ta'); if (!ta?.value.trim()) return Swal.fire({toast:true,position:'top-end',icon:'info',title:'No notes yet',showConfirmButton:false,timer:2000}); navigator.clipboard.writeText(ta.value).then(() => Swal.fire({toast:true,position:'top-end',icon:'success',title:'Copied!',showConfirmButton:false,timer:1800})); }
function clearNotes() { Swal.fire({title:'Clear notes?',text:'Cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Clear',background:'#161b22',color:'#e6edf3',confirmButtonColor:'#f85149'}).then(r => { if (r.isConfirmed) { const ta = document.getElementById('notes-ta'); if (ta) { ta.value=''; localStorage.setItem(NOTES_KEY,''); } } }); }

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
   PDF VIEWER
══════════════════════════════════════════ */
let pdfDoc=null, pdfPage=1, pdfTotal=0, pdfRendering=false, pdfReached=false;

function initPdf() {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    pdfjsLib.getDocument(CURL).promise.then(doc => {
        pdfDoc = doc; pdfTotal = doc.numPages;
        document.getElementById('pdf-total').textContent = pdfTotal;
        document.getElementById('pdf-pi').max = pdfTotal;
        const loading = document.getElementById('pdf-loading');
        if (loading) loading.style.display = 'none';
        document.getElementById('pdf-canvas').style.display = 'block';
        if (pdfTotal > 1) document.getElementById('pdf-next').disabled = false;
        renderPdfPage(1);
    }).catch(e => {
        const loading = document.getElementById('pdf-loading');
        if (loading) loading.innerHTML = '<div style="color:#f85149;padding:30px;text-align:center;"><i class="ti ti-alert-circle" style="font-size:2rem;display:block;margin-bottom:8px;"></i>Failed to load PDF: ' + e.message + '</div>';
    });
}
async function renderPdfPage(n) {
    if (!pdfDoc || pdfRendering) return;
    pdfRendering = true; pdfPage = n;
    const page = await pdfDoc.getPage(n);
    const canvas = document.getElementById('pdf-canvas');
    const box = document.getElementById('pdf-box');
    const scale = Math.min(box.clientWidth / page.getViewport({scale:1}).width * 0.95, 2);
    const vp = page.getViewport({scale});
    canvas.width = vp.width; canvas.height = vp.height;
    canvas.style.opacity = '0'; canvas.style.transform = 'scale(0.97)';
    await page.render({canvasContext: canvas.getContext('2d'), viewport: vp}).promise;
    canvas.style.transition = 'opacity 0.3s, transform 0.3s';
    canvas.style.opacity = '1'; canvas.style.transform = 'scale(1)';
    document.getElementById('pdf-pi').value = n;
    document.getElementById('pdf-prev').disabled = n <= 1;
    document.getElementById('pdf-next').disabled = n >= pdfTotal;
    const pct = (n / pdfTotal) * 100;
    const pb = document.getElementById('top-progress'); if (pb) pb.style.width = pct + '%';
    if (n >= pdfTotal) { pdfReached = true; checkCatalogReady(); }
    pdfRendering = false;
}
function checkCatalogReady() {
    if (IS_COMPLETED || (pdfReached && timerDone)) showAssessBtn();
}

/* ══════════════════════════════════════════
   AI CHAT
══════════════════════════════════════════ */
function initAi() {
    const send = document.getElementById('ai-send');
    const inp  = document.getElementById('ai-input');
    const tog  = document.getElementById('ai-toggle');
    if (!send || !inp) return;

    async function doSend() {
        const msg = inp.value.trim(); if (!msg) return;
        inp.value = '';
        appendAiMsg(msg, 'user');
        const tid = appendAiTyping();
        try {
            const r = await fetch(AI_URL, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF}, body:JSON.stringify({message:msg}) });
            const d = await r.json();
            removeAiTyping(tid);
            appendAiMsg(d.success ? d.message : (d.message||'AI error.'), d.success ? 'bot' : 'err');
        } catch { removeAiTyping(tid); appendAiMsg('AI assistant unavailable.', 'err'); }
    }
    send.onclick = doSend;
    inp.onkeydown = e => { if (e.key==='Enter'&&!e.shiftKey) { e.preventDefault(); doSend(); } };
    if (tog) {
        tog.onclick = () => {
            const c = document.getElementById('ai-chat-container');
            const hidden = c.style.display === 'none';
            c.style.display = hidden ? 'block' : 'none';
            tog.textContent = hidden ? 'Hide' : 'Show';
        };
    }
}
function appendAiMsg(text, type) {
    const box = document.getElementById('ai-msgs'); if (!box) return;
    const d = document.createElement('div'); d.className = 'ai-msg ' + type;
    if (type === 'bot') {
        d.innerHTML = text
            .replace(/Page\s+(\d+)/gi, `<a href="javascript:void(0)" onclick="renderPdfPage($1)" style="color:#58a6ff;font-weight:700;">Page $1</a>`)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    } else { d.textContent = text; }
    box.appendChild(d); box.scrollTop = box.scrollHeight;
}
function appendAiTyping() {
    const box = document.getElementById('ai-msgs'); if (!box) return null;
    const id = 'ai-t-' + Date.now();
    const d = document.createElement('div'); d.id = id; d.className = 'ai-msg bot';
    d.innerHTML = '<span style="display:inline-flex;gap:4px;align-items:center;"><span style="width:6px;height:6px;background:#484f58;border-radius:50%;animation:blink 1.2s 0s ease-in-out infinite;display:inline-block;"></span><span style="width:6px;height:6px;background:#484f58;border-radius:50%;animation:blink 1.2s 0.2s ease-in-out infinite;display:inline-block;"></span><span style="width:6px;height:6px;background:#484f58;border-radius:50%;animation:blink 1.2s 0.4s ease-in-out infinite;display:inline-block;"></span></span>';
    box.appendChild(d); box.scrollTop = box.scrollHeight; return id;
}
function removeAiTyping(id) { if (id) { const el = document.getElementById(id); if (el) el.remove(); } }

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
    if (!VID_CHAPTERS?.length) { list.innerHTML = '<span style="color:#484f58;font-size:0.78rem;">No chapters.</span>'; return; }
    VID_CHAPTERS.forEach(ch => {
        const btn = document.createElement('button');
        btn.className = 'chap-chip';
        btn.innerHTML = `<i class="ti ti-player-skip-forward" style="font-size:0.72rem;"></i>${ch.title} <span style="font-family:monospace;opacity:0.6;">${fvt(ch.time)}</span>`;
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
    sub.textContent = 'Submit Answer'; sub.style.background = '#d29922';
    m.options.forEach((opt, i) => {
        const btn = document.createElement('button');
        btn.className = 'ms-opt'; btn.textContent = opt;
        btn.onclick = () => { document.querySelectorAll('.ms-opt').forEach(b=>b.classList.remove('sel')); btn.classList.add('sel'); selMsIdx = i; sub.disabled = false; };
        opts.appendChild(btn);
    });
    document.getElementById('milestone-overlay').style.display = 'flex';
}

function startYT() { if (ytInt) clearInterval(ytInt); ytInt = setInterval(() => { if (!ytP) return; const ct = ytP.getCurrentTime(), dur = ytP.getDuration(); const to = document.getElementById('vid-time'); if (to) { to.style.display='block'; to.textContent = fvt(ct)+' / '+fvt(dur); } const pb = document.getElementById('top-progress'); if (pb && dur) pb.style.width = (ct/dur*100)+'%'; checkMilestones(ct); }, 800); }
function stopYT() { clearInterval(ytInt); }
function getYtId(u) { const m = u.match(/(?:youtu\.be\/|v=|embed\/)([a-zA-Z0-9_-]{11})/); return m?m[1]:null; }

window.onYouTubeIframeAPIReady = function() {
    const vid = getYtId(CURL); if (!vid) return;
    ytP = new YT.Player('yt-player', { height:'100%', width:'100%', videoId:vid, playerVars:{playsinline:1,rel:0},
        events: { onStateChange(e) { if (e.data===YT.PlayerState.PLAYING) startYT(); else stopYT(); if (e.data===YT.PlayerState.ENDED) { stopYT(); showAssessBtn(); const pb=document.getElementById('top-progress'); if(pb) pb.style.width='100%'; } } } });
};
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
let qList=[], qIdx=0, qAnswers={}, showAll=false, passPct=80;

function openQuiz() {
    if (CTYPE !== 'video' && !timerDone) {
        Swal.fire({title:'Not yet!',text:`Complete the minimum read time (${EST_MINS} min) first.`,icon:'info',background:'#161b22',color:'#e6edf3',confirmButtonColor:'#1f6feb'});
        return;
    }
    if (CTYPE === 'catalog' && CURL && !pdfReached) {
        Swal.fire({title:'Read the full catalog',text:'Scroll through all pages before taking the quiz.',icon:'info',background:'#161b22',color:'#e6edf3',confirmButtonColor:'#1f6feb'});
        return;
    }
    const ov = document.getElementById('quiz-overlay'); ov.style.display = 'block'; ov.scrollTop = 0;
    loadQuiz();
}
function closeQuiz() {
    document.getElementById('quiz-overlay').style.display = 'none';
}
async function loadQuiz() {
    const sub = document.getElementById('quiz-sub'); if (sub) sub.textContent = 'Loading...';
    try {
        const r = await fetch(ASSESS_URL); const d = await r.json();
        if (!d.success || !d.questions.length) {
            Swal.fire({title:'No questions',text:'No assessment questions found.',icon:'info',background:'#161b22',color:'#e6edf3'}); closeQuiz(); return;
        }
        qList = d.questions; showAll = d.show_all_at_once; passPct = d.passing_percentage;
        qIdx = 0; qAnswers = {};
        if (sub) sub.textContent = `Pass: ${passPct}% · ${qList.length} questions`;
        buildDots();
        document.getElementById('quiz-result').style.display = 'none';
        document.getElementById('quiz-card').style.display = 'block';
        if (showAll) { document.getElementById('quiz-single-body').style.display = 'none'; document.getElementById('quiz-all-body').style.display = 'block'; renderAll(); }
        else { document.getElementById('quiz-all-body').style.display = 'none'; document.getElementById('quiz-single-body').style.display = 'block'; renderQ(); }
    } catch(e) { Swal.fire({title:'Error',text:'Could not load quiz.',icon:'error',background:'#161b22',color:'#e6edf3'}); closeQuiz(); }
}
function buildDots() {
    const dd = document.getElementById('q-dots'); if (!dd) return; dd.innerHTML = '';
    if (showAll) { dd.style.display='none'; return; }
    dd.style.display = 'flex';
    qList.forEach((_,i) => { const d=document.createElement('div'); d.className='q-dot'; d.id='qd-'+i; dd.appendChild(d); });
    updateDots();
}
function updateDots() {
    qList.forEach((q,i) => { const d=document.getElementById('qd-'+i); if (!d) return; d.className='q-dot'+(qAnswers[q.id]!==undefined?' ans':'')+(i===qIdx?' curr':''); });
}
const LETTERS = ['A','B','C','D','E'];
function renderQ() {
    const q = qList[qIdx];
    const qnum = document.getElementById('q-num'); if (qnum) qnum.textContent = `Question ${qIdx+1} of ${qList.length}`;
    const qt = document.getElementById('q-text'); if (qt) qt.textContent = q.question;
    const opts = document.getElementById('q-opts'); if (!opts) return; opts.innerHTML = '';
    q.options.forEach((opt, i) => {
        const d = document.createElement('div'); d.className = 'quiz-opt'+(qAnswers[q.id]===i?' sel':'');
        d.innerHTML = `<div class="opt-dot"></div><span class="opt-ltr">${LETTERS[i]}</span><span class="opt-text">${opt}</span>`;
        d.onclick = () => { qAnswers[q.id] = i; renderQ(); };
        opts.appendChild(d);
    });
    const prev = document.getElementById('q-prev'), next = document.getElementById('q-next'), sub = document.getElementById('q-submit');
    prev.style.visibility = qIdx === 0 ? 'hidden' : 'visible';
    const last = qIdx === qList.length - 1;
    next.style.display = last ? 'none' : 'inline-flex';
    sub.style.display  = last ? 'inline-flex' : 'none';
    updateDots();
}
function renderAll() {
    const body = document.getElementById('q-all-content'); if (!body) return; body.innerHTML = '';
    qList.forEach((q, qi) => {
        const block = document.createElement('div'); block.className = 'qall-q';
        block.innerHTML = `<div class="qall-q-text"><span class="qall-q-num">${qi+1}</span>${q.question} <small style="color:#484f58;font-weight:400;">(${q.marks} pt${q.marks>1?'s':''})</small></div>`;
        q.options.forEach((opt, i) => {
            const d = document.createElement('div'); d.className = 'quiz-opt'+(qAnswers[q.id]===i?' sel':'');
            d.innerHTML = `<div class="opt-dot"></div><span class="opt-ltr">${LETTERS[i]}</span><span class="opt-text">${opt}</span>`;
            d.onclick = () => { qAnswers[q.id] = i; renderAll(); updateAllCount(); };
            block.appendChild(d);
        });
        body.appendChild(block);
    });
    updateAllCount();
}
function updateAllCount() {
    const el = document.getElementById('q-all-count');
    if (el) el.textContent = Object.keys(qAnswers).length + '/' + qList.length + ' answered';
}
async function submitQuiz() {
    if (Object.keys(qAnswers).length < qList.length) {
        Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Answer all questions first',showConfirmButton:false,timer:2500,background:'#161b22',color:'#e6edf3'}); return;
    }
    Swal.fire({title:'Submitting...',didOpen:()=>Swal.showLoading(),background:'#161b22',color:'#e6edf3',allowOutsideClick:false});
    try {
        const r = await fetch(SUBMIT_URL, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({answers:qAnswers})});
        const d = await r.json(); Swal.close();
        if (d.success) showResult(d);
        else Swal.fire({title:'Error',text:'Submission failed.',icon:'error',background:'#161b22',color:'#e6edf3'});
    } catch { Swal.close(); Swal.fire({title:'Network error',text:'Please try again.',icon:'error',background:'#161b22',color:'#e6edf3'}); }
}
function showResult(d) {
    document.getElementById('quiz-card').style.display = 'none';
    const res = document.getElementById('quiz-result'); res.style.display = 'block';
    const ov = document.getElementById('quiz-overlay'); if (ov) ov.scrollTop = 0;
    const sc = Math.round(d.score), col = d.passed ? '#3fb950' : '#f85149';
    const c = 2 * Math.PI * 46;
    document.getElementById('result-ring').innerHTML = `
        <svg width="120" height="120" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="46" fill="none" stroke="#21262d" stroke-width="8"/>
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
        ? '<span style="display:inline-block;background:rgba(63,185,80,0.15);border:1px solid rgba(63,185,80,0.3);color:#3fb950;font-size:0.72rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:5px 14px;border-radius:20px;">🏆 PASSED</span>'
        : '<span style="display:inline-block;background:rgba(248,81,73,0.12);border:1px solid rgba(248,81,73,0.25);color:#f85149;font-size:0.72rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:5px 14px;border-radius:20px;">✗ NOT PASSED</span>';
    document.getElementById('result-title').textContent = d.passed ? 'Excellent Work!' : 'Keep Going!';
    document.getElementById('result-msg').innerHTML = d.passed
        ? `You scored <strong style="color:#3fb950;">${sc}%</strong> — well past the ${d.passing_percentage}% pass mark. Module complete! 🚀`
        : `You scored <strong style="color:#f85149;">${sc}%</strong>. You need <strong>${d.passing_percentage}%</strong> to pass. Review the material and try again.`;
    const retry = document.getElementById('result-retry');
    if (retry) retry.style.display = d.passed ? 'none' : 'inline-flex';
    const nextBtn   = document.getElementById('result-next-btn');
    const portalBtn = document.getElementById('result-portal-btn');
    if (d.passed) {
        confetti({particleCount:180,spread:90,origin:{y:0.55},colors:['#3fb950','#58a6ff','#f0883e']});
        if (NEXT_MODULE_URL && nextBtn) {
            nextBtn.href = NEXT_MODULE_URL;
            nextBtn.innerHTML = `<i class="ti ti-arrow-right" style="font-size:0.85rem;"></i> ${NEXT_MODULE_TITLE || 'Next Module'}`;
            nextBtn.style.display = 'inline-flex';
            if (portalBtn) portalBtn.classList.remove('primary'); // demote portal btn
            // Auto-navigate after 4 seconds
            let countdown = 4;
            const countEl = document.createElement('div');
            countEl.style.cssText = 'font-size:0.72rem;color:#484f58;margin-top:10px;';
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
function retakeQuiz() { qAnswers={}; qIdx=0; document.getElementById('quiz-result').style.display='none'; document.getElementById('quiz-card').style.display='block'; buildDots(); if(showAll){renderAll();}else{renderQ();} }

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

    // Mobile course nav toggles
    document.getElementById('cnav-toggle')?.addEventListener('click', toggleCourseNav);
    document.getElementById('cnav-toggle-bottom')?.addEventListener('click', toggleCourseNav);

    // Assessment btn
    document.getElementById('assess-btn')?.addEventListener('click', openQuiz);

    // Quiz nav
    document.getElementById('q-prev')?.addEventListener('click', () => { qIdx--; renderQ(); });
    document.getElementById('q-next')?.addEventListener('click', () => {
        if (qAnswers[qList[qIdx].id] === undefined) { Swal.fire({toast:true,position:'top-end',icon:'warning',title:'Select an answer',showConfirmButton:false,timer:2000,background:'#161b22',color:'#e6edf3'}); return; }
        qIdx++; renderQ();
    });
    document.getElementById('q-submit')?.addEventListener('click', submitQuiz);
    document.getElementById('q-all-submit')?.addEventListener('click', submitQuiz);

    // Milestone submit
    document.getElementById('ms-submit')?.addEventListener('click', () => {
        if (selMsIdx === curMilestone.correct) {
            confetti({particleCount:60,spread:60,origin:{y:0.7}});
            const s = document.getElementById('ms-submit');
            s.textContent = '✓ Correct!'; s.style.background = '#1f883d'; s.disabled = true;
            setTimeout(() => { document.getElementById('milestone-overlay').style.display='none'; playV(); }, 1400);
        } else {
            Swal.fire({title:'Not quite!',text:'Try again.',icon:'error',background:'#0d1117',color:'#e6edf3',confirmButtonColor:'#f85149'});
        }
    });

    // PDF controls
    document.getElementById('pdf-prev')?.addEventListener('click', () => pdfPage > 1 && renderPdfPage(pdfPage-1));
    document.getElementById('pdf-next')?.addEventListener('click', () => pdfPage < pdfTotal && renderPdfPage(pdfPage+1));
    document.getElementById('pdf-pi')?.addEventListener('change', e => { const n = parseInt(e.target.value); if (n>=1&&n<=pdfTotal) renderPdfPage(n); });
    document.addEventListener('keydown', e => {
        if (CTYPE==='catalog' && document.getElementById('quiz-overlay').style.display==='none') {
            if (e.key==='ArrowRight'||e.key==='ArrowDown') { e.preventDefault(); pdfPage<pdfTotal&&renderPdfPage(pdfPage+1); }
            if (e.key==='ArrowLeft'||e.key==='ArrowUp')   { e.preventDefault(); pdfPage>1&&renderPdfPage(pdfPage-1); }
        }
    });

    // Init by type
    if (CTYPE === 'catalog' && CURL) { initPdf(); initAi(); if (IS_COMPLETED) { markCompletedUI(); } else { startTimer(); } }
    else if (CTYPE === 'video') {
        initChapters();
        if (IS_COMPLETED) { markCompletedUI(); showAssessBtn(); }
        if (CURL.includes('youtube.com') || CURL.includes('youtu.be')) {
            const s = document.createElement('script'); s.src = 'https://www.youtube.com/iframe_api'; document.head.appendChild(s);
        } else { initLocalVideo(); }
    } else {
        // text modules AND catalog-without-URL both use startTimer()
        // startTimer() already handles IS_COMPLETED → markCompletedUI()
        startTimer();
    }
});
</script>
@endsection
