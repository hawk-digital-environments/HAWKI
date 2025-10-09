# Docker Production Deployment

For Docker deployment instructions, see [`_docker_production/README.md`](_docker_production/README.md).

## Quick Start

# Docker Production Deployment

For detailed Docker deployment instructions, see [`_docker_production/README.md`](_docker_production/README.md).

## Quick Start

### Production with Official HAWK Image
```bash
cd _docker_production
./deploy-prod.sh  # Uses pre-built HAWK-provided image
```

### Staging/Test with Custom Modifications
```bash
cd _docker_production
./deploy-staging.sh  # Builds from current repository
```

### Active Development (Live Code)
```bash
cd _docker_production
./deploy-dev.sh --build  # Initial setup

# Quick updates during development
git pull
cd _docker_production
./update-dev.sh  # Changes live in ~10 seconds
```

## Deployment Strategy

| Script | Use Case | Code Source | Update Time |
|--------|----------|-------------|-------------|
| **deploy-prod.sh** | Production (Official HAWK) | HAWK Registry | Fast (no build) |
| **deploy-staging.sh** | Staging/Test (Custom) | Built from Repo | ~10 min (rebuild) |
| **deploy-dev.sh** | Active Development | Live Volume | ~10 sec (no rebuild) |

## File Structure

- **`Dockerfile`** - Multi-stage build for production (must stay in root for build context)
- **`_docker_production/`** - All Docker deployment configs and scripts
- **Local Development** - Uses Laravel HERD (no Docker needed)

## File Structure

- **`Dockerfile`** - Multi-stage build for production (must stay in root for build context)
- **`_docker_production/`** - All Docker deployment configs and scripts
- **Local Development** - Uses Laravel HERD (no Docker needed)
