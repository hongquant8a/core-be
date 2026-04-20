#!/bin/bash
# Deploy script cho backend danatec-core.
# Được trigger qua webhook: POST /api/deploy/webhook (DeployController).
# Controller gọi thẳng file này từ repo qua base_path('scripts/deploy.sh').

set -e

PHP=/www/server/php/83/bin/php
COMPOSER=/usr/bin/composer
BACKEND=/www/wwwroot/danatec-core.theworkpc.com/backend
LOG=/www/wwwroot/danatec-core.theworkpc.com/deploy.log

exec >> "$LOG" 2>&1

echo ""
echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy start ==="
cd "$BACKEND"

git pull origin stable

$COMPOSER install --no-dev --optimize-autoloader --no-interaction

$PHP artisan migrate --force
$PHP artisan db:seed --force

$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

# Supervisor tự spawn lại worker sau khi process kết thúc.
$PHP artisan queue:restart

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Deploy done ==="
