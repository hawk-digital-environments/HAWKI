#!/bin/bash#!/bin/bash

# =====================================================set -e

# HAWKI - Development Quick Update Script

# =====================================================echo "🔄 Quick update from Git (Development Setup)..."

# Quickly updates the development environment after git pull

# - Pulls latest codecd ..

# - Detects dependency changes

# - Updates only what's needed# Pull latest code

# - Rebuilds cachesecho "📥 Pulling latest code..."

#git pull

# Usage:

#   git pull && ./update-dev.sh# Check if package files changed

# =====================================================COMPOSER_CHANGED=0

PACKAGE_CHANGED=0

set -e

if git diff HEAD@{1} HEAD --name-only | grep -q "composer.json\|composer.lock"; then

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"    COMPOSER_CHANGED=1

cd "$SCRIPT_DIR/.."    echo "📦 composer.json or composer.lock changed - will update dependencies"

fi

echo "🔄 Quick update from Git (Development Setup)..."

echo ""if git diff HEAD@{1} HEAD --name-only | grep -q "package.json\|package-lock.json"; then

    PACKAGE_CHANGED=1

# Load environment    echo "📦 package.json or package-lock.json changed - will update dependencies"

if [ -f "_docker_production/env/.env" ]; thenfi

    set -a

    source _docker_production/env/.env# Update Composer if needed (WITH dev dependencies for development)

    set +aif [ $COMPOSER_CHANGED -eq 1 ]; then

fi    echo "📦 Updating Composer dependencies..."

if [ -f "_docker_production/env/.env.dev" ]; then    docker compose -f _docker_production/docker-compose.dev.yml exec app composer install --optimize-autoloader

    set -afi

    source _docker_production/env/.env.dev

    set +a# Update NPM if needed (on HOST, since code is live-mounted)

fiif [ $PACKAGE_CHANGED -eq 1 ]; then

    echo "📦 NPM dependencies changed!"

export COMPOSE_PROFILES=dev    echo "   Please run on your HOST machine:"

    echo "   cd /Users/stenseegel/gitHub/HAWKI-origin/HAWKI"

# Pull latest code    echo "   npm install"

echo "📥 Pulling latest code..."    echo "   npm run dev   # or npm run build"

git pullfi

echo ""

# Clear Laravel caches

# Check if package files changedecho "⚡ Clearing Laravel caches..."

COMPOSER_CHANGED=0docker compose -f _docker_production/docker-compose.dev.yml exec app php artisan optimize:clear

PACKAGE_CHANGED=0

# Recache for performance

if git diff HEAD@{1} HEAD --name-only | grep -q "composer.json\|composer.lock"; thenecho "📦 Rebuilding caches..."

    COMPOSER_CHANGED=1docker compose -f _docker_production/docker-compose.dev.yml exec app bash -c "php artisan config:cache && \

    echo "📦 composer.json or composer.lock changed - will update dependencies"    php artisan route:cache && \

fi    php artisan view:cache"



if git diff HEAD@{1} HEAD --name-only | grep -q "package.json\|package-lock.json"; then# Update git info

    PACKAGE_CHANGED=1echo "📝 Updating Git info..."

    echo "📦 package.json or package-lock.json changed - will update dependencies"docker compose -f _docker_production/docker-compose.dev.yml exec app bash -c "git config --global --add safe.directory /var/www/html && /var/www/html/git_info.sh"

fi

echo ""echo ""

echo "✅ Update complete! Changes are live."

# Update Composer if needed (WITH dev dependencies)echo ""

if [ $COMPOSER_CHANGED -eq 1 ]; then

    echo "📦 Updating Composer dependencies..."if [ $COMPOSER_CHANGED -eq 0 ] && [ $PACKAGE_CHANGED -eq 0 ]; then

    docker compose -f _docker_production/docker-compose.yml exec app composer install --optimize-autoloader    echo "💡 To manually update dependencies:"

    echo ""    echo "   Composer: docker compose -f _docker_production/docker-compose.dev.yml exec app composer install"

fi    echo "   NPM: Run on HOST - npm install && npm run build"

fi

# Update NPM if needed (on HOST, since code is live-mounted)
if [ $PACKAGE_CHANGED -eq 1 ]; then
    echo "📦 NPM dependencies changed!"
    echo "   Please run on your HOST machine:"
    echo "   → npm install"
    echo "   → npm run dev   # or npm run build"
    echo ""
fi

# Run migrations if needed
if git diff HEAD@{1} HEAD --name-only | grep -q "database/migrations"; then
    echo "🗄️  Running database migrations..."
    docker compose -f _docker_production/docker-compose.yml exec app php artisan migrate --force
    echo ""
fi

# Clear Laravel caches
echo "⚡ Clearing Laravel caches..."
docker compose -f _docker_production/docker-compose.yml exec app php artisan optimize:clear
echo ""

# Update git info
echo "📝 Updating Git info..."
docker compose -f _docker_production/docker-compose.yml exec app bash -c "\
    git config --global --add safe.directory /var/www/html && \
    /var/www/html/git_info.sh" 2>/dev/null || true
echo ""

echo "✅ Update complete! Changes are live."
echo ""

if [ $COMPOSER_CHANGED -eq 0 ] && [ $PACKAGE_CHANGED -eq 0 ]; then
    echo "💡 No dependency changes detected."
    echo "   To manually update:"
    echo "   Composer: docker compose exec app composer install"
    echo "   NPM: npm install && npm run build (on HOST)"
fi
echo ""
