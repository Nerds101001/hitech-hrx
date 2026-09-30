<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A person who may fill one KPI for the whole team.
 *
 * See the migration for why this exists. Optionally scoped to a department, so
 * the same KPI can have a different owner per team.
 */
class KpiDataOwner extends Model implements AuditableContract
{
    use Auditable, TenantTrait;

    protected $fillable = [
        'tenant_id',
        'kpi_template_id',
        'user_id',
        'department_id',
        'note',
        'created_by_id',
    ];

    public function template()
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** The KPI template ids this user may fill for other people. */
    /**
     * Whether Bulk KPI Entry has anything for this person: a KPI admin (admin,
     * HR, kpi.manage) or a named data owner of at least one KPI. The same rule
     * KpiBulkEntryController::index() enforces - anyone else gets a 403 there,
     * so the sidebar must not draw the link for them.
     */
    public static function canOpenBulkEntry(User $user): bool
    {
        return $user->hasRole(['admin', 'hr']) || $user->can('kpi.manage') || static::where('user_id', $user->id)->exists();
    }

    public static function templateIdsFor(User $user): array
    {
        return static::where('user_id', $user->id)->pluck('kpi_template_id')->unique()->values()->all();
    }

    /**
     * Departments this user may fill $templateId for. An empty array means
     * every department -- a row with a null department_id is unrestricted.
     */
    public static function departmentScopeFor(User $user, int $templateId): array
    {
        $rows = static::where('user_id', $user->id)->where('kpi_template_id', $templateId)->get();

        if ($rows->isEmpty()) {
            return [];
        }
        if ($rows->contains(fn ($r) => $r->department_id === null)) {
            return [];   // unrestricted
        }

        return $rows->pluck('department_id')->filter()->unique()->values()->all();
    }
}
