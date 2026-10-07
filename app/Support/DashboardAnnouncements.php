<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The announcements feed shown in every dashboard rail.
 *
 * One implementation for the employee, manager, admin, HR and finance
 * dashboards, so they cannot drift apart the way the sales figures did.
 *
 * Current announcements (the Announcements module) are the whole feed. The
 * legacy notices table is not read at all: it holds nothing but seeded
 * "Notice 1/2/3" rows, which used to fill the panel whenever nothing current
 * was published.
 */
class DashboardAnnouncements
{
    /**
     * @return \Illuminate\Support\Collection announcements, each with a
     *         ->joiner array when the post welcomes a new starter.
     */
    public static function forUser(?User $user, int $limit = 5)
    {
        $items = collect();

        if ($user) {
            try {
                $items = Announcement::current()
                    ->visibleTo($user)
                    ->orderByDesc('published_at')
                    ->take($limit)
                    ->get();
            } catch (\Throwable $e) {
                Log::warning('Dashboard announcements: ' . $e->getMessage());
                $items = collect();
            }
        }

        // No fallback to the legacy notices table: it holds only the seeded
        // "Notice 1/2/3" rows, which then showed as the latest announcements
        // whenever nothing current was published.
        return self::attachJoiners($items);
    }

    /**
     * Resolve the employee behind a "Welcome to the team, X!" post so the popup
     * can show their designation, department and email. Taken from the user
     * record, never parsed out of the stored HTML.
     *
     * Public: also used by AnnouncementController::myFeed() so the full feed
     * page's joiner cards stay on the same live-lookup logic as the dashboard
     * rail instead of re-deriving it from the stored HTML a second way.
     */
    public static function attachJoiners($items)
    {
        $needles = [];
        foreach ($items as $n) {
            $n->joiner = null;
            if (preg_match('/welcome to the team,\s*(.+?)\s*!*$/i', (string) ($n->title ?? ''), $m)) {
                $needles[$n->id] = preg_replace('/\s+/', ' ', trim($m[1]));
            }
        }
        if (empty($needles)) {
            return $items;
        }

        try {
            // One pass over active staff rather than a query per announcement.
            $byName = User::with(['designation', 'department'])
                ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
                ->get()
                ->keyBy(fn($u) => strtolower(preg_replace('/\s+/', ' ', trim($u->getFullName()))));

            foreach ($items as $n) {
                $needle = $needles[$n->id] ?? null;
                if (!$needle) {
                    continue;
                }
                $match = $byName->get(strtolower($needle));
                if (!$match) {
                    continue;
                }
                $n->joiner = [
                    'id'          => $match->id,
                    'name'        => $match->getFullName(),
                    'designation' => optional($match->designation)->name ?: '—',
                    'department'  => optional($match->department)->name ?: '—',
                    'email'       => $match->email,
                    // No phone: a welcome post goes to everyone, and a new
                    // joiner's personal mobile is not theirs to broadcast.
                    'photo'       => $match->getProfilePicture() ?: null,
                    'joined'      => $match->date_of_joining
                                      ? Carbon::parse($match->date_of_joining)->format('d M Y') : null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Dashboard announcement joiners: ' . $e->getMessage());
        }

        return $items;
    }
}
