<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Holiday;

/**
 * "My Holidays" — every user's own answer to "which holidays actually apply
 * to me", whatever their role. The only Holidays page before this was the
 * admin/hr management screen (create/edit, gated by role) — nothing let a
 * regular employee see their own calendar, and the admin screen lists every
 * holiday everywhere regardless of site/department, which isn't the same
 * question. Holiday::forUser()/appliesToUser() already did the real
 * scoping work; this page is the first place that actually shows it to the
 * person it's for.
 */
class MyHolidaysController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $year = (int) (request('year') ?? now()->year);

        $holidays = Holiday::forUser($user)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->filter(fn ($h) => $h->appliesToUser($user))
            ->values();

        $today = now()->toDateString();
        $upcoming = $holidays->filter(fn ($h) => $h->date->toDateString() >= $today)->values();
        $past = $holidays->filter(fn ($h) => $h->date->toDateString() < $today)->sortByDesc('date')->values();

        // The very next one gets its own hero treatment on the page, so it's
        // pulled out here rather than the view re-deriving "first of
        // upcoming" itself.
        $nextHoliday = $upcoming->first();
        $daysUntilNext = $nextHoliday ? now()->startOfDay()->diffInDays($nextHoliday->date->copy()->startOfDay()) : null;

        return view('tenant.my-holidays.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'upcoming' => $upcoming,
            'past' => $past,
            'year' => $year,
            'yearOptions' => range(now()->year - 1, now()->year + 1),
            'nextHoliday' => $nextHoliday,
            'daysUntilNext' => $daysUntilNext,
        ]);
    }
}
