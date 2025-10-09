# HAWKI Environment Configuration

This directory contains the environment configuration files for HAWKI deployments.

## Active Files

| File | Purpose | Version Control |
|------|---------|----------------|
| `.env` | **Generated** - Active configuration used by containers | ❌ Ignored |
| `.env.example` | **Template** - Base configuration template | ✅ Committed |
| `.env.dev` | **Profile** - Development-specific overrides | ✅ Committed |
| `.env.staging` | **Profile** - Staging-specific overrides | ✅ Committed |
| `.env.prod` | **Profile** - Production-specific overrides | ✅ Committed |
| `env-init.sh` | **Script** - Environment initialization script | ✅ Committed |

## Quick Start

### Initialize Environment

```bash
# For development
./env-init.sh --profile=dev

# For staging
./env-init.sh --profile=staging

# For production
./env-init.sh --profile=prod

# Force regeneration (overwrites existing .env)
./env-init.sh --profile=staging --force
```

### How Configuration Merging Works

```
┌─────────────────────┐
│  .env.example       │  Base template with all keys
│  (Common defaults)  │
└──────────┬──────────┘
           │
           │ merged with
           ↓
┌─────────────────────┐
│  .env.{profile}     │  Profile-specific overrides
│  (dev/staging/prod) │
└──────────┬──────────┘
           │
           │ generates
           ↓
┌─────────────────────┐
│  .env               │  Final configuration
│  (Used by Docker)   │
└─────────────────────┘
```

## Configuration Structure

### Base Template (`.env.example`)
Contains all configuration keys with sensible defaults:
```bash
APP_NAME="HAWKI"
APP_ENV=production
APP_DEBUG=false
# ... more common settings
```

### Profile Overrides (`.env.{profile}`)
Only contains settings that differ from base:

**Dev** (`.env.dev`):
```bash
APP_ENV=local
APP_DEBUG=true
APP_URL=https://app.hawki.dev
```

**Staging** (`.env.staging`):
```bash
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://staging.hawki.example.com
```

**Production** (`.env.prod`):
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hawki.example.com
```

## Automatic Initialization

The deploy scripts (`deploy-dev.sh`, `deploy-staging.sh`, `deploy-prod.sh`) automatically call `env-init.sh` if:
- `.env` doesn't exist
- `--init` flag is provided

Example:
```bash
cd ../_docker_production
./deploy-staging.sh --init
```

## Adding New Configuration Keys

1. Add the key to `.env.example` with a default value
2. Add profile-specific values to `.env.{profile}` files if they differ
3. Run `env-init.sh --force` to regenerate `.env`

Example:
```bash
# Edit .env.example
echo "NEW_FEATURE_ENABLED=false" >> .env.example

# Override for dev
echo "NEW_FEATURE_ENABLED=true" >> .env.dev

# Regenerate
./env-init.sh --profile=dev --force
```

## Security Notes

⚠️ **Important:**
- `.env` contains generated secrets (APP_KEY, ENCRYPTION_KEY)
- Never commit `.env` to version control
- Always use `env-init.sh` to generate keys automatically
- Keys are unique per deployment

## Troubleshooting

### Problem: Configuration changes not reflected

**Solution:** Regenerate the .env file
```bash
./env-init.sh --profile=staging --force
cd ..
./deploy-staging.sh
```

### Problem: Missing configuration keys

**Solution:** Update `.env.example` and regenerate
```bash
# Add missing keys to .env.example
./env-init.sh --profile=dev --force
```

### Problem: Wrong environment values

**Check these files in order:**
1. `env/.env` - Final generated config
2. `env/.env.{profile}` - Profile overrides
3. `env/.env.example` - Base template

## See Also

- [../ENV_CONFIGURATION.md](../ENV_CONFIGURATION.md) - Complete configuration guide
- [env-init.sh](./env-init.sh) - Initialization script source
