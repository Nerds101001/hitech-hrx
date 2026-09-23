#!/usr/bin/env bash
#
# Deploys the audit-coverage work:
#   - attendances free-text columns widened (the "Data too long for 'notes'" 500)
#   - audits indexed, partitioned, de-noised, auditable_id widened for UUIDs
#   - the Auditable trait added to every loadable model on production
#
# Run from the repo root:   bash deployment/deploy-audit-coverage.sh
#
# WHY THIS PATCHES PROD RATHER THAN UPLOADING THE LOCAL MODELS
# ------------------------------------------------------------
# All 153 model files on production are wrapped as
#     <?php /* Protected by HRX Security */ eval(gzinflate(base64_decode('...')));
# and seven of them -- Attendance, CcAttendanceUnlock, Holiday, KpiAssignment,
# KpiTemplate, MindSpeak, User -- contain code that exists nowhere in git.
# Uploading the local copies would strip the wrapper from 153 files AND discard
# those seven files' prod-only changes. So the model step unwraps what is
# already on the server, adds the trait, and re-wraps it identically.
#
set -euo pipefail

cd "$(dirname "$0")/.."

set -a
. <(tr -d '\r' < deployment/.env.deploy | sed 's/^\xEF\xBB\xBF//')
set +a

PSCP="/c/Program Files/PuTTY/pscp.exe"
PLINK="/c/Program Files/PuTTY/plink.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
R="$HRX_APP_ROOT"
LPHP="${HRX_LOCAL_PHP:-/c/laragon/bin/php/php-8.3.28-Win32-vs16-x64/php.exe}"

run()  { "$PLINK" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }
push() { "$PSCP" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$1" "$SSH:$R/$2"; }

echo "==> 1/7  backing up"
run "cd $R && mkdir -p storage/deploy-backups \
  && B=storage/deploy-backups/audit-deploy-\$(date +%Y%m%d-%H%M%S) \
  && tar czf \$B-models.tar.gz app/Models \
  && tar czf \$B-misc.tar.gz config/audit.php routes/console.php app/Console/Commands/ArchiveAudits.php \
  && echo \"    \$B-{models,misc}.tar.gz\""

echo "==> 2/7  re-wrapping the two console commands in prod's envelope"
"$LPHP" -r '
$wrap = function (string $path): string {
    $src  = file_get_contents($path);
    $body = preg_replace("/^<\?php\s*/", "", $src, 1);
    $p    = base64_encode(gzdeflate($body, 9));
    if (gzinflate(base64_decode($p)) !== $body) { fwrite(STDERR, "round-trip failed: $path\n"); exit(1); }
    return "<?php\n/* Protected by HRX Security */\neval(gzinflate(base64_decode(\x27" . $p . "\x27)));";
};
$t = sys_get_temp_dir();
file_put_contents("$t/ArchiveAudits.obf.php", $wrap("app/Console/Commands/ArchiveAudits.php"));
file_put_contents("$t/MaintainAuditPartitions.obf.php", $wrap("app/Console/Commands/MaintainAuditPartitions.php"));
echo "    wrapped, round-trip verified\n";
'
TMPDIR_LOCAL="$("$LPHP" -r 'echo sys_get_temp_dir();')"

echo "==> 3/7  uploading migrations, config, routes, commands, test"
push "database/migrations/2026_09_23_100000_widen_attendance_free_text_columns.php" "database/migrations/"
push "database/migrations/2026_09_23_100100_scale_audits_table.php"                 "database/migrations/"
push "database/migrations/2026_09_23_100200_allow_non_integer_auditable_ids.php"    "database/migrations/"
push "config/audit.php"                                                             "config/audit.php"
push "routes/console.php"                                                           "routes/console.php"
push "tests/Feature/AuditableModelsAreCompatibleTest.php"                           "tests/Feature/"
push "$TMPDIR_LOCAL/ArchiveAudits.obf.php"            "app/Console/Commands/ArchiveAudits.php"
push "$TMPDIR_LOCAL/MaintainAuditPartitions.obf.php"  "app/Console/Commands/MaintainAuditPartitions.php"

echo "==> 4/7  running migrations"
run "cd $R && $HRX_PHP artisan migrate --force"

echo "==> 5/7  adding the Auditable trait to prod's own models"
push "deployment/prod_add_audit.php" "prod_add_audit.php"
echo "    -- dry run first --"
run "cd $R && $HRX_PHP prod_add_audit.php $R/app/Models"
echo "    -- applying --"
run "cd $R && $HRX_PHP prod_add_audit.php $R/app/Models --apply"

echo "==> 6/7  clearing caches"
run "cd $R && $HRX_PHP artisan view:clear && $HRX_PHP artisan config:clear"

echo "==> 7/7  verifying on the server"
push "deployment/verify_audit_deploy.php" "verify_audit_deploy.php"
run "cd $R && $HRX_PHP verify_audit_deploy.php"
run "cd $R && rm -f prod_add_audit.php verify_audit_deploy.php"

echo
echo "Done. Rollback: restore storage/deploy-backups/audit-deploy-*-models.tar.gz"
echo "and run 'artisan migrate:rollback --step=3'."
