# Nginx Configuration Templates

This directory contains Nginx configuration templates for different deployment profiles.

## Template Files

- **`nginx.template.dev`** - Development environment template
  - Includes Adminer (database management) on `db.hawki.dev`
  - Used when `DEPLOY_PROFILE=dev`

- **`nginx.template.staging`** - Staging environment template
  - No Adminer service (production-like)
  - Used when `DEPLOY_PROFILE=staging`

- **`nginx.template.prod`** - Production environment template
  - No Adminer service
  - Production-optimized settings
  - Used when `DEPLOY_PROFILE=prod`

## Configuration Generation

The `generate-nginx-config.sh` script automatically selects the correct template based on the `DEPLOY_PROFILE` environment variable and generates `nginx.default.conf`.

### Usage

```bash
# Set the deployment profile
export DEPLOY_PROFILE=staging

# Generate configuration
./generate-nginx-config.sh
```

### Variables

The following environment variables can be used to customize the generated configuration:

- `NGINX_SERVER_NAME` - Server name (default: `_`)
- `NGINX_HTTP_PORT` - HTTP port (default: `80`)
- `NGINX_HTTPS_PORT` - HTTPS port (default: `443`)
- `NGINX_ENABLE_IPV6` - Enable IPv6 support (default: `false`)
- `NGINX_EXTRA_PORT` - Additional port to listen on (optional)
- `NGINX_HTTP2_CONFIG` - HTTP/2 configuration style (default: `new`)

### Template Variables

Templates use the following placeholders that are replaced during generation:

- `${NGINX_SERVER_NAME}` - Server name
- `${NGINX_HTTP_PORT}` - HTTP port
- `${NGINX_HTTPS_PORT}` - HTTPS port
- `${NGINX_LISTEN_IPV6_HTTP}` - IPv6 HTTP listen directive
- `${NGINX_LISTEN_EXTRA_PORT}` - Extra port listen directive
- `${NGINX_LISTEN_HTTPS}` - HTTPS listen directive
- `${NGINX_HTTP2_CONFIG}` - HTTP/2 configuration

## Differences Between Templates

### Development (`nginx.template.dev`)
- Includes Adminer database management interface
- Two additional server blocks for `db.hawki.dev` (HTTP redirect + HTTPS)
- Suitable for local development with database access needs

### Staging/Production (`nginx.template.staging`, `nginx.template.prod`)
- No Adminer service
- Cleaner configuration focused on application serving
- Production-ready setup

## Files

- `nginx.template.dev` - Development template (with Adminer)
- `nginx.template.staging` - Staging template (without Adminer)
- `nginx.template.prod` - Production template (without Adminer)
- `nginx.template.original` - Original template backup
- `generate-nginx-config.sh` - Configuration generation script
- `nginx.default.conf` - Generated configuration (do not edit directly)
- `nginx.adminer.conf` - Adminer-specific configuration (for dev only)

## Deployment Scripts

The deployment scripts (`deploy-dev.sh`, `deploy-staging.sh`, `deploy-prod.sh`) automatically set the correct `DEPLOY_PROFILE` before calling `generate-nginx-config.sh`.
