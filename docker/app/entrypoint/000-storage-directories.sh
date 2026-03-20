#!/bin/bash

echo "[ENTRYPOINT] Ensuring Laravel storage directories exist and are owned by www-data..."

declare -a laravelStorageDirs=(
  "/var/www/html/storage"
  "/var/www/html/storage/logs"
  "/var/www/html/storage/app"
  "/var/www/html/storage/app/public"
  "/var/www/html/storage/app/data_repo"
  "/var/www/html/storage/framework"
  "/var/www/html/storage/framework/cache"
  "/var/www/html/storage/framework/sessions"
  "/var/www/html/storage/framework/testing"
  "/var/www/html/storage/framework/views"
)

for dir in "${laravelStorageDirs[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
    fi
    # If directory is not owned by www-data, change ownership
    if [ "$(stat -c '%U' "$dir")" != "www-data" ]; then
        echo " -> Changing ownership of $dir to www-data"
        chown www-data:www-data -R "$dir"
        chmod -R 775 "$dir"
    fi
done
