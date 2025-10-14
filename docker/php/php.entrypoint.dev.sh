#!/bin/bash

composer install

# Ensure the test user exists
if ! [[ -f /var/www/html/storage/app/test_users.json ]]; then
    touch /var/www/html/storage/app/test_users.json
    echo '[
              {
                  "username": "tester",
                  "password": "tester",
                  "name": "TheTester",
                  "email": "tester@hawk.de",
                  "employeetype": "Tester",
                  "avatar_id": ""
              }
          ]' > /var/www/html/storage/app/test_users.json
    chown www-data:www-data /var/www/html/storage/app/test_users.json
fi

php artisan migrate
php artisan db:seed
