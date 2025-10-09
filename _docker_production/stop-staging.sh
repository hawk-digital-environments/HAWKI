#!/bin/bash
set -e  # Exit on error

echo "🛑 Stopping HAWKI Staging Containers..."
echo ""

# Parse arguments
REMOVE_VOLUMES=false
REMOVE_CONTAINERS=false
for arg in "$@"; do
    case $arg in
        --clean|-v)
            REMOVE_VOLUMES=true
            REMOVE_CONTAINERS=true
            ;;
        --remove)
            REMOVE_CONTAINERS=true
            ;;
    esac
done

# Load environment variables
if [ -f "env/.env" ]; then
    set -a
    source env/.env
    set +a
else
    echo "⚠️  Warning: env/.env not found!"
fi

# Load staging-specific defaults
if [ -f "env/.env.staging" ]; then
    set -a
    source env/.env.staging
    set +a
else
    echo "⚠️  Warning: env/.env.staging not found!"
fi

# Export profile for docker-compose
export PROJECT_NAME=${PROJECT_NAME:-hawki-staging}
export PROJECT_HAWKI_IMAGE=${PROJECT_HAWKI_IMAGE:-hawki:staging}

# Change to parent directory (where docker-compose is executed from)
cd ..

# Execute the appropriate docker compose command
if [ "$REMOVE_VOLUMES" = true ]; then
    echo "🗑️  Stopping containers and removing volumes..."
    docker compose -f _docker_production/docker-compose.staging.yml down -v
    echo ""
    echo "✅ Containers stopped and volumes removed!"
elif [ "$REMOVE_CONTAINERS" = true ]; then
    echo "🗑️  Stopping and removing containers..."
    docker compose -f _docker_production/docker-compose.staging.yml down
    echo ""
    echo "✅ Containers stopped and removed!"
else
    echo "⏸️  Stopping containers (keeping them for restart)..."
    docker compose -f _docker_production/docker-compose.staging.yml stop
    echo ""
    echo "✅ Containers stopped!"
    echo ""
    echo "💡 To start them again:"
    echo "   docker compose -f _docker_production/docker-compose.staging.yml start"
    echo "   or run: ./deploy-staging.sh"
fi

cd _docker_production

echo ""
echo "═══════════════════════════════════════════════════════"
echo "📋 Available stop options:"
echo "   Stop only:          ./stop-staging.sh"
echo "   Stop & remove:      ./stop-staging.sh --remove"
echo "   Stop & clean all:   ./stop-staging.sh --clean"
echo "═══════════════════════════════════════════════════════"
