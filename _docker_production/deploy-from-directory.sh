#!/bin/bash
set -e  # Exit on error

chmod 777 -Rf ./storage

# Build from parent directory (where Dockerfile is located)
cd ..

# Set proxy for Docker build
export HTTP_PROXY="http://10.60.3.254:3128"
export HTTPS_PROXY="http://10.60.3.254:3128"
export NO_PROXY="localhost,127.0.0.1"

docker compose -f _docker_production/docker-compose.yml build \
  --build-arg HTTP_PROXY="$HTTP_PROXY" \
  --build-arg HTTPS_PROXY="$HTTPS_PROXY" \
  --build-arg NO_PROXY="$NO_PROXY" \
  --no-cache --pull app

docker compose -f _docker_production/docker-compose.yml up -d --force-recreate --remove-orphans

# Laravel commands (use the production compose file)
docker compose -f _docker_production/docker-compose.yml exec app bash -c "php artisan migrate --force && \
    php artisan db:seed --force && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan optimize:clear"

docker compose -f _docker_production/docker-compose.yml exec app bash -c "git config --global --add safe.directory /var/www/html && /var/www/html/git_info.sh"
docker compose -f _docker_production/docker-compose.yml exec app cat /var/www/html/storage/app/git-info.json