# Ensure that nobody is trying to mount an .env file into the container
# This could cause issues between container variables and the .env file, and is not recommended for production environments.
if [ -f "/var/www/html/.env" ]; then
    if mountpoint -q "/var/www/html/.env"; then
        echo "[ENTRYPOINT] ERROR: The /var/www/html/.env file is a volume mount. This is not recommended for production environments and can cause issues with file permissions. Please use the docker environment variables or the 'env_file' option in docker-compose instead of mounting an .env file."
        exit 1
    else
        echo "[ENTRYPOINT] Warning: The /var/www/html/.env file exists but is not a volume mount. This is not recommended for production environments and can cause issues with file permissions. Please use the docker environment variables or the 'env_file' option in docker-compose instead of using an .env file."
    fi
fi

# Load the /var/www/html/.env.example file and export all variables it contains,
# but only if they are not already set in the environment.
# This allows us to provide default values for all environment variables that the application might need,
# without overriding any values that are already set in the environment.
if [ -f "/var/www/html/.env.example" ]; then
    echo "[ENTRYPOINT] Loading environment variables from /var/www/html/.env.example..."
    set -o allexport
    source <(grep -v '^#' /var/www/html/.env.example | xargs -d '\n' -I {} sh -c 'if [ -z "${!{}}" ]; then echo export {}; fi')
    set +o allexport
else
    echo "[ENTRYPOINT] Warning: /var/www/html/.env.example file not found. Skipping loading environment variables from it."
fi

# Define dynamic environment variables
export APP_URL="${APP_PROTOCOL}://${APP_HOST}"
export VITE_REVERB_HOST="${APP_HOST}"
export VITE_REVERB_SCHEME="${APP_PROTOCOL}"
export REVERB_HOST="${REVERB_HOST:-reverb}"
