#!/usr/bin/env bash
#
# READ-ONLY look at the live HRX server before anything Tally-related is deployed.
#
#   bash deployment/hostinger/tally/audit-live.sh
#
# What happens: tally/audit-remote.php is piped into `php` on the server over SSH (nothing is uploaded, nothing is left
# behind), it prints one JSON report, and that report is saved under deployment/hostinger/artifacts/ (gitignored) and
# then compared, locally, with this repository by tally/compare-audit.php.
#
# What it never does: write, delete, migrate, clear a cache, or run any of the application's own code on the server.
# It reads files, runs SELECT/SHOW queries, and prints each secret as "set" / "not set" only.
set -euo pipefail

cd "$(dirname "$0")/../../.."

# The file begins with a UTF-8 BOM, which `source` would try to execute.
set -a; source <(tr -d '\357\273\277' < deployment/.env.deploy); set +a

PLINK="${PLINK:-/c/Program Files/PuTTY/plink.exe}"
PHP_LOCAL="${PHP_LOCAL:-$(command -v php || echo /c/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe)}"
SSH="$HRX_SSH_USER@$HRX_SSH_HOST"
OUT="deployment/hostinger/artifacts"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT"

echo "Asking $HRX_SSH_HOST (read-only) ..."
"$PLINK" -batch -ssh -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" "$SSH" \
  "cd $HRX_APP_ROOT && $HRX_PHP -d display_errors=0" \
  < deployment/hostinger/tally/audit-remote.php > "$OUT/live-audit-$STAMP.json"

# Is the site behind a CDN / proxy? (decides whether the push endpoint sees the office's real IP)
curl -sI --max-time 15 https://hrx.hitechgroup.in/ 2>/dev/null \
  | grep -iE '^(HTTP|server|cf-ray|cf-cache-status|x-powered-by|via|x-served-by|x-hcdn|platform)' \
  | tr -d '\r' > "$OUT/live-headers-$STAMP.txt" || true

echo "Saved $OUT/live-audit-$STAMP.json ($(wc -c < "$OUT/live-audit-$STAMP.json") bytes)"
echo
"$PHP_LOCAL" deployment/hostinger/tally/compare-audit.php "$OUT/live-audit-$STAMP.json" "$OUT/live-headers-$STAMP.txt" \
  | tee "$OUT/live-audit-$STAMP.report.txt"
echo
echo "Full report: $OUT/live-audit-$STAMP.report.txt"
echo "Live source of each file (decoded) and a diff of every drifted one: $OUT/live-decoded-$STAMP/"
