#!/bin/bash

# Derive environment variables from APP_PROTOCOL AND APP_HOST
export APP_URL="${APP_PROTOCOL}://${APP_HOST}"
export VITE_REVERB_HOST="${APP_HOST}"
export VITE_REVERB_SCHEME="${APP_PROTOCOL}"

gosu www-data php /var/www/html/prepareEnvVariables.php

gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
