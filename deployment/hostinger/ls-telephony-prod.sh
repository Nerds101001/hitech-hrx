#!/usr/bin/env bash
# Read-only: what telephony files exist on production, and which are obfuscated.
set -euo pipefail
cd "$(dirname "$0")/../.."
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="/c/Program Files/PuTTY/plink.exe"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
APP="$HRX_APP_ROOT"
remote() { "$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" "$@"; }

FILES=(
  app/Http/Middleware/VerifyTelephonySignature.php
  app/Http/Controllers/Api/Telephony/TelephonyIngestController.php
  app/Services/Telephony/CallEventNormalizer.php
  app/Models/Telephony/TelephonyRecording.php
  app/Http/Controllers/tenant/TelephonyRecordingController.php
)

for f in "${FILES[@]}"; do
  # One round trip per file: exists?, size, and whether it is obfuscated.
  out=$(remote "if [ -f '$APP/$f' ]; then \
      printf '%s ' \$(stat -c %s '$APP/$f'); \
      if head -c 400 '$APP/$f' | grep -q 'eval(gzinflate'; then echo OBFUSCATED; else echo plain; fi; \
    else echo 'MISSING'; fi" 2>/dev/null | tr -d '\r')
  printf '  %-46s %s\n' "$(basename "$f")" "$out"
done
