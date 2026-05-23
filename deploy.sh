#!/usr/bin/env bash
# =========================================================================
# deploy.sh — Generic deploy script for QLCV
# 
# Lives in the repo root. Triggered by GitHub Actions on push to stable.
# Deploys to all site folders on the target server.
#
# Usage:
#   SITES="site1 site2" bash deploy.sh
#   SITES="site1 site2" bash deploy.sh --be-only
#   SITES="site1 site2" bash deploy.sh --fe-only
# =========================================================================
set -euo pipefail

# ─── CONFIG — override via env vars ──────────────────────────────────────
PHP_BIN="${PHP_BIN:-/www/server/php/83/bin/php}"
COMPOSER="${COMPOSER:-/usr/local/bin/composer}"
PNPM="${PNPM:-pnpm}"
BRANCH="${BRANCH:-stable}"

# Sites to deploy — MUST be defined via env var SITES (e.g. "haichau tgdvdn")
if [ -z "${SITES:-}" ]; then
  echo "ERROR: SITES environment variable is required."
  echo ""
  echo "Usage: SITES=\"site1 site2\" bash deploy.sh [--be-only|--fe-only]"
  echo ""
  echo "Example:"
  echo "  SITES=\"haichau danguyhoakhanh hoacuong vptu tgdvdn\" bash deploy.sh"
  exit 1
fi
IFS=' ' read -ra SITE_LIST <<< "$SITES"

# ─── HELP ─────────────────────────────────────────────────────────────────
if [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
  echo "Usage: bash deploy.sh [--be-only|--fe-only|--help]"
  echo ""
  echo "Environment variables:"
  echo "  SITES       REQUIRED — Space-separated site names"
  echo "  PHP_BIN     PHP binary path (default: /www/server/php/83/bin/php)"
  echo "  COMPOSER    Composer binary path (default: /usr/bin/composer)"
  echo "  PNPM        pnpm binary path (default: pnpm)"
  echo "  BRANCH      Git branch to deploy (default: stable)"
  echo ""
  echo "Examples:"
  echo "  SITES=\"haichau tgdvdn\" bash deploy.sh"
  echo "  SITES=\"haichau\" bash deploy.sh --fe-only"
  exit 0
fi

MODE="${1:-all}"  # all | be-only | fe-only

SITE_DOMAIN="${SITE_DOMAIN:-theworkpc.com}"

# ─── FUNCTIONS ────────────────────────────────────────────────────────────
log()  { echo "[$(date '+%H:%M:%S')] $*"; }
err()  { log "ERROR: $*"; exit 1; }

deploy_be() {
  local site="$1"
  local dir="/www/wwwroot/${site}.${SITE_DOMAIN}/backend"

  [ -d "$dir" ] || { log "  [BE] SKIP: $dir not found"; return 0; }

  log "  [BE] Deploying ${site}..."

  cd "$dir"

  # Stash local changes & switch to target branch
  sudo -u www git stash --include-untracked 2>/dev/null || true
  sudo -u www git fetch origin "$BRANCH" 2>&1 | tail -1
  sudo -u www git checkout -f "$BRANCH"
  sudo -u www git reset --hard "origin/$BRANCH"

  # Composer install (optimize autoloader for production)
  # --ignore-platform-req=php: lock file may require PHP 8.4, server runs 8.3
  log "  [BE] Composer install..."
  sudo -u www $COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=php --ansi 2>&1 | tail -2

  # Cache clear (prevent stale cache after code update)
  log "  [BE] Clearing caches..."
  sudo -u www $PHP_BIN artisan optimize:clear --ansi 2>/dev/null || true

  # Run pending migrations
  log "  [BE] Running migrations..."
  sudo -u www $PHP_BIN artisan migrate --force --ansi 2>&1 | grep -E "DONE|Nothing"

  # Cache for production (config, routes, events, views)
  log "  [BE] Caching for production..."
  sudo -u www $PHP_BIN artisan config:cache --ansi 2>&1 | tail -1
  sudo -u www $PHP_BIN artisan event:cache --ansi 2>&1 | tail -1
  sudo -u www $PHP_BIN artisan route:cache --ansi 2>&1 | tail -1
  sudo -u www $PHP_BIN artisan view:cache --ansi 2>&1 | tail -1

  # Ensure storage symlink exists
  sudo -u www $PHP_BIN artisan storage:link --ansi 2>/dev/null || true

  log "  [BE] ${site} ✅"
}

deploy_fe() {
  local site="$1"
  local dir="/www/wwwroot/${site}.${SITE_DOMAIN}/frontend"

  [ -d "$dir" ] || { log "  [FE] SKIP: $dir not found"; return 0; }

  log "  [FE] Deploying ${site}..."

  cd "$dir"

  # Stash & switch
  sudo -u www git stash --include-untracked 2>/dev/null || true
  sudo -u www git fetch origin "$BRANCH" 2>&1 | tail -1
  sudo -u www git checkout -f "$BRANCH"
  sudo -u www git reset --hard "origin/$BRANCH"

  # pnpm install
  log "  [FE] pnpm install..."
  sudo -u www $PNPM install --frozen-lockfile 2>&1 | tail -1

  # Build
  log "  [FE] Building..."
  sudo -u www $PNPM run build 2>&1 | tail -3

  log "  [FE] ${site} ✅"
}

restart_services() {
  local site="$1"

  # Graceful queue restart (workers finish current job, then die)
  log "  [Svc] Queue: sending RESTART signal..."
  sudo -u www $PHP_BIN artisan queue:restart --ansi 2>/dev/null || true
  sleep 2

  # Supervisor restart (ensures workers come back + reverb restarts)
  if command -v supervisorctl &>/dev/null; then
    log "  [Svc] Supervisor: restarting workers..."
    sudo supervisorctl restart "${site}-queue:*" 2>/dev/null || true
    sudo supervisorctl restart "${site}-reverb:*" 2>/dev/null || true
  fi

  # PHP-FPM graceful reload (opcache reset)
  if [ -f /www/server/php/83/var/run/php-fpm.pid ]; then
    log "  [Svc] PHP-FPM: graceful reload..."
    sudo kill -USR2 "$(cat /www/server/php/83/var/run/php-fpm.pid)" 2>/dev/null || true
  fi

  log "  [Svc] ${site} ✅"
}

# ─── MAIN ─────────────────────────────────────────────────────────────────
log "═══════════════════════════════════════════════"
log "🚀 QLCV Deploy — Mode: $MODE | Branch: $BRANCH"
log "   Sites: ${SITE_LIST[*]}"
log "   Host: $(hostname)"
log "═══════════════════════════════════════════════"
echo ""

for site in "${SITE_LIST[@]}"; do
  echo ""
  log "━━━ $site ━━━"

  case "$MODE" in
    be-only) deploy_be "$site" ;;
    fe-only) deploy_fe "$site" ;;
    *)
      deploy_be "$site"
      restart_services "$site"
      deploy_fe "$site"
      ;;
  esac

  log "━━━ $site done ━━━"
done

echo ""
log "═══════════════════════════════════════════════"
log "🎉 Deploy completed!"
log "   Date: $(date '+%Y-%m-%d %H:%M:%S %Z')"
log "   Mode: $MODE | Branch: $BRANCH"
log "═══════════════════════════════════════════════"
