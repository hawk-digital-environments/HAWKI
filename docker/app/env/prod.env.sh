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
    # Parse dotenv-style KEY=VALUE lines with proper normalization:
    #   - Skip blank lines and comment lines.
    #   - Split only on the first '=' so values containing '=' are preserved.
    #   - Strip surrounding single or double quotes from the value.
    #   - Remove inline comments (unquoted ' #...' suffixes).
    #   - Trim leading/trailing whitespace from both key and value.
    #   - Only export a key when it is not already present in the environment.
    while IFS= read -r line; do
        # Skip blank lines and comment lines
        [[ "$line" =~ ^[[:space:]]*$ || "$line" =~ ^[[:space:]]*# ]] && continue

        # Skip lines without '='
        [[ "$line" != *=* ]] && continue

        # Split on the first '=' only
        key="${line%%=*}"
        value="${line#*=}"

        # Trim whitespace from the key
        key="${key#"${key%%[![:space:]]*}"}"
        key="${key%"${key##*[![:space:]]}"}"

        # Validate key name
        [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue

        # Trim leading whitespace from value
        value="${value#"${value%%[![:space:]]*}"}"

        # Strip surrounding double or single quotes (dotenv style),
        # also discarding any trailing inline comment after the closing quote.
        if [[ "$value" =~ ^\"(.*)\"[[:space:]]*(#.*)?$ ]]; then
            value="${BASH_REMATCH[1]}"
        elif [[ "$value" =~ ^\'(.*)\'[[:space:]]*(#.*)?$ ]]; then
            value="${BASH_REMATCH[1]}"
        else
            # Unquoted value: strip trailing inline comment and whitespace
            value="${value%%[[:space:]]#*}"
            value="${value%"${value##*[![:space:]]}"}"
        fi

        # Only export if the variable is not already set in the environment
        [ -z "${!key+x}" ] && export "$key=$value"
    done < /var/www/html/.env.example
else
    echo "[ENTRYPOINT] Warning: /var/www/html/.env.example file not found. Skipping loading environment variables from it."
fi

# Define dynamic environment variables
export APP_URL="${APP_PROTOCOL}://${APP_HOST}"
export VITE_REVERB_HOST="${APP_HOST}"
export VITE_REVERB_SCHEME="${APP_PROTOCOL}"
export REVERB_HOST="${REVERB_HOST:-reverb}"
