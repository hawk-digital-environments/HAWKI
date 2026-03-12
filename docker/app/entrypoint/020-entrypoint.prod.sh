#!/bin/bash

gosu www-data php artisan config:cache
gosu www-data php artisan route:clear
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
