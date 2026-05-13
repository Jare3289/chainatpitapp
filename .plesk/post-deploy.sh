#!/bin/bash
# .plesk/post-deploy.sh
# Run this as Plesk's "Additional deployment actions"
#   Plesk panel: Git > <repo> > Additional deployment actions
#   Command: bash {DOCROOT}/.plesk/post-deploy.sh
#
# What it does:
#   1. Copies .env.production → .env (if .env.production exists outside webroot)
#   2. Sets reasonable file permissions
#   3. Clears any stale rate-limit / session lock files

set -e

DOCROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo "[deploy] docroot: $DOCROOT"

# 1. Restore .env from a vault file kept OUTSIDE the docroot
#    Recommended path: /var/www/vhosts/chainatpit.com/private/.env
VAULT="$(dirname "$DOCROOT")/private/.env"
if [ -f "$VAULT" ]; then
    cp -f "$VAULT" "$DOCROOT/.env"
    chmod 600 "$DOCROOT/.env"
    echo "[deploy] copied vault .env -> $DOCROOT/.env"
else
    echo "[deploy] WARN: vault file not found at $VAULT"
    echo "[deploy] Create it once with: mkdir -p $(dirname "$VAULT") && nano $VAULT"
fi

# 2. Permissions
#    Files 644, dirs 755, except .env which is 600
find "$DOCROOT" -type d -exec chmod 755 {} \;
find "$DOCROOT" -type f -exec chmod 644 {} \;
[ -f "$DOCROOT/.env" ] && chmod 600 "$DOCROOT/.env"

# Writable directories (uploads, etc.)
[ -d "$DOCROOT/public/uploads" ] && chmod -R 775 "$DOCROOT/public/uploads"

# 3. Clear stale rate-limit cache files (PHP will recreate as needed)
rm -rf /tmp/cnp_ratelimit 2>/dev/null || true

# 4. Sanity check: is required PHP file present?
if [ ! -f "$DOCROOT/config.php" ]; then
    echo "[deploy] ERROR: config.php missing"
    exit 1
fi

echo "[deploy] done at $(date '+%Y-%m-%d %H:%M:%S')"
