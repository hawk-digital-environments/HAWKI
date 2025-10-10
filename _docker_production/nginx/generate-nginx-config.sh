#!/bin/bash

# This script generates the nginx.default.conf from the template
# It uses environment variables from env/.env to customize the configuration

set -e

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="$(dirname "$SCRIPT_DIR")"

# Determine deployment profile (dev, staging, prod)
DEPLOY_PROFILE="${DEPLOY_PROFILE:-dev}"

# Source profile defaults first (for default ports, etc.)
if [ -f "$DOCKER_DIR/env/.env.${DEPLOY_PROFILE}" ]; then
    set -a
    source "$DOCKER_DIR/env/.env.${DEPLOY_PROFILE}"
    set +a
fi

# Then source .env file to override with user-specific values
if [ -f "$DOCKER_DIR/env/.env" ]; then
    set -a
    source "$DOCKER_DIR/env/.env"
    set +a
fi

# Set default values if not provided (final fallback)
export NGINX_SERVER_NAME="${NGINX_SERVER_NAME:-_}"
export NGINX_HTTP_PORT="${NGINX_HTTP_PORT:-80}"
export NGINX_HTTPS_PORT="${NGINX_HTTPS_PORT:-443}"

# IPv6 Support (empty = disabled, or full listen directive)
export NGINX_LISTEN_IPV6_HTTP="${NGINX_ENABLE_IPV6:-false}"
if [ "$NGINX_LISTEN_IPV6_HTTP" = "true" ]; then
    export NGINX_LISTEN_IPV6_HTTP="listen [::]:${NGINX_HTTP_PORT} default_server;"
else
    export NGINX_LISTEN_IPV6_HTTP="# IPv6 disabled"
fi

# Extra port support (e.g., port 3000)
export NGINX_LISTEN_EXTRA_PORT="${NGINX_EXTRA_PORT:-}"
if [ -n "$NGINX_LISTEN_EXTRA_PORT" ]; then
    export NGINX_LISTEN_EXTRA_PORT="listen ${NGINX_LISTEN_EXTRA_PORT} default_server;"
else
    export NGINX_LISTEN_EXTRA_PORT="# No extra port"
fi

# HTTPS Listen directive
if [ "$NGINX_ENABLE_IPV6" = "true" ]; then
    export NGINX_LISTEN_HTTPS="listen ${NGINX_HTTPS_PORT} ssl;
        listen [::]:${NGINX_HTTPS_PORT} ssl;"
else
    export NGINX_LISTEN_HTTPS="listen ${NGINX_HTTPS_PORT} ssl;"
fi

# HTTP2 Configuration (new syntax vs old syntax)
export NGINX_HTTP2_STYLE="${NGINX_HTTP2_STYLE:-new}"
if [ "$NGINX_HTTP2_STYLE" = "new" ]; then
    export NGINX_HTTP2_CONFIG="http2 on;"
else
    # Old style is included in listen directive above
    export NGINX_HTTP2_CONFIG="# HTTP2 configured in listen directive"
fi

echo "🔧 Generating nginx.default.conf from template..."
echo "   Profile: $DEPLOY_PROFILE"
echo "   Server Name: $NGINX_SERVER_NAME"
echo "   HTTP Port: $NGINX_HTTP_PORT"
echo "   HTTPS Port: $NGINX_HTTPS_PORT"
echo "   IPv6 Support: ${NGINX_ENABLE_IPV6:-false}"
[ -n "$NGINX_EXTRA_PORT" ] && echo "   Extra Port: $NGINX_EXTRA_PORT"

# Choose the correct template based on profile
TEMPLATE_FILE="$SCRIPT_DIR/nginx.template.${DEPLOY_PROFILE}"

if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "❌ Error: Template file not found: $TEMPLATE_FILE"
    echo "   Available profiles: dev, staging, prod"
    exit 1
fi

echo "   Using template: nginx.template.${DEPLOY_PROFILE}"

# Generate the config file using sed (more portable than envsubst)
sed -e "s|\${NGINX_SERVER_NAME}|${NGINX_SERVER_NAME}|g" \
    -e "s|\${NGINX_HTTP_PORT}|${NGINX_HTTP_PORT}|g" \
    -e "s|\${NGINX_HTTPS_PORT}|${NGINX_HTTPS_PORT}|g" \
    -e "s|\${NGINX_LISTEN_IPV6_HTTP}|${NGINX_LISTEN_IPV6_HTTP}|g" \
    -e "s|\${NGINX_LISTEN_EXTRA_PORT}|${NGINX_LISTEN_EXTRA_PORT}|g" \
    -e "s|\${NGINX_LISTEN_HTTPS}|${NGINX_LISTEN_HTTPS}|g" \
    -e "s|\${NGINX_HTTP2_CONFIG}|${NGINX_HTTP2_CONFIG}|g" \
    "$TEMPLATE_FILE" > "$SCRIPT_DIR/nginx.default.conf"

echo "✅ nginx.default.conf generated successfully!"
