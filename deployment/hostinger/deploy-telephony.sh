#!/usr/bin/env bash
#
# Deploy the telephony feature to production, revertibly.
#
#   bash deployment/hostinger/deploy-telephony.sh --dry-run   # show the plan
#   bash deployment/hostinger/deploy-telephony.sh             # do it
#   bash deployment/hostinger/deploy-telephony.sh --verify     # check a live deploy
#
# WHY THIS EXISTS RATHER THAN deploy.sh
# -------------------------------------
# Production is not at a git commit. Three files this feature touches ship
# OBFUSCATED there -- routes/api.php, routes/user.php and ClickToCallService --
# and two more (bootstrap/app.php, the menu JSON) are on an older baseline than
# git and carry prod-only state. Uploading the local copy of any of them would
# either destroy the obfuscation or roll production backwards.
#
# So: new files are uploaded whole, and the four files that already exist are
# PATCHED IN PLACE on the server, each with a one-line append or a targeted
# insert that leaves everything else byte-identical.
#
# EVERY run writes a backup and a REVERT.sh to
#   ~/hrx-backups/telephony-<timestamp>/
# Running that one script puts production back exactly as it was.

set -euo pipefail

cd "$(dirname "$0")/../.."
ROOT="$(pwd)"

# shellcheck disable=SC1091
# The file begins with a UTF-8 BOM, which `source` would try to execute.
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="/c/Program Files/PuTTY/plink.exe"
PSCP="/c/Program Files/PuTTY/pscp.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
APP="$HRX_APP_ROOT"
PHP="$HRX_PHP"

DRY_RUN=0
VERIFY_ONLY=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --verify)  VERIFY_ONLY=1 ;;
  esac
done

remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }
upload() { "$PSCP" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$1" "$SSH:$2" >/dev/null; }

# ─────────────────────────────────────────────────────────── what ships ─────

# Brand-new files. None of these exist on production, so uploading them cannot
# overwrite anything, and reverting is simply deleting them.
NEW_FILES=(
  config/telephony.php
  routes/telephony.php
  routes/telephony-web.php
  app/Http/Middleware/VerifyTelephonySignature.php
  app/Http/Controllers/Api/Telephony/TelephonyIngestController.php
  app/Http/Controllers/tenant/TelephonyRecordingController.php
  app/Http/Controllers/tenant/Telephony/TelephonyReportController.php
  app/Http/Controllers/tenant/Telephony/TelephonyAdminController.php
  app/Http/Controllers/tenant/Telephony/TelephonyConnectionController.php
  app/Models/Telephony/TelephonyAgentMap.php
  app/Models/Telephony/TelephonyAgentSession.php
  app/Models/Telephony/TelephonyAgentStateChange.php
  app/Models/Telephony/TelephonyCall.php
  app/Models/Telephony/TelephonyCallEvent.php
  app/Models/Telephony/TelephonyCallLeg.php
  app/Models/Telephony/TelephonyCredential.php
  app/Models/Telephony/TelephonyReconciliationRun.php
  app/Models/Telephony/TelephonyRecording.php
  app/Models/Telephony/TelephonyWebhookDelivery.php
  app/Services/Telephony/CallEventNormalizer.php
  app/Services/Telephony/CentroVisionClient.php
  app/Jobs/Telephony/ProcessTelephonyEvent.php
  app/Console/Commands/TelephonyReconcile.php
  database/migrations/2026_08_19_100000_create_telephony_agent_map_table.php
  database/migrations/2026_08_19_100100_create_telephony_webhook_deliveries_table.php
  database/migrations/2026_08_19_100200_create_telephony_call_records_table.php
  database/migrations/2026_08_19_100300_create_telephony_call_events_table.php
  database/migrations/2026_08_19_100400_create_telephony_call_legs_table.php
  database/migrations/2026_08_19_100500_create_telephony_recordings_table.php
  database/migrations/2026_08_19_100600_create_telephony_recording_access_logs_table.php
  database/migrations/2026_08_19_100700_create_telephony_agent_sessions_table.php
  database/migrations/2026_08_19_100800_create_telephony_agent_state_changes_table.php
  database/migrations/2026_08_19_100900_create_telephony_reconciliation_runs_table.php
  database/migrations/2026_08_20_120000_create_telephony_credentials_table.php
  resources/views/tenant/telephony/_partials/_header.blade.php
  resources/views/tenant/telephony/_partials/_helpers.blade.php
  resources/views/tenant/telephony/calls.blade.php
  resources/views/tenant/telephony/call-detail.blade.php
  resources/views/tenant/telephony/productivity.blade.php
  resources/views/tenant/telephony/analytics.blade.php
  resources/views/tenant/telephony/admin/connection.blade.php
  resources/views/tenant/telephony/admin/webhook-log.blade.php
  resources/views/tenant/telephony/admin/delivery-detail.blade.php
  resources/views/tenant/telephony/admin/agent-map.blade.php
  resources/views/tenant/telephony/admin/exceptions.blade.php
  resources/views/tenant/telephony/admin/reconciliation.blade.php
)

# Files that already exist on production and are byte-identical to the git
# baseline, so the local copy is a safe whole-file replacement. Verified by
# md5 before anything is written -- if prod has drifted since, we stop.
REPLACE_FILES=(
  app/Providers/AppServiceProvider.php
  config/permissions_registry.php
)

# Files patched IN PLACE on the server. Never uploaded from here.
PATCH_FILES=(
  routes/api.php
  routes/user.php
  bootstrap/app.php
  resources/menu/tenantVerticalMenu.json
)

STAMP="$(date +%Y%m%d-%H%M%S)"
# Resolved to an absolute path: pscp does NOT expand $HOME in a remote
# destination, it uses it literally and fails.
REMOTE_HOME="$(remote 'echo $HOME' 2>/dev/null | tr -d '\r\n')"
BACKUP="$REMOTE_HOME/hrx-backups/telephony-$STAMP"

echo "════════════════════════════════════════════════════════════════════"
echo " HRX telephony deploy"
echo "════════════════════════════════════════════════════════════════════"
echo "  target        : $SSH:$APP"
echo "  new files     : ${#NEW_FILES[@]}"
echo "  replaced      : ${#REPLACE_FILES[@]}  (verified identical to baseline first)"
echo "  patched live  : ${#PATCH_FILES[@]}  (append / targeted insert only)"
echo "  migrations    : new tables only, nothing altered or dropped"
echo "  backup + undo : $BACKUP/REVERT.sh"
echo

if [[ $VERIFY_ONLY -eq 1 ]]; then
  echo "── verifying the live deploy ───────────────────────────────────────"
  remote "cd $APP && \
    echo -n '  telephony routes registered : '; $PHP artisan route:list --path=telephony 2>/dev/null | grep -c telephony; \
    echo -n '  telephony tables            : '; $PHP artisan tinker --execute='echo count(\\Illuminate\\Support\\Facades\\DB::select(\"SHOW TABLES LIKE \\\"telephony%\\\"\"));' 2>/dev/null | tail -1"
  echo
  for url in "https://hrx.hitechgroup.in/api/telephony/v1/health" "https://hrx.hitechgroup.in/login"; do
    printf '  %-58s %s\n' "$url" "$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$url")"
  done
  echo "  (401 on /health is correct — it is unsigned. 200 on /login means the site is up.)"
  exit 0
fi

if [[ $DRY_RUN -eq 1 ]]; then
  echo "── DRY RUN. Nothing will be written. ───────────────────────────────"
  echo
  echo "  New files to upload:"
  for f in "${NEW_FILES[@]}"; do
    [[ -f "$f" ]] || { echo "    MISSING LOCALLY: $f"; continue; }
    printf '    %s\n' "$f"
  done
  echo
  echo "  Whole-file replacements (only if prod still matches the baseline):"
  printf '    %s\n' "${REPLACE_FILES[@]}"
  echo
  echo "  Patched in place on the server:"
  echo "    routes/api.php                    append: require __DIR__ . '/telephony.php';"
  echo "    routes/user.php                   append: require __DIR__ . '/telephony-web.php';"
  echo "    bootstrap/app.php                 insert: telephony.signed alias + nightly schedule"
  echo "    resources/menu/tenantVerticalMenu.json  insert: Telephony menu group"
  echo
  echo "  NOT deployed, deliberately:"
  echo "    app/Services/ClickToCallService.php   obfuscated on prod; the ref_id"
  echo "                                          change is unusable until the vendor"
  echo "                                          echoes it back, so it is not worth"
  echo "                                          decode/re-obfuscate risk today."
  echo "    the 2026-08-18 telephony feature      production does not have it, and it"
  echo "                                          carries a MOCK call service."
  exit 0
fi

# ───────────────────────────────────────────────────── pre-flight checks ────

echo "── pre-flight ──────────────────────────────────────────────────────"

for f in "${NEW_FILES[@]}" "${REPLACE_FILES[@]}"; do
  [[ -f "$f" ]] || { echo "  ABORT: $f is missing locally"; exit 1; }
done
echo "  all ${#NEW_FILES[@]} new + ${#REPLACE_FILES[@]} replacement files present locally"

# Refuse to overwrite a file that production has changed since the baseline.
for f in "${REPLACE_FILES[@]}"; do
  baseline="$(git show fea8e2634:"$f" | md5sum | cut -d' ' -f1)"
  mine="$(md5sum "$f" | cut -d' ' -f1)"
  prod="$(remote "cd $APP && md5sum $f" 2>/dev/null | cut -d' ' -f1)"

  if [[ "$prod" == "$baseline" ]]; then
    echo "  $f untouched since the baseline — safe to replace"
  elif [[ "$prod" == "$mine" ]]; then
    # Our own previous deploy. Overwriting it with the identical file is a
    # no-op, and refusing here would make every deploy after the first one
    # impossible.
    echo "  $f already matches this build — safe to replace"
  else
    echo "  ABORT: $f has been edited on production."
    echo "         prod=$prod  baseline=$baseline  local=$mine"
    echo "         Someone changed it on the server. Patch it by hand rather"
    echo "         than overwriting whatever they did."
    exit 1
  fi
done

# Refuse to run twice.
if remote "test -f $APP/routes/telephony.php" 2>/dev/null; then
  echo
  echo "  NOTE: routes/telephony.php already exists on production."
  echo "        This looks like a re-run. Files will be overwritten and the"
  echo "        in-place patches will be skipped if already applied."
fi

# ──────────────────────────────────────────────────────────── backup ────────

echo
echo "── backup ──────────────────────────────────────────────────────────"

remote "set -e
  mkdir -p $BACKUP
  cd $APP
  # Only the files that already exist need backing up; the new ones are
  # reverted by deletion.
  tar czf $BACKUP/existing-files.tar.gz ${PATCH_FILES[*]} ${REPLACE_FILES[*]}
  echo '  backed up:'; tar tzf $BACKUP/existing-files.tar.gz | sed 's/^/    /'
"

# The list of files the revert must delete.
printf '%s\n' "${NEW_FILES[@]}" > /tmp/telephony-new-files.txt
upload /tmp/telephony-new-files.txt "$BACKUP/new-files.txt"

# ─────────────────────────────────────────────────── the revert script ──────

cat > /tmp/REVERT.sh <<REVERTEOF
#!/usr/bin/env bash
#
# Undo the telephony deploy of $STAMP.
#
#   bash ~/hrx-backups/telephony-$STAMP/REVERT.sh
#
# Restores every pre-existing file byte-for-byte and deletes every file the
# deploy added. Takes about five seconds.
#
# The 10 telephony_* database tables are deliberately LEFT IN PLACE. They are
# new, nothing else references them, and an empty unused table costs nothing --
# whereas dropping them would throw away any call data already received, which
# is not recoverable. To remove them as well, uncomment the block at the end.

set -euo pipefail
APP="$APP"
HERE="\$(cd "\$(dirname "\$0")" && pwd)"

echo "Reverting the telephony deploy of $STAMP..."

cd "\$APP"

# 1. Delete everything the deploy added.
deleted=0
while IFS= read -r f; do
  [ -z "\$f" ] && continue
  if [ -f "\$APP/\$f" ]; then rm -f "\$APP/\$f"; deleted=\$((deleted+1)); fi
done < "\$HERE/new-files.txt"
echo "  deleted \$deleted added file(s)"

# Tidy up directories the deploy created, if they are now empty.
for d in app/Models/Telephony app/Services/Telephony app/Jobs/Telephony \\
         app/Http/Controllers/Api/Telephony app/Http/Controllers/tenant/Telephony \\
         resources/views/tenant/telephony/_partials resources/views/tenant/telephony/admin \\
         resources/views/tenant/telephony; do
  rmdir "\$APP/\$d" 2>/dev/null || true
done

# 2. Put the pre-existing files back exactly as they were.
tar xzf "\$HERE/existing-files.tar.gz" -C "\$APP"
echo "  restored \$(tar tzf "\$HERE/existing-files.tar.gz" | wc -l) original file(s)"

# 3. Clear the caches, or Laravel keeps serving the routes that no longer exist.
$PHP artisan route:clear >/dev/null 2>&1 || true
$PHP artisan view:clear  >/dev/null 2>&1 || true
$PHP artisan config:clear >/dev/null 2>&1 || true
echo "  caches cleared"

echo
echo "Reverted. The telephony_* tables were left in place (see the note at the"
echo "top of this script). The site should be exactly as it was before $STAMP."

# --- To drop the tables as well, uncomment: ---------------------------------
# $PHP artisan tinker --execute='
#   foreach (["telephony_reconciliation_runs","telephony_agent_state_changes",
#             "telephony_agent_sessions","telephony_recording_access_logs",
#             "telephony_recordings","telephony_call_legs","telephony_call_events",
#             "telephony_call_records","telephony_webhook_deliveries",
#             "telephony_agent_map"] as \\\$t) {
#     \\Illuminate\\Support\\Facades\\Schema::dropIfExists(\\\$t);
#   }
#   \\Illuminate\\Support\\Facades\\DB::table("migrations")
#     ->where("migration","like","2026_08_19_10%")->delete();
#   echo "telephony tables dropped\\n";'
REVERTEOF

upload /tmp/REVERT.sh "$BACKUP/REVERT.sh"
remote "chmod +x $BACKUP/REVERT.sh"
echo "  revert script written to $BACKUP/REVERT.sh"

# ──────────────────────────────────────────────────────────── upload ────────

echo
echo "── upload ──────────────────────────────────────────────────────────"

# Create the directories first; pscp will not.
remote "cd $APP && mkdir -p \
  app/Models/Telephony app/Services/Telephony app/Jobs/Telephony \
  app/Http/Controllers/Api/Telephony app/Http/Controllers/tenant/Telephony \
  resources/views/tenant/telephony/_partials resources/views/tenant/telephony/admin"

tar czf /tmp/telephony-payload.tar.gz "${NEW_FILES[@]}" "${REPLACE_FILES[@]}"
echo "  payload: $(du -h /tmp/telephony-payload.tar.gz | cut -f1)"
upload /tmp/telephony-payload.tar.gz "$APP/telephony-payload.tar.gz"
remote "cd $APP && tar xzf telephony-payload.tar.gz && rm -f telephony-payload.tar.gz && echo '  extracted in place'"

# ───────────────────────────────────────────────────────────── patch ────────

echo
echo "── patch the four existing files ───────────────────────────────────"

remote "set -e
cd $APP

# routes/api.php -- append the ingest routes. The obfuscated payload above is
# untouched; PHP simply executes the require afterwards.
if ! grep -q \"telephony.php\" routes/api.php; then
  printf \"\\nrequire __DIR__ . '/telephony.php';\\n\" >> routes/api.php
  echo '  routes/api.php     + require telephony.php'
else
  echo '  routes/api.php     already patched'
fi

# routes/user.php -- same, for the screens.
if ! grep -q \"telephony-web.php\" routes/user.php; then
  printf \"\\nrequire __DIR__ . '/telephony-web.php';\\n\" >> routes/user.php
  echo '  routes/user.php    + require telephony-web.php'
else
  echo '  routes/user.php    already patched'
fi
"

# bootstrap/app.php and the menu need a real insert, so do it with PHP on the
# server rather than sed -- a botched regex on bootstrap/app.php takes the whole
# site down.
upload deployment/hostinger/patch-telephony-remote.php "$APP/patch-telephony-remote.php"
remote "cd $APP && $PHP patch-telephony-remote.php && rm -f patch-telephony-remote.php"

# ─────────────────────────────────────────────────────────── migrate ────────

echo
echo "── migrate ─────────────────────────────────────────────────────────"
remote "cd $APP && $PHP artisan migrate --force 2>&1 | tail -14"

# ──────────────────────────────────────────────────────────── caches ────────

echo
echo "── clear caches ────────────────────────────────────────────────────"
remote "cd $APP && $PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan config:clear && echo '  cleared'"

# ───────────────────────────────────────────────────────────── verify ───────

echo
echo "── verify ──────────────────────────────────────────────────────────"
bash "$0" --verify

echo
echo "════════════════════════════════════════════════════════════════════"
echo " Done. To undo everything:"
echo "   ssh -p $HRX_SSH_PORT $SSH"
echo "   bash $BACKUP/REVERT.sh"
echo "════════════════════════════════════════════════════════════════════"
