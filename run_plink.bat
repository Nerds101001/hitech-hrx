@echo off
REM Set HRX_SSH_PASSWORD before running (see deployment\README.md)
set "CMD=cd domains/hitechgroup.in/public_html/hrx && php artisan tinker --execute=\"\$depts = \App\Models\Department::with('users')->get(); foreach(\$depts as \$d) { echo \$d->name . ' (' . \$d->users->count() . ' users): '; echo \$d->users->pluck('first_name')->implode(', '); echo PHP_EOL; }\""
plink -batch -ssh -l u989061032 -pw "%HRX_SSH_PASSWORD%" 193.203.162.152 "%CMD%"
