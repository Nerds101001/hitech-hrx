<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A mapping row said "this agent supports this salesperson" but not in WHICH
 * capacity. For an agent in the combined "Customer Care + New Biz" department
 * that single row rendered under both the CCARE and New Biz pickers, so
 * removing them from one list left the row alive via the other and they
 * reappeared on refresh.
 *
 * The role is recorded on the mapping itself. Existing rows are backfilled from
 * the agent's department, and a combined-department agent is split into two
 * rows so current behaviour is preserved exactly. The old
 * (cc_user_id, sales_user_id) unique key has to make room for that pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cc_salesperson_map', 'role')) {
            Schema::table('cc_salesperson_map', function (Blueprint $table) {
                $table->string('role', 10)->nullable()->after('cc_user_id');
            });
        }

        $indexes = collect(DB::select('SHOW INDEX FROM cc_salesperson_map'))->pluck('Key_name')->unique();
        // The cc_user_id foreign key leans on the old unique index, so give it a
        // plain index of its own before that unique key is taken away.
        if (!$indexes->contains('cc_map_cc_user_idx')) {
            DB::statement('ALTER TABLE cc_salesperson_map ADD INDEX cc_map_cc_user_idx (cc_user_id)');
        }
        if ($indexes->contains('cc_salesperson_map_cc_user_id_sales_user_id_unique')) {
            DB::statement('ALTER TABLE cc_salesperson_map DROP INDEX cc_salesperson_map_cc_user_id_sales_user_id_unique');
        }
        if (!$indexes->contains('cc_map_sales_cc_role_unique')) {
            DB::statement('ALTER TABLE cc_salesperson_map
                           ADD UNIQUE cc_map_sales_cc_role_unique (sales_user_id, cc_user_id, role)');
        }

        $isCcare = fn($d) => str_contains($d, 'ccare') || str_contains($d, 'customer care');
        $isNewBiz = fn($d) => str_contains($d, 'new biz') || str_contains($d, 'new business');

        // Stamp the role on every row that still lacks one.
        $rows = DB::table('cc_salesperson_map as m')
            ->join('users as u', 'u.id', '=', 'm.cc_user_id')
            ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->whereNull('m.role')
            ->get(['m.id', 'd.name as dept']);
        foreach ($rows as $r) {
            $dept = strtolower((string) $r->dept);
            DB::table('cc_salesperson_map')->where('id', $r->id)
                ->update(['role' => $isNewBiz($dept) && !$isCcare($dept) ? 'newbiz' : 'ccare']);
        }

        // A combined-department agent needs a row for BOTH capacities, so the
        // pickers keep showing them exactly where they showed before.
        $all = DB::table('cc_salesperson_map as m')
            ->join('users as u', 'u.id', '=', 'm.cc_user_id')
            ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->get(['m.sales_user_id', 'm.cc_user_id', 'm.role', 'd.name as dept']);
        $have = $all->map(fn($r) => $r->sales_user_id . '|' . $r->cc_user_id . '|' . $r->role)->flip();

        foreach ($all as $r) {
            $dept = strtolower((string) $r->dept);
            if (!($isCcare($dept) && $isNewBiz($dept))) {
                continue;
            }
            foreach (['ccare', 'newbiz'] as $role) {
                $key = $r->sales_user_id . '|' . $r->cc_user_id . '|' . $role;
                if ($have->has($key)) {
                    continue;
                }
                DB::table('cc_salesperson_map')->insert([
                    'sales_user_id' => $r->sales_user_id,
                    'cc_user_id'    => $r->cc_user_id,
                    'role'          => $role,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $have[$key] = true;
            }
        }
    }

    public function down(): void
    {
        DB::statement('DELETE m1 FROM cc_salesperson_map m1
                       INNER JOIN cc_salesperson_map m2
                       WHERE m1.id > m2.id
                         AND m1.sales_user_id = m2.sales_user_id
                         AND m1.cc_user_id = m2.cc_user_id');
        DB::statement('ALTER TABLE cc_salesperson_map DROP INDEX cc_map_sales_cc_role_unique');
        DB::statement('ALTER TABLE cc_salesperson_map
                       ADD UNIQUE cc_salesperson_map_cc_user_id_sales_user_id_unique (cc_user_id, sales_user_id)');
        Schema::table('cc_salesperson_map', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
