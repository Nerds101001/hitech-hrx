<?php

namespace App\Services;

use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Turns a department-level KpiTemplate into one KpiAssignment per employee in
 * that department, and keeps those assignments in step when the template is
 * edited. The scoring engine only ever reads assignments, so nothing here
 * changes how a score is calculated.
 */
class KpiTemplateService
{
    /**
     * Employees a template covers: active users in its department, or every
     * active user in the tenant when the template has no department.
     */
    public static function audienceFor(KpiTemplate $t)
    {
        return User::query()
            ->where('status', 'active')
            ->when($t->tenant_id, fn ($q) => $q->where('tenant_id', $t->tenant_id))
            ->when($t->department_id, fn ($q) => $q->where('department_id', $t->department_id))
            ->get();
    }

    /**
     * Create the assignments this template is missing.
     *
     * Idempotent: an employee who already has an assignment from this template
     * is refreshed rather than given a duplicate, so Apply can be pressed twice
     * or run again after new joiners arrive.
     *
     * @return array{created:int, updated:int, skipped:int}
     */
    public static function apply(KpiTemplate $t, bool $refreshExisting = true): array
    {
        // A KRA is a heading that groups KPIs; it carries no marks of its own,
        // so it never becomes a scored assignment.
        if (!$t->is_active || $t->category === 'KRA') {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $audience = self::audienceFor($t);
        $existing = KpiAssignment::where('kpi_template_id', $t->id)
            ->pluck('id', 'user_id');

        $created = $updated = $skipped = 0;

        DB::transaction(function () use ($t, $audience, $existing, $refreshExisting, &$created, &$updated, &$skipped) {
            foreach ($audience as $user) {
                $payload = self::payloadFor($t, $user);

                if (isset($existing[$user->id])) {
                    if ($refreshExisting) {
                        // Keep whatever target this person has been given. It
                        // was seeded from the template when the assignment was
                        // created and belongs to them from then on.
                        KpiAssignment::where('id', $existing[$user->id])
                            ->update(Arr::except($payload, KpiTemplate::SEEDED_ONCE));
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                KpiAssignment::create($payload + [
                    'user_id'         => $user->id,
                    'kpi_template_id' => $t->id,
                    'created_by_id'   => $t->created_by_id,
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Columns on kpi_assignments that reject null. A template created without
     * them holds null in memory (the default lives in the database, not the
     * model), so they are coalesced here rather than failing the insert.
     */
    private const NOT_NULL_DEFAULTS = [
        'late_score'   => 0,
        'grace_days'   => 0,
        'late_policy'  => 'fixed',
        'data_source'  => 'manual',
        'max_marks'    => 0,
        'category'     => 'KPI',
        'frequency'    => 'monthly',
        'scoring_type' => 'timeline',
    ];

    /** The template fields an assignment inherits, plus the KRA heading. */
    private static function payloadFor(KpiTemplate $t, User $user): array
    {
        $payload = [];
        foreach (KpiTemplate::INHERITED as $f) {
            $payload[$f] = $t->{$f};
        }
        foreach (self::NOT_NULL_DEFAULTS as $col => $default) {
            if (array_key_exists($col, $payload) && $payload[$col] === null) {
                $payload[$col] = $default;
            }
        }
        $payload['tenant_id'] = $t->tenant_id ?? $user->tenant_id;
        $payload['is_active'] = true;
        // Denormalised so a scorecard can group by KRA without another join.
        $payload['kra_group'] = $t->parent?->title;

        return $payload;
    }

    /**
     * Assignments that exist for a template but whose owner has left the
     * department (or the company). Deactivated rather than deleted, so their
     * historical kpi_checks and scores survive.
     */
    public static function prune(KpiTemplate $t): int
    {
        $audienceIds = self::audienceFor($t)->pluck('id');

        return KpiAssignment::where('kpi_template_id', $t->id)
            ->where('is_active', true)
            ->whereNotIn('user_id', $audienceIds)
            ->update(['is_active' => false]);
    }

    /**
     * Give a newly-active employee every template that covers their department.
     * Safe to call repeatedly — apply() will not duplicate.
     */
    public static function applyAllForUser(User $user): int
    {
        $templates = KpiTemplate::where('is_active', true)
            ->where('category', 'KPI')   // KRA headings are not assigned
            ->when($user->tenant_id, fn ($q) => $q->where(function ($w) use ($user) {
                $w->whereNull('tenant_id')->orWhere('tenant_id', $user->tenant_id);
            }))
            ->where(function ($q) use ($user) {
                $q->whereNull('department_id')->orWhere('department_id', $user->department_id);
            })
            ->get();

        $n = 0;
        foreach ($templates as $t) {
            $already = KpiAssignment::where('kpi_template_id', $t->id)
                ->where('user_id', $user->id)->exists();
            if ($already) continue;

            $payload = self::payloadFor($t, $user);
            KpiAssignment::create($payload + [
                'user_id'         => $user->id,
                'kpi_template_id' => $t->id,
                'created_by_id'   => $t->created_by_id,
            ]);
            $n++;
        }

        return $n;
    }
}
