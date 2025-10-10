#!/bin/bash
set -e  # Exit on error

echo "🚀 Starting HAWKI Production Deployment (build from image)..."

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "❌ Error: .env file not found in _docker_production directory!"
    echo "   Please create .env file from .env.example"
    exit 1
fi

# Generate nginx configuration from template
if [ -f "generate-nginx-config.sh" ]; then
    echo "🔧 Generating Nginx configuration..."
    ./generate-nginx-config.sh
fi

# Fix storage permissions for production (Linux only, skip on macOS)
if [ -d "./storage" ]; then
    # Check if running on Linux (where permissions are critical for Docker)
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        echo "📁 Setting storage ownership and permissions (Linux)..."
        STORAGE_UID=${DOCKER_UID:-33}
        STORAGE_GID=${DOCKER_GID:-33}
        
        # Use sudo only if not root
        if [ "$EUID" -ne 0 ]; then
            sudo chown -R ${STORAGE_UID}:${STORAGE_GID} ./storage 2>/dev/null || true
        else
            chown -R ${STORAGE_UID}:${STORAGE_GID} ./storage 2>/dev/null || true
        fi
        
        chmod -R 755 ./storage 2>/dev/null || true
        find ./storage -type f -exec chmod 644 {} \; 2>/dev/null || true
        echo "✅ Storage permissions set (UID:${STORAGE_UID}, GID:${STORAGE_GID})"
        echo ""
    else
        # Skipping storage permissions (not on Linux, Docker handles this)
        echo ""
    fi
fi

# Build from parent directory (where Dockerfile is located)
cd ..

# Load proxy configuration from .env file
if [ -f "_docker_production/.env" ]; then
    export HTTP_PROXY=$(grep -E "^DOCKER_HTTP_PROXY=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    export HTTPS_PROXY=$(grep -E "^DOCKER_HTTPS_PROXY=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    export NO_PROXY=$(grep -E "^DOCKER_NO_PROXY=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    
    # Load VITE variables from .env
    export APP_NAME=$(grep -E "^APP_NAME=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    export REVERB_APP_KEY=$(grep -E "^REVERB_APP_KEY=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    export REVERB_HOST=$(grep -E "^REVERB_HOST=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    export VITE_REVERB_HOST=$(grep -E "^VITE_REVERB_HOST=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    export REVERB_PORT=$(grep -E "^REVERB_PORT=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
    export REVERB_SCHEME=$(grep -E "^REVERB_SCHEME=" _docker_production/.env | cut -d '=' -f2- | tr -d '"' | tr -d "'")
fi

# Set defaults for VITE variables
export VITE_APP_NAME="${APP_NAME:-HAWKI2}"
export VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
export VITE_REVERB_HOST="${VITE_REVERB_HOST:-$REVERB_HOST}"
export VITE_REVERB_PORT="${REVERB_PORT:-443}"
export VITE_REVERB_SCHEME="${REVERB_SCHEME:-https}"

echo ""
echo "🔧 Build configuration:"
echo "   VITE_REVERB_HOST: ${VITE_REVERB_HOST}"
echo "   VITE_REVERB_PORT: ${VITE_REVERB_PORT}"
echo "   VITE_REVERB_SCHEME: ${VITE_REVERB_SCHEME}"
if [ -n "$HTTP_PROXY" ]; then
    echo "   Proxy: ${HTTP_PROXY}"
fi
echo ""

# Validate required VITE variables
if [ -z "$VITE_REVERB_HOST" ]; then
    echo "❌ ERROR: VITE_REVERB_HOST is not set!"
    echo ""
    echo "   Please set in _docker_production/.env:"
    echo "     REVERB_HOST=your-domain.com"
    echo "     VITE_REVERB_HOST=your-domain.com"
    echo ""
    exit 1
fi

if [ -z "$VITE_REVERB_APP_KEY" ]; then
    echo "⚠️  WARNING: REVERB_APP_KEY is not set!"
    echo "   WebSocket authentication may not work properly."
    echo ""
fi

echo "🔨 Building app image..."
docker compose -f _docker_production/docker-compose.prod.yml build \
  --no-cache --pull app

echo "🚢 Starting containers..."
docker compose -f _docker_production/docker-compose.prod.yml up -d --force-recreate --remove-orphans

echo "⚙️  Running Laravel optimizations..."
docker compose -f _docker_production/docker-compose.prod.yml exec app bash -c "php artisan migrate --force && \
    php artisan db:seed --force && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan optimize:clear"

echo "📝 Generating Git info..."
docker compose -f _docker_production/docker-compose.prod.yml exec app bash -c "echo '[]' > /var/www/html/storage/app/test_users.json && git config --global --add safe.directory /var/www/html && /var/www/html/git_info.sh"

# Get APP_URL from .env file
cd _docker_production
APP_URL=$(grep -E "^APP_URL=" .env | cut -d '=' -f2- | tr -d '"' | tr -d "'")

echo ""
echo "✅ Production deployment complete!"
echo ""
if [ -n "$APP_URL" ]; then
    echo "🌐 Access your application at:"
    echo "   → $APP_URL"
else
    echo "🌐 Access your application at:"
    echo "   → http://localhost"
fi
echo ""
