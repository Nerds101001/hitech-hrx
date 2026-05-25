<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FindUserByEmail extends Command
{
    protected $signature = 'user:find {search}';
    protected $description = 'Find user by partial email';

    public function handle()
    {
        $search = $this->argument('search');

        $users = User::withoutGlobalScopes()
            ->where('email', 'LIKE', "%{$search}%")
            ->whereNull('deleted_at')
            ->get(['id', 'first_name', 'last_name', 'email', 'status']);

        if ($users->isEmpty()) {
            $this->warn("No users found matching: {$search}");
            return;
        }

        $rows = $users->map(fn($u) => [
            $u->id,
            $u->email,
            $u->first_name . ' ' . $u->last_name,
            $u->status instanceof \UnitEnum ? $u->status->value : $u->status,
        ])->toArray();

        $this->table(['ID', 'Email', 'Name', 'Status'], $rows);
    }
}
