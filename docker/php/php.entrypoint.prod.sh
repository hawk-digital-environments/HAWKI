#!/bin/bash

mkdir -p /var/www/html/storage
mkdir -p /var/www/html/storage/framework
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/testing
mkdir -p /var/www/html/storage/framework/views
chmod -R 777 /var/www/html/storage/framework

php /var/www/prepareEnvVariables.php

php artisan config:cache
php artisan route:cache
php artisan view:cache
