<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\LeaveUnlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Leave Unlock Windows (Admin/HR): let people apply for leave on dates older
 * than the one-week back-dating limit - the leave twin of the attendance
 * unlock windows under Sales Field Ops (CcAttendanceController::unlocks).
 */
class LeaveUnlockController extends Controller
{
    private function canManage(): bool
    {
        return (bool) Auth::user()?->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR']);
    }

    /** Manage screen: existing windows + everyone a window can target. */
    public function index()
    {
        abort_unless($this->canManage(), 403);

        $windows = LeaveUnlock::with('creator')->orderByDesc('id')->get();
        $people = User::where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code']);

        // A window can name someone who has since left - fetch them too so
        // the "N selected" popup still shows a name, not an id.
        $missing = $windows->where('scope', 'selected')->flatMap(fn ($w) => $w->user_ids ?? [])
            ->unique()->diff($people->pluck('id'));
        $extra = $missing->isEmpty() ? collect()
            : User::withTrashed()->whereIn('id', $missing)->get(['id', 'first_name', 'last_name', 'code']);
        $peopleById = $people->concat($extra)->keyBy('id');

        return view('tenant.leave.unlocks', compact('windows', 'people', 'peopleById'));
    }

    /** Open a window. Capped at LeaveUnlock::MAX_DAYS. */
    public function store(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'from_date'  => ['required', 'date'],
            'to_date'    => ['required', 'date', 'after_or_equal:from_date'],
            'scope'      => ['required', 'in:all,selected'],
            'user_ids'   => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'reason'     => ['nullable', 'string', 'max:255'],
        ]);

        $days = Carbon::parse($data['from_date'])->diffInDays(Carbon::parse($data['to_date'])) + 1;
        if ($days > LeaveUnlock::MAX_DAYS) {
            return back()->withInput()->with('error', 'A window may cover at most ' . LeaveUnlock::MAX_DAYS . " days - you asked for {$days}.");
        }
        if ($data['scope'] === 'selected' && empty($data['user_ids'])) {
            return back()->withInput()->with('error', 'Pick at least one person, or choose "Everyone".');
        }

        LeaveUnlock::create([
            'from_date'  => $data['from_date'],
            'to_date'    => $data['to_date'],
            'scope'      => $data['scope'],
            'user_ids'   => $data['scope'] === 'selected' ? array_values(array_map('intval', $data['user_ids'])) : null,
            'is_active'  => true,
            'reason'     => $data['reason'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', "Leave unlocked for {$days} day(s).");
    }

    /** Enable / disable without losing the record. */
    public function toggle($id)
    {
        abort_unless($this->canManage(), 403);
        $w = LeaveUnlock::findOrFail($id);
        $w->is_active = !$w->is_active;
        $w->save();

        return back()->with('success', 'Window ' . ($w->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function destroy($id)
    {
        abort_unless($this->canManage(), 403);
        LeaveUnlock::findOrFail($id)->delete();

        return back()->with('success', 'Window removed.');
    }
}
