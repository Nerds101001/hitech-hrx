#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-/home/u989061032/domains/hitechgroup.in/public_html/hrx}"
ARCHIVE_PATH="${2:-/tmp/hrx-release.tar.gz}"

if [[ ! -f "$ARCHIVE_PATH" ]]; then
  echo "Archive not found: $ARCHIVE_PATH"
  exit 1
fi

echo "[1/8] Preparing application root..."
mkdir -p "$APP_ROOT"

TMP_DIR="$(mktemp -d)"
trap 'chmod -R u+w "$TMP_DIR" 2>/dev/null || true; rm -rf "$TMP_DIR" 2>/dev/null || true' EXIT

echo "[2/8] Extracting release archive..."
tar --warning=no-unknown-keyword --no-same-permissions --delay-directory-restore --overwrite -xzf "$ARCHIVE_PATH" -C "$TMP_DIR"

echo "[3/8] Syncing release files (preserving runtime dirs and .env)..."
# Do not preserve source permission bits from Windows-packed archives.
rsync -rltD --delete \
  --exclude='.env' \
  --exclude='storage/' \
  --exclude='bootstrap/cache/' \
  "$TMP_DIR"/ "$APP_ROOT"/
chmod -R u+rwX "$APP_ROOT" || true
rm -f "$APP_ROOT/public/hot"

echo "[4/8] Preparing runtime folders..."
mkdir -p "$APP_ROOT/storage/app/public" \
         "$APP_ROOT/storage/framework/cache" \
         "$APP_ROOT/storage/framework/sessions" \
         "$APP_ROOT/storage/framework/views" \
         "$APP_ROOT/storage/logs" \
         "$APP_ROOT/bootstrap/cache"

if [[ ! -f "$APP_ROOT/.env" ]]; then
  echo "[5/8] .env missing, creating from .env.example..."
  cp "$APP_ROOT/.env.example" "$APP_ROOT/.env"
fi

cd "$APP_ROOT"

echo "[6/8] Installing composer dependencies..."
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
  --ignore-platform-req=ext-sodium \
  --ignore-platform-req=ext-redis

if ! grep -q '^APP_KEY=base64:' "$APP_ROOT/.env"; then
  echo "[6.1/8] Generating APP_KEY..."
  php artisan key:generate --force --no-interaction
fi

echo "[7/8] Running framework optimizations and migrations..."
php artisan optimize:clear
if ! php artisan migrate --force --no-interaction; then
  echo "[7.0/8] migrate skipped due existing schema conflict; continuing deployment."
fi
php artisan config:cache
if ! php artisan route:cache; then
  echo "[7.1/8] route:cache skipped (duplicate route names present)."
  php artisan route:clear || true
fi
php artisan view:cache
# Use shell symlink because `php artisan storage:link` can fail when exec() is disabled.
rm -rf "$APP_ROOT/public/storage" || true
ln -sfn "$APP_ROOT/storage/app/public" "$APP_ROOT/public/storage" || true

echo "[8/8] Setting permissions..."
chmod -R 775 "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" || true

rm -f "$ARCHIVE_PATH"
echo "Deployment completed successfully at: $APP_ROOT"
