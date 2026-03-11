# -----------------------------------------------------
# NODE - BUILDER
# -----------------------------------------------------
FROM neunerlei/node-nginx:25 AS node_builder

# Add the app sources
COPY --chown=www-data:www-data . .

RUN npm ci && npm run build

# =====================================================
# APP service
# =====================================================
# APP - ROOT
# -----------------------------------------------------
FROM neunerlei/php-nginx:8.5 AS app_root

LABEL org.opencontainers.image.authors="HAWKI Team <ki@hawk.de>"
LABEL org.opencontainers.image.description="The HAWKI application image"

RUN --mount=type=cache,id=apt-cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,id=apt-lib,target=/var/lib/apt,sharing=locked\
    --mount=type=bind,from=mlocati/php-extension-installer:2,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions \
        ldap

# -----------------------------------------------------
# APP - DEV
# -----------------------------------------------------
FROM app_root AS app_dev

# Install mhsendmail (Mailhog sendmail)
RUN curl --fail --silent --location --output /tmp/mhsendmail https://github.com/mailhog/mhsendmail/releases/download/v0.2.0/mhsendmail_linux_amd64 \
    && chmod +x /tmp/mhsendmail \
    && mv /tmp/mhsendmail /usr/bin/mhsendmail

# Add utilities for dev
RUN --mount=type=cache,id=apt-cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,id=apt-lib,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get upgrade -y && apt-get install -y \
        tmux \
    && pecl install xdebug-3.5.0 \
    && docker-php-ext-enable xdebug

# Install dev.command.sh
COPY --chmod=755 --chown=www-data:www-data docker/app/dev.command.sh /usr/bin/dev.command.sh

# -----------------------------------------------------
# APP - PROD
# -----------------------------------------------------
FROM app_root AS app_prod

COPY --chown=www-data:www-data docker/app /container/custom

# Install the composer dependencies, without running any scripts, this allows us to install the dependencies
# in a single layer and caching them even if the source files are changed
COPY --chown=www-data:www-data ./composer.json ./composer.json
COPY --chown=www-data:www-data ./composer.lock ./composer.lock

# Replace exec /usr/bin/_composer "$@" with "id -u && exit 1" to check the user permissions and prevent composer from running as root, which can cause permission issues with the installed dependencies
RUN composer install --no-dev --no-cache --no-progress --no-interaction --verbose --no-autoloader

# Add the app sources
COPY --chown=www-data:www-data . .
COPY --from=node_builder --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build

RUN mkdir -p /var/www/html/storage/framework/cache && chown www-data:www-data /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions && chown www-data:www-data /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views && chown www-data:www-data /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs && chown www-data:www-data /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/app/public && chown www-data:www-data /var/www/html/storage/app/public

# Dump the autoload file and run the matching scripts, after all the project files are in the image
RUN composer dump-autoload --no-dev --optimize --no-interaction --verbose --no-cache

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/health || exit 1
