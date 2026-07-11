<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkControlRoutine;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\tenant\WorkControlExport;

class WorkControlController extends Controller
{
    public function export()
    {
        return Excel::download(new WorkControlExport, 'work_control_routines.xlsx');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        
        $isAdminOrHR = $user->hasRole(['admin', 'hr']);

        if ($isAdminOrHR) {
            $query = User::where('status', \App\Enums\UserAccountStatus::ACTIVE)
                ->with(['department', 'workControlRoutines']);

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            $users = $query->get();
            $departments = \App\Models\Department::orderBy('name')->get();
            
            return view('tenant.work-control.admin-dashboard', compact('users', 'departments'));
        } else {
            // Employee view
            $routines = WorkControlRoutine::where('user_id', $user->id)->get();
            return view('tenant.work-control.employee-intake', compact('routines', 'user'));
        }
    }

    public function myRoutines()
    {
        $user = Auth::user();
        $routines = WorkControlRoutine::where('user_id', $user->id)->get();
        return view('tenant.work-control.employee-intake', compact('routines', 'user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'routines' => 'nullable|array',
            'routines.*.id' => 'nullable|exists:work_control_routines,id',
            'routines.*.title' => 'required|string|max:255',
            'routines.*.frequency' => 'required|in:daily,weekly,fortnightly,monthly,quarterly,yearly',
            'routines.*.duration_hours' => 'nullable|integer|min:0',
            'routines.*.duration_mins' => 'nullable|integer|min:0|max:59',
        ]);

        $user = Auth::user();
        $submittedIds = [];

        if ($request->has('routines')) {
            foreach ($request->routines as $routineData) {
                $hours = (int)($routineData['duration_hours'] ?? 0);
                $mins = (int)($routineData['duration_mins'] ?? 0);
                $totalMinutes = ($hours * 60) + $mins;

                if ($totalMinutes > 0) {
                    if (isset($routineData['id']) && $routineData['id']) {
                        $routine = WorkControlRoutine::where('id', $routineData['id'])
                            ->where('user_id', $user->id)
                            ->first();
                        if ($routine) {
                            $routine->update([
                                'title' => $routineData['title'],
                                'frequency' => $routineData['frequency'],
                                'duration_minutes' => $totalMinutes,
                            ]);
                            $submittedIds[] = $routine->id;
                        }
                    } else {
                        $routine = WorkControlRoutine::create([
                            'user_id' => $user->id,
                            'title' => $routineData['title'],
                            'frequency' => $routineData['frequency'],
                            'duration_minutes' => $totalMinutes,
                        ]);
                        $submittedIds[] = $routine->id;
                    }
                }
            }
        }

        // Delete routines that were removed
        WorkControlRoutine::where('user_id', $user->id)
            ->whereNotIn('id', $submittedIds)
            ->delete();

        return redirect()->route('tenant.work-control.index')->with('success', 'Routines updated successfully.');
    }
}
