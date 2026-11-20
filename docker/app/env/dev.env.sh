#!/bin/bash

export XDEBUG_MODE=${XDEBUG_MODE:-'debug,coverage'}
export XDEBUG_START_WITH_REQUEST=${XDEBUG_START_WITH_REQUEST:-yes}
export XDEBUG_CLIENT_HOST=${XDEBUG_CLIENT_HOST:-host.docker.internal}
export XDEBUG_CLIENT_PORT=${XDEBUG_CLIENT_PORT:-9003}
export XDEBUG_LOG=${XDEBUG_LOG:-/var/www/html/storage/logs/xdebug.log}
export XDEBUG_LOG_LEVEL=${XDEBUG_LOG_LEVEL:-7}

# If DOCKER_PROJECT_PROTOCOL and DOCKER_PROJECT_HOST, override the APP_URL
if [ -n "$DOCKER_PROJECT_PROTOCOL" ] && [ -n "$DOCKER_PROJECT_HOST" ]; then
    export APP_URL="${DOCKER_PROJECT_PROTOCOL}://${DOCKER_PROJECT_HOST}"
fi

# Derive the ASSET_URL from the APP_URL if it is not set explicitly.
# This makes sure generated asset URLs point to the externally visible URL of the app,
# even if the container is internally reachable under a different host.
# Note: an exported ASSET_URL always wins over a value defined in the .env file.
if [ -n "$APP_URL" ]; then
    export ASSET_URL="${ASSET_URL:-$APP_URL}"
fi
