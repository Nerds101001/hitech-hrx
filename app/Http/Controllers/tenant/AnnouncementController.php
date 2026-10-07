<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Site;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Yajra\DataTables\Facades\DataTables;

class AnnouncementController extends Controller
{
    /** HR/admin publish company-wide or targeted; managers only to their team. */
    private function isPublisherAdmin(): bool
    {
        return auth()->user()->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR']);
    }

    private function isPublisherManager(): bool
    {
        return auth()->user()->hasRole(['manager', 'Manager']) && !$this->isPublisherAdmin();
    }

    public function dashboard(Request $request)
    {
        $user = auth()->user();

        // Everyone reaches /announcements from the sidebar. Those who cannot
        // publish get their own feed rather than a 403 — an announcements link
        // that only some roles can open is worse than no link at all.
        if (!$this->isPublisherAdmin() && !$this->isPublisherManager()
            && !$user->can('announcements.manage')) {
            return $this->myFeed($request);
        }

        $isAdmin = $this->isPublisherAdmin();

        // A manager's dashboard only ever shows their own team posts
        $scope = fn () => $isAdmin
            ? Announcement::query()
            : Announcement::query()->where('created_by_id', $user->id);

        $statCounts = [
            'total'    => $scope()->count(),
            'active'   => $scope()->where('is_active', true)
                            ->where(function ($q) {
                                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                            })->count(),
            'urgent'   => $scope()->where('is_active', true)->whereIn('priority', ['high', 'critical'])->count(),
            'targeted' => $scope()->whereIn('audience_type', ['targeted', 'team'])->count(),
            'expired'  => $scope()->whereNotNull('end_date')->where('end_date', '<', now()->toDateString())->count(),
        ];

        return view('tenant.announcement.dashboard', [
            'pageConfigs'  => ['contentLayout' => 'wide'],
            'statCounts'   => $statCounts,
            'departments'  => Department::withTrashed()->orderBy('name')->get(['id', 'name']),
            'sites'        => Site::orderBy('name')->get(['id', 'name']),
            'designations' => Designation::orderBy('name')->get(['id', 'name']),
            'roles'        => \Spatie\Permission\Models\Role::orderBy('name')->get(['id', 'name']),
            'isAdmin'      => $isAdmin,
            'isManager'    => $this->isPublisherManager(),
        ]);
    }

    public function dashboardAjax(Request $request)
    {
        try {
            $user = auth()->user();
            $query = Announcement::with('createdBy')->select('announcements.*');

            if (!$this->isPublisherAdmin()) {
                $query->where('created_by_id', $user->id);
            }

            if ($request->filled('audienceFilter')) {
                $query->where('audience_type', $request->audienceFilter);
            }
            if ($request->filled('typeFilter')) {
                $query->where('type', $request->typeFilter);
            }
            if ($request->filled('priorityFilter')) {
                $query->where('priority', $request->priorityFilter);
            }
            if ($request->filled('statusFilter')) {
                match ($request->statusFilter) {
                    'active' => $query->where('is_active', true)->where(function ($q) {
                        $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                    }),
                    'expired' => $query->whereNotNull('end_date')->where('end_date', '<', now()->toDateString()),
                    'inactive' => $query->where('is_active', false),
                    default => null,
                };
            }
            if ($request->filled('searchTerm')) {
                $term = $request->searchTerm;
                $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('content', 'like', "%{$term}%"));
            }

            return DataTables::of($query)
                ->addColumn('audience_summary', fn ($a) => $a->audience_summary)
                ->addColumn('author', fn ($a) => $a->createdBy?->getFullName() ?? 'System')
                ->addColumn('published', fn ($a) => $a->published_at ? $a->published_at->format('d M Y, h:i A') : '—')
                ->addColumn('window', function ($a) {
                    $s = $a->start_date ? $a->start_date->format('d M Y') : '—';
                    $e = $a->end_date ? $a->end_date->format('d M Y') : 'No end';
                    return $s . ' → ' . $e;
                })
                ->addColumn('is_expired', fn ($a) => $a->end_date && $a->end_date->lt(now()->startOfDay()))
                // Posts the system makes itself (welcome posts) never stored a
                // reach, and showed "0 people"; an Everyone post reaches the
                // whole active headcount.
                ->addColumn('reach', function ($a) use (&$everyone) {
                    if ($a->recipients_count || $a->audience_type !== 'global') {
                        return $a->recipients_count ?? 0;
                    }
                    return $everyone ??= \App\Models\User::notTerminated()
                        ->where('status', \App\Enums\UserAccountStatus::ACTIVE)->count();
                })
                ->orderColumn('published', 'published_at $1')
                ->make(true);
        } catch (\Exception $e) {
            Log::error('Announcement dashboardAjax: ' . $e->getMessage());
            return Error::response($e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$this->isPublisherAdmin() && !$this->isPublisherManager()) {
            return Error::response('You are not allowed to publish announcements.', 403);
        }

        $validated = $request->validate([
            'id'                     => 'nullable|exists:announcements,id',
            'title'                  => 'required|string|max:200',
            'content'                => 'required|string',
            'type'                   => 'required|in:general,urgent,policy,holiday,event',
            'priority'               => 'required|in:low,medium,high,critical',
            'audience_type'          => 'required|in:global,targeted,team',
            'start_date'             => 'required|date',
            'end_date'               => 'nullable|date|after_or_equal:start_date',
            'target_department_ids'   => 'nullable|array',
            'target_department_ids.*' => 'exists:departments,id',
            'target_site_ids'         => 'nullable|array',
            'target_site_ids.*'       => 'exists:sites,id',
            'target_designation_ids'  => 'nullable|array',
            'target_designation_ids.*' => 'exists:designations,id',
            'target_role_names'       => 'nullable|array',
            'target_role_names.*'     => 'exists:roles,name',
            'notify_email'            => 'nullable|boolean',
            'requires_acknowledgment' => 'nullable|boolean',
        ]);

        $audience = $validated['audience_type'];

        // A manager may only ever post to their own team — never company-wide
        // and never to an arbitrary department/location they don't own.
        if ($this->isPublisherManager() && $audience !== 'team') {
            return Error::response('Managers can only publish to their own team.', 403);
        }

        if ($audience === 'targeted') {
            $hasTarget = collect(['target_department_ids', 'target_site_ids', 'target_designation_ids', 'target_role_names'])
                ->contains(fn ($k) => !empty($validated[$k] ?? []));
            if (!$hasTarget) {
                return Error::response('Select at least one department, location, designation or role.', 422);
            }
        }

        $isNew = empty($validated['id']);

        $payload = [
            'title'                   => $validated['title'],
            'content'                 => $validated['content'],
            'type'                    => $validated['type'],
            'priority'                => $validated['priority'],
            'audience_type'           => $audience,
            'start_date'              => $validated['start_date'],
            'end_date'                => $validated['end_date'] ?? null,
            'is_active'               => true,
            'notify_email'            => (bool) ($validated['notify_email'] ?? false),
            'requires_acknowledgment' => (bool) ($validated['requires_acknowledgment'] ?? false),
            // Targets only make sense for 'targeted'; clear them otherwise so a
            // switch to global/team can't leave stale filters behind.
            'target_department_ids'   => $audience === 'targeted' ? ($validated['target_department_ids'] ?? []) : null,
            'target_site_ids'         => $audience === 'targeted' ? ($validated['target_site_ids'] ?? []) : null,
            'target_designation_ids'  => $audience === 'targeted' ? ($validated['target_designation_ids'] ?? []) : null,
            'target_role_names'       => $audience === 'targeted' ? ($validated['target_role_names'] ?? []) : null,
        ];

        if ($isNew) {
            $payload['created_by_id'] = $user->id;
            $payload['published_at']  = now();
            $payload['tenant_id']     = $user->tenant_id;
            $announcement = Announcement::create($payload);
        } else {
            $announcement = Announcement::findOrFail($validated['id']);
            if (!$this->canManage($announcement)) {
                return Error::response('You can only edit your own announcements.', 403);
            }
            $announcement->update($payload);
        }

        // Resolve audience and notify
        $recipients = $announcement->resolveRecipients();
        $announcement->forceFill(['recipients_count' => $recipients->count()])->saveQuietly();

        if ($isNew && $recipients->isNotEmpty()) {
            try {
                Notification::send($recipients, new AnnouncementPublished($announcement));
            } catch (\Throwable $e) {
                // A mail failure must not lose the announcement itself
                Log::error('Announcement notify failed: ' . $e->getMessage());
            }
        }

        return Success::response([
            'message' => $isNew ? 'Announcement published' : 'Announcement updated',
            'reach'   => $recipients->count(),
        ]);
    }

    public function getByIdAjax($id)
    {
        $a = Announcement::findOrFail($id);
        if (!$this->canManage($a)) {
            return Error::response('Not allowed.', 403);
        }

        return Success::response([
            'id'                      => $a->id,
            'title'                   => $a->title,
            'content'                 => $a->content,
            'type'                    => $a->type,
            'priority'                => $a->priority,
            'audience_type'           => $a->audience_type,
            'start_date'              => $a->start_date?->format('Y-m-d'),
            'end_date'                => $a->end_date?->format('Y-m-d'),
            'target_department_ids'    => $a->target_department_ids ?: [],
            'target_site_ids'          => $a->target_site_ids ?: [],
            'target_designation_ids'   => $a->target_designation_ids ?: [],
            'target_role_names'        => $a->target_role_names ?: [],
            'notify_email'             => (bool) $a->notify_email,
            'requires_acknowledgment'  => (bool) $a->requires_acknowledgment,
        ]);
    }

    public function deleteAjax($id)
    {
        $a = Announcement::findOrFail($id);
        if (!$this->canManage($a)) {
            return Error::response('You can only delete your own announcements.', 403);
        }
        $a->delete();

        return Success::response('Announcement deleted');
    }

    public function toggleStatusAjax($id)
    {
        $a = Announcement::findOrFail($id);
        if (!$this->canManage($a)) {
            return Error::response('Not allowed.', 403);
        }
        $a->is_active = !$a->is_active;
        $a->save();

        return Success::response($a->is_active ? 'Announcement activated' : 'Announcement deactivated');
    }

    /** Admin/HR manage anything; a manager only their own posts. */
    private function canManage(Announcement $a): bool
    {
        return $this->isPublisherAdmin() || $a->created_by_id === auth()->id();
    }

    /** Employee-facing feed: only what is targeted at this user, newest first. */
    public function myFeed(Request $request)
    {
        $user = auth()->user();

        $items = Announcement::with('createdBy')
            ->current()
            ->visibleTo($user)
            ->orderByRaw("FIELD(priority,'critical','high','medium','low')")
            ->orderByDesc('published_at')
            ->paginate(15);

        \App\Support\DashboardAnnouncements::attachJoiners($items->getCollection());

        return view('tenant.announcement.my_feed', [
            'pageConfigs'   => ['contentLayout' => 'wide'],
            'announcements' => $items,
        ]);
    }
}
