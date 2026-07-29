<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSetupTask;
use App\Models\ExitClearance;
use Illuminate\Support\Facades\Schema;

/**
 * "My Tasks" — one place where any user finds the work that is actually
 * waiting on them, whatever their role. Each source decides for itself
 * whether the signed-in user owns that item, so a user only ever sees their
 * own queue.
 */
class MyTasksController extends Controller
{
    public function index()
    {
        return view('tenant.my-tasks.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'groups'      => self::forUser(auth()->user()),
        ]);
    }

    /**
     * Everything outstanding for a user, grouped by kind.
     *
     * Static so the sidebar badge and the dashboard card can ask the same
     * question without duplicating the rules.
     */
    public static function forUser($user): array
    {
        if (!$user) {
            return [];
        }

        $groups = [];

        // ---- Attendance setup (accounts) --------------------------------
        if ($user->hasRole(['accounts', 'admin', 'Admin', 'hr', 'HR'])
            && Schema::hasTable('employee_setup_tasks')) {
            try {
                $setup = EmployeeSetupTask::pending()
                    ->with(['user.department', 'user.designation'])
                    ->orderBy('created_at')->get();

                if ($setup->isNotEmpty()) {
                    $groups[] = [
                        'key'    => 'setup',
                        'title'  => 'Attendance Setup',
                        'sub'    => 'Record employee ID and biometric enrolment so payroll can start',
                        'icon'   => 'bx-id-card',
                        'tone'   => 'primary',
                        'action' => url('/employee-setup'),
                        'items'  => $setup->map(function ($t) {
                            $meta = trim(implode(' · ', array_filter([
                                optional(optional($t->user)->designation)->name,
                                optional(optional($t->user)->department)->name,
                            ])));

                            return [
                                'title' => optional($t->user)->getFullName() ?? 'Employee',
                                'meta'  => $meta,
                                'tags'  => array_values(array_filter([
                                    $t->needs_emp_id ? 'Employee ID' : null,
                                    $t->needs_biometric ? 'Biometric' : 'Biometric exempt',
                                ])),
                                'url'   => url('/employee-setup'),
                                // Completed in a dialog on this page — sending
                                // the user off to the queue page just to come
                                // back was the long way round.
                                'modal' => [
                                    'id'        => $t->id,
                                    'name'      => optional($t->user)->getFullName() ?? 'Employee',
                                    'meta'      => $meta,
                                    'emp'       => optional($t->user)->code,
                                    'needs_emp' => $t->needs_emp_id ? 1 : 0,
                                    'needs_bio' => $t->needs_biometric ? 1 : 0,
                                ],
                            ];
                        })->all(),
                    ];
                }
            } catch (\Throwable $e) { /* module not ready */ }
        }

        // ---- Exit clearances this user can actually sign ------------------
        if (Schema::hasTable('exit_clearances')) {
            try {
                $mine = ExitClearance::pending()->with('user')->get()
                    ->filter(fn($c) => $c->isSignableBy($user));

                if ($mine->isNotEmpty()) {
                    $groups[] = [
                        'key'    => 'noc',
                        'title'  => 'Exit Clearance (NOC)',
                        'sub'    => 'Confirm there are no dues or pending items from your side',
                        'icon'   => 'bx-user-x',
                        'tone'   => 'danger',
                        'action' => url('/exit-clearance'),
                        'items'  => $mine->map(fn($c) => [
                            'title' => optional($c->user)->getFullName() ?? 'Employee',
                            'meta'  => $c->label . ' clearance · ' . ucfirst($c->exit_type),
                            'tags'  => [$c->label],
                            'url'   => url('/exit-clearance'),
                        ])->values()->all(),
                    ];
                }
            } catch (\Throwable $e) { /* module not ready */ }
        }

        return $groups;
    }

    /** Total outstanding items, for the sidebar badge. */
    public static function countFor($user): int
    {
        return collect(self::forUser($user))->sum(fn($g) => count($g['items']));
    }
}
