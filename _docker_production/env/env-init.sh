#!/usr/bin/env bash
# =====================================================
# HAWKI Environment Initialization Script
# =====================================================
# Automatically generates .env file from .env.example + profile defaults
# Generates missing encryption keys
# Sets up SSL certificates for dev mode
# Configures /etc/hosts for local domains (dev mode)
#
# Usage:
#   ./env-init.sh [--profile=dev|staging|prod] [--non-interactive] [--force]
#
# Options:
#   --profile=PROFILE       Environment profile (dev, staging, prod)
#   --non-interactive       Run without prompts (use defaults)
#   --force                 Overwrite existing .env file
# =====================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_DIR="$(dirname "$SCRIPT_DIR")"
PROFILE="${DEPLOY_PROFILE:-dev}"
NON_INTERACTIVE=false
FORCE=false
CUSTOM_ENV="$SCRIPT_DIR/.env.custom"

# Portable sed in-place editing (works on both macOS and Linux)
sed_inplace() {
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "$@"
    else
        sed -i "$@"
    fi
}

# Parse arguments
for arg in "$@"; do
    case $arg in
        --profile=*)
            PROFILE="${arg#*=}"
            ;;
        --non-interactive)
            NON_INTERACTIVE=true
            ;;
        --force)
            FORCE=true
            ;;
        *)
            echo -e "${RED}Unknown option: $arg${NC}"
            exit 1
            ;;
    esac
done

# Validate profile
if [[ ! "$PROFILE" =~ ^(dev|staging|prod)$ ]]; then
    echo -e "${RED}❌ Invalid profile: $PROFILE${NC}"
    echo -e "${YELLOW}Valid profiles: dev, staging, prod${NC}"
    exit 1
fi

# =====================================================
# Helper Functions
# =====================================================

# Function to get current value from .env
get_current_value() {
    local key=$1
    local file=$2
    
    if [ -f "$file" ] && grep -q "^${key}=" "$file"; then
        grep "^${key}=" "$file" | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'"
    else
        echo ""
    fi
}

# Function to get or prompt for custom value
get_custom_value() {
    local key=$1
    local prompt_text=$2
    local default_value=$3
    local current_value=$4
    
    # If value already exists in .env and we're not forcing, keep it
    if [ -n "$current_value" ] && [ "$current_value" != "your-domain.com" ] && [ "$current_value" != "staging.your-domain.com" ] && [ "$current_value" != "" ]; then
        if [ "$NON_INTERACTIVE" = false ]; then
            read -p "$prompt_text [current: $current_value]: " value
            value=${value:-$current_value}
            echo "$value"
        else
            echo "$current_value"
        fi
        return 0
    fi
    
    # No valid value exists, prompt for it
    if [ "$NON_INTERACTIVE" = false ]; then
        read -p "$prompt_text [$default_value]: " value
        value=${value:-$default_value}
        echo "$value"
    else
        # Non-interactive: use default
        echo "$default_value"
    fi
}

# Function to generate random key
generate_key() {
    echo "base64:$(openssl rand -base64 32)"
}

# Function to check and generate missing keys
ensure_key() {
    local key_name=$1
    local key_description=$2
    
    if ! grep -q "^${key_name}=" "$SCRIPT_DIR/.env" || grep -q "^${key_name}=$" "$SCRIPT_DIR/.env" || grep -q "^${key_name}= *$" "$SCRIPT_DIR/.env"; then
        local new_key=$(generate_key)
        if grep -q "^${key_name}=" "$SCRIPT_DIR/.env"; then
            # Key exists but is empty, replace it
            sed_inplace "s|^${key_name}=.*|${key_name}=${new_key}|" "$SCRIPT_DIR/.env"
        else
            # Key doesn't exist, append it
            echo "${key_name}=${new_key}" >> "$SCRIPT_DIR/.env"
        fi
        echo -e "${GREEN}   ✓ Generated ${key_description}${NC}"
    fi
}

# Helper function to set or update value in .env
set_env_value() {
    local key=$1
    local value=$2
    local file="$SCRIPT_DIR/.env"
    
    if grep -q "^${key}=" "$file"; then
        # Key exists, update it
        sed_inplace "s|^${key}=.*|${key}=${value}|" "$file"
    else
        # Key doesn't exist, append it
        echo "${key}=${value}" >> "$file"
    fi
}

# =====================================================
# Main Script
# =====================================================

echo -e "${BLUE}🔧 HAWKI Environment Initialization${NC}"
echo -e "${BLUE}Profile: ${GREEN}$PROFILE${NC}"
echo ""

# Read current values BEFORE checking if file exists
CURRENT_SERVER_NAME=""
CURRENT_PROXY=""
if [ -f "$SCRIPT_DIR/.env" ]; then
    CURRENT_SERVER_NAME=$(get_current_value "NGINX_SERVER_NAME" "$SCRIPT_DIR/.env")
    CURRENT_PROXY=$(get_current_value "DOCKER_HTTP_PROXY" "$SCRIPT_DIR/.env")
fi

# Check if .env already exists
if [ -f "$SCRIPT_DIR/.env" ] && [ "$FORCE" = false ]; then
    echo -e "${YELLOW}⚠️  .env file already exists!${NC}"
    if [ "$NON_INTERACTIVE" = false ]; then
        read -p "Do you want to overwrite it? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo -e "${GREEN}✅ Using existing .env file${NC}"
            exit 0
        fi
    else
        echo -e "${GREEN}✅ Using existing .env file (non-interactive mode)${NC}"
        exit 0
    fi
fi

# Generate .env from .env.example + profile defaults
echo -e "${BLUE}📝 Generating .env file...${NC}"

# Start with .env.example if it exists
if [ -f "$SCRIPT_DIR/.env.example" ]; then
    cp "$SCRIPT_DIR/.env.example" "$SCRIPT_DIR/.env"
    echo -e "${GREEN}   ✓ Base configuration from .env.example${NC}"
else
    touch "$SCRIPT_DIR/.env"
    echo -e "${YELLOW}   ⚠ No .env.example found, creating empty .env${NC}"
fi

# Smart merge profile-specific defaults
PROFILE_ENV="$SCRIPT_DIR/.env.$PROFILE"
if [ -f "$PROFILE_ENV" ]; then
    echo -e "${GREEN}   ✓ Merging $PROFILE-specific configuration...${NC}"
    
    # Read profile file line by line
    while IFS= read -r line || [ -n "$line" ]; do
        # Skip empty lines and comments
        [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
        
        # Extract key from line (everything before first =)
        key=$(echo "$line" | cut -d'=' -f1)
        
        # Skip if no valid key
        [[ -z "$key" ]] && continue
        
        # Get the full value (everything after first =)
        value=$(echo "$line" | cut -d'=' -f2-)
        
        # Update or append the key in .env
        if grep -q "^${key}=" "$SCRIPT_DIR/.env"; then
            # Key exists, replace it
            sed_inplace "s|^${key}=.*|${key}=${value}|" "$SCRIPT_DIR/.env"
        else
            # Key doesn't exist, append it
            echo "${key}=${value}" >> "$SCRIPT_DIR/.env"
        fi
    done < "$PROFILE_ENV"
    
    echo -e "${GREEN}   ✓ Profile defaults merged successfully${NC}"
fi

# Generate all encryption keys
echo -e "${BLUE}🔐 Checking encryption keys...${NC}"
ensure_key "APP_KEY" "APP_KEY"
ensure_key "USERDATA_ENCRYPTION_SALT" "USERDATA_ENCRYPTION_SALT"
ensure_key "INVITATION_SALT" "INVITATION_SALT"
ensure_key "AI_CRYPTO_SALT" "AI_CRYPTO_SALT"
ensure_key "PASSKEY_SALT" "PASSKEY_SALT"
ensure_key "BACKUP_SALT" "BACKUP_SALT"
ensure_key "REVERB_APP_KEY" "REVERB_APP_KEY"
ensure_key "REVERB_APP_SECRET" "REVERB_APP_SECRET"

# Set queue connection if not set
echo -e "${BLUE}⚙️  Checking queue configuration...${NC}"
if ! grep -q "^QUEUE_CONNECTION=" "$SCRIPT_DIR/.env"; then
    set_env_value "QUEUE_CONNECTION" "database"
    echo -e "${GREEN}   ✓ QUEUE_CONNECTION set to database${NC}"
fi

# Set default passwords if they are "changeme" or empty
echo -e "${BLUE}🔒 Checking passwords...${NC}"

# DB_PASSWORD
if grep -q "^DB_PASSWORD=changeme" "$SCRIPT_DIR/.env" || grep -q "^DB_PASSWORD=$" "$SCRIPT_DIR/.env" || ! grep -q "^DB_PASSWORD=" "$SCRIPT_DIR/.env"; then
    set_env_value "DB_PASSWORD" "password"
    echo -e "${GREEN}   ✓ DB_PASSWORD set to default${NC}"
fi

# REDIS_PASSWORD
if grep -q "^REDIS_PASSWORD=changeme" "$SCRIPT_DIR/.env" || grep -q "^REDIS_PASSWORD=$" "$SCRIPT_DIR/.env" || ! grep -q "^REDIS_PASSWORD=" "$SCRIPT_DIR/.env"; then
    set_env_value "REDIS_PASSWORD" "password"
    echo -e "${GREEN}   ✓ REDIS_PASSWORD set to default${NC}"
fi

# HAWKI_FILE_CONVERTER_API_KEY
if grep -q "^HAWKI_FILE_CONVERTER_API_KEY=changeme" "$SCRIPT_DIR/.env" || grep -q "^HAWKI_FILE_CONVERTER_API_KEY=$" "$SCRIPT_DIR/.env" || ! grep -q "^HAWKI_FILE_CONVERTER_API_KEY=" "$SCRIPT_DIR/.env"; then
    # Generate a secure random key for file converter
    CONVERTER_KEY=$(openssl rand -hex 32)
    set_env_value "HAWKI_FILE_CONVERTER_API_KEY" "$CONVERTER_KEY"
    echo -e "${GREEN}   ✓ HAWKI_FILE_CONVERTER_API_KEY generated${NC}"
fi

# Profile-specific configuration
echo -e "${BLUE}🌍 Profile-specific configuration...${NC}"
echo ""

# Note: CURRENT_SERVER_NAME and CURRENT_PROXY are already loaded at the beginning

# Determine configuration keys based on profile
if [ "$PROFILE" = "dev" ]; then
    # Dev uses fixed values
    SERVER_NAME="app.hawki.dev"
    APP_URL="https://${SERVER_NAME}"
    PROXY_URL=""
    
    echo -e "${GREEN}   ✓ Dev profile uses fixed configuration${NC}"
    echo -e "     SERVER_NAME: ${SERVER_NAME}"
    echo -e "     APP_URL: ${APP_URL}"
    echo ""
else
    # Staging/Prod: prompt for custom values
    echo -e "${YELLOW}   Configuration for ${PROFILE} environment${NC}"
    echo ""
    
    # Determine default server name
    if [ "$PROFILE" = "staging" ]; then
        default_server="staging.your-domain.com"
    else
        default_server="your-domain.com"
    fi
    
    # Get server name
    echo ""
    echo -e "${BLUE}📍 Server Configuration${NC}"
    echo ""
    SERVER_NAME=$(get_custom_value "NGINX_SERVER_NAME" "   Enter server domain name (without https://)" "$default_server" "$CURRENT_SERVER_NAME")
    APP_URL="https://${SERVER_NAME}"
    
    echo ""
    echo -e "${GREEN}   ✓ Server domain: ${SERVER_NAME}${NC}"
    echo -e "${GREEN}   ✓ App URL: ${APP_URL}${NC}"
    echo ""
    
    # Get proxy configuration
    echo -e "${BLUE}🔐 Proxy Configuration${NC}"
    echo -e "${YELLOW}   (Leave empty if no proxy is required)${NC}"
    echo ""
    PROXY_URL=$(get_custom_value "DOCKER_HTTP_PROXY" "   Enter HTTP/HTTPS proxy URL" "" "$CURRENT_PROXY")
    
    if [ -n "$PROXY_URL" ]; then
        echo -e "${GREEN}   ✓ Proxy configured: ${PROXY_URL}${NC}"
    else
        echo -e "${GREEN}   ✓ No proxy configured${NC}"
    fi
    echo ""
fi

# Apply configuration to .env file
echo -e "${BLUE}📝 Applying configuration to .env...${NC}"

# Set APP_URL (derived from SERVER_NAME)
set_env_value "APP_URL" "$APP_URL"
echo -e "${GREEN}   ✓ APP_URL=${APP_URL}${NC}"

# Set NGINX_SERVER_NAME
set_env_value "NGINX_SERVER_NAME" "$SERVER_NAME"
echo -e "${GREEN}   ✓ NGINX_SERVER_NAME=${SERVER_NAME}${NC}"

# Set REVERB_HOST (same as SERVER_NAME)
set_env_value "REVERB_HOST" "$SERVER_NAME"
echo -e "${GREEN}   ✓ REVERB_HOST=${SERVER_NAME}${NC}"

# Set VITE_REVERB_HOST (same as SERVER_NAME)
set_env_value "VITE_REVERB_HOST" "$SERVER_NAME"
echo -e "${GREEN}   ✓ VITE_REVERB_HOST=${SERVER_NAME}${NC}"

# Set proxy configuration only if explicitly provided by user
if [ -n "$PROXY_URL" ]; then
    set_env_value "DOCKER_HTTP_PROXY" "$PROXY_URL"
    set_env_value "DOCKER_HTTPS_PROXY" "$PROXY_URL"
    echo -e "${GREEN}   ✓ DOCKER_HTTP_PROXY=${PROXY_URL}${NC}"
    echo -e "${GREEN}   ✓ DOCKER_HTTPS_PROXY=${PROXY_URL}${NC}"
else
    # Check if proxy was already set by profile merge
    MERGED_PROXY=$(get_current_value "DOCKER_HTTP_PROXY" "$SCRIPT_DIR/.env")
    if [ -n "$MERGED_PROXY" ]; then
        echo -e "${GREEN}   ✓ Using proxy from profile: ${MERGED_PROXY}${NC}"
    else
        # No proxy configured at all, remove any empty values
        sed_inplace '/^DOCKER_HTTP_PROXY=/d' "$SCRIPT_DIR/.env" 2>/dev/null || true
        sed_inplace '/^DOCKER_HTTPS_PROXY=/d' "$SCRIPT_DIR/.env" 2>/dev/null || true
        echo -e "${GREEN}   ✓ No proxy configured${NC}"
    fi
fi

echo ""
    
# Dev-specific setup
if [ "$PROFILE" = "dev" ]; then
    echo -e "${BLUE}🔧 Dev-specific setup...${NC}"
    
    # Generate SSL certificates for local domains
    CERTS_DIR="$DOCKER_DIR/certs"
    if [ ! -f "$CERTS_DIR/app.hawki.dev.crt" ]; then
        echo -e "${GREEN}   Generating SSL certificates...${NC}"
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "$CERTS_DIR/app.hawki.dev.key" \
            -out "$CERTS_DIR/app.hawki.dev.crt" \
            -subj "/C=DE/ST=Lower Saxony/L=Hildesheim/O=HAWKI Dev/CN=app.hawki.dev" \
            > /dev/null 2>&1
        
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "$CERTS_DIR/db.hawki.dev.key" \
            -out "$CERTS_DIR/db.hawki.dev.crt" \
            -subj "/C=DE/ST=Lower Saxony/L=Hildesheim/O=HAWKI Dev/CN=db.hawki.dev" \
            > /dev/null 2>&1
        
        # Create symlinks for default cert
        ln -sf app.hawki.dev.crt "$CERTS_DIR/cert.pem"
        ln -sf app.hawki.dev.key "$CERTS_DIR/key.pem"
        
        echo -e "${GREEN}   ✓ SSL certificates generated${NC}"
        
        # Add to macOS keychain if possible
        if command -v security &> /dev/null; then
            echo -e "${YELLOW}   Adding certificates to macOS keychain...${NC}"
            sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain "$CERTS_DIR/app.hawki.dev.crt" 2>/dev/null || true
            sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain "$CERTS_DIR/db.hawki.dev.crt" 2>/dev/null || true
            echo -e "${GREEN}   ✓ Certificates added to keychain${NC}"
        fi
    fi
    
    # Setup /etc/hosts entries
    if ! grep -q "app.hawki.dev" /etc/hosts 2>/dev/null; then
        echo -e "${YELLOW}   Setting up /etc/hosts entries...${NC}"
        echo "127.0.0.1 app.hawki.dev" | sudo tee -a /etc/hosts > /dev/null
        echo "127.0.0.1 db.hawki.dev" | sudo tee -a /etc/hosts > /dev/null
        echo -e "${GREEN}   ✓ /etc/hosts entries added${NC}"
    fi
    
    # Set DOCKER_UID and DOCKER_GID to current user
    CURRENT_UID=$(id -u)
    CURRENT_GID=$(id -g)
    if grep -q "^DOCKER_UID=" "$SCRIPT_DIR/.env"; then
        sed_inplace "s|^DOCKER_UID=.*|DOCKER_UID=${CURRENT_UID}|" "$SCRIPT_DIR/.env"
    else
        echo "DOCKER_UID=${CURRENT_UID}" >> "$SCRIPT_DIR/.env"
    fi
    if grep -q "^DOCKER_GID=" "$SCRIPT_DIR/.env"; then
        sed_inplace "s|^DOCKER_GID=.*|DOCKER_GID=${CURRENT_GID}|" "$SCRIPT_DIR/.env"
    else
        echo "DOCKER_GID=${CURRENT_GID}" >> "$SCRIPT_DIR/.env"
    fi
fi

echo ""
echo -e "${GREEN}✅ Environment initialized successfully!${NC}"
echo -e "${BLUE}Profile: ${GREEN}$PROFILE${NC}"
echo -e "${BLUE}Config: ${GREEN}$SCRIPT_DIR/.env${NC}"

if [ "$PROFILE" = "dev" ]; then
    echo ""
    echo -e "${YELLOW}📝 Next steps:${NC}"
    echo -e "   1. Review .env file: ${GREEN}nano $SCRIPT_DIR/.env${NC}"
    echo -e "   2. Deploy: ${GREEN}./deploy-dev.sh${NC}"
    echo -e "   3. Access: ${GREEN}https://app.hawki.dev${NC}"
    echo -e "   4. Adminer: ${GREEN}https://db.hawki.dev${NC}"
fi

exit 0
