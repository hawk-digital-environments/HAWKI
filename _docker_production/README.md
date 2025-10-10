# HAWKI Docker Deployment

This directory contains all Docker production and staging deployment configurations.

## 📁 File Structure

```
_docker_production/
├── docker-compose.yml              # Unified docker-compose with profiles
├── deploy-prod.sh                  # Deploy with HAWK-provided official image (Production)
├── deploy-staging.sh               # Deploy with custom build (Staging/Testing)
├── deploy-dev.sh                   # Deploy for active development (live code)
├── stop-prod.sh                    # Stop production containers (with confirmation)
├── stop-staging.sh                 # Stop staging containers
├── stop-dev.sh                     # Stop development containers
├── update-dev.sh                   # Quick update for dev setup (no rebuild)
├── .env                            # Environment variables (NOT in Git!)
├── .env.example                    # Environment template
├── nginx.default.conf.template     # Nginx configuration template
├── storage/                        # Persistent storage
├── config/                         # Production config overrides
└── certs/                          # SSL certificates
```

---

## 🚀 Deployment Workflows

### 1. Production with Official HAWK Image

**Use Case**: Production servers using the official HAWK-provided Docker image

```bash
cd _docker_production
./deploy-prod.sh
```

**Stopping Containers**:
```bash
./stop-prod.sh              # Stop only (requires --yes confirmation)
./stop-prod.sh --remove     # Stop & remove containers
./stop-prod.sh --clean      # Stop, remove containers & volumes
```

**Characteristics**:
- ✅ Uses pre-built official image from HAWK registry
- ✅ Maximum stability and tested deployment
- ✅ Fast deployment (no build required)
- ❌ No custom code modifications possible

**When to use**:
- Production servers with official HAWK releases
- When you want the stable, tested version
- Standard deployments without modifications

---

### 2. Production/Test with Custom Modifications

**Use Case**: Staging or test servers with your own code modifications

```bash
cd _docker_production
./deploy-staging.sh
```

**Stopping Containers**:
```bash
./stop-staging.sh              # Stop only
./stop-staging.sh --remove     # Stop & remove containers
./stop-staging.sh --clean      # Stop, remove containers & volumes
```

**Characteristics**:
- ✅ Builds image from current repository code
- ✅ Includes your custom modifications
- ✅ Code baked into image (immutable deployment)
- ❌ Requires full rebuild (~10 minutes) for changes
- ❌ `git pull` has no effect (code in image)

**When to use**:
- Test servers with custom features
- Production with approved modifications
- When you need custom code but stable deployment
- Before contributing changes back to HAWK

---

### 3. Development/Testing During Active Development

**Use Case**: Development during active coding - fast iterations without rebuilds

#### Initial Setup:
```bash
cd _docker_production
./deploy-dev.sh --build
```

This will:
- Build the Docker image (if needed)
- Start containers with live code mounting
- **Install Composer dependencies** (`composer install`)
- **Install NPM dependencies** (`npm install`)
- **Build frontend assets** (`npm run build`)
- Run migrations and seeders
- Configure Laravel caching

#### Quick Updates (Fast Development Cycle):
```bash
cd ~/HAWKI
git pull  # or make local changes
cd _docker_production
./update-dev.sh  # ~10 seconds instead of 10 minutes!
```

**Stopping Containers**:
```bash
./stop-dev.sh              # Stop only (fastest restart)
./stop-dev.sh --remove     # Stop & remove containers
./stop-dev.sh --clean      # Stop, remove containers & volumes (clean slate)
```

The `update-dev.sh` script:
- Pulls latest code from Git
- **Auto-detects** if `composer.json` or `package.json` changed
- **Automatically** runs `composer install` if needed
- **Automatically** runs `npm install && npm run build` if needed
- Clears and rebuilds Laravel caches
- Updates Git info

**Characteristics**:
- ✅ Code mounted live from repository (`..:/var/www/html`)
- ✅ Changes immediately available (just refresh browser)
- ✅ Dependencies auto-updated when needed
- ✅ Perfect for rapid development cycles
- ✅ `git pull` → changes instantly live
- ⚠️ Not for production (live code mounting)

**When to use**:
- Active development and testing
- Rapid prototyping
- Feature development
- Bug fixing with quick iterations
- Before building final image with `deploy-staging.sh`

---

## 📊 Deployment Comparison

| Feature | Production<br>`deploy-prod.sh` | Staging<br>`deploy-staging.sh` | Development<br>`deploy-dev.sh` |
|---------|------------------------------|----------------------------------|--------------------------------------|
| **Use Case** | Production (HAWK Official) | Production/Test (Custom Code) | Active Development |
| **Image Source** | HAWK Registry | Built from Repo | Built from Repo |
| **Code Location** | Pre-built Image | Inside Built Image | Live Volume Mount |
| **Build Time** | None (pull only) | ~10 minutes | ~10 min (first time) |
| **Update Time** | Pull + Restart | ~10 minutes (rebuild) | ~10 seconds (`update-live.sh`) |
| **git pull Effect** | ❌ None | ❌ None (need rebuild) | ✅ Immediate |
| **Custom Code** | ❌ Not possible | ✅ Included in build | ✅ Live changes |
| **Stability** | 🔒 Highest | 🔒 High | ⚠️ Development only |
| **Security** | 🔒 Highest | 🔒 High | ⚠️ Medium |

---

## 🔄 Typical Development Workflow

### Phase 1: Active Development
```bash
# Start with live code for fast iterations
./deploy-live.sh --build

# Make changes, test immediately
vim ../app/Http/Controllers/SomeController.php
./update-live.sh  # 10 seconds

# Keep iterating...
git pull
./update-live.sh
```

### Phase 2: Testing & Approval
```bash
# Build custom image for stable testing
./deploy-staging.sh

# Test thoroughly with immutable deployment
# If issues found, go back to Phase 1
```

### Phase 3: Production
```bash
# Option A: Deploy with your custom build
./deploy-staging.sh

# Option B: Contribute to HAWK, then use official image
PR to https://github.com/hawk-digital-environments/HAWKI.git

# Wait for HAWK team to build official image
./deploy-prod.sh
```

---

## 🏗️ Architecture

### Why is Dockerfile in Root?

The `Dockerfile` **must** stay in the root directory because:

1. **Build Context**: Docker needs access to entire codebase
   ```dockerfile
   COPY --chown=www-data:www-data . .
   ```

2. **Multi-Stage Build**: Node builder needs `package.json`, `resources/`, etc.
   ```dockerfile
   FROM node:23-bookworm AS node_builder
   COPY --chown=node:node . .
   RUN npm install && npm run build
   ```

3. **All docker-compose files reference it**:
   ```yaml
   build:
     context: ..              # Points to repository root
     dockerfile: Dockerfile   # Dockerfile in root
   ```

### Build Context Explanation

```
HAWKI/                          ← Build context (root)
├── Dockerfile                  ← Build instructions
├── app/                        ← PHP code (needed for build)
├── resources/                  ← Frontend code (needed for build)
├── public/                     ← Static assets
├── package.json               ← NPM dependencies
├── composer.json              ← PHP dependencies
└── _docker_production/
    ├── docker-compose.prod.yml     ← References ../Dockerfile
    └── deploy-staging.sh      ← cd .. && docker compose build
```

---

## 🔧 Configuration Files

### docker-compose.prod.yml (Production & Custom Build)
- Uses `build: context: .. / dockerfile: Dockerfile`
- Code is **inside** the Docker image
- Mounts only: storage, config overrides
- Target: `app_prod` (optimized, no dev tools)
- Used by: `deploy-prod.sh` (pull image) & `deploy-staging.sh` (build image)

### docker-compose.dev.yml (Development)
- Uses same build context
- Code is **live-mounted**: `- ..:/var/www/html`
- Mounts: entire repository + storage overrides
- Target: `app_prod` (but with live code)
- Used by: `deploy-dev.sh` & `update-dev.sh`

---

## 🌍 Environment Variables

Configure your deployment in `.env`:

```bash
PROJECT_NAME=hawki-prod
PROJECT_HAWKI_IMAGE=digitalenvironments/hawki:latest
APP_URL=https://your-domain.com
DB_DATABASE=hawki_production
DB_USERNAME=hawki
DB_PASSWORD=secret
```

---

##  Proxy Configuration

All deploy scripts automatically configure HTTP proxy for the university network:

```bash
export HTTP_PROXY="http://10.60.3.254:3128"
export HTTPS_PROXY="http://10.60.3.254:3128"
export NO_PROXY="localhost,127.0.0.1"
```

This is passed to Docker build via `--build-arg` for:
- `apt-get` package downloads
- `npm install` during build
- `composer install` during build

---

## 🐛 Troubleshooting

### Stopping Containers

The correct way to stop containers depends on your deployment profile:

```bash
# Development
cd _docker_production
./stop-dev.sh              # Stop containers (keep for quick restart)
./stop-dev.sh --remove     # Stop & remove containers
./stop-dev.sh --clean      # Clean everything including volumes

# Staging
./stop-staging.sh          # Stop containers
./stop-staging.sh --remove # Stop & remove containers
./stop-staging.sh --clean  # Clean everything including volumes

# Production (requires confirmation)
./stop-prod.sh --yes              # Stop containers
./stop-prod.sh --remove --yes     # Stop & remove containers
./stop-prod.sh --clean --yes      # Clean everything including volumes
```

**Why use stop scripts instead of `docker compose down`?**
- The scripts automatically load the correct environment variables
- They set the correct COMPOSE_PROFILES (dev/staging/prod)
- They prevent "variable not set" warnings
- They provide clear feedback and options

### Port Already Allocated (MySQL 3306)
```bash
# Stop conflicting containers
docker ps | grep mysql
docker stop <container-name>
```

### Build Fails (Network Timeout)
- Check proxy configuration in deploy scripts
- Verify university network access

### Live Code Not Updating
```bash
# Clear Laravel caches
cd _docker_production
./update-live.sh
```

### Permission Issues
```bash
# Fix storage permissions
chmod -R 755 storage
find storage -type f -exec chmod 644 {} \;
```

---

## 🌐 Nginx Configuration

Nginx configuration is **dynamically generated** from a template using environment variables.

### Configuration Files

- **`nginx.default.conf.template`**: Template with placeholders (tracked in Git)
- **`nginx.default.conf`**: Generated config (NOT tracked, auto-generated)
- **`generate-nginx-config.sh`**: Generation script (runs automatically during deployment)

### Environment Variables

Configure in `.env`:

```bash
# Nginx Configuration
NGINX_SERVER_NAME=ki-test.hrz.uni-giessen.de  # Domain name (use '_' for wildcard)
NGINX_HTTP_PORT=80                             # HTTP port
NGINX_HTTPS_PORT=443                           # HTTPS port
NGINX_EXTRA_PORT=3000                          # Optional additional port (leave empty to disable)
NGINX_ENABLE_IPV6=false                        # Enable IPv6 support (true/false)
NGINX_HTTP2_STYLE=new                          # HTTP2 style (new/old)
```

### How It Works

1. **Template**: Contains placeholders like `${NGINX_SERVER_NAME}`
2. **Script**: Reads `.env` and replaces placeholders using `sed`
3. **Automatic**: Runs automatically when you execute any deploy script

### Manual Generation

If you need to regenerate the config manually:

```bash
cd _docker_production
./generate-nginx-config.sh
```

### Example Configurations

**Production with specific domain:**
```bash
NGINX_SERVER_NAME=ki.university.edu
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443
NGINX_EXTRA_PORT=
NGINX_ENABLE_IPV6=false
```

**Development with wildcard and extra port:**
```bash
NGINX_SERVER_NAME=_
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443
NGINX_EXTRA_PORT=3000
NGINX_ENABLE_IPV6=false
```

**With IPv6 support:**
```bash
NGINX_SERVER_NAME=ki.university.edu
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443
NGINX_EXTRA_PORT=
NGINX_ENABLE_IPV6=true
```

---

## 📝 Best Practices

1. **Production (Official)**: Use `deploy-prod.sh` with HAWK-provided image
2. **Staging (Custom)**: Use `deploy-staging.sh` after thorough testing
3. **Development**: Use `deploy-dev.sh` for fast iterations
4. **Testing Flow**: `deploy-dev.sh` → develop → `deploy-staging.sh` → test → `deploy-prod.sh` (production)
5. **Git Info**: Commit ID is automatically stored in `storage/app/git_info.json`
6. **Backups**: Always backup `.env` and `storage/` before deploying

---

## 🆘 Support

For issues or questions:
- Check logs: `docker compose -f docker-compose.prod.yml logs -f app`
- Inspect containers: `docker compose -f docker-compose.prod.yml ps`
- Access container: `docker compose -f docker-compose.prod.yml exec app bash`
