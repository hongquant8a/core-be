#!/usr/bin/env bash
# =========================================================================
# deploy.sh — Generic deploy script for QLCV
# 
# Lives in the repo root. Triggered by GitHub Actions on push to stable.
# Deploys to all site folders on the target server.
#
# Usage:
#   # Single server:
#   bash deploy.sh
#
#   # With env vars (GitHub Actions sets these):
#   SITES="site1 site2" bash deploy.sh
#   PHP_BIN=/custom/php bash deploy.sh
# =========================================================================
set -euo pipefail

# ─── CONFIG — override via env vars ──────────────────────────────────────
PHP_BIN="${PHP_BIN:-/www/server/php/83/bin/php}"
COMPOSER="${COMPOSER:-/usr/bin/composer}"
PNPM="${PNPM:-pnpm}"
BRANCH="${BRANCH:-stable}"

# Sites to deploy (auto-detect or override)
if [ -n "${SITES:-}" ]; then
  IFS=' ' read -ra SITE_LIST <<< "$SITES"
else
  # Auto-detect: find all QLCV site directories
  mapfile -t SITE_LIST < <(find /www/wwwroot -maxdepth 1 -type d -name "*.theworkpc.com" | sed 's|/www/wwwroot/||; s|\.theworkpc\.com||' | sort)
fi

# ─── HELP ─────────────────────────────────────────────────────────────────
if [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
  echo "Usage: bash deploy.sh [--be-only|--fe-only|--help]"
  echo ""
  echo "Environment variables:"
  echo "  SITES       Space-separated site names (default: auto-detect)"
  echo "  PHP_BIN     PHP binary path"
  echo "  COMPOSER    Composer binary path"
  echo "  PNPM        pnpm binary path"
  echo "  BRANCH      Git branch to deploy (default: stable)"
  echo ""
  echo "Examples:"
  echo "  SITES=\"haichau tgdvdn\" bash deploy.sh --fe-only"
  echo "  bash deploy.sh"
  exit 0
fi

MODE="${1:-}"; MODE="${MODE#--}"; MODE="${MODE:-all}"  # all | be-only | fe-only

# ─── FUNCTIONS ────────────────────────────────────────────────────────────
log()  { echo "[$(date '+%H:%M:%S')] $*"; }
err()  { log "ERROR: $*"; exit 1; }

deploy_be() {
  local site="$1"
  local dir="/www/wwwroot/${site}.theworkpc.com/backend"

  [ -d "$dir" ] || { log "  [BE] SKIP: $dir not found"; return 0; }

  log "  [BE] Deploying ${site}..."

  cd "$dir"
  # Remove immutable flag (prevents git checkout/reset errors)
  sudo chattr -i "${dir}/public/.user.ini" 2>/dev/null || true

  # Stash local changes & switch to target branch
  sudo -u quandh git clean -fd 2>/dev/null || true
  # If repo is corrupted, re-clone
  sudo -u quandh git fsck 2>&1 | grep -q "error|corrupt" && { log "  [FE] Repo corrupted, re-cloning..."; sudo rm -rf "${dir}" && sudo -u quandh git clone --branch "$BRANCH" "$REPO_URL" "${dir}" && return 0; } || true
  sudo -u quandh git fetch origin "$BRANCH" 2>&1 | tail -1
  sudo -u quandh git checkout -f "$BRANCH"
  sudo chattr -i "${dir}/public/.user.ini" 2>/dev/null || true
  sudo -u quandh git reset --hard "origin/$BRANCH"

  # Composer install (only if lock changed — fast path: always check)
  log "  [BE] Composer install..."
  sudo -u quandh $COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=php --ansi 2>&1 || log "  [BE] Composer install had warnings (continuing...)"

  # Artisan commands
  log "  [BE] Running artisan commands..."
  sudo -u quandh $PHP_BIN artisan optimize:clear 2>/dev/null || true
  # Fix open_basedir (aaPanel resets .user.ini)
  local ini="${dir}/public/.user.ini"
  echo "open_basedir=${dir%/backend}/:/tmp/" | sudo tee "$ini" > /dev/null
  sudo chattr +i "$ini" 2>/dev/null || true
  sudo -u quandh $PHP_BIN artisan migrate --force 2>&1 | grep -E "DONE|Nothing"
  sudo -u quandh $PHP_BIN artisan horizon:terminate 2>/dev/null || true

  # Seed (add data, NOT fresh — seeder checks duplicates)
  # log "  [BE] Running seeders..."
  # sudo -u quandh $PHP_BIN artisan db:seed --force --ansi 2>&1 | grep -E "DONE|Seeding" | tail -3

  # Cleanup orphan permissions/settings
  log "  [BE] Cleanup obsolete..."
  sudo -u quandh $PHP_BIN artisan seed:cleanup-obsolete --ansi 2>&1 | tail -2
  sudo -u quandh $PHP_BIN artisan optimize 2>/dev/null || true

  log "  [BE] Fixing storage permissions..."
  sudo chown -R www:www "${dir}/storage" "${dir}/bootstrap/cache" 2>/dev/null || true
  sudo chmod -R 775 "${dir}/storage" "${dir}/bootstrap/cache" 2>/dev/null || true

  log "  [BE] ${site} ✅"
}

deploy_fe() {
  local site="$1"
  local dir="/www/wwwroot/${site}.theworkpc.com/frontend"

  [ -d "$dir" ] || { log "  [FE] SKIP: $dir not found"; return 0; }

  log "  [FE] Deploying ${site}..."

  cd "$dir"
  # Remove immutable flag (prevents git checkout/reset errors)
  sudo chattr -i "${dir}/public/.user.ini" 2>/dev/null || true

  # Stash & switch
  sudo -u quandh git clean -fd 2>/dev/null || true
  # If repo is corrupted, re-clone
  sudo -u quandh git fsck 2>&1 | grep -q "error|corrupt" && { log "  [FE] Repo corrupted, re-cloning..."; sudo rm -rf "${dir}" && sudo -u quandh git clone --branch "$BRANCH" "$REPO_URL" "${dir}" && return 0; } || true
  sudo -u quandh git fetch origin "$BRANCH" 2>&1 | tail -1
  sudo -u quandh git checkout -f "$BRANCH"
  sudo chattr -i "${dir}/public/.user.ini" 2>/dev/null || true
  sudo -u quandh git reset --hard "origin/$BRANCH"

  # pnpm install
  sudo -u quandh $PNPM rebuild 2>&1 | tail -1
  log "  [FE] pnpm install..."
  sudo -u quandh $PNPM rebuild 2>&1 | tail -1
  sudo rm -rf "${dir}/node_modules" 2>/dev/null || true
  sudo -u quandh $PNPM install --no-frozen-lockfile  2>&1 | tail -1

  # Build
  sudo chmod -R 775 "${dir}/dist" 2>/dev/null || true
  log "  [FE] Building..."
  sudo rm -rf "${dir}/dist"
  sudo rm -f "${dir}/.eslintrc-auto-import.json"
  sudo -u quandh $PNPM run build 2>&1 | tail -3

  log "  [FE] ${site} ✅"
}

restart_services() {
  local site="$1"

  # Supervisor (queue + reverb)
  if command -v supervisorctl &>/dev/null; then
    log "  [Svc] Restarting supervisor workers..."
    sudo supervisorctl restart "${site}-queue:*" 2>/dev/null || true
    sudo supervisorctl restart "${site}-reverb:*" 2>/dev/null || true
  fi

  # PHP-FPM reload (clear opcache after deploy)
  if [ -f /www/server/php/83/var/run/php-fpm.pid ]; then
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