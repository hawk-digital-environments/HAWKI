#!/bin/bash
# =====================================================
# HAWKI Frontend Build Script (Docker Environment)
# =====================================================
# Builds frontend assets using Docker environment variables
# This ensures the built assets use the correct WebSocket URLs
# =====================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROFILE="${1:-dev}"

echo "🎨 Building frontend for Docker $PROFILE environment..."
echo ""

# Load Docker environment variables
if [ -f "$SCRIPT_DIR/env/.env" ]; then
    echo "📦 Loading Docker environment ($PROFILE)..."
    set -a
    source "$SCRIPT_DIR/env/.env"
    set +a
fi

# Load profile-specific defaults
if [ -f "$SCRIPT_DIR/env/.env.$PROFILE" ]; then
    echo "📦 Loading profile defaults (.env.$PROFILE)..."
    set -a
    source "$SCRIPT_DIR/env/.env.$PROFILE"
    set +a
fi

# Export Vite variables explicitly
export VITE_APP_NAME="${APP_NAME:-HAWKI2}"
export VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
export VITE_REVERB_HOST="${REVERB_HOST}"
export VITE_REVERB_PORT="${REVERB_PORT}"
export VITE_REVERB_SCHEME="${REVERB_SCHEME}"

echo ""
echo "🔧 Build Configuration:"
echo "   APP_NAME:            $VITE_APP_NAME"
echo "   REVERB_HOST:         $VITE_REVERB_HOST"
echo "   REVERB_PORT:         $VITE_REVERB_PORT"
echo "   REVERB_SCHEME:       $VITE_REVERB_SCHEME"
echo "   REVERB_APP_KEY:      ${VITE_REVERB_APP_KEY:0:8}..."
echo ""

# Navigate to project root
cd "$SCRIPT_DIR/.."

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "📦 Installing npm dependencies..."
    npm install
    echo ""
fi

# Build frontend
echo "🔨 Building frontend assets..."
npm run build

echo ""
echo "✅ Frontend build complete!"
echo ""
echo "📝 The built assets in public/build/ now use:"
echo "   WebSocket URL: ${VITE_REVERB_SCHEME}://${VITE_REVERB_HOST}:${VITE_REVERB_PORT}"
echo ""
