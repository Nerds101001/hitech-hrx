#!/usr/bin/env bash
#
# Fetch production's own copy of each file an incremental deploy would replace,
# and diff it against the local one.
#
#   bash deployment/hostinger/diff-telephony-prod.sh
#
# Writes nothing to the server. Comparing md5 against a guessed git baseline is
# not good enough here: production is at no git commit, so the only honest
# question is "what exactly differs between what is running and what I am about
# to upload" -- and the answer should be nothing but this session's changes.
set -euo pipefail

cd "$(dirname "$0")/../.."

set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PSCP="/c/Program Files/PuTTY/pscp.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
APP="$HRX_APP_ROOT"

OUT="${SCRATCH:-/tmp}/prod-telephony"
mkdir -p "$OUT"

FILES=(
  app/Http/Middleware/VerifyTelephonySignature.php
  app/Http/Controllers/Api/Telephony/TelephonyIngestController.php
  app/Services/Telephony/CallEventNormalizer.php
  app/Models/Telephony/TelephonyRecording.php
  app/Http/Controllers/tenant/TelephonyRecordingController.php
)

for f in "${FILES[@]}"; do
  dest="$OUT/$(basename "$f")"
  "$PSCP" -batch -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" \
    "$SSH:$APP/$f" "$dest" >/dev/null 2>&1 || { echo "  COULD NOT FETCH $f"; continue; }

  echo "═══ $f"
  # --strip-trailing-cr: production files are CRLF in places; a line-ending
  # difference is not a code difference and would bury the real ones.
  if diff -q --strip-trailing-cr "$dest" "$f" >/dev/null 2>&1; then
    echo "  identical to local - nothing to upload"
  else
    diff --strip-trailing-cr -u "$dest" "$f" \
      | sed -n '3,$p' | grep -E '^[+-]' | grep -vE '^(\+\+\+|---)' \
      | head -60 | sed 's/^/    /'
    echo "    ---"
    echo "    $(diff --strip-trailing-cr -u "$dest" "$f" | grep -cE '^\+[^+]') added, $(diff --strip-trailing-cr -u "$dest" "$f" | grep -cE '^-[^-]') removed"
  fi
  echo
done
