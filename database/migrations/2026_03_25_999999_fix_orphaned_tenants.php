<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration {
    public function up(): void
    {
        // Find the first admin to use as the default tenant for existing records
        $admin = User::role('admin')->first() ?: User::first();
        if ($admin && $admin->tenant_id) {
            $tid = $admin->tenant_id;
            
            // Using DB raw to bypass global scopes for data fix
            \Illuminate\Support\Facades\DB::table('leave_types')->whereNull('tenant_id')->update(['tenant_id' => $tid]);
            \Illuminate\Support\Facades\DB::table('assets')->whereNull('tenant_id')->update(['tenant_id' => $tid]);
            \Illuminate\Support\Facades\DB::table('asset_categories')->whereNull('tenant_id')->update(['tenant_id' => $tid]);
            \Illuminate\Support\Facades\DB::table('leave_balances')->whereNull('tenant_id')->update(['tenant_id' => $tid]);
            \Illuminate\Support\Facades\DB::table('user_devices')->whereNull('tenant_id')->update(['tenant_id' => $tid]);
            
            echo "Successfully fixed data for tenant: $tid\n";
        } else {
            echo "No admin or tenant found. Skipping data migration.\n";
        }
    }

    public function down(): void
    {
        // No reverse logic needed
    }
};
