#!/usr/bin/env bash
#
# Incremental telephony deploy: pushed recording URLs, and /health naming the
# caller back to itself.
#
#   bash deployment/hostinger/deploy-telephony-recordings.sh --check    # verify only, writes nothing
#   bash deployment/hostinger/deploy-telephony-recordings.sh            # deploy
#
# WHY NOT deploy-telephony.sh
# ---------------------------
# That script is the first-run installer: it uploads ~50 new files and patches
# routes/api.php, bootstrap/app.php and the menu JSON. All of that is already
# done. Re-running it to ship five changed files would re-patch live route
# files for no reason, which is the kind of unnecessary risk that turns a small
# deploy into an outage.
#
# WHAT MAKES THIS ONE DIFFERENT
# -----------------------------
# All five files ship OBFUSCATED on production -- verified, not assumed. So
# each is obfuscated locally first and the obfuscated copy is uploaded.
# Uploading the plain local file would strip protection somebody deliberately
# applied.
#
# Before overwriting anything, production's own copy is decoded and compared
# AS TOKENS against the git baseline it should still match. Bytes cannot answer
# that question -- obfuscation strips comments and collapses whitespace by
# definition, so the bytes always differ. Tokens answer the one that matters:
# has anyone changed the code on the server since it was deployed?
#
# There is also a migration, which is the part that cannot simply be deleted to
# undo. It only ADDS two nullable columns, so the revert drops them and the
# rollback is exact.
#
# EVERY run writes a backup and a REVERT.sh to ~/hrx-backups/telrec-<stamp>/.
set -euo pipefail

cd "$(dirname "$0")/../.."
ROOT="$(pwd)"

set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PHP_LOCAL="${PHP_LOCAL:-$(command -v php || echo /c/laragon/bin/php/php-8.3.28-Win32-vs16-x64/php.exe)}"
PLINK="/c/Program Files/PuTTY/plink.exe"
PSCP="/c/Program Files/PuTTY/pscp.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
APP="$HRX_APP_ROOT"
PHP="$HRX_PHP"

TOKENS="deployment/hostinger/php-tokens.php"
OBF="deployment/hostinger/obfuscate-one.php"

CHECK_ONLY=0
[[ "${1:-}" == "--check" ]] && CHECK_ONLY=1

remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }
upload() { "$PSCP" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$1" "$SSH:$2" >/dev/null; }
fetch()  { "$PSCP" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH:$1" "$2" >/dev/null; }

# file : the git ref whose content production should currently match
declare -a TO_UPLOAD=()

declare -a CHANGED=(
  "app/Http/Middleware/VerifyTelephonySignature.php|26d3f598a~1"
  "app/Http/Controllers/Api/Telephony/TelephonyIngestController.php|26d3f598a~1"
  "app/Services/Telephony/CallEventNormalizer.php|fba608976~1"
  "app/Models/Telephony/TelephonyRecording.php|fba608976~1"
  "app/Http/Controllers/tenant/TelephonyRecordingController.php|fba608976~1"
)

MIGRATION="database/migrations/2026_09_07_120000_add_recording_url_to_telephony_recordings_table.php"

STAMP="$(date +%Y%m%d-%H%M%S)"
REMOTE_HOME="$(remote 'echo $HOME' 2>/dev/null | tr -d '\r\n')"
BACKUP="$REMOTE_HOME/hrx-backups/telrec-$STAMP"
WORK="${SCRATCH:-/tmp}/telrec-$STAMP"
mkdir -p "$WORK"

echo "════════════════════════════════════════════════════════════════════"
echo " HRX telephony - recording URL + /health vendor label"
echo "════════════════════════════════════════════════════════════════════"
echo "  target   : $SSH:$APP"
echo "  files    : ${#CHANGED[@]} (obfuscated, as production has them)"
echo "  migration: adds recording_url + url_expires_at, both nullable"
echo "  backup   : $BACKUP/REVERT.sh"
echo

# ───────────────────────────────────────────── 1. has production drifted? ────
echo "── verifying production still matches its baseline ─────────────────"
DRIFT=0
for entry in "${CHANGED[@]}"; do
  f="${entry%%|*}"; ref="${entry##*|}"
  base="$(basename "$f")"

  fetch "$APP/$f" "$WORK/$base.prod" || { echo "  !! could not fetch $f"; DRIFT=1; continue; }
  "$PHP_LOCAL" "$OBF" --decode "$WORK/$base.prod" > "$WORK/$base.prod.php"
  git show "$ref:$f" > "$WORK/$base.base.php"

  prod="$("$PHP_LOCAL" "$TOKENS" "$WORK/$base.prod.php" | md5sum | cut -d' ' -f1)"
  was="$("$PHP_LOCAL" "$TOKENS" "$WORK/$base.base.php" | md5sum | cut -d' ' -f1)"
  now="$("$PHP_LOCAL" "$TOKENS" "$f" | md5sum | cut -d' ' -f1)"

  # Three outcomes, not two. Production is at no git commit and its parts were
  # deployed at different times, so "already carries this change" is a normal
  # state -- treating it as drift would refuse a perfectly safe deploy.
  if [[ "$prod" == "$now" ]]; then
    printf '  %-46s already current - skipping\n' "$base"
  elif [[ "$prod" == "$was" ]]; then
    printf '  %-46s at the expected baseline - will upload\n' "$base"
    TO_UPLOAD+=("$f")
  else
    printf '  %-46s !! MATCHES NEITHER - STOP\n' "$base"
    DRIFT=1
  fi
done

if [[ $DRIFT -eq 1 ]]; then
  echo
  echo "  STOPPING. Production's code is not what this deploy expects to replace."
  echo "  Someone changed it on the server, or it was deployed from a different"
  echo "  commit. Overwriting now would silently discard that work."
  echo "  Decoded copies for inspection: $WORK"
  exit 1
fi

echo
echo "── target column state ─────────────────────────────────────────────"
COLS="$(remote "cd $APP && $PHP artisan tinker --execute='echo implode(\",\", \\Illuminate\\Support\\Facades\\Schema::getColumnListing(\"telephony_recordings\"));' 2>/dev/null | tail -1" | tr -d '\r')"
if [[ "$COLS" == *recording_url* ]]; then
  echo "  recording_url already present - migration will be a no-op"
  MIGRATE_NEEDED=0
else
  echo "  recording_url absent - migration will add it"
  MIGRATE_NEEDED=1
fi

echo
echo "  ${#TO_UPLOAD[@]} of ${#CHANGED[@]} files need uploading."

if [[ $CHECK_ONLY -eq 1 ]]; then
  echo
  echo "── --check: nothing was written. ───────────────────────────────────"
  exit 0
fi

if [[ ${#TO_UPLOAD[@]} -eq 0 && $MIGRATE_NEEDED -eq 0 ]]; then
  echo
  echo "  Production is already up to date. Nothing to do."
  exit 0
fi

# ───────────────────────────────────────────────────── 2. back production up ─
echo
echo "── backing up ──────────────────────────────────────────────────────"
remote "mkdir -p $BACKUP/files"
for f in "${TO_UPLOAD[@]}"; do
  remote "mkdir -p $BACKUP/files/$(dirname "$f") && cp -p $APP/$f $BACKUP/files/$f"
  printf '  saved %s\n' "$f"
done

# ──────────────────────────────────────────── 3. obfuscate, then upload ──────
echo
echo "── uploading (obfuscated) ──────────────────────────────────────────"
for f in "${TO_UPLOAD[@]}"; do
  base="$(basename "$f")"
  "$PHP_LOCAL" "$OBF" "$f" "$WORK/$base.obf"

  # Never ship something that will not parse. A fatal here takes the whole
  # site down, not just telephony.
  "$PHP_LOCAL" -l "$WORK/$base.obf" >/dev/null || {
    echo "  !! $base failed a syntax check after obfuscation - STOPPING"; exit 1; }

  upload "$WORK/$base.obf" "$APP/$f"
  printf '  uploaded %-52s %s bytes\n' "$f" "$(wc -c < "$WORK/$base.obf" | tr -d ' ')"
done

if [[ $MIGRATE_NEEDED -eq 1 ]]; then
  upload "$MIGRATION" "$APP/$MIGRATION"
  echo "  uploaded $MIGRATION"
fi

# ─────────────────────────────────────────────────────────── 4. migrate ──────
if [[ $MIGRATE_NEEDED -eq 1 ]]; then
  echo
  echo "── running the migration ───────────────────────────────────────────"
  # --path, not a bare migrate: a bare `artisan migrate` would also run every
  # other pending migration on this server, which is not what was approved.
  remote "cd $APP && $PHP artisan migrate --force --path=$MIGRATION 2>&1 | tail -5"
fi

# ───────────────────────────────────────────────────── 5. clear caches ───────
echo
echo "── clearing compiled state ─────────────────────────────────────────"
remote "cd $APP && $PHP artisan config:clear >/dev/null 2>&1; $PHP artisan cache:clear >/dev/null 2>&1; echo '  done'"

# ────────────────────────────────────────────────────── 6. write REVERT ──────
cat > "$WORK/REVERT.sh" <<REVERT
#!/usr/bin/env bash
# Undo the telephony recording-URL deploy of $STAMP.
set -euo pipefail
APP="$APP"
HERE="\$(cd "\$(dirname "\$0")" && pwd)"

echo "restoring files..."
cd "\$HERE/files"
find . -type f | while read -r f; do
  cp -p "\$f" "\$APP/\${f#./}"
  echo "  \${f#./}"
done

echo "dropping the added columns..."
$PHP "\$APP/artisan" tinker --execute='
  \Illuminate\Support\Facades\Schema::table("telephony_recordings", function (\$t) {
    foreach (["recording_url", "url_expires_at"] as \$c) {
      if (\Illuminate\Support\Facades\Schema::hasColumn("telephony_recordings", \$c)) {
        \$t->dropColumn(\$c);
      }
    }
  });
  echo "dropped\n";
'

rm -f "\$APP/$MIGRATION"
cd "\$APP" && $PHP artisan config:clear >/dev/null 2>&1 || true
echo "reverted."
REVERT

upload "$WORK/REVERT.sh" "$BACKUP/REVERT.sh"
remote "chmod +x $BACKUP/REVERT.sh"

echo
echo "════════════════════════════════════════════════════════════════════"
echo " Deployed. To undo:"
echo "   bash $BACKUP/REVERT.sh"
echo "════════════════════════════════════════════════════════════════════"
