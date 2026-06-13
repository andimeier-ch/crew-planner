#!/bin/bash
# Local deploy script — run this on your Mac, not on the server.
# Builds the frontend, pushes files via rsync, and runs server-side steps over SSH.
set -e

# ── Configuration ────────────────────────────────────────────────────────────
SSH_USER="andimeie"
SSH_HOST="s058.cyon.net"
REMOTE_DIR="/home/andimeie/public_html/crew-planner"
# ─────────────────────────────────────────────────────────────────────────────

echo "==> Building frontend..."
(cd frontend && npm run build)

echo "==> Syncing frontend build to server..."
rsync -avz --delete \
    public/index.html \
    public/assets/ \
    "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/public/"

echo "==> Pushing PHP/config changes via git..."
ssh "${SSH_USER}@${SSH_HOST}" "cd ${REMOTE_DIR} && git pull"

echo "==> Running server-side steps..."
ssh "${SSH_USER}@${SSH_HOST}" bash << 'REMOTE'
set -e
cd ~/crew-planner

composer install --no-dev --optimize-autoloader --no-interaction

php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

php bin/console doctrine:migrations:migrate --no-interaction --env=prod

chmod -R 755 var/
REMOTE

echo ""
echo "Deploy complete."
