echo [ENTRYPOINT] Running database migrations...
gosu www-data php artisan migrate --force

# In this case, the job of this container is done.
exit 0
