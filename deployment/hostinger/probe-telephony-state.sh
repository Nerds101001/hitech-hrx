#!/usr/bin/env bash
#
# Read-only look at production before an incremental telephony deploy.
#
#   bash deployment/hostinger/probe-telephony-state.sh
#
# Writes nothing. Answers the three questions worth knowing before touching a
# server that is not at a git commit: does prod's copy of each file I am about
# to replace still match what was deployed, what migrations are pending, and is
# anything else queued that a bare `artisan migrate` would drag in with mine.
set -euo pipefail

cd "$(dirname "$0")/../.."

# The file begins with a UTF-8 BOM, which `source` would try to execute.
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="/c/Program Files/PuTTY/plink.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
APP="$HRX_APP_ROOT"
PHP="$HRX_PHP"

remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }

FILES=(
  app/Http/Middleware/VerifyTelephonySignature.php
  app/Http/Controllers/Api/Telephony/TelephonyIngestController.php
  app/Services/Telephony/CallEventNormalizer.php
  app/Models/Telephony/TelephonyRecording.php
  app/Http/Controllers/tenant/TelephonyRecordingController.php
)

echo "── files to be replaced ────────────────────────────────────────────"
echo "   prod md5 vs the last-deployed local version (HEAD~1 of each)"
echo
for f in "${FILES[@]}"; do
  prod=$(remote "md5sum $APP/$f 2>/dev/null | cut -d' ' -f1" | tr -d '\r\n')
  # What prod SHOULD hold: the version before this session's edits.
  was=$(git show "HEAD~2:$f" 2>/dev/null | md5sum | cut -d' ' -f1)
  now=$(md5sum "$f" | cut -d' ' -f1)
  if [[ -z "$prod" ]]; then
    state="ABSENT ON PROD"
  elif [[ "$prod" == "$now" ]]; then
    state="already current"
  elif [[ "$prod" == "$was" ]]; then
    state="as expected, safe to replace"
  else
    state="!! DRIFTED - prod differs from both"
  fi
  printf '  %-62s %s\n' "$(basename "$f")" "$state"
done

echo
echo "── pending migrations on production ────────────────────────────────"
remote "cd $APP && $PHP artisan migrate:status 2>&1 | grep -i pending | head -40" || echo "  (none reported)"

echo
echo "── telephony tables present ────────────────────────────────────────"
remote "cd $APP && $PHP artisan tinker --execute='foreach (\\Illuminate\\Support\\Facades\\DB::select(\"SHOW TABLES LIKE \\\"telephony%\\\"\") as \\\$t) { echo implode(\"\",(array)\\\$t), \"\\n\"; }'" 2>/dev/null | grep telephony || echo "  (none)"

echo
echo "── does telephony_recordings already have the new columns? ─────────"
remote "cd $APP && $PHP artisan tinker --execute='echo implode(\", \", \\Illuminate\\Support\\Facades\\Schema::getColumnListing(\"telephony_recordings\"));'" 2>/dev/null | tail -2
