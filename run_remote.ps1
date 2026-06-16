$cmd = "cd /home/u989061032/domains/hitechgroup.in/public_html/hrx && php artisan tinker --execute=\"" . '$user = App\Models\User::find(503); if($user){ $user->status = App\Enums\UserAccountStatus::ACTIVE; $user->save(); echo \"Success\n\"; } else { echo \"Not found\n\"; }' . "\""
plink -ssh -P 65002 -l u989061032 -pw "Diplo@6589#" -batch 89.117.188.10 $cmd
