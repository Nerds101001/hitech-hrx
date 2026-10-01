<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Announcement extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'created_by_id',
        'title',
        'content',
        // SECURITY (M-15): 'is_html' intentionally NOT mass-assignable — it gates
        // raw {!! !!} rendering of announcement content. It is set explicitly in the
        // server-side welcome path (HrNotificationService) only, never from a
        // request payload, so a create($request->all()) can never turn it on.
        'type',
        'priority',
        'start_date',
        'end_date',
        'is_active',
        'requires_acknowledgment',
        'audience_type',
        'target_department_ids',
        'target_site_ids',
        'target_role_names',
        'target_designation_ids',
        'notify_email',
        'published_at',
        'recipients_count',
        'tenant_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_html' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'notify_email' => 'boolean',
        'published_at' => 'datetime',
        'target_department_ids' => 'array',
        'target_site_ids' => 'array',
        'target_role_names' => 'array',
        'target_designation_ids' => 'array',
    ];

    // Relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        // end_date is a date: the post runs to the end of that day. Compared with
        // now() it expired at 00:00 on its last day (a post ending 1 Oct vanished
        // from every feed on the morning of 1 Oct).
        return $query->where('is_active', true)
                   ->where(function($q) {
                       $q->whereNull('end_date')
                         ->orWhere('end_date', '>=', now()->toDateString());
                   })
                   ->where('start_date', '<=', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Announcements this user should see — global ones, targeted ones matching
     * any of their attributes (department / location / role / designation), and
     * team ones published by a manager they report to.
     *
     * Targeting is OR across the four dimensions: picking "Sales" + "Mumbai"
     * reaches everyone in Sales plus everyone in Mumbai. An empty target list
     * means that dimension is not constrained.
     */
    public function scopeVisibleTo($query, User $user)
    {
        $roleNames = $user->getRoleNames()->all();
        // A delegate acting for a vacant position also sees that position's
        // team announcements (managerIdentityIds covers self + delegated).
        $managerIds = method_exists($user, 'managerIdentityIds')
            ? $user->managerIdentityIds()
            : [$user->id];

        return $query->where(function ($q) use ($user, $roleNames, $managerIds) {
            $q->where('audience_type', 'global')
              ->orWhere(function ($t) use ($user, $roleNames) {
                  $t->where('audience_type', 'targeted')->where(function ($m) use ($user, $roleNames) {
                      if ($user->department_id) {
                          $m->orWhereJsonContains('target_department_ids', (string) $user->department_id)
                            ->orWhereJsonContains('target_department_ids', (int) $user->department_id);
                      }
                      if ($user->site_id) {
                          $m->orWhereJsonContains('target_site_ids', (string) $user->site_id)
                            ->orWhereJsonContains('target_site_ids', (int) $user->site_id);
                      }
                      if ($user->designation_id) {
                          $m->orWhereJsonContains('target_designation_ids', (string) $user->designation_id)
                            ->orWhereJsonContains('target_designation_ids', (int) $user->designation_id);
                      }
                      foreach ($roleNames as $rn) {
                          $m->orWhereJsonContains('target_role_names', $rn);
                      }
                  });
              })
              // Team announcement: published by this user's manager (or by a
              // vacant manager account they are the delegate of), or by
              // themselves so a manager sees what they posted.
              ->orWhere(function ($tm) use ($user, $managerIds) {
                  $tm->where('audience_type', 'team')
                     ->where(function ($w) use ($user, $managerIds) {
                         $w->whereIn('created_by_id', array_filter([$user->reporting_to_id]))
                           ->orWhereIn('created_by_id', $managerIds);
                     });
              });
        });
    }

    /** The actual users this announcement reaches (used for notifying). */
    public function resolveRecipients()
    {
        $q = User::notTerminated()->where('status', \App\Enums\UserAccountStatus::ACTIVE);

        if ($this->audience_type === 'global') {
            return $q->get();
        }

        if ($this->audience_type === 'team') {
            $author = $this->createdBy;
            if (!$author) {
                return collect();
            }
            $ids = method_exists($author, 'managerIdentityIds')
                ? $author->managerIdentityIds()
                : [$author->id];
            return $q->whereIn('reporting_to_id', $ids)->get();
        }

        $depts   = $this->target_department_ids ?: [];
        $sites   = $this->target_site_ids ?: [];
        $desigs  = $this->target_designation_ids ?: [];
        $roles   = $this->target_role_names ?: [];

        // Nothing selected => reaches nobody, rather than silently everybody
        if (!$depts && !$sites && !$desigs && !$roles) {
            return collect();
        }

        return $q->where(function ($w) use ($depts, $sites, $desigs, $roles) {
            if ($depts)  { $w->orWhereIn('department_id', $depts); }
            if ($sites)  { $w->orWhereIn('site_id', $sites); }
            if ($desigs) { $w->orWhereIn('designation_id', $desigs); }
            if ($roles)  {
                $w->orWhereHas('roles', fn ($r) => $r->whereIn('name', $roles));
            }
        })->get();
    }

    /** Human-readable audience summary for the dashboard table. */
    public function getAudienceSummaryAttribute(): string
    {
        if ($this->audience_type === 'global') {
            return 'Everyone';
        }
        if ($this->audience_type === 'team') {
            return 'Team of ' . ($this->createdBy?->getFullName() ?? 'author');
        }

        $parts = [];
        if ($d = $this->target_department_ids) {
            $parts[] = Department::whereIn('id', $d)->pluck('name')->implode(', ');
        }
        if ($s = $this->target_site_ids) {
            $parts[] = Site::whereIn('id', $s)->pluck('name')->implode(', ');
        }
        if ($g = $this->target_designation_ids) {
            $parts[] = Designation::whereIn('id', $g)->pluck('name')->implode(', ');
        }
        if ($r = $this->target_role_names) {
            $parts[] = collect($r)->map(fn ($x) => ucwords(str_replace('_', ' ', $x)))->implode(', ');
        }

        return implode(' · ', array_filter($parts)) ?: 'No audience selected';
    }

    // Accessors
    public function getTypeBadgeAttribute()
    {
        return match($this->type) {
            'general' => '<span class="badge bg-label-primary">General</span>',
            'urgent' => '<span class="badge bg-label-danger">Urgent</span>',
            'policy' => '<span class="badge bg-label-info">Policy</span>',
            'holiday' => '<span class="badge bg-label-success">Holiday</span>',
            'event' => '<span class="badge bg-label-warning">Event</span>',
            default => '<span class="badge bg-label-secondary">' . ucfirst($this->type) . '</span>',
        };
    }

    public function getPriorityBadgeAttribute()
    {
        return match($this->priority) {
            'low' => '<span class="badge bg-label-info">Low</span>',
            'medium' => '<span class="badge bg-label-warning">Medium</span>',
            'high' => '<span class="badge bg-label-danger">High</span>',
            'critical' => '<span class="badge bg-label-danger">Critical</span>',
            default => '<span class="badge bg-label-secondary">' . ucfirst($this->priority) . '</span>',
        };
    }

    public function getIsActiveLabelAttribute()
    {
        return $this->is_active ? 
            '<span class="badge bg-label-success">Active</span>' : 
            '<span class="badge bg-label-secondary">Inactive</span>';
    }

    public function getRequiresAcknowledgmentLabelAttribute()
    {
        return $this->requires_acknowledgment ? 
            '<span class="badge bg-label-warning">Required</span>' : 
            '<span class="badge bg-label-info">Optional</span>';
    }
}
