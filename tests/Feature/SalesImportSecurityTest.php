<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit, HIGH). The Sales Report import checked who
 * may import on the page only: preview and process took any post from anyone
 * inside the sales section, for any salesperson, and trusted the temp file
 * path from the form behind a bare 'temp/' prefix check ('temp/../../.env').
 * The Sales Target import had the same path check.
 */
class SalesImportSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private function person(string $dept, ?string $role = null, array $perms = []): User
    {
        Mail::fake();
        $d = Department::create(['name' => $dept . ' ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Imp ' . uniqid(), 'email' => 'qaimp' . uniqid() . '@example.test',
            'code' => 'QI' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => 'active', 'department_id' => $d->id,
        ]);
        if ($role) {
            $u->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function processPayload(User $salesperson, string $tempPath): array
    {
        return ['temp_path' => $tempPath, 'salesperson_id' => $salesperson->id, 'party_col' => 0, 'potential_col' => 1,
            'data_start_row' => 2, 'month_mappings' => '{}'];
    }

    public function test_a_read_only_sales_grant_cannot_import(): void
    {
        $viewer = $this->person('ZZ Accounts', 'employee', ['sales_analytics.view']);
        $target = $this->person('ZZ Sales');

        $this->actingAs($viewer)->post(route('sales-visits.pipeline.import.preview'), [
            'file' => UploadedFile::fake()->create('x.csv', 1, 'text/csv'), 'salesperson_id' => $target->id,
        ])->assertForbidden();
        $this->actingAs($viewer)->post(route('sales-visits.pipeline.import.process'), $this->processPayload($target, 'temp/pipeline_matrix_1_abcdefgh.csv'))
            ->assertForbidden();
    }

    public function test_the_file_path_cannot_walk_out_of_the_temp_folder(): void
    {
        $admin = $this->person('ZZ HR', 'admin');
        $target = $this->person('ZZ Sales');

        foreach (['temp/../../.env', 'temp/../../../etc/passwd', 'temp/pipeline_matrix_1_abcdefgh.csv'] as $path) {
            $this->actingAs($admin)->post(route('sales-visits.pipeline.import.process'), $this->processPayload($target, $path))
                ->assertRedirect(route('sales-visits.pipeline.import'))
                ->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid file reference'));
        }
    }

    public function test_only_a_salesperson_from_your_own_list(): void
    {
        // A New Biz user sees only the salespeople tagged to them.
        $nb = $this->person('ZZ New Biz Team', 'employee', ['sales_report.import']);
        $mine = $this->person('ZZ Sales');
        $other = $this->person('ZZ Sales');
        \App\Models\CcSalespersonMap::create(['cc_user_id' => $nb->id, 'sales_user_id' => $mine->id, 'role' => 'newbiz']);

        $this->actingAs($nb)->post(route('sales-visits.pipeline.import.preview'), [
            'file' => UploadedFile::fake()->create('x.csv', 1, 'text/csv'), 'salesperson_id' => $other->id,
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'your own list'));
    }

    public function test_the_target_import_path_cannot_walk_out_either(): void
    {
        $admin = $this->person('ZZ HR', 'admin');
        $sp = $this->person('ZZ Sales');

        $this->actingAs($admin)->post(route('sales-visits.sales-targets.import.store'), [
            'salesperson_id' => $sp->id, 'file_path' => 'temp_imports/../../.env', 'mapping' => ['month_year' => 0],
        ])->assertRedirect(route('sales-visits.sales-targets.import'))
          ->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid file reference'));
    }
}
