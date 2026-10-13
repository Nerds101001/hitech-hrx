<?php

namespace App\Services;

use App\Models\SalesPipelineHandover;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hands a leaver's customers to a replacement from a chosen month on.
 *
 *   plan()   changes nothing: it says exactly what would move, what is unusual, and
 *            gives a fingerprint of that exact list.
 *   apply()  re-plans, refuses unless nothing has changed since the preview (same
 *            fingerprint) and there are no errors, then does everything in ONE
 *            transaction and keeps the lot as a batch that can be undone.
 *   revert() puts a batch back, and refuses if anything later has built on it.
 *
 * What moves
 *   - Sales Report rows: each role column the leaver holds (salesperson, CCare,
 *     New Biz, New Biz 2) takes the replacement for that role. The leaver stays
 *     credited for every month BEFORE the effective month (SalesPipelineHandover).
 *   - The leaver's additional-rep tags end at the effective month.
 *   - Client Master: salesperson / CCare / New Biz / billing / finance columns.
 *   - The CCare map: the replacement is added beside the leaver, never instead.
 *
 * What does NOT move (reported, never changed): monthly targets, tasks, trials.
 *
 * Sales Report rows are written with the query builder on purpose: saving the
 * model runs the New Biz -> CCare hook, which would rewrite ccare_from.
 */
class LeaverHandoverService
{
    /** The roles a replacement is chosen for, and the Sales Report columns each covers. */
    public const ROLES = [
        'salesperson' => ['label' => 'Salesperson', 'columns' => ['salesperson_id']],
        'ccare'       => ['label' => 'CCare',       'columns' => ['ccare_id']],
        'new_biz'     => ['label' => 'New Biz',     'columns' => ['new_biz_id', 'new_biz_2_id']],
    ];

    /** Client Master columns and the replacement role that fills each ('billing'/'finance' are optional extras). */
    private const CRM_COLUMNS = [
        'salesperson_id' => 'salesperson', 'ccare_id' => 'ccare', 'new_biz_id' => 'new_biz',
        'billing_id' => 'billing', 'finance_id' => 'finance',
    ];

    /** CCare-map role -> replacement role. */
    private const MAP_ROLE = ['ccare' => 'ccare', 'newbiz' => 'new_biz'];

    /** User::status is an enum: compare its value, never the object. */
    public static function statusOf(User $u): string
    {
        $s = $u->status;

        return $s instanceof \BackedEnum ? (string) $s->value : (string) $s;
    }

    /**
     * @param  array<string,int|null>  $replacements  role => user id (salesperson, ccare, new_biz, billing, finance)
     * @return array<string,mixed>
     */
    public static function plan(User $leaver, array $replacements, string $effectiveMonth): array
    {
        $errors = $warnings = [];
        $replacements = array_filter(array_map(fn ($v) => $v ? (int) $v : null, $replacements));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $effectiveMonth)) {
            $errors[] = 'The effective month must look like 2026-11.';
        } elseif ($effectiveMonth < now()->format('Y-m')) {
            $errors[] = "The effective month cannot be before the current month ({$effectiveMonth}): a month that has already started is never re-credited.";
        }
        // Two uses of the same tool: a leaver's whole book must be handed over (nothing may
        // be stranded), while a TRANSFER from someone still working may move only some roles.
        $leaverStatus = self::statusOf($leaver);
        $isTransfer = $leaverStatus === 'active';
        if (in_array($leaverStatus, ['onboarding', 'onboarding_submitted'], true)) {
            $errors[] = "{$leaver->first_name} {$leaver->last_name} has not started yet (status: {$leaverStatus}).";
        } elseif ($isTransfer) {
            $warnings[] = "{$leaver->first_name} {$leaver->last_name} is still working, so this is a transfer: any role with no replacement chosen stays with them.";
        }

        // The replacements.
        $people = User::whereIn('id', array_values($replacements))->get()->keyBy('id');
        foreach ($replacements as $role => $uid) {
            $u = $people->get($uid);
            if (!$u) {
                $errors[] = "Replacement for {$role} (user #{$uid}) does not exist.";
            } elseif (self::statusOf($u) !== 'active') {
                $errors[] = "{$u->first_name} {$u->last_name} (replacement for {$role}) is not an active employee.";
            } elseif ($u->id === $leaver->id) {
                $errors[] = 'A leaver cannot be their own replacement.';
            }
        }

        // Sales Report rows the leaver holds, per role column.
        $cols = array_merge(...array_column(self::ROLES, 'columns'));
        $rows = DB::table('sales_pipelines')->whereNull('deleted_at')
            ->where(function ($q) use ($cols, $leaver) {
                foreach ($cols as $i => $c) {
                    $i === 0 ? $q->where($c, $leaver->id) : $q->orWhere($c, $leaver->id);
                }
            })
            ->orderBy('id')->get(array_merge(['id', 'party_name'], $cols));

        $roleOfColumn = [];
        foreach (self::ROLES as $role => $def) {
            foreach ($def['columns'] as $c) {
                $roleOfColumn[$c] = $role;
            }
        }

        $moves = [];
        $missing = [];
        foreach ($rows as $row) {
            foreach ($cols as $c) {
                if ((int) $row->{$c} !== $leaver->id) {
                    continue;
                }
                $role = $roleOfColumn[$c];
                if (empty($replacements[$role])) {
                    $missing[$role] = ($missing[$role] ?? 0) + 1;
                    continue;
                }
                $moves[] = ['pipeline_id' => (int) $row->id, 'party' => $row->party_name, 'role' => $c, 'from' => $leaver->id, 'to' => $replacements[$role]];
            }
        }
        foreach ($missing as $role => $n) {
            if ($isTransfer) {
                continue;   // not moving that role
            }
            $errors[] = "{$n} customer(s) are held as " . self::ROLES[$role]['label'] . ' but no replacement was chosen for that role.';
        }

        // Additional-rep tags that end.
        $tags = DB::table('sales_pipeline_extra_reps as er')
            ->join('sales_pipelines as sp', 'sp.id', '=', 'er.sales_pipeline_id')
            ->whereNull('sp.deleted_at')->where('er.user_id', $leaver->id)->pluck('er.sales_pipeline_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        if (SalesPipelineHandover::hasAny() && $tags) {
            $ended = DB::table('sales_pipeline_handovers')->whereNull('reverted_at')->where('role', SalesPipelineHandover::EXTRA)
                ->where('from_user_id', $leaver->id)->pluck('sales_pipeline_id')->all();
            $tags = array_values(array_diff($tags, $ended));
        }

        // A replacement who already handed one of these customers on would make a loop in its history.
        if ($moves && SalesPipelineHandover::hasAny()) {
            $existing = DB::table('sales_pipeline_handovers')->whereNull('reverted_at')
                ->whereIn('sales_pipeline_id', array_column($moves, 'pipeline_id'))->get(['sales_pipeline_id', 'role', 'from_user_id', 'effective_month']);
            foreach ($moves as $m) {
                foreach ($existing as $e) {
                    if ((int) $e->sales_pipeline_id === $m['pipeline_id'] && $e->role === $m['role'] && (int) $e->from_user_id === $m['to']) {
                        $errors[] = "Customer #{$m['pipeline_id']} ({$m['party']}) was earlier handed on by the chosen replacement for the same role: pick someone else for it.";
                    }
                    if ((int) $e->sales_pipeline_id === $m['pipeline_id'] && $e->role === $m['role'] && $e->effective_month > $effectiveMonth) {
                        $errors[] = "Customer #{$m['pipeline_id']} ({$m['party']}) already has a later handover ({$e->effective_month}).";
                    }
                }
            }
        }

        // Client Master.
        $crm = [];
        if (Schema::hasTable('crm_client_mappings')) {
            foreach (self::CRM_COLUMNS as $col => $role) {
                $ids = DB::table('crm_client_mappings')->whereNull('deleted_at')->where($col, $leaver->id)->pluck('id');
                if ($ids->isEmpty()) {
                    continue;
                }
                if (empty($replacements[$role])) {
                    if ($isTransfer) {
                        continue;
                    }
                    $errors[] = "{$ids->count()} Client Master record(s) have the leaver as " . str_replace('_id', '', $col) . ' but no replacement was chosen for that role.';
                    continue;
                }
                foreach ($ids as $id) {
                    $crm[] = ['id' => (int) $id, 'column' => $col, 'from' => $leaver->id, 'to' => $replacements[$role]];
                }
            }
        }

        // CCare map: the replacement is ADDED alongside the leaver.
        $mapAdds = [];
        foreach (DB::table('cc_salesperson_map')->where('sales_user_id', $leaver->id)->get() as $r) {
            if (!empty($replacements['salesperson'])) {
                $mapAdds[] = ['cc_user_id' => (int) $r->cc_user_id, 'sales_user_id' => $replacements['salesperson'], 'role' => $r->role, 'tenant_id' => $r->tenant_id];
            } elseif (!$isTransfer) {
                $warnings[] = 'Mapped to a CCare person as a salesperson but no salesperson replacement chosen: the map is not carried over.';
                break;
            }
        }
        foreach (DB::table('cc_salesperson_map')->where('cc_user_id', $leaver->id)->get() as $r) {
            $rr = self::MAP_ROLE[$r->role ?? ''] ?? null;
            if ($rr && !empty($replacements[$rr])) {
                $mapAdds[] = ['cc_user_id' => $replacements[$rr], 'sales_user_id' => (int) $r->sales_user_id, 'role' => $r->role, 'tenant_id' => $r->tenant_id];
            } elseif (!$isTransfer) {
                $warnings[] = 'The leaver was a ' . ($r->role ?: 'CCare') . ' agent for ' . 'some salespeople but no matching replacement was chosen: those mappings are not carried over.';
                break;
            }
        }
        // Skip what is already there.
        $mapAdds = array_values(array_filter($mapAdds, fn ($a) => !DB::table('cc_salesperson_map')
            ->where('cc_user_id', $a['cc_user_id'])->where('sales_user_id', $a['sales_user_id'])->where('role', $a['role'])->exists()
            && $a['cc_user_id'] !== $a['sales_user_id']));
        $mapAdds = array_values(array_unique($mapAdds, SORT_REGULAR));

        // Money: what stays with the leaver and what goes to the replacement, to sanity-check the month.
        $pids = array_unique(array_column($moves, 'pipeline_id'));
        $stay = $go = 0.0;
        $goingSales = 0;
        if ($pids) {
            foreach (array_chunk($pids, 1000) as $chunk) {
                $stay += (float) DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $chunk)->where('month_year', '<', $effectiveMonth)->sum('sale_amount');
                $go   += (float) DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $chunk)->where('month_year', '>=', $effectiveMonth)->sum('sale_amount');
                $goingSales += DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $chunk)->where('month_year', '>=', $effectiveMonth)->where('sale_amount', '>', 0)->count();
            }
        }

        // Not moved, but the person should know.
        $notMoved = [
            'targets' => (int) DB::table('salesperson_targets')->where('salesperson_id', $leaver->id)->where('month_year', '>=', $effectiveMonth)->count(),
        ];
        if ($notMoved['targets']) {
            $warnings[] = "{$notMoved['targets']} monthly target row(s) from {$effectiveMonth} on belong to the leaver and are left as they are.";
        }

        $summary = [
            'customers'        => count($pids),
            'role_moves'       => count($moves),
            'by_role'          => array_count_values(array_column($moves, 'role')),
            'tags_ending'      => count($tags),
            'client_master'    => count($crm),
            'ccare_map_added'  => count($mapAdds),
            'sale_stays_L'     => round($stay, 2),
            'sale_moves_L'     => round($go, 2),
            'sale_month_rows'  => $goingSales,
        ];

        $fingerprint = sha1(json_encode([$leaver->id, $effectiveMonth, $moves, $tags, $crm, $mapAdds]));

        return compact('errors', 'warnings', 'moves', 'tags', 'crm', 'mapAdds', 'summary', 'notMoved', 'fingerprint')
            + ['leaver_id' => $leaver->id, 'effective_month' => $effectiveMonth, 'replacements' => $replacements];
    }

    /**
     * Do it. Returns the batch id.
     *
     * @throws \RuntimeException when there are errors, or the data changed since the preview
     */
    public static function apply(User $leaver, array $replacements, string $effectiveMonth, string $fingerprint, ?int $byUserId, ?string $note = null): int
    {
        $plan = self::plan($leaver, $replacements, $effectiveMonth);
        if ($plan['errors']) {
            throw new \RuntimeException('Cannot hand over: ' . implode(' ', $plan['errors']));
        }
        if (!hash_equals($plan['fingerprint'], $fingerprint)) {
            throw new \RuntimeException('The customers changed since the preview was made. Preview again before applying.');
        }
        if (!$plan['moves'] && !$plan['tags'] && !$plan['crm'] && !$plan['mapAdds']) {
            throw new \RuntimeException('Nothing to hand over.');
        }

        $batchId = DB::transaction(function () use ($plan, $leaver, $effectiveMonth, $byUserId, $note) {
            $now = now();
            $batchId = DB::table('sales_pipeline_handover_batches')->insertGetId([
                'leaver_id' => $leaver->id, 'effective_month' => $effectiveMonth,
                'replacements' => json_encode($plan['replacements']), 'summary' => json_encode($plan['summary']),
                'status' => 'applied', 'note' => $note, 'created_by' => $byUserId, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($plan['moves'] as $m) {
                DB::table('sales_pipeline_handovers')->insert([
                    'batch_id' => $batchId, 'sales_pipeline_id' => $m['pipeline_id'], 'role' => $m['role'],
                    'from_user_id' => $m['from'], 'to_user_id' => $m['to'], 'effective_month' => $effectiveMonth,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                // Only if it still holds the leaver: the preview and this are the same transaction's view of the data.
                $changed = DB::table('sales_pipelines')->where('id', $m['pipeline_id'])->where($m['role'], $m['from'])
                    ->update([$m['role'] => $m['to'], 'updated_at' => $now]);
                if ($changed !== 1) {
                    throw new \RuntimeException("Customer #{$m['pipeline_id']} changed while handing over; nothing was applied.");
                }
            }
            foreach ($plan['tags'] as $pid) {
                DB::table('sales_pipeline_handovers')->insert([
                    'batch_id' => $batchId, 'sales_pipeline_id' => $pid, 'role' => SalesPipelineHandover::EXTRA,
                    'from_user_id' => $leaver->id, 'to_user_id' => null, 'effective_month' => $effectiveMonth,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            foreach ($plan['crm'] as $c) {
                $changed = DB::table('crm_client_mappings')->where('id', $c['id'])->where($c['column'], $c['from'])
                    ->update([$c['column'] => $c['to'], 'updated_at' => $now]);
                if ($changed !== 1) {
                    throw new \RuntimeException("Client Master record #{$c['id']} changed while handing over; nothing was applied.");
                }
            }
            $added = [];
            foreach ($plan['mapAdds'] as $a) {
                $added[] = DB::table('cc_salesperson_map')->insertGetId($a + ['created_at' => $now, 'updated_at' => $now]);
            }

            DB::table('sales_pipeline_handover_batches')->where('id', $batchId)->update([
                'before_state' => json_encode(['crm' => $plan['crm'], 'map_added' => $added]),
            ]);

            return $batchId;
        });

        SalesPipelineHandover::flush();

        return $batchId;
    }

    /**
     * Everything one handover did, for the detail sheet and its export: each customer
     * with who it moved from and to, and what stays with the previous person versus
     * what goes to the new one. Null when there is no such batch.
     *
     * @return array<string,mixed>|null
     */
    public static function detail(int $batchId): ?array
    {
        $batch = DB::table('sales_pipeline_handover_batches')->where('id', $batchId)->first();
        if (!$batch) {
            return null;
        }

        $events = DB::table('sales_pipeline_handovers as h')
            ->leftJoin('sales_pipelines as sp', 'sp.id', '=', 'h.sales_pipeline_id')
            ->where('h.batch_id', $batchId)->orderBy('sp.party_name')->orderBy('h.id')
            ->get(['h.id', 'h.sales_pipeline_id', 'h.role', 'h.from_user_id', 'h.to_user_id', 'h.effective_month', 'h.reverted_at', 'sp.party_name']);

        $before = json_decode((string) $batch->before_state, true) ?: [];
        $crm = $before['crm'] ?? [];
        $mapIds = $before['map_added'] ?? [];

        $userIds = collect([$batch->leaver_id, $batch->created_by, $batch->reverted_by])
            ->merge($events->pluck('from_user_id'))->merge($events->pluck('to_user_id'))
            ->merge(array_column($crm, 'from'))->merge(array_column($crm, 'to'))->filter()->unique()->values();
        $maps = $mapIds ? DB::table('cc_salesperson_map')->whereIn('id', $mapIds)->get() : collect();
        $userIds = $userIds->merge($maps->pluck('cc_user_id'))->merge($maps->pluck('sales_user_id'))->unique()->values();
        $names = User::whereIn('id', $userIds)->get()->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);
        $name = fn ($id) => $id ? ($names[$id] ?? ('#' . $id)) : null;

        // Sales on each customer, split at the effective month.
        $money = [];
        $pids = $events->pluck('sales_pipeline_id')->unique()->values();
        foreach ($pids->chunk(1000) as $chunk) {
            foreach (DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $chunk)
                ->groupBy('sales_pipeline_id')
                ->selectRaw('sales_pipeline_id pid, SUM(CASE WHEN month_year < ? THEN sale_amount ELSE 0 END) stays, SUM(CASE WHEN month_year >= ? THEN sale_amount ELSE 0 END) goes', [$batch->effective_month, $batch->effective_month])
                ->get() as $m) {
                $money[(int) $m->pid] = ['stays' => (float) $m->stays, 'goes' => (float) $m->goes];
            }
        }

        $roleLabel = ['salesperson_id' => 'Salesperson', 'ccare_id' => 'CCare', 'new_biz_id' => 'New Biz', 'new_biz_2_id' => 'New Biz 2', SalesPipelineHandover::EXTRA => 'Additional rep (tag ends)'];
        $customers = $events->map(fn ($e) => [
            'pipeline_id' => (int) $e->sales_pipeline_id,
            'customer'    => $e->party_name ?: ('#' . $e->sales_pipeline_id),
            'role'        => $roleLabel[$e->role] ?? $e->role,
            'from'        => $name($e->from_user_id),
            'to'          => $e->to_user_id ? $name($e->to_user_id) : '— (tag ends)',
            'from_month'  => $e->effective_month,
            'stays'       => round($money[(int) $e->sales_pipeline_id]['stays'] ?? 0.0, 2),
            'goes'        => round($money[(int) $e->sales_pipeline_id]['goes'] ?? 0.0, 2),
        ])->values()->all();

        $crmNames = $crm ? DB::table('crm_client_mappings')->whereIn('id', array_column($crm, 'id'))->pluck('crm_company_name', 'id') : collect();
        $clientMaster = array_map(fn ($c) => [
            'customer' => $crmNames[$c['id']] ?? ('#' . $c['id']), 'field' => str_replace('_id', '', $c['column']),
            'from' => $name($c['from']), 'to' => $name($c['to']),
        ], $crm);

        $ccareMap = $maps->map(fn ($m) => [
            'ccare' => $name($m->cc_user_id), 'salesperson' => $name($m->sales_user_id), 'role' => $m->role ?: 'ccare',
        ])->values()->all();

        return [
            'batch'        => $batch,
            'summary'      => json_decode((string) $batch->summary, true) ?: [],
            'leaver'       => $name($batch->leaver_id),
            'by'           => $name($batch->created_by) ?: 'System',
            'reverted_by'  => $name($batch->reverted_by),
            'customers'    => $customers,
            'client_master' => $clientMaster,
            'ccare_map'    => $ccareMap,
        ];
    }

    /**
     * Undo a batch. Refused if a later handover touches the same customers, or a
     * customer was changed by hand since: then it reports what to look at.
     *
     * @return array{reverted:int, skipped:array<int,string>}
     */
    public static function revert(int $batchId, ?int $byUserId): array
    {
        $batch = DB::table('sales_pipeline_handover_batches')->where('id', $batchId)->first();
        if (!$batch || $batch->status !== 'applied') {
            throw new \RuntimeException('That handover is not there to undo (or was already undone).');
        }

        $events = DB::table('sales_pipeline_handovers')->where('batch_id', $batchId)->whereNull('reverted_at')->get();
        $later = DB::table('sales_pipeline_handovers')->whereNull('reverted_at')->where('batch_id', '<>', $batchId)
            ->whereIn('sales_pipeline_id', $events->pluck('sales_pipeline_id')->unique()->all())
            ->where('id', '>', $events->min('id') ?? 0)->exists();
        if ($later) {
            throw new \RuntimeException('A later handover builds on this one. Undo the later one first.');
        }

        // All or nothing. A customer edited by hand since the handover would, if the
        // history were removed but its column left alone, credit the past to the
        // replacement -- so any such customer stops the undo and is listed instead.
        $before = json_decode((string) $batch->before_state, true) ?: [];
        $moved = [];
        foreach ($events as $e) {
            if ($e->role !== SalesPipelineHandover::EXTRA
                && !DB::table('sales_pipelines')->where('id', $e->sales_pipeline_id)->where($e->role, $e->to_user_id)->exists()) {
                $moved[] = "Customer #{$e->sales_pipeline_id}: {$e->role} was changed after the handover.";
            }
        }
        foreach ($before['crm'] ?? [] as $c) {
            if (!DB::table('crm_client_mappings')->where('id', $c['id'])->where($c['column'], $c['to'])->exists()) {
                $moved[] = "Client Master #{$c['id']}: {$c['column']} was changed after the handover.";
            }
        }
        if ($moved) {
            throw new \RuntimeException('Cannot undo: ' . implode(' ', array_slice($moved, 0, 10)) . (count($moved) > 10 ? ' (and ' . (count($moved) - 10) . ' more)' : ''));
        }

        $skipped = [];
        $n = DB::transaction(function () use ($batchId, $events, $before, $byUserId) {
            $now = now();
            $n = 0;
            foreach ($events as $e) {
                if ($e->role !== SalesPipelineHandover::EXTRA) {
                    DB::table('sales_pipelines')->where('id', $e->sales_pipeline_id)->where($e->role, $e->to_user_id)
                        ->update([$e->role => $e->from_user_id, 'updated_at' => $now]);
                }
                DB::table('sales_pipeline_handovers')->where('id', $e->id)->update(['reverted_at' => $now, 'updated_at' => $now]);
                $n++;
            }

            foreach ($before['crm'] ?? [] as $c) {
                DB::table('crm_client_mappings')->where('id', $c['id'])->where($c['column'], $c['to'])
                    ->update([$c['column'] => $c['from'], 'updated_at' => $now]);
            }
            if (!empty($before['map_added'])) {
                DB::table('cc_salesperson_map')->whereIn('id', $before['map_added'])->delete();
            }

            DB::table('sales_pipeline_handover_batches')->where('id', $batchId)->update([
                'status' => 'reverted', 'reverted_by' => $byUserId, 'reverted_at' => $now, 'updated_at' => $now,
            ]);

            return $n;
        });

        SalesPipelineHandover::flush();

        return ['reverted' => $n, 'skipped' => $skipped];
    }
}
