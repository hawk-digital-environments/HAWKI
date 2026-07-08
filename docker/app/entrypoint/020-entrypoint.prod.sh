#!/bin/bash

gosu www-data mkdir -p /var/www/html/storage/framework/cache
gosu www-data mkdir -p /var/www/html/storage/framework/sessions
gosu www-data mkdir -p /var/www/html/storage/framework/views
gosu www-data mkdir -p /var/www/html/storage/logs
gosu www-data mkdir -p /var/www/html/storage/app/public

gosu www-data php artisan config:cache
gosu www-data php artisan route:clear
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
