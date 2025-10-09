#!/bin/bash
set -e  # Exit on error

echo "🛑 Stopping HAWKI Production Containers..."
echo ""
echo "⚠️  WARNING: You are about to stop PRODUCTION containers!"
echo ""

# Parse arguments
REMOVE_VOLUMES=false
REMOVE_CONTAINERS=false
CONFIRMED=false
for arg in "$@"; do
    case $arg in
        --clean|-v)
            REMOVE_VOLUMES=true
            REMOVE_CONTAINERS=true
            ;;
        --remove)
            REMOVE_CONTAINERS=true
            ;;
        --yes)
            CONFIRMED=true
            ;;
    esac
done

# Require confirmation for production
if [ "$CONFIRMED" = false ]; then
    read -p "Are you sure you want to stop production containers? (yes/no): " -r
    echo
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        echo "❌ Operation cancelled."
        exit 1
    fi
fi

# Load environment variables
if [ -f "env/.env" ]; then
    set -a
    source env/.env
    set +a
else
    echo "⚠️  Warning: env/.env not found!"
fi

# Load prod-specific defaults
if [ -f "env/.env.prod" ]; then
    set -a
    source env/.env.prod
    set +a
else
    echo "⚠️  Warning: env/.env.prod not found!"
fi

# Export profile for docker-compose
export COMPOSE_PROFILES=prod
export BUILD_TARGET=app_prod
export CODE_MOUNT="/dev/null"
export CODE_MOUNT_MODE="ro"
export ENV_PATH="./env/.env"
export PUBLIC_MOUNT="/dev/null"
export RESTART_POLICY="unless-stopped"

# Change to parent directory (where docker-compose is executed from)
cd ..

# Execute the appropriate docker compose command
if [ "$REMOVE_VOLUMES" = true ]; then
    echo "🗑️  Stopping containers and removing volumes..."
    docker compose -f _docker_production/docker-compose.yml down -v
    echo ""
    echo "✅ Containers stopped and volumes removed!"
elif [ "$REMOVE_CONTAINERS" = true ]; then
    echo "🗑️  Stopping and removing containers..."
    docker compose -f _docker_production/docker-compose.yml down
    echo ""
    echo "✅ Containers stopped and removed!"
else
    echo "⏸️  Stopping containers (keeping them for restart)..."
    docker compose -f _docker_production/docker-compose.yml stop
    echo ""
    echo "✅ Containers stopped!"
    echo ""
    echo "💡 To start them again:"
    echo "   docker compose -f _docker_production/docker-compose.yml start"
    echo "   or run: ./deploy-prod.sh"
fi

cd _docker_production

echo ""
echo "═══════════════════════════════════════════════════════"
echo "📋 Available stop options:"
echo "   Stop only:          ./stop-prod.sh --yes"
echo "   Stop & remove:      ./stop-prod.sh --remove --yes"
echo "   Stop & clean all:   ./stop-prod.sh --clean --yes"
echo "═══════════════════════════════════════════════════════"
