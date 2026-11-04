#!/bin/bash
# One-command deploy for the live server. Run this from the clone directory
# (see one-time setup in the project notes), NOT from public_html/backend
# itself — that folder is plain uploaded files, not a git repo, because
# the live docroot sits one level inside this monorepo (backend/) while
# the rest (the Flutter app/) must never land on the web server.
set -e

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="${DEPLOY_DIR:-$HOME/domains/elimugrid.ecofieldgroup.com/public_html/backend}"

echo "==> Pulling latest code into $REPO_DIR"
cd "$REPO_DIR"
git pull origin main

echo "==> Syncing backend/ into $DEPLOY_DIR (env, vendor, storage, build left untouched)"
if command -v rsync >/dev/null 2>&1; then
  rsync -a \
    --exclude='.env' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage' \
    --exclude='public/build' \
    --exclude='bootstrap/cache' \
    "$REPO_DIR/backend/" "$DEPLOY_DIR/"
else
  # rsync isn't available on this host (common on shared cPanel) — fall
  # back to tar, which ships on virtually every Linux box. Piping one
  # tar's stdout into another's stdin copies the tree while --exclude
  # keeps the same server-owned paths untouched.
  tar -cf - \
    --exclude='.env' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage' \
    --exclude='public/build' \
    --exclude='bootstrap/cache' \
    -C "$REPO_DIR/backend" . | tar -xf - -C "$DEPLOY_DIR"
fi

echo "==> Running composer, migrations, and cache rebuild"
cd "$DEPLOY_DIR"
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache

echo "==> Done. Current schedule:"
php artisan schedule:list
