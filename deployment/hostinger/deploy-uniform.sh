#!/usr/bin/env bash
#
# Deploy the uniform-requests feature, which routes/tenant.php on production
# references but whose files were never uploaded.
#
#   bash deployment/hostinger/deploy-uniform.sh --dry-run
#   bash deployment/hostinger/deploy-uniform.sh
#
# The symptom this fixes: production logs
#   Class "App\Http\Controllers\tenant\UniformRequestController" does not exist
# every time anything enumerates routes, and a real user hit a 500 from
# /uniform-requests. It also blocks `route:cache` entirely, which matters on a
# node running at load 30.
#
# Every file here is NEW on production and the migration only CREATEs a table,
# so nothing existing is modified. Reverting is deleting five files; the table
# is left alone because dropping it would discard any request an employee had
# already submitted.

set -euo pipefail

cd "$(dirname "$0")/../.."

# shellcheck disable=SC1091
# The file begins with a UTF-8 BOM, which `source` would try to execute.
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="/c/Program Files/PuTTY/plink.exe"
PSCP="/c/Program Files/PuTTY/pscp.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
APP="$HRX_APP_ROOT"
PHP="$HRX_PHP"

DRY_RUN=0
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=1

remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }
upload() { "$PSCP" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$1" "$SSH:$2" >/dev/null; }

FILES=(
  app/Http/Controllers/tenant/UniformRequestController.php
  app/Models/UniformRequest.php
  database/migrations/2026_08_12_090000_create_uniform_requests_table.php
  resources/views/tenant/uniform-requests/index.blade.php
  resources/views/tenant/uniform-requests/manage.blade.php
)

STAMP="$(date +%Y%m%d-%H%M%S)"
REMOTE_HOME="$(remote 'echo $HOME' 2>/dev/null | tr -d '\r\n')"
BACKUP="$REMOTE_HOME/hrx-backups/uniform-$STAMP"

echo "════════════════════════════════════════════════════════════════════"
echo " uniform-requests — deploy the feature routes/tenant.php already calls"
echo "════════════════════════════════════════════════════════════════════"
echo "  target  : $SSH:$APP"
echo "  files   : ${#FILES[@]} (all new on production)"
echo "  schema  : 1 new table, nothing altered"
echo "  undo    : $BACKUP/REVERT.sh"
echo

for f in "${FILES[@]}"; do
  [[ -f "$f" ]] || { echo "  ABORT: $f missing locally"; exit 1; }
done
echo "  all ${#FILES[@]} files present locally"

# None of these should already exist. If one does, production has a version we
# did not put there and overwriting it blindly would be wrong.
existing="$(remote "cd $APP && ls -1 ${FILES[*]} 2>/dev/null | wc -l" | tr -d '\r\n')"
if [[ "$existing" != "0" ]]; then
  echo "  NOTE: $existing of these already exist on production and will be overwritten."
fi

if [[ $DRY_RUN -eq 1 ]]; then
  echo
  echo "── DRY RUN, nothing written ────────────────────────────────────────"
  printf '    %s\n' "${FILES[@]}"
  echo
  echo "  then: php artisan migrate --force   (creates uniform_requests)"
  echo "        php artisan route:clear / view:clear"
  exit 0
fi

echo
echo "── backup ──────────────────────────────────────────────────────────"
remote "mkdir -p $BACKUP"
printf '%s\n' "${FILES[@]}" > /tmp/uniform-files.txt
upload /tmp/uniform-files.txt "$BACKUP/new-files.txt"

cat > /tmp/UNIFORM_REVERT.sh <<REVERTEOF
#!/usr/bin/env bash
#
# Undo the uniform-requests deploy of $STAMP.
#
# Deletes the five files it added. The uniform_requests TABLE is deliberately
# left in place: it is new and unreferenced once the code is gone, whereas
# dropping it would discard any request an employee had already submitted.
#
# Note this restores the state where routes/tenant.php references a controller
# that does not exist -- which is what was logging errors in the first place.

set -euo pipefail
APP="$APP"
HERE="\$(cd "\$(dirname "\$0")" && pwd)"

deleted=0
while IFS= read -r f; do
  [ -z "\$f" ] && continue
  if [ -f "\$APP/\$f" ]; then rm -f "\$APP/\$f"; deleted=\$((deleted+1)); fi
done < "\$HERE/new-files.txt"

rmdir "\$APP/resources/views/tenant/uniform-requests" 2>/dev/null || true

cd "\$APP"
$PHP artisan route:clear >/dev/null 2>&1 || true
$PHP artisan view:clear  >/dev/null 2>&1 || true

echo "Reverted: deleted \$deleted file(s). uniform_requests table left intact."
REVERTEOF
upload /tmp/UNIFORM_REVERT.sh "$BACKUP/REVERT.sh"
remote "chmod +x $BACKUP/REVERT.sh"
echo "  revert script: $BACKUP/REVERT.sh"

echo
echo "── upload ──────────────────────────────────────────────────────────"
remote "cd $APP && mkdir -p resources/views/tenant/uniform-requests"
tar czf /tmp/uniform-payload.tar.gz "${FILES[@]}"
upload /tmp/uniform-payload.tar.gz "$APP/uniform-payload.tar.gz"
remote "cd $APP && tar xzf uniform-payload.tar.gz && rm -f uniform-payload.tar.gz && echo '  extracted'"

echo
echo "── migrate ─────────────────────────────────────────────────────────"
remote "cd $APP && $PHP artisan migrate --force 2>&1 | tail -6"

echo
echo "── clear caches ────────────────────────────────────────────────────"
remote "cd $APP && $PHP artisan route:clear >/dev/null && $PHP artisan view:clear >/dev/null && echo '  cleared'"

echo
echo "── verify ──────────────────────────────────────────────────────────"
remote "cd $APP && \
  echo -n '  controller resolves : '; $PHP -r 'require \"vendor/autoload.php\"; echo class_exists(\"App\\\\Http\\\\Controllers\\\\tenant\\\\UniformRequestController\") ? \"yes\" : \"NO\";'; echo; \
  echo -n '  table exists        : '; $PHP artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable(\"uniform_requests\") ? \"yes\" : \"NO\";' 2>/dev/null | tail -1; \
  echo -n '  route:list works    : '; $PHP artisan route:list --path=uniform 2>&1 | grep -c uniform || echo 0"

echo
echo " Done. Undo with:  bash $BACKUP/REVERT.sh"
