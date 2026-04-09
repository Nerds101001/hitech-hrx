#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-/home/u989061032/domains/hitechgroup.in/public_html/hrx}"
ARCHIVE_PATH="${2:-/tmp/hrx-partial.tar.gz}"

if [[ ! -f "$ARCHIVE_PATH" ]]; then
  echo "Archive not found: $ARCHIVE_PATH"
  exit 1
fi

echo "[1/7] Preparing application root..."
mkdir -p "$APP_ROOT"

TMP_DIR="$(mktemp -d)"
trap 'chmod -R u+w "$TMP_DIR" 2>/dev/null || true; rm -rf "$TMP_DIR" 2>/dev/null || true' EXIT

echo "[2/7] Extracting partial archive..."
tar --warning=no-unknown-keyword --no-same-permissions --delay-directory-restore --overwrite -xzf "$ARCHIVE_PATH" -C "$TMP_DIR"

echo "[3/7] Syncing changed files only..."
rsync -rltD \
  --exclude='.env' \
  --exclude='storage/' \
  --exclude='bootstrap/cache/' \
  "$TMP_DIR"/ "$APP_ROOT"/
chmod -R u+rwX "$APP_ROOT" || true
rm -f "$APP_ROOT/public/hot"

echo "[4/7] Preparing runtime folders..."
mkdir -p "$APP_ROOT/storage/app/public" \
         "$APP_ROOT/storage/framework/cache" \
         "$APP_ROOT/storage/framework/sessions" \
         "$APP_ROOT/storage/framework/views" \
         "$APP_ROOT/storage/logs" \
         "$APP_ROOT/bootstrap/cache"

cd "$APP_ROOT"

# Pre-emptive cache clear to avoid 'Closure serialization' crashes during autoload discovery.
# We manually delete the directory because 'php artisan' itself crashes when the cache is corrupt.
echo "[4.1/7] Performing nuclear cache clear..."
rm -rf "$APP_ROOT/bootstrap/cache"
mkdir -p "$APP_ROOT/bootstrap/cache"
chmod 775 "$APP_ROOT/bootstrap/cache"

if [[ -f "$TMP_DIR/composer.lock" || -f "$TMP_DIR/composer.json" ]]; then
  echo "[5/7] Installing composer dependencies..."
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    --ignore-platform-req=ext-sodium \
    --ignore-platform-req=ext-redis
else
  echo "[5/7] Skipping composer install (no composer changes)."
fi

echo "[6/7] Clearing caches..."
php artisan optimize:clear || true
php artisan config:cache || true
php artisan view:cache || true
if ! php artisan route:cache; then
  php artisan route:clear || true
fi

if compgen -G "$TMP_DIR/database/migrations/*.php" > /dev/null; then
  echo "[6.1/7] Running migrations..."
  php artisan migrate --force --no-interaction || true
else
  echo "[6.1/7] No new migrations detected."
fi

rm -rf "$APP_ROOT/public/storage" || true
ln -sfn "$APP_ROOT/storage/app/public" "$APP_ROOT/public/storage" || true

echo "[7/7] Setting permissions..."
chmod -R 775 "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache" || true

rm -f "$ARCHIVE_PATH"
echo "Partial deployment completed successfully at: $APP_ROOT"
