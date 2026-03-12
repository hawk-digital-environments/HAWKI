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

# Allow the build process to inject a custom cache buster
ARG CACHE_BUSTER=default
ENV APP_CACHE_BUSTER=${CACHE_BUSTER}

COPY --chown=www-data:www-data docker/app /container/custom

# Install the composer dependencies, without running any scripts, this allows us to install the dependencies
# in a single layer and caching them even if the source files are changed
COPY --chown=www-data:www-data ./composer.json ./composer.json
COPY --chown=www-data:www-data ./composer.lock ./composer.lock

# Install the composer dependencies, without running any scripts, this allows us to install the dependencies
# in a single layer and caching them even if the source files are changed
# However, since the "laravel-backup" package is not really well suited for a docker container environment,
# we drop it directly after installing the dependencies. This way we can keep it in the "composer.json" for on-host
# installations, but it won't be included in the final image.
RUN composer install --no-dev --no-cache --no-progress --no-interaction --verbose --no-autoloader \
    && composer remove --no-dev --no-cache --no-progress --no-interaction --verbose --no-autoloader \
        spatie/laravel-backup \
    && composer clear-cache

# Add the app sources
COPY --chown=www-data:www-data . .
COPY --from=node_builder --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build

# Dump the autoload file and run the matching scripts, after all the project files are in the image
# Laravel commands require some directories to be writeable by the web server user, so we need to create them and set the permissions before running the composer scripts
# however we remove them directly afterwards, to not have them in the final image.
RUN mkdir -p /var/www/html/storage/framework/cache && chown www-data:www-data /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions && chown www-data:www-data /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views && chown www-data:www-data /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs && chown www-data:www-data /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/app/public && chown www-data:www-data /var/www/html/storage/app/public \
    && composer dump-autoload --no-dev --optimize --no-interaction --verbose --no-cache \
    && rm -rf /var/www/html/storage \
    && mkdir -p /var/www/html/storage \
    && chown www-data:www-data /var/www/html/storage

# Copy our container overrides
COPY docker/app/errors.nginx.conf /container/templates/nginx/snippets/errors.nginx.conf
