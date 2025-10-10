#!/bin/bash
set -e  # Exit on error

echo "🛑 Stopping HAWKI Development Containers..."
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

# Load dev-specific defaults
if [ -f "env/.env.dev" ]; then
    set -a
    source env/.env.dev
    set +a
else
    echo "⚠️  Warning: env/.env.dev not found!"
fi

# Export profile for docker-compose
export PROJECT_NAME=${PROJECT_NAME:-hawki-dev}
export PROJECT_HAWKI_IMAGE=${PROJECT_HAWKI_IMAGE:-hawki:dev}

# Change to parent directory (where docker-compose is executed from)
cd ..

# Execute the appropriate docker compose command
if [ "$REMOVE_VOLUMES" = true ]; then
    echo "🗑️  Stopping containers and removing volumes..."
    docker compose -f _docker_production/docker-compose.dev.yml down -v
    echo ""
    echo "✅ Containers stopped and volumes removed!"
elif [ "$REMOVE_CONTAINERS" = true ]; then
    echo "🗑️  Stopping and removing containers..."
    docker compose -f _docker_production/docker-compose.dev.yml down
    echo ""
    echo "✅ Containers stopped and removed!"
else
    echo "⏸️  Stopping containers (keeping them for restart)..."
    docker compose -f _docker_production/docker-compose.dev.yml stop
    echo ""
    echo "✅ Containers stopped!"
    echo ""
    echo "💡 To start them again:"
    echo "   docker compose -f _docker_production/docker-compose.dev.yml start"
    echo "   or run: ./deploy-dev.sh"
fi

cd _docker_production

echo ""
echo "═══════════════════════════════════════════════════════"
echo "📋 Available stop options:"
echo "   Stop only:          ./stop-dev.sh"
echo "   Stop & remove:      ./stop-dev.sh --remove"
echo "   Stop & clean all:   ./stop-dev.sh --clean"
echo "═══════════════════════════════════════════════════════"
