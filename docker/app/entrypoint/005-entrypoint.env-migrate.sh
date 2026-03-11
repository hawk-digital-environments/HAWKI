echo [ENTRYPOINT] Running in migrate mode.
echo [ENTRYPOINT] Creating storage directories and setting permissions...

mkdir -p /var/www/html/storage
mkdir -p /var/www/html/storage/framework
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/testing
mkdir -p /var/www/html/storage/framework/views
chmod -R 777 /var/www/html/storage

echo [ENTRYPOINT] Running database migrations...
gosu www-data php artisan migrate --force

# In this case, the job of this container is done.
exit 0
