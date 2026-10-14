@extends('layouts.layoutMaster')

@section('title', 'Employee Training Program')

@section('page-style')
<style>
    .tp-page {
        --tp-primary: #0b5f68;
        --tp-primary-dark: #07464d;
        --tp-gradient: linear-gradient(120deg, #07464d 0%, #0b6b73 55%, #11a3ad 100%);
        --tp-border: #e3ebee;
        --tp-ink: #1e293b;
        --tp-muted: #64748b;
        --tp-soft: #f4f8f9;
        --tp-green: #16a34a;
        --tp-orange: #f59e0b;
        --tp-blue: #3b82f6;
        --tp-red: #ef4444;
        --tp-purple: #8b5cf6;
    }

    /* ── Hero ───────────────────────────────────────── */
    .tp-hero {
        background: var(--tp-gradient); border-radius: 18px; padding: 24px 28px; margin-bottom: 18px; color: #fff;
        position: relative; overflow: hidden; box-shadow: 0 12px 32px rgba(7, 70, 77, .22);
        display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(300px, 380px); gap: 26px; align-items: center;
    }
    .tp-hero::before { content: ''; position: absolute; right: -70px; top: -90px; width: 280px; height: 280px; border-radius: 50%; background: rgba(255,255,255,.06); pointer-events: none; }
    .tp-hero > * { position: relative; z-index: 1; }
    .tp-hero-badge {
        display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,.45); border-radius: 8px;
        padding: 4px 10px; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 12px;
    }
    .tp-hero h1 { font-size: 1.85rem; font-weight: 800; margin: 0 0 6px; color: #fff; letter-spacing: -.01em; }
    .tp-hero p { margin: 0 0 16px; color: rgba(255,255,255,.85); font-size: .92rem; }
    .tp-hero-meta { display: flex; flex-wrap: wrap; gap: 8px 24px; font-size: .82rem; }
    .tp-hero-meta span { display: inline-flex; align-items: center; gap: 7px; }
    .tp-hero-meta i { font-size: 1.05rem; opacity: .9; }
    .tp-ring-wrap { display: flex; align-items: center; gap: 18px; }
    .tp-ring { position: relative; width: 112px; height: 112px; flex-shrink: 0; }
    .tp-ring svg { transform: rotate(-90deg); }
    .tp-ring-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .tp-ring-center b { font-size: 1.55rem; font-weight: 800; line-height: 1; }
    .tp-ring-center span { font-size: .72rem; opacity: .85; margin-top: 3px; }
    .tp-ring-text b { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.1; }
    .tp-ring-text span { font-size: .8rem; opacity: .85; line-height: 1.3; display: block; }
    .tp-timeline { background: rgba(4, 38, 43, .55); border: 1px solid rgba(255,255,255,.12); border-radius: 14px; padding: 16px 18px; }
    .tp-timeline-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,.12); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
    .tp-timeline-title { font-weight: 700; font-size: .86rem; }
    .tp-timeline-dates { font-size: .8rem; opacity: .85; }
    .tp-timeline-pill { font-size: .7rem; font-weight: 700; border-radius: 8px; padding: 4px 12px; white-space: nowrap; background: #d1fae5; color: #065f46; }
    .tp-timeline-pill.tl-new { background: #e2e8f0; color: #334155; }
    .tp-timeline-pill.tl-prog { background: #ccfbf1; color: #0f766e; }
    .tp-timeline-track { flex: 1; height: 8px; border-radius: 8px; background: rgba(255,255,255,.18); overflow: hidden; }
    .tp-timeline-track > span { display: block; height: 100%; border-radius: 8px; background: #5eead4; }
    .tp-timeline-left { font-size: .8rem; white-space: nowrap; }

    /* ── Cards ──────────────────────────────────────── */
    .tp-card { background: #fff; border: 1px solid var(--tp-border); border-radius: 14px; box-shadow: 0 2px 10px rgba(15, 23, 42, .04); }
    .tp-card-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 18px 8px; }
    .tp-card-title { font-size: 1.05rem; font-weight: 800; color: var(--tp-ink); margin: 0; }
    .tp-card-link { font-size: .76rem; font-weight: 600; color: var(--tp-primary); text-decoration: none; white-space: nowrap; }
    .tp-card-link:hover { color: var(--tp-primary-dark); }
    .tp-card-body { padding: 8px 18px 18px; }

    /* ── KPI cards ──────────────────────────────────── */
    .tp-kpi { display: flex; gap: 12px; align-items: flex-start; padding: 16px; height: 100%; }
    .tp-kpi-icon { width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .tp-kpi-value { font-size: 1.45rem; font-weight: 800; color: var(--tp-ink); line-height: 1.1; }
    .tp-kpi-label { font-size: .8rem; color: var(--tp-ink); font-weight: 500; }
    .tp-kpi-sub { font-size: .7rem; color: var(--tp-muted); margin-top: 2px; }
    .tp-trend { display: inline-block; margin-top: 5px; font-size: .68rem; font-weight: 700; color: var(--tp-green); background: #f0fdf4; border-radius: 6px; padding: 1px 6px; }
    .tpc-teal { background: #e0f2f1; color: #0b6b73; } .tpc-green { background: #dcfce7; color: #16a34a; }
    .tpc-orange { background: #fef3c7; color: #d97706; } .tpc-red { background: #fee2e2; color: #dc2626; }
    .tpc-rose { background: #ffe4e6; color: #e11d48; } .tpc-purple { background: #ede9fe; color: #7c3aed; }
    .tpc-blue { background: #dbeafe; color: #2563eb; }

    /* ── Layout ─────────────────────────────────────── */
    .tp-grid { display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: 18px; align-items: start; }
    .tp-side { display: flex; flex-direction: column; gap: 18px; }

    /* ── Phases ─────────────────────────────────────── */
    .tp-btn-outline {
        display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 10px;
        border: 1px solid var(--tp-border); background: #fff; color: var(--tp-ink); font-size: .8rem; font-weight: 600;
    }
    .tp-btn-outline:hover { border-color: var(--tp-primary); color: var(--tp-primary); }
    .tp-btn-icon { height: 36px; width: 36px; border-radius: 10px; border: 1px solid var(--tp-border); background: #fff; color: var(--tp-ink); display: inline-flex; align-items: center; justify-content: center; }
    .tp-phase { border: 1px solid var(--tp-border); border-radius: 14px; margin-bottom: 14px; overflow: hidden; background: #fff; }
    .tp-phase:last-child { margin-bottom: 0; }
    .tp-phase.is-locked { background: #fafbfc; }
    .tp-phase-head { display: flex; align-items: center; gap: 14px; padding: 14px 16px; cursor: pointer; user-select: none; }
    .tp-phase-head:hover { background: #fafcfc; }
    .tp-phase-head:focus-visible { outline: 2px solid var(--tp-primary); outline-offset: -2px; }
    .tp-phase-num { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.15rem; color: #fff; flex-shrink: 0; }
    .tpn-1 { background: linear-gradient(135deg, #07464d, #0b7c86); }
    .tpn-2 { background: linear-gradient(135deg, #4338ca, #6366f1); }
    .tpn-3 { background: linear-gradient(135deg, #7c3aed, #a855f7); }
    .tpn-4 { background: linear-gradient(135deg, #ea580c, #f97316); }
    .tpn-5 { background: linear-gradient(135deg, #15803d, #22c55e); }
    .tpn-locked { background: #cbd5e1; color: #475569; }
    .tp-phase-info { flex: 1; min-width: 0; }
    .tp-phase-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--tp-muted); }
    .tp-optional { margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: #eef2ff; color: #4f46e5; font-size: .6rem; }
    .tp-phase-name { font-size: 1rem; font-weight: 800; color: var(--tp-ink); line-height: 1.3; }
    .tp-phase.is-locked .tp-phase-name { color: #475569; }
    .tp-phase-desc { font-size: .78rem; color: var(--tp-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tp-phase-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .tp-count { font-size: .76rem; font-weight: 700; color: #b45309; background: #fef3c7; border-radius: 6px; padding: 3px 9px; white-space: nowrap; }
    .tp-phase.is-locked .tp-count { background: transparent; color: var(--tp-muted); }
    .tp-chevron { font-size: 1.3rem; color: var(--tp-muted); transition: transform .25s ease; }
    .tp-chevron.open { transform: rotate(180deg); }
    .tp-phase-progress { display: flex; align-items: center; gap: 12px; padding: 0 16px 12px; }
    .tp-phase-progress b { font-size: .78rem; color: var(--tp-ink); min-width: 38px; text-align: right; }
    .tp-track { flex: 1; height: 7px; border-radius: 8px; background: #e8eef0; overflow: hidden; display: block; }
    .tp-track > span { display: block; height: 100%; border-radius: 8px; }
    .tpb-1 { background: #0b7c86; } .tpb-2 { background: #6366f1; } .tpb-3 { background: #a855f7; } .tpb-4 { background: #f97316; } .tpb-5 { background: #22c55e; }
    .tpb-done { background: #22c55e; } .tpb-fail { background: #ef4444; }
    .tp-phase-body { border-top: 1px solid #eef3f4; }
    .tp-locked-msg { display: flex; align-items: center; gap: 12px; padding: 14px 16px; font-size: .82rem; color: var(--tp-muted); }
    .tp-locked-msg i { font-size: 1.3rem; color: #94a3b8; }
    .tp-locked-msg strong { color: var(--tp-ink); }
    .tp-lib-note { padding: 10px 16px 4px; color: var(--tp-muted); font-size: .8rem; }

    /* ── Pills ──────────────────────────────────────── */
    .tp-pill { display: inline-flex; align-items: center; gap: 4px; font-size: .7rem; font-weight: 700; padding: 4px 11px; border-radius: 6px; white-space: nowrap; }
    .tp-pill-done { background: #dcfce7; color: #15803d; }
    .tp-pill-prog { background: #fef3c7; color: #b45309; }
    .tp-pill-new { background: #f1f5f9; color: #64748b; }
    .tp-pill-fail { background: #fee2e2; color: #b91c1c; }
    .tp-pill-locked { background: #f1f5f9; color: #94a3b8; }

    /* ── Module rows ────────────────────────────────── */
    .tp-mod { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f1f5f6; color: var(--tp-ink); text-decoration: none; transition: background .15s; }
    .tp-mod:last-child { border-bottom: none; }
    .tp-mod:hover { background: #f8fcfc; color: var(--tp-ink); }
    .tp-mod.is-locked { cursor: not-allowed; pointer-events: none; }
    .tp-mod.is-locked .tp-mod-text, .tp-mod.is-locked .tp-mod-type { opacity: .55; }
    .tp-mod-state { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
    .st-completed { background: #22c55e; color: #fff; }
    .st-failed { background: #ef4444; color: #fff; }
    .st-in_progress { background: #fef3c7; color: #d97706; }
    .st-available { background: #ccfbf1; color: #0f766e; }
    .st-locked { background: transparent; color: #94a3b8; font-size: 1.15rem; }
    .tp-mod-type { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; background: #eef4ff; color: #3b82f6; }
    .tp-mod-type.mt-video { background: #fff7e6; color: #d97706; }
    .tp-mod-type.mt-text { background: #ecfdf5; color: #059669; }
    .tp-mod-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .tp-mod-title { font-size: .86rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tp-mod-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 2px; font-size: .7rem; color: #94a3b8; }
    .tp-mod-meta span { display: inline-flex; align-items: center; gap: 3px; }
    .tp-mod-bar { width: 110px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
    .tp-mod-pct { width: 42px; flex-shrink: 0; font-size: .78rem; font-weight: 700; text-align: right; color: var(--tp-ink); }
    .tp-mod-pct.pct-done { color: #15803d; } .tp-mod-pct.pct-fail { color: #b91c1c; }
    .tp-mod-status { width: 96px; flex-shrink: 0; display: flex; justify-content: flex-end; }
    .tp-mod-go { font-size: 1.15rem; color: #94a3b8; width: 18px; flex-shrink: 0; }
    .tp-go { display: block; width: 100%; text-align: center; border-radius: 999px; padding: 6px 0; font-size: .76rem; font-weight: 700; color: #fff; background: #2563eb; }
    .tp-mod:hover .tp-go { filter: brightness(1.08); }
    .tp-go.go-resume { background: #f59e0b; }
    .tp-go.go-retry { background: #ef4444; }
    .tp-mod-lock { color: #94a3b8; font-size: 1.05rem; }

    .tp-done-banner { display: flex; align-items: center; gap: 14px; background: linear-gradient(120deg, #065f46, #10b981); color: #fff; border-radius: 14px; padding: 16px 18px; margin-bottom: 14px; }
    .tp-done-banner i { font-size: 2rem; }
    .tp-done-banner b { display: block; font-size: 1rem; }
    .tp-done-banner span { font-size: .82rem; opacity: .9; }

    /* ── Right column ───────────────────────────────── */
    .tp-donut-row { display: flex; align-items: center; gap: 22px; }
    .tp-donut { width: 132px; height: 132px; border-radius: 50%; position: relative; flex-shrink: 0; }
    .tp-donut::after { content: ''; position: absolute; inset: 17px; background: #fff; border-radius: 50%; }
    .tp-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1; }
    .tp-donut-center b { font-size: 1.5rem; font-weight: 800; color: var(--tp-ink); line-height: 1; }
    .tp-donut-center span { font-size: .74rem; color: var(--tp-muted); margin-top: 3px; }
    .tp-legend { list-style: none; padding: 0; margin: 0; flex: 1; font-size: .8rem; }
    .tp-legend li { display: flex; align-items: center; gap: 8px; padding: 5px 0; color: var(--tp-ink); }
    .tp-legend .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .tp-legend .n { margin-left: auto; font-weight: 700; }
    .tp-legend .p { color: var(--tp-muted); width: 54px; text-align: right; font-weight: 600; }
    .tp-minis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 16px 0; }
    .tp-mini { border: 1px solid var(--tp-border); border-radius: 12px; padding: 10px 12px; display: flex; align-items: center; gap: 10px; min-width: 0; }
    .tp-mini i { font-size: 1.35rem; color: var(--tp-primary); }
    .tp-mini b { display: block; font-size: 1.05rem; font-weight: 800; color: var(--tp-ink); line-height: 1.1; }
    .tp-mini span { font-size: .7rem; color: var(--tp-muted); }
    .tp-cta { display: flex; align-items: center; justify-content: center; gap: 8px; height: 46px; border-radius: 12px; background: var(--tp-primary); color: #fff; font-weight: 700; font-size: .9rem; text-decoration: none; }
    .tp-cta:hover { background: var(--tp-primary-dark); color: #fff; }
    .tp-cta-done { background: #dcfce7; color: #15803d; pointer-events: none; }
    .tp-cta-alt { display: flex; align-items: center; justify-content: center; gap: 6px; height: 40px; margin-top: 10px; border-radius: 12px; border: 1px solid var(--tp-border); color: var(--tp-ink); font-weight: 600; font-size: .82rem; text-decoration: none; }
    .tp-cta-alt:hover { border-color: var(--tp-primary); color: var(--tp-primary); }
    .tp-pb-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f6; font-size: .78rem; color: var(--tp-ink); }
    .tp-pb-row:last-child { border-bottom: none; }
    .tp-pb-row .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .tp-pb-name { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
    .tp-pb-count { width: 38px; text-align: right; color: var(--tp-muted); font-weight: 600; flex-shrink: 0; }
    .tp-pb-row .tp-track { flex: 0 0 64px; }
    .tp-pb-pct { width: 36px; text-align: right; font-weight: 700; flex-shrink: 0; }
    .tp-act { display: flex; align-items: flex-start; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f6; font-size: .8rem; color: var(--tp-ink); }
    .tp-act:last-child { border-bottom: none; }
    .tp-act-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
    .tp-act .when { margin-left: auto; font-size: .7rem; color: var(--tp-muted); white-space: nowrap; padding-left: 8px; }
    .tp-viewing { font-size: .8rem; color: var(--tp-muted); text-align: center; padding: 14px; }

    @media (max-width: 1399.98px) {
        .tp-hero { grid-template-columns: minmax(0, 1fr) auto; }
        .tp-timeline { grid-column: 1 / -1; }
    }
    @media (max-width: 1199.98px) {
        .tp-grid { grid-template-columns: minmax(0, 1fr); }
        .tp-side { order: -1; }
    }
    @media (max-width: 767.98px) {
        .tp-hero { grid-template-columns: minmax(0, 1fr); padding: 20px; }
        .tp-hero h1 { font-size: 1.35rem; }
        .tp-mod-bar, .tp-mod-pct, .tp-mod-go, .tp-count { display: none; }
        .tp-mod-status { width: auto; }
        .tp-phase-desc { white-space: normal; }
        .tp-pb-row .tp-track { display: none; }
    }
</style>
@endsection

@section('content')

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

    // ── Display figures (read-only; nothing here changes what unlocks) ──
    $isSelf = $user->id === auth()->id();

    // The compulsory phases gate the account - the hero ring is their progress,
    // the same measure as $progressPercentage and the lock banner.
    $compIds   = config('training.onboarding_phase_ids', [1, 2]);
    $compMods  = $phases->filter(fn($p) => in_array($p->id, $compIds, true))->flatMap(fn($p) => $p->modules);
    $compTotal = $compMods->count();
    $compDone  = $compMods->filter(fn($m) => optional($m->userProgress->first())->status === 'completed')->count();
    // Real per-module count, never the separate training_status cache column:
    // that flag gets set to 'completed' both as a side effect of
    // is_training_required=false at account creation (0 modules attempted
    // still read "Complete") and by HR's manual approval override (a
    // partially-done account, e.g. 4 of 11, also read "Complete") - both
    // confirmed live and silently disagreeing with $compDone/$compTotal
    // computed right above, which is why this reads directly off them.
    $isDone = $compTotal > 0 && $compDone >= $compTotal;

    // Every module, by where it stands: the cards and the donut.
    $inProgMods = 0; $failedMods = 0; $doneThisMonth = 0; $firstAt = null; $lastDoneAt = null; $events = collect();
    foreach ($phases as $ph) {
        foreach ($ph->modules as $mod) {
            $prog = $mod->userProgress->first();
            if (!$prog) continue;
            if ($prog->status === 'in_progress') $inProgMods++;
            if ($prog->status === 'failed') $failedMods++;
            if ($prog->status === 'completed' && $prog->completed_at) {
                if ($prog->completed_at->gte(now()->startOfMonth())) $doneThisMonth++;
                if (!$lastDoneAt || $prog->completed_at->gt($lastDoneAt)) $lastDoneAt = $prog->completed_at;
            }
            $began = $prog->started_at ?? $prog->created_at;
            if ($began && (!$firstAt || $began->lt($firstAt))) $firstAt = $began;
            if (in_array($prog->status, ['completed', 'failed', 'in_progress'], true)) {
                $at = $prog->status === 'completed' ? ($prog->completed_at ?? $prog->updated_at) : ($prog->updated_at ?? $began);
                if ($at) $events->push(['at' => $at, 'kind' => $prog->status, 'title' => $mod->title, 'score' => $prog->assessment_score]);
            }
        }
    }
    foreach (($libraryVideos ?? collect()) as $v) {
        $vv = $videoViews->get($v->id);
        $at = $vv ? ($vv->completed_at ?? $vv->started_at ?? $vv->updated_at) : null;
        if ($at) $events->push(['at' => $at, 'kind' => $vv->completed_at ? 'watched' : 'watching', 'title' => $v->title, 'score' => null]);
    }
    $events = $events->sortByDesc(fn($e) => $e['at']->timestamp)->take(5)->values();

    $notStartedMods = max(0, $totalMods - $doneMods - $inProgMods - $failedMods);
    $overallPct = $totalMods > 0 ? (int) round($doneMods / $totalMods * 100) : 0;
    $modPct = fn($n) => $totalMods > 0 ? round($n / $totalMods * 100, 1) : 0;
    $modsLeft = max(0, $totalMods - $doneMods);
    $journey = $isDone || ($totalMods > 0 && $modsLeft === 0) ? 'Completed' : ($hasAnyProgress ? 'In Progress' : 'Not Started');
    $firstName = $user->first_name ?: $user->name;

    $d1 = $modPct($doneMods); $d2 = $d1 + $modPct($inProgMods); $d3 = $d2 + $modPct($failedMods);
    $donut = $totalMods > 0
        ? "conic-gradient(#22c55e 0 {$d1}%, #f59e0b {$d1}% {$d2}%, #ef4444 {$d2}% {$d3}%, #3b82f6 {$d3}% 100%)"
        : 'conic-gradient(#e2e8f0 0 100%)';

    $phaseRows = [];   // filled by the phase loop, so the breakdown matches the cards
@endphp

<div class="tp-page">

    {{-- Flash error --}}
    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bx bx-error-circle fs-5"></i> {{ session('error') }}
        </div>
    @endif

    {{-- ── Hero ─────────────────────────────────────────────────── --}}
    <div class="tp-hero">
        <div>
            <span class="tp-hero-badge"><i class="bx bx-certification"></i> Employee Training Program</span>
            <h1>{{ $user->name }}'s Training</h1>
            <p>Master your role through structured phases &mdash; each building on the last.</p>
            <div class="tp-hero-meta">
                <span><i class="bx bx-layer"></i> {{ $phases->count() }} Phases</span>
                <span><i class="bx bx-book-open"></i> {{ $totalMods }} Modules</span>
                <span><i class="bx bx-time-five"></i> {{ round($estTotalMins / 60, 1) }}h total</span>
                @if($avgScore > 0)
                    <span><i class="bx bx-trophy"></i> {{ $avgScore }}% avg score</span>
                @endif
            </div>
        </div>

        <div class="tp-ring-wrap" title="Compulsory modules passed - these unlock the account">
            @php $c = round(2 * M_PI * 48, 2); @endphp
            <div class="tp-ring">
                <svg width="112" height="112" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="48" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="10"/>
                    <circle cx="60" cy="60" r="48" fill="none" stroke="#5eead4" stroke-width="10"
                        stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ round($c * (1 - $progressPercentage / 100), 2) }}"
                        stroke-linecap="round" style="transition:stroke-dashoffset 1.5s ease;"/>
                </svg>
                <div class="tp-ring-center"><b>{{ $progressPercentage }}%</b><span>Completed</span></div>
            </div>
            <div class="tp-ring-text">
                <b>{{ $compDone }}/{{ $compTotal }}</b>
                <span>Compulsory<br>Modules Completed</span>
            </div>
        </div>

        <div class="tp-timeline">
            <div class="d-flex align-items-start gap-3">
                <span class="tp-timeline-icon"><i class="bx bx-calendar"></i></span>
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="tp-timeline-title">Training Timeline</div>
                    <div class="tp-timeline-dates">
                        @if($firstAt)
                            {{ $firstAt->format('d M Y') }} &ndash; {{ $journey === 'Completed' && $lastDoneAt ? $lastDoneAt->format('d M Y') : 'Present' }}
                        @else
                            Not started yet
                        @endif
                    </div>
                </div>
                <span class="tp-timeline-pill {{ $journey === 'Completed' ? '' : ($journey === 'In Progress' ? 'tl-prog' : 'tl-new') }}">{{ $journey }}</span>
            </div>
            <div class="d-flex align-items-center gap-3 mt-3">
                <div class="tp-timeline-track"><span style="width:{{ $overallPct }}%"></span></div>
                <b class="tp-timeline-left">{{ $modsLeft > 0 ? $modsLeft . ' ' . \Illuminate\Support\Str::plural('module', $modsLeft) . ' left' : 'All modules done' }}</b>
            </div>
        </div>
    </div>

    {{-- ── KPI cards: this person's modules ───────────────────── --}}
    <div class="row g-3 mb-3">
        @php
            $kpis = [
                ['icon' => 'bx bx-book-open', 'bg' => 'tpc-teal', 'value' => $totalMods, 'label' => 'Total Modules', 'sub' => $phases->count() . ' phases', 'trend' => null],
                ['icon' => 'bx bx-check-circle', 'bg' => 'tpc-green', 'value' => $doneMods, 'label' => 'Completed', 'sub' => $modPct($doneMods) . '% of modules',
                 'trend' => $doneThisMonth > 0 ? '+' . $doneThisMonth . ' this month' : null],
                ['icon' => 'bx bx-play-circle', 'bg' => 'tpc-orange', 'value' => $inProgMods, 'label' => 'In Progress', 'sub' => $modPct($inProgMods) . '% of modules', 'trend' => null],
                ['icon' => 'bx bx-time-five', 'bg' => 'tpc-rose', 'value' => $notStartedMods, 'label' => 'Not Started', 'sub' => $modPct($notStartedMods) . '% of modules', 'trend' => null],
                ['icon' => 'bx bx-error', 'bg' => 'tpc-red', 'value' => $failedMods, 'label' => 'Needs Retry', 'sub' => $modPct($failedMods) . '% of modules', 'trend' => null],
                ['icon' => 'bx bx-bar-chart-alt-2', 'bg' => 'tpc-purple', 'value' => $avgScore > 0 ? $avgScore . '%' : '—', 'label' => 'Average Score',
                 'sub' => $scoreCount > 0 ? 'across ' . $scoreCount . ' ' . \Illuminate\Support\Str::plural('assessment', $scoreCount) : 'no assessments yet', 'trend' => null],
            ];
        @endphp
        @foreach($kpis as $k)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="tp-card tp-kpi">
                    <div class="tp-kpi-icon {{ $k['bg'] }}"><i class="{{ $k['icon'] }}"></i></div>
                    <div style="min-width:0;">
                        <div class="tp-kpi-value">{{ $k['value'] }}</div>
                        <div class="tp-kpi-label">{{ $k['label'] }}</div>
                        <div class="tp-kpi-sub">{{ $k['sub'] }}</div>
                        @if($k['trend'])<span class="tp-trend">↑ {{ $k['trend'] }}</span>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="tp-grid">

        {{-- ── LEFT: phases and modules ─────────────────────────── --}}
        <div class="tp-card" id="tpPhases">
            <div class="tp-card-head">
                <h5 class="tp-card-title">Training Phases &amp; Modules</h5>
                <div class="d-flex align-items-center gap-2">
                    @if($phases->isNotEmpty())
                        <button type="button" class="tp-btn-outline" id="tpExpandAll"><i class="bx bx-expand-alt"></i> <span>Expand All</span></button>
                    @endif
                    @if($isSelf || ($resultsLink ?? null))
                        <div class="dropdown">
                            <button type="button" class="tp-btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More options"><i class="bx bx-dots-vertical-rounded"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                {{-- Your own results; the results dashboard is admin/HR only. --}}
                                @if($isSelf)
                                    <li><a class="dropdown-item" href="{{ route('training.report-card', auth()->id()) }}"><i class="bx bx-certification me-2"></i>My Results</a></li>
                                @endif
                                @if($resultsLink ?? null)
                                    <li><a class="dropdown-item" href="{{ route('training.activity') }}"><i class="bx bx-bar-chart-alt-2 me-2"></i>{{ $resultsLink }} Dashboard</a></li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
            <div class="tp-card-body">

                @if($isDone)
                    <div class="tp-done-banner">
                        <i class="bx bx-trophy"></i>
                        <div><b>Training Complete!</b><span>You've mastered all phases. Welcome to the team!</span></div>
                    </div>
                @endif

                @foreach($phases as $phaseIdx => $phase)
                @php
                    // Learn @ Hi Tech lists Digital Library videos live; it is complete
                    // once $videosRequired of them are watched (any of them).
                    $isLibraryPhase = in_array($phase->id, $libraryPhaseIds ?? [], true) && ($libraryVideos ?? collect())->isNotEmpty();
                    if ($isLibraryPhase) {
                        $libWatched = $libraryVideos->filter(fn($v) => optional($videoViews->get($v->id))->completed_at)->count();
                        $pTotalMods = $videosRequired;
                        $pDoneMods  = min($libWatched, $videosRequired);
                    } else {
                        $pTotalMods = $phase->modules->count();
                        $pDoneMods  = $phase->modules->filter(fn($m) => optional($m->userProgress->first())->status === 'completed')->count();
                    }
                    $pPct       = $pTotalMods > 0 ? round($pDoneMods / $pTotalMods * 100) : 0;
                    $ci         = (($phase->order - 1) % 5) + 1;

                    // Phase accessibility: all previous phases must be 100% - except the
                    // Learn @ Hi Tech videos, which open once the prerequisite phases
                    // (1 and 2) are done, whatever sits between.
                    $isAccessible = true;
                    $libraryBlocker = $isLibraryPhase ? \App\Http\Controllers\tenant\TrainingController::libraryPrerequisiteUndone($user->id) : null;
                    if ($isLibraryPhase) {
                        $isAccessible = $libraryBlocker === null;
                    } elseif ($phaseIdx > 0) {
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

                    [$phaseLabel, $phasePill] = $pPct === 100 ? ['Completed', 'tp-pill-done']
                        : (!$isAccessible ? ['Locked', 'tp-pill-locked']
                        : ($pPct > 0 ? ['In Progress', 'tp-pill-prog'] : ['Not Started', 'tp-pill-new']));
                    $phaseColors = ['#0b7c86', '#6366f1', '#a855f7', '#f97316', '#22c55e'];
                    $phaseRows[] = ['title' => $phase->title, 'done' => $pDoneMods, 'total' => $pTotalMods, 'pct' => $pPct,
                        'label' => $phaseLabel, 'pill' => $phasePill, 'color' => $isAccessible ? ($phaseColors[$ci - 1] ?? $phaseColors[0]) : '#94a3b8'];
                @endphp

                <div class="tp-phase {{ !$isAccessible ? 'is-locked' : '' }}">
                    <div class="tp-phase-head" id="ph-header-{{ $phase->id }}" data-phase="{{ $phase->id }}" role="button" tabindex="0" aria-expanded="{{ $startOpen ? 'true' : 'false' }}" aria-controls="phase-{{ $phase->id }}">
                        <div class="tp-phase-num tpn-{{ $isAccessible ? $ci : 'locked' }}">{{ str_pad($phase->order, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="tp-phase-info">
                            <div class="tp-phase-label">Phase {{ $phase->order }}
                                @unless(in_array($phase->id, config('training.onboarding_phase_ids', [1, 2]), true))
                                    <span class="tp-optional">Optional</span>
                                @endunless
                            </div>
                            <div class="tp-phase-name">{{ $phase->title }}</div>
                            @if($phase->description)
                                <div class="tp-phase-desc">{{ $phase->description }}</div>
                            @endif
                        </div>
                        <div class="tp-phase-right">
                            <span class="tp-pill {{ $phasePill }}">@if(!$isAccessible && $pPct !== 100)<i class="bx bx-lock-alt"></i>@endif{{ $phaseLabel }}</span>
                            <span class="tp-count">{{ $pDoneMods }}/{{ $pTotalMods }}@if($isLibraryPhase) watched @endif</span>
                            <i class="bx bx-chevron-down tp-chevron {{ $startOpen ? 'open' : '' }}" id="chevron-{{ $phase->id }}"></i>
                        </div>
                    </div>

                    @if($isAccessible)
                        <div class="tp-phase-progress">
                            <span class="tp-track"><span class="tpb-{{ $ci }}" style="width:{{ $pPct }}%;"></span></span>
                            <b>{{ $pPct }}%</b>
                        </div>
                    @endif

                    <div class="tp-phase-body" id="phase-{{ $phase->id }}" style="{{ $startOpen ? '' : 'display:none;' }}">

                        @if(!$isAccessible)
                            <div class="tp-locked-msg">
                                <i class="bx bx-lock-alt"></i>
                                @if($isLibraryPhase && $libraryBlocker)
                                    <span>Complete <strong>{{ $libraryBlocker->phase->title }}</strong> to unlock these videos.</span>
                                @else
                                    <span>Complete <strong>Phase {{ $phase->order - 1 }}</strong> to unlock this phase.</span>
                                @endif
                            </div>
                        @elseif($isLibraryPhase)
                            <div class="tp-lib-note">
                                Watch any <strong>{{ $videosRequired }}</strong> of these Learn @ Hi Tech sessions to complete this phase
                                ({{ config('training.library_video_min_minutes', 20) }} min minimum each). New sessions appear here as they are added to the Digital Library.
                            </div>
                            @foreach($libraryVideos as $video)
                            @php
                                $vv      = $videoViews->get($video->id);
                                $vStatus = optional($vv)->completed_at ? 'completed' : ($vv ? 'in_progress' : 'available');
                            @endphp
                            <a href="{{ route('training.library-video.show', $video->id) }}" class="tp-mod">
                                <span class="tp-mod-state st-{{ $vStatus }}">
                                    <i class="bx {{ $vStatus === 'completed' ? 'bx-check' : ($vStatus === 'in_progress' ? 'bx-play' : 'bx-right-arrow-alt') }}"></i>
                                </span>
                                <span class="tp-mod-type mt-video"><i class="bx bx-play-circle"></i></span>
                                <span class="tp-mod-text">
                                    <span class="tp-mod-title">{{ $video->title }}</span>
                                    <span class="tp-mod-meta">
                                        <span><i class="bx bx-time-five"></i> {{ config('training.library_video_min_minutes', 20) }} min</span>
                                        <span><i class="bx bx-video"></i> Video</span>
                                        @if($video->session_date)
                                            <span><i class="bx bx-calendar"></i> {{ \Carbon\Carbon::parse($video->session_date)->format('d M Y') }}</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="tp-mod-bar">
                                    @if($vStatus === 'completed')
                                        <span class="tp-track"><span class="tpb-done" style="width:100%;"></span></span>
                                    @elseif($vStatus === 'in_progress')
                                        <span class="tp-go go-resume">Resume</span>
                                    @else
                                        <span class="tp-go">Watch</span>
                                    @endif
                                </span>
                                <span class="tp-mod-pct {{ $vStatus === 'completed' ? 'pct-done' : '' }}">{{ $vStatus === 'completed' ? '100%' : '–' }}</span>
                                <span class="tp-mod-status">
                                    <span class="tp-pill {{ $vStatus === 'completed' ? 'tp-pill-done' : ($vStatus === 'in_progress' ? 'tp-pill-prog' : 'tp-pill-new') }}">{{ $vStatus === 'completed' ? 'Watched' : ($vStatus === 'in_progress' ? 'In Progress' : 'Not Started') }}</span>
                                </span>
                                <i class="bx bx-chevron-right tp-mod-go"></i>
                            </a>
                            @endforeach
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
                                $stateKey = in_array($status, ['completed', 'in_progress', 'available', 'failed'], true) ? $status : 'locked';
                                $stateIcon = ['completed' => 'bx-check', 'in_progress' => 'bx-play', 'available' => 'bx-right-arrow-alt', 'failed' => 'bx-x', 'locked' => 'bx-lock-alt'][$stateKey];
                                [$typeClass, $typeIcon] = match($module->content_type) {
                                    'video'   => ['mt-video', 'bx-play-circle'],
                                    'catalog' => ['', 'bx-file'],
                                    default   => ['mt-text', 'bx-book-content'],
                                };
                                $score = $prog && $prog->assessment_score > 0 ? round($prog->assessment_score) : null;
                                [$statusLabel, $statusPill] = [
                                    'completed'   => ['Completed', 'tp-pill-done'],
                                    'in_progress' => ['In Progress', 'tp-pill-prog'],
                                    'available'   => ['Not Started', 'tp-pill-new'],
                                    'failed'      => ['Failed', 'tp-pill-fail'],
                                    'locked'      => ['Locked', 'tp-pill-locked'],
                                ][$stateKey];
                            @endphp

                            <a href="{{ $href }}" class="tp-mod {{ $isClickable ? '' : 'is-locked' }}" @if(!$isClickable) tabindex="-1" aria-disabled="true" @endif>
                                <span class="tp-mod-state st-{{ $stateKey }}"><i class="bx {{ $stateIcon }}"></i></span>
                                <span class="tp-mod-type {{ $typeClass }}"><i class="bx {{ $typeIcon }}"></i></span>
                                <span class="tp-mod-text">
                                    <span class="tp-mod-title">{{ $module->title }}</span>
                                    <span class="tp-mod-meta">
                                        <span><i class="bx bx-time-five"></i> {{ $module->estimated_time_minutes }} min</span>
                                        {{-- Same wording the admin Content Type picker uses. --}}
                                        <span><i class="bx bx-paperclip"></i> {{ ['policy' => 'Text', 'catalog' => 'PDF', 'video' => 'Video'][$module->content_type] ?? ucfirst($module->content_type ?? 'reading') }}</span>
                                        @if($module->is_assessment_required ?? true)
                                            <span><i class="bx bx-clipboard"></i> Quiz</span>
                                        @endif
                                        @if($prog && $prog->attempts > 0)
                                            <span><i class="bx bx-revision"></i> {{ $prog->attempts }} {{ $prog->attempts == 1 ? 'Try' : 'Tries' }}</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="tp-mod-bar">
                                    @if($status === 'completed')
                                        <span class="tp-track" title="{{ $score !== null ? 'Assessment score' : 'Completed' }}"><span class="tpb-done" style="width:{{ $score ?? 100 }}%;"></span></span>
                                    @elseif($status === 'available')
                                        <span class="tp-go">Start</span>
                                    @elseif($status === 'in_progress')
                                        <span class="tp-go go-resume">Resume</span>
                                    @elseif($status === 'failed')
                                        <span class="tp-go go-retry">Retry</span>
                                    @else
                                        <i class="bx bx-lock-alt tp-mod-lock"></i>
                                    @endif
                                </span>
                                <span class="tp-mod-pct {{ $status === 'completed' ? 'pct-done' : ($status === 'failed' ? 'pct-fail' : '') }}">
                                    @if($score !== null){{ $score }}%@elseif($status === 'completed')100%@elseif($status === 'locked')@else–@endif
                                </span>
                                <span class="tp-mod-status"><span class="tp-pill {{ $statusPill }}">@if($stateKey === 'locked')<i class="bx bx-lock-alt"></i>@endif{{ $statusLabel }}</span></span>
                                <i class="bx bx-chevron-right tp-mod-go" @if(!$isClickable) style="visibility:hidden;" @endif></i>
                            </a>

                            @empty
                                <div class="text-center text-muted py-4" style="font-size:.82rem;">
                                    <i class="bx bx-archive d-block mb-2" style="font-size:1.8rem;opacity:.35;"></i>
                                    No modules yet.
                                </div>
                            @endforelse
                        @endif
                    </div>
                </div>
                @endforeach

                @if($phases->isEmpty())
                    <div class="text-center py-5">
                        <i class="bx bx-book-open d-block mb-3" style="font-size:3rem;opacity:.2;"></i>
                        <h5 class="text-muted">No training content yet</h5>
                        <p class="text-muted mb-0" style="font-size:.85rem;">Training phases will appear here once added by your HR team.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── RIGHT: progress, breakdown, activity ─────────────── --}}
        <aside class="tp-side">

            <div class="tp-card">
                <div class="tp-card-head"><h5 class="tp-card-title">{{ $isSelf ? 'Your Training Progress' : $firstName . "'s Progress" }}</h5></div>
                <div class="tp-card-body">
                    <div class="tp-donut-row">
                        <div class="tp-donut" style="background:{{ $donut }};">
                            <div class="tp-donut-center"><b>{{ $overallPct }}%</b><span>Completed</span></div>
                        </div>
                        <ul class="tp-legend">
                            <li><span class="dot" style="background:#22c55e"></span>Completed <span class="n">{{ $doneMods }}</span><span class="p">({{ $modPct($doneMods) }}%)</span></li>
                            <li><span class="dot" style="background:#f59e0b"></span>In Progress <span class="n">{{ $inProgMods }}</span><span class="p">({{ $modPct($inProgMods) }}%)</span></li>
                            <li><span class="dot" style="background:#3b82f6"></span>Not Started <span class="n">{{ $notStartedMods }}</span><span class="p">({{ $modPct($notStartedMods) }}%)</span></li>
                            <li><span class="dot" style="background:#ef4444"></span>Needs Retry <span class="n">{{ $failedMods }}</span><span class="p">({{ $modPct($failedMods) }}%)</span></li>
                        </ul>
                    </div>
                    <div class="tp-minis">
                        <div class="tp-mini"><i class="bx bx-book"></i><div><b>{{ $phases->count() }}</b><span>Phases</span></div></div>
                        <div class="tp-mini"><i class="bx bx-file"></i><div><b>{{ $totalMods }}</b><span>Modules</span></div></div>
                        <div class="tp-mini"><i class="bx bx-time-five"></i><div><b>{{ round($estTotalMins / 60, 1) }}h</b><span>Total Duration</span></div></div>
                    </div>

                    @if($nextModId && !$isDone && $isSelf)
                        <a href="{{ route('training.module.show', $nextModId) }}" class="tp-cta" title="{{ $nextModName }}">
                            {{ $hasAnyProgress ? 'Continue Learning' : 'Start Learning' }} <i class="bx bx-right-arrow-alt fs-5"></i>
                        </a>
                    @elseif($isDone)
                        <div class="tp-cta tp-cta-done"><i class="bx bx-trophy"></i> Training Complete!</div>
                    @endif
                    @if($isSelf)
                        <a href="{{ route('training.report-card', auth()->id()) }}" class="tp-cta-alt" title="Your assessment results"><i class="bx bx-certification"></i> My Results</a>
                    @endif
                </div>
            </div>

            <div class="tp-card">
                <div class="tp-card-head">
                    <h5 class="tp-card-title">Phase Breakdown</h5>
                    <a href="#tpPhases" class="tp-card-link">View Details →</a>
                </div>
                <div class="tp-card-body">
                    @forelse($phaseRows as $r)
                        <div class="tp-pb-row">
                            <span class="dot" style="background:{{ $r['color'] }}"></span>
                            <span class="tp-pb-name" title="{{ $r['title'] }}">{{ $r['title'] }}</span>
                            <span class="tp-pb-count">{{ $r['done'] }}/{{ $r['total'] }}</span>
                            <span class="tp-track"><span style="width:{{ $r['pct'] }}%;background:{{ $r['color'] }};"></span></span>
                            <span class="tp-pb-pct">{{ $r['pct'] }}%</span>
                            <span class="tp-pill {{ $r['pill'] }}">@if($r['label'] === 'Locked')<i class="bx bx-lock-alt"></i>@endif{{ $r['label'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-center py-3 mb-0" style="font-size:.84rem;">No phases yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="tp-card">
                <div class="tp-card-head">
                    <h5 class="tp-card-title">Recent Training Activity</h5>
                    <a href="{{ route('training.report-card', $user->id) }}" class="tp-card-link">View All →</a>
                </div>
                <div class="tp-card-body">
                    @forelse($events as $ev)
                        @php
                            [$evIcon, $evTone] = [
                                'completed'   => ['bx-check-circle', 'tpc-green'],
                                'failed'      => ['bx-x-circle', 'tpc-red'],
                                'in_progress' => ['bx-play-circle', 'tpc-blue'],
                                'watched'     => ['bx-video', 'tpc-orange'],
                                'watching'    => ['bx-video', 'tpc-orange'],
                            ][$ev['kind']];
                            $who = $isSelf ? 'You' : $firstName;
                        @endphp
                        <div class="tp-act">
                            <span class="tp-act-icon {{ $evTone }}"><i class="bx {{ $evIcon }}"></i></span>
                            <div>
                                <b>{{ $who }}</b>
                                @if($ev['kind'] === 'completed')
                                    completed {{ $ev['title'] }}@if($ev['score'] > 0)<div style="color:#16a34a;font-weight:700;">Scored {{ round($ev['score']) }}%</div>@endif
                                @elseif($ev['kind'] === 'failed')
                                    did not pass {{ $ev['title'] }}@if($ev['score'] !== null)<div style="color:#dc2626;font-weight:700;">Scored {{ round($ev['score']) }}%</div>@endif
                                @elseif($ev['kind'] === 'watched')
                                    watched {{ $ev['title'] }}
                                @elseif($ev['kind'] === 'watching')
                                    started watching {{ $ev['title'] }}
                                @else
                                    started {{ $ev['title'] }}
                                @endif
                            </div>
                            <span class="when">{{ $ev['at']->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-center py-3 mb-0" style="font-size:.84rem;">No training activity yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- HR View info --}}
            @if(!$isSelf)
                <div class="tp-card tp-viewing">
                    <i class="bx bx-show d-block mb-1" style="font-size:1.3rem;color:#94a3b8;"></i>
                    Viewing <strong style="color:#0f172a;">{{ $user->name }}</strong>'s training progress
                </div>
            @endif
        </aside>
    </div>
</div>

@endsection

@section('page-script')
<script>
// Plain JS on purpose: runs before the deferred bundles (jQuery, bootstrap) load.
(function () {
    function setOpen(id, open) {
        var body = document.getElementById('phase-' + id);
        if (!body) return;
        var chevron = document.getElementById('chevron-' + id);
        var head = document.getElementById('ph-header-' + id);
        body.style.display = open ? '' : 'none';
        if (chevron) chevron.classList.toggle('open', open);
        if (head) head.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    // Kept for anything that still calls it by the body id ("phase-{id}").
    window.togglePhase = function (id) {
        var body = document.getElementById(id);
        if (body) setOpen(id.replace('phase-', ''), body.style.display === 'none');
    };

    var heads = document.querySelectorAll('.tp-phase-head[data-phase]');
    var expandBtn = document.getElementById('tpExpandAll');
    function allOpen() {
        return Array.prototype.every.call(heads, function (h) {
            var b = document.getElementById('phase-' + h.getAttribute('data-phase'));
            return !b || b.style.display !== 'none';
        });
    }
    function syncExpandLabel() {
        if (expandBtn) expandBtn.querySelector('span').textContent = allOpen() ? 'Collapse All' : 'Expand All';
    }
    Array.prototype.forEach.call(heads, function (h) {
        h.addEventListener('click', function () { window.togglePhase('phase-' + h.getAttribute('data-phase')); syncExpandLabel(); });
        h.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); h.click(); }
        });
    });
    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            var open = !allOpen();
            Array.prototype.forEach.call(heads, function (h) { setOpen(h.getAttribute('data-phase'), open); });
            syncExpandLabel();
        });
    }
    syncExpandLabel();
})();
</script>
@endsection
