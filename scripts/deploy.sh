#!/usr/bin/env bash
# Deploy aman di DomaiNesia/cPanel setelah kode masuk main.
# Usage: bash scripts/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> git pull"
git pull origin main

echo "==> composer"
composer install --no-dev --optimize-autoloader

echo "==> migrate (aman, tanpa seed/fresh)"
php artisan migrate --force

echo "==> cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ -f package.json ] && [ -d node_modules ] || command -v npm >/dev/null 2>&1; then
  if [ -f package-lock.json ]; then
    echo "==> frontend build (opsional)"
    npm ci
    npm run build
  fi
fi

echo "==> selesai"
php artisan migrate:status | tail -n 20
