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
(cd frontend && ddev npm run build)

echo "==> Syncing frontend build to server..."
rsync -avz --no-perms --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    public/index.html "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/public/"
rsync -avz --delete --no-perms --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    public/assets/ "${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}/public/assets/"

echo "==> Pushing PHP/config changes via git..."
ssh "${SSH_USER}@${SSH_HOST}" "cd ${REMOTE_DIR} && git pull"

echo "==> Running server-side steps..."
ssh "${SSH_USER}@${SSH_HOST}" bash -l << REMOTE
set -e
export APP_ENV=prod
cd "${REMOTE_DIR}"

composer install --no-dev --optimize-autoloader --no-interaction

php bin/console cache:clear --no-warmup
php bin/console cache:warmup

php bin/console doctrine:migrations:migrate --no-interaction

chmod -R 755 var/
REMOTE

echo ""
echo "Deploy complete."
