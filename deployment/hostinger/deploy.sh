#!/usr/bin/env bash
#
# Deploy named files to the HRX production server.
#
#   bash deployment/hostinger/deploy.sh <file> [<file> ...] [--migrate]
#
# Credentials come from deployment/.env.deploy (gitignored) or the environment;
# they are never read from this file. See deployment/README.md.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
[ -f deployment/.env.deploy ] && . deployment/.env.deploy

HRX_SSH_HOST="${HRX_SSH_HOST:-89.117.188.10}"
HRX_SSH_PORT="${HRX_SSH_PORT:-65002}"
HRX_SSH_USER="${HRX_SSH_USER:-u989061032}"
HRX_APP_ROOT="${HRX_APP_ROOT:-/home/u989061032/domains/hitechgroup.in/public_html/hrx}"
HRX_PHP="${HRX_PHP:-/opt/alt/php84/usr/bin/php}"
PLINK="${PLINK:-/c/Program Files/PuTTY/plink.exe}"
PSCP="${PSCP:-/c/Program Files/PuTTY/pscp.exe}"

if [ -z "${HRX_SSH_PASSWORD:-}" ]; then
  echo "HRX_SSH_PASSWORD is not set." >&2
  echo "Copy deployment/.env.deploy.example to deployment/.env.deploy and fill it in." >&2
  exit 1
fi

MIGRATE=false
FILES=()
for arg in "$@"; do
  if [ "$arg" = "--migrate" ]; then MIGRATE=true; else FILES+=("$arg"); fi
done

if [ ${#FILES[@]} -eq 0 ]; then
  echo "Usage: bash deployment/hostinger/deploy.sh <file> [<file> ...] [--migrate]" >&2
  exit 1
fi

for f in "${FILES[@]}"; do
  [ -e "$f" ] || { echo "No such file: $f" >&2; exit 1; }
done

BUNDLE="$(mktemp -t hrx-deploy-XXXXXX).tgz"
trap 'rm -f "$BUNDLE"' EXIT

# One tarball keeps directory structure intact and makes the upload atomic
# enough that a half-copied set of blades never gets served.
tar -czf "$BUNDLE" "${FILES[@]}"
echo "Deploying ${#FILES[@]} file(s) to $HRX_SSH_HOST …"

"$PSCP" -P "$HRX_SSH_PORT" -pw "$HRX_SSH_PASSWORD" \
        "$BUNDLE" "$HRX_SSH_USER@$HRX_SSH_HOST:$HRX_APP_ROOT/deploy.tgz" >/dev/null

REMOTE="cd '$HRX_APP_ROOT' && tar -xzf deploy.tgz && rm -f deploy.tgz"
if [ "$MIGRATE" = true ]; then
  REMOTE="$REMOTE && $HRX_PHP artisan migrate --force"
fi
# Blade and route changes are invisible until the compiled caches are dropped.
REMOTE="$REMOTE && $HRX_PHP artisan view:clear && $HRX_PHP artisan route:clear && echo DEPLOYED"

"$PLINK" -ssh -batch -P "$HRX_SSH_PORT" -l "$HRX_SSH_USER" -pw "$HRX_SSH_PASSWORD" \
         "$HRX_SSH_HOST" "$REMOTE"

for f in "${FILES[@]}"; do echo "  $f"; done
