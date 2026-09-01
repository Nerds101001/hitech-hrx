#!/usr/bin/env bash
#
# Deploy named files to the HRX production server.
#
#   bash deployment/hostinger/deploy.sh <file> [<file> ...] [--migrate] [--skip-view-check]
#
# Before uploading, checks every *.blade.php tracked in git against what
# actually exists on the server and warns (non-blocking) about any gap - a
# view can be committed and referenced by an already-live page without ever
# being pushed, staying invisible until some later deploy's view:clear
# exposes it as a live 500. Pass --skip-view-check to skip that check (e.g.
# deploying in a tight loop right after it already ran clean).
#
# Credentials come from deployment/.env.deploy (gitignored) or the environment;
# they are never read from this file. See deployment/README.md.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
# The file begins with a UTF-8 BOM, which `.` would try to execute.
[ -f deployment/.env.deploy ] && { set -a; . <(tr -d '\357\273\277' < deployment/.env.deploy); set +a; }

HRX_SSH_HOST="${HRX_SSH_HOST:-89.117.188.10}"
HRX_SSH_PORT="${HRX_SSH_PORT:-65002}"
HRX_SSH_USER="${HRX_SSH_USER:-u989061032}"
HRX_APP_ROOT="${HRX_APP_ROOT:-/home/u989061032/domains/hitechgroup.in/public_html/hrx}"
HRX_PHP="${HRX_PHP:-/opt/alt/php84/usr/bin/php}"
PLINK="${PLINK:-/c/Program Files/PuTTY/plink.exe}"
PSCP="${PSCP:-/c/Program Files/PuTTY/pscp.exe}"
# A local PHP, for obfuscating before upload -- same fallback deploy-telephony.sh uses.
PHP_LOCAL="${PHP_LOCAL:-$(command -v php || echo /c/laragon/bin/php/php-8.3.28-Win32-vs16-x64/php.exe)}"

if [ -z "${HRX_SSH_PASSWORD:-}" ]; then
  echo "HRX_SSH_PASSWORD is not set." >&2
  echo "Copy deployment/.env.deploy.example to deployment/.env.deploy and fill it in." >&2
  exit 1
fi

MIGRATE=false
SKIP_VIEW_CHECK=false
FILES=()
for arg in "$@"; do
  case "$arg" in
    --migrate) MIGRATE=true ;;
    --skip-view-check) SKIP_VIEW_CHECK=true ;;
    *) FILES+=("$arg") ;;
  esac
done

if [ ${#FILES[@]} -eq 0 ]; then
  echo "Usage: bash deployment/hostinger/deploy.sh <file> [<file> ...] [--migrate] [--skip-view-check]" >&2
  exit 1
fi

for f in "${FILES[@]}"; do
  [ -e "$f" ] || { echo "No such file: $f" >&2; exit 1; }
done

STAGE="$(mktemp -d -t hrx-deploy-stage-XXXXXX)"
BUNDLE="$(mktemp -t hrx-deploy-XXXXXX).tgz"
BLADE_LIST="$(mktemp -t hrx-blade-list-XXXXXX)"
trap 'rm -f "$BUNDLE" "$BLADE_LIST"; rm -rf "$STAGE"' EXIT

# Pre-deploy drift check: a blade view can be committed to git and referenced
# by an already-live page without ever actually being pushed to production -
# it stays invisible while the compiled view cache serves an older version,
# then 500s the moment any deploy's own `artisan view:clear` forces a fresh
# compile. Caught live this way twice (a dashboard widget, then two
# unconditional includes on the Sales Pipeline page breaking it outright) -
# checked here, before every deploy, instead of after a user hits it.
if [ "$SKIP_VIEW_CHECK" = true ]; then
  echo "Skipping pre-deploy view check (--skip-view-check)."
else
  echo "Checking for blade views tracked in git but missing on production…"
  git ls-files "resources/views/**/*.blade.php" > "$BLADE_LIST"
  MISSING="$("$PLINK" -ssh -batch -P "$HRX_SSH_PORT" -l "$HRX_SSH_USER" -pw "$HRX_SSH_PASSWORD" \
    "$HRX_SSH_HOST" "cd '$HRX_APP_ROOT' && while IFS= read -r f; do [ -f \"\$f\" ] || echo \"\$f\"; done" \
    < "$BLADE_LIST" || true)"

  if [ -n "$MISSING" ]; then
    echo "⚠  WARNING: blade view(s) tracked in git but missing on production:" >&2
    echo "$MISSING" | sed 's/^/    /' >&2
    echo "   A page that includes one of these can look fine right up until the next" >&2
    echo "   view:clear (e.g. this very deploy) forces a recompile and it 500s instead." >&2
    echo "   Consider adding the file(s) above to this deploy. Proceeding with the" >&2
    echo "   requested files only; pass --skip-view-check to silence this check." >&2
  else
    echo "OK - no missing blade views."
  fi
fi

# Obfuscate whatever remote_deploy.sh's full-tree pass (step 2.5/8, via
# deployment/hostinger/obfuscate.php) would also obfuscate: anything under
# app/, routes/, or Modules/, except .blade.php templates and anything under
# config/, lang/, bootstrap/, database/. Without this, pushing a single file
# through this script -- the common path for a quick hotfix -- silently
# reverted that file to plaintext on production, undoing protection someone
# else had already applied via a full deploy.
#
# Staged locally with obfuscate-one.php (same tool, same wrapper, same
# round-trip self-check deploy-telephony.sh already relies on) so what lands
# on the server is already in its final form -- no PHP execution needed on
# the remote host mid-deploy, and no window where a freshly-extracted file
# sits there in plaintext waiting for a later step to fix it.
is_obfuscation_target() {
  local f="$1"
  case "$f" in
    *.php) ;;
    *) return 1 ;;
  esac
  case "$f" in
    *.blade.php) return 1 ;;
  esac
  local wrapped="/$f/"
  case "$wrapped" in
    */config/*|*/lang/*|*/bootstrap/*|*/database/*) return 1 ;;
  esac
  case "$wrapped" in
    */app/*|*/routes/*|*/Modules/*) return 0 ;;
  esac
  return 1
}

for f in "${FILES[@]}"; do
  mkdir -p "$STAGE/$(dirname "$f")"
  if is_obfuscation_target "$f"; then
    "$PHP_LOCAL" deployment/hostinger/obfuscate-one.php "$f" "$STAGE/$f"
  else
    cp "$f" "$STAGE/$f"
  fi
done

# One tarball keeps directory structure intact and makes the upload atomic
# enough that a half-copied set of blades never gets served.
( cd "$STAGE" && tar -czf "$BUNDLE" "${FILES[@]}" )
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
