<?php

namespace App\Support;

use App\Http\Controllers\tenant\TrainingController;
use App\Models\TrainingPhase;
use App\Models\TrainingVideoView;
use App\Models\User;

/**
 * Everything on one person's training report card, worked out once so the
 * page and its PDF always say the same thing.
 *
 * The figures that count are the compulsory phases
 * (config('training.onboarding_phase_ids')): they are what holds a new
 * joiner's account and what "passed" means. The average score and module
 * counts cover every phase, as the portal shows them.
 */
class TrainingTranscript
{
    public static function for(User $user): object
    {
        $user->loadMissing(['department', 'designation', 'site']);
        $phases = TrainingPhase::with([
            'modules' => fn ($q) => $q->orderBy('order'),
            'modules.userProgress' => fn ($q) => $q->where('user_id', $user->id),
        ])->orderBy('order')->orderBy('id')->get();

        $compIds  = config('training.onboarding_phase_ids', [1, 2]);
        $libIds   = config('training.library_video_phase_ids', []);
        $videos   = TrainingController::libraryVideos();
        $views    = TrainingVideoView::where('user_id', $user->id)->get()->keyBy('library_file_id');
        $required = max(1, (int) config('training.library_videos_required', 3));
        $minMins  = (int) config('training.library_video_min_minutes', 20);

        $groups = [];
        $compTotal = $compDone = $allTotal = $allDone = $attempts = 0;
        $scores = [];
        $failed = [];
        $next = null;
        $firstAt = $lastAt = $compDoneAt = null;
        $touch = function ($at) use (&$firstAt, &$lastAt) {
            if (!$at) return;
            if (!$firstAt || $at->lt($firstAt)) $firstAt = $at;
            if (!$lastAt || $at->gt($lastAt)) $lastAt = $at;
        };

        foreach ($phases as $ph) {
            $compulsory = in_array($ph->id, $compIds, true);
            $isLibrary  = in_array($ph->id, $libIds, true) && $videos->isNotEmpty();
            $rows = [];

            if ($isLibrary) {
                // Learn @ Hi Tech: any $required of the Digital Library sessions.
                foreach ($videos as $v) {
                    $vv = $views->get($v->id);
                    if (!$vv) continue;
                    $touch($vv->started_at);
                    $touch($vv->completed_at);
                    $rows[] = (object) [
                        'title' => $v->title, 'type' => 'Session video', 'minutes' => $minMins,
                        'attempts' => null, 'score' => null, 'date' => $vv->completed_at,
                        'status' => $vv->completed_at ? 'Watched' : 'Started',
                    ];
                }
                $watched = $views->filter(fn ($vv) => $vv->completed_at && $videos->contains('id', $vv->library_file_id))->count();
                $done = min($watched, $required);
                $total = $required;
            } else {
                $done = 0;
                foreach ($ph->modules as $m) {
                    $p = $m->userProgress->first();
                    $score = $p && $p->assessment_score > 0 ? round((float) $p->assessment_score, 1) : null;
                    $status = match ($p->status ?? null) {
                        'completed'   => $score !== null ? 'Passed' : 'Completed',
                        'failed'      => 'Failed',
                        'in_progress' => 'In progress',
                        default       => 'Not started',
                    };
                    if ($p) {
                        $touch($p->started_at);
                        $touch($p->completed_at);
                        $attempts += (int) $p->attempts;
                    }
                    if (in_array($status, ['Passed', 'Completed'], true)) {
                        $done++;
                        if ($score !== null) $scores[] = (float) $p->assessment_score;   // exact, not the rounded figure shown
                        if ($compulsory && $p->completed_at && (!$compDoneAt || $p->completed_at->gt($compDoneAt))) $compDoneAt = $p->completed_at;
                    } elseif ($status === 'Failed') {
                        $failed[] = $m->title;
                    }
                    if ($compulsory && !$next && !in_array($status, ['Passed', 'Completed'], true)) $next = $m->title;
                    $rows[] = (object) [
                        'title' => $m->title,
                        'type' => ['policy' => 'Text', 'catalog' => 'PDF', 'video' => 'Video'][$m->content_type] ?? ucfirst((string) $m->content_type),
                        'minutes' => (int) $m->estimated_time_minutes,
                        'attempts' => $p ? (int) $p->attempts : 0,
                        'score' => $score,
                        'date' => $p && in_array($status, ['Passed', 'Completed'], true) ? $p->completed_at : null,
                        'status' => $status,
                    ];
                }
                $total = $ph->modules->count();
                $allTotal += $total;
                $allDone += $done;
                if ($compulsory) {
                    $compTotal += $total;
                    $compDone += $done;
                }
            }

            $groups[] = (object) [
                'title' => $ph->title, 'compulsory' => $compulsory, 'library' => $isLibrary,
                'done' => $done, 'total' => $total, 'rows' => $rows,
            ];
        }

        $compPassed = $compTotal > 0 && $compDone >= $compTotal;
        $approved   = $user->training_status === 'completed';
        $held       = TrainingLock::progressFor($user) !== null;
        $started    = $firstAt !== null;
        // HR can finalise training before every module is passed; say so rather
        // than call it a pass.
        $status = $approved ? ($compPassed ? 'Completed' : 'Completed (approved by HR)')
            : ($compPassed ? 'Compulsory passed' : ($held ? 'In training (account on hold)' : ($started ? 'In progress' : 'Not started')));
        $result = $compPassed ? 'PASSED' : ($approved ? 'APPROVED BY HR' : ($started ? 'IN PROGRESS' : 'NOT STARTED'));

        $manager = $user->reporting_to_id ? User::find($user->reporting_to_id) : null;
        $joined  = $user->date_of_joining;
        $span    = $joined && $joined->lte(now()) ? $joined->diff(now()) : null;

        return (object) [
            'user'        => $user,
            'manager'     => $manager ? trim($manager->first_name . ' ' . $manager->last_name) : null,
            'joined'      => $joined,
            'tenure'      => $span ? trim(($span->y ? $span->y . ' yr ' : '') . $span->m . ' mo') : null,
            'groups'      => $groups,
            'compTotal'   => $compTotal,
            'compDone'    => $compDone,
            'compPct'     => $compTotal > 0 ? (int) round($compDone / $compTotal * 100) : 0,
            'allTotal'    => $allTotal,
            'allDone'     => $allDone,
            'avgScore'    => $scores ? round(array_sum($scores) / count($scores), 1) : null,
            'scored'      => count($scores),
            'attempts'    => $attempts,
            'watched'     => collect($groups)->firstWhere('library', true)->done ?? null,
            'required'    => $required,
            'failed'      => $failed,
            'next'        => $next,
            'firstAt'     => $firstAt,
            'lastAt'      => $lastAt,
            'completedAt' => $compPassed ? $compDoneAt : null,
            'status'      => $status,
            'result'      => $result,
            'approved'    => $approved,
            'held'        => $held,
        ];
    }
}
